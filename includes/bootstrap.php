<?php
// includes/bootstrap.php
// รวมไว้บนสุดของทุกไฟล์ API: ตั้งค่า CORS, และกำหนด header เป็น JSON เสมอ

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
