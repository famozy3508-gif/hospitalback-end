<?php
// api/admin/search_history.php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('nurse');

$method = $_SERVER['REQUEST_METHOD'];

// ========== เพิ่ม/แก้ไขประวัติแพ้ยา (พยาบาลจัดการให้นักเรียน) ==========
if ($method === 'POST') {
    $body = get_json_body();
    $action = $body['action'] ?? '';

    if ($action === 'add_allergy') {
        $student_id_target = (int)($body['student_id_target'] ?? 0);
        $allergy_name = trim($body['allergy_name'] ?? '');
        $reaction = trim($body['reaction'] ?? '');
        $severity = $body['severity'] ?? 'mild';

        if (empty($allergy_name) || empty($student_id_target)) {
            json_response(['error' => 'กรุณากรอกข้อมูลให้ครบ'], 400);
        }

        $stmt = $pdo->prepare("INSERT INTO tb_allergies (user_id, allergy_name, reaction, severity, updated_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$student_id_target, $allergy_name, $reaction, $severity, $_SESSION['user_id']]);
        json_response(['success' => true, 'message' => 'เพิ่มรายการแพ้ยาเรียบร้อยแล้ว']);
    }

    if ($action === 'edit_allergy') {
        $allergy_id = (int)($body['allergy_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE tb_allergies SET allergy_name=?, reaction=?, severity=?, updated_by=? WHERE allergy_id=?");
        $stmt->execute([trim($body['allergy_name'] ?? ''), trim($body['reaction'] ?? ''), $body['severity'] ?? 'mild', $_SESSION['user_id'], $allergy_id]);
        json_response(['success' => true, 'message' => 'แก้ไขประวัติแพ้ยาเรียบร้อยแล้ว']);
    }

    json_response(['error' => 'action ไม่ถูกต้อง'], 400);
}

// ========== ลบประวัติแพ้ยา ==========
if ($method === 'DELETE') {
    $allergy_id = (int)($_GET['allergy_id'] ?? 0);
    $pdo->prepare("DELETE FROM tb_allergies WHERE allergy_id = ?")->execute([$allergy_id]);
    json_response(['success' => true, 'message' => 'ลบประวัติแพ้ยาเรียบร้อยแล้ว']);
}

// ========== ค้นหา / ดูรายละเอียด ==========
if ($method === 'GET') {

    // ?view=user_id -> ดูรายละเอียดคนเดียว (โปรไฟล์ + แพ้ยา + ประวัติรักษา + นัดหมาย)
    if (isset($_GET['view'])) {
        $view_id = (int)$_GET['view'];

        $stmt = $pdo->prepare("SELECT u.email, u.username, u.avatar, p.* FROM tb_users u 
            LEFT JOIN tb_student_profile p ON u.user_id = p.user_id WHERE u.user_id = ?");
        $stmt->execute([$view_id]);
        $student = $stmt->fetch();

        $stmt2 = $pdo->prepare("SELECT * FROM tb_allergies WHERE user_id = ? ORDER BY created_at DESC");
        $stmt2->execute([$view_id]);
        $allergies = $stmt2->fetchAll();

        $stmt3 = $pdo->prepare("SELECT * FROM tb_visits WHERE student_id = ? ORDER BY visit_datetime DESC");
        $stmt3->execute([$view_id]);
        $visits = $stmt3->fetchAll();

        $stmt4 = $pdo->prepare("SELECT * FROM tb_appointments WHERE student_id = ? ORDER BY appointment_datetime DESC");
        $stmt4->execute([$view_id]);
        $appointments = $stmt4->fetchAll();

        json_response([
            'student' => $student,
            'allergies' => $allergies,
            'visits' => $visits,
            'appointments' => $appointments,
        ]);
    }

    // ค้นหารายชื่อ (คำค้น + ระดับชั้น/สาขา)
    $keyword = trim($_GET['keyword'] ?? '');
    $filter_level = $_GET['filter_level'] ?? '';
    $filter_dept = $_GET['filter_dept'] ?? '';
    $has_search = !empty($keyword) || !empty($filter_level) || !empty($filter_dept);

    if ($has_search) {
        $where = ["u.role = 'student'"];
        $params = [];

        if (!empty($keyword)) {
            $where[] = "(p.student_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ?)";
            $term = "%$keyword%";
            $params[] = $term; $params[] = $term; $params[] = $term;
        }
        if (!empty($filter_level)) { $where[] = "p.education_level = ?"; $params[] = $filter_level; }
        if (!empty($filter_dept)) { $where[] = "p.department = ?"; $params[] = $filter_dept; }

        $where_sql = implode(' AND ', $where);
        $stmt = $pdo->prepare("SELECT u.user_id, p.student_code, p.first_name, p.last_name 
            FROM tb_users u JOIN tb_student_profile p ON u.user_id = p.user_id 
            WHERE $where_sql ORDER BY p.student_code ASC");
        $stmt->execute($params);
    } else {
        $stmt = $pdo->query("SELECT u.user_id, p.student_code, p.first_name, p.last_name 
            FROM tb_users u JOIN tb_student_profile p ON u.user_id = p.user_id 
            WHERE u.role = 'student' ORDER BY p.student_code ASC");
    }

    json_response($stmt->fetchAll());
}

json_response(['error' => 'Method not allowed'], 405);