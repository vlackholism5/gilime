<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();

$sourceDocId = (int)($_GET['source_doc_id'] ?? 0);
$routeLabel  = trim((string)($_GET['route_label'] ?? ''));

if ($sourceDocId <= 0 || $routeLabel === '') {
  http_response_code(400);
  exit('bad params: source_doc_id, route_label required');
}

// latest PARSE_MATCH 선택 규칙 (고정): source_doc_id, job_type=PARSE_MATCH, job_status=success, ORDER BY id DESC LIMIT 1
$latestJobStmt = $pdo->prepare("
  SELECT id
  FROM shuttle_doc_job_log
  WHERE source_doc_id=:doc
    AND job_type='PARSE_MATCH'
    AND job_status='success'
  ORDER BY id DESC
  LIMIT 1
");
$latestJobStmt->execute([':doc' => $sourceDocId]);
$latestParseJobId = (int)(($latestJobStmt->fetch()['id'] ?? 0));

// flash
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$error = null;

// POST: approve/reject single row
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $candId = (int)($_POST['candidate_id'] ?? 0);

  if ($candId <= 0) {
    $error = 'bad candidate_id';
  } else {
    // 안전장치: 스냅샷(job_id) 외 후보는 업데이트 못 하게 (운영 안정)
    $candMetaStmt = $pdo->prepare("
      SELECT created_job_id
      FROM shuttle_stop_candidate
      WHERE id=:id AND source_doc_id=:doc AND route_label=:rl
      LIMIT 1
    ");
    $candMetaStmt->execute([':id' => $candId, ':doc' => $sourceDocId, ':rl' => $routeLabel]);
    $candMeta = $candMetaStmt->fetch();
    $candJobId = (int)($candMeta['created_job_id'] ?? 0);

    // B) stale candidate 완전 차단: created_job_id != latest 이면 업데이트 불가
    if ($latestParseJobId > 0 && $candJobId !== $latestParseJobId) {
      $error = 'stale candidate: latest parse_job_id=' . $latestParseJobId . ', candidate_job_id=' . $candJobId;
    } else {
      if ($action === 'approve') {
        $matchedStopId = trim((string)($_POST['matched_stop_id'] ?? ''));
        if ($matchedStopId === '') $matchedStopId = 'MANUAL';

        $stmt = $pdo->prepare("
          UPDATE shuttle_stop_candidate
          SET status='approved',
              approved_by=:uid,
              approved_at=NOW(),
              matched_stop_id=:msid,
              matched_stop_name=raw_stop_name,
              match_score=0.900,
              match_method='manual_approve',
              updated_at=NOW()
          WHERE id=:id
            AND source_doc_id=:doc
            AND route_label=:rl
            AND created_job_id=:jid
        ");
        $stmt->execute([
          ':uid' => (int)($_SESSION['user_id'] ?? 0),
          ':msid' => $matchedStopId,
          ':id' => $candId,
          ':doc' => $sourceDocId,
          ':rl' => $routeLabel,
          ':jid' => $latestParseJobId,
        ]);

        $_SESSION['flash'] = 'approved: candidate #' . $candId;
        header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
        exit;

      } elseif ($action === 'reject') {
        $reason = trim((string)($_POST['rejected_reason'] ?? 'manual reject'));

        $stmt = $pdo->prepare("
          UPDATE shuttle_stop_candidate
          SET status='rejected',
              rejected_reason=:rr,
              updated_at=NOW()
          WHERE id=:id
            AND source_doc_id=:doc
            AND route_label=:rl
            AND created_job_id=:jid
        ");
        $stmt->execute([
          ':rr' => $reason,
          ':id' => $candId,
          ':doc' => $sourceDocId,
          ':rl' => $routeLabel,
          ':jid' => $latestParseJobId,
        ]);

        $_SESSION['flash'] = 'rejected: candidate #' . $candId;
        header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
        exit;

      } else {
        $error = 'unknown action';
      }
    }
  }
}

// doc meta
$docStmt = $pdo->prepare("SELECT id, source_name, title, file_path, ocr_status, parse_status, validation_status, updated_at
                          FROM shuttle_source_doc WHERE id=:id LIMIT 1");
$docStmt->execute([':id' => $sourceDocId]);
$doc = $docStmt->fetch();
if (!$doc) {
  http_response_code(404);
  exit('source doc not found');
}

// candidates list (latest job snapshot 기준, 없으면 active fallback)
if ($latestParseJobId > 0) {
  $candStmt = $pdo->prepare("
    SELECT id, source_doc_id, route_label, created_job_id, seq_in_route, raw_stop_name,
           matched_stop_id, matched_stop_name, match_score, match_method,
           status, approved_by, approved_at, rejected_reason, updated_at, is_active
    FROM shuttle_stop_candidate
    WHERE source_doc_id=:doc
      AND route_label=:rl
      AND created_job_id=:jid
    ORDER BY seq_in_route ASC, id ASC
  ");
  $candStmt->execute([':doc' => $sourceDocId, ':rl' => $routeLabel, ':jid' => $latestParseJobId]);
} else {
  $candStmt = $pdo->prepare("
    SELECT id, source_doc_id, route_label, created_job_id, seq_in_route, raw_stop_name,
           matched_stop_id, matched_stop_name, match_score, match_method,
           status, approved_by, approved_at, rejected_reason, updated_at, is_active
    FROM shuttle_stop_candidate
    WHERE source_doc_id=:doc
      AND route_label=:rl
      AND is_active=1
    ORDER BY seq_in_route ASC, id ASC
  ");
  $candStmt->execute([':doc' => $sourceDocId, ':rl' => $routeLabel]);
}
$cands = $candStmt->fetchAll();

// route_stop list (is_active=1만 표시)
$routeStmt = $pdo->prepare("
  SELECT source_doc_id, route_label, stop_order, stop_id, stop_name, created_job_id
  FROM shuttle_route_stop
  WHERE source_doc_id=:doc AND route_label=:rl AND is_active=1
  ORDER BY stop_order ASC
");
$routeStmt->execute([':doc' => $sourceDocId, ':rl' => $routeLabel]);
$routeStops = $routeStmt->fetchAll();
// D) 현재 active 스냅샷의 PROMOTE job_id (created_job_id)
$activePromoteJobId = !empty($routeStops) ? (int)($routeStops[0]['created_job_id'] ?? 0) : 0;

// v0.6-9: PROMOTE 히스토리 최근 10건 + base_job_id, route_label, 해당 parse 스냅샷 후보/승인 수
$promoHistStmt = $pdo->prepare("
  SELECT j.id AS promote_job_id,
         j.created_at,
         j.result_note,
         j.route_label AS job_route_label,
         j.base_job_id,
         (SELECT COUNT(*) FROM shuttle_route_stop r WHERE r.created_job_id = j.id) AS rows_cnt,
         (SELECT COUNT(*) FROM shuttle_stop_candidate c
          WHERE c.source_doc_id = j.source_doc_id AND c.route_label = COALESCE(j.route_label, :rl)
            AND c.created_job_id = j.base_job_id) AS base_cand_total,
         (SELECT COUNT(*) FROM shuttle_stop_candidate c
          WHERE c.source_doc_id = j.source_doc_id AND c.route_label = COALESCE(j.route_label, :rl2)
            AND c.created_job_id = j.base_job_id AND c.status = 'approved') AS base_cand_approved
  FROM shuttle_doc_job_log j
  WHERE j.source_doc_id = :doc
    AND j.job_type = 'PROMOTE'
  ORDER BY j.id DESC
  LIMIT 10
");
$promoHistStmt->execute([':doc' => $sourceDocId, ':rl' => $routeLabel, ':rl2' => $routeLabel]);
$promoHistory = $promoHistStmt->fetchAll();

// summary counts (latest snapshot 기준, 없으면 active fallback)
if ($latestParseJobId > 0) {
  // HY093 방지: placeholder 재사용 금지
  $sumStmt = $pdo->prepare("
    SELECT
      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc1 AND route_label=:rl1 AND created_job_id=:jid1) AS cand_total,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc2 AND route_label=:rl2 AND created_job_id=:jid2 AND status='approved') AS cand_approved,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc3 AND route_label=:rl3 AND created_job_id=:jid3 AND status='pending') AS cand_pending,

      (SELECT COUNT(*)
         FROM shuttle_route_stop
        WHERE source_doc_id=:doc4 AND route_label=:rl4 AND is_active=1) AS route_stop_cnt
  ");
  $sumStmt->execute([
    ':doc1' => $sourceDocId, ':rl1' => $routeLabel, ':jid1' => $latestParseJobId,
    ':doc2' => $sourceDocId, ':rl2' => $routeLabel, ':jid2' => $latestParseJobId,
    ':doc3' => $sourceDocId, ':rl3' => $routeLabel, ':jid3' => $latestParseJobId,
    ':doc4' => $sourceDocId, ':rl4' => $routeLabel,
  ]);
} else {
  $sumStmt = $pdo->prepare("
    SELECT
      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc1 AND route_label=:rl1 AND is_active=1) AS cand_total,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc2 AND route_label=:rl2 AND is_active=1 AND status='approved') AS cand_approved,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc3 AND route_label=:rl3 AND is_active=1 AND status='pending') AS cand_pending,

      (SELECT COUNT(*)
         FROM shuttle_route_stop
        WHERE source_doc_id=:doc4 AND route_label=:rl4 AND is_active=1) AS route_stop_cnt
  ");
  $sumStmt->execute([
    ':doc1' => $sourceDocId, ':rl1' => $routeLabel,
    ':doc2' => $sourceDocId, ':rl2' => $routeLabel,
    ':doc3' => $sourceDocId, ':rl3' => $routeLabel,
    ':doc4' => $sourceDocId, ':rl4' => $routeLabel,
  ]);
}

$sum = $sumStmt->fetch() ?: ['cand_total'=>0,'cand_approved'=>0,'cand_pending'=>0,'route_stop_cnt'=>0];

// v0.6-7: approved 후보 중 matched_stop_id 빈 값/NULL 개수 (승격 전 실수 방지)
$emptyStopStmt = $pdo->prepare("
  SELECT COUNT(*) AS cnt
  FROM shuttle_stop_candidate
  WHERE source_doc_id=:doc AND route_label=:rl AND created_job_id=:jid
    AND status='approved'
    AND (matched_stop_id IS NULL OR matched_stop_id = '')
");
$emptyStopStmt->execute([':doc' => $sourceDocId, ':rl' => $routeLabel, ':jid' => $latestParseJobId]);
$approvedEmptyStopCnt = (int)($emptyStopStmt->fetch()['cnt'] ?? 0);

// promote gate (v0.6-7 보수적 조건)
$canPromote = ($latestParseJobId > 0)
  && ((int)$sum['cand_pending'] === 0)
  && ((int)$sum['cand_approved'] > 0)
  && ($approvedEmptyStopCnt === 0);
$promoteBlockReason = '';
if ($latestParseJobId <= 0) $promoteBlockReason = 'latest PARSE_MATCH job이 없어 승격할 수 없습니다.';
else if ((int)$sum['cand_pending'] > 0) $promoteBlockReason = 'pending 후보가 남아있어 승격할 수 없습니다.';
else if ((int)$sum['cand_approved'] <= 0) $promoteBlockReason = 'approved 후보가 없어 승격할 수 없습니다.';
else if ($approvedEmptyStopCnt > 0) $promoteBlockReason = 'approved 후보 중 matched_stop_id가 비어 있는 항목이 있어 승격할 수 없습니다.';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>Admin - Route Review</title>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding:24px;}
    a{color:#0b57d0;text-decoration:none;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
    .meta{display:grid;grid-template-columns:160px 1fr;gap:8px;margin:10px 0 18px;}
    .k{color:#666;}
    table{border-collapse:collapse;width:100%;margin-top:10px;}
    th,td{border-bottom:1px solid #eee;padding:8px;text-align:left;font-size:13px;vertical-align:top;}
    th{background:#fafafa;}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;border:1px solid #ddd;font-size:12px;}
    .row-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap;}
    input[type=text]{padding:6px 8px;border:1px solid #ddd;border-radius:8px;}
    button{padding:6px 10px;border:1px solid #ddd;border-radius:8px;background:#fff;cursor:pointer;}
    .err{color:#b00020;margin:10px 0;}
    .flash{margin:10px 0;padding:10px 12px;border:1px solid #e6e6e6;border-radius:10px;background:#fafafa;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .card{border:1px solid #eee;border-radius:12px;padding:12px;}
    .muted{color:#666;font-size:12px;}
  </style>
</head>
<body>
  <div class="top">
    <div>
      <div class="muted">
        <a href="<?= APP_BASE ?>/admin/index.php">Docs</a>
        &nbsp; / &nbsp;
        <a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$doc['id'] ?>">Doc #<?= (int)$doc['id'] ?></a>
      </div>
      <h2 style="margin:6px 0 0;">Route Review</h2>
      <div class="muted">source_doc_id=<?= (int)$sourceDocId ?>, route_label=<?= h($routeLabel) ?></div>
    </div>
    <div>
      <a href="<?= APP_BASE ?>/admin/logout.php">Logout</a>
    </div>
  </div>

  <?php if ($flash): ?><div class="flash"><?= h((string)$flash) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?= h((string)$error) ?></div><?php endif; ?>

  <div class="meta">
    <div class="k">title</div><div><?= h((string)$doc['title']) ?></div>
    <div class="k">file_path</div><div><?= h((string)$doc['file_path']) ?></div>
    <div class="k">ocr / parse / validation</div>
    <div>
      <span class="badge"><?= h((string)$doc['ocr_status']) ?></span>
      <span class="badge"><?= h((string)$doc['parse_status']) ?></span>
      <span class="badge"><?= h((string)$doc['validation_status']) ?></span>
    </div>
    <div class="k">updated</div><div><?= h((string)$doc['updated_at']) ?></div>
    <div class="k">latest_parse_job_id</div><div><?= (int)$latestParseJobId ?></div>
    <div class="k">스냅샷 비교</div>
    <div>Candidate 스냅샷: parse_job_id=<?= (int)$latestParseJobId ?> &nbsp;|&nbsp; Active route_stop 스냅샷: PROMOTE job_id (created_job_id)=<?= $activePromoteJobId ?></div>
    <div class="k">summary</div>
    <div>
      cand_total=<?= (int)$sum['cand_total'] ?> /
      approved=<?= (int)$sum['cand_approved'] ?> /
      pending=<?= (int)$sum['cand_pending'] ?> /
      route_stop=<?= (int)$sum['route_stop_cnt'] ?>
    </div>
  </div>

  <div class="grid2">
    <div class="card">
      <h3 style="margin:0 0 8px;">Candidates</h3>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>seq</th>
            <th>raw_stop_name</th>
            <th>status</th>
            <th>matched_stop_id</th>
            <th>action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cands as $c): ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><?= (int)$c['seq_in_route'] ?></td>
            <td><?= h((string)$c['raw_stop_name']) ?></td>
            <td><?= h((string)$c['status']) ?></td>
            <td><?= h((string)($c['matched_stop_id'] ?? '')) ?></td>
            <td>
              <div class="row-actions">
                <?php
                $isLatestSnapshot = ((int)($c['created_job_id'] ?? 0) === $latestParseJobId);
                if ((string)$c['status'] === 'pending' && $isLatestSnapshot):
                ?>
                  <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="action" value="approve" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="matched_stop_id" value="" placeholder="ex) ST0001" />
                    <button type="submit">Approve</button>
                  </form>
                  <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="action" value="reject" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="rejected_reason" value="manual reject" />
                    <button type="submit">Reject</button>
                  </form>
                <?php elseif ((string)$c['status'] === 'pending' && !$isLatestSnapshot): ?>
                  <span class="muted">stale (이전 스냅샷, 승인/거절 불가)</span>
                <?php else: ?>
                  <span class="muted">no action</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$cands): ?>
          <tr><td colspan="6" class="muted">no candidates</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div style="margin-top:12px;">
        <form method="post" action="<?= APP_BASE ?>/admin/promote.php" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
          <input type="hidden" name="source_doc_id" value="<?= (int)$sourceDocId ?>" />
          <input type="hidden" name="route_label" value="<?= h($routeLabel) ?>" />
          <input type="hidden" name="parse_job_id" value="<?= (int)$latestParseJobId ?>" />

          <?php if ($canPromote): ?>
            <button type="submit">Promote Approved → Route Stops</button>
            <span class="muted">approved만 승격합니다. 기존 active route_stop은 비활성화 후 신규 스냅샷으로 추가됩니다.</span>
          <?php else: ?>
            <button type="button" disabled style="opacity:.45;cursor:not-allowed;">
              Promote Approved → Route Stops
            </button>
            <span class="muted"><?= h($promoteBlockReason) ?></span>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <div class="card">
      <h3 style="margin:0 0 8px;">Route Stops</h3>
      <?php if ($activePromoteJobId > 0): ?>
      <p class="muted" style="margin:0 0 8px;">현재 active 스냅샷: PROMOTE job_id (created_job_id) = <?= $activePromoteJobId ?></p>
      <?php endif; ?>
      <table>
        <thead>
          <tr>
            <th>order</th>
            <th>stop_id</th>
            <th>stop_name</th>
            <th>created_job_id</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($routeStops as $rs): ?>
          <tr>
            <td><?= (int)$rs['stop_order'] ?></td>
            <td><?= h((string)$rs['stop_id']) ?></td>
            <td><?= h((string)$rs['stop_name']) ?></td>
            <td><?= (int)($rs['created_job_id'] ?? 0) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (!$routeStops): ?>
          <tr><td colspan="4" class="muted">no route stops</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <h3 style="margin-top:20px;">PROMOTE 히스토리 (최근 10건)</h3>
  <table>
    <thead>
      <tr>
        <th>promote_job_id</th>
        <th>created_at</th>
        <th>rows</th>
        <th>base_job_id (parse)</th>
        <th>result_note</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($promoHistory as $ph):
        $baseId = (int)($ph['base_job_id'] ?? 0);
        $baseCandTotal = (int)($ph['base_cand_total'] ?? 0);
        $baseCandApproved = (int)($ph['base_cand_approved'] ?? 0);
      ?>
      <tr>
        <td><?= (int)$ph['promote_job_id'] ?></td>
        <td><?= h((string)$ph['created_at']) ?></td>
        <td><?= (int)$ph['rows_cnt'] ?><?php if ((int)$ph['rows_cnt'] === 0): ?> <span class="muted" title="legacy">(legacy, 스냅샷 연결키 없음)</span><?php endif; ?></td>
        <td>
          <?php if ($baseId > 0): ?>
            <?= $baseId ?> <span class="muted">(후보 <?= $baseCandTotal ?> / 승인 <?= $baseCandApproved ?>)</span>
          <?php else: ?>
            <span class="muted">—</span>
          <?php endif; ?>
        </td>
        <td><?= h((string)($ph['result_note'] ?? '')) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$promoHistory): ?>
      <tr><td colspan="5" class="muted">no PROMOTE history</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <p class="muted" style="margin-top:14px;">
    운영 기준: PARSE_MATCH(job_id 스냅샷) → candidate 승인 → promote로 route_stop 반영 → job_log로 추적.
  </p>
</body>
</html>
