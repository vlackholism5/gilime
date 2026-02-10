<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();

// (1) Docs needing review: doc별 latest_parse_job_id 기준 pending_total, pending_risky_total. pending_risky_total DESC
$docsNeedingReview = [];
try {
  $stmt = $pdo->query("
    SELECT j.source_doc_id, j.id AS latest_parse_job_id, j.updated_at,
      (SELECT COUNT(*) FROM shuttle_stop_candidate c
       WHERE c.source_doc_id = j.source_doc_id AND c.created_job_id = j.id AND c.status = 'pending') AS pending_total,
      (SELECT COUNT(*) FROM shuttle_stop_candidate c
       WHERE c.source_doc_id = j.source_doc_id AND c.created_job_id = j.id AND c.status = 'pending'
         AND (c.match_method = 'like_prefix' OR c.match_method IS NULL)) AS pending_risky_total
    FROM shuttle_doc_job_log j
    INNER JOIN (
      SELECT source_doc_id, MAX(id) AS mid
      FROM shuttle_doc_job_log
      WHERE job_type = 'PARSE_MATCH' AND job_status = 'success'
      GROUP BY source_doc_id
    ) t ON j.source_doc_id = t.source_doc_id AND j.id = t.mid
    WHERE j.job_type = 'PARSE_MATCH' AND j.job_status = 'success'
    ORDER BY pending_risky_total DESC, pending_total DESC
  ");
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
  <title>Admin - Ops Dashboard</title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    a:hover{text-decoration:underline;}
    table{border-collapse:collapse;width:100%;margin-top:10px;background:#fff;}
    th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;font-size:13px;}
    th{background:#f7f8fa;font-weight:600;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    .muted{color:#666;font-size:12px;}
    .card{margin-bottom:20px;}
  </style>
</head>
<body>
  <div class="top">
    <div>
      <a href="<?= APP_BASE ?>/admin/index.php">Docs</a>
      <span class="muted"> / Ops Dashboard</span>
    </div>
    <a href="<?= APP_BASE ?>/admin/logout.php">Logout</a>
  </div>

  <h2>Ops Dashboard</h2>
  <p class="muted" style="margin:0 0 12px;">오늘 뭐부터 볼지 1페이지. 실제 promote는 route_review에서만.</p>

  <h3 class="card">Docs needing review</h3>
  <table>
    <thead>
      <tr>
        <th>source_doc_id</th>
        <th>latest_parse_job_id</th>
        <th>pending_total</th>
        <th>pending_risky_total</th>
        <th>updated_at</th>
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
          <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['source_doc_id'] ?>">Doc</a> <a href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1&doc_id=<?= (int)$r['source_doc_id'] ?>">Queue</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="muted">no data</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h3 class="card">Recent PARSE_MATCH jobs</h3>
  <table>
    <thead>
      <tr>
        <th>parse_job_id</th>
        <th>source_doc_id</th>
        <th>job_type</th>
        <th>job_status</th>
        <th>created_at</th>
        <th>updated_at</th>
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
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="muted">no data</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h3 class="card">Promote candidates</h3>
  <p class="muted" style="margin:0 0 8px;">pending=0인 route (표시 전용). 실제 Promote는 route_review에서만.</p>
  <table>
    <thead>
      <tr>
        <th>source_doc_id</th>
        <th>route_label</th>
        <th>created_job_id</th>
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
          <td><a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$r['source_doc_id'] ?>&route_label=<?= urlencode((string)$r['route_label']) ?>&quick_mode=1&show_advanced=0">route_review</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="4" class="muted">no candidates</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
