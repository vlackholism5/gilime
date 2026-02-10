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

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
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

  <div class="meta">
    <div class="k">source_name</div><div><?= h((string)$doc['source_name']) ?></div>
    <div class="k">title</div><div><?= h((string)$doc['title']) ?></div>
    <div class="k">file_path</div><div><?= h((string)$doc['file_path']) ?></div>
    <div class="k">ocr_status</div><div><?= h((string)$doc['ocr_status']) ?></div>
    <div class="k">parse_status</div><div><?= h((string)$doc['parse_status']) ?></div>
    <div class="k">validation_status</div><div><?= h((string)$doc['validation_status']) ?></div>
    <div class="k">latest_parse_job_id</div><div><?= (int)$latestParseJobId ?></div>
  </div>

  <h3 style="margin-top:18px;">Routes (auto-detected)</h3>
  <p class="muted" style="margin:0 0 8px;">기준: latest PARSE_MATCH 스냅샷 (job_id=<?= (int)$latestParseJobId ?>)</p>
  <div class="routes">
    <?php if ($routes): ?>
      <?php foreach ($routes as $r): $rl = (string)$r['route_label']; ?>
        <a class="pill" href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$id ?>&route_label=<?= urlencode($rl) ?>">
          <?= h($rl) ?> 검수하기 →
        </a>
      <?php endforeach; ?>
    <?php else: ?>
      <span class="muted">아직 candidate가 없습니다. 아래 Run Parse/Match를 먼저 실행하세요.</span>
    <?php endif; ?>
  </div>

  <form method="post" action="<?= APP_BASE ?>/admin/run_job.php" style="margin:0 0 18px;">
    <input type="hidden" name="source_doc_id" value="<?= (int)$id ?>" />
    <button class="btn" type="submit">Run Parse/Match (PoC)</button>
    <span class="muted" style="margin-left:8px;">job_log 기록 + candidate 자동 생성(더미 파서)</span>
  </form>

  <h3>ocr_text (preview)</h3>
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
    v1.x: Doc 상세 → (Run Parse/Match) → latest PARSE_MATCH(job_id) 스냅샷 기준 route_label 탐지 → route_review로 진입
  </p>
</body>
</html>
