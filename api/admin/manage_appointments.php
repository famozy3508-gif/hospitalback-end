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

        // เหตุผลนัดหมาย (tb_appointments.reason) เอง จำกัด varchar(500) แต่ข้อความแจ้งเตือนอัตโนมัติที่สร้างจาก
        // prefix คงที่ + เหตุผลนี้ (tb_notifications.message) จำกัด varchar(600) ต้องเผื่อความยาว prefix ไว้ด้วย
        // ไม่งั้น reason ที่ผ่าน validate 500 เองอาจทำให้ message รวมเกิน 600 แล้วถูกตัดทอนเงียบๆ
        // ใช้ min(คอลัมน์ reason เอง, พื้นที่ที่เหลือใน message หลังหัก prefix) เผื่อในอนาคตมีคนแก้ข้อความ prefix
        // ให้ยาวขึ้นจนพื้นที่เหลือน้อยกว่าคอลัมน์ reason เอง ลิมิตจะขยับตามอัตโนมัติโดยไม่ต้องแก้เลขตรงนี้
        $notif_prefix = "คุณมีนัดหมายพบห้องพยาบาลวันที่ " . date('d/m/Y เวลา H:i', strtotime($appointment_datetime)) . " น. เหตุผล: ";
        $max_reason_len = min(500, 600 - mb_strlen($notif_prefix));
        validate_max_length($reason, $max_reason_len, 'เหตุผลนัดหมาย (เผื่อพื้นที่ให้ข้อความแจ้งเตือนอัตโนมัติแล้ว)');

        // 1+2. บันทึกนัดหมาย + สร้างแจ้งเตือนในเว็บ ต้องไปด้วยกันเสมอ (ครอบ transaction กันเกิดนัดหมายที่ไม่มีแจ้งเตือนคู่กัน)
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO tb_appointments (student_id, appointment_datetime, reason, status) VALUES (?, ?, ?, 'pending')");
            $stmt->execute([$student_id, $appointment_datetime, $reason]);
            $appointment_id = $pdo->lastInsertId();

            $message = $notif_prefix . $reason;
            $stmt2 = $pdo->prepare("INSERT INTO tb_notifications (student_id, message, related_appointment_id) VALUES (?, ?, ?)");
            $stmt2->execute([$student_id, $message, $appointment_id]);

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('สร้างนัดหมายไม่สำเร็จ: ' . $e->getMessage());
            json_response(['error' => 'ไม่สามารถสร้างนัดหมายได้ กรุณาลองใหม่อีกครั้ง'], 500);
        }

        // 3. ส่งอีเมลแจ้งเตือนไปหานักเรียนคนนี้ด้วย พร้อมรายละเอียดนัดหมาย (เทมเพลตแบบทางการ)
        // อยู่นอก transaction เสมอ เพราะเป็น side effect ภายนอกฐานข้อมูล ถ้าส่งไม่สำเร็จก็ไม่ควรย้อนรอยนัดหมายที่บันทึกไปแล้วออก
        $stmt_email = $pdo->prepare("SELECT email FROM tb_users WHERE user_id = ?");
        $stmt_email->execute([$student_id]);
        $student_email = $stmt_email->fetchColumn();

        $formatted_date = date('d/m/Y', strtotime($appointment_datetime));
        $formatted_time = date('H:i', strtotime($appointment_datetime)) . ' น.';
        $reason_display = !empty($reason) ? htmlspecialchars($reason) : 'ไม่ได้ระบุ';

        $icon_link = 'https://hospital-frontend-gray-one.vercel.app';

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

        // ดึงข้อมูลเดิมมาเทียบก่อน update เพื่อรู้ว่าวันเวลา/เหตุผลเปลี่ยนจริงไหม (เปลี่ยนแค่สถานะ เช่น "มาตามนัดแล้ว"
        // ไม่ควรส่งอีเมล/สร้างแจ้งเตือนไปรบกวนนักเรียนซ้ำ) ค่าเดิมจาก DB ต้อง normalize ผ่าน strtotime+date ให้อยู่
        // ฟอร์แมตเดียวกับค่าใหม่ที่ประกอบจาก พ.ศ. ก่อนเทียบสตริง ไม่งั้นจะเข้าใจผิดว่าเปลี่ยนทุกครั้งทั้งที่ไม่ได้เปลี่ยน
        $stmt_old = $pdo->prepare("SELECT student_id, appointment_datetime, reason FROM tb_appointments WHERE appointment_id = ?");
        $stmt_old->execute([$appointment_id]);
        $old = $stmt_old->fetch();
        if (!$old) {
            json_response(['error' => 'ไม่พบนัดหมายนี้'], 404);
        }

        $old_datetime_normalized = date('Y-m-d H:i:s', strtotime($old['appointment_datetime']));
        $old_reason = trim($old['reason'] ?? '');
        $datetime_changed = ($old_datetime_normalized !== $appointment_datetime);
        $reason_changed = ($old_reason !== $reason);
        $should_notify = $datetime_changed || $reason_changed;

        // prefix ของข้อความแจ้งเตือน "แก้ไข" ยาวกว่า prefix ของ action add จึงคำนวณลิมิต reason แยกต่างหาก
        // (หลักการเดียวกับ add: min(คอลัมน์ reason เอง 500, พื้นที่ที่เหลือใน tb_notifications.message 600 หลังหัก
        // prefix) กันข้อความถูกตัดทอนเงียบๆ ตอน insert) คำนวณเฉพาะตอนจะแจ้งเตือนจริงเท่านั้น เพื่อไม่ให้กรณีแก้แค่
        // สถานะถูกจำกัดความยาว reason แคบกว่าที่ควร (เหลือแค่ขีดจำกัดคอลัมน์ reason เอง 500)
        if ($should_notify) {
            $notif_prefix = "นัดหมายพบห้องพยาบาลของคุณถูกแก้ไข เป็นวันที่ " . date('d/m/Y เวลา H:i', strtotime($appointment_datetime)) . " น. เหตุผล: ";
            $max_reason_len = min(500, 600 - mb_strlen($notif_prefix));
            validate_max_length($reason, $max_reason_len, 'เหตุผลนัดหมาย (เผื่อพื้นที่ให้ข้อความแจ้งเตือนอัตโนมัติแล้ว)');
        } else {
            validate_max_length($reason, 500, 'เหตุผลนัดหมาย');
        }

        // 1+2. บันทึกนัดหมาย + สร้างแจ้งเตือนในเว็บใหม่ (เฉพาะกรณีวันเวลา/เหตุผลเปลี่ยนจริง) ต้องไปด้วยกันเสมอ
        // สร้างแถวแจ้งเตือนใหม่แทนการแก้ข้อความเดิม เพื่อคงประวัตินัดหมายเดิมไว้ และให้ตัวนับแจ้งเตือนยังไม่อ่าน
        // (unread_count) ขึ้นจริงแม้นักเรียนจะเคยเปิดอ่านแจ้งเตือนเดิมของนัดหมายนี้ไปแล้วก็ตาม
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE tb_appointments SET appointment_datetime=?, reason=?, status=? WHERE appointment_id=?");
            $stmt->execute([$appointment_datetime, $reason, $status, $appointment_id]);

            if ($should_notify) {
                $message = $notif_prefix . $reason;
                $stmt2 = $pdo->prepare("INSERT INTO tb_notifications (student_id, message, related_appointment_id) VALUES (?, ?, ?)");
                $stmt2->execute([$old['student_id'], $message, $appointment_id]);
            }

            $pdo->commit();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('แก้ไขนัดหมายไม่สำเร็จ: ' . $e->getMessage());
            json_response(['error' => 'ไม่สามารถแก้ไขนัดหมายได้ กรุณาลองใหม่อีกครั้ง'], 500);
        }

        // 3. ส่งอีเมลแจ้งการแก้ไข เฉพาะกรณีวันเวลา/เหตุผลเปลี่ยนจริง อยู่นอก transaction เสมอเหมือน action add
        // เพราะเป็น side effect ภายนอกฐานข้อมูล ถ้าส่งไม่สำเร็จก็ไม่ควรย้อนรอยการแก้ไขที่บันทึกไปแล้วออก
        if ($should_notify) {
            $stmt_email = $pdo->prepare("SELECT email FROM tb_users WHERE user_id = ?");
            $stmt_email->execute([$old['student_id']]);
            $student_email = $stmt_email->fetchColumn();

            $formatted_date = date('d/m/Y', strtotime($appointment_datetime));
            $formatted_time = date('H:i', strtotime($appointment_datetime)) . ' น.';
            $reason_display = !empty($reason) ? htmlspecialchars($reason) : 'ไม่ได้ระบุ';

            // แสดงวันเวลานัดหมายเดิมคู่กับของใหม่ เฉพาะกรณีวันเวลาเปลี่ยนจริง ช่วยให้นักเรียนเห็นชัดว่าเปลี่ยนจาก
            // อะไรเป็นอะไร โดยไม่ต้องไปเทียบเองกับอีเมลฉบับก่อนหน้า
            $old_date_row = '';
            if ($datetime_changed) {
                $old_formatted_date = date('d/m/Y', strtotime($old['appointment_datetime']));
                $old_formatted_time = date('H:i', strtotime($old['appointment_datetime'])) . ' น.';
                $old_date_row = "
              <tr>
                <td style='padding:18px 22px; border-bottom:1px solid #E2E6EB;'>
                  <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                      <td style='font-size:13px; color:#6B7280; padding-bottom:4px;'>วันเวลานัดหมายเดิม</td>
                    </tr>
                    <tr>
                      <td style='font-size:15px; color:#9AA0A6; text-decoration:line-through;'>{$old_formatted_date} เวลา {$old_formatted_time}</td>
                    </tr>
                  </table>
                </td>
              </tr>";
            }

            $icon_link = 'https://hospital-frontend-gray-one.vercel.app';

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
            <p style='margin:0; font-size:19px; font-weight:bold; color:#2B2F33;'>นัดหมายห้องพยาบาลของคุณถูกแก้ไข</p>
          </td>
        </tr>

        <!-- Body text -->
        <tr>
          <td style='padding:8px 32px 24px; text-align:center;'>
            <p style='margin:0; font-size:14px; color:#6B7280; line-height:1.6;'>
              นัดหมายเข้าพบห้องพยาบาลของคุณมีการเปลี่ยนแปลงรายละเอียด กรุณาตรวจสอบข้อมูลล่าสุดด้านล่างและมาตามเวลาที่กำหนด
            </p>
          </td>
        </tr>

        <!-- Detail card -->
        <tr>
          <td style='padding:0 32px 32px;'>
            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' style='background-color:#F5F7FA; border-radius:10px;'>
{$old_date_row}
              <tr>
                <td style='padding:18px 22px; border-bottom:1px solid #E2E6EB;'>
                  <table role='presentation' width='100%' cellpadding='0' cellspacing='0'>
                    <tr>
                      <td style='font-size:13px; color:#6B7280; padding-bottom:4px;'>วันที่นัดหมายใหม่</td>
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
                      <td style='font-size:13px; color:#6B7280; padding-bottom:4px;'>เวลานัดหมายใหม่</td>
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

            send_notification_email($student_email, "แจ้งแก้ไขนัดหมายห้องพยาบาล", $email_body);
        }

        json_response(['success' => true, 'message' => $should_notify
            ? 'แก้ไขนัดหมายเรียบร้อยแล้ว (ส่งแจ้งเตือนในเว็บ + อีเมลให้นักเรียนแล้ว)'
            : 'แก้ไขนัดหมายเรียบร้อยแล้ว']);
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