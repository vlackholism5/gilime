<?php
declare(strict_types=1);
/**
 * 경로가 나오는 출발/도착 정류장 쌍 조회 (길찾기 결과 예시용)
 * seoul_bus_route_stop_master에서 같은 노선으로 연결된 (from, to) 쌍을 뽑음.
 *
 * 실행: php scripts/php/list_route_finder_pairs_with_routes.php
 */
require_once __DIR__ . '/../../app/inc/auth/db.php';
require_once __DIR__ . '/../../app/inc/route/route_finder.php';

$pdo = pdo();

$sql = "
    SELECT DISTINCT
           f.stop_name AS from_name,
           t.stop_name AS to_name,
           COUNT(DISTINCT f.route_id) AS route_count
    FROM seoul_bus_route_stop_master f
    INNER JOIN seoul_bus_route_stop_master t
      ON f.route_id = t.route_id AND f.seq_in_route < t.seq_in_route
    WHERE f.stop_id IS NOT NULL AND t.stop_id IS NOT NULL
    GROUP BY f.stop_id, t.stop_id, f.stop_name, t.stop_name
    HAVING route_count >= 1
    ORDER BY route_count DESC, from_name, to_name
    LIMIT 20
";

$pairs = [];
try {
    $st = $pdo->query($sql);
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $pairs[] = [
            'from' => (string)$row['from_name'],
            'to'   => (string)$row['to_name'],
            'route_count' => (int)$row['route_count'],
        ];
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}

$baseUrl = 'http://localhost/gilime_mvp_01/public/user/route_finder.php';
echo "=== 경로가 나오는 출발/도착 예시 (버스 노선 연결) ===\n";
echo "※ 아래 URL로 접속하면 경로 카드와 구독 안내 문구를 확인할 수 있습니다.\n\n";

if ($pairs === []) {
    echo "(목록 없음 - seoul_bus_route_stop_master 데이터 확인 필요)\n";
    exit(0);
}

foreach (array_slice($pairs, 0, 10) as $i => $p) {
    $fromEnc = rawurlencode($p['from']);
    $toEnc   = rawurlencode($p['to']);
    $url = "{$baseUrl}?step=result&from={$fromEnc}&to={$toEnc}";
    echo ($i + 1) . ". 출발: {$p['from']} → 도착: {$p['to']} (노선 {$p['route_count']}개)\n";
    echo "   URL: $url\n\n";
}

echo "총 " . count($pairs) . "쌍 (상위 10개만 출력)\n";
