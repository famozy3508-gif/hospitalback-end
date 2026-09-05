<?php
// api/student/delete_account.php  (POST)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = get_json_body();
$password = $body['password'] ?? '';
$user_id = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT password FROM tb_users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    json_response(['error' => 'รหัสผ่านไม่ถูกต้อง กรุณาลองใหม่'], 401);
}

$pdo->beginTransaction();
$pdo->prepare("DELETE FROM tb_visits WHERE student_id = ?")->execute([$user_id]);
$pdo->prepare("DELETE FROM tb_appointments WHERE student_id = ?")->execute([$user_id]);
$pdo->prepare("DELETE FROM tb_allergies WHERE user_id = ?")->execute([$user_id]);
$pdo->prepare("DELETE FROM tb_notifications WHERE student_id = ?")->execute([$user_id]);
$pdo->prepare("DELETE FROM tb_login_logs WHERE user_id = ?")->execute([$user_id]);
$pdo->prepare("DELETE FROM tb_student_profile WHERE user_id = ?")->execute([$user_id]);
$pdo->prepare("DELETE FROM tb_users WHERE user_id = ?")->execute([$user_id]);
$pdo->commit();

// ไม่ต้องลบ tb_sessions เอง - มี ON DELETE CASCADE จาก tb_users อยู่แล้ว token ของ user นี้เลยหมดอายุไปพร้อมกัน
json_response(['success' => true]);
