<?php
/**
 * 임시 셔틀 파서 테스트
 * 실행: php scripts/test_temp_shuttle_parser.php
 */
declare(strict_types=1);

$projectRoot = dirname(__DIR__, 2);
chdir($projectRoot);

require_once $projectRoot . '/app/inc/parse/temp_shuttle_parser.php';

$pdfDir = $projectRoot . '/data/inbound/source_docs/shuttle_pdf_zip/서울버스일부파업_01차';
$files = glob($pdfDir . '/*.pdf');

if (empty($files)) {
    echo "No PDF files in: {$pdfDir}\n";
    exit(1);
}

$testFiles = [
    $pdfDir . '/696438316b8489.10782102.pdf',  // 동작구
    $pdfDir . '/6964384eca7de4.40966136.pdf',  // 서초구
    $pdfDir . '/6964383d983985.95217707.pdf',  // 관악구
];
$testFile = null;
foreach ($testFiles as $f) {
    if (file_exists($f)) {
        $testFile = $f;
        break;
    }
}
if (!$testFile) {
    $testFile = $files[0] ?? null;
}
if (!$testFile) {
    echo "No PDF found.\n";
    exit(1);
}
echo "Testing: " . basename($testFile) . "\n";
echo str_repeat('-', 60) . "\n";

$result = parse_temp_shuttle_pdf($testFile);

if (in_array('--debug', $argv ?? [], true)) {
    $text = null;
    if (function_exists('extract_pdf_text')) {
        $text = extract_pdf_text($testFile);
    }
    echo "Text length: " . ($text ? strlen($text) : 0) . "\n";
    if ($text) {
        echo "First 500 chars:\n" . substr($text, 0, 500) . "\n...\n";
        echo "Contains '7.0km': " . (strpos($text, '7.0km') !== false ? 'yes' : 'no') . "\n";
        echo "Contains 'km': " . (strpos($text, 'km') !== false ? 'yes' : 'no') . "\n";
        echo "Contains '대': " . (strpos($text, '대') !== false ? 'yes' : 'no') . "\n";
    }
    echo "\n";
}

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
        if (count($r['stops']) > 5) echo "    ... +" . (count($r['stops']) - 5) . " more\n";
        echo "\n";
    }

    if (in_array('--save', $argv ?? [], true)) {
        require_once $projectRoot . '/app/inc/auth/db.php';
        $pdo = pdo();
        $saved = save_temp_shuttle_to_db($pdo, $result);
        echo "DB Saved: {$saved['routes']} routes, {$saved['stops']} stops\n";
        if (!empty($saved['errors'])) {
            echo "Errors: " . implode('; ', array_slice($saved['errors'], 0, 5)) . "\n";
        }
    }
} else {
    echo "✗ Error: {$result['error']}\n";
    echo "Code: " . ($result['error_code'] ?? '-') . "\n";
}
