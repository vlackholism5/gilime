<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pw = (string)($_POST['password'] ?? '');

  $stmt = pdo()->prepare("SELECT id, password_hash, is_active FROM users WHERE email=:e LIMIT 1");
  $stmt->execute([':e' => $email]);
  $u = $stmt->fetch();

  if (!$u || (int)$u['is_active'] !== 1) {
    $error = '유효하지 않은 계정입니다';
  } elseif (!password_verify($pw, (string)$u['password_hash'])) {
    $error = '비밀번호가 올바르지 않습니다';
  } else {
    login_user((int)$u['id']);
    header('Location: ' . APP_BASE . '/admin/index.php');
    exit;
  }
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>길라임 관리자 로그인</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container py-5">
  <div class="card g-card g-max-360 mx-auto">
    <div class="card-body p-4">
    <div class="g-page-head">
      <h2 class="h4 mb-1">관리자 로그인</h2>
      <p class="helper mb-0">관리자 계정으로 로그인합니다.</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label" for="email">이메일</label>
        <input class="form-control form-control-sm" id="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
      </div>
      <div class="mb-3">
        <label class="form-label" for="password">비밀번호</label>
        <input class="form-control form-control-sm" id="password" name="password" type="password" />
      </div>
      <button class="btn btn-gilaime-primary w-100" type="submit">로그인</button>
    </form>
    <p class="text-muted-g small mt-3 mb-0">
      PoC 기본 계정: admin@example.com / Admin123!
    </p>
    </div>
  </div>
  </main>
</body>
</html>
