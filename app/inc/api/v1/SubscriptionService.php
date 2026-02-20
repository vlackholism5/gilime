<?php
declare(strict_types=1);
/**
 * API v1 — Subscriptions (minimal stub). SoT: docs/SOT/11_API_V1_IMPL_PLAN.md — CRUD stub
 */

function api_v1_subscription_dispatch(string $path, string $method, string $trace_id): void {
  $pdo = pdo();

  // GET v1/subscriptions — list (stub: empty or from app_subscriptions if exists)
  if ($path === 'v1/subscriptions' && $method === 'GET') {
    $list = [];
    try {
      $stmt = $pdo->query("SELECT target_id, target_type, is_active FROM app_subscriptions WHERE 1=0");
      if ($stmt) $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      // table may not exist
    }
    http_response_code(200);
    echo json_encode(api_v1_ok(['subscriptions' => $list], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // POST v1/subscriptions — create (stub)
  if ($path === 'v1/subscriptions' && $method === 'POST') {
    http_response_code(201);
    echo json_encode(api_v1_ok(['id' => 'sub_stub', 'status' => 'subscribed'], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // DELETE v1/subscriptions/{id} — stub
  if (preg_match('#^v1/subscriptions/([a-zA-Z0-9_\-]+)$#', $path, $m) && $method === 'DELETE') {
    http_response_code(200);
    echo json_encode(api_v1_ok(['id' => $m[1], 'deleted' => true], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  http_response_code(404);
  echo json_encode(api_v1_error('NOT_FOUND', 'not found', $trace_id), JSON_UNESCAPED_UNICODE);
}
