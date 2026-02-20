<?php
declare(strict_types=1);
/**
 * API v1 — Issues (user list + admin CRUD + activate/deactivate). SoT: docs/SOT/11_API_V1_IMPL_PLAN.md
 */

/**
 * @param array<string, mixed> $arr
 * @return array<string, mixed>
 */
function api_v1_issue_dispatch(string $path, string $method, string $trace_id): void {
  // Admin paths require X-ADMIN-TOKEN (B9)
  if (strpos($path, 'v1/admin/') === 0) {
    $token = trim((string)($_SERVER['HTTP_X_ADMIN_TOKEN'] ?? ''));
    if ($token === '') {
      http_response_code(403);
      echo json_encode(api_v1_error('FORBIDDEN', 'X-ADMIN-TOKEN required', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
  }

  // v1/admin/issues/{id}/activate | deactivate
  if (preg_match('#^v1/admin/issues/(\d+)/(activate|deactivate)$#', $path, $m) && $method === 'POST') {
    $id = (int) $m[1];
    $action = $m[2];
    $pdo = pdo();
    if ($action === 'activate') {
      $stmt = $pdo->prepare('SELECT id, title, start_at, end_at FROM issues WHERE id = ?');
      $stmt->execute([$id]);
      $row = $stmt->fetch(PDO::FETCH_ASSOC);
      if (!$row) {
        http_response_code(404);
        echo json_encode(api_v1_error('NOT_FOUND', 'issue not found', $trace_id), JSON_UNESCAPED_UNICODE);
        return;
      }
      if (trim($row['title'] ?? '') === '' || $row['start_at'] === null || $row['end_at'] === null) {
        http_response_code(400);
        echo json_encode(api_v1_error('VALIDATION_ERROR', 'title, start_at, end_at required for activate', $trace_id), JSON_UNESCAPED_UNICODE);
        return;
      }
      $pdo->prepare('UPDATE issues SET status = ? WHERE id = ?')->execute(['active', $id]);
    } else {
      $pdo->prepare('UPDATE issues SET status = ? WHERE id = ?')->execute(['ended', $id]);
    }
    http_response_code(200);
    echo json_encode(api_v1_ok(['id' => $id, 'status' => $action === 'activate' ? 'active' : 'ended'], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // v1/admin/issues — list or create
  if ($path === 'v1/admin/issues' && $method === 'GET') {
    $pdo = pdo();
    $stmt = $pdo->query('SELECT id, title, severity, status, start_at, end_at, created_at FROM issues ORDER BY id DESC');
    $list = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    http_response_code(200);
    echo json_encode(api_v1_ok(['issues' => $list], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  if ($path === 'v1/admin/issues' && $method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true) ?: [];
    $title = trim((string)($data['title'] ?? ''));
    if ($title === '') {
      http_response_code(400);
      echo json_encode(api_v1_error('VALIDATION_ERROR', 'title required', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    $severity = trim((string)($data['severity'] ?? 'medium'));
    if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) $severity = 'medium';
    $pdo = pdo();
    $stmt = $pdo->prepare('INSERT INTO issues (title, severity, status, start_at, end_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
      $title,
      $severity,
      'draft',
      !empty($data['start_at']) ? $data['start_at'] : null,
      !empty($data['end_at']) ? $data['end_at'] : null,
    ]);
    $id = (int) $pdo->lastInsertId();
    http_response_code(201);
    echo json_encode(api_v1_ok(['id' => $id, 'title' => $title, 'status' => 'draft'], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // v1/admin/issues/{id} — update
  if (preg_match('#^v1/admin/issues/(\d+)$#', $path, $m) && in_array($method, ['PUT', 'PATCH'], true)) {
    $id = (int) $m[1];
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true) ?: [];
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT id FROM issues WHERE id = ?');
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
      http_response_code(404);
      echo json_encode(api_v1_error('NOT_FOUND', 'issue not found', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    $updates = [];
    $params = [];
    if (array_key_exists('title', $data)) { $updates[] = 'title = ?'; $params[] = trim((string)$data['title']); }
    if (array_key_exists('severity', $data)) { $updates[] = 'severity = ?'; $params[] = in_array($data['severity'], ['low','medium','high','critical'], true) ? $data['severity'] : 'medium'; }
    if (array_key_exists('start_at', $data)) { $updates[] = 'start_at = ?'; $params[] = $data['start_at']; }
    if (array_key_exists('end_at', $data)) { $updates[] = 'end_at = ?'; $params[] = $data['end_at']; }
    if ($updates === []) {
      http_response_code(200);
      echo json_encode(api_v1_ok(['id' => $id], $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    $params[] = $id;
    $pdo->prepare('UPDATE issues SET ' . implode(', ', $updates) . ' WHERE id = ?')->execute($params);
    http_response_code(200);
    echo json_encode(api_v1_ok(['id' => $id], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // v1/issues — user: list active (and draft for debug if needed)
  if ($path === 'v1/issues' && $method === 'GET') {
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT id, title, severity, status, start_at, end_at FROM issues WHERE status IN (?, ?) ORDER BY start_at DESC');
    $stmt->execute(['active', 'draft']);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);
    http_response_code(200);
    echo json_encode(api_v1_ok(['issues' => $list], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // v1/issues/{id}
  if (preg_match('#^v1/issues/(\d+)$#', $path, $m) && $method === 'GET') {
    $id = (int) $m[1];
    $pdo = pdo();
    $stmt = $pdo->prepare('SELECT id, title, severity, status, start_at, end_at FROM issues WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      http_response_code(404);
      echo json_encode(api_v1_error('NOT_FOUND', 'issue not found', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    http_response_code(200);
    echo json_encode(api_v1_ok($row, $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  http_response_code(404);
  echo json_encode(api_v1_error('NOT_FOUND', 'not found', $trace_id), JSON_UNESCAPED_UNICODE);
}
