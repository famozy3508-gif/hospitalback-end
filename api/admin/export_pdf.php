<?php
// api/admin/export_pdf.php
// หมายเหตุ: endpoint นี้เปิดผ่าน <a href="..."> ตรงๆ (ไม่ใช้ fetch) เพื่อให้เบราว์เซอร์ดาวน์โหลดไฟล์ได้เลย
// จึงไม่ใช้ bootstrap.php (ที่ตั้ง JSON header) แต่ดึงแค่ auth_token.php มาเช็ค token แทน
// ลิงก์ <a href> แนบ Authorization header เองไม่ได้ จึง fallback รับ token ผ่าน query string ?token=... แทน (ดู auth_token.php)
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/auth_token.php';
require_once __DIR__ . '/../../vendor/autoload.php';

$session = get_authenticated_session($pdo);
if (!$session || $session['role'] !== 'nurse') {
    http_response_code(403);
    die('ไม่มีสิทธิ์เข้าถึง กรุณาเข้าสู่ระบบด้วยบัญชีพยาบาล');
}
$_SESSION['user_id'] = $session['user_id'];
$_SESSION['username'] = $session['username'];

if (!isset($_GET['student_id'])) {
    die("กรุณาระบุนักเรียนที่ต้องการส่งออกข้อมูล");
}
$student_id = (int)$_GET['student_id'];

$stmt = $pdo->prepare("SELECT u.email, p.* FROM tb_users u 
    LEFT JOIN tb_student_profile p ON u.user_id = p.user_id WHERE u.user_id = ?");
$stmt->execute([$student_id]);
$profile = $stmt->fetch();

if (!$profile) {
    die("ไม่พบข้อมูลนักเรียนคนนี้");
}

$stmt2 = $pdo->prepare("SELECT * FROM tb_allergies WHERE user_id = ? ORDER BY created_at DESC");
$stmt2->execute([$student_id]);
$allergies = $stmt2->fetchAll();

$stmt3 = $pdo->prepare("SELECT * FROM tb_visits WHERE student_id = ? ORDER BY visit_datetime DESC");
$stmt3->execute([$student_id]);
$visits = $stmt3->fetchAll();

function severity_label($s) {
    $labels = ['mild' => 'เล็กน้อย', 'moderate' => 'ปานกลาง', 'severe' => 'รุนแรง'];
    return $labels[$s] ?? $s;
}

// mPDF ตัดขึ้นบรรทัดใหม่ได้เฉพาะที่ "ช่องว่าง" เท่านั้น แต่ภาษาไทยเขียนติดกันไม่มีช่องว่างระหว่างคำ ข้อความยาวๆ
// ในคอลัมน์แคบ (เช่น อาการ/วินิจฉัย/ชื่อยาที่แพ้) จึงกลายเป็น "คำเดียว" ที่ยาวเกินความกว้างคอลัมน์
//
// ลองมาแล้ว 2 วิธีก่อนจะมาลงเอยที่ "เลิกใช้ <table>":
// 1) แทรก U+200B (zero-width space) ระหว่างตัวอักษร - Sarabun-Regular.ttf ไม่มี glyph ของ U+200B (เช็คด้วย
//    fontTools: ไม่อยู่ใน cmap) mPDF เลยวาดเป็นกล่องสี่เหลี่ยม (.notdef) แทรกอยู่ทุกตัวอักษร อ่านไม่ได้เลย
// 2) เปลี่ยนไปแทรก U+00AD (soft hyphen) แทน - Sarabun-Regular.ttf มี glyph จริง (uni00AD) และ mPDF มีกลไก
//    จัดการ soft hyphen เป็นของตัวเอง (ดู "Break at Soft HYPHEN" ใน Mpdf.php) วิธีนี้ใช้ได้กับข้อความทั่วไป
//    (เช่นในย่อหน้า <p> ธรรมดา) แต่พอทดสอบด้วยข้อความยาวติดกัน 255 ตัวอักษรไม่มีช่องว่างเลยภายใน <td> ของตาราง
//    พบว่าฟอนต์ยังถูกบีบเหลือ ~3-4pt เหมือนเดิม แม้จะตั้ง shrink_tables_to_fit=1 และ CSS hyphens:manual ไว้แล้ว
//    (ลองทั้งใส่ที่ td/th และที่ table เอง ก็ไม่มีผล) สรุปว่าเป็นข้อจำกัดของอัลกอริทึมคำนวณความสูงแถวของตาราง
//    ใน mPDF เอง (ดูเหมือนจะประเมินความสูงแถวจากสมมติฐานว่าตัดบรรทัดไม่ได้ แล้วบีบฟอนต์ให้พอดีตามนั้น) ไม่ใช่
//    ปัญหาที่ตัวอักษรที่แทรกเข้าไปอีกต่อไป ยืนยันด้วยการทดสอบแยก: ข้อความชุดเดียวกันเป๊ะ พอเอาออกจาก <table>
//    ไปไว้ใน <div>/<p> ธรรมดา ฟอนต์กลับปกติทันที (10.5-14pt ไม่บีบเลย) ทั้งที่เป็น mPDF ตัวเดียวกัน
//
// เปลี่ยนทั้งประวัติแพ้ยาและประวัติการเข้ารับบริการจากตาราง (<table>) เป็นการ์ดแบบรายการ (<div class="record">)
// แทน ตารางแบบเดิมจะยังชนบั๊กนี้ได้เสมอถ้ามีข้อความยาวไม่มีช่องว่างพอ ส่วนการ์ดกว้างเต็มหน้าเสมอ ไม่มีคอลัมน์ให้บีบ
// ยังคงแทรก soft hyphen (U+00AD) ไว้เหมือนเดิมเพื่อให้ขึ้นบรรทัดใหม่ได้แม้ไม่มีช่องว่างเลยทั้งข้อความ
// "ห้าม" แทรกคั่นกลางระหว่างพยัญชนะกับสระ/วรรณยุกต์ลอย (combining mark) ที่ประกบอยู่ด้านบน/ล่างของมัน
// (เช่น ไม้เอก-โท-ตรี-จัตวา, สระอิ-อี-อึ-อือ-อุ-อู) ไม่งั้นจะแสดงผลเพี้ยน/ลอยหลุดออกมา
// ใช้กับฟิลด์ข้อความอิสระที่ผู้ใช้พิมพ์เองเท่านั้น (ไม่ใช้กับวันที่/ตัวเลข/ป้ายกำกับสั้นๆ)
function pdf_wrap_thai($text) {
    $combining_marks = "\u{0E31}\u{0E34}\u{0E35}\u{0E36}\u{0E37}\u{0E38}\u{0E39}\u{0E3A}\u{0E47}\u{0E48}\u{0E49}\u{0E4A}\u{0E4B}\u{0E4C}\u{0E4D}\u{0E4E}";
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $out = '';
    $n = count($chars);
    for ($i = 0; $i < $n; $i++) {
        $out .= $chars[$i];
        $next_is_combining = $i < $n - 1 && mb_strpos($combining_marks, $chars[$i + 1]) !== false;
        if ($i < $n - 1 && !$next_is_combining) {
            $out .= "\xC2\xAD";
        }
    }
    return $out;
}

$html = '
<style>
    body { font-family: "sarabun", sans-serif; font-size: 16px; hyphens: manual; }
    h1 { text-align: center; color: #2c7a7b; font-size: 22px; }
    .info-line { margin: 4px 0; }
    .record {
        border: 1px solid #999; border-radius: 4px; padding: 8px 10px; margin-bottom: 8px;
        font-size: 14px; hyphens: manual;
    }
    .record p { margin: 3px 0; }
    .record .label { font-weight: bold; color: #2c7a7b; }
    .record .meta { color: #666; }
</style>

<h1>ระบบห้องพยาบาล - ใบสรุปประวัติการรักษา</h1>

<p class="info-line"><strong>ชื่อ-สกุล:</strong> ' . htmlspecialchars(($profile['first_name'] ?? '-') . ' ' . ($profile['last_name'] ?? '')) . '</p>
<p class="info-line"><strong>รหัสนักเรียน:</strong> ' . htmlspecialchars($profile['student_code'] ?? '-') . '</p>
<p class="info-line"><strong>ระดับชั้น/สาขา:</strong> ' . htmlspecialchars(($profile['education_level'] ?? '-') . ' ' . ($profile['department'] ?? '')) . '</p>
<p class="info-line"><strong>กรุ๊ปเลือด:</strong> ' . htmlspecialchars($profile['blood_type'] ?: '-') . '
&nbsp;&nbsp;<strong>โรคประจำตัว:</strong> ' . pdf_wrap_thai(htmlspecialchars($profile['chronic_disease'] ?: 'ไม่มี')) . '</p>

<h3>ประวัติแพ้ยา</h3>';

if (count($allergies) === 0) {
    $html .= '<p>ไม่มีข้อมูลการแพ้ยา</p>';
} else {
    foreach ($allergies as $a) {
        $html .= '<div class="record">
            <p class="meta">ความรุนแรง: ' . severity_label($a['severity']) . '</p>
            <p><span class="label">ชื่อยา/สาร:</span> ' . pdf_wrap_thai(htmlspecialchars($a['allergy_name'])) . '</p>
            <p><span class="label">อาการ:</span> ' . pdf_wrap_thai(htmlspecialchars($a['reaction'])) . '</p>
        </div>';
    }
}

$html .= '<h3>ประวัติการเข้ารับบริการ</h3>';

if (count($visits) === 0) {
    $html .= '<p>ยังไม่มีประวัติการรักษา</p>';
} else {
    foreach ($visits as $v) {
        $html .= '<div class="record">
            <p class="meta">วันที่: ' . date('d/m/Y H:i', strtotime($v['visit_datetime'])) . ' น.</p>
            <p><span class="label">อาการ:</span> ' . pdf_wrap_thai(htmlspecialchars($v['symptoms'])) . '</p>
            <p><span class="label">วินิจฉัย:</span> ' . pdf_wrap_thai(htmlspecialchars($v['diagnosis'])) . '</p>
            <p><span class="label">ยาที่ได้รับ:</span> ' . pdf_wrap_thai(htmlspecialchars($v['medicine_given'])) . '</p>
        </div>';
    }
}

$stmt_nurse = $pdo->prepare("SELECT first_name, last_name FROM tb_users WHERE user_id = ?");
$stmt_nurse->execute([$_SESSION['user_id']]);
$nurse_info = $stmt_nurse->fetch();
$nurse_name = trim(($nurse_info['first_name'] ?? '') . ' ' . ($nurse_info['last_name'] ?? '')) ?: $_SESSION['username'];

$html .= '<p style="margin-top:30px; font-size:13px; color:#666;">
    ออกเอกสารโดย: พยาบาล ' . htmlspecialchars($nurse_name) . ' 
    &nbsp;วันที่ออกเอกสาร: ' . date('d/m/Y H:i') . ' น.
</p>';

$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];
$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

// Render ใช้ ephemeral filesystem แบบ read-only บางส่วน โฟลเดอร์ vendor/mpdf/.../tmp เขียนไม่ได้
// ใช้ sys_get_temp_dir() แทน (เช่น /tmp) ซึ่งเขียนได้เสมอ แล้วสร้างโฟลเดอร์ย่อยไว้ให้ mPDF ใช้เอง
$mpdfTempDir = sys_get_temp_dir() . '/mpdf';
if (!is_dir($mpdfTempDir)) {
    mkdir($mpdfTempDir, 0755, true);
}

$mpdf = new \Mpdf\Mpdf([
    'tempDir' => $mpdfTempDir,
    'fontDir' => array_merge($fontDirs, [__DIR__ . '/../../fonts']),
    'fontdata' => $fontData + ['sarabun' => ['R' => 'Sarabun-Regular.ttf', 'B' => 'Sarabun-Bold.ttf']],
    'default_font' => 'sarabun',
    // ปิดการบีบฟอนต์อัตโนมัติของ mPDF (ค่า default คือ 1.4 = ยอมบีบฟอนต์ในตารางลงได้ถึง ~71%
    // เพื่อให้ตารางกว้างพอดีหน้า) เพราะทำให้ตัวหนังสืออ่านยากเมื่อมีข้อความยาวในคอลัมน์
    // ใช้ CSS กำหนดความกว้างคอลัมน์ + word-wrap ให้ข้อความขึ้นบรรทัดใหม่แทน ตารางจะสูงขึ้นและ
    // ขึ้นหน้าใหม่เองตามปกติของ mPDF เมื่อเนื้อหายาวเกินหน้าเดียว
    'shrink_tables_to_fit' => 1,
]);

$mpdf->WriteHTML($html);
$filename = 'ประวัติ_' . ($profile['student_code'] ?? $student_id) . '.pdf';
$mpdf->Output($filename, 'D');
