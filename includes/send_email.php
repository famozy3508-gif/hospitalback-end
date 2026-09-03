<?php
// includes/send_email.php
// ฟังก์ชันกลางสำหรับส่งอีเมลแจ้งเตือน ใช้ PHPMailer ผ่าน Gmail SMTP
// วิธีใช้: require_once '../includes/send_email.php'; แล้วเรียก send_notification_email($to, $subject, $body);

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_notification_email($to_email, $subject, $body_html) {
    if (empty($to_email)) {
        return false;
    }

    $config = require __DIR__ . '/../config/mail_config.php';

    $mail = new PHPMailer(true);

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
        error_log('ส่งอีเมลไม่สำเร็จ: ' . $mail->ErrorInfo);
        return false;
    }
}