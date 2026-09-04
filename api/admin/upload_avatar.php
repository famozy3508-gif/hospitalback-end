<?php
// api/admin/upload_avatar.php  (POST multipart/form-data, field name: avatar)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/cloudinary.php';
require_login('nurse');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    json_response(['error' => 'กรุณาเลือกไฟล์รูปภาพ'], 400);
}

$file = $_FILES['avatar'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime_type, $allowed_types)) {
    json_response(['error' => 'รองรับเฉพาะไฟล์รูปภาพ (JPG, PNG, GIF, WEBP) เท่านั้น'], 400);
}

if ($file['size'] > 2 * 1024 * 1024) {
    json_response(['error' => 'ขนาดไฟล์ต้องไม่เกิน 2MB'], 400);
}

$url = upload_avatar_to_cloudinary($file);

json_response(['success' => true, 'url' => $url]);