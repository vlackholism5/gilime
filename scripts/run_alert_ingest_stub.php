<?php
declare(strict_types=1);
/**
 * v1.4-05: Batch stub — inserts 1–3 dummy app_alert_events for testing.
 * Idempotent by content_hash; no external API. Run from project root: php scripts/run_alert_ingest_stub.php
 */
require_once __DIR__ . '/../app/inc/db.php';

$dummies = [
  ['event_type' => 'strike', 'title' => '[Stub] Strike alert 1', 'body' => 'Dummy strike body.', 'hash' => 'stub_strike_1'],
  ['event_type' => 'event', 'title' => '[Stub] Event alert 1', 'body' => 'Dummy event body.', 'hash' => 'stub_event_1'],
  ['event_type' => 'update', 'title' => '[Stub] Update alert 1', 'body' => 'Dummy update body.', 'hash' => 'stub_update_1'],
];

$pdo = pdo();
$now = date('Y-m-d H:i:s');
$inserted = 0;

$stmt = $pdo->prepare("
  INSERT INTO app_alert_events (event_type, title, body, ref_type, ref_id, route_label, content_hash, published_at, created_at)
  VALUES (:etype, :title, :body, 'route', 1, 'R1', :chash, :pub, :created)
");
$check = $pdo->prepare("SELECT 1 FROM app_alert_events WHERE content_hash = :chash LIMIT 1");

foreach ($dummies as $d) {
  $contentHash = hash('sha256', $d['hash']);
  $check->execute([':chash' => $contentHash]);
  if ($check->fetch()) continue;
  $stmt->execute([
    ':etype' => $d['event_type'],
    ':title' => $d['title'],
    ':body' => $d['body'],
    ':chash' => $contentHash,
    ':pub' => $now,
    ':created' => $now,
  ]);
  $inserted++;
}

echo "run_alert_ingest_stub: inserted {$inserted} dummy event(s).\n";
