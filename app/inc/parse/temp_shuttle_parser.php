<?php
/**
 * 임시 셔틀버스 PDF 파서 (v1.7-21)
 *
 * 1 PDF → N 노선. 노선별 정류장, 출발/막차, 배차간격 추출.
 * 설계: docs/operations/SHUTTLE_TEMP_DESIGN_v1_7.md
 *
 * @requires pdf_parser.php (run_ocr_extract, PdfParser)
 */

declare(strict_types=1);

require_once __DIR__ . '/pdf_parser.php';

const TEMP_SHUTTLE_PARSER_VERSION = 'v1.7-21';

/**
 * 임시 셔틀버스 PDF 파싱 (1 PDF → N 노선)
 *
 * @param string $filePath PDF 파일 경로
 * @return array [
 *   'success' => bool,
 *   'error' => string|null,
 *   'error_code' => string|null,
 *   'parser_version' => string,
 *   'parsed_at_ms' => int,
 *   'district_name' => string|null,
 *   'first_bus_time' => string|null,
 *   'last_bus_time' => string|null,
 *   'routes' => [
 *     ['route_label' => string, 'stops' => [...], 'distance_km' => float|null, 'bus_count' => int|null, 'run_count' => int|null, 'headway_min' => string|null],
 *     ...
 *   ]
 * ]
 */
function parse_temp_shuttle_pdf(string $filePath): array
{
    $startedAt = microtime(true);
    $result = [
        'success' => false,
        'error' => null,
        'error_code' => null,
        'parser_version' => TEMP_SHUTTLE_PARSER_VERSION,
        'parsed_at_ms' => 0,
        'district_name' => null,
        'first_bus_time' => null,
        'last_bus_time' => null,
        'routes' => [],
    ];

    $text = extract_pdf_text($filePath);
    if ($text === null || trim($text) === '') {
        $result['error'] = 'PDF 텍스트 추출 실패';
        $result['error_code'] = 'NO_TEXT';
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }

    // 텍스트 정규화 (공백 축소, 문자 간격 제거)
    $text = normalize_temp_shuttle_text($text);

    $district = extract_district_name($text);
    $result['district_name'] = $district;

    $times = extract_operating_time($text);
    $result['first_bus_time'] = $times['first'] ?? null;
    $result['last_bus_time'] = $times['last'] ?? null;

    $routes = extract_temp_shuttle_routes($text, $district);
    if (empty($routes)) {
        $result['error'] = '노선을 추출할 수 없습니다.';
        $result['error_code'] = 'ROUTES_NOT_FOUND';
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }

    $result['routes'] = $routes;
    $result['success'] = true;
    $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
    return $result;
}

/**
 * PDF 텍스트 추출 (pdf_parser 또는 OCR)
 */
function extract_pdf_text(string $filePath): ?string
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return null;
    }
    $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return null;
    }

    if (class_exists(\Smalot\PdfParser\Parser::class)) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            if ($text !== null && trim($text) !== '') {
                return $text;
            }
        } catch (Throwable $e) {
            // fall through to OCR
        }
    }

    return run_ocr_extract($filePath);
}

/**
 * 공백 정규화 (예: "노 량 진 역" → "노량진역")
 */
function normalize_temp_shuttle_text(string $text): string
{
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim($text);
    return $text;
}

/**
 * 자치구명 추출
 */
function extract_district_name(string $text): ?string
{
    $districts = ['동작구', '서초구', '관악구', '금천구', '영등포구', '강남구', '송파구', '마포구'];
    foreach ($districts as $d) {
        if (strpos($text, $d) !== false) {
            return $d;
        }
    }
    return null;
}

/**
 * 운행시간 추출 (첫차, 막차)
 */
function extract_operating_time(string $text): array
{
    $out = ['first' => null, 'last' => null];
    // 06:00~22:00, 06시~22시, 05:00～22:00
    if (preg_match('/(\d{1,2}):(\d{2})\s*[~∼～]\s*(\d{1,2}):(\d{2})/u', $text, $m)) {
        $out['first'] = sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
        $out['last'] = sprintf('%02d:%02d', (int)$m[3], (int)$m[4]);
    } elseif (preg_match('/(\d{1,2})시\s*[~∼～]\s*(\d{1,2})시/u', $text, $m)) {
        $out['first'] = sprintf('%02d:00', (int)$m[1]);
        $out['last'] = sprintf('%02d:00', (int)$m[2]);
    }
    return $out;
}

/**
 * 노선 블록 분리 및 정류장·메타 추출
 */
function extract_temp_shuttle_routes(string $text, ?string $district): array
{
    $district = $district ?? '';

    $routeLabels = extract_route_labels_from_text($text, $district);

    $routes = [];

    // 패턴 A: 동작구 등 - "stops 7.0km 8대 48회 20분" (공백 유연: 7.0km8대 가능)
    $pattern = '/(\d+\.?\d*)\s*km\s*(\d+)\s*대\s*(\d+)\s*회\s*(\d+~?\d*)\s*분/u';
    if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
        $prevEnd = 0;
        foreach ($matches[0] as $idx => $match) {
            $metaStart = $match[1];
            $stopContent = substr($text, $prevEnd, $metaStart - $prevEnd);
            $prevEnd = $metaStart + strlen($match[0]);

            $stops = parse_stop_sequence($stopContent);
            if (count($stops) >= 2) {
                $baseLabel = $routeLabels[$idx] ?? $district . ($idx + 1);
                $routeLabel = (strpos($baseLabel, '(임시)') !== false || strpos($baseLabel, '임시') !== false)
                    ? $baseLabel : $baseLabel . '(임시)';
                $routes[] = [
                    'route_label' => $routeLabel,
                    'stops' => $stops,
                    'distance_km' => (float)$matches[1][$idx][0],
                    'bus_count' => (int)$matches[2][$idx][0],
                    'run_count' => (int)$matches[3][$idx][0],
                    'headway_min' => trim($matches[4][$idx][0]),
                ];
            }
        }
    }

    if (!empty($routes)) {
        return $routes;
    }

    // 패턴 B: 관악/서초 - 블록별 (연번 1, 2, 3... 또는 〔N호차〕)
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $currentBlock = '';
    $currentMeta = [];
    $blockStarted = false;

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '') continue;

        $isBlockStart = (bool)preg_match('/^(\d+)\s*$/u', $trimmed) || (bool)preg_match('/〔(\d+)호차〕/u', $trimmed);
        $isMetaLine = (bool)preg_match('/^(\d+\.?\d*)\s*km\s*$/u', $trimmed)
            || (bool)preg_match('/^(\d+)\s*대\s*$/u', $trimmed)
            || (bool)preg_match('/^(\d+)\s*회\s*$/u', $trimmed)
            || (bool)preg_match('/^(\d+~?\d*)\s*분\s*$/u', $trimmed)
            || (bool)preg_match('/^(\d+\.?\d*)\s*km\s+(\d+)\s*대\s+(\d+)\s*회/u', $trimmed);

        if ($isBlockStart && $blockStarted && $currentBlock !== '') {
            $stops = parse_stop_sequence($currentBlock);
            if (!empty($stops)) {
                $routeLabel = $routeLabels[count($routes)] ?? $district . (count($routes) + 1) . '(임시)';
                $routes[] = [
                    'route_label' => $routeLabel,
                    'stops' => $stops,
                    'distance_km' => $currentMeta['distance_km'] ?? null,
                    'bus_count' => $currentMeta['bus_count'] ?? null,
                    'run_count' => $currentMeta['run_count'] ?? null,
                    'headway_min' => $currentMeta['headway_min'] ?? null,
                ];
            }
            $currentBlock = '';
            $currentMeta = [];
        }

        if ($isBlockStart) {
            $blockStarted = true;
            continue;
        }

        if ($isMetaLine) {
            $meta = parse_route_metadata($trimmed);
            if ($meta['distance_km'] !== null) $currentMeta['distance_km'] = $meta['distance_km'];
            if ($meta['bus_count'] !== null) $currentMeta['bus_count'] = $meta['bus_count'];
            if ($meta['run_count'] !== null) $currentMeta['run_count'] = $meta['run_count'];
            if ($meta['headway_min'] !== null) $currentMeta['headway_min'] = $meta['headway_min'];
        } elseif ($blockStarted
            && !preg_match('/^(연번|임시|무료셔틀|운행구간|운행거리|배정대수|운행횟수|배차간격)/u', $trimmed)
            && !preg_match('/노선도\s*$/u', $trimmed)
            && !preg_match('/^--\s*\d+\s*of\s+\d+\s*--/u', $trimmed)
            && !preg_match('/^○\s*투입/u', $trimmed)
        ) {
            $currentBlock .= ' ' . $trimmed;
        }
    }

    if ($currentBlock !== '') {
        $stops = parse_stop_sequence($currentBlock);
        if (!empty($stops)) {
            $routeLabel = $routeLabels[count($routes)] ?? $district . (count($routes) + 1) . '(임시)';
            $routes[] = [
                'route_label' => $routeLabel,
                'stops' => $stops,
                'distance_km' => $currentMeta['distance_km'] ?? null,
                'bus_count' => $currentMeta['bus_count'] ?? null,
                'run_count' => $currentMeta['run_count'] ?? null,
                'headway_min' => $currentMeta['headway_min'] ?? null,
            ];
        }
    }

    return $routes;
}

/**
 * 텍스트에서 노선 라벨 추출 (동작1(임시), 관악 임시1번 등)
 */
function extract_route_labels_from_text(string $text, string $district): array
{
    $labels = [];
    if (preg_match_all('/([가-힣]+?\d+)\s*\(임시\)/u', $text, $m)) {
        $labels = array_values(array_unique($m[1]));
    }
    if (empty($labels) && preg_match_all('/([가-힣]+)\s*임시\s*(\d+)번/u', $text, $m)) {
        $labels = array_values(array_unique($m[0]));
    }
    if (empty($labels) && preg_match_all('/임시\s*(\d+)번/u', $text, $m)) {
        $labels = array_map(fn($n) => "임시{$n}번", array_unique($m[1]));
    }
    return $labels;
}

/**
 * 정류장 시퀀스 파싱 (→, ↔, ~, -, – 구분)
 */
function parse_stop_sequence(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') return [];

    $raw = preg_replace('/^.*?(운행거리배정대수운행횟수배차간격|운행구간|배차간격|노선도)\s*/us', '', $raw);
    $raw = preg_replace('/(운행거리배정대수운행횟수배차간격)\s*/u', ' ', $raw);
    $raw = preg_replace('/(자치구|운행대수|운행시간|운행노선|및\s*횟수|연번|임시|무료셔틀버스)\s*/u', ' ', $raw);
    $raw = trim($raw);

    $parts = preg_split('/\s*[→↔~－\-–—,]\s*/u', $raw);
    $stops = [];
    $seq = 0;

    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '') continue;
        if (preg_match('/^\d+(\.\d+)?\s*km/u', $p)) continue;
        if (preg_match('/^\d+대/u', $p)) continue;
        if (preg_match('/^\d+회/u', $p)) continue;
        if (preg_match('/^\d+~?\d*분/u', $p)) continue;
        if (preg_match('/\(기점\)|\(종점\)|\(시점\)|\(회차\)|유턴/u', $p)) {
            $p = preg_replace('/\s*\((?:기점|종점|시점|회차)\)\s*/u', '', $p);
            $p = preg_replace('/\s*유턴\s*/u', '', $p);
            $p = trim($p);
        }

        $nameAndId = extract_stop_name_and_id($p);
        if ($nameAndId['name'] !== '') {
            $seq++;
            $stops[] = [
                'seq' => $seq,
                'raw_stop_name' => $nameAndId['name'],
                'stop_id' => $nameAndId['stop_id'],
            ];
        }
    }

    return $stops;
}

/**
 * 정류장명·stop_id 추출 (괄호 안 5자리 이상 숫자)
 */
function extract_stop_name_and_id(string $raw): array
{
    $raw = trim($raw);
    $stopId = null;

    if (preg_match('/^(.+?)\s*\((\d{5,})(?:,\s*[^)]*)?\)/u', $raw, $m)) {
        $name = trim($m[1]);
        $stopId = (int)$m[2];
        $name = preg_replace('/\s*\(\d{5,}(?:,[^)]*)?\)\s*$/u', '', $name);
        $name = trim($name);
    } elseif (preg_match('/^(.+?)\s*\((\d{5,})\)\s*$/u', $raw, $m)) {
        $name = trim($m[1]);
        $stopId = (int)$m[2];
    } else {
        $name = preg_replace('/\s*\((?:기점|종점|시점|회차)\)\s*/u', '', $raw);
        $name = trim($name);
    }

    $name = preg_replace('/\s+/u', ' ', $name);
    $name = trim($name);

    return [
        'name' => $name,
        'stop_id' => $stopId,
    ];
}

/**
 * 노선 메타 추출 (거리, 대수, 횟수, 배차간격)
 */
function parse_route_metadata(string $line): array
{
    $out = [
        'distance_km' => null,
        'bus_count' => null,
        'run_count' => null,
        'headway_min' => null,
    ];

    if (preg_match('/(\d+\.?\d*)\s*km/u', $line, $m)) {
        $out['distance_km'] = (float)$m[1];
    }
    if (preg_match('/(\d+)\s*대/u', $line, $m)) {
        $out['bus_count'] = (int)$m[1];
    }
    if (preg_match('/(\d+)\s*회/u', $line, $m)) {
        $out['run_count'] = (int)$m[1];
    }
    if (preg_match('/(\d+~?\d*)\s*분/u', $line, $m)) {
        $out['headway_min'] = trim($m[1]);
    } elseif (preg_match('/(\d+)\s*~\s*(\d+)\s*분/u', $line, $m)) {
        $out['headway_min'] = $m[1] . '~' . $m[2];
    }

    return $out;
}

function is_route_metadata_line(string $line): bool
{
    return (bool)preg_match('/(\d+\.?\d*)\s*km|(\d+)\s*대|(\d+)\s*회|(\d+~?\d*)\s*분/u', $line);
}

/**
 * 정류장명 → stop_id 매칭 (seoul_bus_stop_master)
 * 순서: id_extract → exact → normalized → alias → like_prefix
 */
function match_temp_stop_to_master(PDO $pdo, string $rawStopName, ?int $extractedStopId): ?array
{
    $raw = trim($rawStopName);
    if ($raw === '') return null;

    $normalized = trim(preg_replace('/\s+/', ' ', $raw));

    if ($extractedStopId !== null) {
        $checkStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_id = :id LIMIT 1");
        $checkStmt->execute([':id' => $extractedStopId]);
        $row = $checkStmt->fetch();
        if ($row) {
            return [
                'stop_id' => (string)$row['stop_id'],
                'stop_name' => (string)$row['stop_name'],
                'match_method' => 'id_extract',
            ];
        }
    }

    $exactStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name = :name LIMIT 1");
    $likeStmt = $pdo->prepare("SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_name LIKE CONCAT(:prefix, '%') LIMIT 1");

    $exactStmt->execute([':name' => $raw]);
    $row = $exactStmt->fetch();
    if ($row) {
        return [
            'stop_id' => (string)$row['stop_id'],
            'stop_name' => (string)$row['stop_name'],
            'match_method' => 'exact',
        ];
    }

    if ($normalized !== $raw) {
        $exactStmt->execute([':name' => $normalized]);
        $row = $exactStmt->fetch();
        if ($row) {
            return [
                'stop_id' => (string)$row['stop_id'],
                'stop_name' => (string)$row['stop_name'],
                'match_method' => 'normalized',
            ];
        }
    }

    if (class_exists('PDO') && phpversion('pdo') !== false) {
        try {
            $aliasStmt = $pdo->prepare("SELECT canonical_text FROM shuttle_stop_alias WHERE alias_text = :alias AND is_active = 1 LIMIT 1");
            $aliasStmt->execute([':alias' => $normalized]);
            $aliasRow = $aliasStmt->fetch();
            if ($aliasRow) {
                $canonical = trim((string)($aliasRow['canonical_text'] ?? ''));
                if ($canonical !== '') {
                    $exactStmt->execute([':name' => $canonical]);
                    $row = $exactStmt->fetch();
                    if ($row) {
                        return [
                            'stop_id' => (string)$row['stop_id'],
                            'stop_name' => (string)$row['stop_name'],
                            'match_method' => 'alias_exact',
                        ];
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    if (mb_strlen($normalized) > 2) {
        $likeStmt->execute([':prefix' => $raw]);
        $row = $likeStmt->fetch();
        if ($row) {
            return [
                'stop_id' => (string)$row['stop_id'],
                'stop_name' => (string)$row['stop_name'],
                'match_method' => 'like_prefix',
            ];
        }
    }

    return null;
}

/**
 * 파싱 결과를 DB에 저장 (shuttle_temp_route, shuttle_temp_route_stop)
 */
function save_temp_shuttle_to_db(PDO $pdo, array $parseResult, ?int $sourceDocId = null): array
{
    $saved = ['routes' => 0, 'stops' => 0, 'errors' => []];

    if (!$parseResult['success'] || empty($parseResult['routes'])) {
        return $saved;
    }

    $district = $parseResult['district_name'] ?? null;
    $firstBus = $parseResult['first_bus_time'] ?? null;
    $lastBus = $parseResult['last_bus_time'] ?? null;

    $insRouteStmt = $pdo->prepare("
        INSERT INTO shuttle_temp_route (route_label, district_name, source_doc_id, first_bus_time, last_bus_time, headway_min, distance_km, bus_count, run_count, raw_json)
        VALUES (:rl, :dn, :doc, :fb, :lb, :hw, :dist, :bc, :rc, :json)
        ON DUPLICATE KEY UPDATE
          district_name = VALUES(district_name),
          source_doc_id = VALUES(source_doc_id),
          first_bus_time = VALUES(first_bus_time),
          last_bus_time = VALUES(last_bus_time),
          headway_min = VALUES(headway_min),
          distance_km = VALUES(distance_km),
          bus_count = VALUES(bus_count),
          run_count = VALUES(run_count),
          raw_json = VALUES(raw_json),
          updated_at = CURRENT_TIMESTAMP
    ");

    $delStopStmt = $pdo->prepare("DELETE FROM shuttle_temp_route_stop WHERE temp_route_id = :tid");
    $insStopStmt = $pdo->prepare("
        INSERT INTO shuttle_temp_route_stop (temp_route_id, seq_in_route, raw_stop_name, stop_id, stop_name, match_method)
        VALUES (:tid, :seq, :raw, :sid, :sname, :method)
    ");

    $getRouteIdStmt = $pdo->prepare("SELECT id FROM shuttle_temp_route WHERE route_label = :rl LIMIT 1");

    foreach ($parseResult['routes'] as $route) {
        $routeLabel = $route['route_label'] ?? '';
        if ($routeLabel === '') continue;

        $headway = $route['headway_min'] ?? null;
        if (is_numeric($headway)) {
            $headway = (string)$headway . '분';
        }

        try {
            $rawJson = json_encode($route, JSON_UNESCAPED_UNICODE);
            $insRouteStmt->execute([
                ':rl' => $routeLabel,
                ':dn' => $district,
                ':doc' => $sourceDocId,
                ':fb' => $firstBus,
                ':lb' => $lastBus,
                ':hw' => $headway,
                ':dist' => $route['distance_km'] ?? null,
                ':bc' => $route['bus_count'] ?? null,
                ':rc' => $route['run_count'] ?? null,
                ':json' => $rawJson,
            ]);
            $saved['routes']++;

            $getRouteIdStmt->execute([':rl' => $routeLabel]);
            $row = $getRouteIdStmt->fetch();
            $tempRouteId = $row ? (int)$row['id'] : (int)$pdo->lastInsertId();
            if ($tempRouteId <= 0) {
                $getRouteIdStmt->execute([':rl' => $routeLabel]);
                $row = $getRouteIdStmt->fetch();
                $tempRouteId = (int)($row['id'] ?? 0);
            }

            if ($tempRouteId > 0) {
                $delStopStmt->execute([':tid' => $tempRouteId]);

                foreach ($route['stops'] ?? [] as $stop) {
                    $rawName = $stop['raw_stop_name'] ?? '';
                    $extractedId = isset($stop['stop_id']) ? (int)$stop['stop_id'] : null;
                    $match = match_temp_stop_to_master($pdo, $rawName, $extractedId > 0 ? $extractedId : null);

                    $stopId = null;
                    $stopName = null;
                    $matchMethod = null;
                    if ($match) {
                        $stopId = (int)$match['stop_id'];
                        $stopName = $match['stop_name'] ?? $rawName;
                        $matchMethod = $match['match_method'] ?? 'manual';
                    }

                    $insStopStmt->execute([
                        ':tid' => $tempRouteId,
                        ':seq' => $stop['seq'] ?? 0,
                        ':raw' => $rawName,
                        ':sid' => $stopId ?: null,
                        ':sname' => $stopName,
                        ':method' => $matchMethod,
                    ]);
                    $saved['stops']++;
                }
            }
        } catch (Throwable $e) {
            $saved['errors'][] = $routeLabel . ': ' . $e->getMessage();
        }
    }

    return $saved;
}

// CLI 테스트
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    if ($argc < 2) {
        echo "Usage: php temp_shuttle_parser.php <path_to_pdf> [--save]\n";
        echo "  --save : Save to DB (requires db connection)\n";
        exit(1);
    }

    $pdfPath = $argv[1];
    $doSave = in_array('--save', array_slice($argv, 2), true);

    echo "Parsing: {$pdfPath}\n";
    echo str_repeat('-', 60) . "\n";

    $result = parse_temp_shuttle_pdf($pdfPath);

    if ($result['success']) {
        echo "✓ Success! ({$result['parsed_at_ms']}ms)\n";
        echo "District: " . ($result['district_name'] ?? '-') . "\n";
        echo "Time: " . ($result['first_bus_time'] ?? '-') . " ~ " . ($result['last_bus_time'] ?? '-') . "\n";
        echo "Routes: " . count($result['routes']) . "\n\n";

        foreach ($result['routes'] as $r) {
            echo "  [{$r['route_label']}] " . count($r['stops']) . " stops";
            if ($r['distance_km'] !== null) echo ", {$r['distance_km']}km";
            if ($r['headway_min'] !== null) echo ", {$r['headway_min']}";
            echo "\n";
            foreach (array_slice($r['stops'], 0, 5) as $s) {
                $id = $s['stop_id'] ?? '';
                echo "    {$s['seq']}. {$s['raw_stop_name']}" . ($id ? " (id:{$id})" : '') . "\n";
            }
            if (count($r['stops']) > 5) {
                echo "    ... +" . (count($r['stops']) - 5) . " more\n";
            }
            echo "\n";
        }

        if ($doSave) {
            require_once __DIR__ . '/../auth/db.php';
            $pdo = pdo();
            $saved = save_temp_shuttle_to_db($pdo, $result);
            echo "DB Saved: {$saved['routes']} routes, {$saved['stops']} stops\n";
            if (!empty($saved['errors'])) {
                echo "Errors: " . implode('; ', $saved['errors']) . "\n";
            }
        }
    } else {
        echo "✗ Error: {$result['error']}\n";
        echo "Code: " . ($result['error_code'] ?? '-') . "\n";
    }
}
