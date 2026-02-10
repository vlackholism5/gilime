<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pw = (string)($_POST['password'] ?? '');

  $stmt = pdo()->prepare("SELECT id, password_hash, is_active FROM users WHERE email=:e LIMIT 1");
  $stmt->execute([':e' => $email]);
  $u = $stmt->fetch();

  if (!$u || (int)$u['is_active'] !== 1) {
    $error = 'Invalid account';
  } elseif (!password_verify($pw, (string)$u['password_hash'])) {
    $error = 'Invalid password';
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
  <title>Gilaime Admin Login</title>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding:24px;}
    .box{max-width:360px;margin:40px auto;border:1px solid #ddd;border-radius:10px;padding:16px;}
    label{display:block;margin-top:10px;}
    input{width:100%;padding:10px;margin-top:6px;}
    button{margin-top:14px;padding:10px 14px;}
    .err{color:#b00020;margin-top:10px;}
  </style>
</head>
<body>
  <div class="box">
    <h2>Admin Login</h2>
    <?php if ($error): ?><div class="err"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <label>Email</label>
      <input name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" />
      <label>Password</label>
      <input name="password" type="password" />
      <button type="submit">Login</button>
    </form>
    <p style="margin-top:12px;color:#666;font-size:12px;">
      PoC 기본 계정: admin@example.com / Admin123!
    </p>
  </div>
</body>
</html>
