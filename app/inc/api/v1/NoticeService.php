<?php
declare(strict_types=1);
/**
 * API v1 — Notices (user read-only)
 */

function api_v1_notice_dispatch(string $path, string $method, string $trace_id): void {
  if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(api_v1_error('METHOD_NOT_ALLOWED', 'GET only', $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // v1/notices/{id}
  if (preg_match('#^v1/notices/(\d+)$#', $path, $m)) {
    $id = (int)$m[1];
    $pdo = pdo();
    $stmt = $pdo->prepare("
      SELECT id, category, label, title, body_md, starts_at, ends_at, published_at,
             (CASE WHEN ends_at IS NOT NULL AND ends_at < NOW() THEN 1 ELSE 0 END) AS is_ended
      FROM notices
      WHERE id = ?
      LIMIT 1
    ");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
      http_response_code(404);
      echo json_encode(api_v1_error('NOT_FOUND', 'notice not found', $trace_id), JSON_UNESCAPED_UNICODE);
      return;
    }
    http_response_code(200);
    echo json_encode(api_v1_ok($row, $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  // v1/notices
  if ($path === 'v1/notices') {
    $category = trim((string)($_GET['category'] ?? 'notice'));
    if (!in_array($category, ['notice', 'event'], true)) $category = 'notice';
    $status = trim((string)($_GET['status'] ?? 'active'));
    if (!in_array($status, ['active', 'all'], true)) $status = 'active';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $size = (int)($_GET['size'] ?? 20);
    $size = max(1, min(50, $size));
    $offset = ($page - 1) * $size;

    $where = ['category = :category'];
    if ($status === 'active') {
      $where[] = "status = 'published'";
      $where[] = '(starts_at IS NULL OR starts_at <= NOW())';
      $where[] = '(ends_at IS NULL OR NOW() <= ends_at)';
    }
    $whereSql = implode(' AND ', $where);

    $pdo = pdo();
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM notices WHERE {$whereSql}");
    $countStmt->execute([':category' => $category]);
    $total = (int)$countStmt->fetchColumn();

    $listStmt = $pdo->prepare("
      SELECT id, category, label, title, is_pinned, published_at, starts_at, ends_at,
             (CASE WHEN ends_at IS NOT NULL AND ends_at < NOW() THEN 1 ELSE 0 END) AS is_ended
      FROM notices
      WHERE {$whereSql}
      ORDER BY is_pinned DESC, published_at DESC, id DESC
      LIMIT :limit OFFSET :offset
    ");
    $listStmt->bindValue(':category', $category, PDO::PARAM_STR);
    $listStmt->bindValue(':limit', $size, PDO::PARAM_INT);
    $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $listStmt->execute();
    $items = $listStmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode(api_v1_ok([
      'items' => $items,
      'page' => $page,
      'size' => $size,
      'total' => $total,
      'category' => $category,
      'status' => $status,
    ], $trace_id), JSON_UNESCAPED_UNICODE);
    return;
  }

  http_response_code(404);
  echo json_encode(api_v1_error('NOT_FOUND', 'not found', $trace_id), JSON_UNESCAPED_UNICODE);
}
