<?php
// includes/auth_token.php
// ตรวจสอบ token แบบ Bearer แทนการใช้ PHP session cookie
// เหตุผล: cookie ข้ามโดเมน (Vercel frontend <-> Render backend) ถูกมือถือ (Safari/Chrome mobile)
// บล็อกเป็น third-party cookie ทำให้ล็อกอินค้างบนมือถือ จึงเปลี่ยนมาส่ง token ทาง
// Authorization header แทน ซึ่งไม่ผูกกับนโยบาย cookie ของเบราว์เซอร์เลย

// อ่าน token จาก "Authorization: Bearer <token>"
// เผื่อกรณี endpoint ที่เปิดผ่าน <a href="..."> ตรงๆ (เช่นลิงก์ดาวน์โหลด PDF) ซึ่งแนบ header เองไม่ได้
// ให้ fallback ไปอ่านจาก query string ?token=... แทนได้ด้วย
function get_bearer_token() {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';

    if (empty($auth_header) && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                $auth_header = $value;
                break;
            }
        }
    }

    if (preg_match('/Bearer\s+(\S+)/i', $auth_header, $matches)) {
        return $matches[1];
    }

    return $_GET['token'] ?? null;
}

// ตรวจ token กับตาราง tb_sessions (ต้องยังไม่หมดอายุ) พร้อม join เอา username มาด้วยเลย
// คืนค่า ['user_id' => ..., 'role' => ..., 'username' => ...] หรือ null ถ้า token ไม่มี/ไม่ถูกต้อง/หมดอายุ
function get_authenticated_session($pdo) {
    $token = get_bearer_token();
    if (!$token) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT s.user_id, s.role, u.username FROM tb_sessions s
        JOIN tb_users u ON u.user_id = s.user_id
        WHERE s.token = ? AND s.expires_at > NOW()");
    $stmt->execute([$token]);
    $session = $stmt->fetch();

    return $session ?: null;
}
