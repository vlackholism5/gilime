<?php
declare(strict_types=1);
/**
 * v1.7-07: Outbound stub. v1.7-10: retry/backoff for failed.
 * 대상: pending + failed(backoff 경과). channel='web'. sent/shown 건드리지 않음.
 * Run: php scripts/run_delivery_outbound_stub.php [--limit=200]
 */
require_once __DIR__ . '/../../app/inc/auth/db.php';

$opts = getopt('', ['limit:']);
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 200;

$pdo = pdo();

// Eligible: pending always; failed only when backoff window passed (1/5/15/60 min by retry_count)
$stmt = $pdo->prepare("
  SELECT id, alert_event_id, user_id, channel, status, retry_count
  FROM app_alert_deliveries
  WHERE channel = 'web'
    AND (
      status = 'pending'
      OR (
        status = 'failed'
        AND (
          (retry_count = 1 AND created_at <= NOW() - INTERVAL 1 MINUTE)
          OR (retry_count = 2 AND created_at <= NOW() - INTERVAL 5 MINUTE)
          OR (retry_count = 3 AND created_at <= NOW() - INTERVAL 15 MINUTE)
          OR (retry_count >= 4 AND created_at <= NOW() - INTERVAL 60 MINUTE)
        )
      )
    )
  ORDER BY created_at ASC
  LIMIT " . (int)$limit
);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$updateSent = $pdo->prepare("
  UPDATE app_alert_deliveries
  SET status = 'sent', delivered_at = NOW(), last_error = NULL
  WHERE id = :id AND (status = 'pending' OR status = 'failed')
");
$updateFailed = $pdo->prepare("
  UPDATE app_alert_deliveries
  SET status = 'failed', last_error = :err, retry_count = retry_count + 1, delivered_at = NULL, sent_at = NULL
  WHERE id = :id AND (status = 'pending' OR status = 'failed')
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
      $updateFailed->execute([':id' => (int)$r['id'], ':err' => substr($e->getMessage(), 0, 255) ?: 'stub_fail']);
      if ($updateFailed->rowCount() > 0) {
        $failed++;
      }
    } catch (Throwable $e2) {}
  }
}

// skipped_backoff: failed + channel=web but backoff not yet passed
$skippedStmt = $pdo->prepare("
  SELECT COUNT(*) AS c FROM app_alert_deliveries
  WHERE channel = 'web' AND status = 'failed'
    AND NOT (
      (retry_count = 1 AND created_at <= NOW() - INTERVAL 1 MINUTE)
      OR (retry_count = 2 AND created_at <= NOW() - INTERVAL 5 MINUTE)
      OR (retry_count = 3 AND created_at <= NOW() - INTERVAL 15 MINUTE)
      OR (retry_count >= 4 AND created_at <= NOW() - INTERVAL 60 MINUTE)
    )
");
$skippedStmt->execute();
$skipped_backoff = (int)$skippedStmt->fetch(PDO::FETCH_ASSOC)['c'];

echo "run_delivery_outbound_stub: processed=" . count($rows) . ", sent={$sent}, failed={$failed}, skipped_backoff={$skipped_backoff}\n";
