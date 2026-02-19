<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/db.php';

$pid = isset($argv[1]) ? (int)$argv[1] : 0;
if ($pid <= 0) {
  fwrite(STDERR, "Usage: php scripts/kill_mysql_process.php <process_id>\n");
  exit(1);
}

$pdo = pdo();
$pdo->exec("KILL {$pid}");
echo "killed process id={$pid}\n";
