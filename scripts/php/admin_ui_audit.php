<?php
declare(strict_types=1);
/**
 * Admin UI/UX SOT 감사 스크립트
 * 사용: php scripts/php/admin_ui_audit.php
 * public/admin/*.php 를 스캔하여 SOT 규칙 적용 여부를 보고합니다.
 * 참조: docs/ui/ADMIN_UI_BATCH_FIX_GUIDE.md, docs/ui/SOT_GILAIME_UI_SYSTEM.md
 */

$baseDir = dirname(__DIR__, 2);
$adminDir = $baseDir . '/public/admin';

$rules = [
  'main_container'   => ['pattern' => 'container-fluid\s+py-4',   'desc' => 'main에 container-fluid py-4'],
  'admin_nav'        => ['pattern' => 'render_admin_nav',        'desc' => 'render_admin_nav() 호출'],
  'admin_header'     => ['pattern' => 'render_admin_header',     'desc' => 'render_admin_header() 호출'],
  'g_page_head'      => ['pattern' => 'g-page-head',             'desc' => 'g-page-head 사용'],
  'table_responsive' => ['pattern' => 'table-responsive',       'desc' => '테이블 래퍼 table-responsive'],
  'g_table'         => ['pattern' => 'g-table',                 'desc' => '테이블 클래스 g-table'],
  'g_card'          => ['pattern' => 'g-card',                  'desc' => '카드 클래스 g-card'],
  'hint_or_helper'   => ['pattern' => 'text-muted-g|\.helper',  'desc' => '힌트/helper (text-muted-g 또는 .helper)'],
  'btn_sot'         => ['pattern' => 'btn-gilaime-primary|btn-outline-secondary', 'desc' => '버튼 SOT (gilaime-primary 또는 outline-secondary)'],
  'app_base_links'   => ['pattern' => 'APP_BASE.*admin/',        'desc' => '링크에 APP_BASE 사용'],
];

// 로그인/리다이렉트 전용 등 전체 레이아웃이 없는 파일 제외
$skipFiles = ['login.php', 'logout.php', 'run_job.php', 'parse_match.php', 'promote.php', 'run_gpt_review.php', 'import_candidate_review.php', 'export_candidates.php', 'doc_ping.php'];

$files = [];
foreach (glob($adminDir . '/*.php') as $path) {
  $name = basename($path);
  if (in_array($name, $skipFiles, true)) {
    continue;
  }
  $files[$name] = $path;
}

echo "Admin UI SOT 감사 (public/admin)\n";
echo str_repeat('-', 60) . "\n";

$report = [];
foreach ($files as $name => $path) {
  $content = @file_get_contents($path);
  if ($content === false) {
    $report[$name] = ['error' => 'read failed'];
    continue;
  }
  $report[$name] = [];
  foreach ($rules as $key => $r) {
    $delim = '#';
    $ok = (bool) @preg_match($delim . $r['pattern'] . $delim, $content);
    $report[$name][$key] = $ok;
  }
}

// 요약: 규칙별 누락 파일
echo "\n[규칙별 누락 파일]\n";
foreach ($rules as $key => $r) {
  $missing = [];
  foreach ($report as $name => $checks) {
    if (isset($checks['error'])) {
      continue;
    }
    if (empty($checks[$key])) {
      $missing[] = $name;
    }
  }
  if (count($missing) > 0) {
    echo "  " . $r['desc'] . ": " . implode(', ', $missing) . "\n";
  }
}

// 파일별 상세 (누락만)
echo "\n[파일별 누락 규칙]\n";
foreach ($report as $name => $checks) {
  if (isset($checks['error'])) {
    echo "  " . $name . ": " . $checks['error'] . "\n";
    continue;
  }
  $missing = [];
  foreach ($rules as $key => $r) {
    if (empty($checks[$key])) {
      $missing[] = $r['desc'];
    }
  }
  if (count($missing) > 0) {
    echo "  " . $name . ": " . implode(' / ', $missing) . "\n";
  }
}

// 빈 메시지 비표준 패턴 (추가 점검)
echo "\n[빈 테이블 메시지 점검]\n";
$emptyMsgPatterns = ['(없음)', '(none)', 'no data'];
foreach ($files as $name => $path) {
  $content = @file_get_contents($path);
  if ($content === false) {
    continue;
  }
  foreach ($emptyMsgPatterns as $pat) {
    if (strpos($content, $pat) !== false) {
      echo "  " . $name . ": 비표준 빈 메시지 의심 '" . $pat . "' → ADMIN_QA_CHECKLIST §4 참고\n";
    }
  }
}

echo "\n감사 완료. 수정 시 docs/ui/ADMIN_UI_BATCH_FIX_GUIDE.md 참고.\n";
