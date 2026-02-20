<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

// 진단: doc.php?ping=1 또는 doc_ping.php → require 전 종료 (쿼리 인코딩 이슈 배제)
$qs = $_SERVER['QUERY_STRING'] ?? '';
if (isset($_GET['ping']) || strpos($qs, 'ping') !== false) {
  header('Content-Type: text/plain; charset=UTF-8');
  echo 'pong';
  exit;
}

// 미처리 예외 시 invalid response 방지 (개발 환경 진단용)
set_exception_handler(function (Throwable $e) {
  if (!headers_sent()) {
    header('Content-Type: text/plain; charset=UTF-8');
    http_response_code(500);
  }
  echo 'Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine();
  exit;
});

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/lib/error_normalize.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$pdo = pdo();

$id = (int)($_GET['id'] ?? 0);
$justParsed = isset($_GET['just_parsed']) && $_GET['just_parsed'] === '1';
if ($id <= 0) { http_response_code(400); exit('bad id'); }

$stmt = $pdo->prepare("SELECT * FROM shuttle_source_doc WHERE id=:id");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('not found'); }

// jobs
$jobsStmt = $pdo->prepare("SELECT * FROM shuttle_doc_job_log WHERE source_doc_id=:id ORDER BY id DESC LIMIT 50");
$jobsStmt->execute([':id' => $id]);
$jobs = $jobsStmt->fetchAll();

$lastParseJobStatus = '';
$lastParseErrorCode = '';
$lastParseDurationMs = null;
$lastParseRouteLabel = '';
$failedTopN = [];

// latest PARSE_MATCH 선택 규칙 (고정): source_doc_id, job_type=PARSE_MATCH, job_status=success, ORDER BY id DESC LIMIT 1
$latestJobStmt = $pdo->prepare("
  SELECT id
  FROM shuttle_doc_job_log
  WHERE source_doc_id=:id
    AND job_type='PARSE_MATCH'
    AND job_status='success'
  ORDER BY id DESC
  LIMIT 1
");
$latestJobStmt->execute([':id' => $id]);
$latestParseJobId = (int)(($latestJobStmt->fetch()['id'] ?? 0));

foreach ($jobs as $j) {
  if ((string)($j['job_type'] ?? '') !== 'PARSE_MATCH') continue;
  $lastParseJobStatus = (string)($j['job_status'] ?? '');
  $note = (string)($j['result_note'] ?? '');
  if ($lastParseJobStatus === 'failed') {
    $lastParseErrorCode = normalize_error_code($note);
  }
  if (preg_match('/duration_ms=(\d+)/', $note, $m)) {
    $lastParseDurationMs = (int)$m[1];
  }
  if (preg_match('/route=([^\s]+)/', $note, $m)) {
    $lastParseRouteLabel = trim((string)$m[1]);
  }
  break;
}
// parse_status=failed인데 위에서 error_code를 못 찾은 경우(최신 job이 success 등): 가장 최근 failed PARSE_MATCH에서 표시용 값 추출
if (($doc['parse_status'] ?? '') === 'failed' && $lastParseErrorCode === '') {
  foreach ($jobs as $j) {
    if ((string)($j['job_type'] ?? '') !== 'PARSE_MATCH') continue;
    if ((string)($j['job_status'] ?? '') !== 'failed') continue;
    $note = (string)($j['result_note'] ?? '');
    $lastParseErrorCode = normalize_error_code($note);
    if ($lastParseDurationMs === null && preg_match('/duration_ms=(\d+)/', $note, $m)) {
      $lastParseDurationMs = (int)$m[1];
    }
    if ($lastParseRouteLabel === '' && preg_match('/route=([^\s]+)/', $note, $m)) {
      $lastParseRouteLabel = trim((string)$m[1]);
    }
    break;
  }
  if ($lastParseErrorCode === '') {
    $lastParseErrorCode = 'UNKNOWN';
  }
}

foreach ($jobs as $j) {
  $isParseFailed = ((string)($j['job_type'] ?? '') === 'PARSE_MATCH') && ((string)($j['job_status'] ?? '') === 'failed');
  if (!$isParseFailed) continue;
  $note = (string)($j['result_note'] ?? '');
  $code = normalize_error_code($note);
  $failedTopN[$code] = ($failedTopN[$code] ?? 0) + 1;
}
arsort($failedTopN);
if (count($failedTopN) > 5) {
  $failedTopN = array_slice($failedTopN, 0, 5, true);
}

// v0.6-25: 직전 PARSE_MATCH job_id (delta 계산용)
$prevParseJobId = 0;
if ($latestParseJobId > 0) {
  $prevJobStmt = $pdo->prepare("
    SELECT id
    FROM shuttle_doc_job_log
    WHERE source_doc_id=:id
      AND job_type='PARSE_MATCH'
      AND job_status='success'
      AND id < :latest
    ORDER BY id DESC
    LIMIT 1
  ");
  $prevJobStmt->execute([':id' => $id, ':latest' => $latestParseJobId]);
  $prevParseJobId = (int)(($prevJobStmt->fetch()['id'] ?? 0));
}

// v0.6-22: PARSE_MATCH 매칭 품질 지표 (latest job 기준)
$metrics = [];
$prevMetricsByRoute = [];
if ($latestParseJobId > 0) {
  $metricsStmt = $pdo->prepare("
    SELECT route_label, cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, alias_used_cnt, high_cnt, med_cnt, low_cnt, none_cnt
    FROM shuttle_parse_metrics
    WHERE parse_job_id = :jid
    ORDER BY route_label
  ");
  $metricsStmt->execute([':jid' => $latestParseJobId]);
  $metrics = $metricsStmt->fetchAll();
  // v0.6-28: 리스크 우선 정렬 (low_confidence_cnt DESC → none_matched_cnt DESC → cand_total DESC)
  usort($metrics, function ($a, $b) {
    $c = (int)$b['low_confidence_cnt'] - (int)$a['low_confidence_cnt'];
    if ($c !== 0) return $c;
    $c = (int)$b['none_matched_cnt'] - (int)$a['none_matched_cnt'];
    if ($c !== 0) return $c;
    return (int)$b['cand_total'] - (int)$a['cand_total'];
  });

  if ($prevParseJobId > 0) {
    $prevMetricsStmt = $pdo->prepare("
      SELECT route_label, auto_matched_cnt, low_confidence_cnt, none_matched_cnt
      FROM shuttle_parse_metrics
      WHERE parse_job_id = :jid
    ");
    $prevMetricsStmt->execute([':jid' => $prevParseJobId]);
    foreach ($prevMetricsStmt->fetchAll() as $row) {
      $prevMetricsByRoute[(string)$row['route_label']] = $row;
    }
  }
}

// v0.6-27: 운영 경고용 delta 합계 (route_label 합산)
$totalLowDelta = 0;
$totalNoneDelta = 0;
if ($prevParseJobId > 0 && $metrics) {
  foreach ($metrics as $m) {
    $rl = (string)$m['route_label'];
    $prev = $prevMetricsByRoute[$rl] ?? null;
    if ($prev !== null) {
      $totalLowDelta += (int)$m['low_confidence_cnt'] - (int)$prev['low_confidence_cnt'];
      $totalNoneDelta += (int)$m['none_matched_cnt'] - (int)$prev['none_matched_cnt'];
    }
  }
}

// v0.6-7: Routes 기준 = latest PARSE_MATCH 스냅샷 (승인/승격과 동일 규칙, 혼동 방지)
if ($latestParseJobId > 0) {
  $routeStmt = $pdo->prepare("
    SELECT DISTINCT route_label
    FROM shuttle_stop_candidate
    WHERE source_doc_id = :id
      AND created_job_id = :jid
    ORDER BY route_label ASC
  ");
  $routeStmt->execute([':id' => $id, ':jid' => $latestParseJobId]);
  $routes = $routeStmt->fetchAll();
} else {
  $routes = [];
}

// v0.6-29: Review Progress (latest job) - route_label별 검수 진행률 (집계 1회)
$reviewProgress = [];
if ($latestParseJobId > 0) {
  $progressStmt = $pdo->prepare("
    SELECT route_label,
      COUNT(*) AS cand_total,
      SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
      SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_cnt,
      SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_cnt
    FROM shuttle_stop_candidate
    WHERE source_doc_id = :id AND created_job_id = :jid
    GROUP BY route_label
  ");
  $progressStmt->execute([':id' => $id, ':jid' => $latestParseJobId]);
  $reviewProgress = $progressStmt->fetchAll();
  foreach ($reviewProgress as &$row) {
    $ct = (int)$row['cand_total'];
    $ac = (int)$row['approved_cnt'];
    $rc = (int)$row['rejected_cnt'];
    $row['done_cnt'] = $ac + $rc;
    $row['done_rate'] = $ct > 0 ? (int)round(($row['done_cnt'] / $ct) * 100) : 0;
  }
  unset($row);
  usort($reviewProgress, function ($a, $b) {
    $c = (int)$b['pending_cnt'] - (int)$a['pending_cnt'];
    if ($c !== 0) return $c;
    return (int)$b['cand_total'] - (int)$a['cand_total'];
  });
}

// v0.6-31: only_risky 토글 (GET)
$onlyRisky = (int)($_GET['only_risky'] ?? 0);

// v0.6-31: Next Actions Summary (by route) - 1쿼리
$nextActionsSummary = [];
if ($latestParseJobId > 0) {
  $summaryStmt = $pdo->prepare("
    SELECT route_label,
      COUNT(*) AS pending_total,
      SUM(CASE WHEN match_method = 'like_prefix' THEN 1 ELSE 0 END) AS pending_low_cnt,
      SUM(CASE WHEN match_method IS NULL THEN 1 ELSE 0 END) AS pending_none_cnt
    FROM shuttle_stop_candidate
    WHERE source_doc_id = :doc_id
      AND created_job_id = :jid
      AND status = 'pending'
    GROUP BY route_label
    ORDER BY (SUM(CASE WHEN match_method = 'like_prefix' THEN 1 ELSE 0 END) + SUM(CASE WHEN match_method IS NULL THEN 1 ELSE 0 END)) DESC, pending_total DESC, route_label ASC
  ");
  $summaryStmt->execute([':doc_id' => $id, ':jid' => $latestParseJobId]);
  $nextActionsSummary = $summaryStmt->fetchAll();
  foreach ($nextActionsSummary as &$row) {
    $row['pending_risky_cnt'] = (int)$row['pending_low_cnt'] + (int)$row['pending_none_cnt'];
  }
  unset($row);
}

// v0.6-30/31: Next Actions - Top 20 pending (only_risky=1이면 LOW/NONE만, 1쿼리)
$topPendingCandidates = [];
if ($latestParseJobId > 0) {
  $topSql = "
    SELECT c.id, c.source_doc_id, c.route_label, c.raw_stop_name, c.matched_stop_name, c.match_method, c.match_score
    FROM shuttle_stop_candidate c
    WHERE c.source_doc_id = :doc_id
      AND c.created_job_id = :latest_parse_job_id
      AND c.status = 'pending'";
  if ($onlyRisky) {
    $topSql .= "
      AND (c.match_method = 'like_prefix' OR c.match_method IS NULL)";
  }
  $topSql .= "
    ORDER BY
      (c.match_method = 'like_prefix') DESC,
      (c.match_method IS NULL) DESC,
      (c.match_score IS NULL) ASC,
      c.match_score ASC,
      c.id ASC
    LIMIT 20";
  $topStmt = $pdo->prepare($topSql);
  $topStmt->execute([':doc_id' => $id, ':latest_parse_job_id' => $latestParseJobId]);
  $topPendingCandidates = $topStmt->fetchAll();
}

// v0.6-26: PARSE_MATCH Metrics History (최근 5회)
$metricsHistory = [];
$recentJobIds = [];
$recentJobStmt = $pdo->prepare("
  SELECT id
  FROM shuttle_doc_job_log
  WHERE source_doc_id=:id
    AND job_type='PARSE_MATCH'
    AND job_status='success'
  ORDER BY id DESC
  LIMIT 5
");
$recentJobStmt->execute([':id' => $id]);
foreach ($recentJobStmt->fetchAll() as $row) {
  $recentJobIds[] = (int)$row['id'];
}
if ($recentJobIds !== []) {
  $placeholders = implode(',', array_fill(0, count($recentJobIds), '?'));
  $historyStmt = $pdo->prepare("
    SELECT parse_job_id, route_label, cand_total, auto_matched_cnt, low_confidence_cnt, none_matched_cnt, alias_used_cnt, high_cnt, med_cnt, low_cnt, none_cnt
    FROM shuttle_parse_metrics
    WHERE parse_job_id IN ($placeholders)
    ORDER BY parse_job_id DESC, route_label ASC
  ");
  $historyStmt->execute($recentJobIds);
  $metricsHistory = $historyStmt->fetchAll();
  // v0.6-28: 최신/리스크 우선 정렬 (parse_job_id DESC → low_confidence_cnt DESC → none_matched_cnt DESC → cand_total DESC)
  usort($metricsHistory, function ($a, $b) {
    $c = (int)$b['parse_job_id'] - (int)$a['parse_job_id'];
    if ($c !== 0) return $c;
    $c = (int)$b['low_confidence_cnt'] - (int)$a['low_confidence_cnt'];
    if ($c !== 0) return $c;
    $c = (int)$b['none_matched_cnt'] - (int)$a['none_matched_cnt'];
    if ($c !== 0) return $c;
    return (int)$b['cand_total'] - (int)$a['cand_total'];
  });
}

// v1.1: 마지막 검수(작업) 시각 (job_log 기반)
$lastJobLogAt = null;
$lastJobStmt = $pdo->prepare("SELECT updated_at FROM shuttle_doc_job_log WHERE source_doc_id=:id ORDER BY id DESC LIMIT 1");
$lastJobStmt->execute([':id' => $id]);
$lastJob = $lastJobStmt->fetch();
if ($lastJob && !empty($lastJob['updated_at'])) {
  $lastJobLogAt = (string)$lastJob['updated_at'];
}

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// v0.6-30: route_review와 동일한 신뢰도 분류 (표시 전용)
function matchConfidenceLabel(?string $matchMethod): string {
  if ($matchMethod === null || $matchMethod === '') return 'NONE';
  if (in_array($matchMethod, ['exact', 'alias_live_rematch', 'alias_exact', 'route_stop_master'], true)) return 'HIGH';
  if (in_array($matchMethod, ['normalized', 'alias_normalized'], true)) return 'MED';
  if ($matchMethod === 'like_prefix') return 'LOW';
  return 'NONE';
}

function normalizeStopNameDisplay(string $s): string {
  return trim(preg_replace('/\s+/', ' ', $s));
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>관리자 - 문서 파싱·노선 매칭 현황 (문서 #<?= (int)$id ?>)</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '문서 #' . $id, 'url' => null],
  ], false); ?>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert alert-info py-2"><?= h((string)$_SESSION['flash']) ?></div>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>
  <?php if ($justParsed && $latestParseJobId > 0): ?>
    <div class="alert alert-success py-2">
      <strong>파싱 성공.</strong> 다음: <a href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1&doc_id=<?= (int)$id ?>">검수 대기열</a>
      <?php if ($routes && count($routes) > 0): $firstRl = (string)$routes[0]['route_label']; ?>
      또는 <a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($firstRl) ?>&show_advanced=0">노선 검수 (<?= h($firstRl) ?>)</a>
      <?php endif; ?>
      로 진행하세요.
    </div>
  <?php endif; ?>
  <?php if ($lastParseJobStatus === 'failed'): ?>
    <div class="alert alert-warning py-2">
      최근 파싱/매칭이 실패했습니다.
      오류 코드: <strong><?= h($lastParseErrorCode !== '' ? $lastParseErrorCode : 'UNKNOWN') ?></strong><?= $lastParseDurationMs !== null ? ' · 소요: ' . (int)$lastParseDurationMs . 'ms' : '' ?>.
      파일 경로/PDF 형식/용량을 확인한 뒤 아래 <strong>재실행</strong> 버튼으로 다시 시도하세요.
    </div>
  <?php endif; ?>

  <div class="g-page-head">
    <h2 class="h3">문서 파싱 · 노선 매칭 현황</h2>
    <p class="helper mb-0">문서 #<?= (int)$id ?> · 상태를 확인하고 파싱/매칭 실행 또는 노선 검수로 진행합니다.</p>
  </div>
  <details class="kbd-help mb-3">
    <summary>단축키 안내</summary>
    <div class="body">/ : 검색 입력으로 이동 · Esc : 닫기 · Ctrl+Enter : 주요 폼 제출(지원 페이지)</div>
  </details>

  <!-- A. 문서 메타 -->
  <div class="card g-card mb-3">
  <div class="card-body g-meta-grid">
    <div class="g-meta-key">출처(source_name)</div><div><?= h((string)$doc['source_name']) ?></div>
    <div class="g-meta-key">제목(title)</div><div><?= h((string)$doc['title']) ?></div>
    <div class="g-meta-key">파일 경로(file_path)</div><div><?= h((string)$doc['file_path']) ?></div>
    <div class="g-meta-key">OCR 상태(ocr_status)</div><div><?= h((string)$doc['ocr_status']) ?></div>
    <div class="g-meta-key">파싱 상태(parse_status)</div><div><?= h((string)$doc['parse_status']) ?></div>
    <div class="g-meta-key">최근 파싱 상태(last_parse_status)</div><div><?= h($lastParseJobStatus !== '' ? $lastParseJobStatus : 'n/a') ?></div>
    <div class="g-meta-key">최근 파싱 오류코드(last_parse_error_code)</div><div<?= $lastParseJobStatus === 'failed' ? ' title="실패 시 job_log result_note에서 추출. 파싱 원인 파악용."' : '' ?>><?= h($lastParseErrorCode !== '' ? $lastParseErrorCode : '—') ?></div>
    <div class="g-meta-key">최근 파싱 소요(ms)(last_parse_time_ms)</div><div<?= $lastParseJobStatus === 'failed' ? ' title="실패 시에도 result_note의 duration_ms 있으면 표시."' : '' ?>><?= $lastParseDurationMs !== null ? (int)$lastParseDurationMs : '—' ?></div>
    <div class="g-meta-key">최근 파싱 노선(last_parse_route_label)</div><div><?= h($lastParseRouteLabel !== '' ? $lastParseRouteLabel : '—') ?></div>
    <div class="g-meta-key">검증 상태(validation_status)</div><div><?= h((string)$doc['validation_status']) ?></div>
    <div class="g-meta-key">최신 파싱 Job ID</div><div><?= (int)$latestParseJobId ?></div>
    <?php if ($lastJobLogAt !== null): ?>
    <div class="g-meta-key">마지막 검수 시각</div><div><?= h($lastJobLogAt) ?></div>
    <?php endif; ?>
    <div class="g-meta-key">별칭 감사</div><div><a href="<?= APP_BASE ?>/admin/alias_audit.php">별칭 감사</a></div>
    <div class="g-meta-key">알림 운영</div><div><a href="<?= APP_BASE ?>/admin/alert_ops.php">알림 운영</a></div>
  </div>
  </div>

  <!-- B. 실행 버튼 영역 (상단 고정) -->
  <div class="card g-card mb-3">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
      <form method="post" action="<?= APP_BASE ?>/admin/run_job.php" class="d-inline" data-loading-msg="파싱/매칭 처리 중... 잠시만 기다려 주세요.">
        <input type="hidden" name="source_doc_id" value="<?= (int)$id ?>" />
        <button class="btn btn-gilaime-primary btn-sm" type="submit">
          <?= $lastParseJobStatus === 'failed' ? '재실행' : '파싱/매칭 실행' ?>
        </button>
      </form>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/index.php?parse_status=failed">실패건 보기</a>
      <?php if ($latestParseJobId > 0): ?>
      <a class="btn btn-gilaime-primary btn-sm" href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1&doc_id=<?= (int)$id ?>">검수 대기열 바로가기</a>
      <?php if ($routes && count($routes) > 0): $firstRl = (string)$routes[0]['route_label']; ?>
      <a class="btn btn-gilaime-primary btn-sm" href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($firstRl) ?>&show_advanced=0">첫 노선 검수 (<?= h($firstRl) ?>)</a>
      <?php endif; ?>
      <?php endif; ?>
    </div>
    <p class="text-muted-g small mb-0">
      파싱 결과 코드(<strong><?= h($lastParseErrorCode !== '' ? $lastParseErrorCode : '-') ?></strong>)
      · 처리시간 <?= $lastParseDurationMs !== null ? (int)$lastParseDurationMs . 'ms' : '—' ?>.
      실패 시 오류 코드를 확인한 뒤 파일 경로/PDF 형식/용량을 점검하고 위 <strong>재실행</strong> 버튼을 클릭하세요.
      동일 문서 재실행 시 이전 후보(is_active=1)는 비활성화되고 새 job_id로 후보가 생성됩니다. UI는 최신 success job 기준으로 표시됩니다.
      배치 재처리: <code>php scripts/php/run_parse_match_batch.php --only_failed=1 --limit=20</code>
    </p>
  </div>
  </div>

  <?php if ($failedTopN): ?>
  <h3 class="h5 mt-4">PARSE_MATCH 실패 TopN (최근 50개 작업)</h3>
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense">
    <thead>
      <tr><th>error_code</th><th>cnt</th></tr>
    </thead>
    <tbody>
      <?php foreach ($failedTopN as $code => $cnt): ?>
      <tr>
        <td><?= h((string)$code) ?></td>
        <td><?= (int)$cnt ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>

  <!-- C. latest job 요약 (Routes) -->
  <h3 class="h5 mt-4">노선 목록 (최신 PARSE_MATCH)</h3>
  <p class="text-muted-g small mb-2">기준: latest PARSE_MATCH 스냅샷 (job_id=<?= (int)$latestParseJobId ?>)</p>
  <div class="g-routes mb-3">
    <?php if ($routes): ?>
      <?php foreach ($routes as $r): $rl = (string)$r['route_label']; ?>
        <a class="g-pill" href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($rl) ?>&show_advanced=0">
          노선검수 <?= h($rl) ?>
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <span class="text-muted-g small">아직 후보가 없습니다. 파싱/매칭을 먼저 실행하세요.</span>
    <?php endif; ?>
  </div>

  <!-- D. PARSE_MATCH Metrics (latest job) -->
  <h3 class="h5 mt-4">PARSE_MATCH 지표 (최신 작업)</h3>
  <p class="text-muted-g small mb-1">이 표는 route_review와 동일한 기준(latest snapshot)입니다. job_id=<?= (int)$latestParseJobId ?><?= $prevParseJobId > 0 ? ', prev_job_id=' . $prevParseJobId : '' ?></p>
  <p class="text-muted-g small mb-2">delta는 직전 PARSE_MATCH 작업 대비 변화량입니다. 이전 작업이 없으면 delta는 표시되지 않습니다.</p>
  <p class="text-muted-g small mb-2">route_label을 클릭하면 해당 노선의 검수 화면(route_review)으로 이동합니다.</p>
  <?php if ($prevParseJobId > 0 && $totalLowDelta > 0): ?>
  <p class="text-muted-g small mb-1">주의: LOW(like_prefix) 후보가 직전 job 대비 +<?= $totalLowDelta ?> 증가했습니다.</p>
  <?php endif; ?>
  <?php if ($prevParseJobId > 0 && $totalNoneDelta > 0): ?>
  <p class="text-muted-g small mb-2">주의: NONE(미매칭) 후보가 직전 job 대비 +<?= $totalNoneDelta ?> 증가했습니다.</p>
  <?php endif; ?>
  <?php
  $totalCand = 0; $totalLowNone = 0;
  foreach ($metrics as $m) { $totalCand += (int)$m['cand_total']; $totalLowNone += (int)$m['low_cnt'] + (int)$m['none_cnt']; }
  $lowNoneRatio = $totalCand > 0 ? ($totalLowNone / $totalCand) * 100 : 0;
  ?>
  <?php if ($metrics && $lowNoneRatio > 30): ?>
  <div class="alert alert-warning py-2 mb-2">
    <strong>품질 기준선 경고:</strong> LOW+NONE 비중이 <?= (int)round($lowNoneRatio) ?>%입니다. 승격 전 검수에서 LOW/NONE 후보를 반드시 확인하세요.
  </div>
  <?php endif; ?>
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense">
    <thead>
      <tr>
        <th>노선 라벨</th>
        <th>후보 수</th>
        <th>자동 매칭 수</th>
        <th>낮은 신뢰도 수</th>
        <th>미매칭 수</th>
        <th>별칭 사용 수</th>
        <th>HIGH 수</th>
        <th>MED 수</th>
        <th>LOW 수</th>
        <th>NONE 수</th>
        <th>자동 매칭 증감</th>
        <th>LOW 증감</th>
        <th>NONE 증감</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($metrics): ?>
        <?php
        foreach ($metrics as $m):
          $rl = (string)$m['route_label'];
          $prev = $prevMetricsByRoute[$rl] ?? null;
          $autoDelta = $prev !== null ? (int)$m['auto_matched_cnt'] - (int)$prev['auto_matched_cnt'] : null;
          $lowDelta = $prev !== null ? (int)$m['low_confidence_cnt'] - (int)$prev['low_confidence_cnt'] : null;
          $noneDelta = $prev !== null ? (int)$m['none_matched_cnt'] - (int)$prev['none_matched_cnt'] : null;
          $fmt = function ($d) { return $d === null ? '—' : ($d > 0 ? '+' . $d : (string)$d); };
        ?>
        <tr>
          <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($rl) ?>&show_advanced=0"><?= h($rl) ?></a></td>
          <td><?= (int)$m['cand_total'] ?></td>
          <td><?= (int)$m['auto_matched_cnt'] ?></td>
          <td><?= (int)$m['low_confidence_cnt'] ?></td>
          <td><?= (int)$m['none_matched_cnt'] ?></td>
          <td><?= (int)$m['alias_used_cnt'] ?></td>
          <td><?= (int)$m['high_cnt'] ?></td>
          <td><?= (int)$m['med_cnt'] ?></td>
          <td><?= (int)$m['low_cnt'] ?></td>
          <td><?= (int)$m['none_cnt'] ?></td>
          <td><?= $fmt($autoDelta) ?></td>
          <td><?= $fmt($lowDelta) ?></td>
          <td><?= $fmt($noneDelta) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="13" class="text-muted-g small">지표가 없습니다 (파싱/매칭 먼저 실행)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>

  <!-- v0.6-29: Review Progress (latest job) -->
  <h3 class="h5 mt-4" title="Review Progress: 최신 PARSE_MATCH 작업 기준 route_label별 검수 진행률(대기/승인/거절/완료율). STATUS_FOR_GPT 대비.">검수 진행률 (최신 작업)</h3>
  <p class="text-muted-g small mb-2">pending이 0이 되면 승격(Promote) 가능 여부를 route_review에서 확인하세요.</p>
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense">
    <thead>
      <tr>
        <th>노선 라벨</th>
        <th>후보 수</th>
        <th>대기 수</th>
        <th>승인 수</th>
        <th>거절 수</th>
        <th>완료 수</th>
        <th>완료율(%)</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($reviewProgress): ?>
        <?php foreach ($reviewProgress as $rp):
          $rpRl = (string)$rp['route_label'];
        ?>
        <tr>
          <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($rpRl) ?>&show_advanced=0"><?= h($rpRl) ?></a></td>
          <td><?= (int)$rp['cand_total'] ?></td>
          <td><?= (int)$rp['pending_cnt'] ?></td>
          <td><?= (int)$rp['approved_cnt'] ?></td>
          <td><?= (int)$rp['rejected_cnt'] ?></td>
          <td><?= (int)$rp['done_cnt'] ?></td>
          <td><?= (int)$rp['done_rate'] ?></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="7" class="text-muted-g small" title="Review Progress: 최신 파싱 작업이 없거나 후보가 없으면 표시됩니다.">— 데이터가 없습니다 (파싱/매칭 먼저 실행)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>

  <!-- v0.6-31: Next Actions (Summary + Top20 + only_risky 토글) -->
  <h3 class="h5 mt-4" title="Next Actions: pending 후보 노선별 요약 및 상위 20건. 리스크(LOW/NONE) 필터·route_review 이동. STATUS_FOR_GPT 대비.">다음 작업</h3>
  <?php
  $nextActionsBase = APP_BASE . '/admin/doc.php?id=' . (int)$id;
  $startRouteLabel = $nextActionsSummary[0]['route_label'] ?? '';
  ?>
  <?php if ($startRouteLabel !== ''): ?>
  <p class="mb-2">
    <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($startRouteLabel) ?>&quick_mode=1&show_advanced=0">오늘 작업 시작</a>
    <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($startRouteLabel) ?>&show_advanced=0">검수 화면 열기</a>
    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= APP_BASE ?>/user/journey.php?route_label=<?= urlencode($startRouteLabel) ?>">사용자 경로안내 확인</a>
    <span class="text-muted-g small ms-2">pending_risky 가장 많은 노선(<?= h($startRouteLabel) ?>)으로 이동</span>
    <span class="mx-2">|</span><a href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1">검수 대기열로 이동</a>
  </p>
  <?php endif; ?>
  <p class="mb-2">
    <?php if ($onlyRisky): ?>
    <a href="<?= $nextActionsBase ?>">전체 대기 보기</a>
    <?php else: ?>
    <a href="<?= $nextActionsBase ?>&only_risky=1">리스크 후보만 보기</a>
    <?php endif; ?>
  </p>

  <!-- Next Actions Summary (by route) -->
  <h4 class="h6 mt-3 mb-2">다음 작업 요약 (노선별)</h4>
  <?php if ($nextActionsSummary): ?>
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense">
    <thead>
      <tr>
        <th>노선 라벨</th>
        <th>전체 대기</th>
        <th>LOW 대기</th>
        <th>NONE 대기</th>
        <th>리스크 대기</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($nextActionsSummary as $sumRow):
        $sumRl = (string)$sumRow['route_label'];
      ?>
      <tr>
        <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($sumRl) ?>&show_advanced=0"><?= h($sumRl) ?></a></td>
        <td><?= (int)$sumRow['pending_total'] ?></td>
        <td><?= (int)$sumRow['pending_low_cnt'] ?></td>
        <td><?= (int)$sumRow['pending_none_cnt'] ?></td>
        <td><?= (int)$sumRow['pending_risky_cnt'] ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="text-muted-g small" title="Next Actions Summary: pending 후보가 없으면 표시됩니다.">— 대기 후보가 없습니다</p>
  <?php endif; ?>

  <h4 class="h6 mt-3 mb-2">다음 작업 (대기 후보 상위 20개) <?= $onlyRisky ? '(LOW/NONE만)' : '(전체 대기)' ?></h4>
  <p class="text-muted-g small mb-2"><?= $onlyRisky ? '이 표는 대기(pending) 후보 중 LOW/NONE만 대상으로 상위 20건입니다.' : '이 표는 대기(pending) 후보 중 리스크 우선순위(LOW/NONE/score) 기준 상위 20건입니다.' ?></p>
  <?php if ($topPendingCandidates): ?>
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense">
    <thead>
      <tr>
        <th>노선 라벨</th>
        <th>원문 정류장명</th>
        <th>정규화</th>
        <th>매칭 결과</th>
        <th>근거</th>
        <th>신뢰도</th>
        <th>처리 위치</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($topPendingCandidates as $tc):
        $tcRl = (string)$tc['route_label'];
        $matchedName = trim((string)($tc['matched_stop_name'] ?? ''));
        $method = $tc['match_method'] ?? null;
        $score = isset($tc['match_score']) ? (string)$tc['match_score'] : '';
      ?>
      <tr>
        <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($tcRl) ?>&quick_mode=1&show_advanced=0"><?= h($tcRl) ?></a></td>
        <td><?= h((string)($tc['raw_stop_name'] ?? '')) ?></td>
        <td><?= h(normalizeStopNameDisplay((string)($tc['raw_stop_name'] ?? ''))) ?></td>
        <td><?= $matchedName !== '' ? h($matchedName) : '—' ?></td>
        <td><?= $method !== null && $method !== '' ? h($method) . ($score !== '' ? ' (' . h($score) . ')' : '') : '—' ?></td>
        <td><?= h(matchConfidenceLabel($method)) ?></td>
        <td>route_review에서 처리</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php else: ?>
  <p class="text-muted-g small" title="Next Actions Top20: pending 후보가 없거나 필터 결과가 없으면 표시됩니다.">— 대기 후보가 없습니다</p>
  <?php endif; ?>

  <!-- v0.6-26: PARSE_MATCH Metrics History (최근 5회) -->
  <h3 class="h5 mt-4">PARSE_MATCH 지표 이력 (최근 5개 작업)</h3>
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense">
    <thead>
      <tr>
        <th>파싱 Job ID</th>
        <th>노선 라벨</th>
        <th>후보 수</th>
        <th>자동 매칭 수</th>
        <th>낮은 신뢰도 수</th>
        <th>미매칭 수</th>
        <th>별칭 사용 수</th>
        <th>HIGH 수</th>
        <th>MED 수</th>
        <th>LOW 수</th>
        <th>NONE 수</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($metricsHistory): ?>
        <?php foreach ($metricsHistory as $row):
          $histRl = (string)$row['route_label'];
        ?>
        <tr>
          <td><?= (int)$row['parse_job_id'] ?></td>
          <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($histRl) ?>&show_advanced=0"><?= h($histRl) ?></a></td>
          <td><?= (int)$row['cand_total'] ?></td>
          <td><?= (int)$row['auto_matched_cnt'] ?></td>
          <td><?= (int)$row['low_confidence_cnt'] ?></td>
          <td><?= (int)$row['none_matched_cnt'] ?></td>
          <td><?= (int)$row['alias_used_cnt'] ?></td>
          <td><?= (int)$row['high_cnt'] ?></td>
          <td><?= (int)$row['med_cnt'] ?></td>
          <td><?= (int)$row['low_cnt'] ?></td>
          <td><?= (int)$row['none_cnt'] ?></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="11" class="text-muted-g small">작업 이력이 없습니다</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>

  <h3 class="h5 mt-4">ocr_text (미리보기)</h3>
  <pre class="g-pre"><?= h(substr((string)($doc['ocr_text'] ?? ''), 0, 4000)) ?></pre>

  <h3 class="h5">작업 이력</h3>
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead><tr><th>ID</th><th>유형</th><th>상태</th><th>메모</th><th>수정 시각</th></tr></thead>
    <tbody>
      <?php foreach ($jobs as $j): ?>
      <tr>
        <td><?= (int)$j['id'] ?></td>
        <td><?= h((string)$j['job_type']) ?></td>
        <td><?= h((string)$j['job_status']) ?></td>
        <td><?= h((string)($j['result_note'] ?? $j['request_note'] ?? '')) ?></td>
        <td><?= h((string)$j['updated_at']) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$jobs): ?>
      <tr><td colspan="5" class="text-muted-g small">작업 이력이 없습니다</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>

  <p class="text-muted-g small mt-3">
    파싱·매칭 현황 → 파싱/매칭 실행 → 최신 PARSE_MATCH 스냅샷 기준 노선 탐지 → 노선 검수 화면 진입
  </p>
  </main>
  <?php render_admin_tutorial_modal(); ?>

  <div id="g-loading-overlay" class="g-loading-overlay" hidden aria-live="polite">
    <div class="g-loading-spinner" aria-hidden="true"></div>
    <span id="g-loading-msg">처리 중...</span>
  </div>
  <script>
  (function(){
    var overlay = document.getElementById('g-loading-overlay');
    var msgEl = document.getElementById('g-loading-msg');
    document.querySelectorAll('form[data-loading-msg]').forEach(function(f){
      f.addEventListener('submit', function(){
        var msg = f.getAttribute('data-loading-msg') || '처리 중...';
        if (msgEl) msgEl.textContent = msg;
        overlay.removeAttribute('hidden');
        var btn = f.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = '처리 중...'; }
      });
    });
  })();
  </script>
</body>
</html>
