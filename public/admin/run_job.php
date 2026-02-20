<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/parse/pdf_parser.php';
require_once __DIR__ . '/../../app/inc/lib/observability.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('POST 요청만 허용됩니다');
}

$pdo = pdo();
$traceId = get_trace_id();
$jobStartedAt = microtime(true);

/**
 * 업로드 정책(고정)
 * - 허용 루트: public/uploads (절대 경로 realpath 기준)
 * - 화이트리스트: .pdf 확장자만, 최대 10MB
 * - path traversal(..), 외부 드라이브/심볼릭 링크 미허용
 */
const UPLOADS_ROOT_SUFFIX = '/public/uploads';

$sourceDocId = (int)($_POST['source_doc_id'] ?? 0);
if ($sourceDocId <= 0) {
  safe_log('parse_job_bad_request', $traceId, ['source_doc_id' => $sourceDocId]);
  http_response_code(400);
  exit('source_doc_id 값이 올바르지 않습니다');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  safe_log('parse_job_unauthorized', $traceId, ['source_doc_id' => $sourceDocId]);
  header('Location: ' . APP_BASE . '/admin/login.php');
  exit;
}

function updateParseStatus(PDO $pdo, int $sourceDocId, string $status): void {
  try {
    $st = $pdo->prepare("
      UPDATE shuttle_source_doc
      SET parse_status=:st, updated_at=NOW()
      WHERE id=:id
    ");
    $st->execute([':st' => $status, ':id' => $sourceDocId]);
  } catch (Throwable $ignore) {}
}

/**
 * 실패 시 job_log에 error_code 포함 기록 (운영 상태 일관화)
 */
function insertFailedJobLog(PDO $pdo, int $sourceDocId, int $userId, string $errorCode, string $errorMsg): void {
  try {
    $st = $pdo->prepare("
      INSERT INTO shuttle_doc_job_log
        (source_doc_id, job_type, job_status, requested_by, request_note, result_note, created_at, updated_at)
      VALUES
        (:doc, 'PARSE_MATCH', 'failed', :uid, 'generated candidates (pdf_parser)', :note, NOW(), NOW())
    ");
    $st->execute([
      ':doc' => $sourceDocId,
      ':uid' => $userId,
      ':note' => 'error_code=' . $errorCode . ' error=' . mb_substr($errorMsg, 0, 220),
    ]);
  } catch (Throwable $ignore) {}
}

/**
 * 운영 기준(고정)
 * - PARSE_MATCH는 shuttle_stop_candidate만 갱신한다.
 * - shuttle_route_stop은 절대 건드리지 않는다.
 *
 * 재처리(Replay) 플로우:
 * - 동일 문서 재실행 시 기존 후보(is_active=1)를 비활성화 후 새 후보 생성.
 * - UI(doc.php)는 latest job_status=success인 job_id 기준으로 표시.
 * - 실패 시 result_note에 error_code=XXX 포함하여 로그/분석 가능.
 */

/** v0.6-12: raw_stop_name → normalized (trim + collapse space) */
function normalizeStopName(string $raw): string {
  return trim(preg_replace('/\s+/', ' ', $raw));
}

/**
 * v1.7-19: route_label → route_id 매핑 후 seoul_bus_route_stop_master에서 seq_in_route로 stop_id 조회
 * 정규화/매칭: 정류장명 1개 단위 필요. 노선 정보는 route_stop_master 매칭용.
 */
function matchStopFromRouteMaster(PDO $pdo, string $routeLabel, int $seqInRoute): ?array {
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

/**
 * v0.6-11/12: raw_stop_name → seoul_bus_stop_master 조회 (인덱스 활용, 풀스캔/LIKE %...% 금지)
 * 순서: exact(1.0) → normalized(0.7) → alias→canonical 재시도(0.85) → like_prefix(0.7)
 */
function matchStopFromMaster(PDO $pdo, string $rawStopName): ?array {
  $raw = trim($rawStopName);
  if ($raw === '') return null;
  $normalized = normalizeStopName($raw);

  $exactStmt = $pdo->prepare("
    SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = :name LIMIT 1
  ");
  $likeStmt = $pdo->prepare("
    SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE CONCAT(:prefix, '%') LIMIT 1
  ");

  // 1) 정확일치
  $exactStmt->execute([':name' => $raw]);
  $row = $exactStmt->fetch();
  if ($row) {
    return [
      'stop_id' => (string)$row['stop_id'],
      'stop_name' => (string)$row['stop_name'],
      'match_score' => 1.0,
      'match_method' => 'exact',
    ];
  }

  // 2) 공백 정규화 후 일치
  if ($normalized !== $raw) {
    $exactStmt->execute([':name' => $normalized]);
    $row = $exactStmt->fetch();
    if ($row) {
      return [
        'stop_id' => (string)$row['stop_id'],
        'stop_name' => (string)$row['stop_name'],
        'match_score' => 0.7,
        'match_method' => 'normalized',
      ];
    }
  }

  // 3) v0.6-12: alias 적용 → canonical으로 exact/normalized 재시도
  $aliasStmt = $pdo->prepare("
    SELECT canonical_text FROM shuttle_stop_alias WHERE alias_text = :alias AND is_active = 1 LIMIT 1
  ");
  $aliasStmt->execute([':alias' => $normalized]);
  $aliasRow = $aliasStmt->fetch();
  if ($aliasRow) {
    $canonical = trim((string)$aliasRow['canonical_text']);
    if ($canonical !== '') {
      $exactStmt->execute([':name' => $canonical]);
      $row = $exactStmt->fetch();
      if ($row) {
        return [
          'stop_id' => (string)$row['stop_id'],
          'stop_name' => (string)$row['stop_name'],
          'match_score' => 0.85,
          'match_method' => 'alias_exact',
        ];
      }
      $canonNorm = normalizeStopName($canonical);
      if ($canonNorm !== $canonical) {
        $exactStmt->execute([':name' => $canonNorm]);
        $row = $exactStmt->fetch();
        if ($row) {
          return [
            'stop_id' => (string)$row['stop_id'],
            'stop_name' => (string)$row['stop_name'],
            'match_score' => 0.85,
            'match_method' => 'alias_normalized',
          ];
        }
      }
    }
  }

  // 4) prefix LIKE (마지막) — v0.6-14: normalized 2글자 이하이면 like_prefix 금지(과매칭 방지)
  if (mb_strlen($normalized) > 2) {
    $likeStmt->execute([':prefix' => $raw]);
    $row = $likeStmt->fetch();
    if ($row) {
      return [
        'stop_id' => (string)$row['stop_id'],
        'stop_name' => (string)$row['stop_name'],
        'match_score' => 0.7,
        'match_method' => 'like_prefix',
      ];
    }
  }

  return null;
}

// PDF 파싱 실행
// 1) shuttle_source_doc에서 file_path 조회
$docStmt = $pdo->prepare("
  SELECT file_path, ocr_status, parse_status
  FROM shuttle_source_doc
  WHERE id = :id
  LIMIT 1
");
$docStmt->execute([':id' => $sourceDocId]);
$docRow = $docStmt->fetch();

if (!$docRow) {
  safe_log('parse_job_doc_not_found', $traceId, ['source_doc_id' => $sourceDocId]);
  $_SESSION['flash'] = '문서를 찾을 수 없습니다: source_doc_id=' . $sourceDocId;
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

$filePath = trim((string)$docRow['file_path']);
if (empty($filePath)) {
  updateParseStatus($pdo, $sourceDocId, 'failed');
  insertFailedJobLog($pdo, $sourceDocId, $userId, 'PARSE_FILE_PATH_EMPTY', 'file_path empty');
  $_SESSION['flash'] = '파일 경로가 비어 있습니다: source_doc_id=' . $sourceDocId;
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

// 2) 파일 정책 검증 + 업로드 루트 고정
$projectRoot = (string)realpath(__DIR__ . '/../../');
$uploadsRoot = (string)realpath($projectRoot . '/public/uploads');
$normalizedPath = str_replace('\\', '/', $filePath);
$trimmed = ltrim($normalizedPath, '/');
$trimmed = preg_replace('#^public/uploads/#i', '', $trimmed);
$trimmed = preg_replace('#^uploads/#i', '', $trimmed);

if (strpos($trimmed, '..') !== false) {
  updateParseStatus($pdo, $sourceDocId, 'failed');
  insertFailedJobLog($pdo, $sourceDocId, $userId, 'PARSE_PATH_TRAVERSAL', 'path traversal blocked');
  $_SESSION['flash'] = '허용되지 않은 파일 경로입니다(상위 경로 탐색): source_doc_id=' . $sourceDocId;
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

$ext = strtolower((string)pathinfo($trimmed, PATHINFO_EXTENSION));
if ($ext !== 'pdf') {
  updateParseStatus($pdo, $sourceDocId, 'failed');
  insertFailedJobLog($pdo, $sourceDocId, $userId, 'PARSE_INVALID_FILE_TYPE', 'only .pdf allowed');
  $_SESSION['flash'] = '허용되지 않은 파일 확장자입니다. .pdf만 업로드할 수 있습니다.';
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

$candidatePath = $uploadsRoot . '/' . $trimmed;
$absolutePath = realpath($candidatePath);
if ($absolutePath === false || !is_file($absolutePath)) {
  updateParseStatus($pdo, $sourceDocId, 'failed');
  insertFailedJobLog($pdo, $sourceDocId, $userId, 'PARSE_FILE_NOT_FOUND', 'PDF file not found');
  $_SESSION['flash'] = 'PDF 파일을 찾을 수 없습니다: source_doc_id=' . $sourceDocId . ' (file_path=' . $filePath . ')';
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

if (strpos(str_replace('\\', '/', $absolutePath), str_replace('\\', '/', $uploadsRoot) . '/') !== 0) {
  updateParseStatus($pdo, $sourceDocId, 'failed');
  insertFailedJobLog($pdo, $sourceDocId, $userId, 'PARSE_PATH_OUTSIDE_UPLOADS', 'resolved path outside uploads root');
  $_SESSION['flash'] = '해결된 파일 경로가 uploads 루트 밖에 있습니다.';
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

$fileSize = filesize($absolutePath);
if ($fileSize !== false && $fileSize > 10 * 1024 * 1024) {
  updateParseStatus($pdo, $sourceDocId, 'failed');
  insertFailedJobLog($pdo, $sourceDocId, $userId, 'PARSE_FILE_TOO_LARGE', 'max 10MB');
  $_SESSION['flash'] = 'PDF 파일 용량이 너무 큽니다(최대 10MB).';
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

updateParseStatus($pdo, $sourceDocId, 'running');

// v1.7-14: job_log running 먼저 삽입 (상태 전이 일관화)
$jobInsEarly = $pdo->prepare("
  INSERT INTO shuttle_doc_job_log
    (source_doc_id, job_type, job_status, requested_by, request_note, created_at, updated_at)
  VALUES
    (:doc, 'PARSE_MATCH', 'running', :uid, 'generated candidates (pdf_parser)', NOW(), NOW())
");
$jobInsEarly->execute([':doc' => $sourceDocId, ':uid' => $userId]);
$jobIdEarly = (int)$pdo->lastInsertId();

safe_log('parse_job_start', $traceId, [
  'source_doc_id' => $sourceDocId,
  'job_id' => $jobIdEarly,
  'parse_status' => 'running',
  'file_path' => $filePath,
]);

// 3) PDF 파싱 실행 (v1.7-21: 구조화 스크립트 설정 시 우선 시도 → CSV로 후보 생성, null 허용)
$parseResult = null;
$structuredScript = defined('STRUCTURED_PARSE_SCRIPT') ? trim((string)STRUCTURED_PARSE_SCRIPT) : '';
if ($structuredScript !== '') {
  $scriptPath = realpath($projectRoot . '/' . $structuredScript);
  if ($scriptPath && is_file($scriptPath)) {
    $pythonCmd = defined('OCR_PYTHON_CMD') ? (string)OCR_PYTHON_CMD : 'python';
    $tmpCsv = sys_get_temp_dir() . '/gilime_parse_' . $sourceDocId . '_' . getmypid() . '.csv';
    $cmd = escapeshellarg($pythonCmd) . ' ' . escapeshellarg($scriptPath)
      . ' --input ' . escapeshellarg($absolutePath)
      . ' --output ' . escapeshellarg($tmpCsv);
    $out = [];
    @exec($cmd . ' 2>&1', $out, $code);
    if ($code === 0 && is_file($tmpCsv)) {
      $routeLabel = '운행구간';
      $stops = [];
      $fh = @fopen($tmpCsv, 'rb');
      if ($fh !== false) {
        $header = fgetcsv($fh);
        if ($header !== false) {
          while (($cols = fgetcsv($fh)) !== false) {
            $row = array_combine($header, array_pad($cols, count($header), ''));
            if ($row === false) continue;
            $rl = trim((string)($row['route_label'] ?? ''));
            if ($rl !== '') $routeLabel = $rl;
            $raw = trim((string)($row['raw_stop_name'] ?? ''));
            if ($raw === '') continue;
            $seq = (int)($row['seq_in_route'] ?? count($stops) + 1);
            $stops[] = ['seq' => $seq, 'raw_stop_name' => $raw];
          }
        }
        fclose($fh);
      }
      @unlink($tmpCsv);
      if ($stops !== []) {
        $parseResult = [
          'success' => true,
          'route_label' => $routeLabel,
          'stops' => $stops,
          'parsed_at_ms' => 0,
          'parser_version' => 'structured_csv',
        ];
      }
    }
  }
}
if ($parseResult === null) {
  $parseResult = parse_shuttle_pdf($absolutePath);
}
safe_log('parse_pdf_done', $traceId, [
  'source_doc_id' => $sourceDocId,
  'success' => $parseResult['success'] ? 1 : 0,
  'error_code' => (string)($parseResult['error_code'] ?? ''),
  'duration_ms' => (int)($parseResult['parsed_at_ms'] ?? 0),
  'parser_version' => (string)($parseResult['parser_version'] ?? ''),
]);

if (!$parseResult['success']) {
  $errCode = (string)($parseResult['error_code'] ?? 'UNKNOWN');
  $errMsg = (string)($parseResult['error'] ?? 'parse failed');
  updateParseStatus($pdo, $sourceDocId, 'failed');
  // v1.7-14: 기존 running job_log를 failed로 업데이트 (insert 대신)
  $durationMs = (int)($parseResult['parsed_at_ms'] ?? 0);
  try {
    $st = $pdo->prepare("
      UPDATE shuttle_doc_job_log
      SET job_status='failed', result_note=:note, updated_at=NOW()
      WHERE id=:id
    ");
    $st->execute([
      ':note' => 'error_code=' . $errCode . ' duration_ms=' . $durationMs . ' error=' . mb_substr($errMsg, 0, 200),
      ':id' => $jobIdEarly,
    ]);
  } catch (Throwable $ignore) {}
  safe_log('parse_job_end', $traceId, [
    'source_doc_id' => $sourceDocId,
    'job_id' => $jobIdEarly,
    'result' => 'failed',
    'error_code' => $errCode,
    'elapsed_ms' => (int)round((microtime(true) - $jobStartedAt) * 1000),
    'parse_duration_ms' => $durationMs,
  ]);
  $_SESSION['flash'] = 'PDF 파싱 실패 [' . $errCode . ']: ' . ($parseResult['error'] ?? '알 수 없는 오류');
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

// 4) 파싱 결과를 $dummy 형식으로 변환
$routeLabel = $parseResult['route_label'];
$dummy = [];
foreach ($parseResult['stops'] as $stop) {
  $dummy[] = [
    'route_label' => $routeLabel,
    'seq' => $stop['seq'],
    'raw_stop_name' => $stop['raw_stop_name'],
  ];
}

if (empty($dummy)) {
  updateParseStatus($pdo, $sourceDocId, 'failed');
  try {
    $st = $pdo->prepare("
      UPDATE shuttle_doc_job_log
      SET job_status='failed', result_note=:note, updated_at=NOW()
      WHERE id=:id
    ");
    $st->execute([
      ':note' => 'error_code=PARSE_NO_STOPS duration_ms=' . (int)($parseResult['parsed_at_ms'] ?? 0) . ' error=no stops extracted',
      ':id' => $jobIdEarly,
    ]);
  } catch (Throwable $ignore) {}
  safe_log('parse_job_end', $traceId, [
    'source_doc_id' => $sourceDocId,
    'job_id' => $jobIdEarly,
    'result' => 'failed',
    'error_code' => 'PARSE_NO_STOPS',
    'elapsed_ms' => (int)round((microtime(true) - $jobStartedAt) * 1000),
  ]);
  $_SESSION['flash'] = 'PDF에서 정류장을 추출하지 못했습니다.';
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}

try {
  // (가드) 실행 전 route_stop 개수 스냅샷
  $beforeCntStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM shuttle_route_stop
    WHERE source_doc_id=:doc
  ");
  $beforeCntStmt->execute([':doc' => $sourceDocId]);
  $beforeCnt = (int)($beforeCntStmt->fetch()['cnt'] ?? 0);

  $pdo->beginTransaction();

  // 1) job_log: jobIdEarly (이미 running으로 삽입됨) 재사용
  $jobId = $jobIdEarly;

  // 2) 기존 후보 비활성화 (doc 단위)
  $deact = $pdo->prepare("
    UPDATE shuttle_stop_candidate
    SET is_active=0, updated_at=NOW()
    WHERE source_doc_id=:doc AND is_active=1
  ");
  $deact->execute([':doc' => $sourceDocId]);

  // 3) 새 후보 생성 (created_job_id=jobId, is_active=1) + v0.6-11 자동매칭 추천
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
  $autoMatchedCnt = 0;
  foreach ($dummy as $d) {
    // v1.7-19: route_stop_master 우선 (route_label + seq) → 없으면 stop_name 기반 매칭
    $match = matchStopFromRouteMaster($pdo, $d['route_label'], (int)$d['seq']);
    if (!$match) {
      $match = matchStopFromMaster($pdo, $d['raw_stop_name']);
    }
    if ($match) $autoMatchedCnt++;
    $ins->execute([
      ':doc'    => $sourceDocId,
      ':rl'     => $d['route_label'],
      ':jid'    => $jobId,
      ':seq'    => $d['seq'],
      ':name'   => $d['raw_stop_name'],
      ':msid'   => $match ? $match['stop_id'] : null,
      ':msname' => $match ? $match['stop_name'] : null,
      ':score'  => $match ? $match['match_score'] : null,
      ':method' => $match ? $match['match_method'] : null,
    ]);
    $rows++;
  }

  // 4) job_log: success
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
      . ' route=' . (string)$routeLabel
      . ' rows=' . $rows
      . ' created_job_id=' . $jobId,
    ':id'   => $jobId,
  ]);

  $pdo->commit();
  updateParseStatus($pdo, $sourceDocId, 'success');
  $autoMatchedRatio = $rows > 0 ? round(100 * $autoMatchedCnt / $rows, 1) : 0;
  safe_log('candidate_insert_done', $traceId, [
    'source_doc_id' => $sourceDocId,
    'job_id' => $jobId,
    'rows' => $rows,
    'auto_matched_cnt' => $autoMatchedCnt,
    'auto_matched_ratio_pct' => $autoMatchedRatio,
    'route_label' => (string)$routeLabel,
  ]);

  // v0.6-22: PARSE_MATCH 성공 후 매칭 품질 지표 저장 (route_label별)
  try {
    $routeLabels = $pdo->query("
      SELECT DISTINCT route_label
      FROM shuttle_stop_candidate
      WHERE source_doc_id = {$sourceDocId} AND created_job_id = {$jobId}
    ")->fetchAll(PDO::FETCH_COLUMN);

    $metricsStmt = $pdo->prepare("
      INSERT INTO shuttle_parse_metrics
        (source_doc_id, parse_job_id, route_label, cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, alias_used_cnt, high_cnt, med_cnt, low_cnt, none_cnt)
      VALUES
        (:doc, :jid, :rl, :cand_total, :auto_matched, :low_confidence, :none_matched, :alias_used, :high, :med, :low, :none)
      ON DUPLICATE KEY UPDATE
        cand_total = VALUES(cand_total),
        auto_matched_cnt = VALUES(auto_matched_cnt),
        low_confidence_cnt = VALUES(low_confidence_cnt),
        none_matched_cnt = VALUES(none_matched_cnt),
        alias_used_cnt = VALUES(alias_used_cnt),
        high_cnt = VALUES(high_cnt),
        med_cnt = VALUES(med_cnt),
        low_cnt = VALUES(low_cnt),
        none_cnt = VALUES(none_cnt)
    ");

    foreach ($routeLabels as $rl) {
      $metricsData = $pdo->prepare("
        SELECT
          COUNT(*) AS cand_total,
          SUM(CASE WHEN match_method IS NOT NULL THEN 1 ELSE 0 END) AS auto_matched,
          SUM(CASE WHEN match_method = 'like_prefix' THEN 1 ELSE 0 END) AS low_confidence,
          SUM(CASE WHEN matched_stop_id IS NULL OR matched_stop_id = '' THEN 1 ELSE 0 END) AS none_matched,
          SUM(CASE WHEN match_method IN ('alias_exact','alias_normalized','alias_live_rematch') THEN 1 ELSE 0 END) AS alias_used,
          SUM(CASE WHEN match_method IN ('exact','alias_live_rematch','alias_exact','route_stop_master') THEN 1 ELSE 0 END) AS high,
          SUM(CASE WHEN match_method IN ('normalized','alias_normalized') THEN 1 ELSE 0 END) AS med,
          SUM(CASE WHEN match_method = 'like_prefix' THEN 1 ELSE 0 END) AS low,
          SUM(CASE WHEN match_method IS NULL THEN 1 ELSE 0 END) AS none
        FROM shuttle_stop_candidate
        WHERE source_doc_id = :doc AND created_job_id = :jid AND route_label = :rl
      ");
      $metricsData->execute([':doc' => $sourceDocId, ':jid' => $jobId, ':rl' => $rl]);
      $m = $metricsData->fetch();

      $metricsStmt->execute([
        ':doc' => $sourceDocId,
        ':jid' => $jobId,
        ':rl' => $rl,
        ':cand_total' => (int)($m['cand_total'] ?? 0),
        ':auto_matched' => (int)($m['auto_matched'] ?? 0),
        ':low_confidence' => (int)($m['low_confidence'] ?? 0),
        ':none_matched' => (int)($m['none_matched'] ?? 0),
        ':alias_used' => (int)($m['alias_used'] ?? 0),
        ':high' => (int)($m['high'] ?? 0),
        ':med' => (int)($m['med'] ?? 0),
        ':low' => (int)($m['low'] ?? 0),
        ':none' => (int)($m['none'] ?? 0),
      ]);
    }
  } catch (Throwable $metricsErr) {
    // metrics 저장 실패해도 PARSE_MATCH는 성공으로 처리 (비치명적)
    error_log('shuttle_parse_metrics save failed: ' . $metricsErr->getMessage());
  }

  // (가드) 실행 후 route_stop 개수 비교: 바뀌면 실패로 처리(운영 기준 위반)
  $afterCntStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM shuttle_route_stop
    WHERE source_doc_id=:doc
  ");
  $afterCntStmt->execute([':doc' => $sourceDocId]);
  $afterCnt = (int)($afterCntStmt->fetch()['cnt'] ?? 0);

  if ($afterCnt !== $beforeCnt) {
    // 이 파일은 route_stop을 건드릴 수 없으므로, 바뀌었다면 외부 요인(트리거/다른 코드) 가능성.
    $_SESSION['flash'] =
      "PARSE_MATCH finished but route_stop changed (before={$beforeCnt}, after={$afterCnt}). "
      . "This violates policy. Check other code/DB triggers.";
  } else {
    $_SESSION['flash'] = "파싱/매칭 성공: rows={$rows}, job_id={$jobId}";
  }
  safe_log('parse_job_end', $traceId, [
    'source_doc_id' => $sourceDocId,
    'job_id' => $jobId,
    'result' => 'success',
    'elapsed_ms' => (int)round((microtime(true) - $jobStartedAt) * 1000),
    'parse_duration_ms' => (int)($parseResult['parsed_at_ms'] ?? 0),
    'stop_cnt' => $rows,
    'auto_matched_ratio_pct' => $autoMatchedRatio,
  ]);

  $_SESSION['last_parsed_doc_id'] = $sourceDocId;
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId . '&just_parsed=1');
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  updateParseStatus($pdo, $sourceDocId, 'failed');
  safe_log('parse_job_end', $traceId, [
    'source_doc_id' => $sourceDocId,
    'result' => 'failed',
    'error_code' => 'RUN_JOB_EXCEPTION',
    'error' => mb_substr($e->getMessage(), 0, 120),
    'elapsed_ms' => (int)round((microtime(true) - $jobStartedAt) * 1000),
  ]);

  // 실패 기록(가능하면)
  try {
    $fail = $pdo->prepare("
      INSERT INTO shuttle_doc_job_log
        (source_doc_id, job_type, job_status, requested_by, request_note, result_note, created_at, updated_at)
      VALUES
        (:doc, 'PARSE_MATCH', 'failed', :uid, 'generated candidates (pdf_parser)', :note, NOW(), NOW())
    ");
    $fail->execute([
      ':doc'  => $sourceDocId,
      ':uid'  => $userId,
      ':note' => 'error_code=PARSE_RUN_EXCEPTION error=' . mb_substr($e->getMessage(), 0, 220),
    ]);
  } catch (Throwable $ignore) {}

  $_SESSION['flash'] = '파싱/매칭 실패: ' . mb_substr($e->getMessage(), 0, 240);
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}