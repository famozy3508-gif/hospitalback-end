<?php
// api/auth/check_available.php  (POST)
// เช็คว่า username / student_code / email ถูกใช้ไปแล้วหรือยัง - ไม่ต้องล็อกอินก็เรียกได้
// (ใช้ตอน onBlur ในฟอร์มสมัครสมาชิก/เพิ่มสมาชิก เพื่อเตือนผู้ใช้ก่อนกดสมัครจริง)
//
// ตอบแค่ available: true/false เท่านั้น ห้ามเปิดเผยว่าเป็นของใคร (เช่น ชื่อ, user_id)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/rate_limit.php';
require_once __DIR__ . '/../../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

// public endpoint ไม่มี auth คั่น จึงต้อง rate limit กันคนไล่เดาข้อมูลในระบบ
enforce_rate_limit($pdo, 'check_available', 20, 60);

$body = get_json_body();
$field = $body['field'] ?? '';
$value = trim($body['value'] ?? '');

$field_rules = [
    'username' => [
        'pattern' => '/^[A-Za-z0-9_.-]{3,50}$/',
        'error' => 'รูปแบบชื่อผู้ใช้ไม่ถูกต้อง',
        'message' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาเลือกชื่ออื่น',
        'sql' => 'SELECT 1 FROM tb_users WHERE username = ? LIMIT 1',
    ],
    'student_code' => [
        'pattern' => '/^[0-9]{1,20}$/',
        'error' => 'รูปแบบรหัสนักศึกษาไม่ถูกต้อง',
        'message' => 'รหัสนักศึกษานี้มีอยู่ในระบบแล้ว',
        'sql' => 'SELECT 1 FROM tb_student_profile WHERE student_code = ? LIMIT 1',
    ],
    'email' => [
        'pattern' => null, // ใช้ filter_var แทน
        'error' => 'รูปแบบอีเมลไม่ถูกต้อง',
        'message' => 'อีเมลนี้ถูกใช้งานแล้ว',
        'sql' => 'SELECT 1 FROM tb_users WHERE email = ? LIMIT 1',
    ],
];

if (!isset($field_rules[$field])) {
    json_response(['error' => 'ฟิลด์ไม่ถูกต้อง'], 400);
}

$rule = $field_rules[$field];
$valid = $field === 'email' ? filter_var($value, FILTER_VALIDATE_EMAIL) !== false : preg_match($rule['pattern'], $value) === 1;

if (!$valid) {
    json_response(['error' => $rule['error']], 400);
}

$stmt = $pdo->prepare($rule['sql']);
$stmt->execute([$value]);
$taken = (bool) $stmt->fetch();

json_response(['available' => !$taken, 'message' => $taken ? $rule['message'] : null]);
