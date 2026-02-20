<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$pdo = pdo();

$onlyRisky = (int)($_GET['only_risky'] ?? 1);
$limitParam = (int)($_GET['limit'] ?? 50);
$limitParam = max(10, min(200, $limitParam));
$sortTop = isset($_GET['sort']) && $_GET['sort'] === 'simple' ? 'simple' : 'default';
$filterDocId = isset($_GET['doc_id']) && $_GET['doc_id'] !== '' ? (int)$_GET['doc_id'] : null;
$filterRouteLabel = isset($_GET['route_label']) && trim((string)$_GET['route_label']) !== '' ? trim((string)$_GET['route_label']) : null;

// doc별 최신 PARSE_MATCH job (1쿼리)
$latestJobStmt = $pdo->query("
  SELECT j.source_doc_id, j.id AS job_id, j.updated_at
  FROM shuttle_doc_job_log j
  INNER JOIN (
    SELECT source_doc_id, MAX(id) AS mid
    FROM shuttle_doc_job_log
    WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
    GROUP BY source_doc_id
  ) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
  WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
");
$latestByDoc = $latestJobStmt->fetchAll(PDO::FETCH_ASSOC);
if ($latestByDoc === []) {
  $queueSummary = [];
  $topCandidates = [];
} else {
  $pairs = [];
  foreach ($latestByDoc as $row) {
    $pairs[] = '(' . (int)$row['source_doc_id'] . ',' . (int)$row['job_id'] . ')';
  }
  $pairList = implode(',', $pairs);

  // Queue Summary: doc_id, route_label, pending_total, pending_low, pending_none, pending_risky, last_review_at
  $summarySql = "
    SELECT c.source_doc_id AS doc_id, c.route_label,
      COUNT(*) AS pending_total,
      SUM(CASE WHEN c.match_method = 'like_prefix' THEN 1 ELSE 0 END) AS pending_low,
      SUM(CASE WHEN c.match_method IS NULL THEN 1 ELSE 0 END) AS pending_none,
      MAX(l.updated_at) AS last_review_at
    FROM shuttle_stop_candidate c
    INNER JOIN (
      SELECT j.source_doc_id, j.id AS job_id, j.updated_at
      FROM shuttle_doc_job_log j
      INNER JOIN (
        SELECT source_doc_id, MAX(id) AS mid
        FROM shuttle_doc_job_log
        WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
        GROUP BY source_doc_id
      ) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
      WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
    ) l ON c.source_doc_id = l.source_doc_id AND c.created_job_id = l.job_id
    WHERE c.status = 'pending'
  ";
  $summaryParams = [];
  if ($filterDocId !== null) {
    $summarySql .= " AND c.source_doc_id = :doc_id";
    $summaryParams[':doc_id'] = $filterDocId;
  }
  if ($filterRouteLabel !== null) {
    $summarySql .= " AND c.route_label = :route_label";
    $summaryParams[':route_label'] = $filterRouteLabel;
  }
  $summarySql .= "
    GROUP BY c.source_doc_id, c.route_label
    ORDER BY (SUM(CASE WHEN c.match_method = 'like_prefix' THEN 1 ELSE 0 END) + SUM(CASE WHEN c.match_method IS NULL THEN 1 ELSE 0 END)) DESC,
      COUNT(*) DESC, MAX(l.updated_at) ASC
  ";
  $summaryStmt = $pdo->prepare($summarySql);
  $summaryStmt->execute($summaryParams);
  $queueSummary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($queueSummary as &$row) {
    $row['pending_risky'] = (int)$row['pending_low'] + (int)$row['pending_none'];
  }
  unset($row);

  $topSql = "
    SELECT c.id AS cand_id, c.created_job_id, c.source_doc_id AS doc_id, c.route_label, c.raw_stop_name, c.matched_stop_name, c.match_method, c.match_score
    FROM shuttle_stop_candidate c
    INNER JOIN (
      SELECT source_doc_id, MAX(id) AS mid
      FROM shuttle_doc_job_log
      WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
      GROUP BY source_doc_id
    ) t ON c.source_doc_id = t.source_doc_id AND c.created_job_id = t.mid
    WHERE c.status = 'pending'
  ";
  $topParams = [];
  if ($filterDocId !== null) {
    $topSql .= " AND c.source_doc_id = :doc_id";
    $topParams[':doc_id'] = $filterDocId;
  }
  if ($filterRouteLabel !== null) {
    $topSql .= " AND c.route_label = :route_label";
    $topParams[':route_label'] = $filterRouteLabel;
  }
  if ($onlyRisky) {
    $topSql .= " AND (c.match_method = 'like_prefix' OR c.match_method IS NULL)";
  }
  if ($sortTop === 'simple') {
    $topSql .= " ORDER BY c.id ASC LIMIT " . $limitParam;
  } else {
    $topSql .= "
    ORDER BY (c.match_method IS NULL) DESC, (c.match_method = 'like_prefix') DESC,
      c.match_score ASC, c.id ASC
    LIMIT " . $limitParam;
  }
  $topStmt = $pdo->prepare($topSql);
  $topStmt->execute($topParams);
  $topCandidates = $topStmt->fetchAll(PDO::FETCH_ASSOC);
}

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

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

$urlBase = APP_BASE . '/admin/review_queue.php';
$q = ['only_risky' => $onlyRisky, 'limit' => $limitParam];
if ($sortTop !== 'default') $q['sort'] = $sortTop;
if ($filterDocId !== null) $q['doc_id'] = $filterDocId;
if ($filterRouteLabel !== null) $q['route_label'] = $filterRouteLabel;
$urlOnlyRiskyOn = $urlBase . '?' . http_build_query(array_merge($q, ['only_risky' => 1]));
$urlOnlyRiskyOff = $urlBase . '?' . http_build_query(array_merge($q, ['only_risky' => 0]));
$urlAllDocs = $urlBase . '?' . http_build_query(['only_risky' => $onlyRisky, 'limit' => $limitParam]);
$uniqueDocIds = array_values(array_unique(array_map(function ($r) { return (int)$r['doc_id']; }, $queueSummary)));
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>관리자 - 검수 대기열</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '검수 대기열', 'url' => null],
  ], false); ?>

  <div class="g-page-head">
    <h2 class="h3">검수 대기열</h2>
    <p class="helper mb-0">리스크 우선으로 후보 검수 대기열을 확인합니다.</p>
    <p class="helper text-muted-g small mt-1 mb-0" title="집계·필터 의도">LOW: like_prefix 매칭만 된 후보. NONE: match_method 없음(미매칭). <strong>리스크 대기</strong> = LOW + NONE 합계이며, 검수 시 우선 확인할 항목입니다.</p>
  </div>

  <p class="text-muted-g small mb-2">
    <?php if ($onlyRisky): ?>
    <a href="<?= h($urlOnlyRiskyOff) ?>">전체 대기 보기</a>
    <?php else: ?>
    <a href="<?= h($urlOnlyRiskyOn) ?>">리스크(LOW/NONE)만 보기</a>
    <?php endif; ?>
    | 표시 수=<?= (int)$limitParam ?> (10~200)
  </p>

  <p class="text-muted-g small mb-2">
    <a href="<?= h($urlAllDocs) ?>">전체 문서</a>
    <?php foreach ($uniqueDocIds as $did): ?>
    | <a href="<?= h($urlBase . '?' . http_build_query(array_merge($q, ['doc_id' => $did]))) ?>">문서 <?= (int)$did ?>만</a>
    <?php endforeach; ?>
    <?php if ($filterDocId !== null): ?>
    <?php foreach ($queueSummary as $sr): ?>
    | <a href="<?= h($urlBase . '?' . http_build_query(array_merge($q, ['doc_id' => $filterDocId, 'route_label' => $sr['route_label']]))) ?>">노선 <?= h((string)$sr['route_label']) ?>만</a>
    <?php endforeach; ?>
    <?php endif; ?>
  </p>
  <h3 class="h5 mb-2">대기열 요약 (리스크 우선)</h3>
  <div class="card g-card mb-3">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th>문서 ID</th>
        <th>노선 라벨</th>
        <th>전체 대기</th>
        <th>LOW 대기</th>
        <th>NONE 대기</th>
        <th>리스크 대기</th>
        <th>최근 검수 시각</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($queueSummary): ?>
        <?php foreach ($queueSummary as $s): ?>
        <tr>
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$s['doc_id'] ?>"><?= (int)$s['doc_id'] ?></a></td>
          <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$s['doc_id'] ?>&route_label=<?= urlencode((string)$s['route_label']) ?>&quick_mode=1&show_advanced=0"><?= h((string)$s['route_label']) ?></a></td>
          <td><?= (int)$s['pending_total'] ?></td>
          <td><?= (int)$s['pending_low'] ?></td>
          <td><?= (int)$s['pending_none'] ?></td>
          <td><?= (int)$s['pending_risky'] ?></td>
          <td><?= $s['last_review_at'] !== null ? h((string)$s['last_review_at']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="7" class="text-muted-g small">대기 후보가 없습니다 (파싱/매칭 실행 후 확인)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
  </div>
  </div>

  <h3 class="h5 mb-2">상위 <?= (int)$limitParam ?>개 후보 (LOW/NONE 우선) <span class="text-muted-g small">| <a href="<?= h($urlBase . '?' . http_build_query(array_merge($q, ['sort' => 'default']))) ?>">정렬: 기본</a> <a href="<?= h($urlBase . '?' . http_build_query(array_merge($q, ['sort' => 'simple']))) ?>">정렬: 단순</a></span></h3>
  <div class="card g-card">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th>후보 ID</th>
        <th>생성 Job ID</th>
        <th>문서 ID</th>
        <th>노선 라벨</th>
        <th>원문</th>
        <th>정규화</th>
        <th>매칭 방식</th>
        <th>점수</th>
        <th>신뢰도</th>
        <th>링크</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($topCandidates): ?>
        <?php foreach ($topCandidates as $tc):
          $reviewUrl = APP_BASE . '/admin/route_review.php?source_doc_id=' . (int)$tc['doc_id'] . '&route_label=' . urlencode((string)$tc['route_label']) . '&quick_mode=1&show_advanced=0&focus_cand_id=' . (int)$tc['cand_id'];
        ?>
        <tr>
          <td><?= (int)$tc['cand_id'] ?></td>
          <td><?= (int)($tc['created_job_id'] ?? 0) ?></td>
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$tc['doc_id'] ?>"><?= (int)$tc['doc_id'] ?></a></td>
          <td><a href="<?= h($reviewUrl) ?>"><?= h((string)$tc['route_label']) ?></a></td>
          <td><?= h((string)($tc['raw_stop_name'] ?? '')) ?></td>
          <td><?= h(normalizeStopNameDisplay((string)($tc['raw_stop_name'] ?? ''))) ?></td>
          <td><?= $tc['match_method'] !== null && $tc['match_method'] !== '' ? h((string)$tc['match_method']) : '—' ?></td>
          <td><?= isset($tc['match_score']) && $tc['match_score'] !== null && $tc['match_score'] !== '' ? h((string)$tc['match_score']) : '—' ?></td>
          <td><?= h(matchConfidenceLabel($tc['match_method'] ?? null)) ?></td>
          <td><a href="<?= h($reviewUrl) ?>">열기</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="10" class="text-muted-g small">후보가 없습니다</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
  </div>
  </div>
  </main>
  <?php render_admin_tutorial_modal(); ?>
</body>
</html>
