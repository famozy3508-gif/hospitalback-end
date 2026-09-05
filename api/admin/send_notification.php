<?php
// api/admin/send_notification.php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/send_email.php';
require_login('nurse');

$method = $_SERVER['REQUEST_METHOD'];

// ===== ฟังก์ชันสร้างเทมเพลตอีเมล (ใช้ดีไซน์เดียวกับอีเมลนัดหมาย) =====
function build_notification_email($title, $message, $is_broadcast = false) {
    $message_display = nl2br(htmlspecialchars($message));
    $subtitle = $is_broadcast
        ? 'ประกาศฉบับนี้ส่งถึงนักเรียนทุกคนที่มีอีเมลลงทะเบียนไว้ในระบบ'
        : 'คุณได้รับข้อความแจ้งเตือนจากห้องพยาบาล กรุณาอ่านรายละเอียดด้านล่าง';

    $icon_link = 'https://hospital-frontend-gray-one.vercel.app';

    return "
<table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background-color:#F5F7FA; padding:40px 20px; font-family: Tahoma, Arial, sans-serif;'>
  <tr>
    <td align='center'>
      <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='max-width:520px; background-color:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,0.06);'>

        <!-- Header -->
        <tr>
          <td style='background-color:#1E4E8C; padding:28px 32px;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
              <tr>
                <td style='color:#ffffff; font-size:18px; font-weight:bold;'>
                  ระบบห้องพยาบาล
                </td>
              </tr>
              <tr>
                <td style='color:#BFD6EE; font-size:12px; padding-top:2px;'>
                  Hospital Notification System
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Icon + Title -->
        <tr>
          <td style='padding:32px 32px 8px; text-align:center;'>
            <a href='{$icon_link}' style='text-decoration:none;'>
              <div style='width:64px; height:64px; background-color:#EAF2FB; border-radius:50%; margin:0 auto 10px; line-height:64px; font-size:30px;'>🔔</div>
            </a>
            <p style='margin:0 0 12px; font-size:12px; color:#2B6CB0;'>👆 แตะไอคอนด้านบนเพื่อเข้าสู่เว็บไซต์วิทยาลัย</p>
            <p style='margin:0; font-size:19px; font-weight:bold; color:#2B2F33;'>{$title}</p>
          </td>
        </tr>

        <!-- Subtitle -->
        <tr>
          <td style='padding:8px 32px 24px; text-align:center;'>
            <p style='margin:0; font-size:14px; color:#6B7280; line-height:1.6;'>
              {$subtitle}
            </p>
          </td>
        </tr>

        <!-- Message card -->
        <tr>
          <td style='padding:0 32px 32px;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background-color:#F5F7FA; border-radius:10px;'>
              <tr>
                <td style='padding:20px 22px;'>
                  <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                      <td style='font-size:13px; color:#6B7280; padding-bottom:6px;'>ข้อความ</td>
                    </tr>
                    <tr>
                      <td style='font-size:16px; color:#2B2F33; line-height:1.6;'>{$message_display}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Divider -->
        <tr>
          <td style='padding:0 32px;'>
            <div style='border-top:1px solid #E2E6EB;'></div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style='padding:20px 32px 28px; text-align:center;'>
            <p style='margin:0; font-size:12px; color:#9AA0A6; line-height:1.6;'>
              อีเมลฉบับนี้ถูกส่งโดยอัตโนมัติจากระบบห้องพยาบาล<br>
              กรุณาอย่าตอบกลับอีเมลฉบับนี้ หากมีข้อสงสัยกรุณาติดต่อห้องพยาบาลโดยตรง
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>";
}

if ($method === 'POST') {
    $body = get_json_body();
    $message = trim($body['message'] ?? '');
    $broadcast = !empty($body['broadcast']);

    if (empty($message)) {
        json_response(['error' => 'กรุณากรอกข้อความแจ้งเตือน'], 400);
    }

    // ===== โหมดส่งให้ทุกคน (แบบข่าวสาร/ประกาศ) =====
    if ($broadcast) {
        $stmt_all = $pdo->query("SELECT user_id, email FROM tb_users WHERE role = 'student' AND email IS NOT NULL AND email != ''");
        $recipients = $stmt_all->fetchAll();

        if (count($recipients) === 0) {
            json_response(['error' => 'ไม่พบนักเรียนที่มีอีเมลในระบบเลย'], 400);
        }

        $email_body = build_notification_email('ประกาศจากห้องพยาบาล', $message, true);
        $sent_count = 0;

        foreach ($recipients as $r) {
            $stmt_insert = $pdo->prepare("INSERT INTO tb_notifications (student_id, message) VALUES (?, ?)");
            $stmt_insert->execute([$r['user_id'], $message]);

            send_notification_email($r['email'], "ประกาศจากห้องพยาบาล", $email_body);
            $sent_count++;
        }

        json_response(['success' => true, 'message' => "ส่งประกาศให้นักเรียนทั้งหมดเรียบร้อยแล้ว ($sent_count คน)"]);
    }

    // ===== โหมดส่งคนเดียว =====
    $student_id = (int)($body['student_id'] ?? 0);
    if (empty($student_id)) {
        json_response(['error' => 'กรุณาเลือกนักเรียนและกรอกข้อความแจ้งเตือน'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO tb_notifications (student_id, message) VALUES (?, ?)");
    $stmt->execute([$student_id, $message]);

    $stmt_email = $pdo->prepare("SELECT email FROM tb_users WHERE user_id = ?");
    $stmt_email->execute([$student_id]);
    $student_email = $stmt_email->fetchColumn();

    $email_body = build_notification_email('แจ้งเตือนจากห้องพยาบาล', $message, false);
    send_notification_email($student_email, "แจ้งเตือนจากห้องพยาบาล", $email_body);

    json_response(['success' => true, 'message' => 'ส่งแจ้งเตือนเรียบร้อยแล้ว']);
}

if ($method === 'DELETE') {
    $delete_id = (int)($_GET['id'] ?? 0);
    $pdo->prepare("DELETE FROM tb_notifications WHERE notification_id = ?")->execute([$delete_id]);
    json_response(['success' => true, 'message' => 'ลบแจ้งเตือนเรียบร้อยแล้ว']);
}

if ($method === 'GET') {
    // ?mode=students -> รายชื่อนักเรียนสำหรับ dropdown (กรองด้วยรหัสนักศึกษา และ/หรือ ระดับชั้น/สาขาได้ พร้อมอีเมล)
    if (($_GET['mode'] ?? '') === 'students') {
        $student_code = trim($_GET['student_code'] ?? '');
        $filter_level = $_GET['filter_level'] ?? '';
        $filter_dept = $_GET['filter_dept'] ?? '';
        $has_search = !empty($student_code) || !empty($filter_level) || !empty($filter_dept);

        if ($has_search) {
            $where = ["u.role = 'student'"];
            $params = [];
            if (!empty($student_code)) { $where[] = "p.student_code = ?"; $params[] = $student_code; }
            if (!empty($filter_level)) { $where[] = "p.education_level = ?"; $params[] = $filter_level; }
            if (!empty($filter_dept)) { $where[] = "p.department = ?"; $params[] = $filter_dept; }
            $where_sql = implode(' AND ', $where);

            $stmt = $pdo->prepare("SELECT u.user_id, u.email, p.student_code, p.first_name, p.last_name 
                FROM tb_users u JOIN tb_student_profile p ON u.user_id = p.user_id 
                WHERE $where_sql ORDER BY p.student_code ASC");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->query("SELECT u.user_id, u.email, p.student_code, p.first_name, p.last_name 
                FROM tb_users u LEFT JOIN tb_student_profile p ON u.user_id = p.user_id 
                WHERE u.role = 'student' ORDER BY p.student_code ASC");
        }
        json_response($stmt->fetchAll());
    }

    // default -> แจ้งเตือนที่ส่งล่าสุด 20 รายการ
    $notifications = $pdo->query("SELECT n.*, p.student_code, p.first_name, p.last_name 
        FROM tb_notifications n LEFT JOIN tb_student_profile p ON n.student_id = p.user_id 
        ORDER BY n.created_at DESC LIMIT 20")->fetchAll();
    json_response($notifications);
}

json_response(['error' => 'Method not allowed'], 405);