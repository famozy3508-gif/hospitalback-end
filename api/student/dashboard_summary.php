<?php
// api/student/dashboard_summary.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT u.username, u.avatar, p.first_name, p.last_name, p.nickname, p.student_code FROM tb_users u 
    LEFT JOIN tb_student_profile p ON u.user_id = p.user_id WHERE u.user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch();

$stmt2 = $pdo->prepare("SELECT * FROM tb_appointments WHERE student_id = ? AND status = 'pending' 
    ORDER BY appointment_datetime ASC LIMIT 1");
$stmt2->execute([$user_id]);
$next_appointment = $stmt2->fetch();

$stmt3 = $pdo->prepare("SELECT COUNT(*) FROM tb_notifications WHERE student_id = ? AND is_read = 0");
$stmt3->execute([$user_id]);
$unread_count = (int)$stmt3->fetchColumn();

json_response([
    'name' => $profile['first_name'] ?: $profile['username'],
    'first_name' => $profile['first_name'] ?: '',
    'last_name' => $profile['last_name'] ?: '',
    'nickname' => $profile['nickname'] ?: '',
    'student_code' => $profile['student_code'] ?: '',
    'avatar' => $profile['avatar'] ?: '',
    'next_appointment' => $next_appointment ?: null,
    'unread_count' => $unread_count,
]);