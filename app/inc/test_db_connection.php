<?php
/**
 * DB 접속 테스트 (브라우저: /gilime_mvp_01/app/inc/test_db_connection.php)
 * 접속 성공 시 "OK" 출력. 실패 시 오류 메시지 출력 후 이 파일 삭제 권장.
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

try {
  $pdo = pdo();
  echo 'OK: DB 접속 성공 (gilaime)';
} catch (Throwable $e) {
  echo '접속 실패: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
  echo '<br><br><strong>조치:</strong> app/inc/config.local.php 에서 DB_PASS를 MySQL root 비밀번호로 수정하세요.';
  echo ' XAMPP에서 root 비밀번호를 모르면 phpMyAdmin 접속 후 사용자 계정에서 확인/재설정하세요.';
}
