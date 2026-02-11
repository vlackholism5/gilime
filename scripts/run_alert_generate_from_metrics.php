<?php
declare(strict_types=1);
/**
 * v1.4-08: Generate app_alert_events from shuttle_parse_metrics + shuttle_doc_job_log (no external API).
 * Rules: none_matched_cnt increase -> type=update "NONE 증가"; low_confidence_cnt increase -> "LOW 증가".
 * Idempotent by content_hash. Run: php scripts/run_alert_generate_from_metrics.php
 */
require_once __DIR__ . '/../app/inc/db.php';

$pdo = pdo();

// Latest PARSE_MATCH job per doc
$latestRows = $pdo->query("
  SELECT j.source_doc_id, j.id AS job_id
  FROM shuttle_doc_job_log j
  INNER JOIN (
    SELECT source_doc_id, MAX(id) AS mid
    FROM shuttle_doc_job_log
    WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
    GROUP BY source_doc_id
  ) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
  WHERE j.job_type = 'PARSE_MATCH' AND job_status = 'success'
")->fetchAll(PDO::FETCH_ASSOC);

$inserted = 0;
$now = date('Y-m-d H:i:s');

$insStmt = $pdo->prepare("
  INSERT INTO app_alert_events (event_type, title, body, ref_type, ref_id, route_label, content_hash, published_at, created_at)
  VALUES ('update', :title, :body, 'route', :ref_id, :route_label, :chash, :pub, :created)
");
$checkStmt = $pdo->prepare("SELECT 1 FROM app_alert_events WHERE content_hash = :chash LIMIT 1");

foreach ($latestRows as $row) {
  $docId = (int)$row['source_doc_id'];
  $latestJobId = (int)$row['job_id'];

  $prevRow = $pdo->prepare("
    SELECT id FROM shuttle_doc_job_log
    WHERE source_doc_id = :did AND job_type = 'PARSE_MATCH' AND job_status = 'success' AND id < :jid
    ORDER BY id DESC LIMIT 1
  ");
  $prevRow->execute([':did' => $docId, ':jid' => $latestJobId]);
  $prev = $prevRow->fetch(PDO::FETCH_ASSOC);
  $prevJobId = $prev ? (int)$prev['id'] : 0;

  $latestMetrics = $pdo->prepare("
    SELECT route_label, none_matched_cnt, low_confidence_cnt
    FROM shuttle_parse_metrics
    WHERE parse_job_id = :jid
  ");
  $latestMetrics->execute([':jid' => $latestJobId]);
  $latestByRoute = [];
  foreach ($latestMetrics->fetchAll(PDO::FETCH_ASSOC) as $m) {
    $latestByRoute[(string)$m['route_label']] = [
      'none' => (int)$m['none_matched_cnt'],
      'low' => (int)$m['low_confidence_cnt'],
    ];
  }

  $prevByRoute = [];
  if ($prevJobId > 0) {
    $prevMetrics = $pdo->prepare("SELECT route_label, none_matched_cnt, low_confidence_cnt FROM shuttle_parse_metrics WHERE parse_job_id = :jid");
    $prevMetrics->execute([':jid' => $prevJobId]);
    foreach ($prevMetrics->fetchAll(PDO::FETCH_ASSOC) as $m) {
      $prevByRoute[(string)$m['route_label']] = [
        'none' => (int)$m['none_matched_cnt'],
        'low' => (int)$m['low_confidence_cnt'],
      ];
    }
  }

  foreach ($latestByRoute as $routeLabel => $cur) {
    $prevV = $prevByRoute[$routeLabel] ?? ['none' => 0, 'low' => 0];
    $deltaNone = $cur['none'] - $prevV['none'];
    $deltaLow = $cur['low'] - $prevV['low'];

    if ($deltaNone > 0) {
      $title = 'NONE 증가';
      $body = json_encode(['doc_id' => $docId, 'route_label' => $routeLabel, 'delta' => $deltaNone, 'prev' => $prevV['none'], 'cur' => $cur['none']], JSON_UNESCAPED_UNICODE);
      $chash = hash('sha256', "metrics_none_{$docId}_{$routeLabel}_{$latestJobId}");
      $checkStmt->execute([':chash' => $chash]);
      if (!$checkStmt->fetch()) {
        $insStmt->execute([
          ':title' => $title,
          ':body' => $body,
          ':ref_id' => $docId,
          ':route_label' => $routeLabel,
          ':chash' => $chash,
          ':pub' => $now,
          ':created' => $now,
        ]);
        $inserted++;
      }
    }
    if ($deltaLow > 0) {
      $title = 'LOW 증가';
      $body = json_encode(['doc_id' => $docId, 'route_label' => $routeLabel, 'delta' => $deltaLow, 'prev' => $prevV['low'], 'cur' => $cur['low']], JSON_UNESCAPED_UNICODE);
      $chash = hash('sha256', "metrics_low_{$docId}_{$routeLabel}_{$latestJobId}");
      $checkStmt->execute([':chash' => $chash]);
      if (!$checkStmt->fetch()) {
        $insStmt->execute([
          ':title' => $title,
          ':body' => $body,
          ':ref_id' => $docId,
          ':route_label' => $routeLabel,
          ':chash' => $chash,
          ':pub' => $now,
          ':created' => $now,
        ]);
        $inserted++;
      }
    }
  }
}

echo "run_alert_generate_from_metrics: inserted {$inserted} event(s).\n";
