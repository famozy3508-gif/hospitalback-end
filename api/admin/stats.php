<?php
// api/admin/stats.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('nurse');

$total_students = (int)$pdo->query("SELECT COUNT(*) FROM tb_users WHERE role = 'student'")->fetchColumn();
$visits_this_month = (int)$pdo->query("SELECT COUNT(*) FROM tb_visits 
    WHERE MONTH(visit_datetime) = MONTH(CURDATE()) AND YEAR(visit_datetime) = YEAR(CURDATE())")->fetchColumn();
$pending_appointments = (int)$pdo->query("SELECT COUNT(*) FROM tb_appointments WHERE status = 'pending'")->fetchColumn();

$top_symptoms = $pdo->query("SELECT symptoms, COUNT(*) as count FROM tb_visits 
    WHERE symptoms IS NOT NULL AND symptoms != '' 
    GROUP BY symptoms ORDER BY count DESC LIMIT 5")->fetchAll();

$severity_stmt = $pdo->query("SELECT severity, COUNT(*) as count FROM tb_allergies GROUP BY severity");
$severity_raw = $severity_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$severity_labels = ['mild' => 'เล็กน้อย', 'moderate' => 'ปานกลาง', 'severe' => 'รุนแรง'];
$allergy_severity = [];
foreach ($severity_labels as $key => $label) {
    $allergy_severity[] = ['label' => $label, 'count' => (int)($severity_raw[$key] ?? 0)];
}

$monthly_visits = [];
$thai_months_short = [1=>'ม.ค.',2=>'ก.พ.',3=>'มี.ค.',4=>'เม.ย.',5=>'พ.ค.',6=>'มิ.ย.',
                       7=>'ก.ค.',8=>'ส.ค.',9=>'ก.ย.',10=>'ต.ค.',11=>'พ.ย.',12=>'ธ.ค.'];
for ($i = 5; $i >= 0; $i--) {
    $target = strtotime("-$i months");
    $m = (int)date('n', $target);
    $y = (int)date('Y', $target);
    $stmt_month = $pdo->prepare("SELECT COUNT(*) FROM tb_visits WHERE MONTH(visit_datetime) = ? AND YEAR(visit_datetime) = ?");
    $stmt_month->execute([$m, $y]);
    $monthly_visits[] = ['month' => $thai_months_short[$m], 'count' => (int)$stmt_month->fetchColumn()];
}

json_response([
    'total_students' => $total_students,
    'visits_this_month' => $visits_this_month,
    'pending_appointments' => $pending_appointments,
    'top_symptoms' => $top_symptoms,
    'allergy_severity' => $allergy_severity,
    'monthly_visits' => $monthly_visits,
]);
