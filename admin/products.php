<?php
session_start();

if (empty($_SESSION['admin'])) {
    header('Location: index.php');
    exit;
}

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

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// ── products.json 자동 갱신 (GitHub Pages 대응) ──────────
function regenerateJson(PDO $pdo): void {
    $rows = $pdo->query(
        "SELECT * FROM `products` WHERE `is_active`=1 ORDER BY `sort_order` ASC, `id` ASC"
    )->fetchAll();

    foreach ($rows as &$row) {
        $row['tags']      = array_values(array_filter(array_map('trim', explode(',', $row['tags'] ?? ''))));
        $row['is_active'] = true;
        $row['id']        = (int)$row['id'];
        $row['sort_order'] = (int)$row['sort_order'];
        unset($row['created_at'], $row['updated_at']);
    }

    $json = json_encode(['success' => true, 'data' => $rows], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents(dirname(__DIR__) . '/products.json', $json);

    // ── GitHub API로 자동 배포 ──
    $configPath = dirname(__DIR__) . '/api/github_config.php';
    if (!file_exists($configPath)) return;
    require_once $configPath;

    $apiUrl = "https://api.github.com/repos/" . GITHUB_OWNER . "/" . GITHUB_REPO . "/contents/" . GITHUB_FILE;
    $headers = [
        "Authorization: token " . GITHUB_TOKEN,
        "Accept: application/vnd.github+json",
        "User-Agent: damon-admin",
        "Content-Type: application/json",
    ];

    // 현재 파일 SHA 조회
    $ch = curl_init($apiUrl . "?ref=" . GITHUB_BRANCH);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $res  = curl_exec($ch);
    curl_close($ch);
    $info = json_decode($res, true);
    $sha  = $info['sha'] ?? null;
    if (!$sha) return;

    // 파일 업데이트
    $body = json_encode([
        'message' => '상품 업데이트 (자동)',
        'content' => base64_encode($json),
        'sha'     => $sha,
        'branch'  => GITHUB_BRANCH,
    ], JSON_UNESCAPED_UNICODE);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// ── 기본 상품 시드 ───────────────────────────────────────
$cnt = (int)$pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
if ($cnt === 0) {
    $defaults = [
        ['배추김치', '냉장', '국내산 배추와 천일염으로 담근 정통 배추김치입니다. 업소용 대용량으로 공급하며 신선도 유지에 최적화된 포장으로 납품합니다.', 'https://wimg.sedaily.com/news/legacy/2025/06/17/2GU3WOVXX7_1.jpg', 'kimchi', '업소용 대용량,정기납품 가능,국내산', '인기', 1, 1],
        ['깍두기',   '냉장', '아삭한 식감이 살아있는 깍두기입니다. 설렁탕·해장국·고깃집 등 다양한 업종에 납품 중이며 업소 규모에 맞게 수량 조절이 가능합니다.', '', 'kkakdugi', '업소용 대용량,정기납품 가능', '', 1, 2],
        ['우거지',   '냉장', '청결하게 손질된 신선한 우거지로 국물 요리의 풍미를 더합니다. 해장국·된장찌개 등에 활용하기 좋은 상태로 납품합니다.', 'https://storage.googleapis.com/takeapp/media/cmbypqjwu00000akzhacm133g.jpg', 'ugeoji', '손질 완료,정기납품 가능', '', 1, 3],
        ['순대',     '냉동', '당일 생산·당일 납품 원칙으로 가장 신선한 상태를 유지합니다. 순대국밥·분식점 등 다양한 업종에 맞는 규격으로 공급합니다.', 'https://image.8dogam.com/resized/product/asset/v1/upload/c86bbca1f3fd499f882b38f3f2284b0b.jpg?type=big&res=2x&ext=webp', 'sundae', '당일납품,다양한 규격', '당일납품', 1, 4],
        ['당면',     '가공', '국내산 원료로 생산한 탄력 있고 쫄깃한 고품질 당면입니다. 잡채·찜닭·전골 등 다양한 요리에 사용되며 대용량 납품이 가능합니다.', 'https://i.namu.wiki/i/jPp4TaJVyjswwZpYHMT6DUn5oVNNZmF0I9Ytqc2UDdXwg1c1RRYim1DBPSgosfAOcZjGyBjxwCQ4hT-hdGMO9w.webp', 'dangmyeon', '국내산 원료,정기납품 가능,대용량', '', 1, 5],
        ['고추가루', '가공', '국내산 고추 100%로 만든 색깔 좋고 매운맛이 살아있는 고추가루입니다. 김치 담금용·요리용 등 용도에 따라 선택 가능합니다.', 'https://i.pinimg.com/1200x/0d/4a/cf/0d4acfce740e9cc7aa9553fd55a96f5d.jpg', 'pepper', '국내산 100%,김치용·요리용', '국내산 100%', 1, 6],
    ];
    $st = $pdo->prepare("INSERT INTO `products` (`name`,`category`,`description`,`image_url`,`photo_class`,`tags`,`badge`,`is_active`,`sort_order`) VALUES (?,?,?,?,?,?,?,?,?)");
    foreach ($defaults as $d) $st->execute($d);
}

// ── POST 처리 ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id          = (int)($_POST['id'] ?? 0);
        $name        = mb_substr(trim($_POST['name']        ?? ''), 0, 100);
        $category    = in_array($_POST['category'] ?? '', ['냉장','냉동','가공']) ? $_POST['category'] : '냉장';
        $description = mb_substr(trim($_POST['description'] ?? ''), 0, 2000);
        $image_url   = mb_substr(trim($_POST['image_url']   ?? ''), 0, 500);
        $photo_class = mb_substr(trim($_POST['photo_class'] ?? ''), 0, 50);
        $tags        = mb_substr(trim($_POST['tags']        ?? ''), 0, 300);
        $badge       = mb_substr(trim($_POST['badge']       ?? ''), 0, 50);
        $is_active   = isset($_POST['is_active']) ? 1 : 0;
        $sort_order  = max(0, (int)($_POST['sort_order'] ?? 0));

        if ($name) {
            if ($id) {
                $pdo->prepare("UPDATE `products` SET `name`=?,`category`=?,`description`=?,`image_url`=?,`photo_class`=?,`tags`=?,`badge`=?,`is_active`=?,`sort_order`=? WHERE `id`=?")
                    ->execute([$name,$category,$description,$image_url,$photo_class,$tags,$badge,$is_active,$sort_order,$id]);
                $msg = '상품이 수정되었습니다.';
            } else {
                $pdo->prepare("INSERT INTO `products` (`name`,`category`,`description`,`image_url`,`photo_class`,`tags`,`badge`,`is_active`,`sort_order`) VALUES (?,?,?,?,?,?,?,?,?)")
                    ->execute([$name,$category,$description,$image_url,$photo_class,$tags,$badge,$is_active,$sort_order]);
                $msg = '상품이 추가되었습니다.';
            }
            regenerateJson($pdo);
            header('Location: products.php?flash=' . urlencode($msg));
            exit;
        }
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare("DELETE FROM `products` WHERE `id`=?")->execute([$id]);
        regenerateJson($pdo);
        header('Location: products.php?flash=' . urlencode('상품이 삭제되었습니다.'));
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $pdo->prepare("UPDATE `products` SET `is_active`=1-`is_active` WHERE `id`=?")->execute([$id]);
        regenerateJson($pdo);
        header('Location: products.php');
        exit;
    }
}

// ── 데이터 조회 ──────────────────────────────────────────
$products = $pdo->query("SELECT * FROM `products` ORDER BY `sort_order` ASC, `id` ASC")->fetchAll();
$totalCnt = count($products);
$activeCnt = count(array_filter($products, fn($p) => $p['is_active']));

$flash = isset($_GET['flash']) ? $_GET['flash'] : '';

// 패널 상태
$panelMode    = '';
$panelProduct = null;

if (isset($_GET['add'])) {
    $panelMode = 'add';
} elseif (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt   = $pdo->prepare("SELECT * FROM `products` WHERE `id`=?");
    $stmt->execute([$editId]);
    $panelProduct = $stmt->fetch();
    if ($panelProduct) $panelMode = 'edit';
}

function catBadgeClass(string $cat): string {
    return match($cat) { '냉장' => 'badge--cold', '냉동' => 'badge--frozen', '가공' => 'badge--processed', default => '' };
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>담온유통 | 상품 관리</title>
<style>
/* ── Reset & Base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Pretendard', 'Noto Sans KR', sans-serif; background: #f4f6f8; color: #1c1c1c; font-size: 14px; min-height: 100vh; }
a { color: inherit; text-decoration: none; }
button, input, select, textarea { font-family: inherit; }
button { cursor: pointer; }

/* ── Layout ── */
.sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 220px; background: #1a2e1a; color: #c8e0c8; display: flex; flex-direction: column; z-index: 100; }
.sidebar-logo { padding: 28px 24px 20px; border-bottom: 1px solid #2a4a2a; }
.sidebar-logo-name { font-size: 18px; font-weight: 700; color: #7dc85a; }
.sidebar-logo-sub  { font-size: 10px; color: #6a8a6a; letter-spacing: 1px; margin-top: 2px; }
.sidebar-label { padding: 20px 24px 8px; font-size: 10px; font-weight: 700; color: #4a7a4a; letter-spacing: 1.5px; text-transform: uppercase; }
.sidebar-nav { flex: 1; padding: 0 12px; }
.sidebar-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: #a0c8a0; font-size: 13px; font-weight: 500; margin-bottom: 2px; transition: all .15s; }
.sidebar-link:hover, .sidebar-link.active { background: #2a4a2a; color: #7dc85a; }
.sidebar-footer { padding: 20px 24px; border-top: 1px solid #2a4a2a; }
.sidebar-logout { background: none; border: 1.5px solid #3a6a3a; color: #8ab88a; border-radius: 8px; padding: 8px 16px; font-size: 13px; width: 100%; transition: all .15s; }
.sidebar-logout:hover { background: #2a4a2a; color: #fff; }

.main { margin-left: 220px; min-height: 100vh; }
.topbar { background: #fff; border-bottom: 1px solid #e8ece8; padding: 18px 32px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
.topbar-title { font-size: 16px; font-weight: 700; }
.topbar-meta  { font-size: 12px; color: #888; }
.content { padding: 28px 32px; }

/* ── Stats ── */
.stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px 24px; border: 1.5px solid #eee; }
.stat-label { font-size: 12px; color: #888; margin-bottom: 8px; }
.stat-num { font-size: 28px; font-weight: 800; color: #1a2e1a; }
.stat-num--active { color: #4d8b2a; }
.stat-num--inactive { color: #aaa; }

/* ── Toolbar ── */
.toolbar { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.btn-add { display: inline-flex; align-items: center; gap: 8px; background: #4d8b2a; color: #fff; border: none; border-radius: 8px; padding: 10px 20px; font-size: 13px; font-weight: 600; text-decoration: none; transition: background .15s; }
.btn-add:hover { background: #3a6e1f; }

/* ── Flash ── */
.flash { background: #f0f7ea; border: 1.5px solid #b8dca0; color: #2d6a2d; border-radius: 10px; padding: 12px 18px; font-size: 13px; margin-bottom: 20px; }

/* ── Table ── */
.table-wrap { background: #fff; border-radius: 12px; border: 1.5px solid #eee; overflow: hidden; }
.table-head { padding: 14px 24px; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #666; }
table { width: 100%; border-collapse: collapse; }
th { background: #f8faf6; padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 700; color: #666; border-bottom: 1px solid #eee; white-space: nowrap; }
td { padding: 14px 16px; border-bottom: 1px solid #f4f4f4; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafff6; }
.td-name { font-weight: 600; }
.td-desc { color: #666; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; }
.td-img img { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; display: block; }
.td-img-empty { width: 48px; height: 48px; background: #f0f0f0; border-radius: 8px; }

/* ── Badges ── */
.badge { display: inline-flex; align-items: center; border-radius: 20px; padding: 3px 10px; font-size: 11px; font-weight: 700; }
.badge--cold      { background: #e8f5e0; color: #2d7a20; }
.badge--frozen    { background: #e8f0ff; color: #2050c0; }
.badge--processed { background: #fff3e0; color: #b06000; }
.badge--on  { background: #f0f7ea; color: #4d8b2a; }
.badge--off { background: #f4f4f4; color: #aaa; }

/* ── Row actions ── */
.row-actions { display: flex; gap: 6px; }
.btn-edit   { border: 1.5px solid #ccc; background: #fff; border-radius: 6px; padding: 5px 12px; font-size: 12px; color: #555; transition: all .15s; text-decoration: none; display: inline-block; }
.btn-edit:hover { border-color: #4d8b2a; color: #4d8b2a; }
.btn-toggle { border: none; border-radius: 6px; padding: 5px 10px; font-size: 11px; font-weight: 600; cursor: pointer; transition: all .15s; }
.btn-toggle--on  { background: #f4f4f4; color: #888; }
.btn-toggle--on:hover  { background: #ffe8e8; color: #c00; }
.btn-toggle--off { background: #eef8e6; color: #4d8b2a; }
.btn-toggle--off:hover { background: #d8f0c0; }

/* ── Empty ── */
.empty-msg { text-align: center; padding: 60px 20px; color: #aaa; }

/* ── Panel overlay ── */
.panel-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 200; display: flex; justify-content: flex-end; }
.panel { background: #fff; width: 540px; max-width: 100vw; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: -8px 0 32px rgba(0,0,0,.15); }
.panel-header { padding: 24px 28px 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: #fff; z-index: 10; }
.panel-title { font-size: 16px; font-weight: 700; }
.panel-close { background: none; border: none; color: #888; font-size: 22px; line-height: 1; padding: 4px; }
.panel-close:hover { color: #333; }
.panel-body { padding: 28px; flex: 1; }
.panel-footer { padding: 20px 28px; border-top: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }

/* ── Form ── */
.form-row { display: flex; gap: 16px; margin-bottom: 20px; }
.form-row > * { flex: 1; }
.form-group { margin-bottom: 20px; }
.form-label { font-size: 12px; font-weight: 700; color: #555; display: block; margin-bottom: 6px; }
.form-label span { color: #e05050; }
.form-input, .form-select, .form-textarea {
    width: 100%; border: 1.5px solid #ddd; border-radius: 8px; padding: 10px 14px; font-size: 13px; outline: none; transition: border-color .2s; background: #fff;
}
.form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #4d8b2a; }
.form-textarea { resize: vertical; min-height: 80px; }
.form-hint { font-size: 11px; color: #aaa; margin-top: 4px; }
.form-check { display: flex; align-items: center; gap: 10px; }
.form-check input[type="checkbox"] { width: 16px; height: 16px; accent-color: #4d8b2a; cursor: pointer; }
.form-check label { font-size: 13px; color: #444; cursor: pointer; }
.img-preview { margin-top: 8px; width: 80px; height: 80px; object-fit: cover; border-radius: 8px; display: none; border: 1.5px solid #eee; }
.img-preview.show { display: block; }
.form-divider { border: none; border-top: 1px solid #f0f0f0; margin: 20px 0; }

.btn-save { background: #4d8b2a; color: #fff; border: none; border-radius: 8px; padding: 11px 28px; font-size: 14px; font-weight: 600; transition: background .15s; }
.btn-save:hover { background: #3a6e1f; }
.btn-delete-p { background: none; border: 1.5px solid #e0b0b0; color: #c05050; border-radius: 8px; padding: 9px 20px; font-size: 13px; font-weight: 600; transition: all .15s; }
.btn-delete-p:hover { background: #fff3f3; }

/* ── Responsive ── */
@media (max-width: 900px) {
    .sidebar { width: 200px; }
    .main { margin-left: 200px; }
    .stats { grid-template-columns: repeat(2, 1fr); }
    .panel { width: 100vw; }
}
@media (max-width: 640px) {
    .sidebar { display: none; }
    .main { margin-left: 0; }
    .topbar, .content { padding-left: 16px; padding-right: 16px; }
}
</style>
</head>
<body>

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-name">담온유통</div>
    <div class="sidebar-logo-sub">ADMIN BOARD</div>
  </div>
  <div class="sidebar-label">메뉴</div>
  <nav class="sidebar-nav">
    <a href="index.php" class="sidebar-link">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="9" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="1" y="9" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="9" y="9" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
      문의 게시판
    </a>
    <a href="products.php" class="sidebar-link active">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="9" y="6" width="6" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M9 1h5M9 3.5h3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      상품 관리
    </a>
    <a href="/damon_home/" class="sidebar-link" target="_blank">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2L14 14H2L8 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
      사이트 바로가기
    </a>
  </nav>
  <div class="sidebar-footer">
    <form method="post" action="index.php">
      <input type="hidden" name="action" value="logout">
      <button class="sidebar-logout" type="submit">로그아웃</button>
    </form>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <div class="topbar">
    <span class="topbar-title">상품 관리</span>
    <span class="topbar-meta">총 <?= $totalCnt ?>개 상품</span>
  </div>

  <div class="content">

    <?php if ($flash): ?>
    <div class="flash"><?= esc($flash) ?></div>
    <?php endif; ?>

    <!-- 통계 -->
    <div class="stats">
      <div class="stat-card">
        <div class="stat-label">전체 상품</div>
        <div class="stat-num"><?= $totalCnt ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">노출 중</div>
        <div class="stat-num stat-num--active"><?= $activeCnt ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">비활성</div>
        <div class="stat-num stat-num--inactive"><?= $totalCnt - $activeCnt ?></div>
      </div>
    </div>

    <!-- 툴바 -->
    <div class="toolbar">
      <span style="font-size:13px;color:#666;"><?= $totalCnt ?>개 등록됨</span>
      <a href="products.php?add=1" class="btn-add">
        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v12M1 7h12" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
        상품 추가
      </a>
    </div>

    <!-- 목록 -->
    <div class="table-wrap">
      <div class="table-head">상품 목록</div>
      <?php if (empty($products)): ?>
        <div class="empty-msg">등록된 상품이 없습니다.</div>
      <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>이미지</th>
            <th>상품명</th>
            <th>카테고리</th>
            <th>배지</th>
            <th>설명</th>
            <th>순서</th>
            <th>상태</th>
            <th>관리</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($products as $p): ?>
          <tr>
            <td class="td-img">
              <?php if ($p['image_url']): ?>
                <img src="<?= esc($p['image_url']) ?>" alt="<?= esc($p['name']) ?>">
              <?php else: ?>
                <div class="td-img-empty"></div>
              <?php endif; ?>
            </td>
            <td class="td-name"><?= esc($p['name']) ?></td>
            <td><span class="badge <?= catBadgeClass($p['category']) ?>"><?= esc($p['category']) ?></span></td>
            <td style="font-size:12px;color:#777;"><?= esc($p['badge'] ?: '—') ?></td>
            <td class="td-desc"><?= esc($p['description']) ?></td>
            <td style="color:#999;font-size:12px;"><?= (int)$p['sort_order'] ?></td>
            <td>
              <?php if ($p['is_active']): ?>
                <span class="badge badge--on">노출</span>
              <?php else: ?>
                <span class="badge badge--off">숨김</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="row-actions">
                <a href="products.php?edit=<?= $p['id'] ?>" class="btn-edit">수정</a>
                <form method="post" style="display:inline">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= $p['id'] ?>">
                  <?php if ($p['is_active']): ?>
                    <button class="btn-toggle btn-toggle--on" type="submit">숨기기</button>
                  <?php else: ?>
                    <button class="btn-toggle btn-toggle--off" type="submit">노출</button>
                  <?php endif; ?>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ── 추가/수정 패널 ── -->
<?php if ($panelMode): ?>
<div class="panel-overlay" id="panelOverlay">
  <div class="panel">
    <div class="panel-header">
      <div class="panel-title"><?= $panelMode === 'add' ? '상품 추가' : '상품 수정' ?></div>
      <a href="products.php"><button class="panel-close" aria-label="닫기">×</button></a>
    </div>

    <div class="panel-body">
      <form method="post" id="productForm">
        <input type="hidden" name="action" value="save">
        <?php if ($panelMode === 'edit' && $panelProduct): ?>
          <input type="hidden" name="id" value="<?= $panelProduct['id'] ?>">
        <?php endif; ?>

        <!-- 기본 정보 -->
        <div class="form-row">
          <div class="form-group" style="flex:2">
            <label class="form-label">상품명 <span>*</span></label>
            <input class="form-input" type="text" name="name" value="<?= esc($panelProduct['name'] ?? '') ?>" placeholder="예: 배추김치" required>
          </div>
          <div class="form-group">
            <label class="form-label">카테고리 <span>*</span></label>
            <select class="form-select" name="category">
              <?php foreach (['냉장','냉동','가공'] as $cat): ?>
                <option value="<?= $cat ?>" <?= ($panelProduct['category'] ?? '냉장') === $cat ? 'selected' : '' ?>><?= $cat ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">상품 설명</label>
          <textarea class="form-textarea" name="description" rows="3" placeholder="상품에 대한 상세 설명을 입력하세요."><?= esc($panelProduct['description'] ?? '') ?></textarea>
        </div>

        <hr class="form-divider">

        <!-- 이미지 & 스타일 -->
        <div class="form-group">
          <label class="form-label">이미지 URL</label>
          <input class="form-input" type="url" name="image_url" id="imageUrlInput" value="<?= esc($panelProduct['image_url'] ?? '') ?>" placeholder="https://...">
          <img class="img-preview <?= !empty($panelProduct['image_url']) ? 'show' : '' ?>" id="imgPreview" src="<?= esc($panelProduct['image_url'] ?? '') ?>" alt="미리보기">
          <p class="form-hint">이미지가 없으면 CSS 배경색이 표시됩니다.</p>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">CSS 클래스</label>
            <input class="form-input" type="text" name="photo_class" value="<?= esc($panelProduct['photo_class'] ?? '') ?>" placeholder="예: kimchi">
            <p class="form-hint">카드 배경색용 클래스 (kimchi, sundae 등)</p>
          </div>
          <div class="form-group">
            <label class="form-label">배지 텍스트</label>
            <input class="form-input" type="text" name="badge" value="<?= esc($panelProduct['badge'] ?? '') ?>" placeholder="예: 인기, 당일납품">
            <p class="form-hint">카드 상단에 표시되는 강조 문구</p>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">태그</label>
          <input class="form-input" type="text" name="tags" value="<?= esc($panelProduct['tags'] ?? '') ?>" placeholder="정기납품 가능, 국내산, 업소용">
          <p class="form-hint">쉼표(,)로 구분하여 여러 태그 입력</p>
        </div>

        <hr class="form-divider">

        <!-- 설정 -->
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">정렬 순서</label>
            <input class="form-input" type="number" name="sort_order" value="<?= (int)($panelProduct['sort_order'] ?? 0) ?>" min="0" placeholder="0">
            <p class="form-hint">숫자가 낮을수록 앞에 표시</p>
          </div>
          <div class="form-group" style="display:flex;align-items:center;padding-top:28px;">
            <div class="form-check">
              <input type="checkbox" name="is_active" id="isActive" <?= ($panelProduct['is_active'] ?? 1) ? 'checked' : '' ?>>
              <label for="isActive">홈페이지에 노출</label>
            </div>
          </div>
        </div>

      </form>
    </div>

    <div class="panel-footer">
      <?php if ($panelMode === 'edit' && $panelProduct): ?>
        <form method="post" onsubmit="return confirm('이 상품을 삭제하시겠습니까?')">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= $panelProduct['id'] ?>">
          <button class="btn-delete-p" type="submit">삭제</button>
        </form>
      <?php else: ?>
        <span></span>
      <?php endif; ?>
      <button class="btn-save" type="submit" form="productForm">저장</button>
    </div>
  </div>
</div>

<script>
// 오버레이 배경 클릭 → 닫기
document.getElementById('panelOverlay').addEventListener('click', function(e) {
  if (e.target === this) location.href = 'products.php';
});

// 이미지 URL 입력 → 미리보기
const imgInput   = document.getElementById('imageUrlInput');
const imgPreview = document.getElementById('imgPreview');
if (imgInput && imgPreview) {
  imgInput.addEventListener('input', function() {
    const url = this.value.trim();
    if (url) {
      imgPreview.src = url;
      imgPreview.classList.add('show');
    } else {
      imgPreview.classList.remove('show');
    }
  });
}
</script>
<?php endif; ?>

</body>
</html>
