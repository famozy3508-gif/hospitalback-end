<?php
// config/db_connect.php

// ตั้งเขตเวลาเป็นไทยเสมอ ป้องกันเวลาคลาดเคลื่อนตอนสร้าง PDF หรือบันทึกข้อมูล
date_default_timezone_set('Asia/Bangkok');

// อ่านค่าการเชื่อมต่อจาก Environment Variables ก่อน (ตั้งค่าจริงตอน deploy เช่นบน Railway/โฮสติ้ง)
// ถ้าไม่มี Environment Variable กำหนดไว้ (เช่นตอนทดสอบในเครื่อง XAMPP) จะ fallback ไปใช้ค่า localhost เดิม
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'hospital_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'เชื่อมต่อฐานข้อมูลไม่สำเร็จ: ' . $e->getMessage()]);
    exit;
}