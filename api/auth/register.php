<?php
// api/auth/register.php  (POST)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = get_json_body();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';
$confirm_password = $body['confirm_password'] ?? '';
$email = trim($body['email'] ?? '');
$student_code = trim($body['student_code'] ?? '');
$first_name = trim($body['first_name'] ?? '');
$last_name = trim($body['last_name'] ?? '');

if (empty($username) || empty($password) || empty($email) || empty($student_code)) {
    json_response(['error' => 'กรุณากรอกข้อมูลให้ครบทุกช่อง'], 400);
}
if (!preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)) {
    json_response(['error' => 'ชื่อผู้ใช้ต้องเป็นตัวอักษรภาษาอังกฤษ ตัวเลข หรือ . _ - เท่านั้น (3-50 ตัวอักษร)'], 400);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['error' => 'รูปแบบอีเมลไม่ถูกต้อง'], 400);
}
if ($password !== $confirm_password) {
    json_response(['error' => 'รหัสผ่านทั้งสองช่องไม่ตรงกัน'], 400);
}
if (strlen($password) < 5) {
    json_response(['error' => 'รหัสผ่านต้องมีอย่างน้อย 5 ตัวอักษร'], 400);
}

$stmt = $pdo->prepare("SELECT user_id FROM tb_users WHERE username = ?");
$stmt->execute([$username]);
if ($stmt->fetch()) {
    json_response(['error' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาเลือกชื่ออื่น'], 409);
}

// เช็คซ้ำที่ backend อีกชั้นเสมอ แม้ฝั่ง frontend จะเช็คผ่าน check_available.php ไปแล้วตอน onBlur
// เพราะอาจมีคนอื่นสมัครชื่อ/อีเมล/รหัสนักศึกษาเดียวกันแทรกเข้ามาระหว่างที่ผู้ใช้กรอกฟอร์มอยู่
$stmt = $pdo->prepare("SELECT user_id FROM tb_users WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->fetch()) {
    json_response(['error' => 'อีเมลนี้ถูกใช้งานแล้ว'], 409);
}

$stmt = $pdo->prepare("SELECT profile_id FROM tb_student_profile WHERE student_code = ?");
$stmt->execute([$student_code]);
if ($stmt->fetch()) {
    json_response(['error' => 'รหัสนักศึกษานี้มีอยู่ในระบบแล้ว'], 409);
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("INSERT INTO tb_users (username, password, role, email) VALUES (?, ?, 'student', ?)");
    $stmt->execute([$username, $hashed_password, $email]);
    $new_user_id = $pdo->lastInsertId();

    $stmt2 = $pdo->prepare("INSERT INTO tb_student_profile (user_id, student_code, first_name, last_name) VALUES (?, ?, ?, ?)");
    $stmt2->execute([$new_user_id, $student_code, $first_name, $last_name]);

    $pdo->commit();
    json_response(['success' => true, 'message' => 'สมัครสมาชิกสำเร็จ! กรุณาเข้าสู่ระบบ']);
} catch (Exception $e) {
    $pdo->rollBack();
    json_response(['error' => 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง'], 500);
}
