<?php
/**
 * PDF Parser for Shuttle Route Documents
 * 
 * PDF에서 노선 정보와 정류장 목록을 추출합니다.
 * 
 * @requires smalot/pdfparser (composer require smalot/pdfparser)
 */

$autoloadPath = __DIR__ . '/../../../vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use Smalot\PdfParser\Parser as PdfParser;

const PDF_PARSER_VERSION = 'v1.0.0-ocr';
/** v1.7-14: 운영형 에러코드 (PARSE_ prefix로 로그/DB 추적) */
const PDF_PARSE_ERR_DEP_MISSING = 'PARSE_DEP_MISSING';
const PDF_PARSE_ERR_FILE_NOT_FOUND = 'PARSE_FILE_NOT_FOUND';
const PDF_PARSE_ERR_FILE_READ_FAILED = 'PARSE_FILE_READ_FAILED';
const PDF_PARSE_ERR_INVALID_FILE_TYPE = 'PARSE_INVALID_FILE_TYPE';
const PDF_PARSE_ERR_FILE_TOO_LARGE = 'PARSE_FILE_TOO_LARGE';
const PDF_PARSE_ERR_NO_TEXT = 'PARSE_NO_TEXT';
const PDF_PARSE_ERR_ROUTE_NOT_FOUND = 'PARSE_NO_ROUTE';
const PDF_PARSE_ERR_STOPS_NOT_FOUND = 'PARSE_NO_STOPS';
const PDF_PARSE_ERR_PARSE_EXCEPTION = 'PARSE_EXCEPTION';
const PDF_PARSE_ERR_OCR_FAILED = 'PARSE_OCR_FAILED';
const PDF_PARSE_ERR_PATH_TRAVERSAL = 'PARSE_PATH_TRAVERSAL';

/** v1.7-14: 최대 파일 크기 (10MB) */
const PDF_MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

/** v1.7-14: route_label 정규화 (trim + 연속 공백 단일화 + 제어문자 제거, 최대 255자) */
function normalize_route_label(string $raw): string {
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return mb_strlen($s) > 255 ? mb_substr($s, 0, 255) : $s;
}

/** v1.7-14: raw_stop_name 정규화 (trim + 연속 공백 단일화 + 제어문자 제거, 최대 255자) */
function normalize_raw_stop_name(string $raw): string {
    $s = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);
    $s = trim(preg_replace('/\s+/u', ' ', $s));
    return mb_strlen($s) > 255 ? mb_substr($s, 0, 255) : $s;
}

/** v1.7-20: Python OCR 경로 (config.local.php에서 정의 가능) */
if (!defined('OCR_PYTHON_CMD')) define('OCR_PYTHON_CMD', 'python');
if (!defined('OCR_TESSERACT_CMD')) define('OCR_TESSERACT_CMD', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');

/**
 * v1.7-20: Python OCR로 PDF 텍스트 추출 (digital + Tesseract fallback)
 * @return string|null 추출된 텍스트, 실패 시 null
 */
function run_ocr_extract(string $pdfPath): ?string
{
    $pdfPath = realpath($pdfPath);
    if ($pdfPath === false || !is_file($pdfPath)) return null;

    $scriptDir = __DIR__ . '/../../scripts/python';
    $scriptPath = $scriptDir . '/extract_text.py';
    if (!file_exists($scriptPath)) return null;

    $outDir = sys_get_temp_dir() . '/gilime_ocr_' . getmypid();
    if (!is_dir($outDir)) {
        @mkdir($outDir, 0755, true);
    }
    $outFile = $outDir . '/' . pathinfo($pdfPath, PATHINFO_FILENAME) . '_' . uniqid() . '.txt';

    $tessArg = '';
    if (defined('OCR_TESSERACT_CMD') && OCR_TESSERACT_CMD !== '') {
        $tessArg = ' --tesseract-cmd "' . str_replace('"', '\\"', OCR_TESSERACT_CMD) . '"';
    }

    $pythonCmd = defined('OCR_PYTHON_CMD') ? OCR_PYTHON_CMD : 'python';
    if (strpos($pythonCmd, ' ') !== false || strpos($pythonCmd, '\\') !== false) {
        $pythonCmd = '"' . $pythonCmd . '"';
    }

    $cmd = sprintf(
        '%s "%s" --input-file "%s" --output "%s" --output-format text_only --lang kor+eng%s',
        $pythonCmd,
        $scriptPath,
        $pdfPath,
        $outFile,
        $tessArg
    );

    $output = [];
    $ret = 0;
    exec($cmd . ' 2>&1', $output, $ret);

    if ($ret !== 0 || !file_exists($outFile)) {
        @unlink($outFile);
        @rmdir($outDir);
        return null;
    }

    $text = file_get_contents($outFile);
    @unlink($outFile);
    @rmdir($outDir);

    return is_string($text) && trim($text) !== '' ? $text : null;
}

/**
 * PDF 파일에서 정류장 정보를 파싱
 * 
 * @param string $filePath PDF 파일 경로
 * @return array [
 *   'success' => bool,
 *   'error' => string|null,
 *   'error_code' => string|null,
 *   'warning_codes' => array<int,string>,
 *   'parser_version' => string,
 *   'parsed_at_ms' => int,
 *   'route_label' => string|null,
 *   'stops' => array [
 *     ['seq' => int, 'raw_stop_name' => string],
 *     ...
 *   ]
 * ]
 */
function parse_shuttle_pdf(string $filePath): array
{
    $startedAt = microtime(true);
    $result = [
        'success' => false,
        'error' => null,
        'error_code' => null,
        'warning_codes' => [],
        'parser_version' => PDF_PARSER_VERSION,
        'parsed_at_ms' => 0,
        'route_label' => null,
        'stops' => [],
    ];

    // 0) composer 의존성 확인
    if (!class_exists(PdfParser::class)) {
        $result['error'] = 'PDF parser dependency missing. Run "composer install" first.';
        $result['error_code'] = PDF_PARSE_ERR_DEP_MISSING;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }

    // 1) 경로/파일 검증
    if (strpos($filePath, '..') !== false) {
        $result['error'] = 'Path traversal not allowed';
        $result['error_code'] = PDF_PARSE_ERR_PATH_TRAVERSAL;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }
    $resolved = realpath($filePath);
    if ($resolved === false || !is_file($resolved)) {
        $result['error'] = "PDF file not found: {$filePath}";
        $result['error_code'] = PDF_PARSE_ERR_FILE_NOT_FOUND;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }
    $filePath = $resolved;

    if (!is_readable($filePath)) {
        $result['error'] = "PDF file is not readable: {$filePath}";
        $result['error_code'] = PDF_PARSE_ERR_FILE_READ_FAILED;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }

    $ext = strtolower((string)pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        $result['error'] = 'Invalid file type. Only .pdf is allowed.';
        $result['error_code'] = PDF_PARSE_ERR_INVALID_FILE_TYPE;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }

    $size = filesize($filePath);
    if ($size !== false && $size > PDF_MAX_FILE_SIZE_BYTES) {
        $result['error'] = 'PDF file is too large. Max size is 10MB.';
        $result['error_code'] = PDF_PARSE_ERR_FILE_TOO_LARGE;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }

    try {
        // 2) PDF 파서 초기화
        $parser = new PdfParser();
        $pdf = $parser->parseFile($filePath);
        
        // 3) 전체 텍스트 추출
        $text = $pdf->getText();

        // v1.7-20: 텍스트 없으면 Python OCR 시도
        if (empty($text)) {
            $ocrText = run_ocr_extract($filePath);
            if ($ocrText !== null && trim($ocrText) !== '') {
                $text = $ocrText;
                $result['warning_codes'][] = 'ocr_used';
            } else {
                $result['error'] = 'PDF contains no extractable text. OCR failed or not configured.';
                $result['error_code'] = PDF_PARSE_ERR_OCR_FAILED;
                $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
                return $result;
            }
        }

        // 4) 노선 라벨 추출 (예: "R1", "R2", "노선1" 등)
        $routeLabel = extract_route_label($text);
        if (!$routeLabel) {
            $result['error'] = 'Could not detect route label from PDF';
            $result['error_code'] = PDF_PARSE_ERR_ROUTE_NOT_FOUND;
            $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
            return $result;
        }
        $normRoute = normalize_route_label($routeLabel);
        if ($normRoute === '') {
            $result['error'] = 'Route label is empty after normalization';
            $result['error_code'] = PDF_PARSE_ERR_ROUTE_NOT_FOUND;
            $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
            return $result;
        }
        $result['route_label'] = $normRoute;

        // 5) 정류장 목록 추출
        $stops = extract_stops_from_text($text);
        if (empty($stops)) {
            $result['error'] = 'No stops found in PDF';
            $result['error_code'] = PDF_PARSE_ERR_STOPS_NOT_FOUND;
            $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
            return $result;
        }
        // v1.7-14: raw_stop_name 정규화 + 빈 정류장 필터
        $filtered = [];
        $seq = 0;
        foreach ($stops as $s) {
            $norm = normalize_raw_stop_name($s['raw_stop_name']);
            if ($norm !== '') {
                $seq++;
                $filtered[] = ['seq' => $seq, 'raw_stop_name' => $norm];
            }
        }
        if (empty($filtered)) {
            $result['error'] = 'No stops found in PDF (all filtered after normalization)';
            $result['error_code'] = PDF_PARSE_ERR_STOPS_NOT_FOUND;
            $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
            return $result;
        }
        $result['stops'] = $filtered;

        // 6) 성공
        $result['success'] = true;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;

    } catch (Exception $e) {
        $result['error'] = 'PDF parsing error: ' . $e->getMessage();
        $result['error_code'] = PDF_PARSE_ERR_PARSE_EXCEPTION;
        $result['parsed_at_ms'] = (int)round((microtime(true) - $startedAt) * 1000);
        return $result;
    }
}

/**
 * PDF 텍스트에서 노선 라벨 추출
 * 
 * 패턴 예시:
 * - "노선: R1"
 * - "Route: R1"
 * - "R1 노선"
 * - "셔틀버스 R1"
 * 
 * @param string $text PDF 전체 텍스트
 * @return string|null 노선 라벨 (예: "R1")
 */
function extract_route_label(string $text): ?string
{
    // 패턴 1: "노선: R1" 또는 "Route: R1"
    if (preg_match('/노선\s*[:：]\s*([A-Z0-9가-힣]+)/u', $text, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/Route\s*[:：]\s*([A-Z0-9가-힣]+)/ui', $text, $m)) {
        return trim($m[1]);
    }

    // 패턴 2: "R1 노선" 또는 "셔틀버스 R1"
    if (preg_match('/([A-Z]\d+)\s*노선/u', $text, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/셔틀버스\s*([A-Z0-9가-힣]+)/u', $text, $m)) {
        return trim($m[1]);
    }

    // 패턴 3: 문서 상단에 "R1", "R2" 등의 단독 라벨
    // (첫 200자 이내에서 검색)
    $header = mb_substr($text, 0, 200);
    if (preg_match('/\b([A-Z]\d+)\b/', $header, $m)) {
        return trim($m[1]);
    }

    return null;
}

/**
 * 노선 메타 정보 패턴 (정류장이 아님 — 제외 대상)
 * 예: "3km 5대 40회", "10km", "20대", "30회"
 */
function is_route_metadata(string $s): bool
{
    $t = trim($s);
    if ($t === '') return true;
    // 숫자+km, 숫자+대, 숫자+회 조합
    if (preg_match('/^\d+\s*(km|대|회)\s*$/u', $t)) return true;
    if (preg_match('/^(\d+\s*(km|대|회)\s*)+$/u', $t)) return true;
    // 숫자만으로 된 짧은 토큰 (정류장명은 보통 2글자 이상)
    if (preg_match('/^\d{1,4}$/', $t)) return true;
    return false;
}

/**
 * v1.7-19: 문자열을 "정류장명 1개" 단위로 분할
 * 예: "성동세무서 (04212)-송정동아이파크 (05235)-..." → ["성동세무서 (04212)", "송정동아이파크 (05235)", ...]
 * 구분자: - , – , — , ・
 */
function split_into_single_stops(string $raw): array
{
    $raw = trim($raw);
    if ($raw === '') return [];
    $parts = preg_split('/\s*[-–—・]\s*/u', $raw);
    $out = [];
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p === '' || is_route_metadata($p)) continue;
        $out[] = $p;
    }
    return $out;
}

/**
 * PDF 텍스트에서 정류장 목록 추출
 *
 * v1.7-19: "정류장명 1개" 단위 분할 — "성동세무서 (04212)-송정동아이파크 (05235)-..." 형태는 한 줄 안에서 분할
 * v1.7-19: "3km 5대 40회" 등 노선 메타는 제외
 *
 * 예상 형식:
 * 1. 강남역
 * 2. 역삼역
 * 3. 성동세무서 (04212)-송정동아이파크 (05235)-...
 *
 * @param string $text PDF 전체 텍스트
 * @return array [['seq' => 1, 'raw_stop_name' => '강남역'], ...]
 */
function extract_stops_from_text(string $text): array
{
    $stops = [];
    $lines = preg_split('/\r\n|\r|\n/', $text);

    $seq = 0;

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }

        // 패턴 1: "1. 강남역" 형식 (v1.7-19: 내부 "-" 구분 시 정류장 1개씩 분할)
        if (preg_match('/^(\d+)[\.\)]\s*(.+)$/u', $line, $m)) {
            $content = trim($m[2]);
            $singleStops = split_into_single_stops($content);
            foreach ($singleStops as $stopName) {
                if (!empty($stopName)) {
                    $seq++;
                    $stops[] = [
                        'seq' => $seq,
                        'raw_stop_name' => $stopName,
                    ];
                }
            }
            continue;
        }

        // 패턴 2: "- 강남역" 또는 "• 강남역" 형식
        if (preg_match('/^[-•·]\s*(.+)$/u', $line, $m)) {
            $content = trim($m[1]);
            $singleStops = split_into_single_stops($content);
            foreach ($singleStops as $stopName) {
                if (!empty($stopName)) {
                    $seq++;
                    $stops[] = [
                        'seq' => $seq,
                        'raw_stop_name' => $stopName,
                    ];
                }
            }
            continue;
        }

        // 패턴 3: "정류장" 또는 "역"으로 끝나는 경우 (번호 없이)
        if (preg_match('/(역|정류장)$/u', $line)) {
            $singleStops = split_into_single_stops($line);
            foreach ($singleStops as $stopName) {
                if (!empty($stopName)) {
                    $seq++;
                    $stops[] = [
                        'seq' => $seq,
                        'raw_stop_name' => $stopName,
                    ];
                }
            }
            continue;
        }

        // 패턴 4: v1.7-19 — "-" 구분 연속 정류장 형식 (번호 없이 한 줄)
        if (preg_match('/[가-힣a-zA-Z0-9\s]+(\([^)]+\))?\s*[-–—]/u', $line) || preg_match('/[-–—]\s*[가-힣a-zA-Z0-9\s]+/u', $line)) {
            $singleStops = split_into_single_stops($line);
            foreach ($singleStops as $stopName) {
                if (!empty($stopName)) {
                    $seq++;
                    $stops[] = [
                        'seq' => $seq,
                        'raw_stop_name' => $stopName,
                    ];
                }
            }
            continue;
        }
    }

    return $stops;
}

/**
 * 테스트용 헬퍼 함수
 * CLI에서 직접 실행 시 사용
 */
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    if ($argc < 2) {
        echo "Usage: php pdf_parser.php <path_to_pdf>\n";
        exit(1);
    }

    $pdfPath = $argv[1];
    echo "Parsing PDF: {$pdfPath}\n";
    echo str_repeat('-', 60) . "\n";

    $result = parse_shuttle_pdf($pdfPath);

    if ($result['success']) {
        echo "✓ Success!\n";
        echo "Parser Version: {$result['parser_version']}\n";
        echo "Elapsed: {$result['parsed_at_ms']}ms\n";
        echo "Route Label: {$result['route_label']}\n";
        echo "Stops found: " . count($result['stops']) . "\n\n";
        foreach ($result['stops'] as $stop) {
            echo "  {$stop['seq']}. {$stop['raw_stop_name']}\n";
        }
    } else {
        echo "✗ Error: {$result['error']}\n";
        echo "Error Code: " . ($result['error_code'] ?? '-') . "\n";
        echo "Parser Version: {$result['parser_version']}\n";
        echo "Elapsed: {$result['parsed_at_ms']}ms\n";
    }
}
