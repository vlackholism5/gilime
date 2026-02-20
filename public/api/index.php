<?php
declare(strict_types=1);
/**
 * API entry: trace_id + debug endpoints. GILIME_DEBUG=1 for debug routes.
 */
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/lib/observability.php';

$path = trim((string)($_GET['path'] ?? ''));
$trace_id = get_trace_id();

header('Content-Type: application/json; charset=utf-8');

try {
  if (is_debug_enabled()) {
    safe_log('route_enter', $trace_id, [
      'method' => $_SERVER['REQUEST_METHOD'] ?? '',
      'path' => $path,
    ]);
  }

  if ($path === 'debug/ping') {
    if (!is_debug_enabled()) {
      http_response_code(404);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => 'not found'], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    $db_ok = false;
    try {
      require_once __DIR__ . '/../../app/inc/auth/db.php';
      pdo()->query('SELECT 1');
      $db_ok = true;
    } catch (Throwable $e) {
      if (is_debug_enabled()) {
        safe_log('exception', $trace_id, ['code' => $e->getCode(), 'msg' => substr($e->getMessage(), 0, 100)]);
      }
    }
    $body = ['ok' => true, 'ts' => date('Y-m-d H:i:s'), 'db_ok' => $db_ok];
    echo json_encode(attach_trace_id_to_response($body, $trace_id), JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($path === 'debug/echo-trace') {
    if (!is_debug_enabled()) {
      http_response_code(404);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => 'not found'], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    $body = ['ok' => true];
    echo json_encode(attach_trace_id_to_response($body, $trace_id), JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($path === 'subscription/toggle') {
    require_once __DIR__ . '/../../app/inc/auth/db.php';
    require_once __DIR__ . '/../../app/inc/auth/user_session.php';
    $userId = user_session_user_id();
    if ($userId <= 0) {
      http_response_code(401);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => 'unauthorized'], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $action = isset($data['action']) ? trim((string)$data['action']) : '';
    $docId = isset($data['doc_id']) ? (int)$data['doc_id'] : 0;
    $routeLabel = isset($data['route_label']) ? trim((string)$data['route_label']) : '';
    safe_log('handler_enter', $trace_id, [
      'user_id' => $userId,
      'action' => $action,
      'doc_id' => $docId,
      'route_label' => $routeLabel,
    ]);
    if (!in_array($action, ['subscribe', 'unsubscribe'], true) || $docId <= 0 || $routeLabel === '') {
      http_response_code(400);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => 'bad params'], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    $targetId = $docId . '_' . $routeLabel;
    $isActive = $action === 'subscribe' ? 1 : 0;
    safe_log('before_db', $trace_id, ['target_id' => $targetId, 'is_active' => $isActive]);
    $pdo = pdo();
    if ($action === 'subscribe') {
      $stmt = $pdo->prepare("
        INSERT INTO app_subscriptions (user_id, target_type, target_id, alert_type, is_active)
        VALUES (:uid, 'route', :target_id, 'strike,event,update', 1)
        ON DUPLICATE KEY UPDATE is_active = 1, updated_at = NOW()
      ");
      $stmt->execute([':uid' => $userId, ':target_id' => $targetId]);
      $affected = $stmt->rowCount();
    } else {
      $stmt = $pdo->prepare("
        UPDATE app_subscriptions SET is_active = 0, updated_at = NOW()
        WHERE user_id = :uid AND target_type = 'route' AND target_id = :target_id
      ");
      $stmt->execute([':uid' => $userId, ':target_id' => $targetId]);
      $affected = $stmt->rowCount();
    }
    safe_log('after_db_ok', $trace_id, ['affected_rows' => $affected]);
    echo json_encode(attach_trace_id_to_response(['ok' => true, 'action' => $action], $trace_id), JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($path === 'g1/station-lines/by-name' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $station_name = trim((string)($_GET['station_name'] ?? ''));
    if ($station_name === '' || mb_strlen($station_name) > 60) {
      http_response_code(400);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => ['code' => 'bad_request', 'message' => 'station_name required (1-60 chars)']], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    safe_log('api_enter', $trace_id, ['endpoint' => 'by-name', 'station_name' => $station_name]);
    require_once __DIR__ . '/../../app/inc/auth/db.php';
    require_once __DIR__ . '/../../app/inc/api/g1_station_lines.php';
    safe_log('db_query_start', $trace_id, ['endpoint' => 'by-name', 'bind_key' => 'station_name', 'bind_value' => $station_name]);
    $result = g1_station_lines_lookup(pdo(), 'by-name', 'station_name', $station_name);
    safe_log('db_query_end', $trace_id, ['endpoint' => 'by-name', 'rows_returned' => $result['row'] ? 1 : 0, 'query_ms' => $result['query_ms']]);
    if ($result['row'] === null) {
      safe_log('api_exit', $trace_id, ['endpoint' => 'by-name', 'status' => 404]);
      http_response_code(404);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Station not found']], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    $data = [
      'station_name' => $result['row']['station_name'],
      'station_cd' => $result['row']['station_cd'] ?? null,
      'master_line_code' => $result['row']['master_line_code'] ?? null,
      'line_codes' => $result['row']['line_codes'],
      'line_codes_source' => $result['row']['line_codes_source'] ?? 'none',
      'degree_edges' => (int)($result['row']['degree_edges'] ?? 0),
      'meta' => $result['row']['meta'],
    ];
    safe_log('api_exit', $trace_id, ['endpoint' => 'by-name', 'status' => 200]);
    echo json_encode(attach_trace_id_to_response(['ok' => true, 'data' => $data], $trace_id), JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($path === 'g1/station-lines/by-code' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
    $station_cd = trim((string)($_GET['station_cd'] ?? ''));
    if (strlen($station_cd) < 2 || strlen($station_cd) > 10 || !ctype_digit($station_cd)) {
      http_response_code(400);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => ['code' => 'bad_request', 'message' => 'station_cd required (2-10 digits)']], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    safe_log('api_enter', $trace_id, ['endpoint' => 'by-code', 'station_cd' => $station_cd]);
    require_once __DIR__ . '/../../app/inc/auth/db.php';
    require_once __DIR__ . '/../../app/inc/api/g1_station_lines.php';
    safe_log('db_query_start', $trace_id, ['endpoint' => 'by-code', 'bind_key' => 'station_cd', 'bind_value' => $station_cd]);
    $result = g1_station_lines_lookup(pdo(), 'by-code', 'station_cd', $station_cd);
    safe_log('db_query_end', $trace_id, ['endpoint' => 'by-code', 'rows_returned' => $result['row'] ? 1 : 0, 'query_ms' => $result['query_ms']]);
    if ($result['row'] === null) {
      safe_log('api_exit', $trace_id, ['endpoint' => 'by-code', 'status' => 404]);
      http_response_code(404);
      echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'Station not found']], $trace_id), JSON_UNESCAPED_UNICODE);
      exit;
    }
    $data = [
      'station_name' => $result['row']['station_name'],
      'station_cd' => $result['row']['station_cd'] ?? null,
      'master_line_code' => $result['row']['master_line_code'] ?? null,
      'line_codes' => $result['row']['line_codes'],
      'line_codes_source' => $result['row']['line_codes_source'] ?? 'none',
      'degree_edges' => (int)($result['row']['degree_edges'] ?? 0),
      'meta' => $result['row']['meta'],
    ];
    safe_log('api_exit', $trace_id, ['endpoint' => 'by-code', 'status' => 200]);
    echo json_encode(attach_trace_id_to_response(['ok' => true, 'data' => $data], $trace_id), JSON_UNESCAPED_UNICODE);
    exit;
  }

  // API v1: /api/index.php?path=v1/...
  if (strpos($path, 'v1/') === 0) {
    require_once __DIR__ . '/../../app/inc/api/v1/router.php';
    exit;
  }

  http_response_code(404);
  echo json_encode(attach_trace_id_to_response(['ok' => false, 'error' => 'not found'], $trace_id), JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
  if (is_debug_enabled()) {
    safe_log('exception', $trace_id, ['code' => $e->getCode(), 'msg' => substr($e->getMessage(), 0, 100)]);
  }
  http_response_code(500);
  echo json_encode(attach_trace_id_to_response([
    'ok' => false,
    'error' => 'server error',
  ], $trace_id), JSON_UNESCAPED_UNICODE);
}
