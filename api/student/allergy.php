<?php
// api/student/allergy.php  (GET, POST, DELETE)
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../config/db_connect.php';
require_login('student');

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $pdo->prepare("SELECT * FROM tb_allergies WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    json_response($stmt->fetchAll());
}

if ($method === 'POST') {
    $body = get_json_body();
    $allergy_name = trim($body['allergy_name'] ?? '');
    $reaction = trim($body['reaction'] ?? '');
    $severity = $body['severity'] ?? 'mild';

    if (empty($allergy_name)) {
        json_response(['error' => 'กรุณากรอกชื่อยา/สารที่แพ้'], 400);
    }

    $stmt = $pdo->prepare("INSERT INTO tb_allergies (user_id, allergy_name, reaction, severity, updated_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $allergy_name, $reaction, $severity, $user_id]);
    json_response(['success' => true, 'message' => 'เพิ่มข้อมูลการแพ้ยาเรียบร้อยแล้ว']);
}

if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $delete_params);
    $allergy_id = (int)($_GET['id'] ?? $delete_params['id'] ?? 0);
    $stmt = $pdo->prepare("DELETE FROM tb_allergies WHERE allergy_id = ? AND user_id = ?");
    $stmt->execute([$allergy_id, $user_id]);
    json_response(['success' => true]);
}

json_response(['error' => 'Method not allowed'], 405);
