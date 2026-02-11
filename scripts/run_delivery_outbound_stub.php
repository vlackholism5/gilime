<?php
declare(strict_types=1);
/**
 * v1.7-07: Outbound stub — pending deliveries를 sent로 전환. 실제 발송 없음.
 * Run: php scripts/run_delivery_outbound_stub.php [--limit=200]
 */
require_once __DIR__ . '/../app/inc/db.php';

$opts = getopt('', ['limit:']);
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 200;

$pdo = pdo();
$stmt = $pdo->prepare("
  SELECT id, alert_event_id, user_id, channel FROM app_alert_deliveries
  WHERE status = 'pending'
  ORDER BY created_at ASC
  LIMIT " . (int)$limit
);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateSent = $pdo->prepare("
  UPDATE app_alert_deliveries
  SET status = 'sent', delivered_at = NOW(), last_error = NULL
  WHERE id = :id AND status = 'pending'
");
$updateFailed = $pdo->prepare("
  UPDATE app_alert_deliveries
  SET status = 'failed', last_error = :err
  WHERE id = :id AND status = 'pending'
");

$sent = 0;
$failed = 0;
foreach ($rows as $r) {
  try {
    $updateSent->execute([':id' => (int)$r['id']]);
    if ($updateSent->rowCount() > 0) {
      $sent++;
    }
  } catch (Throwable $e) {
    try {
      $updateFailed->execute([':id' => (int)$r['id'], ':err' => substr($e->getMessage(), 0, 255)]);
      if ($updateFailed->rowCount() > 0) $failed++;
    } catch (Throwable $e2) {}
  }
}

echo "run_delivery_outbound_stub: processed " . count($rows) . ", sent={$sent}, failed={$failed}\n";
