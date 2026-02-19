<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/db.php';

$pdo = pdo();

try {
  $rows = $pdo->query("SHOW FULL PROCESSLIST")->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $r) {
    $id = (string)($r['Id'] ?? '');
    $user = (string)($r['User'] ?? '');
    $host = (string)($r['Host'] ?? '');
    $db = (string)($r['db'] ?? '');
    $cmd = (string)($r['Command'] ?? '');
    $time = (string)($r['Time'] ?? '');
    $state = (string)($r['State'] ?? '');
    $info = trim((string)($r['Info'] ?? ''));
    if ($info !== '' && mb_strlen($info) > 120) {
      $info = mb_substr($info, 0, 120) . '...';
    }
    echo "id={$id} user={$user} host={$host} db={$db} cmd={$cmd} time={$time} state={$state} info={$info}\n";
  }
} catch (Throwable $e) {
  fwrite(STDERR, "ERROR: " . $e->getMessage() . PHP_EOL);
  exit(1);
}
