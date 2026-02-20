<?php
declare(strict_types=1);
/**
 * API v1 — Guidance (minimal stub). SoT: docs/SOT/11_API_V1_IMPL_PLAN.md — start/get/reroute
 */

function api_v1_guidance_dispatch(string $path, string $method, string $trace_id): void {
  // POST v1/guidance/start — stub: return session id
  if ($path === 'v1/guidance/start' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true) ?: [];
    $session_id = 'g_' . date('YmdHis') . '_' . bin2hex(random_bytes(4));
    http_response_code(201);
    echo json_encode(api_v1_ok(['session_id' => $session_id, 'status' => 'active'], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // GET v1/guidance/{session_id} — stub
  if (preg_match('#^v1/guidance/([a-zA-Z0-9_\-]+)$#', $path, $m) && $method === 'GET') {
    http_response_code(200);
    echo json_encode(api_v1_ok(['session_id' => $m[1], 'status' => 'active', 'current_step' => null], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // POST v1/guidance/{session_id}/reroute — stub
  if (preg_match('#^v1/guidance/([a-zA-Z0-9_\-]+)/reroute$#', $path, $m) && $method === 'POST') {
    http_response_code(200);
    echo json_encode(api_v1_ok(['session_id' => $m[1], 'rerouted' => true], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  http_response_code(404);
  echo json_encode(api_v1_error('NOT_FOUND', 'not found', $trace_id), JSON_UNESCAPED_UNICODE);
}
