<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('POST only');
}

$pdo = pdo();

$sourceDocId = (int)($_POST['source_doc_id'] ?? 0);
$routeLabel  = trim((string)($_POST['route_label'] ?? ''));
$parseJobId  = (int)($_POST['parse_job_id'] ?? 0);

if ($sourceDocId <= 0 || $routeLabel === '') {
  http_response_code(400);
  exit('bad params');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  header('Location: ' . APP_BASE . '/admin/login.php');
  exit;
}

// latest PARSE_MATCH 선택 규칙 (고정): source_doc_id, job_type=PARSE_MATCH, job_status=success, ORDER BY id DESC LIMIT 1
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
$latestParseJobId = (int)(($latestJobStmt->fetch()['id'] ?? 0));

if ($parseJobId <= 0) {
  $parseJobId = $latestParseJobId;
}

if ($parseJobId <= 0) {
  $_SESSION['flash'] = 'PROMOTE blocked: no PARSE_MATCH success job found';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

// C) promote는 반드시 latest PARSE_MATCH job만 승격 허용
if ($parseJobId !== $latestParseJobId) {
  $_SESSION['flash'] = 'PROMOTE blocked: only latest PARSE_MATCH job can be promoted (requested=' . $parseJobId . ', latest=' . $latestParseJobId . ')';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

// v0.6-7: latestParseJobId <= 0 이면 승격 차단 (route_review에서도 비활성화하나 서버 이중 검사)
if ($latestParseJobId <= 0) {
  $_SESSION['flash'] = 'PROMOTE blocked: no latest PARSE_MATCH job.';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

// v0.6-7: approved 후보 중 matched_stop_id 빈 값/NULL이 1개라도 있으면 차단
$emptyCheck = $pdo->prepare("
  SELECT COUNT(*) AS cnt
  FROM shuttle_stop_candidate
  WHERE source_doc_id=:doc AND route_label=:rl AND created_job_id=:jid
    AND status='approved'
    AND (matched_stop_id IS NULL OR matched_stop_id = '')
");
$emptyCheck->execute([':doc' => $sourceDocId, ':rl' => $routeLabel, ':jid' => $parseJobId]);
if ((int)($emptyCheck->fetch()['cnt'] ?? 0) > 0) {
  $_SESSION['flash'] = 'PROMOTE blocked: approved 후보 중 matched_stop_id가 비어 있는 항목이 있습니다.';
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}

try {
  $pdo->beginTransaction();

  // 1) job_log: PROMOTE running (v0.6-9: route_label, base_job_id 저장)
  $jobIns = $pdo->prepare("
    INSERT INTO shuttle_doc_job_log
      (source_doc_id, job_type, job_status, requested_by, request_note, route_label, base_job_id, created_at, updated_at)
    VALUES
      (:doc, 'PROMOTE', 'running', :uid, :note, :rl, :base_job, NOW(), NOW())
  ");
  $jobIns->execute([
    ':doc' => $sourceDocId,
    ':uid' => $userId,
    ':note' => 'promote approved stops into shuttle_route_stop (from parse_job_id=' . $parseJobId . ')',
    ':rl' => $routeLabel,
    ':base_job' => $parseJobId,
  ]);
  $jobId = (int)$pdo->lastInsertId();

  // 2) 기존 route_stop 비활성화 (doc + route, DELETE 금지)
  $deact = $pdo->prepare("
    UPDATE shuttle_route_stop
    SET is_active=0, updated_at=NOW()
    WHERE source_doc_id=:doc AND route_label=:rl AND is_active=1
  ");
  $deact->execute([':doc' => $sourceDocId, ':rl' => $routeLabel]);

  // 3) approved 후보를 route_stop로 승격 (created_job_id = PROMOTE job_id, is_active=1)
  $ins = $pdo->prepare("
    INSERT INTO shuttle_route_stop
      (source_doc_id, route_label, stop_order, stop_id, stop_name, created_by, created_at, updated_at, created_job_id, is_active)
    SELECT
      c.source_doc_id,
      c.route_label,
      c.seq_in_route AS stop_order,
      c.matched_stop_id AS stop_id,
      c.matched_stop_name AS stop_name,
      :uid AS created_by,
      NOW(),
      NOW(),
      :jobid AS created_job_id,
      1 AS is_active
    FROM shuttle_stop_candidate c
    WHERE c.source_doc_id=:doc
      AND c.route_label=:rl
      AND c.created_job_id=:cid
      AND c.status='approved'
      AND c.matched_stop_id IS NOT NULL
      AND c.matched_stop_id <> ''
    ORDER BY c.seq_in_route
  ");
  $ins->execute([
    ':uid' => $userId,
    ':jobid' => $jobId,
    ':doc' => $sourceDocId,
    ':rl'  => $routeLabel,
    ':cid' => $parseJobId,
  ]);
  $promoted = (int)$ins->rowCount();

  // v0.6-8: promoted=0이면 success로 포장하지 않고 failed 처리 (운영 안정성)
  if ($promoted === 0) {
    $pdo->rollBack();
    $failNote = 'promoted rows=0. 가능 원인: (a)approved=0 (b)pending>0 (c)approved 중 matched_stop_id 비어 있음 (d)latest_parse_job_id=0';
    try {
      $failIns = $pdo->prepare("
        INSERT INTO shuttle_doc_job_log
          (source_doc_id, job_type, job_status, requested_by, request_note, result_note, route_label, base_job_id, created_at, updated_at)
        VALUES
          (:doc, 'PROMOTE', 'failed', :uid, :req, :note, :rl, :base_job, NOW(), NOW())
      ");
      $failIns->execute([
        ':doc' => $sourceDocId,
        ':uid' => $userId,
        ':req' => 'promote approved stops into shuttle_route_stop (from parse_job_id=' . $parseJobId . ')',
        ':note' => $failNote,
        ':rl' => $routeLabel,
        ':base_job' => $parseJobId,
      ]);
    } catch (Throwable $ignore) {}
    $_SESSION['flash'] = 'PROMOTE 실패: 승격된 정류장이 0건입니다. ' . $failNote . ' job_status=failed로 기록했습니다.';
    header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
    exit;
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
    ':note' => 'promoted approved stops into shuttle_route_stop (rows=' . $promoted . ', base_job_id=' . $parseJobId . ')',
    ':id' => $jobId,
  ]);

  $pdo->commit();

  $_SESSION['flash'] = "PROMOTE success (rows={$promoted}), job_id={$jobId}";
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  try {
    $fail = $pdo->prepare("
      INSERT INTO shuttle_doc_job_log
        (source_doc_id, job_type, job_status, requested_by, request_note, result_note, route_label, base_job_id, created_at, updated_at)
      VALUES
        (:doc, 'PROMOTE', 'failed', :uid, :req, :note, :rl, :base_job, NOW(), NOW())
    ");
    $fail->execute([
      ':doc' => $sourceDocId,
      ':uid' => $userId,
      ':req' => 'promote approved stops into shuttle_route_stop (from parse_job_id=' . $parseJobId . ')',
      ':note' => 'error: ' . mb_substr($e->getMessage(), 0, 240),
      ':rl' => $routeLabel,
      ':base_job' => $parseJobId,
    ]);
  } catch (Throwable $ignore) {}

  $_SESSION['flash'] = 'PROMOTE failed: ' . mb_substr($e->getMessage(), 0, 240);
  header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
  exit;
}
