<?php
// api/admin/manage_visits.php  (GET list+students, POST add/edit, DELETE)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('nurse');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $body = get_json_body();
    $action = $body['action'] ?? '';

    if ($action === 'add') {
        $student_id = (int)($body['student_id'] ?? 0);
        $symptoms = trim($body['symptoms'] ?? '');
        $diagnosis = trim($body['diagnosis'] ?? '');
        $treatment = trim($body['treatment'] ?? '');
        $medicine_given = trim($body['medicine_given'] ?? '');
        $notes = trim($body['notes'] ?? '');

        if (empty($student_id) || empty($symptoms)) {
            json_response(['error' => 'กรุณาเลือกนักเรียนและกรอกอาการให้ครบ'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO tb_visits (student_id, symptoms, diagnosis, treatment, medicine_given, notes) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$student_id, $symptoms, $diagnosis, $treatment, $medicine_given, $notes]);
        json_response(['success' => true, 'message' => 'บันทึกข้อมูลเรียบร้อยแล้ว']);
    }

    if ($action === 'edit') {
        $visit_id = (int)($body['visit_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE tb_visits SET symptoms=?, diagnosis=?, treatment=?, medicine_given=?, notes=? WHERE visit_id=?");
        $stmt->execute([
            trim($body['symptoms'] ?? ''), trim($body['diagnosis'] ?? ''), trim($body['treatment'] ?? ''),
            trim($body['medicine_given'] ?? ''), trim($body['notes'] ?? ''), $visit_id
        ]);
        json_response(['success' => true, 'message' => 'แก้ไขข้อมูลเรียบร้อยแล้ว']);
    }

    json_response(['error' => 'action ไม่ถูกต้อง'], 400);
}

if ($method === 'DELETE') {
    $delete_id = (int)($_GET['id'] ?? 0);
    $pdo->prepare("DELETE FROM tb_visits WHERE visit_id = ?")->execute([$delete_id]);
    json_response(['success' => true, 'message' => 'ลบข้อมูลเรียบร้อยแล้ว']);
}

if ($method === 'GET') {
    // ?mode=students -> รายชื่อนักเรียนสำหรับ dropdown (กรองด้วยรหัสนักศึกษาแบบตรงเป๊ะ และ/หรือ ระดับชั้น/สาขาได้)
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

    // default -> ประวัติการบันทึกล่าสุด (กรองด้วยรหัสนักศึกษา/ระดับชั้น/สาขาได้)
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

        $stmt = $pdo->prepare("SELECT v.*, p.student_code, p.first_name, p.last_name 
            FROM tb_visits v LEFT JOIN tb_student_profile p ON v.student_id = p.user_id 
            WHERE $where_sql ORDER BY v.visit_datetime DESC LIMIT 20");
        $stmt->execute($params);
        $visits = $stmt->fetchAll();
    } else {
        $visits = $pdo->query("SELECT v.*, p.student_code, p.first_name, p.last_name 
            FROM tb_visits v LEFT JOIN tb_student_profile p ON v.student_id = p.user_id 
            ORDER BY v.visit_datetime DESC LIMIT 20")->fetchAll();
    }
    json_response($visits);
}

json_response(['error' => 'Method not allowed'], 405);