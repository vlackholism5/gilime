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
 * @return array [['stop_id' => int, 'stop_name' => string], ...]
 */
function route_finder_suggest_stops(PDO $pdo, string $input, int $limit = 10): array
{
    $raw = trim($input);
    if ($raw === '' || mb_strlen($raw) < 1) return [];

    $normalized = trim(preg_replace('/\s+/u', ' ', $raw));
    $prefix = $raw;
    $contains = '%' . $raw . '%';

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
 * 경로별 정류장 요약 (출발·도착 사이 3~7개)
 */
function route_finder_stops_summary(PDO $pdo, string $routeType, $routeId, int $fromSeq, int $toSeq): string
{
    try {
        if ($routeType === 'bus') {
            $st = $pdo->prepare("
                SELECT stop_name FROM seoul_bus_route_stop_master
                WHERE route_id = :rid AND seq_in_route BETWEEN :from_seq AND :to_seq
                ORDER BY seq_in_route ASC
                LIMIT 7
            ");
            $st->execute([':rid' => $routeId, ':from_seq' => $fromSeq, ':to_seq' => $toSeq]);
        } else {
            $st = $pdo->prepare("
                SELECT COALESCE(stop_name, raw_stop_name) AS stop_name
                FROM shuttle_temp_route_stop
                WHERE temp_route_id = :rid AND seq_in_route BETWEEN :from_seq AND :to_seq
                ORDER BY seq_in_route ASC
                LIMIT 7
            ");
            $st->execute([':rid' => $routeId, ':from_seq' => $fromSeq, ':to_seq' => $toSeq]);
        }
        $names = array_column($st->fetchAll(PDO::FETCH_ASSOC), 'stop_name');
        return implode(' - ', array_map('trim', $names));
    } catch (Throwable $e) {
        return '';
    }
}
