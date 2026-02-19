<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . APP_BASE . '/admin/index.php');
  exit;
}

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

$rows = [];
$fileFormat = '';

if (!empty($_FILES['review_file']['tmp_name']) && is_uploaded_file($_FILES['review_file']['tmp_name'])) {
  $tmp = $_FILES['review_file']['tmp_name'];
  $content = file_get_contents($tmp);
  if ($content === false) {
    $_SESSION['flash'] = '파일 읽기 실패';
    header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
    exit;
  }

  $ext = strtolower(pathinfo($_FILES['review_file']['name'], PATHINFO_EXTENSION));
  if ($ext === 'json') {
    $fileFormat = 'json';
    $decoded = json_decode($content, true);
    if (!is_array($decoded)) {
      $_SESSION['flash'] = 'JSON 파싱 실패';
      header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
      exit;
    }
    $rows = $decoded['candidates'] ?? $decoded;
    if (!is_array($rows)) {
      $rows = [];
    }
  } else {
    $fileFormat = 'csv';
    $lines = preg_split('/\r\n|\r|\n/', $content);
    $header = null;
    foreach ($lines as $i => $line) {
      $cols = str_getcsv($line);
      if ($i === 0 && count($cols) > 0) {
        $header = array_map('trim', $cols);
        continue;
      }
      if ($header === null || count($cols) < 2) continue;
      $row = array_combine($header, array_pad($cols, count($header), ''));
      if ($row !== false) $rows[] = $row;
    }
  }
} else {
  $_SESSION['flash'] = '파일을 선택해 주세요.';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

// Parse rows: candidate_id, action (approve|reject), matched_stop_id (required when approve)
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

$msg = "일괄 반영 완료: 승인 {$approvedCnt}, 거절 {$rejectedCnt}, 스킵 {$skippedCnt}";
if ($errors !== []) {
  $msg .= '. 경고: ' . implode('; ', array_slice($errors, 0, 5));
  if (count($errors) > 5) $msg .= ' …';
}
$_SESSION['flash'] = $msg;

header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
exit;
