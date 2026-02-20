<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
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
  <title>관리자 - 별칭 감사</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => '별칭 감사', 'url' => null],
  ], false); ?>

  <div class="g-page-head">
    <h2 class="h3">별칭 감사</h2>
    <p class="helper mb-0">읽기 전용. 승인/승격은 노선 검수 화면에서만 수행합니다.</p>
  </div>

  <h3 class="h5 mb-2">별칭 이슈 (차단/거절 로그)</h3>
  <p class="text-muted-g small mb-2">alias_text 길이 2 이하(legacy) 또는 canonical 미존재. 최대 100건.</p>
  <div class="card g-card mb-3">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th>id</th>
        <th>별칭 텍스트</th>
        <th>표준 텍스트</th>
        <th>이슈</th>
        <th>수정 시각</th>
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
          $issueLabel = isset($r['issue']) ? $r['issue'] : (mb_strlen(trim((string)($r['alias_text'] ?? ''))) <= 2 ? '별칭 길이 2 이하' : '—');
        ?>
        <tr>
          <td><?= (int)($r['id'] ?? 0) ?></td>
          <td><?= h((string)($r['alias_text'] ?? '')) ?></td>
          <td><?= h((string)($r['canonical_text'] ?? '')) ?></td>
          <td><?= h($issueLabel) ?></td>
          <td><?= !empty($r['updated_at']) ? h((string)$r['updated_at']) : '—' ?></td>
          <td><a href="<?= APP_BASE ?>/admin/review_queue.php?only_risky=1">검수 대기열</a></td>
        </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr><td colspan="6" class="text-muted-g small">이슈가 없습니다</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
  </div>
  </div>

  <h3 class="h5 mb-2">최근 별칭 등록/수정</h3>
  <p class="text-muted-g small mb-2">최근 50건. doc/route는 candidate 사용 시 route_review에서 연결됩니다.</p>
  <div class="card g-card">
  <div class="card-body">
  <div class="table-responsive">
  <table class="table table-hover align-middle g-table g-table-dense mb-0">
    <thead>
      <tr>
        <th>id</th>
        <th>별칭 텍스트</th>
        <th>표준 텍스트</th>
        <th>규칙 버전</th>
        <th>활성 여부</th>
        <th>생성 시각</th>
        <th>수정 시각</th>
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
        <tr><td colspan="7" class="text-muted-g small">데이터가 없습니다</td></tr>
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
