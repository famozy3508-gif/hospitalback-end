<?php
// api/admin/dashboard_summary.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('nurse');

// ข้อมูลของพยาบาล/แอดมินที่ล็อกอินอยู่ (ชื่อจริง, ตำแหน่ง, รูปโปรไฟล์)
$stmt_me = $pdo->prepare("SELECT first_name, last_name, position, avatar FROM tb_users WHERE user_id = ?");
$stmt_me->execute([$_SESSION['user_id']]);
$me = $stmt_me->fetch();
$nurse_name = trim(($me['first_name'] ?? '') . ' ' . ($me['last_name'] ?? '')) ?: $_SESSION['username'];
$nurse_position = $me['position'] ?: 'พยาบาล';
$nurse_avatar = $me['avatar'] ?: '';

$total_students = (int)$pdo->query("SELECT COUNT(*) FROM tb_users WHERE role = 'student'")->fetchColumn();
$total_appointments = (int)$pdo->query("SELECT COUNT(*) FROM tb_appointments")->fetchColumn();
$pending_appointments = (int)$pdo->query("SELECT COUNT(*) FROM tb_appointments WHERE status = 'pending'")->fetchColumn();

// ===== การกระจายตัวของโรคประจำตัว (สำหรับกราฟโดนัท) =====
$disease_rows = $pdo->query("SELECT chronic_disease, COUNT(*) as cnt FROM tb_student_profile 
    WHERE chronic_disease IS NOT NULL AND chronic_disease != '' 
    GROUP BY chronic_disease ORDER BY cnt DESC")->fetchAll();

$disease_distribution = [];
$other_count = 0;
foreach ($disease_rows as $i => $row) {
    if ($i < 5) {
        $disease_distribution[] = ['label' => $row['chronic_disease'], 'count' => (int)$row['cnt']];
    } else {
        $other_count += (int)$row['cnt'];
    }
}
if ($other_count > 0) {
    $disease_distribution[] = ['label' => 'อื่นๆ', 'count' => $other_count];
}

// ===== จำนวนคนเข้ารับบริการรายวัน (7 วันล่าสุด) สำหรับกราฟแท่ง =====
$thai_days_short = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
$daily_visits = [];
for ($i = 6; $i >= 0; $i--) {
    $target_date = date('Y-m-d', strtotime("-$i days"));
    $day_of_week = (int)date('w', strtotime($target_date));
    $stmt_day = $pdo->prepare("SELECT COUNT(*) FROM tb_visits WHERE DATE(visit_datetime) = ?");
    $stmt_day->execute([$target_date]);
    $daily_visits[] = [
        'month' => $thai_days_short[$day_of_week] . ' ' . date('d/m', strtotime($target_date)),
        'count' => (int)$stmt_day->fetchColumn()
    ];
}

// ===== จำนวนคนเข้ารับบริการ 6 เดือนล่าสุด (สำหรับกราฟแท่ง) =====
$thai_months_short = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',
                       7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
$monthly_visits = [];
for ($i = 5; $i >= 0; $i--) {
    $target = strtotime("-$i months");
    $m = (int)date('n', $target);
    $y = (int)date('Y', $target);
    $stmt_month = $pdo->prepare("SELECT COUNT(*) FROM tb_visits WHERE MONTH(visit_datetime) = ? AND YEAR(visit_datetime) = ?");
    $stmt_month->execute([$m, $y]);
    $monthly_visits[] = ['month' => $thai_months_short[$m], 'count' => (int)$stmt_month->fetchColumn()];
}

json_response([
    'nurse_name' => $nurse_name,
    'nurse_position' => $nurse_position,
    'nurse_avatar' => $nurse_avatar,
    'total_students' => $total_students,
    'total_appointments' => $total_appointments,
    'pending_appointments' => $pending_appointments,
    'disease_distribution' => $disease_distribution,
    'daily_visits' => $daily_visits,
    'monthly_visits' => $monthly_visits,
]);