<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();

// (1) Alias Issues: legacy short(alias_text<=2) or canonical not in stop_master. No shuttle_alias_log — use shuttle_stop_alias only. Max 100.
$aliasIssues = [];
try {
  $issuesStmt = $pdo->query("
    SELECT a.id, a.alias_text, a.canonical_text, a.updated_at
    FROM shuttle_stop_alias a
    WHERE a.is_active = 1 AND (LENGTH(TRIM(a.alias_text)) <= 2)
    ORDER BY a.updated_at DESC
    LIMIT 100
  ");
  $aliasIssues = $issuesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $aliasIssues = [];
}

// canonical 미존재 후보: canonical_text가 stop_master에 없는 alias (선택, 1쿼리)
$canonicalMissing = [];
try {
  $missStmt = $pdo->query("
    SELECT a.id, a.alias_text, a.canonical_text
    FROM shuttle_stop_alias a
    LEFT JOIN seoul_bus_stop_master m ON m.stop_name = a.canonical_text
    WHERE a.is_active = 1 AND m.stop_id IS NULL
    LIMIT 50
  ");
  $canonicalMissing = $missStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $canonicalMissing = [];
}

// (2) Recent Alias Writes: 최근 50건
$recentAlias = [];
try {
  $recentStmt = $pdo->query("
    SELECT id, alias_text, canonical_text, rule_version, is_active, created_at, updated_at
    FROM shuttle_stop_alias
    ORDER BY updated_at DESC
    LIMIT 50
  ");
  $recentAlias = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $recentAlias = [];
}

function h(string $s): string {
  return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>Admin - Alias Audit</title>
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
      <span class="muted"> / Alias Audit</span>
    </div>
    <a href="<?= APP_BASE ?>/admin/logout.php">Logout</a>
  </div>

  <h2>Alias Audit</h2>
  <p class="muted" style="margin:0 0 12px;">read-only. 승인/승격은 route_review에서만.</p>

  <h3 class="card">Alias Issues (blocked/rejected logs)</h3>
  <p class="muted" style="margin:0 0 8px;">alias_text 길이 2 이하(legacy) 또는 canonical 미존재. 최대 100건.</p>
  <table>
    <thead>
      <tr>
        <th>id</th>
        <th>alias_text</th>
        <th>canonical_text</th>
        <th>이슈</th>
        <th>updated_at</th>
        <th>링크</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $seen = [];
      foreach ($aliasIssues as $row) {
        $seen[(int)$row['id']] = true;
      }
      foreach ($canonicalMissing as $row) {
        if (isset($seen[(int)$row['id']])) continue;
        $aliasIssues[] = $row + ['updated_at' => null, 'issue' => 'canonical 미존재'];
      }
      usort($aliasIssues, function ($a, $b) {
        $at = $a['updated_at'] ?? '';
        $bt = $b['updated_at'] ?? '';
        return strcmp((string)$bt, (string)$at);
      });
      $aliasIssues = array_slice($aliasIssues, 0, 100);
      ?>
      <?php if ($aliasIssues): ?>
        <?php foreach ($aliasIssues as $r):
          $issueLabel = isset($r['issue']) ? $r['issue'] : (mb_strlen(trim((string)($r['alias_text'] ?? ''))) <= 2 ? 'alias_text<=2' : '—');
        ?>
        <tr>
          <td><?= (int)($r['id'] ?? 0) ?></td>
          <td><?= h((string)($r['alias_text'] ?? '')) ?></td>
          <td><?= h((string)($r['canonical_text'] ?? '')) ?></td>
          <td><?= h($issueLabel) ?></td>
          <td><?= !empty($r['updated_at']) ? h((string)$r['updated_at']) : '—' ?></td>
          <td><a href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1">Queue</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="muted">no issues (또는 테이블 확인 필요)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <h3 class="card">Recent Alias Writes</h3>
  <p class="muted" style="margin:0 0 8px;">최근 50건. doc/route는 candidate 사용 시 route_review에서 연결.</p>
  <table>
    <thead>
      <tr>
        <th>id</th>
        <th>alias_text</th>
        <th>canonical_text</th>
        <th>rule_version</th>
        <th>is_active</th>
        <th>created_at</th>
        <th>updated_at</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($recentAlias): ?>
        <?php foreach ($recentAlias as $r): ?>
        <tr>
          <td><?= (int)$r['id'] ?></td>
          <td><?= h((string)$r['alias_text']) ?></td>
          <td><?= h((string)$r['canonical_text']) ?></td>
          <td><?= h((string)($r['rule_version'] ?? '')) ?></td>
          <td><?= (int)($r['is_active'] ?? 0) ?></td>
          <td><?= h((string)($r['created_at'] ?? '')) ?></td>
          <td><?= h((string)($r['updated_at'] ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="7" class="muted">no data (또는 테이블 확인 필요)</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
