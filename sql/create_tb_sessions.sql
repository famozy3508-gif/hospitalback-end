-- sql/create_tb_sessions.sql
-- ตารางเก็บ token สำหรับ token-based authentication (แทน PHP session cookie)
-- เหตุผล: session cookie ข้ามโดเมน (Vercel frontend <-> Render backend) ถูกมือถือ
-- (Safari/Chrome mobile) บล็อกเป็น third-party cookie ทำให้ล็อกอินค้างบนมือถือ
--
-- วิธีรัน: เปิด Railway MySQL (หรือฐานข้อมูลที่ backend ใช้งานจริง) แล้วรัน SQL นี้ครั้งเดียว
--   mysql -h <host> -P <port> -u <user> -p<password> <database> < sql/create_tb_sessions.sql

CREATE TABLE IF NOT EXISTS `tb_sessions` (
  `token` varchar(64) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('student','nurse','admin') NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`token`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `tb_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `tb_users` (`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
