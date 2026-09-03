<?php
// api/admin/manage_appointments.php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_once __DIR__ . '/../../includes/send_email.php';
require_login('nurse');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $body = get_json_body();
    $action = $body['action'] ?? '';

    if ($action === 'add') {
        $student_id = (int)($body['student_id'] ?? 0);
        $day = (int)($body['day'] ?? 0);
        $month = (int)($body['month'] ?? 0);
        $year_be = (int)($body['year'] ?? 0);
        $hour = $body['hour'] ?? '';
        $minute = $body['minute'] ?? '';
        $reason = trim($body['reason'] ?? '');

        if (empty($student_id) || empty($day) || empty($month) || empty($year_be) || $hour === '' || $minute === '') {
            json_response(['error' => 'กรุณาเลือกนักเรียนและระบุวันเวลานัดหมายให้ครบ'], 400);
        }

        $year_ad = $year_be - 543;
        $appointment_datetime = sprintf('%04d-%02d-%02d %02d:%02d:00', $year_ad, $month, $day, $hour, $minute);

        // 1. บันทึกนัดหมายลงฐานข้อมูล
        $stmt = $pdo->prepare("INSERT INTO tb_appointments (student_id, appointment_datetime, reason, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$student_id, $appointment_datetime, $reason]);
        $appointment_id = $pdo->lastInsertId();

        // 2. สร้างแจ้งเตือนในเว็บ
        $message = "คุณมีนัดหมายพบห้องพยาบาลวันที่ " . date('d/m/Y เวลา H:i', strtotime($appointment_datetime)) . " น. เหตุผล: " . $reason;
        $stmt2 = $pdo->prepare("INSERT INTO tb_notifications (student_id, message, related_appointment_id) VALUES (?, ?, ?)");
        $stmt2->execute([$student_id, $message, $appointment_id]);

        // 3. ส่งอีเมลแจ้งเตือนไปหานักเรียนคนนี้ด้วย พร้อมรายละเอียดนัดหมาย (เทมเพลตแบบทางการ)
        $stmt_email = $pdo->prepare("SELECT email FROM tb_users WHERE user_id = ?");
        $stmt_email->execute([$student_id]);
        $student_email = $stmt_email->fetchColumn();

        $formatted_date = date('d/m/Y', strtotime($appointment_datetime));
        $formatted_time = date('H:i', strtotime($appointment_datetime)) . ' น.';
        $reason_display = !empty($reason) ? htmlspecialchars($reason) : 'ไม่ได้ระบุ';

        // TODO: เปลี่ยนลิงก์นี้เป็นเว็บวิทยาลัยจริงก่อนส่งอาจารย์ตรวจ! ตอนนี้ใส่ไว้เล่นๆ ชั่วคราว
        $icon_link = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';

        $email_body = "
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
              <div style='width:64px; height:64px; background-color:#EAF2FB; border-radius:50%; margin:0 auto 10px; line-height:64px; font-size:30px;'>⚕️</div>
            </a>
            <p style='margin:0 0 12px; font-size:12px; color:#2B6CB0;'>👆 แตะไอคอนด้านบนเพื่อเข้าสู่เว็บไซต์วิทยาลัย</p>
            <p style='margin:0; font-size:19px; font-weight:bold; color:#2B2F33;'>แจ้งเตือนนัดหมายห้องพยาบาล</p>
          </td>
        </tr>

        <!-- Body text -->
        <tr>
          <td style='padding:8px 32px 24px; text-align:center;'>
            <p style='margin:0; font-size:14px; color:#6B7280; line-height:1.6;'>
              คุณมีนัดหมายเข้าพบห้องพยาบาล กรุณาตรวจสอบรายละเอียดด้านล่างและมาตามเวลาที่กำหนด
            </p>
          </td>
        </tr>

        <!-- Detail card -->
        <tr>
          <td style='padding:0 32px 32px;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background-color:#F5F7FA; border-radius:10px;'>
              <tr>
                <td style='padding:18px 22px; border-bottom:1px solid #E2E6EB;'>
                  <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                      <td style='font-size:13px; color:#6B7280; padding-bottom:4px;'>วันที่นัดหมาย</td>
                    </tr>
                    <tr>
                      <td style='font-size:16px; font-weight:bold; color:#1E4E8C;'>{$formatted_date}</td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style='padding:18px 22px; border-bottom:1px solid #E2E6EB;'>
                  <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                      <td style='font-size:13px; color:#6B7280; padding-bottom:4px;'>เวลานัดหมาย</td>
                    </tr>
                    <tr>
                      <td style='font-size:16px; font-weight:bold; color:#1E4E8C;'>{$formatted_time}</td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style='padding:18px 22px;'>
                  <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                      <td style='font-size:13px; color:#6B7280; padding-bottom:4px;'>เหตุผล / รายละเอียด</td>
                    </tr>
                    <tr>
                      <td style='font-size:15px; color:#2B2F33;'>{$reason_display}</td>
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

        send_notification_email($student_email, "แจ้งเตือนนัดหมายห้องพยาบาล", $email_body);

        json_response(['success' => true, 'message' => 'สร้างนัดหมายและส่งแจ้งเตือนเรียบร้อยแล้ว (ในเว็บ + อีเมล)']);
    }

    if ($action === 'edit') {
        $appointment_id = (int)($body['appointment_id'] ?? 0);
        $day = (int)($body['day'] ?? 0);
        $month = (int)($body['month'] ?? 0);
        $year_be = (int)($body['year'] ?? 0);
        $hour = $body['hour'] ?? 0;
        $minute = $body['minute'] ?? 0;
        $reason = trim($body['reason'] ?? '');
        $status = $body['status'] ?? 'pending';

        $year_ad = $year_be - 543;
        $appointment_datetime = sprintf('%04d-%02d-%02d %02d:%02d:00', $year_ad, $month, $day, $hour, $minute);

        $stmt = $pdo->prepare("UPDATE tb_appointments SET appointment_datetime=?, reason=?, status=? WHERE appointment_id=?");
        $stmt->execute([$appointment_datetime, $reason, $status, $appointment_id]);
        json_response(['success' => true, 'message' => 'แก้ไขนัดหมายเรียบร้อยแล้ว']);
    }

    if ($action === 'complete' || $action === 'cancel') {
        $id = (int)($body['appointment_id'] ?? 0);
        $status = $action === 'complete' ? 'completed' : 'cancelled';
        $pdo->prepare("UPDATE tb_appointments SET status = ? WHERE appointment_id = ?")->execute([$status, $id]);
        json_response(['success' => true, 'message' => 'อัปเดตสถานะเรียบร้อยแล้ว']);
    }

    json_response(['error' => 'action ไม่ถูกต้อง'], 400);
}

if ($method === 'DELETE') {
    $delete_id = (int)($_GET['id'] ?? 0);
    $pdo->prepare("DELETE FROM tb_notifications WHERE related_appointment_id = ?")->execute([$delete_id]);
    $pdo->prepare("DELETE FROM tb_appointments WHERE appointment_id = ?")->execute([$delete_id]);
    json_response(['success' => true, 'message' => 'ลบนัดหมายเรียบร้อยแล้ว']);
}

if ($method === 'GET') {
    // ?mode=students -> รายชื่อนักเรียนสำหรับ dropdown (กรองด้วยรหัสนักศึกษา และ/หรือ ระดับชั้น/สาขาได้)
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

            $stmt = $pdo->prepare("SELECT u.user_id, p.student_code, p.first_name, p.last_name 
                FROM tb_users u JOIN tb_student_profile p ON u.user_id = p.user_id 
                WHERE $where_sql ORDER BY p.student_code ASC");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->query("SELECT u.user_id, p.student_code, p.first_name, p.last_name 
                FROM tb_users u LEFT JOIN tb_student_profile p ON u.user_id = p.user_id 
                WHERE u.role = 'student' ORDER BY p.student_code ASC");
        }
        json_response($stmt->fetchAll());
    }

    // default -> นัดหมายล่าสุด (กรองด้วยรหัสนักศึกษา/ระดับชั้น/สาขาได้)
    $student_code = trim($_GET['student_code'] ?? '');
    $filter_level = $_GET['filter_level'] ?? '';
    $filter_dept = $_GET['filter_dept'] ?? '';
    $has_search = !empty($student_code) || !empty($filter_level) || !empty($filter_dept);

    if ($has_search) {
        $where = [];
        $params = [];
        if (!empty($student_code)) { $where[] = "p.student_code LIKE ?"; $params[] = "%$student_code%"; }
        if (!empty($filter_level)) { $where[] = "p.education_level = ?"; $params[] = $filter_level; }
        if (!empty($filter_dept)) { $where[] = "p.department = ?"; $params[] = $filter_dept; }
        $where_sql = implode(' AND ', $where);

        $stmt = $pdo->prepare("SELECT a.*, p.student_code, p.first_name, p.last_name 
            FROM tb_appointments a LEFT JOIN tb_student_profile p ON a.student_id = p.user_id 
            WHERE $where_sql ORDER BY a.appointment_datetime DESC LIMIT 20");
        $stmt->execute($params);
        $appointments = $stmt->fetchAll();
    } else {
        $appointments = $pdo->query("SELECT a.*, p.student_code, p.first_name, p.last_name 
            FROM tb_appointments a LEFT JOIN tb_student_profile p ON a.student_id = p.user_id 
            ORDER BY a.appointment_datetime DESC LIMIT 20")->fetchAll();
    }
    json_response($appointments);
}

json_response(['error' => 'Method not allowed'], 405);