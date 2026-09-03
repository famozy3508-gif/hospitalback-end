<?php
// api/admin/upload_avatar.php  (POST multipart/form-data, field name: avatar)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
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

$ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$ext = $ext_map[$mime_type];
$filename = 'avatar_' . uniqid() . '.' . $ext;

$upload_dir = __DIR__ . '/../../uploads/avatars/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
    json_response(['error' => 'อัปโหลดไฟล์ไม่สำเร็จ กรุณาลองใหม่'], 500);
}

json_response(['success' => true, 'filename' => $filename]);