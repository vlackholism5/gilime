<?php
declare(strict_types=1);

define('APP_BASE', '/gilime_mvp_01');

if (file_exists(__DIR__ . '/config.local.php')) {
  require __DIR__ . '/config.local.php';
}

if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: '3306');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'gilaime');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? (string)getenv('DB_PASS') : '');

// v1.7-20: Python OCR (텍스트 없는 PDF 처리)
if (!defined('OCR_PYTHON_CMD')) define('OCR_PYTHON_CMD', 'python');  // 또는 'py', 'c:\...\python.exe'
if (!defined('OCR_TESSERACT_CMD')) define('OCR_TESSERACT_CMD', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');

// v1.7-20: GPT 검수 파이프라인 (route_review → run_gpt_review.php)
if (!defined('GPT_PYTHON_CMD')) define('GPT_PYTHON_CMD', 'python');  // 또는 'py', 'c:\...\python.exe'
if (!defined('GPT_OPENAPI_API_KEY')) define('GPT_OPENAPI_API_KEY', '');  // config.local.php에서 설정 (API 키는 .gitignore 대상)
