<?php
// api/admin/manage_students.php  (GET, POST add/edit, DELETE)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('nurse');

$profile_cols = "p.student_code, p.first_name AS student_first_name, p.last_name AS student_last_name, 
    p.nickname, p.phone, p.blood_type, p.chronic_disease, p.education_level, p.department";

$method = $_SERVER['REQUEST_METHOD'];

// ========== เพิ่ม/แก้ไข ==========
if ($method === 'POST') {
    $body = get_json_body();
    $action = $body['action'] ?? '';

    if ($action === 'add') {
        $role = $body['role'] ?? 'student';
        $username = trim($body['username'] ?? '');
        $password = $body['password'] ?? '';
        $first_name = trim($body['first_name'] ?? '');
        $last_name = trim($body['last_name'] ?? '');

        if (empty($username) || empty($password)) {
            json_response(['error' => 'กรุณากรอกชื่อผู้ใช้และรหัสผ่านให้ครบ'], 400);
        }

        $stmt = $pdo->prepare("SELECT user_id FROM tb_users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            json_response(['error' => 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว'], 409);
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $pdo->beginTransaction();

        if ($role === 'nurse') {
            $position = trim($body['position'] ?? '');
            $avatar = trim($body['avatar'] ?? '');
            $stmt = $pdo->prepare("INSERT INTO tb_users (username, password, role, first_name, last_name, position, avatar) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role, $first_name, $last_name, $position, $avatar ?: null]);
        } else {
            $email = trim($body['email'] ?? '');
            $avatar = trim($body['avatar'] ?? '');
            $stmt = $pdo->prepare("INSERT INTO tb_users (username, password, role, email, avatar) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $role, $email, $avatar ?: null]);
            $new_id = $pdo->lastInsertId();

            $student_code = trim($body['student_code'] ?? '');
            $nickname = trim($body['nickname'] ?? '');
            $education_level = $body['education_level'] ?? '';
            $department = $body['department'] ?? '';

            $stmt2 = $pdo->prepare("INSERT INTO tb_student_profile 
                (user_id, student_code, first_name, last_name, nickname, education_level, department) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt2->execute([$new_id, $student_code, $first_name, $last_name, $nickname, $education_level, $department]);
        }

        $pdo->commit();
        json_response(['success' => true, 'message' => 'เพิ่มบุคลากรเรียบร้อยแล้ว']);
    }

    if ($action === 'edit') {
        $edit_id = (int)($body['user_id'] ?? 0);
        $username = trim($body['username'] ?? '');
        $role = $body['role'] ?? 'student';
        $first_name = trim($body['first_name'] ?? '');
        $last_name = trim($body['last_name'] ?? '');
        $new_password = trim($body['password'] ?? '');
        $avatar = trim($body['avatar'] ?? '');

        $stmt_check = $pdo->prepare("SELECT user_id FROM tb_users WHERE username = ? AND user_id != ?");
        $stmt_check->execute([$username, $edit_id]);
        if ($stmt_check->fetch()) {
            json_response(['error' => 'ชื่อผู้ใช้นี้ถูกใช้งานโดยคนอื่นแล้ว'], 409);
        }

        if ($role === 'nurse') {
            $position = trim($body['position'] ?? '');
            if (!empty($avatar)) {
                $stmt1 = $pdo->prepare("UPDATE tb_users SET username=?, role=?, first_name=?, last_name=?, position=?, avatar=? WHERE user_id=?");
                $stmt1->execute([$username, $role, $first_name, $last_name, $position, $avatar, $edit_id]);
            } else {
                $stmt1 = $pdo->prepare("UPDATE tb_users SET username=?, role=?, first_name=?, last_name=?, position=? WHERE user_id=?");
                $stmt1->execute([$username, $role, $first_name, $last_name, $position, $edit_id]);
            }
        } else {
            $email = trim($body['email'] ?? '');
            if (!empty($avatar)) {
                $stmt1 = $pdo->prepare("UPDATE tb_users SET username=?, email=?, role=?, avatar=? WHERE user_id=?");
                $stmt1->execute([$username, $email, $role, $avatar, $edit_id]);
            } else {
                $stmt1 = $pdo->prepare("UPDATE tb_users SET username=?, email=?, role=? WHERE user_id=?");
                $stmt1->execute([$username, $email, $role, $edit_id]);
            }
        }

        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE tb_users SET password = ? WHERE user_id = ?")->execute([$hashed, $edit_id]);
        }

        if ($role === 'student') {
            $phone = trim($body['phone'] ?? '');
            $blood_type = trim($body['blood_type'] ?? '');
            $chronic_disease = trim($body['chronic_disease'] ?? '');
            $student_code = trim($body['student_code'] ?? '');
            $nickname = trim($body['nickname'] ?? '');
            $education_level = $body['education_level'] ?? '';
            $department = $body['department'] ?? '';

            $stmt_p = $pdo->prepare("SELECT profile_id FROM tb_student_profile WHERE user_id = ?");
            $stmt_p->execute([$edit_id]);

            if ($stmt_p->fetch()) {
                $stmt2 = $pdo->prepare("UPDATE tb_student_profile 
                    SET student_code=?, first_name=?, last_name=?, nickname=?, phone=?, blood_type=?, chronic_disease=?, education_level=?, department=? 
                    WHERE user_id=?");
                $stmt2->execute([$student_code, $first_name, $last_name, $nickname, $phone, $blood_type, $chronic_disease, $education_level, $department, $edit_id]);
            } else {
                $stmt2 = $pdo->prepare("INSERT INTO tb_student_profile 
                    (user_id, student_code, first_name, last_name, nickname, phone, blood_type, chronic_disease, education_level, department) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt2->execute([$edit_id, $student_code, $first_name, $last_name, $nickname, $phone, $blood_type, $chronic_disease, $education_level, $department]);
            }
        }

        json_response(['success' => true, 'message' => 'แก้ไขข้อมูลเรียบร้อยแล้ว']);
    }

    json_response(['error' => 'action ไม่ถูกต้อง'], 400);
}

// ========== ลบ (ลบทุกข้อมูลที่เกี่ยวข้องกับ user คนนี้แบบ cascade) ==========
if ($method === 'DELETE') {
    $delete_id = (int)($_GET['id'] ?? 0);

    if ($delete_id === (int)$_SESSION['user_id']) {
        json_response(['error' => 'ไม่สามารถลบบัญชีนี้ได้ เนื่องจากเป็นบัญชีที่คุณกำลังใช้งานอยู่'], 400);
    }

    try {
        $pdo->beginTransaction();

        // สำคัญมาก: ต้องลบตามลำดับนี้เท่านั้น เพื่อไม่ให้ Foreign Key ชนกัน
        // 1) ลบ tb_notifications ก่อนเสมอ เพราะมันอ้างอิงไปยัง tb_appointments (related_appointment_id)
        //    ถ้าลบ tb_appointments ก่อน จะเหลือ notification ที่ชี้ไปยังนัดหมายที่ไม่มีอยู่แล้ว -> ฐานข้อมูลปฏิเสธ
        $pdo->prepare("DELETE FROM tb_notifications WHERE student_id = ?")->execute([$delete_id]);
        $pdo->prepare("DELETE n FROM tb_notifications n 
            INNER JOIN tb_appointments a ON n.related_appointment_id = a.appointment_id 
            WHERE a.student_id = ? OR a.nurse_id = ?")->execute([$delete_id, $delete_id]);

        // 2) ตอนนี้ปลอดภัยแล้ว ค่อยลบ visits / appointments / allergies
        $pdo->prepare("DELETE FROM tb_visits WHERE student_id = ? OR nurse_id = ?")->execute([$delete_id, $delete_id]);
        $pdo->prepare("DELETE FROM tb_appointments WHERE student_id = ? OR nurse_id = ?")->execute([$delete_id, $delete_id]);
        $pdo->prepare("DELETE FROM tb_allergies WHERE user_id = ? OR updated_by = ?")->execute([$delete_id, $delete_id]);
        $pdo->prepare("DELETE FROM tb_login_logs WHERE user_id = ?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM tb_student_profile WHERE user_id = ?")->execute([$delete_id]);
        $pdo->prepare("DELETE FROM tb_users WHERE user_id = ?")->execute([$delete_id]);

        $pdo->commit();
        json_response(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('ลบสมาชิกไม่สำเร็จ: ' . $e->getMessage());
        json_response(['error' => 'ไม่สามารถลบสมาชิกคนนี้ได้ เนื่องจากยังมีข้อมูลที่เกี่ยวข้องอยู่ในระบบ กรุณาติดต่อผู้ดูแลระบบ'], 500);
    }
}

// ========== ดึงรายชื่อ (GET) พร้อมตัวกรอง ==========
if ($method === 'GET') {
    $student_code = trim($_GET['student_code'] ?? '');
    $filter_level = $_GET['filter_level'] ?? '';
    $filter_dept = $_GET['filter_dept'] ?? '';
    $has_search = !empty($student_code) || !empty($filter_level) || !empty($filter_dept);

    if ($has_search) {
        $where = ["u.role IN ('student','nurse')"];
        $params = [];

        if (!empty($student_code)) {
            $where[] = "p.student_code = ?";
            $params[] = $student_code;
        }
        if (!empty($filter_level)) {
            $where[] = "p.education_level = ?";
            $params[] = $filter_level;
        }
        if (!empty($filter_dept)) {
            $where[] = "p.department = ?";
            $params[] = $filter_dept;
        }

        $where_sql = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT u.user_id, u.username, u.role, u.email, u.first_name, u.last_name, u.position, u.avatar, $profile_cols 
            FROM tb_users u LEFT JOIN tb_student_profile p ON u.user_id = p.user_id 
            WHERE $where_sql ORDER BY u.role ASC, p.student_code ASC");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query("SELECT u.user_id, u.username, u.role, u.email, u.first_name, u.last_name, u.position, u.avatar, $profile_cols 
            FROM tb_users u LEFT JOIN tb_student_profile p ON u.user_id = p.user_id 
            WHERE u.role IN ('student','nurse') ORDER BY u.role ASC, p.student_code ASC");
    }

    json_response($stmt->fetchAll());
}

json_response(['error' => 'Method not allowed'], 405);