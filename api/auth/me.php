<?php
// api/auth/me.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';

$session = get_authenticated_session($pdo);

if ($session) {
    json_response([
        'logged_in' => true,
        'user_id' => $session['user_id'],
        'username' => $session['username'],
        'role' => $session['role'],
    ]);
} else {
    json_response(['logged_in' => false]);
}
