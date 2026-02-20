<?php
declare(strict_types=1);
/**
 * 정류장 ID 쌍 → 정류장명 조회 후 길찾기 URL 출력
 * 사용: php scripts/php/route_finder_urls_from_stop_ids.php
 *
 * 아래 쌍(경로 있음): from_stop_id, to_stop_id 또는 to_name
 */
require_once __DIR__ . '/../../app/inc/auth/db.php';

$pdo = pdo();
$baseUrl = 'http://localhost/gilime_mvp_01/public/user/route_finder.php';

// 쌍: [from_stop_id, to_stop_id] 또는 [from_stop_id, to_name 문자열]
$pairs = [
    [232001137, 232000291],
    [232001137, 232000854],
    [232001137, 232000856],
    [232000857, '개화역광역환승센터'],
];

$stopIds = [232001137, 232000291, 232000854, 232000856, 232000857];
$idToName = [];
if (!empty($stopIds)) {
    $placeholders = implode(',', array_fill(0, count($stopIds), '?'));
    $st = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_id IN ($placeholders)");
    $st->execute(array_values($stopIds));
    while ($row = $st->fetch(PDO::FETCH_ASSOC)) {
        $idToName[(int)$row['stop_id']] = (string)$row['stop_name'];
    }
}

echo "=== 정류장 ID → 이름 ===\n";
foreach ($idToName as $id => $name) {
    echo "  $id => $name\n";
}

echo "\n=== 경로 나오는 URL 예시 ===\n";
foreach ($pairs as $i => $pair) {
    $fromId = $pair[0];
    $to = $pair[1];
    $fromName = $idToName[$fromId] ?? null;
    $toName = is_int($to) ? ($idToName[$to] ?? null) : $to;
    if ($fromName === null || $toName === null) {
        $toVal = is_int($to) ? (string)$to : $to;
        echo ($i + 1) . ". (이름 없음) from_id=$fromId to=$toVal\n";
        continue;
    }
    $url = $baseUrl . '?step=result&from=' . rawurlencode($fromName) . '&to=' . rawurlencode($toName);
    echo ($i + 1) . ". 출발: {$fromName} → 도착: {$toName}\n";
    echo "   $url\n\n";
}
