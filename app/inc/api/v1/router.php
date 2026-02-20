<?php
declare(strict_types=1);
/**
 * API v1 path dispatcher. SoT: docs/SOT/11_API_V1_IMPL_PLAN.md
 * Entry: path starts with "v1/" (e.g. v1/ping, v1/issues, v1/admin/issues).
 */
require_once __DIR__ . '/ApiResponder.php';

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// GET v1/ping — liveness
if ($path === 'v1/ping' && $method === 'GET') {
  http_response_code(200);
  echo json_encode(api_v1_ok(['pong' => true], $trace_id), JSON_UNESCAPED_UNICODE);
  return;
}

// v1/issues + v1/admin/issues — IssueService (B4)
if ((($path === 'v1/issues' || preg_match('#^v1/issues(/[^/]*)?$#', $path)) && in_array($method, ['GET', 'POST'], true))
    || (preg_match('#^v1/admin/issues#', $path) && in_array($method, ['GET', 'POST', 'PUT', 'PATCH'], true))) {
  require_once __DIR__ . '/../../auth/db.php';
  require_once __DIR__ . '/IssueService.php';
  api_v1_issue_dispatch($path, $method, $trace_id);
  return;
}

// v1/admin/shuttles — ShuttleService (B5)
if (preg_match('#^v1/admin/shuttles#', $path)) {
  require_once __DIR__ . '/../../auth/db.php';
  require_once __DIR__ . '/ShuttleService.php';
  api_v1_shuttle_dispatch($path, $method, $trace_id);
  return;
}

// v1/routes/search — RouteService (B6)
if ($path === 'v1/routes/search' && $method === 'POST') {
  require_once __DIR__ . '/../../auth/db.php';
  require_once __DIR__ . '/RouteService.php';
  api_v1_routes_search_dispatch($trace_id);
  return;
}

// v1/guidance — GuidanceService stub (B7)
if (preg_match('#^v1/guidance#', $path)) {
  require_once __DIR__ . '/GuidanceService.php';
  api_v1_guidance_dispatch($path, $method, $trace_id);
  return;
}

// v1/subscriptions — SubscriptionService stub (B7)
if (preg_match('#^v1/subscriptions#', $path)) {
  require_once __DIR__ . '/../../auth/db.php';
  require_once __DIR__ . '/SubscriptionService.php';
  api_v1_subscription_dispatch($path, $method, $trace_id);
  return;
}

// v1/notices — NoticeService (read-only)
if (($path === 'v1/notices' || preg_match('#^v1/notices/\d+$#', $path)) && $method === 'GET') {
  require_once __DIR__ . '/../../auth/db.php';
  require_once __DIR__ . '/NoticeService.php';
  api_v1_notice_dispatch($path, $method, $trace_id);
  return;
}

// Not found
http_response_code(404);
echo json_encode(api_v1_error('NOT_FOUND', 'not found', $trace_id), JSON_UNESCAPED_UNICODE);
