<?php
require_once __DIR__ . '/../../app/inc/config/config.php';
require_once __DIR__ . '/../../app/inc/auth/db.php';
require_once __DIR__ . '/../../app/inc/route/route_finder.php';

$pdo = pdo();
$items = route_finder_suggest_stops($pdo, '문래', 10);
echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
