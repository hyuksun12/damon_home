<?php
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/api/db.php';
$pdo = getDB();

// ── 테이블 자동 생성 ──────────────────────────────────────
$pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`        VARCHAR(100)  NOT NULL,
    `category`    ENUM('냉장','냉동','가공') NOT NULL DEFAULT '냉장',
    `description` TEXT          NOT NULL,
    `image_url`   VARCHAR(500)  DEFAULT '',
    `photo_class` VARCHAR(50)   DEFAULT '',
    `tags`        VARCHAR(300)  DEFAULT '',
    `badge`       VARCHAR(50)   DEFAULT '',
    `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
    `sort_order`  INT          NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_active`   (`is_active`),
    INDEX `idx_sort`     (`sort_order`, `id`)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

// ── 기본 상품 시드 (처음 한 번) ──────────────────────────
$count = (int)$pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
if ($count === 0) {
    $defaults = [
        ['배추김치', '냉장', '국내산 배추와 천일염으로 담근 정통 배추김치입니다. 업소용 대용량으로 공급하며 신선도 유지에 최적화된 포장으로 납품합니다.', 'https://wimg.sedaily.com/news/legacy/2025/06/17/2GU3WOVXX7_1.jpg', 'kimchi', '업소용 대용량,정기납품 가능,국내산', '인기', 1, 1],
        ['깍두기',   '냉장', '아삭한 식감이 살아있는 깍두기입니다. 설렁탕·해장국·고깃집 등 다양한 업종에 납품 중이며 업소 규모에 맞게 수량 조절이 가능합니다.', '', 'kkakdugi', '업소용 대용량,정기납품 가능', '', 1, 2],
        ['우거지',   '냉장', '청결하게 손질된 신선한 우거지로 국물 요리의 풍미를 더합니다. 해장국·된장찌개 등에 활용하기 좋은 상태로 납품합니다.', 'https://storage.googleapis.com/takeapp/media/cmbypqjwu00000akzhacm133g.jpg', 'ugeoji', '손질 완료,정기납품 가능', '', 1, 3],
        ['순대',     '냉동', '당일 생산·당일 납품 원칙으로 가장 신선한 상태를 유지합니다. 순대국밥·분식점 등 다양한 업종에 맞는 규격으로 공급합니다.', 'https://image.8dogam.com/resized/product/asset/v1/upload/c86bbca1f3fd499f882b38f3f2284b0b.jpg?type=big&res=2x&ext=webp', 'sundae', '당일납품,다양한 규격', '당일납품', 1, 4],
        ['당면',     '가공', '국내산 원료로 생산한 탄력 있고 쫄깃한 고품질 당면입니다. 잡채·찜닭·전골 등 다양한 요리에 사용되며 대용량 납품이 가능합니다.', 'https://i.namu.wiki/i/jPp4TaJVyjswwZpYHMT6DUn5oVNNZmF0I9Ytqc2UDdXwg1c1RRYim1DBPSgosfAOcZjGyBjxwCQ4hT-hdGMO9w.webp', 'dangmyeon', '국내산 원료,정기납품 가능,대용량', '', 1, 5],
        ['고추가루', '가공', '국내산 고추 100%로 만든 색깔 좋고 매운맛이 살아있는 고추가루입니다. 김치 담금용·요리용 등 용도에 따라 선택 가능합니다.', 'https://i.pinimg.com/1200x/0d/4a/cf/0d4acfce740e9cc7aa9553fd55a96f5d.jpg', 'pepper', '국내산 100%,김치용·요리용', '국내산 100%', 1, 6],
    ];
    $stmt = $pdo->prepare("INSERT INTO `products` (`name`,`category`,`description`,`image_url`,`photo_class`,`tags`,`badge`,`is_active`,`sort_order`) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($defaults as $d) $stmt->execute($d);
}

// ── GET 파라미터 ─────────────────────────────────────────
$category = trim($_GET['category'] ?? '');
$limit    = max(0, (int)($_GET['limit'] ?? 0));

$where  = ['`is_active` = 1'];
$params = [];

if ($category && $category !== 'all') {
    $where[]  = '`category` = ?';
    $params[] = $category;
}

$whereSql = 'WHERE ' . implode(' AND ', $where);
$limitSql = $limit > 0 ? "LIMIT $limit" : '';
$sql = "SELECT * FROM `products` $whereSql ORDER BY `sort_order` ASC, `id` ASC $limitSql";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

foreach ($rows as &$row) {
    $row['tags']      = array_values(array_filter(array_map('trim', explode(',', $row['tags'] ?? ''))));
    $row['is_active'] = (bool)$row['is_active'];
}

echo json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE);
