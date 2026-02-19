<?php
declare(strict_types=1);
/**
 * v1.8 추천검색어 API — 정류장 자동완성
 * GET /api/route/suggest_stops?q={검색어}
 * @see docs/ux/ROUTE_FINDER_AUTOCOMPLETE_v1_8.md
 */
require_once __DIR__ . '/../../../app/inc/config/config.php';
require_once __DIR__ . '/../../../app/inc/auth/db.php';
require_once __DIR__ . '/../../../app/inc/route/route_finder.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') {
  echo json_encode(['items' => []], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  $pdo = pdo();
  $items = route_finder_suggest_stops($pdo, $q, 10);
  echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['items' => [], 'error' => 'server error'], JSON_UNESCAPED_UNICODE);
}
