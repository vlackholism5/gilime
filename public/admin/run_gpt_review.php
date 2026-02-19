<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . APP_BASE . '/admin/index.php');
  exit;
}

set_time_limit(120);

$pdo = pdo();
$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  header('Location: ' . APP_BASE . '/admin/login.php');
  exit;
}

$sourceDocId = (int)($_POST['source_doc_id'] ?? 0);
$routeLabel  = trim((string)($_POST['route_label'] ?? ''));

if ($sourceDocId <= 0 || $routeLabel === '') {
  $_SESSION['flash'] = 'source_doc_id, route_label 필요';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

// GPT 설정
$gptPythonCmd = defined('GPT_PYTHON_CMD') ? GPT_PYTHON_CMD : 'python';
$gptApiKey    = defined('GPT_OPENAPI_API_KEY') ? GPT_OPENAPI_API_KEY : (getenv('OPENAI_API_KEY') ?: getenv('GILIME_OPENAPI_API_KEY') ?: '');
if ($gptApiKey === '') {
  $_SESSION['flash'] = 'GPT 검수 사용 시 config.local.php에 GPT_OPENAPI_API_KEY 설정이 필요합니다.';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
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
  $_SESSION['flash'] = 'PARSE_MATCH success job이 없습니다.';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

// candidates (export_candidates와 동일 쿼리)
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

if (empty($cands)) {
  $_SESSION['flash'] = '검수할 후보가 없습니다.';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

function normalizeStopNameDisplay(string $s): string {
  return trim(preg_replace('/\s+/', ' ', $s));
}

// v1.7-19: 공공데이터 route_stop_master 기반 suggested_stop_id
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

// 임시 JSON 생성
$tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gilime_gpt_' . getmypid();
if (!is_dir($tempDir)) {
  mkdir($tempDir, 0700, true);
}
$inputPath  = $tempDir . DIRECTORY_SEPARATOR . 'candidates.json';
$outputPath = $tempDir . DIRECTORY_SEPARATOR . 'review_results.json';

$inputJson = json_encode([
  'meta' => [
    'source_doc_id' => $sourceDocId,
    'route_label'   => $routeLabel,
    'parse_job_id'  => $latestParseJobId,
    'count'         => count($rows),
  ],
  'candidates' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents($inputPath, $inputJson);

$scriptPath = realpath(__DIR__ . '/../../scripts/python/gpt_review_pipeline.py');
if (!$scriptPath || !is_file($scriptPath)) {
  $_SESSION['flash'] = 'gpt_review_pipeline.py 스크립트를 찾을 수 없습니다.';
  @unlink($inputPath);
  @rmdir($tempDir);
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

// 환경변수 설정 (Python subprocess 상속)
putenv('OPENAI_API_KEY=' . $gptApiKey);
putenv('GILIME_OPENAPI_API_KEY=' . $gptApiKey);

$cmd = $gptPythonCmd . ' ' . escapeshellarg($scriptPath)
  . ' --input ' . escapeshellarg($inputPath)
  . ' --output ' . escapeshellarg($outputPath)
  . ' --route-label ' . escapeshellarg($routeLabel);

$output = [];
$returnCode = 0;
exec($cmd . ' 2>&1', $output, $returnCode);

@unlink($inputPath);

if ($returnCode !== 0 || !is_file($outputPath)) {
  $_SESSION['flash'] = 'GPT 검수 실행 실패: ' . implode(' ', array_slice($output, 0, 3));
  @unlink($outputPath);
  @rmdir($tempDir);
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

$resultContent = file_get_contents($outputPath);
@unlink($outputPath);
@rmdir($tempDir);

$decoded = json_decode($resultContent, true);
if (!is_array($decoded)) {
  $_SESSION['flash'] = 'GPT 검수 결과 JSON 파싱 실패';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

$rows = $decoded['candidates'] ?? $decoded;
if (!is_array($rows)) {
  $rows = [];
}

// import_candidate_review와 동일 로직으로 DB 반영
$approveStmt = $pdo->prepare("
  UPDATE shuttle_stop_candidate
  SET status='approved',
      approved_by=:uid,
      approved_at=NOW(),
      matched_stop_id=:msid,
      matched_stop_name=COALESCE(NULLIF(TRIM(:msname),''), raw_stop_name),
      match_method='batch_import',
      updated_at=NOW()
  WHERE id=:id
    AND source_doc_id=:doc
    AND route_label=:rl
    AND created_job_id=:jid
");

$rejectStmt = $pdo->prepare("
  UPDATE shuttle_stop_candidate
  SET status='rejected',
      rejected_reason=:rr,
      updated_at=NOW()
  WHERE id=:id
    AND source_doc_id=:doc
    AND route_label=:rl
    AND created_job_id=:jid
");

$approvedCnt = 0;
$rejectedCnt = 0;
$skippedCnt = 0;
$errors = [];

foreach ($rows as $idx => $r) {
  $candId = (int)($r['candidate_id'] ?? $r['id'] ?? 0);
  $action = strtolower(trim((string)($r['action'] ?? '')));
  $matchedStopId = trim((string)($r['matched_stop_id'] ?? ''));
  $matchedStopName = trim((string)($r['matched_stop_name'] ?? ''));

  if ($candId <= 0) {
    $skippedCnt++;
    continue;
  }

  if ($action === 'approve') {
    if ($matchedStopId === '') {
      $errors[] = "행 " . ($idx + 1) . ": approve 시 matched_stop_id 필수";
      $skippedCnt++;
      continue;
    }
    $approveStmt->execute([
      ':uid'   => $userId,
      ':msid'  => $matchedStopId,
      ':msname'=> $matchedStopName,
      ':id'    => $candId,
      ':doc'   => $sourceDocId,
      ':rl'    => $routeLabel,
      ':jid'   => $latestParseJobId,
    ]);
    if ($approveStmt->rowCount() > 0) $approvedCnt++;
    else $skippedCnt++;
  } elseif ($action === 'reject') {
    $rejectStmt->execute([
      ':rr' => 'batch_import (GPT 검수)',
      ':id' => $candId,
      ':doc'=> $sourceDocId,
      ':rl' => $routeLabel,
      ':jid'=> $latestParseJobId,
    ]);
    if ($rejectStmt->rowCount() > 0) $rejectedCnt++;
    else $skippedCnt++;
  } else {
    $skippedCnt++;
  }
}

$msg = "GPT 검수 완료: 승인 {$approvedCnt}, 거절 {$rejectedCnt}, 스킵 {$skippedCnt}";
if ($errors !== []) {
  $msg .= '. 경고: ' . implode('; ', array_slice($errors, 0, 5));
  if (count($errors) > 5) $msg .= ' …';
}
$_SESSION['flash'] = $msg;

header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
exit;
