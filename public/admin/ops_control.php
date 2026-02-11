<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();
$base = APP_BASE . '/admin';

// A) Deliveries retry/backoff: status counts + failed top 20 (retry_count 포함)
$statusCounts = $pdo->query("
  SELECT status, COUNT(*) AS cnt FROM app_alert_deliveries GROUP BY status
")->fetchAll(PDO::FETCH_ASSOC);
$failedTop20 = $pdo->query("
  SELECT id, user_id, alert_event_id, channel, status, retry_count, last_error, created_at
  FROM app_alert_deliveries
  WHERE status = 'failed'
  ORDER BY created_at DESC, id DESC
  LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

// B) 최근 metrics 이벤트 10
$recentMetricsEvents = $pdo->query("
  SELECT id, event_type, title, ref_id, route_label, published_at, created_at
  FROM app_alert_events
  WHERE title LIKE '[Metrics]%'
  ORDER BY created_at DESC, id DESC
  LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>Admin - Ops Control</title>
  <style>
    body{font-family:system-ui,-apple-system,sans-serif;padding:24px;background:#f9fafb;}
    a{color:#0b57d0;text-decoration:none;}
    a:hover{text-decoration:underline;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
    section{margin-bottom:24px;background:#fff;padding:16px;border-radius:8px;box-shadow:0 1px 3px rgba(0,0,0,.08);}
    h3{margin:0 0 12px 0;font-size:16px;}
    table{border-collapse:collapse;width:100%;}
    th,td{border-bottom:1px solid #eee;padding:8px 10px;text-align:left;font-size:13px;}
    th{background:#f7f8fa;}
    .muted{color:#666;font-size:12px;}
    code{background:#f0f0f0;padding:2px 6px;border-radius:4px;}
  </style>
</head>
<body>
  <div class="top">
    <h2>Ops Control</h2>
    <div>
      <a href="<?= $base ?>/alert_ops.php">Alert Ops</a>
      <span style="margin:0 8px;">|</span>
      <a href="<?= $base ?>/alert_event_audit.php">Alert Audit</a>
      <span style="margin:0 8px;">|</span>
      <a href="<?= $base ?>/ops_summary.php">Ops Summary</a>
      <span style="margin:0 8px;">|</span>
      <a href="<?= $base ?>/index.php">Admin Home</a>
    </div>
  </div>

  <section>
    <h3>A. Deliveries retry/backoff 현황</h3>
    <p><strong>상태별:</strong>
      <?php
        $parts = [];
        foreach ($statusCounts as $row) {
          $parts[] = $row['status'] . '=' . (int)$row['cnt'];
        }
        echo $parts ? implode(', ', $parts) : '(none)';
      ?>
    </p>
    <p><strong>Failed Top 20</strong></p>
    <table>
      <thead>
        <tr>
          <th>id</th><th>user_id</th><th>event_id</th><th>channel</th><th>retry_count</th><th>last_error</th><th>created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($failedTop20 as $d): ?>
          <tr>
            <td><?= (int)$d['id'] ?></td>
            <td><?= (int)$d['user_id'] ?></td>
            <td><a href="<?= $base ?>/alert_event_audit.php?event_id=<?= (int)$d['alert_event_id'] ?>"><?= (int)$d['alert_event_id'] ?></a></td>
            <td><?= h((string)$d['channel']) ?></td>
            <td><?= (int)($d['retry_count'] ?? 0) ?></td>
            <td><?= h((string)($d['last_error'] ?? '')) ?></td>
            <td><?= h((string)$d['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$failedTop20): ?>
          <tr><td colspan="7" class="muted">(none)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <p class="muted">Run outbound stub (CLI): <code>php scripts/run_delivery_outbound_stub.php --limit=200</code></p>
  </section>

  <section>
    <h3>B. Real ingest 실행 안내 + 최근 metrics 이벤트 10</h3>
    <p class="muted">Run metrics ingest (CLI): <code>php scripts/run_alert_ingest_real_metrics.php --since_minutes=1440 --limit=200</code></p>
    <table>
      <thead>
        <tr>
          <th>id</th><th>event_type</th><th>title</th><th>ref_id</th><th>route</th><th>published</th><th>created</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentMetricsEvents as $e): ?>
          <tr>
            <td><a href="<?= $base ?>/alert_ops.php?event_id=<?= (int)$e['id'] ?>"><?= (int)$e['id'] ?></a></td>
            <td><?= h((string)($e['event_type'] ?? '')) ?></td>
            <td><?= h((string)($e['title'] ?? '')) ?></td>
            <td><?= (int)($e['ref_id'] ?? 0) ?></td>
            <td><?= h((string)($e['route_label'] ?? '')) ?></td>
            <td><?= h((string)($e['published_at'] ?? '')) ?></td>
            <td><?= h((string)$e['created_at']) ?></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$recentMetricsEvents): ?>
          <tr><td colspan="7" class="muted">(none)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section>
    <h3>C. Gate quick links</h3>
    <p>
      <a href="<?= $base ?>/alert_ops.php">Alert Ops</a>
      <span style="margin:0 8px;">|</span>
      <a href="<?= $base ?>/alert_event_audit.php">Alert Audit</a>
      <span style="margin:0 8px;">|</span>
      <a href="<?= $base ?>/ops_summary.php">Ops Summary</a>
    </p>
  </section>
</body>
</html>
