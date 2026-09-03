<?php
// api/student/notifications.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

$user_id = $_SESSION['user_id'];

$pdo->prepare("UPDATE tb_notifications SET is_read = 1 WHERE student_id = ?")->execute([$user_id]);

$stmt = $pdo->prepare("SELECT * FROM tb_notifications WHERE student_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
json_response($stmt->fetchAll());
