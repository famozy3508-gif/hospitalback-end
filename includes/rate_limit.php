<?php
// includes/rate_limit.php
// จำกัดจำนวนครั้งที่เรียก endpoint สาธารณะ (ไม่ต้องล็อกอิน) ต่อ IP ภายในช่วงเวลาหนึ่ง
// กันคนเอาไปไล่เดาว่ามี username/email/รหัสนักศึกษาไหนอยู่ในระบบบ้าง
// เก็บสถานะไว้ในตาราง tb_rate_limits (ดู sql/create_tb_rate_limits.sql)

// helper: เช็ค+นับจำนวนครั้งที่เรียก $action นี้จาก IP ปัจจุบัน ถ้าเกิน $max_requests
// ภายใน $window_seconds วินาทีที่ผ่านมา จะตอบ 429 แล้วจบ request ทันที (เหมือน json_response)
function enforce_rate_limit($pdo, $action, $max_requests, $window_seconds) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $bucket_key = $action . ':' . $ip;
    $window_seconds = (int) $window_seconds;

    // เก็บ 1 แถวต่อ bucket - ถ้า window เก่าหมดอายุแล้วให้รีเซ็ตนับใหม่จาก 1, ถ้ายังไม่หมดให้ +1
    // ($window_seconds มาจากโค้ดเราเอง ไม่ใช่ input ผู้ใช้ จึงปลอดภัยที่จะแทรกตรงๆ ใน SQL)
    $stmt = $pdo->prepare("
        INSERT INTO tb_rate_limits (bucket_key, window_start, request_count)
        VALUES (:key, NOW(), 1)
        ON DUPLICATE KEY UPDATE
            request_count = IF(window_start < DATE_SUB(NOW(), INTERVAL $window_seconds SECOND), 1, request_count + 1),
            window_start = IF(window_start < DATE_SUB(NOW(), INTERVAL $window_seconds SECOND), NOW(), window_start)
    ");
    $stmt->execute(['key' => $bucket_key]);

    $check = $pdo->prepare("SELECT request_count FROM tb_rate_limits WHERE bucket_key = ?");
    $check->execute([$bucket_key]);
    $row = $check->fetch();

    if ($row && (int) $row['request_count'] > $max_requests) {
        json_response(['error' => 'คำขอมากเกินไป กรุณาลองใหม่ภายหลัง'], 429);
    }
}
