<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) session_start();

/**
 * PoC: 최소 권한 체크
 * - 운영 시에는 role 체크(관리자/운영자) 추가 권장
 */
function require_admin(): void {
  if (!isset($_SESSION['user_id'])) {
    header('Location: ' . APP_BASE . '/admin/login.php');
    exit;
  }
}

function current_user_id(): int {
  return (int)($_SESSION['user_id'] ?? 0);
}

function login_user(int $userId): void {
  $_SESSION['user_id'] = $userId;
}

function logout_user(): void {
  $_SESSION = [];
  if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
      $params["path"], $params["domain"],
      (bool)$params["secure"], (bool)$params["httponly"]
    );
  }
  session_destroy();
}
