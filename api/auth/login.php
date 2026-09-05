<?php
// api/auth/login.php  (POST)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$body = get_json_body();
$username = trim($body['username'] ?? '');
$password = $body['password'] ?? '';

if (empty($username) || empty($password)) {
    json_response(['error' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน'], 400);
}

$stmt = $pdo->prepare("SELECT * FROM tb_users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

if ($user && password_verify($password, $user['password'])) {
    if ($user['is_active'] == 0) {
        json_response(['error' => 'บัญชีนี้ถูกระงับการใช้งาน กรุณาติดต่อเจ้าหน้าที่'], 403);
    }

    // ออก token แบบสุ่ม (แทน PHP session cookie ที่ใช้ข้ามโดเมนไม่ได้บนมือถือ) เก็บลง tb_sessions
    // อายุ token 7 วัน - หมดอายุแล้วต้องล็อกอินใหม่
    $token = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
    $pdo->prepare("INSERT INTO tb_sessions (token, user_id, role, expires_at) VALUES (?, ?, ?, ?)")
        ->execute([$token, $user['user_id'], $user['role'], $expires_at]);

    $ip = $_SERVER['REMOTE_ADDR'];
    $log = $pdo->prepare("INSERT INTO tb_login_logs (user_id, ip_address, status) VALUES (?, ?, 'success')");
    $log->execute([$user['user_id'], $ip]);

    json_response([
        'success' => true,
        'token' => $token,
        'user_id' => $user['user_id'],
        'username' => $user['username'],
        'role' => $user['role'],
    ]);
} else {
    if ($user) {
        $ip = $_SERVER['REMOTE_ADDR'];
        $log = $pdo->prepare("INSERT INTO tb_login_logs (user_id, ip_address, status) VALUES (?, ?, 'failed')");
        $log->execute([$user['user_id'], $ip]);
    }
    json_response(['error' => 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'], 401);
}
