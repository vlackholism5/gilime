<?php
declare(strict_types=1);
/**
 * v1.8 길찾기 — 경로 조회 로직
 * @see docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md
 *
 * 출발/도착 정류장명 → stop_id 매칭 → 경로 검색 (seoul_bus + shuttle_temp)
 */
require_once __DIR__ . '/../auth/db.php';

/**
 * 사용자 입력 → stop_id 매칭 (seoul_bus_stop_master)
 * exact → like_prefix → like_contains 순서 (ROUTE_FINDER_DIAGNOSIS 5.2)
 */
function route_finder_resolve_stop(PDO $pdo, string $input): ?array
{
    $raw = trim($input);
    if ($raw === '') return null;

    // "이름 · 123" 또는 "이름 (정류소번호 123)" 또는 "이름 (123)" 형식: 숫자를 stop_id로 조회 (구분자 · 또는 괄호)
    if (preg_match('/^(.+?)\s*·\s*(\d+)\s*$/u', $raw, $m) || preg_match('/^(.+?)\s*\(정류소번호\s*(\d+)\)\s*$/u', $raw, $m) || preg_match('/^(.+?)\s*\((\d+)\)\s*$/u', $raw, $m)) {
        $stopId = (int)$m[2];
        try {
            $st = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_route_stop_master WHERE stop_id = ? LIMIT 1");
            $st->execute([$stopId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $name = isset($row['stop_name']) && trim((string)$row['stop_name']) !== '' ? trim((string)$row['stop_name']) : trim((string)$m[1]);
                return ['stop_id' => (int)$row['stop_id'], 'stop_name' => $name];
            }
            $st = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_id = ? LIMIT 1");
            $st->execute([$stopId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $name = isset($row['stop_name']) && trim((string)$row['stop_name']) !== '' ? trim((string)$row['stop_name']) : trim((string)$m[1]);
                return ['stop_id' => (int)$row['stop_id'], 'stop_name' => $name];
            }
        } catch (Throwable $e) {
            // fallback to name match below
        }
    }

    // 정류장ID:{stop_id} 또는 숫자만 들어온 경우: stop_id 직접 매칭 (route_stop_master 또는 stop_master에 있으면 인정)
    if (preg_match('/^정류장ID:(\d+)$/u', $raw, $m) || preg_match('/^(\d+)$/u', $raw, $m)) {
        $stopId = (int)$m[1];
        $displayName = '정류장ID:' . $stopId;
        try {
            // 1) route_stop_master에 있으면 경로 검색 가능 → 인정 (이름 있으면 사용, 없으면 정류장ID:nnn)
            $st = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_route_stop_master WHERE stop_id = ? LIMIT 1");
            $st->execute([$stopId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $name = isset($row['stop_name']) && trim((string)$row['stop_name']) !== '' ? trim((string)$row['stop_name']) : $displayName;
                return ['stop_id' => (int)$row['stop_id'], 'stop_name' => $name];
            }
            // 2) stop_master에만 있어도 인정 (이름 null이면 정류장ID:nnn)
            $st = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_id = ? LIMIT 1");
            $st->execute([$stopId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $name = isset($row['stop_name']) && trim((string)$row['stop_name']) !== '' ? trim((string)$row['stop_name']) : $displayName;
                return ['stop_id' => (int)$row['stop_id'], 'stop_name' => $name];
            }
        } catch (Throwable $e) {
            // 테이블 없거나 오류 시 아래 이름 매칭으로 진행
        }
    }

    $normalized = trim(preg_replace('/\s+/u', ' ', $raw));

    try {
        $exactStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = :name LIMIT 1");
        $exactStmt->execute([':name' => $raw]);
        $row = $exactStmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['stop_id' => (int)$row['stop_id'], 'stop_name' => (string)$row['stop_name']];
        }

        if ($normalized !== $raw) {
            $exactStmt->execute([':name' => $normalized]);
            $row = $exactStmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return ['stop_id' => (int)$row['stop_id'], 'stop_name' => (string)$row['stop_name']];
            }
        }

        if (mb_strlen($raw) >= 2) {
            $likeStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE CONCAT(:prefix, '%') LIMIT 1");
            $likeStmt->execute([':prefix' => $raw]);
            $row = $likeStmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return ['stop_id' => (int)$row['stop_id'], 'stop_name' => (string)$row['stop_name']];
            }

            $containsStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE CONCAT('%', :contains, '%') ORDER BY LENGTH(stop_name) ASC LIMIT 1");
            $containsStmt->execute([':contains' => $raw]);
            $row = $containsStmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return ['stop_id' => (int)$row['stop_id'], 'stop_name' => (string)$row['stop_name']];
            }
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

/**
 * 검색어 포함 정류장 목록 (근처 정류장 보기)
 * @return array [['stop_id' => int, 'stop_name' => string], ...]
 */
function route_finder_nearby_stops(PDO $pdo, string $input, int $limit = 30): array
{
    $raw = trim($input);
    if ($raw === '' || mb_strlen($raw) < 1) return [];

    $contains = '%' . $raw . '%';
    $prefix = $raw;

    try {
        $sql = "
            SELECT stop_id, stop_name FROM seoul_bus_stop_master
            WHERE stop_name LIKE :contains
            ORDER BY CASE WHEN stop_name LIKE CONCAT(:prefix, '%') THEN 0 ELSE 1 END, stop_name
            LIMIT :lim
        ";
        $st = $pdo->prepare($sql);
        $st->bindValue(':contains', $contains, PDO::PARAM_STR);
        $st->bindValue(':prefix', $prefix, PDO::PARAM_STR);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['stop_id' => (int)$r['stop_id'], 'stop_name' => (string)$r['stop_name']];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 경로 검색 가능한 샘플 정류장 (route_stop_master에 등록된 정류장)
 * @return array [['stop_name' => string], ...]
 */
function route_finder_sample_stops(PDO $pdo, int $limit = 25): array
{
    try {
        $sql = "
            SELECT DISTINCT s.stop_name
            FROM seoul_bus_route_stop_master rs
            INNER JOIN seoul_bus_stop_master s ON s.stop_id = rs.stop_id
            ORDER BY s.stop_name
            LIMIT :lim
        ";
        $st = $pdo->prepare($sql);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn($r) => ['stop_name' => (string)($r['stop_name'] ?? '')], $rows);
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 사용자 입력 → 추천 정류장 목록 (자동완성용)
 * prefix 일치 우선, 포함 일치 후순
 * @return array [['stop_id' => int, 'stop_name' => string, 'display_label' => string], ...]
 */
function route_finder_suggest_stops(PDO $pdo, string $input, int $limit = 10): array
{
    $raw = trim($input);
    if ($raw === '' || mb_strlen($raw) < 1) return [];

    $prefix = $raw;
    $contains = '%' . $raw . '%';

    try {
        $sql = "
            SELECT stop_id, stop_name, lat, lng FROM seoul_bus_stop_master
            WHERE stop_name LIKE :contains
            ORDER BY CASE WHEN stop_name LIKE CONCAT(:prefix, '%') THEN 0 ELSE 1 END, stop_name
            LIMIT :lim
        ";
        $st = $pdo->prepare($sql);
        $st->bindValue(':contains', $contains, PDO::PARAM_STR);
        $st->bindValue(':prefix', $prefix, PDO::PARAM_STR);
        $st->bindValue(':lim', $limit, PDO::PARAM_INT);
        $st->execute();
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $stopId = (int)$r['stop_id'];
            $stopName = trim((string)($r['stop_name'] ?? ''));
            $displayLabel = $stopName !== '' ? $stopName . ' · ' . $stopId : '정류장 · ' . $stopId;
            $item = ['stop_id' => $stopId, 'stop_name' => $stopName !== '' ? $stopName : '정류장', 'display_label' => $displayLabel];
            if (isset($r['lat']) && isset($r['lng']) && $r['lat'] !== null && $r['lng'] !== null) {
                $item['lat'] = (float)$r['lat'];
                $item['lng'] = (float)$r['lng'];
            }
            $out[] = $item;
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * 정류장 stop_id의 좌표 조회 (지도 마커/폴리라인용)
 * @return array{lat: float, lng: float}|null
 */
function route_finder_stop_coords(PDO $pdo, int $stopId): ?array
{
    try {
        $st = $pdo->prepare("SELECT lat, lng FROM seoul_bus_stop_master WHERE stop_id = ? AND lat IS NOT NULL AND lng IS NOT NULL LIMIT 1");
        $st->execute([$stopId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return ['lat' => (float)$row['lat'], 'lng' => (float)$row['lng']];
        }
    } catch (Throwable $e) {
        return null;
    }
    return null;
}

/**
 * 출발·도착 stop_id로 경로 검색
 * @return array [
 *   ['route_type' => 'bus'|'shuttle_temp', 'route_id'|'temp_route_id', 'route_name'|'route_label', ...],
 *   ...
 * ]
 */
function route_finder_search(PDO $pdo, int $fromStopId, int $toStopId, bool $includeShuttle): array
{
    $routes = [];

    try {
        // 1) seoul_bus_route_stop_master: 출발 정류장 → 도착 정류장 순서로 지나는 노선
        $sql = "
            SELECT rm.route_id, rm.route_name, rm.first_bus_time, rm.last_bus_time, rm.term_min,
                   f.seq_in_route AS from_seq, t.seq_in_route AS to_seq,
                   f.stop_name AS from_name, t.stop_name AS to_name
            FROM seoul_bus_route_stop_master f
            INNER JOIN seoul_bus_route_stop_master t
              ON f.route_id = t.route_id AND f.seq_in_route < t.seq_in_route
            INNER JOIN seoul_bus_route_master rm ON rm.route_id = f.route_id
            WHERE f.stop_id = :from_id AND t.stop_id = :to_id
            ORDER BY (t.seq_in_route - f.seq_in_route) ASC
            LIMIT 10
        ";
        $st = $pdo->prepare($sql);
        $st->execute([':from_id' => $fromStopId, ':to_id' => $toStopId]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $seqDiff = (int)($r['to_seq'] ?? 0) - (int)($r['from_seq'] ?? 0);
            $estMin = min(120, $seqDiff * 3); // 약 3분/정류장, 최대 120분
            $routes[] = [
                'route_type' => 'bus',
                'route_id' => (int)$r['route_id'],
                'route_name' => $r['route_name'] ?? '',
                'from_seq' => (int)$r['from_seq'],
                'to_seq' => (int)$r['to_seq'],
                'first_bus_time' => $r['first_bus_time'] ?? null,
                'last_bus_time' => $r['last_bus_time'] ?? null,
                'term_min' => $r['term_min'] ?? null,
                'headway_min' => $r['term_min'] ? (int)$r['term_min'] . '분' : null,
                'est_min' => $estMin,
                'from_name' => $r['from_name'] ?? '',
                'to_name' => $r['to_name'] ?? '',
            ];
        }
    } catch (Throwable $e) {
        // 테이블 없거나 에러 시 무시
    }

    if ($includeShuttle) {
        try {
            $sql = "
                SELECT tr.id AS temp_route_id, tr.route_label, tr.first_bus_time, tr.last_bus_time, tr.headway_min,
                       f.seq_in_route AS from_seq, t.seq_in_route AS to_seq
                FROM shuttle_temp_route_stop f
                INNER JOIN shuttle_temp_route_stop t
                  ON f.temp_route_id = t.temp_route_id AND f.seq_in_route < t.seq_in_route
                INNER JOIN shuttle_temp_route tr ON tr.id = f.temp_route_id
                WHERE f.stop_id = :from_id AND t.stop_id = :to_id
                ORDER BY (t.seq_in_route - f.seq_in_route) ASC
                LIMIT 10
            ";
            $st = $pdo->prepare($sql);
            $st->execute([':from_id' => $fromStopId, ':to_id' => $toStopId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $seqDiff = (int)($r['to_seq'] ?? 0) - (int)($r['from_seq'] ?? 0);
                $estMin = min(120, $seqDiff * 3);
            $routes[] = [
                'route_type' => 'shuttle_temp',
                'temp_route_id' => (int)$r['temp_route_id'],
                'route_label' => $r['route_label'] ?? '',
                'from_seq' => (int)$r['from_seq'],
                'to_seq' => (int)$r['to_seq'],
                'first_bus_time' => $r['first_bus_time'] ?? null,
                'last_bus_time' => $r['last_bus_time'] ?? null,
                'headway_min' => $r['headway_min'] ?? null,
                'est_min' => $estMin,
                'from_name' => '',
                'to_name' => '',
            ];
            }
        } catch (Throwable $e) {
            // shuttle_temp 테이블 없으면 무시
        }
    }

    return $routes;
}

/**
 * 표시용 정류장 라벨 (정류장ID:nnn 또는 숫자만 있으면 DB에서 이름 조회, 없으면 "정류장 (ID: nnn)" 반환)
 */
function route_finder_stop_display_name(PDO $pdo, string $input): string
{
    $raw = trim($input);
    if ($raw === '') return '';

    if (preg_match('/^정류장ID:(\d+)$/u', $raw, $m) || preg_match('/^(\d+)$/u', $raw, $m)) {
        $stopId = (int)$m[1];
        try {
            $st = $pdo->prepare("SELECT stop_name FROM seoul_bus_route_stop_master WHERE stop_id = ? AND stop_name IS NOT NULL AND TRIM(stop_name) != '' LIMIT 1");
            $st->execute([$stopId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && trim((string)$row['stop_name']) !== '') return trim((string)$row['stop_name']);
            $st = $pdo->prepare("SELECT stop_name FROM seoul_bus_stop_master WHERE stop_id = ? AND stop_name IS NOT NULL AND TRIM(stop_name) != '' LIMIT 1");
            $st->execute([$stopId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && trim((string)$row['stop_name']) !== '') return trim((string)$row['stop_name']);
        } catch (Throwable $e) {
            // ignore
        }
        return '정류장 · ' . $stopId;
    }

    return $raw;
}

/**
 * 표시용 정류장 라벨 — 이름 · 숫자 형식 (경로 결과 등에서 사용)
 */
function route_finder_stop_display_label(PDO $pdo, string $input): string
{
    $resolved = route_finder_resolve_stop($pdo, $input);
    if ($resolved !== null) {
        $name = trim((string)($resolved['stop_name'] ?? ''));
        $sid = (int)($resolved['stop_id'] ?? 0);
        if ($sid > 0) {
            $base = $name !== '' && !preg_match('/^정류장ID:\d+$/u', $name) ? $name : '정류장';
            return $base . ' · ' . $sid;
        }
    }
    return route_finder_stop_display_name($pdo, $input);
}

/**
 * 경로별 정류장 요약 (출발·도착 사이 3~7개). 정류장명이 없으면 "정류장 · nnn" 표시.
 */
function route_finder_stops_summary(PDO $pdo, string $routeType, $routeId, int $fromSeq, int $toSeq): string
{
    try {
        if ($routeType === 'bus') {
            $st = $pdo->prepare("
                SELECT stop_id, stop_name FROM seoul_bus_route_stop_master
                WHERE route_id = :rid AND seq_in_route BETWEEN :from_seq AND :to_seq
                ORDER BY seq_in_route ASC
                LIMIT 7
            ");
            $st->execute([':rid' => $routeId, ':from_seq' => $fromSeq, ':to_seq' => $toSeq]);
        } else {
            $st = $pdo->prepare("
                SELECT stop_id, COALESCE(stop_name, raw_stop_name) AS stop_name
                FROM shuttle_temp_route_stop
                WHERE temp_route_id = :rid AND seq_in_route BETWEEN :from_seq AND :to_seq
                ORDER BY seq_in_route ASC
                LIMIT 7
            ");
            $st->execute([':rid' => $routeId, ':from_seq' => $fromSeq, ':to_seq' => $toSeq]);
        }
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $labels = [];
        foreach ($rows as $r) {
            $name = isset($r['stop_name']) ? trim((string)$r['stop_name']) : '';
            if ($name !== '' && !preg_match('/^정류장ID:\d+$/u', $name)) {
                $sid = isset($r['stop_id']) ? (int)$r['stop_id'] : 0;
                $labels[] = $sid > 0 ? $name . ' · ' . $sid : $name;
            } else {
                $sid = isset($r['stop_id']) ? (int)$r['stop_id'] : 0;
                $labels[] = $sid > 0 ? '정류장 · ' . $sid : ($name !== '' ? $name : '정류장');
            }
        }
        return implode(' - ', $labels);
    } catch (Throwable $e) {
        return '';
    }
}
