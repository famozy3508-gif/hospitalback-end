<?php
// api/student/appointments.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

$stmt = $pdo->prepare("SELECT * FROM tb_appointments WHERE student_id = ? ORDER BY appointment_datetime DESC");
$stmt->execute([$_SESSION['user_id']]);
json_response($stmt->fetchAll());
