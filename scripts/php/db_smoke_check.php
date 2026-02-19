<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/db.php';

$pdo = pdo();

$queries = [
  'source_doc' => "SELECT COUNT(*) AS c FROM shuttle_source_doc",
  'stop_candidate' => "SELECT COUNT(*) AS c FROM shuttle_stop_candidate",
  'route_stop_active' => "SELECT COUNT(*) AS c FROM shuttle_route_stop WHERE is_active=1",
  'published_events' => "SELECT COUNT(*) AS c FROM app_alert_events WHERE published_at IS NOT NULL",
  'delivery_rows' => "SELECT COUNT(*) AS c FROM app_alert_deliveries",
  'delivery_duplicates' => "
    SELECT COUNT(*) AS c
    FROM (
      SELECT alert_event_id, user_id, channel
      FROM app_alert_deliveries
      GROUP BY alert_event_id, user_id, channel
      HAVING COUNT(*) > 1
    ) t
  ",
];

foreach ($queries as $name => $sql) {
  $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
  $cnt = isset($row['c']) ? (int)$row['c'] : 0;
  echo $name . '=' . $cnt . PHP_EOL;
}
