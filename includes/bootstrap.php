<?php
// includes/bootstrap.php
// รวมไว้บนสุดของทุกไฟล์ API: ตั้งค่า CORS, และกำหนด header เป็น JSON เสมอ

// ===== ตัวดักจับ error กลาง =====
// ป้องกันไม่ให้ exception/fatal error ที่ endpoint ไหนลืมครอบ try/catch หลุดออกไปเป็น
// หน้า HTML ของ PHP (ซึ่งอาจมีชื่อไฟล์/query/โครงสร้างตารางฐานข้อมูลปนออกไปด้วย)
// รายละเอียดจริงถูก error_log ไว้ฝั่งเซิร์ฟเวอร์เท่านั้น ฝั่ง client ได้แค่ข้อความกลางๆ
//
// ต้องปิด display_errors ด้วย ไม่งั้น PHP จะพิมพ์ banner "Fatal error: ..." (มี path/บรรทัดจริง)
// ออกไปทาง output ก่อนที่ shutdown function ของเราจะได้ทำงานเสียอีก แล้วเราจะได้ HTML ปนกับ JSON
// ที่ต่อท้ายมา ไม่ใช่ JSON ล้วนตามที่ต้องการ ส่วน log_errors ยังเปิดไว้ให้ PHP เขียนลง error log เอง
// เป็น fallback อีกชั้น ถ้าเรายังพลาดจับ error บางแบบไม่ครบ
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// กันบัฟเฟอร์ output ที่อาจหลุดออกมาก่อน error เกิด (เช่น whitespace, notice ที่ยังไม่ได้ suppress)
// ให้เคลียร์ทิ้งได้ก่อนตอบ JSON สะอาดๆ กลับไป
ob_start();

$GLOBALS['__api_error_responded'] = false;

function handle_uncaught_error($log_message) {
    global $pdo;

    if ($GLOBALS['__api_error_responded']) {
        return; // กันตอบซ้ำ ถ้า shutdown function ทำงานต่อหลัง exception handler ตอบไปแล้ว
    }
    $GLOBALS['__api_error_responded'] = true;

    // ถ้ามี transaction ค้างอยู่ตอน error เกิด ต้อง rollback ก่อนเสมอ ไม่งั้นจะค้างจนกว่า connection จะปิดเอง
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log($log_message);

    // เคลียร์ output ที่อาจค้างอยู่ในบัฟเฟอร์ทิ้งก่อนเสมอ (เช่น HTML fatal error banner ของ PHP เอง)
    // เพื่อให้ client เห็นแค่ JSON ที่เราตั้งใจตอบเท่านั้น ไม่มี HTML ปนออกไป
    if (ob_get_level() > 0) {
        ob_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['error' => 'เกิดข้อผิดพลาดที่ไม่คาดคิด กรุณาลองใหม่อีกครั้ง'], JSON_UNESCAPED_UNICODE);
}

set_exception_handler(function (Throwable $e) {
    handle_uncaught_error(
        'Uncaught exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()
    );
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        handle_uncaught_error('Fatal error: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
    }
});

require_once __DIR__ . '/auth_token.php';

// ===== รายชื่อโดเมนที่อนุญาตให้เรียก API นี้ได้ =====
// ตอนพัฒนา: มีแค่ localhost เท่านั้น
// ตอนมีโดเมนจริงแล้ว: เพิ่มโดเมนจริงเข้าไปในลิสต์นี้ (ไม่ต้องลบของ localhost ออกก็ได้ เผื่อยังต้องทดสอบในเครื่องต่อ)
$allowed_origins = [
    'http://localhost:5173',
    'http://127.0.0.1:5173',
    'https://hospital-frontend-gray-one.vercel.app',
    // 'https://yourdomain.com',   // <-- เอาเครื่องหมาย // ออกแล้วใส่โดเมนจริงตรงนี้ตอนมีข้อมูลแล้ว
    // 'https://www.yourdomain.com',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
}
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
// ต้องอนุญาต Authorization header ด้วย ไม่งั้น browser preflight (OPTIONS) จะบล็อก
// ก่อนคำขอจริงจะถูกส่งไปถึง เพราะ frontend แนบ "Authorization: Bearer <token>" มาทุกครั้ง
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// เบราว์เซอร์จะยิง OPTIONS มาก่อนเวลามี custom header/credentials (preflight request) - ตอบรับแล้วจบเลย
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// helper: อ่าน JSON body ที่ React ส่งมา (fetch ส่งเป็น JSON ไม่ใช่ form-urlencoded แบบเดิม)
function get_json_body() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

// helper: ตอบกลับเป็น JSON แล้วจบการทำงานทันที
function json_response($data, $status_code = 200) {
    http_response_code($status_code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// helper: ตรวจความยาวข้อความก่อน insert/update ลงคอลัมน์ที่จำกัดความยาว (เช่น varchar)
// ต้องใช้ mb_strlen ไม่ใช่ strlen เพราะ 1 ตัวอักษรไทยกิน 3 ไบต์ใน UTF-8 นับด้วย strlen แล้วจะได้ค่าเกินจริง
// ถ้าเกินจะตอบ 400 พร้อมบอกจำนวนตัวอักษรที่กรอกได้จริง แล้วจบ request ทันที (เพื่อกัน MySQL ตัดข้อความทิ้งเงียบๆ
// ตอน insert เมื่อ sql_mode ไม่ได้เปิด STRICT_TRANS_TABLES)
function validate_max_length($value, $max, $field_label) {
    if (mb_strlen($value) > $max) {
        json_response(['error' => "$field_label ยาวเกินไป กรอกได้ไม่เกิน $max ตัวอักษร"], 400);
    }
}

// helper: เช็คว่า login อยู่ไหม (ผ่าน token ใน Authorization header) และ role ตรงที่ต้องการไหม
// ใช้ token-based auth แทน PHP session cookie เพราะ cookie ข้ามโดเมนถูกมือถือบล็อก (ดู includes/auth_token.php)
function require_login($required_role = null) {
    global $pdo;

    $session = get_authenticated_session($pdo);
    if (!$session) {
        json_response(['error' => 'ยังไม่ได้เข้าสู่ระบบ หรือเซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่'], 401);
    }
    if ($required_role !== null && $session['role'] !== $required_role) {
        json_response(['error' => 'ไม่มีสิทธิ์เข้าถึง'], 403);
    }

    // เก็บไว้ใน $_SESSION (แค่ตัวแปรในหน่วยความจำของ request นี้ ไม่ผูกกับ cookie ใดๆ)
    // เพื่อให้โค้ดเดิมที่อ่าน $_SESSION['user_id']/['role']/['username'] ทำงานต่อได้โดยไม่ต้องแก้ทุกไฟล์
    $_SESSION['user_id'] = $session['user_id'];
    $_SESSION['role'] = $session['role'];
    $_SESSION['username'] = $session['username'];
}
