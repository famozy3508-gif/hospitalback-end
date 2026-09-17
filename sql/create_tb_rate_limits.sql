-- sql/create_tb_rate_limits.sql
-- ตารางเก็บสถานะ rate limit ต่อ IP สำหรับ endpoint ที่ไม่ต้องล็อกอิน (เช่น check_available.php)
-- กันคนไล่ยิงเดา username/email/รหัสนักศึกษาว่ามีอยู่ในระบบไหมบ้าง
--
-- วิธีรัน: เปิด Railway MySQL (หรือฐานข้อมูลที่ backend ใช้งานจริง) แล้วรัน SQL นี้ครั้งเดียว
--   mysql -h <host> -P <port> -u <user> -p<password> <database> < sql/create_tb_rate_limits.sql

CREATE TABLE IF NOT EXISTS `tb_rate_limits` (
  `id` int NOT NULL AUTO_INCREMENT,
  `bucket_key` varchar(191) NOT NULL,
  `window_start` datetime NOT NULL,
  `request_count` int NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bucket_key` (`bucket_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
