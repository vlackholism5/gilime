<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
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
  <title>관리자 - 운영 제어</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '운영 제어', 'url' => null],
  ], false); ?>
  <div class="g-page-header-row">
    <div class="g-page-head">
      <h2 class="h3">운영 제어</h2>
      <p class="helper mb-0">재시도/백오프, 실데이터 수집(ingest), 운영 링크를 한 번에 제어합니다.</p>
    </div>
    <div class="d-flex gap-2">
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/alert_ops.php">알림 운영</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/alert_event_audit.php">알림 감사</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/ops_summary.php">운영 요약</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/index.php">관리자 홈</a>
    </div>
  </div>

  <section class="card g-card mb-4">
    <div class="card-body">
    <h3 class="h5">A. 전달(Deliveries) 재시도/백오프 현황</h3>
    <p><strong>상태별:</strong>
      <?php
        $parts = [];
        foreach ($statusCounts as $row) {
          $parts[] = $row['status'] . '=' . (int)$row['cnt'];
        }
        echo $parts ? implode(', ', $parts) : '(없음)';
      ?>
    </p>
    <p><strong>실패 상위 20건</strong></p>
    <div class="table-responsive">
    <table class="table table-hover align-middle g-table g-table-dense mb-0">
      <thead>
        <tr>
          <th>ID</th><th>사용자 ID</th><th>이벤트 ID</th><th>채널</th><th>재시도 횟수</th><th>최근 오류</th><th>생성 시각</th>
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
          <tr><td colspan="7" class="text-muted-g small">데이터가 없습니다</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    <p class="text-muted-g small mt-2 mb-0">아웃바운드 스텁 실행(CLI): <code class="g-nowrap">php scripts/php/run_delivery_outbound_stub.php --limit=200</code></p>
    </div>
  </section>

  <section class="card g-card mb-4">
    <div class="card-body">
    <h3 class="h5">B. 실데이터 수집(ingest) 실행 안내 + 최근 지표 이벤트 10건</h3>
    <p class="text-muted-g small">지표 수집 실행(CLI): <code class="g-nowrap">php scripts/php/run_alert_ingest_real_metrics.php --since_minutes=1440 --limit=200</code></p>
    <div class="table-responsive">
    <table class="table table-hover align-middle g-table g-table-dense mb-0">
      <thead>
        <tr>
          <th>ID</th><th>이벤트 유형</th><th>제목</th><th>참조 ID</th><th>노선</th><th>발행 시각</th><th>생성 시각</th>
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
          <tr><td colspan="7" class="text-muted-g small">데이터가 없습니다</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
    </div>
  </section>

  <section class="card g-card">
    <div class="card-body">
    <h3 class="h5">C. 빠른 링크</h3>
    <p class="d-flex gap-2 mb-0">
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/alert_ops.php">알림 운영</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/alert_event_audit.php">알림 감사</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= $base ?>/ops_summary.php">운영 요약</a>
    </p>
    </div>
  </section>
  </main>
</body>
</html>
