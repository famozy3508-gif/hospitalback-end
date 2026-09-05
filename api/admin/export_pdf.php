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

$html = '
<style>
    body { font-family: "sarabun", sans-serif; font-size: 16px; }
    h1 { text-align: center; color: #2c7a7b; font-size: 22px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #999; padding: 6px; text-align: left; font-size: 14px; }
    th { background: #edf2f7; }
    .info-line { margin: 4px 0; }
</style>

<h1>ระบบห้องพยาบาล - ใบสรุปประวัติการรักษา</h1>

<p class="info-line"><strong>ชื่อ-สกุล:</strong> ' . htmlspecialchars(($profile['first_name'] ?? '-') . ' ' . ($profile['last_name'] ?? '')) . '</p>
<p class="info-line"><strong>รหัสนักเรียน:</strong> ' . htmlspecialchars($profile['student_code'] ?? '-') . '</p>
<p class="info-line"><strong>ระดับชั้น/สาขา:</strong> ' . htmlspecialchars(($profile['education_level'] ?? '-') . ' ' . ($profile['department'] ?? '')) . '</p>
<p class="info-line"><strong>กรุ๊ปเลือด:</strong> ' . htmlspecialchars($profile['blood_type'] ?: '-') . '
&nbsp;&nbsp;<strong>โรคประจำตัว:</strong> ' . htmlspecialchars($profile['chronic_disease'] ?: 'ไม่มี') . '</p>

<h3>ประวัติแพ้ยา</h3>';

if (count($allergies) === 0) {
    $html .= '<p>ไม่มีข้อมูลการแพ้ยา</p>';
} else {
    $html .= '<table><tr><th>ชื่อยา/สาร</th><th>อาการ</th><th>ความรุนแรง</th></tr>';
    foreach ($allergies as $a) {
        $html .= '<tr>
            <td>' . htmlspecialchars($a['allergy_name']) . '</td>
            <td>' . htmlspecialchars($a['reaction']) . '</td>
            <td>' . severity_label($a['severity']) . '</td>
        </tr>';
    }
    $html .= '</table>';
}

$html .= '<h3>ประวัติการเข้ารับบริการ</h3>';

if (count($visits) === 0) {
    $html .= '<p>ยังไม่มีประวัติการรักษา</p>';
} else {
    $html .= '<table><tr><th>วันที่</th><th>อาการ</th><th>วินิจฉัย</th><th>ยาที่ได้รับ</th></tr>';
    foreach ($visits as $v) {
        $html .= '<tr>
            <td>' . date('d/m/Y H:i', strtotime($v['visit_datetime'])) . '</td>
            <td>' . htmlspecialchars($v['symptoms']) . '</td>
            <td>' . htmlspecialchars($v['diagnosis']) . '</td>
            <td>' . htmlspecialchars($v['medicine_given']) . '</td>
        </tr>';
    }
    $html .= '</table>';
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
]);

$mpdf->WriteHTML($html);
$filename = 'ประวัติ_' . ($profile['student_code'] ?? $student_id) . '.pdf';
$mpdf->Output($filename, 'D');
