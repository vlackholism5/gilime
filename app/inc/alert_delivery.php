<?php
declare(strict_types=1);
/**
 * v1.4-06: Log alert delivery (web view). Requires UNIQUE (user_id, alert_event_id, channel).
 */
require_once __DIR__ . '/db.php';

function record_alert_delivery(int $eventId, int $userId, string $channel = 'web', string $status = 'shown'): void {
  $pdo = pdo();
  $pdo->prepare("
    INSERT INTO app_alert_deliveries (alert_event_id, user_id, channel, status, sent_at, created_at)
    VALUES (:eid, :uid, :channel, :status, NOW(), NOW())
    ON DUPLICATE KEY UPDATE status = :status2, sent_at = NOW()
  ")->execute([
    ':eid' => $eventId,
    ':uid' => $userId,
    ':channel' => $channel,
    ':status' => $status,
    ':status2' => $status,
  ]);
}
