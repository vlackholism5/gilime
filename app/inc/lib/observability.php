<?php
declare(strict_types=1);
/**
 * Trace + safe logging. GILIME_DEBUG=1 (or true/yes) when enabled.
 * Sensitive keys (password, token, authorization, etc.) are never logged.
 * PII 마스킹: file_path 등은 len+preview(50자)만 기록.
 *
 * v1.7-14: PARSE_MATCH 표준 이벤트 (증거 수집 포맷)
 * - parse_job_start: source_doc_id, job_id, parse_status=running, file_path(마스킹)
 * - parse_pdf_done: source_doc_id, success, error_code, duration_ms, parser_version
 * - candidate_insert_done: source_doc_id, job_id, rows(추출 stop 수), auto_matched_cnt, auto_matched_ratio_pct, route_label
 * - parse_job_end: source_doc_id, job_id, result(success|failed), elapsed_ms, parse_duration_ms,
 *   stop_cnt(성공 시), auto_matched_ratio_pct(성공 시), error_code(실패 시)
 */

function is_debug_enabled(): bool {
  $v = getenv('GILIME_DEBUG');
  if ($v === false || $v === '') return false;
  $v = strtolower(trim((string)$v));
  return in_array($v, ['1', 'true', 'yes'], true);
}

function get_trace_id(): string {
  $fromHeader = null;
  if (!empty($_SERVER['HTTP_X_TRACE_ID'])) {
    $fromHeader = trim((string)$_SERVER['HTTP_X_TRACE_ID']);
    if ($fromHeader !== '') return $fromHeader;
  }
  if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && $raw !== '') {
      $dec = json_decode($raw, true);
      if (is_array($dec) && isset($dec['trace_id']) && is_string($dec['trace_id'])) {
        $t = trim($dec['trace_id']);
        if ($t !== '') return $t;
      }
    }
  }
  return 'trc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3));
}

/** @param array<string,mixed> $arr */
function attach_trace_id_to_response(array $arr, string $trace_id): array {
  $arr['trace_id'] = $trace_id;
  return $arr;
}

/** Keys that must never be logged (sensitive). */
const OBSERVABILITY_SENSITIVE_KEYS = [
  'password', 'passwd', 'pwd', 'secret', 'token', 'authorization', 'auth',
  'cookie', 'session', 'api_key', 'apikey', 'access_token', 'refresh_token',
  'credit_card', 'ssn', 'phone', 'email',  // optional: relax if only non-PII logged
];

function safe_log(string $event, string $trace_id, array $fields = []): void {
  if (!is_debug_enabled()) return;
  $safe = [];
  foreach ($fields as $k => $v) {
    $lk = strtolower((string)$k);
    foreach (OBSERVABILITY_SENSITIVE_KEYS as $skip) {
      if (strpos($lk, $skip) !== false) continue 2;
    }
    if (is_string($v)) {
      $len = strlen($v);
      $preview = mb_substr($v, 0, 50);
      if ($len > 50) $preview .= '…';
      $safe[$k] = "len={$len} preview=" . $preview;
    } elseif (is_scalar($v)) {
      $safe[$k] = $v;
    } else {
      $safe[$k] = '(non-scalar)';
    }
  }
  $ctx = $safe === [] ? '' : ' ' . json_encode($safe, JSON_UNESCAPED_UNICODE);
  error_log("[TRACE {$trace_id}] {$event}{$ctx}");
}
