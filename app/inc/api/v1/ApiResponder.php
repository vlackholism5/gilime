<?php
declare(strict_types=1);
/**
 * API v1 standard response wrapper. SoT: docs/SOT/11_API_V1_IMPL_PLAN.md
 */

/**
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function api_v1_ok(array $data, string $trace_id): array {
  return [
    'ok' => true,
    'data' => $data,
    'meta' => [
      'trace_id' => $trace_id,
      'server_time' => date('Y-m-d\TH:i:sP'),
    ],
  ];
}

/**
 * @param string $code e.g. VALIDATION_ERROR, NOT_FOUND
 * @param string $message
 * @return array<string, mixed>
 */
function api_v1_error(string $code, string $message, string $trace_id): array {
  return [
    'ok' => false,
    'error' => [
      'code' => $code,
      'message' => $message,
    ],
    'meta' => [
      'trace_id' => $trace_id,
      'server_time' => date('Y-m-d\TH:i:sP'),
    ],
  ];
}
