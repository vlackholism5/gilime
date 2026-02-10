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

/** v0.6-12: 표시용 정규화명 (trim + collapse space) */
function normalizeStopNameDisplay(string $s): string {
  return trim(preg_replace('/\s+/', ' ', $s));
}

/** v0.6-15: stop_master 퀵 검색 (exact → normalized → like_prefix, normalized 2글자 이하면 like_prefix 미적용), 최대 10건 */
function searchStopMasterQuick(PDO $pdo, string $q): array {
  $raw = trim($q);
  if ($raw === '') return [];
  $normalized = normalizeStopNameDisplay($raw);
  $seen = [];
  $out = [];
  $exactStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = :name LIMIT 1");
  $likeStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE CONCAT(:prefix, '%') LIMIT 10");
  $exactStmt->execute([':name' => $raw]);
  $row = $exactStmt->fetch();
  if ($row && !isset($seen[(string)$row['stop_id']])) {
    $seen[(string)$row['stop_id']] = true;
    $out[] = ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_type' => 'exact'];
  }
  if ($normalized !== $raw) {
    $exactStmt->execute([':name' => $normalized]);
    $row = $exactStmt->fetch();
    if ($row && !isset($seen[(string)$row['stop_id']])) {
      $seen[(string)$row['stop_id']] = true;
      $out[] = ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_type' => 'normalized'];
    }
  }
  if (mb_strlen($normalized) > 2) {
    $likeStmt->execute([':prefix' => $raw]);
    while (count($out) < 10 && ($row = $likeStmt->fetch())) {
      if (!isset($seen[(string)$row['stop_id']])) {
        $seen[(string)$row['stop_id']] = true;
        $out[] = ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name'], 'match_type' => 'like_prefix'];
      }
    }
  }
  return array_slice($out, 0, 10);
}

/** v0.6-13: canonical으로 stop_master 조회 (인덱스만: exact → normalized → like_prefix) */
function lookupStopMasterByCanonical(PDO $pdo, string $canonical): ?array {
  $c = trim($canonical);
  if ($c === '') return null;
  $norm = normalizeStopNameDisplay($c);
  $exactStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = :name LIMIT 1");
  $exactStmt->execute([':name' => $c]);
  $row = $exactStmt->fetch();
  if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name']];
  if ($norm !== $c) {
    $exactStmt->execute([':name' => $norm]);
    $row = $exactStmt->fetch();
    if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name']];
  }
  $likeStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE CONCAT(:prefix, '%') LIMIT 1");
  $likeStmt->execute([':prefix' => $c]);
  $row = $likeStmt->fetch();
  if ($row) return ['stop_id' => (string)$row['stop_id'], 'stop_name' => (string)$row['stop_name']];
  return null;
}

// POST: approve/reject/register_alias
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = (string)($_POST['action'] ?? '');
  $candId = (int)($_POST['candidate_id'] ?? 0);

  // v0.6-12/13: alias 등록 (raw_stop_name → alias_text, 입력값 → canonical_text) + 즉시 재매칭
  if ($action === 'register_alias' && $candId > 0) {
    $canonicalText = trim((string)($_POST['canonical_text'] ?? ''));
    if ($canonicalText === '') {
      $error = 'canonical_text(정식 명칭)을 입력하세요.';
    } else {
      $cRow = $pdo->prepare("SELECT raw_stop_name, created_job_id FROM shuttle_stop_candidate WHERE id=:id AND source_doc_id=:doc AND route_label=:rl LIMIT 1");
      $cRow->execute([':id' => $candId, ':doc' => $sourceDocId, ':rl' => $routeLabel]);
      $cRec = $cRow->fetch();
      if ($cRec) {
        $aliasText = normalizeStopNameDisplay((string)$cRec['raw_stop_name']);
        if ($aliasText !== '') {
          $insAlias = $pdo->prepare("
            INSERT INTO shuttle_stop_alias (alias_text, canonical_text, rule_version, is_active, created_at, updated_at)
            VALUES (:alias, :canonical, 'v0.6-12', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE canonical_text=VALUES(canonical_text), updated_at=NOW()
          ");
          $insAlias->execute([':alias' => $aliasText, ':canonical' => $canonicalText]);

          // v0.6-13: canonical으로 stop_master 조회 후 해당 candidate 1건 즉시 재매칭 (latest 스냅샷만)
          $match = lookupStopMasterByCanonical($pdo, $canonicalText);
          $candJobId = (int)($cRec['created_job_id'] ?? 0);
          $isLatest = ($latestParseJobId > 0 && $candJobId === $latestParseJobId);
          if ($match && $isLatest) {
            $upd = $pdo->prepare("
              UPDATE shuttle_stop_candidate
              SET matched_stop_id=:msid, matched_stop_name=:msname, match_score=0.95, match_method='alias_live_rematch', updated_at=NOW()
              WHERE id=:id AND source_doc_id=:doc AND route_label=:rl AND created_job_id=:jid
            ");
            $upd->execute([
              ':msid' => $match['stop_id'], ':msname' => $match['stop_name'],
              ':id' => $candId, ':doc' => $sourceDocId, ':rl' => $routeLabel, ':jid' => $latestParseJobId,
            ]);
            $_SESSION['flash'] = 'alias saved + candidate rematched (id=' . $candId . ', stop_id=' . $match['stop_id'] . ')';
          } elseif ($match && !$isLatest) {
            $_SESSION['flash'] = 'alias 등록: "' . htmlspecialchars($aliasText, ENT_QUOTES, 'UTF-8') . '" → "' . htmlspecialchars($canonicalText, ENT_QUOTES, 'UTF-8') . '" (stale candidate라 rematch 생략)';
          } else {
            $_SESSION['flash'] = 'alias saved but canonical not found in master';
          }
        } else {
          $error = 'raw_stop_name이 비어 있어 alias로 등록할 수 없습니다.';
        }
      } else {
        $error = 'candidate를 찾을 수 없습니다.';
      }
      if (!$error) {
        header('Location: ' . APP_BASE . '/admin/route_review.php?source_doc_id=' . $sourceDocId . '&route_label=' . urlencode($routeLabel));
        exit;
      }
    }
  }

  if ($candId <= 0 && $action !== 'register_alias') {
    $error = 'bad candidate_id';
  } elseif ($action === 'approve' || $action === 'reject') {
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

// v0.6-16: 매칭 실패만 보기 (latest 스냅샷 기준만)
$onlyUnmatched = (int)($_GET['only_unmatched'] ?? 0);
if ($onlyUnmatched && $latestParseJobId > 0) {
  $cands = array_values(array_filter($cands, function ($c) {
    $msid = trim((string)($c['matched_stop_id'] ?? ''));
    return $msid === '';
  }));
}

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
  // HY093 방지: placeholder 재사용 금지. v0.6-18: auto_matched/low_confidence/none_matched/alias_used 추가
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
        WHERE source_doc_id=:doc4 AND route_label=:rl4 AND is_active=1) AS route_stop_cnt,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc5 AND route_label=:rl5 AND created_job_id=:jid5 AND match_method IS NOT NULL) AS auto_matched_cnt,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc6 AND route_label=:rl6 AND created_job_id=:jid6 AND match_method='like_prefix') AS low_confidence_cnt,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc7 AND route_label=:rl7 AND created_job_id=:jid7 AND (matched_stop_id IS NULL OR matched_stop_id='')) AS none_matched_cnt,

      (SELECT COUNT(*)
         FROM shuttle_stop_candidate
        WHERE source_doc_id=:doc8 AND route_label=:rl8 AND created_job_id=:jid8 AND match_method IN ('alias_exact','alias_normalized','alias_live_rematch')) AS alias_used_cnt
  ");
  $sumStmt->execute([
    ':doc1' => $sourceDocId, ':rl1' => $routeLabel, ':jid1' => $latestParseJobId,
    ':doc2' => $sourceDocId, ':rl2' => $routeLabel, ':jid2' => $latestParseJobId,
    ':doc3' => $sourceDocId, ':rl3' => $routeLabel, ':jid3' => $latestParseJobId,
    ':doc4' => $sourceDocId, ':rl4' => $routeLabel,
    ':doc5' => $sourceDocId, ':rl5' => $routeLabel, ':jid5' => $latestParseJobId,
    ':doc6' => $sourceDocId, ':rl6' => $routeLabel, ':jid6' => $latestParseJobId,
    ':doc7' => $sourceDocId, ':rl7' => $routeLabel, ':jid7' => $latestParseJobId,
    ':doc8' => $sourceDocId, ':rl8' => $routeLabel, ':jid8' => $latestParseJobId,
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

$sumRow = $sumStmt->fetch();
$sum = $sumRow ?: ['cand_total'=>0,'cand_approved'=>0,'cand_pending'=>0,'route_stop_cnt'=>0];
// v0.6-18: latest 없을 때 4개 카운트 0
if ($latestParseJobId <= 0) {
  $sum['auto_matched_cnt'] = 0;
  $sum['low_confidence_cnt'] = 0;
  $sum['none_matched_cnt'] = 0;
  $sum['alias_used_cnt'] = 0;
} else {
  $sum['auto_matched_cnt'] = (int)($sumRow['auto_matched_cnt'] ?? 0);
  $sum['low_confidence_cnt'] = (int)($sumRow['low_confidence_cnt'] ?? 0);
  $sum['none_matched_cnt'] = (int)($sumRow['none_matched_cnt'] ?? 0);
  $sum['alias_used_cnt'] = (int)($sumRow['alias_used_cnt'] ?? 0);
}

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

// v0.6-15: Stop Master Quick Search (GET q)
$searchQuery = trim((string)($_GET['q'] ?? ''));
$stopMasterSearchResults = [];
if ($searchQuery !== '') {
  $stopMasterSearchResults = searchStopMasterQuick($pdo, $searchQuery);
}

// v0.6-17: 추천 canonical 요청 단위 캐시 — only_unmatched=1일 때만 계산
$recCache = [];
$recHit = 0;
$recMiss = 0;
$recommendedByCandId = [];
if ($onlyUnmatched) {
  foreach ($cands as $c) {
    $raw = (string)($c['raw_stop_name'] ?? '');
    $cacheKey = normalizeStopNameDisplay($raw);
    if (array_key_exists($cacheKey, $recCache)) {
      $recommendedByCandId[(int)$c['id']] = (string)($recCache[$cacheKey] ?? '');
      $recHit++;
    } else {
      $recList = searchStopMasterQuick($pdo, $raw);
      $firstRec = $recList[0] ?? null;
      $val = $firstRec ? (string)$firstRec['stop_name'] : '';
      $recCache[$cacheKey] = $val !== '' ? $val : null;
      $recommendedByCandId[(int)$c['id']] = $val;
      $recMiss++;
    }
  }
}

/** v0.6-18: 매칭 신뢰도 표시 전용 (텍스트만) */
function matchConfidenceLabel(?string $matchMethod): string {
  if ($matchMethod === null || $matchMethod === '') return 'NONE';
  if (in_array($matchMethod, ['exact', 'alias_live_rematch', 'alias_exact'], true)) return 'HIGH';
  if (in_array($matchMethod, ['normalized', 'alias_normalized'], true)) return 'MED';
  if ($matchMethod === 'like_prefix') return 'LOW';
  return 'NONE';
}

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
      <?php if ($latestParseJobId > 0): ?>
        <br><span class="muted">auto_matched=<?= (int)$sum['auto_matched_cnt'] ?> /
        low_confidence(like_prefix)=<?= (int)$sum['low_confidence_cnt'] ?> /
        none_matched=<?= (int)$sum['none_matched_cnt'] ?> /
        alias_used=<?= (int)$sum['alias_used_cnt'] ?></span>
      <?php endif; ?>
    </div>
    <div class="k">추천 canonical</div>
    <div class="muted" style="font-size:0.85em;">추천 canonical 계산: <?= $onlyUnmatched ? 'ON' : 'OFF' ?>, cache hits=<?= $recHit ?>, misses=<?= $recMiss ?></div>
  </div>

  <div class="card" style="margin-bottom:16px;">
    <h3 style="margin:0 0 8px;">Stop Master Quick Search</h3>
    <p class="muted" style="margin:0 0 8px;font-size:0.9em;">alias canonical_text 입력 전 stop_master 존재 여부 확인용 (exact → normalized → like_prefix, 2글자 이하는 like_prefix 미적용)</p>
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="source_doc_id" value="<?= (int)$sourceDocId ?>" />
      <input type="hidden" name="route_label" value="<?= h($routeLabel) ?>" />
      <?php if ($onlyUnmatched): ?><input type="hidden" name="only_unmatched" value="1" /><?php endif; ?>
      <input type="text" name="q" value="<?= h($searchQuery) ?>" placeholder="stop_name" size="20" />
      <button type="submit">Search</button>
    </form>
    <?php if ($searchQuery !== ''): ?>
    <table style="margin-top:10px;">
      <thead><tr><th>stop_id</th><th>stop_name</th><th>match_type</th></tr></thead>
      <tbody>
        <?php foreach ($stopMasterSearchResults as $r): ?>
        <tr><td><?= h($r['stop_id']) ?></td><td><?= h($r['stop_name']) ?></td><td><?= h($r['match_type']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$stopMasterSearchResults): ?>
        <tr><td colspan="3" class="muted">no results (2글자 이하 검색어는 like_prefix 미적용)</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>

  <div class="grid2">
    <div class="card">
      <h3 style="margin:0 0 8px;">Candidates</h3>
      <p style="margin:0 0 8px;">
        <?php if ($onlyUnmatched): ?>
        <a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$sourceDocId ?>&route_label=<?= urlencode($routeLabel) ?><?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>">전체 보기</a>
        <span class="muted"> (매칭 실패만 표시 중)</span>
        <?php else: ?>
        <a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$sourceDocId ?>&route_label=<?= urlencode($routeLabel) ?>&only_unmatched=1<?= $searchQuery !== '' ? '&q=' . urlencode($searchQuery) : '' ?>">매칭 실패만 보기</a>
        <?php endif; ?>
      </p>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>seq</th>
            <th>raw_stop_name</th>
            <th>추천 canonical</th>
            <th>normalized_name</th>
            <th>status</th>
            <th>매칭 신뢰도</th>
            <th>matched_stop_id</th>
            <th>match_method</th>
            <th>match_score</th>
            <th>action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cands as $c):
            $recommendedCanonical = (string)($recommendedByCandId[(int)$c['id']] ?? '');
            $canonPlaceholder = ($onlyUnmatched && $recommendedCanonical !== '') ? $recommendedCanonical : '정식 명칭';
          ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><?= (int)$c['seq_in_route'] ?></td>
            <td><input type="text" readonly value="<?= h((string)($c['raw_stop_name'] ?? '')) ?>" style="width:100%;max-width:180px;box-sizing:border-box;" title="선택 후 복사" /></td>
            <td><?= $recommendedCanonical !== '' ? h($recommendedCanonical) : '<span class="muted">—</span>' ?></td>
            <td><?= h(normalizeStopNameDisplay((string)($c['raw_stop_name'] ?? ''))) ?></td>
            <td><?= h((string)$c['status']) ?></td>
            <td><?= h(matchConfidenceLabel($c['match_method'] ?? null)) ?></td>
            <td><?= h((string)($c['matched_stop_id'] ?? '')) ?></td>
            <td><?= h((string)($c['match_method'] ?? '')) ?></td>
            <td><?= isset($c['match_score']) ? h((string)$c['match_score']) : '' ?></td>
            <td>
              <div class="row-actions">
                <?php
                $isLatestSnapshot = ((int)($c['created_job_id'] ?? 0) === $latestParseJobId);
                if ((string)$c['status'] === 'pending' && $isLatestSnapshot):
                ?>
                  <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="action" value="approve" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="matched_stop_id" value="<?= h((string)($c['matched_stop_id'] ?? '')) ?>" placeholder="ex) ST0001" />
                    <button type="submit">Approve</button>
                  </form>
                  <form method="post" style="display:flex;gap:6px;align-items:center;">
                    <input type="hidden" name="action" value="reject" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="rejected_reason" value="manual reject" />
                    <button type="submit">Reject</button>
                  </form>
                  <form method="post" style="display:inline;">
                    <input type="hidden" name="action" value="register_alias" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="canonical_text" value="<?= h((string)($c['matched_stop_name'] ?? '')) ?>" placeholder="<?= h($canonPlaceholder) ?>" size="14" title="stop_master에 존재하는 정식 정류장명. 추천값은 placeholder 참고." />
                    <button type="submit">alias 등록</button>
                    <span class="muted" style="font-size:0.85em;">stop_master 정식 명칭(placeholder 참고)</span>
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
          <tr><td colspan="11" class="muted">no candidates</td></tr>
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
