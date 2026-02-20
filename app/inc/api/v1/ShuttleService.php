<?php
declare(strict_types=1);
/**
 * API v1 — Admin Shuttle routes & stops + activate/deactivate. SoT: docs/SOT/11_API_V1_IMPL_PLAN.md, Gilime_Admin_Wireframe_MVP_v2.md
 */

function api_v1_shuttle_dispatch(string $path, string $method, string $trace_id): void {
  $token = trim((string)($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''));
  if ($token === '') {
    http_response_code(403);
    echo json_encode(api_v1_error('FORBIDDEN', 'X-ADMIN-TOKEN required', $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  $pdo = pdo();

  // POST v1/admin/shuttles/routes — create shuttle route
  if ($path === 'v1/admin/shuttles/routes' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true) ?: [];
    $issue_id = (int)($data['issue_id'] ?? 0);
    $route_name = trim((string)($data['route_name'] ?? ''));
    if ($issue_id <= 0 || $route_name === '') {
      http_response_code(400);
      echo json_encode(api_v1_error('VALIDATION_ERROR', 'issue_id and route_name required', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    $stmt = $pdo->prepare('SELECT id FROM issues WHERE id = ?');
    $stmt->execute([$issue_id]);
    if (!$stmt->fetch()) {
      http_response_code(400);
      echo json_encode(api_v1_error('VALIDATION_ERROR', 'issue not found', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    try {
      $pdo->prepare('INSERT INTO shuttle_routes (issue_id, route_name, headway_min, service_hours, status) VALUES (?, ?, ?, ?, ?)')
        ->execute([
          $issue_id,
          $route_name,
          isset($data['headway_min']) ? (int)$data['headway_min'] : null,
          isset($data['service_hours']) ? (string)$data['service_hours'] : null,
          'draft',
        ]);
    } catch (PDOException $e) {
      if ($e->getCode() == 23000) {
        http_response_code(400);
        echo json_encode(api_v1_error('VALIDATION_ERROR', 'duplicate route_name for this issue', $trace_id), JSON_UNESCAPED_UNICODE);
        return;
      }
      throw $e;
    }
    $id = (int) $pdo->lastInsertId();
    http_response_code(201);
    echo json_encode(api_v1_ok(['id' => $id, 'issue_id' => $issue_id, 'route_name' => $route_name, 'status' => 'draft'], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // PUT v1/admin/shuttles/routes/{id}/stops — upsert stop sequence (replace all)
  if (preg_match('#^v1/admin/shuttles/routes/(\d+)/stops$#', $path, $m) && $method === 'PUT') {
    $route_id = (int) $m[1];
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true) ?: [];
    $stops = isset($data['stops']) && is_array($data['stops']) ? $data['stops'] : [];
    if (count($stops) < 2) {
      http_response_code(400);
      echo json_encode(api_v1_error('VALIDATION_ERROR', 'at least 2 stops required', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    $stmt = $pdo->prepare('SELECT id FROM shuttle_routes WHERE id = ?');
    $stmt->execute([$route_id]);
    if (!$stmt->fetch()) {
      http_response_code(404);
      echo json_encode(api_v1_error('NOT_FOUND', 'shuttle route not found', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    $order = 1;
    $seen = [];
    foreach ($stops as $s) {
      $stop_id = trim((string)($s['stop_id'] ?? ''));
      $stop_name = trim((string)($s['stop_name'] ?? ''));
      $lat = isset($s['lat']) ? (float)$s['lat'] : null;
      $lng = isset($s['lng']) ? (float)$s['lng'] : null;
      if ($stop_id === '') {
        http_response_code(400);
        echo json_encode(api_v1_error('VALIDATION_ERROR', 'stop_id required for each stop', $trace_id), JSON_UNESCAPED_UNICODE);
        return;
      }
      if (isset($seen[$stop_id])) {
        http_response_code(400);
        echo json_encode(api_v1_error('VALIDATION_ERROR', 'duplicate stop_id in sequence', $trace_id), JSON_UNESCAPED_UNICODE);
        return;
      }
      $seen[$stop_id] = true;
      $order++;
    }
    $pdo->beginTransaction();
    try {
      $pdo->prepare('DELETE FROM shuttle_stops WHERE shuttle_route_id = ?')->execute([$route_id]);
      $ins = $pdo->prepare('INSERT INTO shuttle_stops (shuttle_route_id, stop_order, stop_id, stop_name, lat, lng) VALUES (?, ?, ?, ?, ?, ?)');
      $order = 1;
      foreach ($stops as $s) {
        $ins->execute([
          $route_id,
          $order++,
          trim((string)($s['stop_id'] ?? '')),
          trim((string)($s['stop_name'] ?? '')),
          isset($s['lat']) ? (float)$s['lat'] : null,
          isset($s['lng']) ? (float)$s['lng'] : null,
        ]);
      }
      $pdo->commit();
    } catch (Throwable $e) {
      $pdo->rollBack();
      throw $e;
    }
    http_response_code(200);
    echo json_encode(api_v1_ok(['shuttle_route_id' => $route_id, 'stops_count' => count($stops)], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // POST v1/admin/shuttles/routes/{id}/activate | deactivate
  if (preg_match('#^v1/admin/shuttles/routes/(\d+)/(activate|deactivate)$#', $path, $m) && $method === 'POST') {
    $route_id = (int) $m[1];
    $action = $m[2];
    $stmt = $pdo->prepare('SELECT id FROM shuttle_routes WHERE id = ?');
    $stmt->execute([$route_id]);
    if (!$stmt->fetch()) {
      http_response_code(404);
      echo json_encode(api_v1_error('NOT_FOUND', 'shuttle route not found', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    if ($action === 'activate') {
      $stmt = $pdo->prepare('SELECT COUNT(*) FROM shuttle_stops WHERE shuttle_route_id = ?');
      $stmt->execute([$route_id]);
      $cnt = (int) $stmt->fetchColumn();
      if ($cnt < 2) {
        http_response_code(400);
        echo json_encode(api_v1_error('VALIDATION_ERROR', 'at least 2 stops required to activate', $trace_id), JSON_UNESCAPED_UNICODE);
        return;
      }
      $pdo->prepare('UPDATE shuttle_routes SET status = ? WHERE id = ?')->execute(['active', $route_id]);
    } else {
      $pdo->prepare('UPDATE shuttle_routes SET status = ? WHERE id = ?')->execute(['ended', $route_id]);
    }
    http_response_code(200);
    echo json_encode(api_v1_ok(['id' => $route_id, 'status' => $action === 'activate' ? 'active' : 'ended'], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // GET v1/admin/shuttles/routes?issue_id= — list routes (optionally by issue_id)
  if ($path === 'v1/admin/shuttles/routes' && $method === 'GET') {
    $issue_id = (int)($_GET['issue_id'] ?? 0);
    if ($issue_id > 0) {
      $stmt = $pdo->prepare('SELECT id, issue_id, route_name, headway_min, service_hours, status FROM shuttle_routes WHERE issue_id = ? ORDER BY id');
      $stmt->execute([$issue_id]);
    } else {
      $stmt = $pdo->query('SELECT id, issue_id, route_name, headway_min, service_hours, status FROM shuttle_routes ORDER BY id DESC');
    }
    $list = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    http_response_code(200);
    echo json_encode(api_v1_ok(['routes' => $list], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  http_response_code(404);
  echo json_encode(api_v1_error('NOT_FOUND', 'not found', $trace_id), JSON_UNESCAPED_UNICODE);
}
