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
    return (string)$m[1];
  }

  $s = strtolower($raw);

  $map = [
    'DEP_MISSING' => [
      'dependency missing',
      'composer install',
      'autoload.php',
      "class 'smalot\\pdfparser\\parser' not found",
    ],
    'FILE_NOT_FOUND' => [
      'file not found',
      'no such file',
      'invalid/missing pdf file',
      'not found for source_doc_id',
    ],
    'FILE_READ_FAILED' => [
      'not readable',
      'failed to open stream',
      'permission denied',
      'file is not readable',
    ],
    'INVALID_FILE_TYPE' => [
      'invalid file type',
      'only .pdf',
      'invalid file extension',
    ],
    'FILE_TOO_LARGE' => [
      'file too large',
      'max 10mb',
      'too large',
    ],
    'NO_TEXT' => [
      'no extractable text',
      'ocr',
      'text empty',
    ],
    'ROUTE_NOT_FOUND' => [
      'could not detect route label',
      'route label',
    ],
    'STOPS_NOT_FOUND' => [
      'no stops found',
      'empty parse result',
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

