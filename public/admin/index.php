<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$docs = pdo()->query("
  SELECT id, source_name, title, file_path, ocr_status, parse_status, validation_status, updated_at
  FROM shuttle_source_doc
  ORDER BY id DESC
  LIMIT 200
")->fetchAll();

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
  <title>Admin - Docs</title>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding:24px;}
    table{border-collapse:collapse;width:100%;}
    th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;font-size:13px;vertical-align:top;}
    th{background:#fafafa;}
    a{color:#0b57d0;text-decoration:none;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
    .routes a{margin-right:10px;display:inline-block;}
  </style>
</head>
<body>
  <div class="top">
    <h2>Source Docs</h2>
    <a href="<?= APP_BASE ?>/admin/logout.php">Logout</a>
  </div>

  <table>
    <thead>
      <tr>
        <th>ID</th><th>source</th><th>title</th><th>file_path</th>
        <th>ocr</th><th>parse</th><th>validation</th><th>updated</th><th>routes</th>
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
          <td style="max-width:360px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?= htmlspecialchars((string)$d['file_path']) ?>
          </td>
          <td><?= htmlspecialchars((string)$d['ocr_status']) ?></td>
          <td><?= htmlspecialchars((string)$d['parse_status']) ?></td>
          <td><?= htmlspecialchars((string)$d['validation_status']) ?></td>
          <td><?= htmlspecialchars((string)$d['updated_at']) ?></td>
          <td class="routes">
            <?php if (!$routes): ?>
              <span style="color:#777;">(none)</span>
            <?php else: ?>
              <?php foreach ($routes as $r): ?>
                <a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$d['id'] ?>&route_label=<?= urlencode((string)$r['route_label']) ?>">
                  Review <?= htmlspecialchars((string)$r['route_label']) ?>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <p style="margin-top:12px;color:#666;font-size:12px;">
    v0.5-6: route_review.php / promote.php 기반 승인→반영 플로우 확장
  </p>
</body>
</html>
