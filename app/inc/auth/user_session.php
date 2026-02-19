<?php
declare(strict_types=1);
/**
 * MVP2 temporary auth: anonymous session_id in cookie, lazy app_users, app_user_sessions.
 * Do not use for production; replace with proper auth later.
 */
require_once __DIR__ . '/db.php';

const USER_SESSION_COOKIE = 'gilaime_sid';
const USER_SESSION_LIFETIME_DAYS = 30;

function user_session_user_id(): int {
  static $userId = null;
  if ($userId !== null) return $userId;

  $token = $_COOKIE[USER_SESSION_COOKIE] ?? null;
  if ($token !== null && strlen($token) >= 16) {
    $pdo = pdo();
    $stmt = $pdo->prepare("
      SELECT s.user_id FROM app_user_sessions s
      INNER JOIN app_users u ON u.id = s.user_id
      WHERE s.session_token = :token AND s.expires_at > NOW()
    ");
    $stmt->execute([':token' => $token]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      $userId = (int)$row['user_id'];
      return $userId;
    }
  }

  $userId = user_session_create_guest();
  return $userId;
}

function user_session_create_guest(): int {
  $pdo = pdo();
  $pdo->exec("INSERT INTO app_users (display_name) VALUES ('Guest')");
  $uid = (int)$pdo->lastInsertId();
  $token = bin2hex(random_bytes(24));
  $expires = date('Y-m-d H:i:s', time() + USER_SESSION_LIFETIME_DAYS * 86400);
  $stmt = $pdo->prepare("
    INSERT INTO app_user_sessions (user_id, session_token, expires_at) VALUES (:uid, :token, :expires)
  ");
  $stmt->execute([':uid' => $uid, ':token' => $token, ':expires' => $expires]);
  setcookie(USER_SESSION_COOKIE, $token, [
    'expires' => time() + USER_SESSION_LIFETIME_DAYS * 86400,
    'path' => defined('APP_BASE') ? APP_BASE : '/',
    'samesite' => 'Lax',
  ]);
  return $uid;
}

function user_session_subscription_count(): int {
  $uid = user_session_user_id();
  $pdo = pdo();
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM app_subscriptions WHERE user_id = :uid AND is_active = 1");
  $stmt->execute([':uid' => $uid]);
  return (int)$stmt->fetchColumn();
}
