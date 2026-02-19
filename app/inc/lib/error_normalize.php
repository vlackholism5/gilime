<?php
declare(strict_types=1);

/**
 * 레거시/자유형 에러 문구를 표준 error_code로 정규화.
 * 이미 error_code=XXX 형식이면 해당 코드를 우선 사용한다.
 */
function normalize_error_code(?string $rawMessageOrNote): string
{
  $raw = trim((string)$rawMessageOrNote);
  if ($raw === '') return 'UNKNOWN';

  if (preg_match('/error_code=([A-Z0-9_]+)/', $raw, $m)) {
    $code = (string)$m[1];
    /** v1.7-14: 레거시 코드 → PARSE_ prefix 정규화 */
    $legacyMap = [
      'DEP_MISSING' => 'PARSE_DEP_MISSING',
      'FILE_NOT_FOUND' => 'PARSE_FILE_NOT_FOUND',
      'FILE_READ_FAILED' => 'PARSE_FILE_READ_FAILED',
      'INVALID_FILE_TYPE' => 'PARSE_INVALID_FILE_TYPE',
      'FILE_TOO_LARGE' => 'PARSE_FILE_TOO_LARGE',
      'NO_TEXT' => 'PARSE_NO_TEXT',
      'OCR_FAILED' => 'PARSE_OCR_FAILED',
      'PATH_TRAVERSAL' => 'PARSE_PATH_TRAVERSAL',
      'PATH_OUTSIDE_UPLOADS' => 'PARSE_PATH_OUTSIDE_UPLOADS',
      'ROUTE_NOT_FOUND' => 'PARSE_NO_ROUTE',
      'STOPS_NOT_FOUND' => 'PARSE_NO_STOPS',
      'FILE_PATH_EMPTY' => 'PARSE_FILE_PATH_EMPTY',
      'RUN_JOB_EXCEPTION' => 'PARSE_RUN_EXCEPTION',
      'BATCH_RUN_EXCEPTION' => 'PARSE_RUN_EXCEPTION',
      'DOC_NOT_FOUND' => 'PARSE_DOC_NOT_FOUND',
    ];
    return $legacyMap[$code] ?? $code;
  }

  $s = strtolower($raw);

  /** v1.7-14: PARSE_ prefix로 통일 (로그/DB 추적) */
  $map = [
    'PARSE_DEP_MISSING' => [
      'dependency missing',
      'composer install',
      'autoload.php',
      "class 'smalot\\pdfparser\\parser' not found",
    ],
    'PARSE_FILE_NOT_FOUND' => [
      'file not found',
      'no such file',
      'invalid/missing pdf file',
      'not found for source_doc_id',
    ],
    'PARSE_FILE_READ_FAILED' => [
      'not readable',
      'failed to open stream',
      'permission denied',
      'file is not readable',
    ],
    'PARSE_INVALID_FILE_TYPE' => [
      'invalid file type',
      'only .pdf',
      'invalid file extension',
    ],
    'PARSE_FILE_TOO_LARGE' => [
      'file too large',
      'max 10mb',
      'too large',
    ],
    'PARSE_NO_TEXT' => [
      'no extractable text',
      'text empty',
    ],
    'PARSE_OCR_FAILED' => [
      'ocr failed',
      'ocr not configured',
      'extract_text',
    ],
    'PARSE_PATH_TRAVERSAL' => [
      'path traversal',
      'traversal not allowed',
    ],
    'PARSE_NO_ROUTE' => [
      'could not detect route label',
      'route label',
      'route label is empty',
    ],
    'PARSE_NO_STOPS' => [
      'no stops found',
      'empty parse result',
      'all filtered after normalization',
    ],
    'PARSE_EXCEPTION' => [
      'pdf parsing error',
      'sqlstate',
      'exception',
      'base table or view not found',
    ],
  ];

  foreach ($map as $code => $needles) {
    foreach ($needles as $needle) {
      if (strpos($s, $needle) !== false) {
        return $code;
      }
    }
  }

  return 'UNKNOWN';
}

