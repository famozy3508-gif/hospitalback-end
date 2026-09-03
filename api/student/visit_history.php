<?php
// api/student/visit_history.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

$stmt = $pdo->prepare("SELECT * FROM tb_visits WHERE student_id = ? ORDER BY visit_datetime DESC");
$stmt->execute([$_SESSION['user_id']]);
json_response($stmt->fetchAll());
