<?php
if (!defined('APP_BASE')) require_once __DIR__ . '/../../app/inc/config/config.php';

// 페이지별 설정 기본값
$pageTitle = $pageTitle ?? 'GILIME';
$mainClass = $mainClass ?? '';
$currentScript = basename($_SERVER['SCRIPT_NAME']);
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= $pageTitle ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
  <?php if ($currentScript === 'home.php'): ?>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
  <?php endif; ?>
</head>
<body class="gilaime-app">
  <!-- 페이지별 본문 시작 -->
  <main class="<?= $mainClass ?>">