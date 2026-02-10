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
  exit('bad source_doc_id');
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId <= 0) {
  header('Location: ' . APP_BASE . '/admin/login.php');
  exit;
}

/**
 * 운영 기준(고정)
 * - PARSE_MATCH는 shuttle_stop_candidate만 갱신한다.
 * - shuttle_route_stop은 절대 건드리지 않는다.
 */

// PoC 더미 파서: route_label=R1 + 3개 정류장 샘플
$dummy = [
  ['route_label' => 'R1', 'seq' => 1, 'raw_stop_name' => '강남역'],
  ['route_label' => 'R1', 'seq' => 2, 'raw_stop_name' => '역삼역'],
  ['route_label' => 'R1', 'seq' => 3, 'raw_stop_name' => '선릉역'],
];

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

  // 1) job_log: running
  $jobIns = $pdo->prepare("
    INSERT INTO shuttle_doc_job_log
      (source_doc_id, job_type, job_status, requested_by, request_note, created_at, updated_at)
    VALUES
      (:doc, 'PARSE_MATCH', 'running', :uid, 'generated candidates (PoC)', NOW(), NOW())
  ");
  $jobIns->execute([':doc' => $sourceDocId, ':uid' => $userId]);
  $jobId = (int)$pdo->lastInsertId();

  // 2) 기존 후보 비활성화 (doc 단위)
  $deact = $pdo->prepare("
    UPDATE shuttle_stop_candidate
    SET is_active=0, updated_at=NOW()
    WHERE source_doc_id=:doc AND is_active=1
  ");
  $deact->execute([':doc' => $sourceDocId]);

  // 3) 새 후보 생성 (created_job_id=jobId, is_active=1)
  $ins = $pdo->prepare("
    INSERT INTO shuttle_stop_candidate
      (source_doc_id, route_label, created_job_id, seq_in_route, raw_stop_name, status, is_active, created_at, updated_at)
    VALUES
      (:doc, :rl, :jid, :seq, :name, 'pending', 1, NOW(), NOW())
  ");

  $rows = 0;
  foreach ($dummy as $d) {
    $ins->execute([
      ':doc'  => $sourceDocId,
      ':rl'   => $d['route_label'],
      ':jid'  => $jobId,
      ':seq'  => $d['seq'],
      ':name' => $d['raw_stop_name'],
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
    ':note' => 'generated candidates (rows=' . $rows . '), created_job_id=' . $jobId,
    ':id'   => $jobId,
  ]);

  $pdo->commit();

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
    $_SESSION['flash'] = "PARSE_MATCH success (rows={$rows}), job_id={$jobId}";
  }

  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();

  // 실패 기록(가능하면)
  try {
    $fail = $pdo->prepare("
      INSERT INTO shuttle_doc_job_log
        (source_doc_id, job_type, job_status, requested_by, request_note, result_note, created_at, updated_at)
      VALUES
        (:doc, 'PARSE_MATCH', 'failed', :uid, 'generated candidates (PoC)', :note, NOW(), NOW())
    ");
    $fail->execute([
      ':doc'  => $sourceDocId,
      ':uid'  => $userId,
      ':note' => 'error: ' . mb_substr($e->getMessage(), 0, 240),
    ]);
  } catch (Throwable $ignore) {}

  $_SESSION['flash'] = 'PARSE_MATCH failed: ' . mb_substr($e->getMessage(), 0, 240);
  header('Location: ' . APP_BASE . '/admin/doc.php?id=' . $sourceDocId);
  exit;
}