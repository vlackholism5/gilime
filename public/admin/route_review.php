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

/** v0.6-36: approve/reject/alias 후 redirect 시 GET 파라미터 유지 (action/candidate_id 등 제외) */
function build_route_review_redirect_query(): string {
  global $sourceDocId, $routeLabel;
  $parts = ['source_doc_id=' . (int)$sourceDocId, 'route_label=' . urlencode($routeLabel)];
  $keep = ['only_unmatched', 'only_low', 'only_risky', 'top', 'show_reco', 'show_qs', 'quick_mode', 'rec_limit', 'q', 'show_advanced'];
  foreach ($keep as $k) {
    if (isset($_GET[$k]) && (string)$_GET[$k] !== '') {
      $parts[] = $k . '=' . urlencode((string)$_GET[$k]);
    }
  }
  return implode('&', $parts);
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
        
        // v0.6-21: alias 등록 검증 강화
        if ($aliasText === '') {
          $error = 'raw_stop_name이 비어 있어 alias로 등록할 수 없습니다.';
        } elseif (mb_strlen($aliasText) <= 2) {
          $error = 'alias blocked: alias_text too short (<=2). raw_stop_name="' . htmlspecialchars($aliasText, ENT_QUOTES, 'UTF-8') . '"';
        } else {
          // canonical이 stop_master에 존재하는지 확인 (저장 전 필수)
          $match = lookupStopMasterByCanonical($pdo, $canonicalText);
          if (!$match) {
            $error = 'alias blocked: canonical not found in stop_master. canonical_text="' . htmlspecialchars($canonicalText, ENT_QUOTES, 'UTF-8') . '"';
          }
        }
        
        // 검증 통과 시에만 alias 저장 + live rematch
        if (!$error) {
          $insAlias = $pdo->prepare("
            INSERT INTO shuttle_stop_alias (alias_text, canonical_text, rule_version, is_active, created_at, updated_at)
            VALUES (:alias, :canonical, 'v0.6-12', 1, NOW(), NOW())
            ON DUPLICATE KEY UPDATE canonical_text=VALUES(canonical_text), updated_at=NOW()
          ");
          $insAlias->execute([':alias' => $aliasText, ':canonical' => $canonicalText]);

          // v0.6-13: canonical으로 stop_master 조회 후 해당 candidate 1건 즉시 재매칭 (latest 스냅샷만)
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
          }
        }
      } else {
        $error = 'candidate를 찾을 수 없습니다.';
      }
      if (!$error) {
        header('Location: ' . APP_BASE . '/admin/route_review.php?' . build_route_review_redirect_query());
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

        // v0.6-21: LOW(like_prefix) 승인 게이트 — 체크 강제
        $candDetailStmt = $pdo->prepare("SELECT match_method FROM shuttle_stop_candidate WHERE id=:id LIMIT 1");
        $candDetailStmt->execute([':id' => $candId]);
        $candDetail = $candDetailStmt->fetch();
        $candMethod = (string)($candDetail['match_method'] ?? '');
        
        if ($candMethod === 'like_prefix') {
          $confirmLow = (string)($_POST['confirm_low'] ?? '');
          if ($confirmLow !== '1') {
            $error = 'LOW(like_prefix) 매칭은 확인 체크 후 승인할 수 있습니다.';
          }
        }
        
        if (!$error) {
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
          header('Location: ' . APP_BASE . '/admin/route_review.php?' . build_route_review_redirect_query());
          exit;
        }

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
        header('Location: ' . APP_BASE . '/admin/route_review.php?' . build_route_review_redirect_query());
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

// v0.6-19: LOW(like_prefix)만 보기 (latest 스냅샷 기준만)
$onlyLow = (int)($_GET['only_low'] ?? 0);
if ($onlyLow && $latestParseJobId > 0) {
  $cands = array_values(array_filter($cands, function ($c) {
    $method = (string)($c['match_method'] ?? '');
    return $method === 'like_prefix';
  }));
}

// v0.6-32: risky(LOW/NONE) pending만 보기 (latest 스냅샷 기준만)
$onlyRisky = (int)($_GET['only_risky'] ?? 0);

// v0.6-35: 초단축 모드 — quick_mode=1이면 명시되지 않은 파라미터만 기본값으로 강제
$quickMode = (int)($_GET['quick_mode'] ?? 0);
if ($quickMode) {
  if (!array_key_exists('only_risky', $_GET)) $onlyRisky = 1;
  if (!array_key_exists('only_unmatched', $_GET)) $onlyUnmatched = 1;
}

if ($onlyRisky && $latestParseJobId > 0) {
  $cands = array_values(array_filter($cands, function ($c) {
    $status = (string)($c['status'] ?? '');
    $method = $c['match_method'] ?? null;
    return $status === 'pending' && ($method === 'like_prefix' || $method === null || $method === '');
  }));
}

// v0.6-33: top 파라미터 — 최종 필터링 후 상위 N건만 표시 (10~300 clamp)
$topParam = (int)($_GET['top'] ?? 0);
if ($quickMode && !array_key_exists('top', $_GET)) $topParam = 30;
if ($topParam > 0) {
  $topParam = max(10, min(300, $topParam));
  $cands = array_slice($cands, 0, $topParam);
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

// v0.6-34: Lazy render 옵션 (기본 OFF)
$showReco = (int)($_GET['show_reco'] ?? 0);
$showQs = (int)($_GET['show_qs'] ?? 0);
// v0.6-36: quick_mode 기본은 경량(show_reco=0, show_qs=0); 필요 시 토글로 켬
if ($quickMode) {
  if (!array_key_exists('show_reco', $_GET)) $showReco = 0;
  if (!array_key_exists('show_qs', $_GET)) $showQs = 0;
}

// v0.6-37: 고급 옵션(필터 링크) 기본 숨김
$showAdvanced = (int)($_GET['show_advanced'] ?? 0);

// v0.6-15: Stop Master Quick Search (GET q) — show_qs=1일 때만 실행
$searchQuery = trim((string)($_GET['q'] ?? ''));
$stopMasterSearchResults = [];
if ($showQs && $searchQuery !== '') {
  $stopMasterSearchResults = searchStopMasterQuick($pdo, $searchQuery);
}

// v0.6-17 / v0.6-33 / v0.6-34: 추천 canonical — show_reco=1 AND only_unmatched=1일 때만 Top N 계산
$recCache = [];
$recHit = 0;
$recMiss = 0;
$recSkipped = 0;
$recSkippedDisplay = '0';
$recLimitParam = (int)($_GET['rec_limit'] ?? 30);
$recLimitParam = max(10, min(100, $recLimitParam));
$recommendedByCandId = [];
if ($showReco && $onlyUnmatched) {
  $candsForRec = array_slice($cands, 0, $recLimitParam);
  $recSkipped = count($cands) - count($candsForRec);
  $recSkippedDisplay = (string)$recSkipped;
  foreach ($candsForRec as $c) {
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
} elseif (!$showReco) {
  $recSkippedDisplay = 'all';
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
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding:24px; background:#f9fafb; max-width:1800px; margin:0 auto;}
    a{color:#0b57d0;text-decoration:none;}
    a:hover{text-decoration:underline;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px; padding:16px; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.08);}
    .top h2{margin:4px 0 0; font-size:22px; font-weight:600;}
    .meta{display:grid;grid-template-columns:160px 1fr;gap:12px;margin:0 0 16px; padding:16px; background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.08);}
    .k{color:#666; font-weight:500;}
    table{border-collapse:collapse;width:100%;margin-top:10px; background:#fff;}
    th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;font-size:13px;vertical-align:top;}
    th{background:#f7f8fa; font-weight:600; color:#444;}
    .badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px; font-weight:500; text-transform:uppercase;}
    .badge.pending{background:#fff4e5; color:#b56b00; border:1px solid #ffcc80;}
    .badge.approved{background:#e7f5e9; color:#1b7a3b; border:1px solid #81c784;}
    .badge.rejected{background:#fee; color:#c62828; border:1px solid #ef5350;}
    .badge.HIGH{background:#e7f5e9; color:#1b7a3b; border:1px solid #81c784;}
    .badge.MED{background:#e3f2fd; color:#1565c0; border:1px solid #64b5f6;}
    .badge.LOW{background:#fff4e5; color:#b56b00; border:1px solid #ffcc80;}
    .badge.NONE{background:#f5f5f5; color:#757575; border:1px solid #ccc;}
    .row-actions{display:flex;gap:8px;align-items:flex-start;flex-direction:column; padding:8px 0;}
    .row-actions form{display:flex;gap:6px;align-items:center; background:#fafafa; padding:8px; border-radius:8px; width:100%;}
    input[type=text]{padding:7px 10px;border:1px solid #ddd;border-radius:6px; font-size:13px;}
    button{padding:7px 12px;border:1px solid #0b57d0;border-radius:6px;background:#0b57d0; color:#fff; cursor:pointer; font-weight:500; font-size:12px;}
    button:hover{background:#094bbd;}
    button:disabled{opacity:.5; cursor:not-allowed; background:#ccc; border-color:#ccc;}
    button.secondary{background:#fff; color:#0b57d0; border:1px solid #0b57d0;}
    button.secondary:hover{background:#f0f4ff;}
    .err{color:#b00020;margin:10px 0; padding:12px; background:#fee; border-radius:8px; border:1px solid #ef5350;}
    .flash{margin:10px 0;padding:12px 16px;border:1px solid #81c784;border-radius:8px;background:#e7f5e9; color:#1b7a3b;}
    .grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .card{border:1px solid #e0e0e0;border-radius:12px;padding:16px; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,0.08);}
    .card h3{margin:0 0 12px; font-size:16px; font-weight:600; color:#333;}
    .muted{color:#666;font-size:12px;}
    .summary-grid{display:grid; grid-template-columns:repeat(4, 1fr); gap:12px; margin-bottom:16px;}
    .summary-item{padding:12px; background:#fff; border:1px solid #e0e0e0; border-radius:8px; text-align:center;}
    .summary-item .label{font-size:11px; color:#666; text-transform:uppercase; margin-bottom:4px;}
    .summary-item .value{font-size:20px; font-weight:600; color:#333;}
    .summary-item.warn .value{color:#b56b00;}
    .summary-item.ok .value{color:#1b7a3b;}
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

  <!-- A. 상단: 상태 요약(카드) -->
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
    <div>Candidate 스냅샷: parse_job_id=<?= (int)$latestParseJobId ?> | Active route_stop: PROMOTE job_id=<?= $activePromoteJobId ?></div>
  </div>

  <div class="summary-grid">
    <div class="summary-item">
      <div class="label">Total</div>
      <div class="value"><?= (int)$sum['cand_total'] ?></div>
    </div>
    <div class="summary-item <?= (int)$sum['cand_approved'] > 0 ? 'ok' : '' ?>">
      <div class="label">Approved</div>
      <div class="value"><?= (int)$sum['cand_approved'] ?></div>
    </div>
    <div class="summary-item <?= (int)$sum['cand_pending'] > 0 ? 'warn' : '' ?>">
      <div class="label">Pending</div>
      <div class="value"><?= (int)$sum['cand_pending'] ?></div>
    </div>
    <div class="summary-item">
      <div class="label">Route Stops</div>
      <div class="value"><?= (int)$sum['route_stop_cnt'] ?></div>
    </div>
  </div>

  <?php if ($latestParseJobId > 0): ?>
  <div class="summary-grid">
    <div class="summary-item ok">
      <div class="label">Auto Matched</div>
      <div class="value"><?= (int)$sum['auto_matched_cnt'] ?></div>
    </div>
    <div class="summary-item warn">
      <div class="label">Low Confidence</div>
      <div class="value"><?= (int)$sum['low_confidence_cnt'] ?></div>
    </div>
    <div class="summary-item">
      <div class="label">None Matched</div>
      <div class="value"><?= (int)$sum['none_matched_cnt'] ?></div>
    </div>
    <div class="summary-item">
      <div class="label">Alias Used</div>
      <div class="value"><?= (int)$sum['alias_used_cnt'] ?></div>
    </div>
  </div>
  <?php endif; ?>

  <!-- B. 중단: 필터/검색 -->
  <?php
  $baseUrl = APP_BASE . '/admin/route_review.php?source_doc_id=' . (int)$sourceDocId . '&route_label=' . urlencode($routeLabel);
  if ($searchQuery !== '' && $showQs) $baseUrl .= '&q=' . urlencode($searchQuery);
  if ($showReco) $baseUrl .= '&show_reco=1';
  if ($showQs) $baseUrl .= '&show_qs=1';
  if ($quickMode) $baseUrl .= '&quick_mode=1';
  if ($showAdvanced) $baseUrl .= '&show_advanced=1';
  // v0.6-36: 초단축 2종 — 빠른 검수(경량) / 추천+검색 포함
  $urlQuickModeFast = APP_BASE . '/admin/route_review.php?source_doc_id=' . (int)$sourceDocId . '&route_label=' . urlencode($routeLabel) . '&quick_mode=1';
  $urlQuickModeRecoQs = APP_BASE . '/admin/route_review.php?source_doc_id=' . (int)$sourceDocId . '&route_label=' . urlencode($routeLabel) . '&quick_mode=1&show_reco=1&show_qs=1';
  // v0.6-38: show_advanced=0일 때 문구 축약
  if (!$showAdvanced) {
    $filterState = '기본(고급숨김)';
  } else {
    $filterState = $onlyUnmatched ? '매칭 실패만' : '전체';
    if ($onlyLow) $filterState .= ' + LOW만';
    if (!empty($onlyRisky)) $filterState .= ' + 리스크';
    if ($topParam > 0) $filterState .= ' + Top' . $topParam;
    if ($showReco) $filterState .= ' + 추천ON';
    if ($showQs) $filterState .= ' + 검색ON';
    if ($quickMode) $filterState .= ' + 초단축';
  }
  $riskySuffix = !empty($onlyRisky) ? '&only_risky=1' : '';
  $topSuffix = $topParam > 0 ? '&top=' . $topParam : '';
  $urlQuickModeOff = APP_BASE . '/admin/route_review.php?source_doc_id=' . (int)$sourceDocId . '&route_label=' . urlencode($routeLabel);
  if ($searchQuery !== '' && $showQs) $urlQuickModeOff .= '&q=' . urlencode($searchQuery);
  if ($showReco) $urlQuickModeOff .= '&show_reco=1';
  if ($showQs) $urlQuickModeOff .= '&show_qs=1';
  $urlQuickModeOff .= $riskySuffix . $topSuffix . ($onlyUnmatched ? '&only_unmatched=1' : '') . ($onlyLow ? '&only_low=1' : '');
  $urlAdvancedOn = $baseUrl . $riskySuffix . $topSuffix . ($onlyUnmatched ? '&only_unmatched=1' : '') . ($onlyLow ? '&only_low=1' : '') . '&show_advanced=1';
  $urlAdvancedOff = APP_BASE . '/admin/route_review.php?source_doc_id=' . (int)$sourceDocId . '&route_label=' . urlencode($routeLabel);
  if ($searchQuery !== '' && $showQs) $urlAdvancedOff .= '&q=' . urlencode($searchQuery);
  if ($showReco) $urlAdvancedOff .= '&show_reco=1';
  if ($showQs) $urlAdvancedOff .= '&show_qs=1';
  $urlAdvancedOff .= $riskySuffix . $topSuffix . ($onlyUnmatched ? '&only_unmatched=1' : '') . ($onlyLow ? '&only_low=1' : '');
  if ($quickMode) $urlAdvancedOff .= '&quick_mode=1';
  $showRecoSuffix = $showReco ? '&show_reco=1' : '';
  $showQsSuffix = $showQs ? '&show_qs=1' : '';
  ?>
  <div class="card" style="margin-bottom:16px;">
    <h3 style="margin:0 0 8px;">필터 / Quick Search</h3>
    <p style="margin:0 0 6px;">
      <?php if ($showAdvanced): ?>
      <a href="<?= h($urlAdvancedOff) ?>">고급 옵션 숨기기</a>
      &nbsp;|&nbsp;
      <a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$sourceDocId ?>&route_label=<?= urlencode($routeLabel) ?>">전체 보기</a>
      &nbsp;|&nbsp;
      <a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$sourceDocId ?>&route_label=<?= urlencode($routeLabel) ?>&only_risky=1&top=30&only_unmatched=1&show_reco=1">빠른 시작: 리스크 Top30 + 추천 ON</a>
      &nbsp;|&nbsp;
      <?php endif; ?>
      <?php if (!$showAdvanced): ?>
      <a href="<?= h($urlQuickModeFast) ?>">초단축: 빠른 검수</a>
      &nbsp;|&nbsp;
      <?php if ($onlyUnmatched): ?>
      <a href="<?= $baseUrl ?><?= $riskySuffix ?><?= $topSuffix ?><?= $onlyLow ? '&only_low=1' : '' ?>">매칭 실패 해제</a>
      <?php else: ?>
      <a href="<?= $baseUrl ?><?= $riskySuffix ?><?= $topSuffix ?>&only_unmatched=1<?= $onlyLow ? '&only_low=1' : '' ?>">매칭 실패만 보기</a>
      <?php endif; ?>
      &nbsp;|&nbsp;
      <?php endif; ?>
      <?php if ($showAdvanced): ?>
      <a href="<?= h($urlQuickModeFast) ?>">초단축: 빠른 검수</a>
      &nbsp;|&nbsp;
      <a href="<?= h($urlQuickModeRecoQs) ?>">초단축: 추천+검색 포함</a>
      &nbsp;|&nbsp;
      <a href="<?= h($urlQuickModeOff) ?>">초단축 모드 OFF</a>
      &nbsp;|&nbsp;
      <?php if ($onlyUnmatched): ?>
      <a href="<?= $baseUrl ?><?= $riskySuffix ?><?= $topSuffix ?><?= $onlyLow ? '&only_low=1' : '' ?>">매칭 실패 해제</a>
      <?php else: ?>
      <a href="<?= $baseUrl ?><?= $riskySuffix ?><?= $topSuffix ?>&only_unmatched=1<?= $onlyLow ? '&only_low=1' : '' ?>">매칭 실패만 보기</a>
      <?php endif; ?>
      &nbsp;|&nbsp;
      <?php else: ?>
      <a href="<?= APP_BASE ?>/admin/route_review.php?source_doc_id=<?= (int)$sourceDocId ?>&route_label=<?= urlencode($routeLabel) ?>&show_advanced=0">전체 보기</a>
      &nbsp;|&nbsp;
      <a href="<?= h($urlQuickModeRecoQs) ?>">초단축: 추천+검색 포함</a>
      &nbsp;|&nbsp;
      <a href="<?= h($urlQuickModeOff) ?>">초단축 모드 OFF</a>
      &nbsp;|&nbsp;
      <?php endif; ?>
      <?php if ($showAdvanced): ?>
      &nbsp;|&nbsp;
      <?php if ($onlyLow): ?>
      <a href="<?= $baseUrl ?><?= $riskySuffix ?><?= $topSuffix ?><?= $onlyUnmatched ? '&only_unmatched=1' : '' ?>">LOW 해제</a>
      <?php else: ?>
      <a href="<?= $baseUrl ?><?= $riskySuffix ?><?= $topSuffix ?>&only_low=1<?= $onlyUnmatched ? '&only_unmatched=1' : '' ?>">LOW만 보기</a>
      <?php endif; ?>
      &nbsp;|&nbsp;
      <?php if (!empty($onlyRisky)): ?>
      <a href="<?= $baseUrl ?><?= $topSuffix ?><?= $onlyUnmatched ? '&only_unmatched=1' : '' ?><?= $onlyLow ? '&only_low=1' : '' ?>">리스크 해제</a>
      <?php else: ?>
      <a href="<?= $baseUrl ?>&only_risky=1<?= $onlyUnmatched ? '&only_unmatched=1' : '' ?><?= $onlyLow ? '&only_low=1' : '' ?>">리스크 후보만 보기</a>
      <?php endif; ?>
      &nbsp;|&nbsp;
      <a href="<?= $baseUrl ?>&only_risky=1&top=30<?= $onlyUnmatched ? '&only_unmatched=1' : '' ?><?= $onlyLow ? '&only_low=1' : '' ?>">리스크 Top30</a>
      &nbsp;|&nbsp;
      <a href="<?= $baseUrl ?>&only_risky=1&top=100<?= $onlyUnmatched ? '&only_unmatched=1' : '' ?><?= $onlyLow ? '&only_low=1' : '' ?>">리스크 Top100</a>
      <?php if (!$showQs): ?>
      &nbsp;|&nbsp;
      <?php $qsOnUrl = APP_BASE . '/admin/route_review.php?source_doc_id=' . (int)$sourceDocId . '&route_label=' . urlencode($routeLabel) . '&show_qs=1'; ?>
      <?php if ($onlyUnmatched): $qsOnUrl .= '&only_unmatched=1'; endif; ?>
      <?php if ($onlyLow): $qsOnUrl .= '&only_low=1'; endif; ?>
      <?php if (!empty($onlyRisky)): $qsOnUrl .= '&only_risky=1'; endif; ?>
      <?php if ($topParam > 0): $qsOnUrl .= '&top=' . $topParam; endif; ?>
      <?php if ($showReco): $qsOnUrl .= '&show_reco=1'; endif; ?>
      <?php if ($showAdvanced): $qsOnUrl .= '&show_advanced=1'; endif; ?>
      <a href="<?= $qsOnUrl ?>">Quick Search 표시</a>
      <?php endif; ?>
      <?php endif; ?>
      <?php if (!$showAdvanced): ?>
      &nbsp;|&nbsp;
      <a href="<?= h($urlAdvancedOn) ?>">고급 옵션 보기</a>
      <?php endif; ?>
    </p>
    <p class="muted" style="margin:0 0 10px; font-size:12px;">현재: <?= h($filterState) ?></p>
    <?php if ($showQs): ?>
    <form method="get" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
      <input type="hidden" name="source_doc_id" value="<?= (int)$sourceDocId ?>" />
      <input type="hidden" name="route_label" value="<?= h($routeLabel) ?>" />
      <?php if ($quickMode): ?><input type="hidden" name="quick_mode" value="1" /><?php endif; ?>
      <?php if ($showAdvanced): ?><input type="hidden" name="show_advanced" value="1" /><?php endif; ?>
      <?php if ($onlyUnmatched): ?><input type="hidden" name="only_unmatched" value="1" /><?php endif; ?>
      <?php if ($onlyLow): ?><input type="hidden" name="only_low" value="1" /><?php endif; ?>
      <?php if (!empty($onlyRisky)): ?><input type="hidden" name="only_risky" value="1" /><?php endif; ?>
      <?php if ($topParam > 0): ?><input type="hidden" name="top" value="<?= (int)$topParam ?>" /><?php endif; ?>
      <?php if ($showReco): ?><input type="hidden" name="show_reco" value="1" /><?php endif; ?>
      <input type="text" name="q" value="<?= h($searchQuery) ?>" placeholder="stop_name 검색" size="20" />
      <button type="submit">Search</button>
    </form>
    <p class="muted" style="margin:4px 0 0; font-size:11px;">alias 입력 전 stop_master 존재 확인용 (exact → normalized → like_prefix)</p>
    <?php if ($searchQuery !== ''): ?>
    <table style="margin-top:10px;">
      <thead><tr><th>stop_id</th><th>stop_name</th><th>match_type</th></tr></thead>
      <tbody>
        <?php foreach ($stopMasterSearchResults as $r): ?>
        <tr><td><?= h($r['stop_id']) ?></td><td><?= h($r['stop_name']) ?></td><td><?= h($r['match_type']) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$stopMasterSearchResults): ?>
        <tr><td colspan="3" class="muted">no results</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    <?php endif; ?>
    <?php endif; ?>
  </div>

  <!-- C. 하단: Candidates 테이블 + Actions -->
  <div class="grid2">
    <div class="card">
      <h3 style="margin:0 0 8px;">Candidates</h3>
      <p class="muted" style="margin:0 0 12px; font-size:12px;">추천 canonical 계산: <?php
if ($showReco && $onlyUnmatched) {
  echo 'ON (limit=' . (int)$recLimitParam . '), cache hits=' . $recHit . ', misses=' . $recMiss . ', skipped=' . $recSkippedDisplay;
} elseif (!$showReco) {
  echo 'OFF (show_reco=0), cache hits=0, misses=0, skipped=' . $recSkippedDisplay;
} else {
  echo 'OFF, cache hits=0 / misses=0';
}
?></p>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>seq</th>
            <th>원문 정류장명</th>
            <th>추천 canonical</th>
            <th>정규화</th>
            <th>status</th>
            <th>신뢰도</th>
            <th>매칭 결과</th>
            <th>근거</th>
            <th>action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($cands as $c):
            $recommendedCanonical = (string)($recommendedByCandId[(int)$c['id']] ?? '');
            $canonPlaceholder = ($showReco && $onlyUnmatched && $recommendedCanonical !== '') ? $recommendedCanonical : '정식 명칭';
          ?>
          <tr>
            <td><?= (int)$c['id'] ?></td>
            <td><?= (int)$c['seq_in_route'] ?></td>
            <td><input type="text" readonly value="<?= h((string)($c['raw_stop_name'] ?? '')) ?>" style="width:100%;max-width:180px;box-sizing:border-box;" title="선택 후 복사" /></td>
            <td><?= $recommendedCanonical !== '' ? h($recommendedCanonical) : '<span class="muted">—</span>' ?></td>
            <td><?= h(normalizeStopNameDisplay((string)($c['raw_stop_name'] ?? ''))) ?></td>
            <td><span class="badge <?= h((string)$c['status']) ?>"><?= h((string)$c['status']) ?></span></td>
            <td><span class="badge <?= h(matchConfidenceLabel($c['match_method'] ?? null)) ?>"><?= h(matchConfidenceLabel($c['match_method'] ?? null)) ?></span></td>
            <td><?= h((string)($c['matched_stop_id'] ?? '')) ?><?= (string)($c['matched_stop_name'] ?? '') !== '' ? ' ' . h((string)$c['matched_stop_name']) : '' ?></td>
            <td><?= h((string)($c['match_method'] ?? '')) ?><?= isset($c['match_score']) ? ' (' . h((string)$c['match_score']) . ')' : '' ?></td>
            <td>
              <div class="row-actions">
                <?php
                $isLatestSnapshot = ((int)($c['created_job_id'] ?? 0) === $latestParseJobId);
                if ((string)$c['status'] === 'pending' && $isLatestSnapshot):
                ?>
                  <form method="post">
                    <input type="hidden" name="action" value="approve" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="matched_stop_id" value="<?= h((string)($c['matched_stop_id'] ?? '')) ?>" placeholder="stop_id (ex: ST0001)" style="flex:1;" />
                    <?php if ((string)($c['match_method'] ?? '') === 'like_prefix'): ?>
                    <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#b56b00;">
                      <input type="checkbox" name="confirm_low" value="1" />
                      LOW(like_prefix) 확인함
                    </label>
                    <?php endif; ?>
                    <button type="submit">Approve</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="action" value="reject" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="rejected_reason" value="manual reject" placeholder="reason" style="flex:1;" />
                    <button type="submit" class="secondary">Reject</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="action" value="register_alias" />
                    <input type="hidden" name="candidate_id" value="<?= (int)$c['id'] ?>" />
                    <input type="text" name="canonical_text" value="<?= h((string)($c['matched_stop_name'] ?? '')) ?>" placeholder="<?= h($canonPlaceholder) ?>" title="stop_master 정식 정류장명" style="flex:1;" />
                    <button type="submit" class="secondary">alias 등록</button>
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
          <tr><td colspan="10" class="muted">no candidates</td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <div style="margin-top:12px;">
        <?php
        // v0.6-19: Promote 전 LOW 비중 경고
        $showLowWarning = false;
        if ($latestParseJobId > 0) {
          $lowCnt = (int)$sum['low_confidence_cnt'];
          $autoCnt = (int)$sum['auto_matched_cnt'];
          if ($lowCnt > 0 && $autoCnt > 0) {
            $lowRatio = $lowCnt / $autoCnt;
            if ($lowRatio >= 0.30) {
              $showLowWarning = true;
            }
          }
        }
        ?>
        
        <?php if ($showLowWarning): ?>
        <div style="padding:12px; background:#fff4e5; border:1px solid #ffcc80; border-radius:8px; margin-bottom:12px;">
          <strong style="color:#b56b00;">주의:</strong>
          <span style="color:#b56b00;">like_prefix(LOW) 비중이 높습니다 (<?= (int)$sum['low_confidence_cnt'] ?> / <?= (int)$sum['auto_matched_cnt'] ?> 자동매칭). Promote 전 후보 재검토 권장.</span>
        </div>
        <?php endif; ?>
        
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

  <?php
  // v0.6-32 체크리스트: 리스크 토글/only_risky URL/게이트 동일 동작.
  // v0.6-33 체크리스트: 추천 TopN, 리스크 Top30/100, 게이트 동일.
  // v0.6-34 체크리스트:
  // 1) 기본 진입(파라미터 없음): 추천/Quick Search 기본 숨김, v0.6-33과 동일 체감.
  // 2) show_qs=0일 때 Quick Search 미표시, "Quick Search 표시" 링크만.
  // 3) show_qs=1일 때 Quick Search 기존처럼 동작.
  // 4) show_reco=0일 때 추천 전부 "—", placeholder "정식 명칭", meta OFF(show_reco=0), skipped=all.
  // 5) show_reco=1+only_unmatched=1일 때만 추천 TopN 계산 및 meta hit/miss/skipped.
  // 6) LOW 승인 체크/alias 검증/stale 차단/promote 게이트 동일.
  ?>
</body>
</html>
