<?php
// mail_config.docker-stub.php
// ใช้เฉพาะตอนรันผ่าน Docker (docker-compose.yml) เท่านั้น — bind mount ทับ
// backend/config/mail_config.php ภายใน container เพื่อบังคับให้ get_mail_config()
// (backend/includes/send_email.php) อ่านค่าจาก environment variable แทนไฟล์จริงในเครื่อง
// ไม่แตะไฟล์ backend/config/mail_config.php เลย เวลากลับไปรันแบบ XAMPP/npm run dev
// ไฟล์เดิมจะยังอยู่ครบและใช้งานได้ตามปกติ

return [
    'smtp_host'     => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port'     => (int)(getenv('SMTP_PORT') ?: 587),
    'smtp_username' => getenv('SMTP_USERNAME') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'from_name'     => getenv('SMTP_FROM_NAME') ?: 'ระบบห้องพยาบาล USP',
];
