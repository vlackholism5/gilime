<?php
declare(strict_types=1);
/**
 * v1.7-11: Real-data ingest 1종 — shuttle_parse_metrics + shuttle_doc_job_log 기반으로
 * app_alert_events에 '[Metrics] Review needed' 이벤트 생성. Idempotent by content_hash.
 * Run: php scripts/run_alert_ingest_real_metrics.php [--since_minutes=1440] [--limit=200]
 */
require_once __DIR__ . '/../app/inc/db.php';

$opts = getopt('', ['since_minutes:', 'limit:']);
$sinceMinutes = isset($opts['since_minutes']) ? max(1, (int)$opts['since_minutes']) : 1440;
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 200;

$pdo = pdo();

// 최근 PARSE_MATCH success job (since_minutes 이내), 최대 limit 건
$jobs = $pdo->prepare("
  SELECT j.source_doc_id, j.id AS job_id, j.updated_at
  FROM shuttle_doc_job_log j
  WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
    AND j.updated_at >= NOW() - INTERVAL :since MINUTE
  ORDER BY j.id DESC
  LIMIT " . (int)$limit
);
$jobs->execute([':since' => $sinceMinutes]);
$jobRows = $jobs->fetchAll(PDO::FETCH_ASSOC);

$insStmt = $pdo->prepare("
  INSERT IGNORE INTO app_alert_events (event_type, title, body, ref_type, ref_id, route_label, content_hash, published_at, created_at)
  VALUES ('update', :title, :body, 'route', :ref_id, :route_label, :chash, NOW(), NOW())
");

$inserted = 0;
$skipped = 0;
$now = date('Y-m-d H:i:s');

foreach ($jobRows as $row) {
  $docId = (int)$row['source_doc_id'];
  $jobId = (int)$row['job_id'];

  $metrics = $pdo->prepare("
    SELECT route_label, none_matched_cnt, low_confidence_cnt, cand_total
    FROM shuttle_parse_metrics
    WHERE parse_job_id = :jid
  ");
  $metrics->execute([':jid' => $jobId]);
  $metricsRows = $metrics->fetchAll(PDO::FETCH_ASSOC);

  foreach ($metricsRows as $m) {
    $routeLabel = (string)$m['route_label'];
    $none = (int)($m['none_matched_cnt'] ?? 0);
    $low = (int)($m['low_confidence_cnt'] ?? 0);
    $cand = (int)($m['cand_total'] ?? 0);
    $body = json_encode([
      'doc_id' => $docId,
      'route_label' => $routeLabel,
      'job_id' => $jobId,
      'none_matched_cnt' => $none,
      'low_confidence_cnt' => $low,
      'cand_total' => $cand,
    ], JSON_UNESCAPED_UNICODE);
    $title = '[Metrics] Review needed';
    $chash = hash('sha256', "metrics|{$docId}|{$routeLabel}|{$jobId}");

    $insStmt->execute([
      ':title' => $title,
      ':body' => $body,
      ':ref_id' => $docId,
      ':route_label' => $routeLabel,
      ':chash' => $chash,
    ]);
    if ($insStmt->rowCount() > 0) {
      $inserted++;
    } else {
      $skipped++;
    }
  }
}

echo "run_alert_ingest_real_metrics: inserted={$inserted}, skipped_duplicate={$skipped}\n";
