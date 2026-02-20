<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$pdo = pdo();

$sortDocs = isset($_GET['sort']) && $_GET['sort'] === 'risky' ? 'risky' : 'updated';

// (1) Docs needing review: 정렬 기본값 updated(v1.3-07) → filesort 비용 기본 경로에서 회피
$docsNeedingReview = [];
try {
  $orderBy = $sortDocs === 'risky'
    ? 'pending_risky_total DESC, pending_total DESC'
    : 'j.updated_at DESC';
  $sql = "
    SELECT j.source_doc_id, j.id AS latest_parse_job_id, j.updated_at,
      COALESCE(agg.pending_total, 0) AS pending_total,
      COALESCE(agg.pending_risky_total, 0) AS pending_risky_total
    FROM shuttle_doc_job_log j
    LEFT JOIN (
      SELECT source_doc_id, created_job_id,
        COUNT(*) AS pending_total,
        SUM(CASE WHEN match_method = 'like_prefix' OR match_method IS NULL THEN 1 ELSE 0 END) AS pending_risky_total
      FROM shuttle_stop_candidate FORCE INDEX (idx_cand_doc_job_status_method)
      WHERE status = 'pending'
      GROUP BY source_doc_id, created_job_id
    ) agg ON j.source_doc_id = agg.source_doc_id AND j.id = agg.created_job_id
    WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
      AND NOT EXISTS (
        SELECT 1 FROM shuttle_doc_job_log j2 USE INDEX (idx_joblog_doc_type_status_id)
        WHERE j2.source_doc_id = j.source_doc_id
          AND j2.job_type = 'PARSE_MATCH' AND j2.job_status = 'success'
          AND j2.id > j.id
      )
    ORDER BY " . $orderBy;
  $stmt = $pdo->query($sql);
  $docsNeedingReview = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $docsNeedingReview = [];
}

// (2) Recent PARSE_MATCH jobs: 최근 20건
$recentParseJobs = [];
try {
  $stmt = $pdo->query("
    SELECT id AS parse_job_id, source_doc_id, job_type, job_status, created_at, updated_at
    FROM shuttle_doc_job_log
    WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
    ORDER BY id DESC
    LIMIT 20
  ");
  $recentParseJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $recentParseJobs = [];
}

// (3) Promote candidates: pending=0인 route_label (표시 전용)
$promoteCandidates = [];
try {
  $stmt = $pdo->query("
    SELECT c.source_doc_id, c.route_label, c.created_job_id,
      (SELECT COUNT(*) FROM shuttle_stop_candidate c2
       WHERE c2.source_doc_id = c.source_doc_id AND c2.route_label = c.route_label
         AND c2.created_job_id = c.created_job_id AND c2.status = 'pending') AS pending_cnt
    FROM shuttle_stop_candidate c
    INNER JOIN (
      SELECT source_doc_id, MAX(id) AS mid
      FROM shuttle_doc_job_log
      WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
      GROUP BY source_doc_id
    ) t ON c.source_doc_id = t.source_doc_id AND c.created_job_id = t.mid
    GROUP BY c.source_doc_id, c.route_label, c.created_job_id
    HAVING pending_cnt = 0
    ORDER BY c.source_doc_id, c.route_label
    LIMIT 100
  ");
  $promoteCandidates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $promoteCandidates = [];
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>관리자 - 운영 대시보드</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '운영 대시보드', 'url' => null],
  ], false); ?>

  <div class="g-page-head">
    <h2 class="h3">운영 대시보드</h2>
    <p class="helper mb-0">오늘 우선순위 점검용 페이지입니다. 실제 승격은 노선 검수 화면에서만 수행합니다.</p>
  </div>

  <h3 class="h5 mb-2">검수가 필요한 문서 <span class="text-muted-g small">| <a href="<?= APP_BASE ?>/admin/ops_dashboard.php?sort=updated">정렬: 최신</a> <a href="<?= APP_BASE ?>/admin/ops_dashboard.php?sort=risky">정렬: 위험도</a></span></h3>
  <p class="text-muted-g small mb-2">리스크 대기 = pending 후보 중 match_method가 like_prefix 또는 NULL인 건수.</p>
  <div class="card g-card mb-3">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th>문서 ID</th>
        <th>최신 파싱 Job ID</th>
        <th>대기 건수</th>
        <th>리스크 대기 건수</th>
        <th>수정 시각</th>
        <th>링크</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($docsNeedingReview): ?>
        <?php foreach ($docsNeedingReview as $r): ?>
        <tr>
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['source_doc_id'] ?>"><?= (int)$r['source_doc_id'] ?></a></td>
          <td><?= (int)$r['latest_parse_job_id'] ?></td>
          <td><?= (int)$r['pending_total'] ?></td>
          <td><?= (int)$r['pending_risky_total'] ?></td>
          <td><?= !empty($r['updated_at']) ? h((string)$r['updated_at']) : '—' ?></td>
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['source_doc_id'] ?>">문서</a> <a href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1&doc_id=<?= (int)$r['source_doc_id'] ?>">대기열</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="text-muted-g small">데이터가 없습니다</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
  </div>
  </div>

  <h3 class="h5 mb-2">최근 PARSE_MATCH 작업</h3>
  <div class="card g-card mb-3">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th>파싱 Job ID</th>
        <th>문서 ID</th>
        <th>작업 유형</th>
        <th>작업 상태</th>
        <th>생성 시각</th>
        <th>수정 시각</th>
        <th>링크</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($recentParseJobs): ?>
        <?php foreach ($recentParseJobs as $r): ?>
        <tr>
          <td><?= (int)$r['parse_job_id'] ?></td>
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['source_doc_id'] ?>"><?= (int)$r['source_doc_id'] ?></a></td>
          <td><?= h((string)$r['job_type']) ?></td>
          <td><?= h((string)$r['job_status']) ?></td>
          <td><?= h((string)($r['created_at'] ?? '')) ?></td>
          <td><?= h((string)($r['updated_at'] ?? '')) ?></td>
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['source_doc_id'] ?>">문서</a> <a href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1&doc_id=<?= (int)$r['source_doc_id'] ?>">대기열</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="7" class="text-muted-g small">데이터가 없습니다</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
  </div>
  </div>

  <h3 class="h5 mb-2">승격 가능 후보</h3>
  <p class="text-muted-g small mb-2">pending=0인 노선(표시 전용)입니다. 실제 승격은 노선 검수 화면에서만 수행합니다.</p>
  <div class="card g-card">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th>문서 ID</th>
        <th>노선 라벨</th>
        <th>생성 Job ID</th>
        <th>링크</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($promoteCandidates): ?>
        <?php foreach ($promoteCandidates as $r): ?>
        <tr>
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['source_doc_id'] ?>"><?= (int)$r['source_doc_id'] ?></a></td>
          <td><?= h((string)$r['route_label']) ?></td>
          <td><?= (int)$r['created_job_id'] ?></td>
          <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$r['source_doc_id'] ?>&route_label=<?= urlencode((string)$r['route_label']) ?>&quick_mode=1&show_advanced=0">노선 검수</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="4" class="text-muted-g small">후보가 없습니다</td></tr>
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
