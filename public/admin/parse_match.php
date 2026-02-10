<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('POST only');
}

$pdo = pdo();

$sourceDocId = (int)($_POST['source_doc_id'] ?? 0);
if ($sourceDocId <= 0) {
  http_response_code(400);
  exit('bad params: source_doc_id required');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  header('Location: ' . APP_BASE . '/admin/login.php');
  exit;
}

try {
  $pdo->beginTransaction();

  // 1) job_log: running
  $jobIns = $pdo->prepare("
    INSERT INTO shuttle_doc_job_log
      (source_doc_id, job_type, job_status, requested_by, request_note, created_at, updated_at)
    VALUES
      (:doc, 'PARSE_MATCH', 'running', :uid, 'generate candidates (PoC dummy)', NOW(), NOW())
  ");
  $jobIns->execute([':doc' => $sourceDocId, ':uid' => $userId]);
  $jobId = (int)$pdo->lastInsertId();

  // 2) 기존 후보 폐기 (doc 단위 전체)
  $sup = $pdo->prepare("
    UPDATE shuttle_stop_candidate
    SET is_active=0,
        superseded_at=NOW()
    WHERE source_doc_id=:doc
      AND is_active=1
  ");
  $sup->execute([':doc' => $sourceDocId]);

  // 3) 기존 route_stop 삭제 (PoC 기준: 불일치 방지)
  $delRoute = $pdo->prepare("DELETE FROM shuttle_route_stop WHERE source_doc_id=:doc");
  $delRoute->execute([':doc' => $sourceDocId]);

  // 4) 더미 후보 생성 (PoC)
  // route_label = R1, 3개 정류장
  $ins = $pdo->prepare("
    INSERT INTO shuttle_stop_candidate
      (source_doc_id, route_label, seq_in_route, raw_stop_name,
       match_score, match_method, match_ruleset_version, status,
       is_active, created_at, updated_at)
    VALUES
      (:doc, :rl, :seq, :name,
       0.100, 'poc_dummy', 'v0', 'pending',
       1, NOW(), NOW())
  ");

  $routeLabel = 'R1';
  $rows = [
    [1, '강남역'],
    [2, '역삼역'],
    [3, '선릉역'],
  ];

  $count = 0;
  foreach ($rows as [$seq, $name]) {
    $ins->execute([
      ':doc' => $sourceDocId,
      ':rl'  => $routeLabel,
      ':seq' => $seq,
      ':name'=> $name,
    ]);
    $count += 1;
  }

  // 5) job_log: success
  $jobUpd = $pdo->prepare("
    UPDATE shuttle_doc_job_log
    SET job_status='success',
        result_note=:note,
        updated_at=NOW()
    WHERE id=:id
  ");
  $jobUpd->execute([
    ':note' => 'generated candidates (rows=' . $count . ')',
    ':id'   => $jobId,
  ]);

  $pdo->commit();

  $_SESSION['flash'] = "PARSE_MATCH success (rows={$count}), job_id={$jobId}";
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  try {
    $fail = $pdo->prepare("
      INSERT INTO shuttle_doc_job_log
        (source_doc_id, job_type, job_status, requested_by, request_note, result_note, created_at, updated_at)
      VALUES
        (:doc, 'PARSE_MATCH', 'failed', :uid, 'generate candidates (PoC dummy)', :note, NOW(), NOW())
    ");
    $fail->execute([
      ':doc' => $sourceDocId,
      ':uid' => $userId,
      ':note'=> 'error: ' . mb_substr($e->getMessage(), 0, 240),
    ]);
  } catch (Throwable $ignore) {}

  $_SESSION['flash'] = 'PARSE_MATCH failed: ' . mb_substr($e->getMessage(), 0, 240);
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}