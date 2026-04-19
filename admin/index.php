<?php
session_start();

// ── 관리자 비밀번호 (변경 가능) ──────────────────────────
define('ADMIN_PASSWORD', 'damon2026');

// ── 로그인 처리 ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'login') {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['admin'] = true;
            header('Location: index.php');
        } else {
            $loginError = '비밀번호가 올바르지 않습니다.';
        }
        // action이 없으면 아래로 계속 진행
    }

    if ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: index.php');
        exit;
    }

    if (!empty($_SESSION['admin'])) {

        if ($_POST['action'] === 'update_status') {
            require_once dirname(__DIR__) . '/api/db.php';
            $allowed = ['신규', '확인중', '처리완료'];
            $status  = $_POST['status'] ?? '';
            $id      = (int)($_POST['id'] ?? 0);
            if ($id && in_array($status, $allowed, true)) {
                $pdo = getDB();
                $pdo->prepare("UPDATE `inquiries` SET `status`=? WHERE `id`=?")->execute([$status, $id]);
            }
            header('Location: index.php?' . http_build_query(array_filter([
                'status' => $_GET['status'] ?? '',
                'q'      => $_GET['q']      ?? '',
                'page'   => $_GET['page']   ?? '',
            ])));
            exit;
        }

        if ($_POST['action'] === 'update_memo') {
            require_once dirname(__DIR__) . '/api/db.php';
            $id   = (int)($_POST['id']   ?? 0);
            $memo = mb_substr(trim($_POST['memo'] ?? ''), 0, 500);
            if ($id) {
                $pdo = getDB();
                $pdo->prepare("UPDATE `inquiries` SET `memo`=? WHERE `id`=?")->execute([$memo, $id]);
            }
            header('Location: index.php?view=' . $id . '&' . http_build_query(array_filter([
                'status' => $_GET['status'] ?? '',
                'q'      => $_GET['q']      ?? '',
                'page'   => $_GET['page']   ?? '',
            ])));
            exit;
        }

        if ($_POST['action'] === 'delete') {
            require_once dirname(__DIR__) . '/api/db.php';
            $id = (int)($_POST['id'] ?? 0);
            if ($id) {
                $pdo = getDB();
                $pdo->prepare("DELETE FROM `inquiries` WHERE `id`=?")->execute([$id]);
            }
            header('Location: index.php?' . http_build_query(array_filter([
                'status' => $_GET['status'] ?? '',
                'q'      => $_GET['q']      ?? '',
            ])));
            exit;
        }
    }
}

$isAdmin = !empty($_SESSION['admin']);

// ── 데이터 조회 ──────────────────────────────────────────
$rows   = [];
$total  = 0;
$counts = ['전체' => 0, '신규' => 0, '확인중' => 0, '처리완료' => 0];
$detail = null;

if ($isAdmin) {
    require_once dirname(__DIR__) . '/api/db.php';
    $pdo = getDB();

    // 상태별 카운트
    $cntRows = $pdo->query("SELECT `status`, COUNT(*) AS cnt FROM `inquiries` GROUP BY `status`")->fetchAll();
    foreach ($cntRows as $r) {
        $counts[$r['status']] = (int)$r['cnt'];
        $counts['전체'] += (int)$r['cnt'];
    }

    // 상세 보기
    $viewId = (int)($_GET['view'] ?? 0);
    if ($viewId) {
        $detail = $pdo->prepare("SELECT * FROM `inquiries` WHERE `id`=?")->execute([$viewId]) ? null : null;
        $stmt   = $pdo->prepare("SELECT * FROM `inquiries` WHERE `id`=?");
        $stmt->execute([$viewId]);
        $detail = $stmt->fetch();
    }

    // 목록
    $filterStatus = $_GET['status'] ?? '';
    $search       = trim($_GET['q'] ?? '');
    $perPage      = 15;
    $page         = max(1, (int)($_GET['page'] ?? 1));

    $where  = [];
    $params = [];

    if ($filterStatus && $filterStatus !== '전체') {
        $where[]  = '`status` = ?';
        $params[] = $filterStatus;
    }
    if ($search) {
        $where[]  = '(`name` LIKE ? OR `phone` LIKE ? OR `company` LIKE ? OR `message` LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }

    $sql_where = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `inquiries` $sql_where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $listStmt = $pdo->prepare("SELECT * FROM `inquiries` $sql_where ORDER BY `created_at` DESC LIMIT $perPage OFFSET $offset");
    $listStmt->execute($params);
    $rows = $listStmt->fetchAll();
}

$totalPages = $total > 0 ? ceil($total / $perPage) : 1;

function esc(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function statusBadge(string $status): string {
    $map = [
        '신규'    => 'badge--new',
        '확인중'  => 'badge--checking',
        '처리완료' => 'badge--done',
    ];
    $cls = $map[$status] ?? '';
    return '<span class="badge ' . $cls . '">' . esc($status) . '</span>';
}

$currentUrl = function(array $merge = [], array $remove = []) {
    $params = array_merge($_GET, $merge);
    foreach ($remove as $k) unset($params[$k]);
    return 'index.php?' . http_build_query(array_filter($params, fn($v) => $v !== ''));
};
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>담온유통 | 관리자 게시판</title>
<style>
/* ── Reset & Base ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Pretendard', 'Noto Sans KR', sans-serif; background: #f4f6f8; color: #1c1c1c; font-size: 14px; min-height: 100vh; }
a { color: inherit; text-decoration: none; }
button { cursor: pointer; font-family: inherit; }

/* ── Login ── */
.login-wrap { display: flex; justify-content: center; align-items: center; min-height: 100vh; background: linear-gradient(135deg, #f0f7ea 0%, #e8f5e0 100%); }
.login-box { background: #fff; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,0.12); padding: 48px 40px; width: 360px; }
.login-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
.login-logo-text { font-size: 20px; font-weight: 700; color: #2d6a2d; letter-spacing: -.5px; }
.login-logo-sub { font-size: 11px; color: #888; letter-spacing: .5px; display: block; }
.login-title { font-size: 18px; font-weight: 700; margin-bottom: 24px; }
.login-label { font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 6px; }
.login-input { width: 100%; border: 1.5px solid #ddd; border-radius: 8px; padding: 10px 14px; font-size: 14px; outline: none; transition: border-color .2s; }
.login-input:focus { border-color: #4d8b2a; }
.login-error { background: #fff3f3; color: #c00; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 16px; }
.login-btn { width: 100%; background: #4d8b2a; color: #fff; border: none; border-radius: 8px; padding: 12px; font-size: 15px; font-weight: 600; margin-top: 16px; transition: background .2s; }
.login-btn:hover { background: #3a6e1f; }

/* ── Layout ── */
.sidebar { position: fixed; left: 0; top: 0; bottom: 0; width: 220px; background: #1a2e1a; color: #c8e0c8; display: flex; flex-direction: column; z-index: 100; }
.sidebar-logo { padding: 28px 24px 20px; border-bottom: 1px solid #2a4a2a; }
.sidebar-logo-name { font-size: 18px; font-weight: 700; color: #7dc85a; }
.sidebar-logo-sub { font-size: 10px; color: #6a8a6a; letter-spacing: 1px; margin-top: 2px; }
.sidebar-label { padding: 20px 24px 8px; font-size: 10px; font-weight: 700; color: #4a7a4a; letter-spacing: 1.5px; text-transform: uppercase; }
.sidebar-nav { flex: 1; padding: 0 12px; }
.sidebar-link { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; color: #a0c8a0; font-size: 13px; font-weight: 500; margin-bottom: 2px; transition: all .15s; }
.sidebar-link:hover, .sidebar-link.active { background: #2a4a2a; color: #7dc85a; }
.sidebar-count { margin-left: auto; background: #4d8b2a; color: #fff; border-radius: 20px; padding: 1px 8px; font-size: 11px; font-weight: 700; }
.sidebar-count--new { background: #e05050; }
.sidebar-footer { padding: 20px 24px; border-top: 1px solid #2a4a2a; }
.sidebar-logout { background: none; border: 1.5px solid #3a6a3a; color: #8ab88a; border-radius: 8px; padding: 8px 16px; font-size: 13px; width: 100%; transition: all .15s; }
.sidebar-logout:hover { background: #2a4a2a; color: #fff; }

.main { margin-left: 220px; min-height: 100vh; }
.topbar { background: #fff; border-bottom: 1px solid #e8ece8; padding: 18px 32px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; }
.topbar-title { font-size: 16px; font-weight: 700; }
.topbar-meta { font-size: 12px; color: #888; }
.content { padding: 28px 32px; }

/* ── Stats ── */
.stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: #fff; border-radius: 12px; padding: 20px 24px; border: 1.5px solid #eee; }
.stat-label { font-size: 12px; color: #888; margin-bottom: 8px; }
.stat-num { font-size: 28px; font-weight: 800; color: #1a2e1a; }
.stat-num--new { color: #e05050; }
.stat-num--checking { color: #e0820c; }
.stat-num--done { color: #4d8b2a; }

/* ── Toolbar ── */
.toolbar { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
.search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 360px; }
.search-input { width: 100%; border: 1.5px solid #ddd; border-radius: 8px; padding: 9px 14px 9px 38px; font-size: 13px; outline: none; transition: border-color .2s; background: #fff; }
.search-input:focus { border-color: #4d8b2a; }
.search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #aaa; }
.filter-tabs { display: flex; gap: 6px; }
.filter-tab { border: 1.5px solid #ddd; background: #fff; border-radius: 8px; padding: 8px 16px; font-size: 13px; font-weight: 500; color: #666; transition: all .15s; }
.filter-tab.active, .filter-tab:hover { border-color: #4d8b2a; color: #4d8b2a; background: #f0f7ea; }
.filter-tab.active { font-weight: 700; }

/* ── Table ── */
.table-wrap { background: #fff; border-radius: 12px; border: 1.5px solid #eee; overflow: hidden; }
.table-head { padding: 14px 24px; border-bottom: 1px solid #f0f0f0; display: flex; align-items: center; justify-content: space-between; }
.table-count { font-size: 13px; color: #666; }
table { width: 100%; border-collapse: collapse; }
th { background: #f8faf6; padding: 12px 16px; text-align: left; font-size: 12px; font-weight: 700; color: #666; border-bottom: 1px solid #eee; white-space: nowrap; }
td { padding: 14px 16px; border-bottom: 1px solid #f4f4f4; vertical-align: middle; }
tr:last-child td { border-bottom: none; }
tr:hover td { background: #fafff6; }
.td-name { font-weight: 600; }
.td-type { color: #555; }
.td-msg { color: #666; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.td-date { color: #999; white-space: nowrap; font-size: 12px; }

.row-link { color: inherit; display: block; }
.btn-view { border: 1.5px solid #ccc; background: #fff; border-radius: 6px; padding: 5px 12px; font-size: 12px; color: #555; transition: all .15s; }
.btn-view:hover { border-color: #4d8b2a; color: #4d8b2a; }

/* ── Badge ── */
.badge { display: inline-flex; align-items: center; border-radius: 20px; padding: 3px 10px; font-size: 11px; font-weight: 700; }
.badge--new { background: #fff0f0; color: #e05050; }
.badge--checking { background: #fff7ea; color: #d4700a; }
.badge--done { background: #f0f7ea; color: #4d8b2a; }

/* ── Pagination ── */
.pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 24px; }
.page-btn { width: 36px; height: 36px; border-radius: 8px; border: 1.5px solid #ddd; background: #fff; color: #555; font-size: 13px; display: flex; align-items: center; justify-content: center; transition: all .15s; text-decoration: none; }
.page-btn:hover { border-color: #4d8b2a; color: #4d8b2a; }
.page-btn.active { background: #4d8b2a; border-color: #4d8b2a; color: #fff; font-weight: 700; }
.page-btn:disabled, .page-btn.disabled { opacity: .35; pointer-events: none; }

/* ── Detail Panel ── */
.detail-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 200; display: flex; justify-content: flex-end; }
.detail-panel { background: #fff; width: 560px; max-width: 100vw; height: 100vh; overflow-y: auto; display: flex; flex-direction: column; box-shadow: -8px 0 32px rgba(0,0,0,.15); }
.detail-header { padding: 24px 28px 20px; border-bottom: 1px solid #eee; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; background: #fff; z-index: 10; }
.detail-title { font-size: 16px; font-weight: 700; }
.detail-close { background: none; border: none; color: #888; font-size: 22px; line-height: 1; padding: 4px; }
.detail-close:hover { color: #333; }
.detail-body { padding: 28px; flex: 1; }
.detail-row { display: flex; gap: 12px; margin-bottom: 20px; }
.detail-field { flex: 1; }
.detail-label { font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.detail-value { font-size: 14px; color: #1c1c1c; font-weight: 500; }
.detail-message { background: #f8faf6; border-radius: 10px; padding: 16px 18px; font-size: 14px; line-height: 1.7; color: #333; white-space: pre-wrap; }
.detail-status-form { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 8px; }
.status-radio { display: none; }
.status-radio + label { border: 1.5px solid #ddd; border-radius: 20px; padding: 5px 14px; font-size: 12px; font-weight: 600; color: #666; cursor: pointer; transition: all .15s; }
.status-radio:checked + label { background: #4d8b2a; border-color: #4d8b2a; color: #fff; }
.status-radio[value="신규"]:checked + label { background: #e05050; border-color: #e05050; }
.status-radio[value="확인중"]:checked + label { background: #d4700a; border-color: #d4700a; }
.btn-status-save { background: #4d8b2a; color: #fff; border: none; border-radius: 8px; padding: 8px 18px; font-size: 13px; font-weight: 600; }
.btn-status-save:hover { background: #3a6e1f; }
.detail-memo { width: 100%; border: 1.5px solid #ddd; border-radius: 8px; padding: 10px 14px; font-size: 13px; resize: vertical; min-height: 80px; font-family: inherit; outline: none; }
.detail-memo:focus { border-color: #4d8b2a; }
.btn-memo-save { background: #f0f7ea; color: #4d8b2a; border: 1.5px solid #c2e0a0; border-radius: 8px; padding: 8px 18px; font-size: 13px; font-weight: 600; }
.btn-memo-save:hover { background: #ddf0cc; }
.detail-footer { padding: 20px 28px; border-top: 1px solid #eee; }
.btn-delete { background: none; border: 1.5px solid #e0b0b0; color: #c05050; border-radius: 8px; padding: 9px 20px; font-size: 13px; font-weight: 600; transition: all .15s; }
.btn-delete:hover { background: #fff3f3; }
.detail-divider { border: none; border-top: 1px solid #f0f0f0; margin: 20px 0; }

/* ── Empty ── */
.empty-msg { text-align: center; padding: 60px 20px; color: #aaa; }
.empty-msg svg { margin-bottom: 12px; display: block; margin-left: auto; margin-right: auto; }

/* ── Responsive ── */
@media (max-width: 900px) {
    .sidebar { width: 200px; }
    .main { margin-left: 200px; }
    .stats { grid-template-columns: repeat(2, 1fr); }
    .detail-panel { width: 100vw; }
}
@media (max-width: 640px) {
    .sidebar { display: none; }
    .main { margin-left: 0; }
    .topbar, .content { padding-left: 16px; padding-right: 16px; }
}
</style>
</head>
<body>

<?php if (!$isAdmin): ?>
<!-- ── 로그인 화면 ── -->
<div class="login-wrap">
  <div class="login-box">
    <div class="login-logo">
      <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
        <circle cx="18" cy="18" r="17" stroke="#4d8b2a" stroke-width="1.5" fill="none"/>
        <path d="M18 28 C18 28 10 22 10 15 C10 10.5 13.5 7 18 7 C22.5 7 26 10.5 26 15 C26 22 18 28 18 28Z" fill="#4d8b2a" opacity="0.15"/>
        <path d="M18 28 C18 28 10 22 10 15 C10 10.5 13.5 7 18 7 C22.5 7 26 10.5 26 15 C26 22 18 28 18 28Z" stroke="#4d8b2a" stroke-width="1.5" fill="none"/>
        <path d="M18 10 Q22 14 18 28" stroke="#4d8b2a" stroke-width="1" fill="none"/>
      </svg>
      <div>
        <div class="login-logo-text">담온유통</div>
        <span class="login-logo-sub">ADMIN BOARD</span>
      </div>
    </div>
    <h1 class="login-title">관리자 로그인</h1>
    <?php if (!empty($loginError)): ?>
      <div class="login-error"><?= esc($loginError) ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="action" value="login">
      <label class="login-label">비밀번호</label>
      <input class="login-input" type="password" name="password" placeholder="비밀번호 입력" autofocus>
      <button class="login-btn" type="submit">로그인</button>
    </form>
  </div>
</div>

<?php else: ?>
<!-- ── 관리자 대시보드 ── -->

<!-- Sidebar -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="sidebar-logo-name">담온유통</div>
    <div class="sidebar-logo-sub">ADMIN BOARD</div>
  </div>
  <div class="sidebar-label">메뉴</div>
  <nav class="sidebar-nav">
    <a href="index.php" class="sidebar-link active">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="9" y="1" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="1" y="9" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="9" y="9" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.5"/></svg>
      문의 게시판
      <?php if ($counts['신규'] > 0): ?>
        <span class="sidebar-count sidebar-count--new"><?= $counts['신규'] ?></span>
      <?php endif; ?>
    </a>
    <a href="products.php" class="sidebar-link">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><rect x="1" y="1" width="6" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><rect x="9" y="6" width="6" height="9" rx="1.5" stroke="currentColor" stroke-width="1.5"/><path d="M9 1h5M9 3.5h3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
      상품 관리
    </a>
    <a href="/damon_home/" class="sidebar-link" target="_blank">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M8 2L14 14H2L8 2Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/></svg>
      사이트 바로가기
    </a>
  </nav>
  <div class="sidebar-footer">
    <form method="post">
      <input type="hidden" name="action" value="logout">
      <button class="sidebar-logout" type="submit">로그아웃</button>
    </form>
  </div>
</aside>

<!-- Main -->
<div class="main">
  <div class="topbar">
    <span class="topbar-title">문의 게시판</span>
    <span class="topbar-meta">총 <?= number_format($counts['전체']) ?>건의 문의</span>
  </div>

  <div class="content">

    <!-- 통계 카드 -->
    <div class="stats">
      <div class="stat-card">
        <div class="stat-label">전체 문의</div>
        <div class="stat-num"><?= number_format($counts['전체']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">신규</div>
        <div class="stat-num stat-num--new"><?= number_format($counts['신규']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">확인중</div>
        <div class="stat-num stat-num--checking"><?= number_format($counts['확인중']) ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-label">처리완료</div>
        <div class="stat-num stat-num--done"><?= number_format($counts['처리완료']) ?></div>
      </div>
    </div>

    <!-- 검색 & 필터 -->
    <div class="toolbar">
      <form method="get" class="search-wrap">
        <?php if (!empty($_GET['status'])): ?>
          <input type="hidden" name="status" value="<?= esc($_GET['status']) ?>">
        <?php endif; ?>
        <svg class="search-icon" width="15" height="15" viewBox="0 0 15 15" fill="none">
          <circle cx="6.5" cy="6.5" r="5" stroke="#aaa" stroke-width="1.5"/>
          <path d="M10.5 10.5L14 14" stroke="#aaa" stroke-width="1.5" stroke-linecap="round"/>
        </svg>
        <input class="search-input" type="text" name="q" value="<?= esc($_GET['q'] ?? '') ?>" placeholder="이름, 연락처, 업체명 검색…">
      </form>

      <div class="filter-tabs">
        <?php foreach (['전체', '신규', '확인중', '처리완료'] as $tab): ?>
          <?php
            $isActive  = ($filterStatus === $tab) || ($tab === '전체' && !$filterStatus);
            $params    = array_filter(['status' => $tab === '전체' ? '' : $tab, 'q' => $_GET['q'] ?? '']);
            $href      = 'index.php' . ($params ? '?' . http_build_query($params) : '');
          ?>
          <a href="<?= esc($href) ?>" class="filter-tab <?= $isActive ? 'active' : '' ?>"><?= esc($tab) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- 목록 테이블 -->
    <div class="table-wrap">
      <div class="table-head">
        <span class="table-count">
          <?php if ($search): ?>
            "<?= esc($search) ?>" 검색 결과 <?= number_format($total) ?>건
          <?php else: ?>
            <?= number_format($total) ?>건
          <?php endif; ?>
        </span>
      </div>

      <?php if (empty($rows)): ?>
        <div class="empty-msg">
          <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
            <circle cx="20" cy="20" r="18" stroke="#ddd" stroke-width="2"/>
            <path d="M13 20h14M20 13v14" stroke="#ddd" stroke-width="2" stroke-linecap="round"/>
          </svg>
          문의 내역이 없습니다.
        </div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>번호</th>
              <th>이름</th>
              <th>연락처</th>
              <th>업체명</th>
              <th>유형</th>
              <th>내용 요약</th>
              <th>상태</th>
              <th>접수일</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row): ?>
            <tr>
              <td style="color:#aaa;font-size:12px"><?= $row['id'] ?></td>
              <td class="td-name"><?= esc($row['name']) ?></td>
              <td><?= esc($row['phone']) ?></td>
              <td style="color:#777"><?= esc($row['company'] ?: '-') ?></td>
              <td class="td-type"><?= esc($row['type']) ?></td>
              <td class="td-msg"><?= esc(mb_substr($row['message'], 0, 60)) ?><?= mb_strlen($row['message']) > 60 ? '…' : '' ?></td>
              <td><?= statusBadge($row['status']) ?></td>
              <td class="td-date"><?= date('m/d H:i', strtotime($row['created_at'])) ?></td>
              <td>
                <a href="<?= esc('index.php?' . http_build_query(array_merge($_GET, ['view' => $row['id']]))) ?>"
                   class="btn-view">상세</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?>
        <a href="<?= esc($currentUrl(['page' => $page - 1])) ?>" class="page-btn">&#8249;</a>
      <?php else: ?>
        <span class="page-btn disabled">&#8249;</span>
      <?php endif; ?>

      <?php
        $start = max(1, $page - 2);
        $end   = min($totalPages, $page + 2);
        for ($i = $start; $i <= $end; $i++): ?>
          <a href="<?= esc($currentUrl(['page' => $i])) ?>" class="page-btn <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
      <?php endfor; ?>

      <?php if ($page < $totalPages): ?>
        <a href="<?= esc($currentUrl(['page' => $page + 1])) ?>" class="page-btn">&#8250;</a>
      <?php else: ?>
        <span class="page-btn disabled">&#8250;</span>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ── 상세 슬라이드 패널 ── -->
<?php if ($detail): ?>
<div class="detail-overlay" id="detailOverlay">
  <div class="detail-panel">
    <div class="detail-header">
      <div>
        <div class="detail-title">문의 상세 #<?= $detail['id'] ?></div>
      </div>
      <a href="<?= esc($currentUrl([], ['view'])) ?>">
        <button class="detail-close" aria-label="닫기">×</button>
      </a>
    </div>

    <div class="detail-body">

      <!-- 기본 정보 -->
      <div class="detail-row">
        <div class="detail-field">
          <div class="detail-label">이름</div>
          <div class="detail-value"><?= esc($detail['name']) ?></div>
        </div>
        <div class="detail-field">
          <div class="detail-label">연락처</div>
          <div class="detail-value"><a href="tel:<?= esc($detail['phone']) ?>"><?= esc($detail['phone']) ?></a></div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-field">
          <div class="detail-label">업체명</div>
          <div class="detail-value"><?= esc($detail['company'] ?: '—') ?></div>
        </div>
        <div class="detail-field">
          <div class="detail-label">문의 유형</div>
          <div class="detail-value"><?= esc($detail['type']) ?></div>
        </div>
      </div>

      <div class="detail-row">
        <div class="detail-field">
          <div class="detail-label">접수일시</div>
          <div class="detail-value"><?= date('Y년 m월 d일 H:i', strtotime($detail['created_at'])) ?></div>
        </div>
      </div>

      <hr class="detail-divider">

      <!-- 문의 내용 -->
      <div class="detail-label" style="margin-bottom:10px">문의 내용</div>
      <div class="detail-message"><?= esc($detail['message']) ?></div>

      <hr class="detail-divider">

      <!-- 상태 변경 -->
      <div class="detail-label" style="margin-bottom:10px">처리 상태</div>
      <form method="post" class="detail-status-form">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= $detail['id'] ?>">
        <?php foreach (['신규', '확인중', '처리완료'] as $s): ?>
          <input class="status-radio" type="radio" name="status" id="s_<?= $s ?>" value="<?= $s ?>"
                 <?= $detail['status'] === $s ? 'checked' : '' ?>>
          <label for="s_<?= $s ?>"><?= $s ?></label>
        <?php endforeach; ?>
        <button class="btn-status-save" type="submit">저장</button>
      </form>

      <hr class="detail-divider">

      <!-- 메모 -->
      <div class="detail-label" style="margin-bottom:10px">관리자 메모</div>
      <form method="post">
        <input type="hidden" name="action" value="update_memo">
        <input type="hidden" name="id" value="<?= $detail['id'] ?>">
        <textarea class="detail-memo" name="memo" placeholder="내부용 메모 (고객에게 노출되지 않음)"><?= esc($detail['memo']) ?></textarea>
        <div style="margin-top:8px">
          <button class="btn-memo-save" type="submit">메모 저장</button>
        </div>
      </form>

    </div>

    <div class="detail-footer">
      <form method="post" onsubmit="return confirm('이 문의를 삭제하시겠습니까?')">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= $detail['id'] ?>">
        <button class="btn-delete" type="submit">문의 삭제</button>
      </form>
    </div>
  </div>
</div>
<script>
// 오버레이 배경 클릭 시 닫기
document.getElementById('detailOverlay').addEventListener('click', function(e) {
  if (e.target === this) location.href = <?= json_encode($currentUrl([], ['view'])) ?>;
});
</script>
<?php endif; ?>

<?php endif; ?>
</body>
</html>
