<?php
// includes/cloudinary.php
// อัปโหลดรูปโปรไฟล์ไป Cloudinary แทนการเก็บไฟล์ในเครื่อง เพราะ Render ใช้ ephemeral storage
// ไฟล์ในเครื่องจะหายทุกครั้งที่ deploy ใหม่ ต้องเก็บรูปไว้ที่อื่นแล้วเก็บแค่ URL ลงฐานข้อมูล
// วิธีใช้: require_once '../../includes/cloudinary.php'; แล้วเรียก upload_avatar_to_cloudinary($_FILES['avatar']);
// ค่า config อ่านจาก environment variables เท่านั้น ไม่ hardcode ลงโค้ด (ตั้งค่าใน Render dashboard)

function upload_avatar_to_cloudinary($file) {
    $cloud_name = getenv('CLOUDINARY_CLOUD_NAME');
    $api_key = getenv('CLOUDINARY_API_KEY');
    $api_secret = getenv('CLOUDINARY_API_SECRET');

    if (!$cloud_name || !$api_key || !$api_secret) {
        json_response(['error' => 'เซิร์ฟเวอร์ยังไม่ได้ตั้งค่า Cloudinary (CLOUDINARY_CLOUD_NAME/API_KEY/API_SECRET)'], 500);
    }

    // เซ็นชื่อคำขอแบบ signed upload ของ Cloudinary: sha1 ของพารามิเตอร์ที่เรียงตามตัวอักษร (a=z) ต่อด้วย api_secret
    $timestamp = time();
    $params_to_sign = ['folder' => 'hospital-avatars', 'timestamp' => $timestamp];
    ksort($params_to_sign);
    $to_sign = '';
    foreach ($params_to_sign as $key => $value) {
        $to_sign .= ($to_sign === '' ? '' : '&') . "$key=$value";
    }
    $signature = sha1($to_sign . $api_secret);

    $curl_file = new CURLFile($file['tmp_name'], $file['type'], $file['name']);

    $ch = curl_init("https://api.cloudinary.com/v1_1/$cloud_name/image/upload");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => [
            'file' => $curl_file,
            'api_key' => $api_key,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => 'hospital-avatars',
        ],
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        json_response(['error' => 'อัปโหลดรูปไป Cloudinary ไม่สำเร็จ: ' . $curl_error], 500);
    }

    $result = json_decode($response, true);
    if ($http_code !== 200 || empty($result['secure_url'])) {
        $reason = $result['error']['message'] ?? 'ไม่ทราบสาเหตุ';
        json_response(['error' => 'อัปโหลดรูปไป Cloudinary ไม่สำเร็จ: ' . $reason], 500);
    }

    return $result['secure_url'];
}
