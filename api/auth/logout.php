<?php
// api/auth/logout.php  (POST)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';

if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE tb_login_logs SET logout_time = NOW() 
        WHERE user_id = ? AND logout_time IS NULL ORDER BY log_id DESC LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
}

$_SESSION = [];
session_destroy();

json_response(['success' => true]);
