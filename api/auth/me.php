<?php
// api/auth/me.php  (GET)
require_once __DIR__ . '/../../includes/bootstrap.php';

if (isset($_SESSION['user_id'])) {
    json_response([
        'logged_in' => true,
        'user_id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'role' => $_SESSION['role'],
    ]);
} else {
    json_response(['logged_in' => false]);
}
