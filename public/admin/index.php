<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$parseStatusFilter = isset($_GET['parse_status']) ? trim((string)$_GET['parse_status']) : '';
$sql = "
  SELECT id, source_name, title, file_path, ocr_status, parse_status, validation_status, updated_at
  FROM shuttle_source_doc
";
$params = [];
if ($parseStatusFilter !== '' && in_array($parseStatusFilter, ['pending', 'failed', 'running', 'success'], true)) {
  $sql .= " WHERE parse_status = :parse_status";
  $params[':parse_status'] = $parseStatusFilter;
}
$sql .= " ORDER BY id DESC LIMIT 200";
if ($params) {
  $st = pdo()->prepare($sql);
  $st->execute($params);
  $docs = $st->fetchAll();
} else {
  $docs = pdo()->query($sql)->fetchAll();
}

$routeStmt = pdo()->prepare("
  SELECT DISTINCT route_label
  FROM shuttle_stop_candidate
  WHERE source_doc_id = :id
  ORDER BY route_label ASC
");
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>관리자 - 문서 허브</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([['label' => '문서 허브', 'url' => null]], false); ?>
  <div class="g-page-header-row">
    <div class="g-page-head">
      <h2>문서 허브</h2>
      <p class="helper mb-0">문서 상태를 확인하고 검수/운영 화면으로 이동합니다.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/index.php?parse_status=failed">실패건 보기</a>
      <?php if ($parseStatusFilter !== ''): ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/index.php">전체 보기</a>
      <?php endif; ?>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1">검수 대기</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/alias_audit.php">Alias 감사</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/alert_ops.php">알림 운영</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/alert_event_audit.php">알림 감사</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/ops_summary.php">운영 요약</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/ops_control.php">운영 제어</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/upload_pdf.php">PDF 업로드</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/admin/ops_dashboard.php">운영 대시보드</a>
      <a class="btn btn-outline-secondary btn-sm" href="<?= APP_BASE ?>/user/home.php" target="_blank" rel="noopener">사용자 홈</a>
    </div>
  </div>
  <details class="kbd-help mb-3">
    <summary>단축키 안내</summary>
    <div class="body">/ : 검색 입력으로 이동 · Esc : 닫기 · Ctrl+Enter : 주요 폼 제출(지원 페이지)</div>
  </details>

  <div class="card g-card">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table mb-0">
    <thead>
      <tr>
        <th class="mono">ID</th><th>출처</th><th>제목</th><th>파일 경로</th>
        <th>OCR</th><th>파싱</th><th>검증</th><th>수정 시각</th><th>파싱·매칭 현황 · 노선검수</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($docs as $d): ?>
        <?php
          $routeStmt->execute([':id' => (int)$d['id']]);
          $routes = $routeStmt->fetchAll();
        ?>
        <tr>
          <td>
            <a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$d['id'] ?>"><?= (int)$d['id'] ?></a>
          </td>
          <td><?= htmlspecialchars((string)$d['source_name']) ?></td>
          <td><?= htmlspecialchars((string)$d['title']) ?></td>
          <td>
            <span class="d-inline-block text-truncate g-max-360"><?= htmlspecialchars((string)$d['file_path']) ?></span>
          </td>
          <td><?= htmlspecialchars((string)$d['ocr_status']) ?></td>
          <td><?= htmlspecialchars((string)$d['parse_status']) ?></td>
          <td><?= htmlspecialchars((string)$d['validation_status']) ?></td>
          <td><?= htmlspecialchars((string)$d['updated_at']) ?></td>
          <td class="d-flex flex-wrap gap-2 align-items-center">
            <a class="btn btn-sm btn-gilaime-primary" href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$d['id'] ?>">파싱·매칭 현황</a>
            <?php if (!$routes): ?>
              <span class="text-muted-g small">(노선 없음)</span>
            <?php else: ?>
              <?php foreach ($routes as $r): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$d['id'] ?>&route_label=<?= urlencode((string)$r['route_label']) ?>">
                  노선검수 <?= htmlspecialchars((string)$r['route_label']) ?>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  </div>
  </div>

  <p class="text-muted-g small mt-3">
    v0.5-6: route_review.php / promote.php 기반 승인→반영 플로우 확장
  </p>
  </main>
  <?php render_admin_tutorial_modal(); ?>
</body>
</html>
