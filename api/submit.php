<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '허용되지 않는 요청입니다.']);
    exit;
}

// FormData 또는 JSON 모두 수용
$raw = file_get_contents('php://input');
$json = json_decode($raw, true);
$data = $json ?: $_POST;

$name    = trim($data['name']    ?? '');
$phone   = trim($data['phone']   ?? '');
$company = trim($data['company'] ?? '');
$type    = trim($data['type']    ?? '');
$message = trim($data['message'] ?? '');

// 필수값 검증
if (!$name || !$phone || !$type || !$message) {
    echo json_encode(['success' => false, 'message' => '필수 항목을 모두 입력해주세요.']);
    exit;
}

// 길이 제한
if (mb_strlen($name) > 100 || mb_strlen($phone) > 50 || mb_strlen($message) > 3000) {
    echo json_encode(['success' => false, 'message' => '입력값이 너무 깁니다.']);
    exit;
}

// 허용된 문의 유형
$allowedTypes = ['납품 문의', '가격 문의', '정기납품 계약', '품목 문의', '기타'];
if (!in_array($type, $allowedTypes, true)) {
    echo json_encode(['success' => false, 'message' => '올바른 문의 유형을 선택해주세요.']);
    exit;
}

try {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        "INSERT INTO `inquiries` (`name`, `phone`, `company`, `type`, `message`)
         VALUES (:name, :phone, :company, :type, :message)"
    );
    $stmt->execute([
        ':name'    => $name,
        ':phone'   => $phone,
        ':company' => $company,
        ':type'    => $type,
        ':message' => $message,
    ]);

    echo json_encode(['success' => true, 'message' => '문의가 접수되었습니다.']);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => '저장 중 오류가 발생했습니다.']);
}
