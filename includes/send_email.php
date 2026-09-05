<?php
// includes/send_email.php
// ฟังก์ชันกลางสำหรับส่งอีเมลแจ้งเตือน ใช้ PHPMailer ผ่าน Gmail SMTP
// วิธีใช้: require_once '../includes/send_email.php'; แล้วเรียก send_notification_email($to, $subject, $body);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// อ่านค่า SMTP: ตอนพัฒนาในเครื่อง (XAMPP) ใช้ config/mail_config.php ที่ไม่ได้ commit ขึ้น GitHub (ดู .gitignore)
// ตอนรันบน Render ไฟล์นี้จะไม่มีอยู่เลย (เพราะไม่ได้ push ขึ้นไป) จึง fallback ไปอ่านจาก environment variables แทน
// ต้องตั้ง SMTP_HOST/SMTP_PORT/SMTP_USERNAME/SMTP_PASSWORD/SMTP_FROM_NAME ใน Render dashboard
function get_mail_config() {
    $config_file = __DIR__ . '/../config/mail_config.php';
    if (file_exists($config_file)) {
        return require $config_file;
    }
    return [
        'smtp_host'     => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'smtp_port'     => (int)(getenv('SMTP_PORT') ?: 587),
        'smtp_username' => getenv('SMTP_USERNAME') ?: '',
        'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
        'from_name'     => getenv('SMTP_FROM_NAME') ?: 'ระบบห้องพยาบาล USP',
    ];
}

function send_notification_email($to_email, $subject, $body_html) {
    if (empty($to_email)) {
        return false;
    }

    $config = get_mail_config();

    if (empty($config['smtp_username']) || empty($config['smtp_password'])) {
        error_log('ส่งอีเมลไม่สำเร็จ: ยังไม่ได้ตั้งค่า SMTP_USERNAME/SMTP_PASSWORD (environment variables หรือ config/mail_config.php)');
        return false;
    }

    $mail = new PHPMailer(true);

    // เก็บบทสนทนา SMTP ทั้งหมด (รวม AUTH LOGIN/PASSWORD ที่เป็น base64) ไว้ในตัวแปรก่อน
    // ไม่เขียนลง error_log ทันทีเพราะมีความลับปนอยู่ - จะ log แบบตัดบรรทัดที่มีข้อมูล auth ออกเฉพาะตอนส่งไม่สำเร็จเท่านั้น
    $smtp_transcript = [];
    $mail->SMTPDebug = SMTP::DEBUG_SERVER;
    $mail->Debugoutput = function ($str) use (&$smtp_transcript) {
        $smtp_transcript[] = trim($str);
    };

    try {
        $mail->isSMTP();
        $mail->Host = $config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $config['smtp_username'];
        $mail->Password = $config['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $config['smtp_port'];
        $mail->CharSet = 'UTF-8';

        $mail->setFrom($config['smtp_username'], $config['from_name']);
        $mail->addAddress($to_email);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body_html;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('ส่งอีเมลไม่สำเร็จ (host=' . $config['smtp_host'] . ':' . $config['smtp_port'] . '): ' . $mail->ErrorInfo);
        // log บทสนทนา SMTP เพื่อดูสาเหตุจริง (เช่น connect ไม่ติด / auth ถูกปฏิเสธ / ผู้ให้บริการ block)
        // ตัดบรรทัดที่เป็นข้อมูล auth (base64 ของ username/password ที่ส่งต่อจาก AUTH LOGIN) ออกก่อน
        $smtp_verbs = ['EHLO', 'HELO', 'MAIL', 'RCPT', 'DATA', 'QUIT', 'AUTH', 'STARTTLS', 'NOOP', 'RSET', 'VRFY'];
        foreach ($smtp_transcript as $line) {
            if (stripos($line, 'CLIENT -> SERVER:') === 0) {
                $cmd = trim(substr($line, strlen('CLIENT -> SERVER:')));
                $verb = strtoupper(strtok($cmd, ' '));
                if (!in_array($verb, $smtp_verbs, true)) {
                    $line = 'CLIENT -> SERVER: [REDACTED]';
                }
            }
            error_log('PHPMailer SMTP: ' . $line);
        }
        return false;
    }
}