<?php
// api/student/login_history.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

$stmt = $pdo->prepare("SELECT * FROM tb_login_logs WHERE user_id = ? ORDER BY login_time DESC LIMIT 20");
$stmt->execute([$_SESSION['user_id']]);
json_response($stmt->fetchAll());
