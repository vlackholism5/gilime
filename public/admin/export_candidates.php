<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_admin();

$pdo = pdo();

$sourceDocId = (int)($_GET['source_doc_id'] ?? 0);
$routeLabel  = trim((string)($_GET['route_label'] ?? ''));
$format      = strtolower(trim((string)($_GET['format'] ?? 'csv')));

if ($sourceDocId <= 0 || $routeLabel === '') {
  http_response_code(400);
  exit('bad params: source_doc_id, route_label required');
}

if (!in_array($format, ['csv', 'json'], true)) {
  $format = 'csv';
}

// latest PARSE_MATCH job
$latestJobStmt = $pdo->prepare("
  SELECT id
  FROM shuttle_doc_job_log
  WHERE source_doc_id=:doc
    AND job_type='PARSE_MATCH'
    AND job_status='success'
  ORDER BY id DESC
  LIMIT 1
");
$latestJobStmt->execute([':doc' => $sourceDocId]);
$latestParseJobId = (int)($latestJobStmt->fetch()['id'] ?? 0);

if ($latestParseJobId <= 0) {
  http_response_code(404);
  exit('no PARSE_MATCH success job found');
}

// candidates
$candStmt = $pdo->prepare("
  SELECT id, source_doc_id, route_label, created_job_id, seq_in_route, raw_stop_name,
         matched_stop_id, matched_stop_name, match_score, match_method, status
  FROM shuttle_stop_candidate
  WHERE source_doc_id=:doc
    AND route_label=:rl
    AND created_job_id=:jid
  ORDER BY seq_in_route ASC, id ASC
");
$candStmt->execute([':doc' => $sourceDocId, ':rl' => $routeLabel, ':jid' => $latestParseJobId]);
$cands = $candStmt->fetchAll(PDO::FETCH_ASSOC);

function normalizeStopNameDisplay(string $s): string {
  return trim(preg_replace('/\s+/', ' ', $s));
}

// v1.7-19: 공공데이터 route_stop_master 기반 suggested_stop_id (seq_in_route로 조회)
$publicSeqToStop = [];
try {
  $exactStmt = $pdo->prepare("SELECT route_id FROM seoul_bus_route_master WHERE route_name = :exact LIMIT 1");
  $exactStmt->execute([':exact' => $routeLabel]);
  $routeRow = $exactStmt->fetch();
  if (!$routeRow) {
    $prefixStmt = $pdo->prepare("SELECT route_id FROM seoul_bus_route_master WHERE route_name LIKE CONCAT(:prefix, '%') LIMIT 1");
    $prefixStmt->execute([':prefix' => $routeLabel]);
    $routeRow = $prefixStmt->fetch();
  }
  if ($routeRow) {
    $routeId = (int)$routeRow['route_id'];
    $stopsStmt = $pdo->prepare("
      SELECT seq_in_route, stop_id, stop_name
      FROM seoul_bus_route_stop_master
      WHERE route_id = :rid
      ORDER BY seq_in_route ASC
      LIMIT 500
    ");
    $stopsStmt->execute([':rid' => $routeId]);
    foreach ($stopsStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $seq = (int)$r['seq_in_route'];
      $publicSeqToStop[$seq] = [
        'stop_id'   => (string)$r['stop_id'],
        'stop_name' => (string)($r['stop_name'] ?? ''),
      ];
    }
  }
} catch (Throwable $e) {
  // ignore
}

$rows = [];
foreach ($cands as $c) {
  $seq = (int)$c['seq_in_route'];
  $suggested = $publicSeqToStop[$seq] ?? null;
  $rows[] = [
    'candidate_id'       => (int)$c['id'],
    'seq_in_route'       => $seq,
    'raw_stop_name'      => (string)($c['raw_stop_name'] ?? ''),
    'normalized_name'    => normalizeStopNameDisplay((string)($c['raw_stop_name'] ?? '')),
    'matched_stop_id'    => trim((string)($c['matched_stop_id'] ?? '')),
    'matched_stop_name'  => trim((string)($c['matched_stop_name'] ?? '')),
    'match_method'       => trim((string)($c['match_method'] ?? '')),
    'status'             => trim((string)($c['status'] ?? '')),
    'suggested_stop_id'  => $suggested ? (string)$suggested['stop_id'] : '',
    'suggested_stop_name'=> $suggested ? (string)$suggested['stop_name'] : '',
  ];
}

if ($format === 'json') {
  header('Content-Type: application/json; charset=utf-8');
  $safeLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routeLabel);
  header('Content-Disposition: attachment; filename="candidates_' . (int)$sourceDocId . '_' . $safeLabel . '.json"');
  echo json_encode([
    'meta' => [
      'source_doc_id' => $sourceDocId,
      'route_label'   => $routeLabel,
      'parse_job_id'  => $latestParseJobId,
      'count'         => count($rows),
    ],
    'candidates' => $rows,
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit;
}

// CSV
header('Content-Type: text/csv; charset=utf-8');
$safeLabel = preg_replace('/[^a-zA-Z0-9_-]/', '_', $routeLabel);
header('Content-Disposition: attachment; filename="candidates_' . (int)$sourceDocId . '_' . $safeLabel . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, "\xEF\xBB\xBF"); // BOM for Excel UTF-8

$headers = ['candidate_id','seq_in_route','raw_stop_name','normalized_name','matched_stop_id','matched_stop_name','match_method','status','suggested_stop_id','suggested_stop_name'];
fputcsv($out, $headers);

foreach ($rows as $r) {
  fputcsv($out, [
    $r['candidate_id'],
    $r['seq_in_route'],
    $r['raw_stop_name'],
    $r['normalized_name'],
    $r['matched_stop_id'],
    $r['matched_stop_name'],
    $r['match_method'],
    $r['status'],
    $r['suggested_stop_id'],
    $r['suggested_stop_name'],
  ]);
}
fclose($out);
