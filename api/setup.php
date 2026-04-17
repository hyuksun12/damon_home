<?php
/**
 * 담온유통 — 자동 설치 스크립트
 * 새 컴퓨터에서 처음 한 번만 실행: http://localhost/damon_home/api/setup.php
 */

$configPath = dirname(__DIR__) . '/config.php';
$message    = '';
$success    = false;
$installed  = false;

// POST: 설치 실행
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host    = trim($_POST['host']    ?? 'localhost');
    $user    = trim($_POST['user']    ?? 'root');
    $pass    =      $_POST['pass']    ?? '';
    $dbName  = trim($_POST['dbname']  ?? 'damon_home');

    try {
        // DB 없이 연결 테스트
        $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // DB 생성
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$dbName`");

        // 테이블 생성
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `inquiries` (
                `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name`       VARCHAR(100)  NOT NULL,
                `phone`      VARCHAR(50)   NOT NULL,
                `company`    VARCHAR(200)  DEFAULT '',
                `type`       VARCHAR(100)  NOT NULL,
                `message`    TEXT          NOT NULL,
                `status`     ENUM('신규','확인중','처리완료') NOT NULL DEFAULT '신규',
                `memo`       VARCHAR(500)  DEFAULT '',
                `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX `idx_status`     (`status`),
                INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
        ");

        // config.php 자동 생성
        $configContent = "<?php\n"
            . "/**\n"
            . " * 담온유통 — DB 설정 파일 (setup.php 에서 자동 생성됨)\n"
            . " */\n"
            . "define('DB_HOST',    " . var_export($host,   true) . ");\n"
            . "define('DB_NAME',    " . var_export($dbName, true) . ");\n"
            . "define('DB_USER',    " . var_export($user,   true) . ");\n"
            . "define('DB_PASS',    " . var_export($pass,   true) . ");\n"
            . "define('DB_CHARSET', 'utf8mb4');\n";

        file_put_contents($configPath, $configContent);

        $success = true;
        $message = '설치가 완료되었습니다!';
        $installed = true;

    } catch (PDOException $e) {
        $message = 'DB 연결 실패: ' . $e->getMessage();
    } catch (Exception $e) {
        $message = '오류: ' . $e->getMessage();
    }
}

// 이미 설치되어 있는지 확인
$alreadyInstalled = file_exists($configPath);

function esc($s) { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>담온유통 — 설치</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Noto Sans KR', sans-serif; background: linear-gradient(135deg, #f0f7ea, #e8f5e0); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
.box { background: #fff; border-radius: 16px; box-shadow: 0 8px 32px rgba(0,0,0,.12); padding: 48px 40px; width: 100%; max-width: 460px; }
.logo { display: flex; align-items: center; gap: 12px; margin-bottom: 32px; }
.logo-name { font-size: 20px; font-weight: 700; color: #2d6a2d; }
.logo-sub { font-size: 11px; color: #888; letter-spacing: .5px; display: block; }
h1 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
.desc { font-size: 13px; color: #888; margin-bottom: 28px; line-height: 1.6; }
label { font-size: 12px; font-weight: 600; color: #555; display: block; margin-bottom: 6px; margin-top: 16px; }
input { width: 100%; border: 1.5px solid #ddd; border-radius: 8px; padding: 10px 14px; font-size: 14px; outline: none; transition: border-color .2s; }
input:focus { border-color: #4d8b2a; }
.hint { font-size: 11px; color: #aaa; margin-top: 4px; }
.btn { width: 100%; background: #4d8b2a; color: #fff; border: none; border-radius: 8px; padding: 13px; font-size: 15px; font-weight: 600; margin-top: 24px; cursor: pointer; transition: background .2s; }
.btn:hover { background: #3a6e1f; }
.alert { border-radius: 10px; padding: 14px 16px; font-size: 13px; margin-bottom: 20px; line-height: 1.6; }
.alert--error { background: #fff3f3; color: #c00; border: 1px solid #f5c0c0; }
.alert--success { background: #f0f7ea; color: #2d6a2d; border: 1px solid #b8dca0; }
.links { margin-top: 20px; display: flex; flex-direction: column; gap: 8px; }
.link-btn { display: block; text-align: center; padding: 10px; border-radius: 8px; font-size: 13px; font-weight: 600; text-decoration: none; border: 1.5px solid #c2e0a0; color: #4d8b2a; background: #f8fbf4; }
.link-btn:hover { background: #eaf5da; }
.warning { background: #fffbe6; border: 1px solid #ffe58a; border-radius: 10px; padding: 12px 16px; font-size: 12px; color: #7a6000; margin-bottom: 20px; line-height: 1.6; }
</style>
</head>
<body>
<div class="box">
  <div class="logo">
    <svg width="36" height="36" viewBox="0 0 36 36" fill="none">
      <circle cx="18" cy="18" r="17" stroke="#4d8b2a" stroke-width="1.5" fill="none"/>
      <path d="M18 28 C18 28 10 22 10 15 C10 10.5 13.5 7 18 7 C22.5 7 26 10.5 26 15 C26 22 18 28 18 28Z" fill="#4d8b2a" opacity="0.15"/>
      <path d="M18 28 C18 28 10 22 10 15 C10 10.5 13.5 7 18 7 C22.5 7 26 10.5 26 15 C26 22 18 28 18 28Z" stroke="#4d8b2a" stroke-width="1.5" fill="none"/>
      <path d="M18 10 Q22 14 18 28" stroke="#4d8b2a" stroke-width="1" fill="none"/>
    </svg>
    <div>
      <div class="logo-name">담온유통</div>
      <span class="logo-sub">INSTALL SETUP</span>
    </div>
  </div>

  <?php if ($installed): ?>
    <!-- 설치 완료 -->
    <div class="alert alert--success">
      ✅ <strong>설치 완료!</strong><br>
      데이터베이스와 테이블이 준비되었습니다.<br>
      config.php 파일이 자동으로 생성되었습니다.
    </div>
    <div class="links">
      <a class="link-btn" href="/damon_home/admin/">→ 관리자 게시판 바로가기</a>
      <a class="link-btn" href="/damon_home/">→ 사이트 바로가기</a>
    </div>

  <?php else: ?>
    <h1>초기 설치</h1>
    <p class="desc">새 컴퓨터에서 처음 한 번만 실행합니다.<br>MySQL 접속 정보를 입력하면 DB와 테이블을 자동으로 생성합니다.</p>

    <?php if ($alreadyInstalled): ?>
      <div class="warning">
        ⚠️ 이미 config.php 파일이 존재합니다.<br>
        재설치하면 기존 설정이 덮어씌워집니다. (데이터는 유지됩니다)
      </div>
    <?php endif; ?>

    <?php if ($message && !$success): ?>
      <div class="alert alert--error">❌ <?= esc($message) ?></div>
    <?php endif; ?>

    <form method="post">
      <label>DB 호스트</label>
      <input type="text" name="host" value="<?= esc($_POST['host'] ?? 'localhost') ?>" placeholder="localhost">

      <label>MySQL 사용자</label>
      <input type="text" name="user" value="<?= esc($_POST['user'] ?? 'root') ?>" placeholder="root">

      <label>MySQL 비밀번호</label>
      <input type="password" name="pass" placeholder="비밀번호 (없으면 빈칸)">
      <p class="hint">Laragon 기본값: 보통 빈칸 또는 root 또는 1234</p>

      <label>데이터베이스 이름</label>
      <input type="text" name="dbname" value="<?= esc($_POST['dbname'] ?? 'damon_home') ?>" placeholder="damon_home">

      <button class="btn" type="submit">설치 시작</button>
    </form>
  <?php endif; ?>
</div>
</body>
</html>
