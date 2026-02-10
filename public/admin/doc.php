<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(400); exit('bad id'); }

$stmt = $pdo->prepare("SELECT * FROM shuttle_source_doc WHERE id=:id");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();
if (!$doc) { http_response_code(404); exit('not found'); }

// jobs
$jobsStmt = $pdo->prepare("SELECT * FROM shuttle_doc_job_log WHERE source_doc_id=:id ORDER BY id DESC LIMIT 50");
$jobsStmt->execute([':id' => $id]);
$jobs = $jobsStmt->fetchAll();

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

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

// v0.6-30: route_review와 동일한 신뢰도 분류 (표시 전용)
function matchConfidenceLabel(?string $matchMethod): string {
  if ($matchMethod === null || $matchMethod === '') return 'NONE';
  if (in_array($matchMethod, ['exact', 'alias_live_rematch', 'alias_exact'], true)) return 'HIGH';
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
  <title>Admin - Doc <?= (int)$id ?></title>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding:24px;}
    a{color:#0b57d0;text-decoration:none;}
    pre{background:#fafafa;border:1px solid #eee;border-radius:10px;padding:12px;white-space:pre-wrap;}
    .meta{display:grid;grid-template-columns:160px 1fr;gap:8px;margin:10px 0;}
    .k{color:#666;}
    table{border-collapse:collapse;width:100%;margin-top:10px;}
    th,td{border-bottom:1px solid #eee;padding:8px;text-align:left;font-size:13px;}
    th{background:#fafafa;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
    .routes{display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 16px;}
    .pill{display:inline-block;border:1px solid #ddd;border-radius:999px;padding:6px 10px;font-size:13px;background:#fff;}
    .btn{display:inline-block;border:1px solid #ddd;border-radius:10px;padding:8px 12px;background:#fff;cursor:pointer;}
    .flash{margin:10px 0;padding:10px 12px;border:1px solid #e6e6e6;border-radius:10px;background:#fafafa;}
    .muted{color:#666;font-size:12px;}
  </style>
</head>
<body>
  <div class="top">
    <p><a href="<?= APP_BASE ?>/admin/index.php">← Back</a></p>
    <a href="<?= APP_BASE ?>/admin/logout.php">Logout</a>
  </div>

  <?php if (!empty($_SESSION['flash'])): ?>
    <div class="flash"><?= h((string)$_SESSION['flash']) ?></div>
    <?php unset($_SESSION['flash']); ?>
  <?php endif; ?>

  <h2>Doc #<?= (int)$id ?></h2>

  <!-- A. 문서 메타 -->
  <div class="meta">
    <div class="k">source_name</div><div><?= h((string)$doc['source_name']) ?></div>
    <div class="k">title</div><div><?= h((string)$doc['title']) ?></div>
    <div class="k">file_path</div><div><?= h((string)$doc['file_path']) ?></div>
    <div class="k">ocr_status</div><div><?= h((string)$doc['ocr_status']) ?></div>
    <div class="k">parse_status</div><div><?= h((string)$doc['parse_status']) ?></div>
    <div class="k">validation_status</div><div><?= h((string)$doc['validation_status']) ?></div>
    <div class="k">latest_parse_job_id</div><div><?= (int)$latestParseJobId ?></div>
  </div>

  <!-- B. 실행 버튼 영역 (상단 고정) -->
  <div style="margin:16px 0;">
    <form method="post" action="<?= APP_BASE ?>/admin/run_job.php" style="display:inline;">
      <input type="hidden" name="source_doc_id" value="<?= (int)$id ?>" />
      <button class="btn" type="submit">Run Parse/Match</button>
    </form>
    <span class="muted" style="margin-left:8px;">job_log 기록 + candidate 자동 생성</span>
  </div>

  <!-- C. latest job 요약 (Routes) -->
  <h3 style="margin-top:18px;">Routes (latest PARSE_MATCH)</h3>
  <p class="muted" style="margin:0 0 8px;">기준: latest PARSE_MATCH 스냅샷 (job_id=<?= (int)$latestParseJobId ?>)</p>
  <div class="routes">
    <?php if ($routes): ?>
      <?php foreach ($routes as $r): $rl = (string)$r['route_label']; ?>
        <a class="pill" href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($rl) ?>&show_advanced=0">
          <?= h($rl) ?> 검수하기
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <span class="muted">아직 candidate가 없습니다. Run Parse/Match를 먼저 실행하세요.</span>
    <?php endif; ?>
  </div>

  <!-- D. PARSE_MATCH Metrics (latest job) -->
  <h3 style="margin-top:24px;">PARSE_MATCH Metrics (latest job)</h3>
  <p class="muted" style="margin:0 0 4px;">이 표는 route_review에서 보는 것과 동일한 기준(latest snapshot)이다. job_id=<?= (int)$latestParseJobId ?><?= $prevParseJobId > 0 ? ', prev_job_id=' . $prevParseJobId : '' ?></p>
  <p class="muted" style="margin:0 0 8px;">delta는 직전 PARSE_MATCH job 대비 변화량이다. prev job이 없으면 delta는 표시되지 않는다.</p>
  <p class="muted" style="margin:0 0 8px;">route_label을 클릭하면 해당 노선의 검수 화면(route_review)으로 이동합니다.</p>
  <?php if ($prevParseJobId > 0 && $totalLowDelta > 0): ?>
  <p class="muted" style="margin:0 0 4px;">주의: LOW(like_prefix) 후보가 직전 job 대비 +<?= $totalLowDelta ?> 증가했습니다.</p>
  <?php endif; ?>
  <?php if ($prevParseJobId > 0 && $totalNoneDelta > 0): ?>
  <p class="muted" style="margin:0 0 8px;">주의: NONE(미매칭) 후보가 직전 job 대비 +<?= $totalNoneDelta ?> 증가했습니다.</p>
  <?php endif; ?>
  <table>
    <thead>
      <tr>
        <th>route_label</th>
        <th>cand_total</th>
        <th>auto_matched_cnt</th>
        <th>low_confidence_cnt</th>
        <th>none_matched_cnt</th>
        <th>alias_used_cnt</th>
        <th>high_cnt</th>
        <th>med_cnt</th>
        <th>low_cnt</th>
        <th>none_cnt</th>
        <th>auto_delta</th>
        <th>low_delta</th>
        <th>none_delta</th>
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
        <tr><td colspan="13" class="muted">no metrics (Run Parse/Match 먼저 실행하세요)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- v0.6-29: Review Progress (latest job) -->
  <h3 style="margin-top:24px;">Review Progress (latest job)</h3>
  <p class="muted" style="margin:0 0 8px;">pending이 0이 되면 Promote 가능 여부를 route_review에서 확인하세요.</p>
  <table>
    <thead>
      <tr>
        <th>route_label</th>
        <th>cand_total</th>
        <th>pending_cnt</th>
        <th>approved_cnt</th>
        <th>rejected_cnt</th>
        <th>done_cnt</th>
        <th>done_rate(%)</th>
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
        <tr><td colspan="7" class="muted">no data (Run Parse/Match 먼저 실행하세요)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <!-- v0.6-31: Next Actions (Summary + Top20 + only_risky 토글) -->
  <h3 style="margin-top:24px;">Next Actions</h3>
  <?php $nextActionsBase = APP_BASE . '/admin/doc.php?id=' . (int)$id; ?>
  <p style="margin:0 0 8px;">
    <?php if ($onlyRisky): ?>
    <a href="<?= $nextActionsBase ?>">전체 pending 보기</a>
    <?php else: ?>
    <a href="<?= $nextActionsBase ?>&only_risky=1">리스크 후보만 보기</a>
    <?php endif; ?>
  </p>

  <!-- Next Actions Summary (by route) -->
  <h4 style="margin:12px 0 6px;">Next Actions Summary (by route)</h4>
  <?php if ($nextActionsSummary): ?>
  <table>
    <thead>
      <tr>
        <th>route_label</th>
        <th>pending_total</th>
        <th>pending_low_cnt</th>
        <th>pending_none_cnt</th>
        <th>pending_risky_cnt</th>
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
  <?php else: ?>
  <p class="muted">no pending candidates</p>
  <?php endif; ?>

  <h4 style="margin:16px 0 6px;">Next Actions (Top 20 pending candidates) <?= $onlyRisky ? '(LOW/NONE only)' : '(all pending)' ?></h4>
  <p class="muted" style="margin:0 0 8px;"><?= $onlyRisky ? '이 표는 pending 중 LOW/NONE만 대상으로 Top 20입니다.' : '이 표는 pending 후보 중 리스크 우선순위(LOW/NONE/score) 기준 Top 20입니다.' ?></p>
  <?php if ($topPendingCandidates): ?>
  <table>
    <thead>
      <tr>
        <th>route_label</th>
        <th>원문 정류장명</th>
        <th>정규화</th>
        <th>매칭 결과</th>
        <th>근거</th>
        <th>신뢰도</th>
        <th>Action</th>
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
        <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($tcRl) ?>&show_advanced=0"><?= h($tcRl) ?></a></td>
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
  <?php else: ?>
  <p class="muted">no pending candidates</p>
  <?php endif; ?>

  <!-- v0.6-26: PARSE_MATCH Metrics History (최근 5회) -->
  <h3 style="margin-top:24px;">PARSE_MATCH Metrics History (recent 5 jobs)</h3>
  <table>
    <thead>
      <tr>
        <th>parse_job_id</th>
        <th>route_label</th>
        <th>cand_total</th>
        <th>auto_matched_cnt</th>
        <th>low_confidence_cnt</th>
        <th>none_matched_cnt</th>
        <th>alias_used_cnt</th>
        <th>high_cnt</th>
        <th>med_cnt</th>
        <th>low_cnt</th>
        <th>none_cnt</th>
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
        <tr><td colspan="11" class="muted">no history</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h3 style="margin-top:24px;">ocr_text (preview)</h3>
  <pre><?= h(substr((string)($doc['ocr_text'] ?? ''), 0, 4000)) ?></pre>

  <h3>Jobs</h3>
  <table>
    <thead><tr><th>id</th><th>type</th><th>status</th><th>note</th><th>updated</th></tr></thead>
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
      <tr><td colspan="5" class="muted">no jobs</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <p class="muted" style="margin-top:12px;">
    Doc 상세 → Run Parse/Match → latest PARSE_MATCH 스냅샷 기준 route 탐지 → route_review 진입
  </p>
</body>
</html>
