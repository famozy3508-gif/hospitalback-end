<?php
// api/student/profile.php  (GET, POST)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->prepare("SELECT u.email, u.avatar, p.* FROM tb_users u 
        LEFT JOIN tb_student_profile p ON u.user_id = p.user_id WHERE u.user_id = ?");
    $stmt->execute([$user_id]);
    json_response($stmt->fetch());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = get_json_body();
    $first_name = trim($body['first_name'] ?? '');
    $last_name = trim($body['last_name'] ?? '');
    $nickname = trim($body['nickname'] ?? '');
    $phone = trim($body['phone'] ?? '');
    $email = trim($body['email'] ?? '');
    $blood_type = trim($body['blood_type'] ?? '');
    $chronic_disease = trim($body['chronic_disease'] ?? '');
    $education_level = $body['education_level'] ?? '';
    $department = $body['department'] ?? '';
    $new_password = trim($body['password'] ?? '');
    $avatar = trim($body['avatar'] ?? '');

    if (empty($first_name) || empty($last_name) || empty($phone) || empty($email)) {
        json_response(['error' => 'กรุณากรอกข้อมูลให้ครบ'], 400);
    }

    if (!empty($avatar)) {
        $pdo->prepare("UPDATE tb_users SET email = ?, avatar = ? WHERE user_id = ?")->execute([$email, $avatar, $user_id]);
    } else {
        $pdo->prepare("UPDATE tb_users SET email = ? WHERE user_id = ?")->execute([$email, $user_id]);
    }

    if (!empty($new_password)) {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE tb_users SET password = ? WHERE user_id = ?")->execute([$hashed, $user_id]);
    }

    $stmt2 = $pdo->prepare("UPDATE tb_student_profile 
        SET first_name=?, last_name=?, nickname=?, phone=?, blood_type=?, chronic_disease=?, education_level=?, department=? 
        WHERE user_id=?");
    $stmt2->execute([$first_name, $last_name, $nickname, $phone, $blood_type, $chronic_disease, $education_level, $department, $user_id]);

    json_response(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
}

json_response(['error' => 'Method not allowed'], 405);