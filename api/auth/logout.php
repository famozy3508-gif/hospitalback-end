<?php
// api/auth/logout.php  (POST)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';

$session = get_authenticated_session($pdo);

if ($session) {
    $stmt = $pdo->prepare("UPDATE tb_login_logs SET logout_time = NOW()
        WHERE user_id = ? AND logout_time IS NULL ORDER BY log_id DESC LIMIT 1");
    $stmt->execute([$session['user_id']]);

    $token = get_bearer_token();
    $pdo->prepare("DELETE FROM tb_sessions WHERE token = ?")->execute([$token]);
}

json_response(['success' => true]);
