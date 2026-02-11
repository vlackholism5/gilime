<?php
declare(strict_types=1);
/**
 * v1.4-06: Log alert delivery (web view). UNIQUE (user_id, alert_event_id, channel).
 * v1.7-05: Publish creates pending rows; here we only UPDATE pending→shown (no INSERT).
 */
require_once __DIR__ . '/db.php';

function record_alert_delivery(int $eventId, int $userId, string $channel = 'web', string $status = 'shown'): void {
  if ($userId <= 0) return;
  $pdo = pdo();
  $pdo->prepare("
    UPDATE app_alert_deliveries
    SET status = :status, sent_at = NOW()
    WHERE alert_event_id = :eid AND user_id = :uid AND channel = :channel AND status = 'pending'
  ")->execute([
    ':eid' => $eventId,
    ':uid' => $userId,
    ':channel' => $channel,
    ':status' => $status,
  ]);
}
