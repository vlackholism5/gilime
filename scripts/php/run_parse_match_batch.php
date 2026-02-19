<?php
declare(strict_types=1);

/**
 * PARSE_MATCH batch runner (PHP only)
 *
 * Usage:
 *   c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --limit=20
 *   c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --only_failed=1 --limit=50
 *   c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --source_doc_id=123
 *   c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --dry_run=1 --limit=10
 */

require_once __DIR__ . '/../../app/inc/auth/db.php';
require_once __DIR__ . '/../../app/inc/parse/pdf_parser.php';
require_once __DIR__ . '/../../app/inc/lib/observability.php';
require_once __DIR__ . '/../../app/inc/lib/error_normalize.php';

if (php_sapi_name() !== 'cli') {
  http_response_code(405);
  exit("CLI only\n");
}

function argInt(array $argv, string $name, int $default): int {
  foreach ($argv as $a) {
    if (strpos($a, "--{$name}=") === 0) return (int)substr($a, strlen($name) + 3);
  }
  return $default;
}

function normalizeStopNameBatch(string $raw): string {
  return trim((string)preg_replace('/\s+/', ' ', $raw));
}

/** v1.7-19: route_label → route_id + seq_in_route로 route_stop_master에서 stop_id 조회 */
function matchStopFromRouteMasterBatch(PDO $pdo, string $routeLabel, int $seqInRoute): ?array {
  $rl = trim($routeLabel);
  if ($rl === '' || $seqInRoute < 1) return null;
  try {
    $routeStmt = $pdo->prepare("SELECT route_id FROM seoul_bus_route_master WHERE route_name = :name LIMIT 1");
    $routeStmt->execute([':name' => $rl]);
    $routeRow = $routeStmt->fetch();
    if (!$routeRow) {
      $routeStmt = $pdo->prepare("SELECT route_id FROM seoul_bus_route_master WHERE route_name LIKE CONCAT(:prefix, '%') LIMIT 1");
      $routeStmt->execute([':prefix' => $rl]);
      $routeRow = $routeStmt->fetch();
    }
    if (!$routeRow) return null;
    $routeId = (int)$routeRow['route_id'];
    $stopStmt = $pdo->prepare("
      SELECT stop_id, stop_name
      FROM seoul_bus_route_stop_master
      WHERE route_id = :rid AND seq_in_route = :seq
      LIMIT 1
    ");
    $stopStmt->execute([':rid' => $routeId, ':seq' => $seqInRoute]);
    $stopRow = $stopStmt->fetch();
    if (!$stopRow) return null;
    $stopName = trim((string)($stopRow['stop_name'] ?? ''));
    if ($stopName === '' || strpos($stopName, '정류장ID:') === 0) {
      return [
        'stop_id' => (string)$stopRow['stop_id'],
        'stop_name' => (string)$stopRow['stop_id'],
        'match_score' => 0.95,
        'match_method' => 'route_stop_master',
      ];
    }
    return [
      'stop_id' => (string)$stopRow['stop_id'],
      'stop_name' => $stopName,
      'match_score' => 0.95,
      'match_method' => 'route_stop_master',
    ];
  } catch (Throwable $e) {
    return null;
  }
}

function matchStopFromMasterBatch(PDO $pdo, string $rawStopName): ?array {
  $raw = trim($rawStopName);
  if ($raw === '') return null;
  $normalized = normalizeStopNameBatch($raw);

  $exactStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = :name LIMIT 1");
  $likeStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE CONCAT(:prefix, '%') LIMIT 1");
  $aliasStmt = $pdo->prepare("SELECT canonical_text FROM shuttle_stop_alias WHERE alias_text = :alias AND is_active = 1 LIMIT 1");

  $exactStmt->execute([':name' => $raw]);
  $row = $exactStmt->fetch();
  if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_score' => 1.0, 'match_method' => 'exact'];

  if ($normalized !== $raw) {
    $exactStmt->execute([':name' => $normalized]);
    $row = $exactStmt->fetch();
    if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_score' => 0.7, 'match_method' => 'normalized'];
  }

  $aliasStmt->execute([':alias' => $normalized]);
  $aliasRow = $aliasStmt->fetch();
  if ($aliasRow) {
    $canonical = trim((string)$aliasRow['canonical_text']);
    if ($canonical !== '') {
      $exactStmt->execute([':name' => $canonical]);
      $row = $exactStmt->fetch();
      if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_score' => 0.85, 'match_method' => 'alias_exact'];
      $canonNorm = normalizeStopNameBatch($canonical);
      if ($canonNorm !== $canonical) {
        $exactStmt->execute([':name' => $canonNorm]);
        $row = $exactStmt->fetch();
        if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_score' => 0.85, 'match_method' => 'alias_normalized'];
      }
    }
  }

  if (mb_strlen($normalized) > 2) {
    $likeStmt->execute([':prefix' => $raw]);
    $row = $likeStmt->fetch();
    if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_score' => 0.7, 'match_method' => 'like_prefix'];
  }
  return null;
}

function updateParseStatusBatch(PDO $pdo, int $docId, string $status): void {
  try {
    $st = $pdo->prepare("UPDATE shuttle_source_doc SET parse_status=:st, updated_at=NOW() WHERE id=:id");
    $st->execute([':st' => $status, ':id' => $docId]);
  } catch (Throwable $ignore) {}
}

function resolveBatchRequestedBy(PDO $pdo): int {
  try {
    $st = $pdo->query("SELECT id FROM users WHERE is_active=1 ORDER BY id ASC LIMIT 1");
    $row = $st ? $st->fetch() : false;
    if ($row && isset($row['id'])) return (int)$row['id'];
  } catch (Throwable $ignore) {}
  return 0;
}

function resolvePdfPathForBatch(string $projectRoot, string $filePath): ?string {
  $uploadsRoot = realpath($projectRoot . '/public/uploads');
  if ($uploadsRoot === false) return null;

  $normalizedPath = str_replace('\\', '/', $filePath);
  $trimmed = ltrim($normalizedPath, '/');
  $trimmed = (string)preg_replace('#^public/uploads/#i', '', $trimmed);
  $trimmed = (string)preg_replace('#^uploads/#i', '', $trimmed);

  if (strpos($trimmed, '..') !== false) return null;
  if (strtolower((string)pathinfo($trimmed, PATHINFO_EXTENSION)) !== 'pdf') return null;

  $candidatePath = $uploadsRoot . '/' . $trimmed;
  $absolutePath = realpath($candidatePath);
  if ($absolutePath === false || !is_file($absolutePath)) return null;

  $uploadsNorm = str_replace('\\', '/', $uploadsRoot);
  $absoluteNorm = str_replace('\\', '/', $absolutePath);
  if (strpos($absoluteNorm, $uploadsNorm . '/') !== 0) return null;

  $fileSize = filesize($absolutePath);
  if ($fileSize !== false && $fileSize > 10 * 1024 * 1024) return null;

  return $absolutePath;
}

/** @return array{ok:bool,error_code:string,rows:int,error:string} */
function runParseForDoc(PDO $pdo, int $docId, int $requestedBy, string $traceId): array {
  $startedAt = microtime(true);
  $docStmt = $pdo->prepare("SELECT id, file_path FROM shuttle_source_doc WHERE id=:id LIMIT 1");
  $docStmt->execute([':id' => $docId]);
  $doc = $docStmt->fetch();
  if (!$doc) return ['ok' => false, 'error_code' => 'PARSE_DOC_NOT_FOUND', 'rows' => 0, 'error' => 'source_doc not found'];

  $filePath = trim((string)$doc['file_path']);
  if ($filePath === '') return ['ok' => false, 'error_code' => 'PARSE_FILE_PATH_EMPTY', 'rows' => 0, 'error' => 'file_path empty'];

  $projectRoot = (string)realpath(__DIR__ . '/../..');
  $absolutePath = resolvePdfPathForBatch($projectRoot, $filePath);
  if ($absolutePath === null) return ['ok' => false, 'error_code' => 'PARSE_FILE_NOT_FOUND', 'rows' => 0, 'error' => 'invalid/missing pdf file'];

  updateParseStatusBatch($pdo, $docId, 'running');
  safe_log('parse_batch_doc_start', $traceId, ['source_doc_id' => $docId]);

  $parseResult = parse_shuttle_pdf($absolutePath);
  if (!$parseResult['success']) {
    updateParseStatusBatch($pdo, $docId, 'failed');
    $errCode = (string)($parseResult['error_code'] ?? 'PARSE_UNKNOWN');
    try {
      $fail = $pdo->prepare("
        INSERT INTO shuttle_doc_job_log
          (source_doc_id, job_type, job_status, requested_by, request_note, result_note, created_at, updated_at)
        VALUES
          (:doc, 'PARSE_MATCH', 'failed', :uid, 'batch generated candidates (pdf_parser)', :note, NOW(), NOW())
      ");
      $fail->execute([
        ':doc' => $docId,
        ':uid' => $requestedBy > 0 ? $requestedBy : null,
        ':note' => 'error_code=' . $errCode . ' error=' . mb_substr((string)($parseResult['error'] ?? 'parse failed'), 0, 220),
      ]);
    } catch (Throwable $ignore) {}
    return ['ok' => false, 'error_code' => $errCode, 'rows' => 0, 'error' => (string)($parseResult['error'] ?? 'parse failed')];
  }

  $routeLabel = (string)($parseResult['route_label'] ?? '');
  $stops = (array)($parseResult['stops'] ?? []);
  if ($routeLabel === '' || $stops === []) {
    updateParseStatusBatch($pdo, $docId, 'failed');
    try {
      $fail = $pdo->prepare("
        INSERT INTO shuttle_doc_job_log
          (source_doc_id, job_type, job_status, requested_by, request_note, result_note, created_at, updated_at)
        VALUES
          (:doc, 'PARSE_MATCH', 'failed', :uid, 'batch generated candidates (pdf_parser)', :note, NOW(), NOW())
      ");
      $fail->execute([
        ':doc' => $docId,
        ':uid' => $requestedBy > 0 ? $requestedBy : null,
        ':note' => 'error_code=STOPS_NOT_FOUND error=empty parse result',
      ]);
    } catch (Throwable $ignore) {}
    return ['ok' => false, 'error_code' => 'PARSE_NO_STOPS', 'rows' => 0, 'error' => 'empty parse result'];
  }

  $pdo->beginTransaction();
  try {
    $jobIns = $pdo->prepare("
      INSERT INTO shuttle_doc_job_log
        (source_doc_id, job_type, job_status, requested_by, request_note, created_at, updated_at)
      VALUES
        (:doc, 'PARSE_MATCH', 'running', :uid, 'batch generated candidates (pdf_parser)', NOW(), NOW())
    ");
    $jobIns->execute([':doc' => $docId, ':uid' => $requestedBy > 0 ? $requestedBy : null]);
    $jobId = (int)$pdo->lastInsertId();

    $deact = $pdo->prepare("UPDATE shuttle_stop_candidate SET is_active=0, updated_at=NOW() WHERE source_doc_id=:doc AND is_active=1");
    $deact->execute([':doc' => $docId]);

    $ins = $pdo->prepare("
      INSERT INTO shuttle_stop_candidate
        (source_doc_id, route_label, created_job_id, seq_in_route, raw_stop_name,
         matched_stop_id, matched_stop_name, match_score, match_method,
         status, is_active, created_at, updated_at)
      VALUES
        (:doc, :rl, :jid, :seq, :name,
         :msid, :msname, :score, :method,
         'pending', 1, NOW(), NOW())
    ");

    $rows = 0;
    foreach ($stops as $s) {
      $seq = (int)($s['seq'] ?? 0);
      $rawStopName = trim((string)($s['raw_stop_name'] ?? ''));
      if ($seq <= 0 || $rawStopName === '') continue;

      $match = matchStopFromRouteMasterBatch($pdo, $routeLabel, $seq);
      if (!$match) {
        $match = matchStopFromMasterBatch($pdo, $rawStopName);
      }
      $ins->execute([
        ':doc' => $docId,
        ':rl' => $routeLabel,
        ':jid' => $jobId,
        ':seq' => $seq,
        ':name' => $rawStopName,
        ':msid' => $match ? $match['stop_id'] : null,
        ':msname' => $match ? $match['stop_name'] : null,
        ':score' => $match ? $match['match_score'] : null,
        ':method' => $match ? $match['match_method'] : null,
      ]);
      $rows++;
    }

    $jobUpd = $pdo->prepare("
      UPDATE shuttle_doc_job_log
      SET job_status='success',
          result_note=:note,
          updated_at=NOW()
      WHERE id=:id
    ");
    $jobUpd->execute([
      ':note' => 'ok parser=' . (string)($parseResult['parser_version'] ?? 'unknown')
        . ' duration_ms=' . (int)($parseResult['parsed_at_ms'] ?? 0)
        . ' route=' . $routeLabel
        . ' rows=' . $rows
        . ' created_job_id=' . $jobId
        . ' elapsed_ms=' . (int)round((microtime(true) - $startedAt) * 1000),
      ':id' => $jobId,
    ]);

    $pdo->commit();
    updateParseStatusBatch($pdo, $docId, 'success');
    safe_log('parse_batch_doc_end', $traceId, ['source_doc_id' => $docId, 'result' => 'success', 'rows' => $rows]);
    return ['ok' => true, 'error_code' => '', 'rows' => $rows, 'error' => ''];
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    updateParseStatusBatch($pdo, $docId, 'failed');
    try {
      $fail = $pdo->prepare("
        INSERT INTO shuttle_doc_job_log
          (source_doc_id, job_type, job_status, requested_by, request_note, result_note, created_at, updated_at)
        VALUES
          (:doc, 'PARSE_MATCH', 'failed', :uid, 'batch generated candidates (pdf_parser)', :note, NOW(), NOW())
      ");
      $fail->execute([
        ':doc' => $docId,
        ':uid' => $requestedBy > 0 ? $requestedBy : null,
        ':note' => 'error_code=BATCH_RUN_EXCEPTION error=' . mb_substr($e->getMessage(), 0, 220),
      ]);
    } catch (Throwable $ignore) {}
    return ['ok' => false, 'error_code' => 'PARSE_RUN_EXCEPTION', 'rows' => 0, 'error' => mb_substr($e->getMessage(), 0, 220)];
  }
}

$sourceDocId = argInt($argv, 'source_doc_id', 0);
$limit = max(1, argInt($argv, 'limit', 20));
$onlyFailed = argInt($argv, 'only_failed', 0) === 1;
$dryRun = argInt($argv, 'dry_run', 0) === 1;
$traceId = 'trc_batch_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));

$pdo = pdo();
$requestedBy = resolveBatchRequestedBy($pdo);
$params = [];
if ($sourceDocId > 0) {
  $sql = "
    SELECT id, file_path, parse_status, 'source_doc_id_override' AS selected_reason
    FROM shuttle_source_doc
    WHERE id=:id
    LIMIT 1
  ";
  $params[':id'] = $sourceDocId;
} elseif ($onlyFailed) {
  $sql = "
    SELECT d.id, d.file_path, d.parse_status,
      CASE
        WHEN d.parse_status = 'failed' THEN 'parse_status_failed'
        ELSE 'legacy_failed_job'
      END AS selected_reason
    FROM shuttle_source_doc d
    WHERE d.parse_status = 'failed'
       OR EXISTS (
         SELECT 1
         FROM shuttle_doc_job_log j
         WHERE j.source_doc_id = d.id
           AND j.job_type = 'PARSE_MATCH'
           AND j.job_status = 'failed'
       )
    ORDER BY d.id ASC
    LIMIT {$limit}
  ";
} else {
  // 계획 정책: only_failed=0 기본은 pending/failed 문서 재처리 대상
  $sql = "
    SELECT id, file_path, parse_status, 'parse_status_pending_or_failed' AS selected_reason
    FROM shuttle_source_doc
    WHERE parse_status IN ('pending', 'failed')
    ORDER BY id ASC
    LIMIT {$limit}
  ";
}
$st = $pdo->prepare($sql);
$st->execute($params);
$docs = $st->fetchAll();

if (!$docs) {
  echo json_encode(['ok' => true, 'trace_id' => $traceId, 'message' => 'no target docs'], JSON_UNESCAPED_UNICODE) . PHP_EOL;
  exit(0);
}

$stats = [
  'ok' => true,
  'trace_id' => $traceId,
  'selected' => count($docs),
  'processed' => 0,
  'success' => 0,
  'failed' => 0,
  'rows_inserted' => 0,
  'fail_topn' => [],
  'failed_details' => [],
  'selected_by_status' => [],
  'selected_reason_top' => [],
  'selection_policy' => $sourceDocId > 0
    ? 'source_doc_id_override'
    : ($onlyFailed
      ? 'only_failed=1 => parse_status=failed OR legacy_failed_job'
      : 'only_failed=0 => parse_status IN (pending,failed)'),
  'dry_run' => $dryRun,
];
$failCounts = [];
$selectedByStatus = [];
$selectedByReason = [];

foreach ($docs as $doc) {
  $docId = (int)$doc['id'];
  $status = (string)($doc['parse_status'] ?? 'UNKNOWN');
  $reason = (string)($doc['selected_reason'] ?? 'unknown_reason');
  $selectedByStatus[$status] = ($selectedByStatus[$status] ?? 0) + 1;
  $selectedByReason[$reason] = ($selectedByReason[$reason] ?? 0) + 1;
  if ($dryRun) {
    $stats['processed']++;
    continue;
  }
  $ret = runParseForDoc($pdo, $docId, $requestedBy, $traceId);
  $stats['processed']++;
  if ($ret['ok']) {
    $stats['success']++;
    $stats['rows_inserted'] += (int)$ret['rows'];
  } else {
    $stats['failed']++;
    $code = $ret['error_code'] !== '' ? $ret['error_code'] : 'UNKNOWN';
    $failCounts[$code] = ($failCounts[$code] ?? 0) + 1;
    if (count($stats['failed_details']) < 10) {
      $stats['failed_details'][] = [
        'source_doc_id' => $docId,
        'error_code' => $code,
        'error' => (string)($ret['error'] ?? ''),
      ];
    }
  }
}

arsort($failCounts);
arsort($selectedByStatus);
arsort($selectedByReason);
$top = [];
foreach (array_slice($failCounts, 0, 5, true) as $code => $cnt) {
  $top[] = ['error_code' => $code, 'cnt' => $cnt];
}
$reasonTop = [];
foreach (array_slice($selectedByReason, 0, 5, true) as $reason => $cnt) {
  $reasonTop[] = ['reason' => $reason, 'cnt' => $cnt];
}
$stats['fail_topn'] = $top;
$stats['requested_by'] = $requestedBy;
$stats['selected_by_status'] = $selectedByStatus;
$stats['selected_reason_top'] = $reasonTop;

if ($dryRun) {
  echo '[DRY-RUN] selection_policy=' . $stats['selection_policy'] . PHP_EOL;
  echo '[DRY-RUN] selected_by_status=' . json_encode($selectedByStatus, JSON_UNESCAPED_UNICODE) . PHP_EOL;
  echo '[DRY-RUN] selected_reason_top=' . json_encode($reasonTop, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo json_encode($stats, JSON_UNESCAPED_UNICODE) . PHP_EOL;

