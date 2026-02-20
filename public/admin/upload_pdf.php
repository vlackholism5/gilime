<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth/auth.php';
require_once __DIR__ . '/../../app/inc/admin/admin_header.php';
require_admin();

$pdo = pdo();
$flash = null;
$flashType = 'ok';
$newDocId = 0;
$savedFile = '';
$batchResults = [];
$batchParseResults = [];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

/** @return array<string,bool> */
function getTableColumns(PDO $pdo, string $table): array {
  $stmt = $pdo->prepare("
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = :table
  ");
  $stmt->execute([':table' => $table]);
  $cols = [];
  foreach ($stmt->fetchAll() as $r) {
    $cols[(string)$r['COLUMN_NAME']] = true;
  }
  return $cols;
}

function ensureUploadsDir(): string {
  $uploadsDir = realpath(__DIR__ . '/../uploads');
  if ($uploadsDir !== false) return $uploadsDir;
  $uploadsDir = __DIR__ . '/../uploads';
  if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
    throw new RuntimeException('uploads 디렉토리를 생성할 수 없습니다.');
  }
  $resolved = realpath($uploadsDir);
  if ($resolved === false) throw new RuntimeException('uploads 경로 확인 실패');
  return $resolved;
}

function buildSafePdfFileName(string $origName): string {
  $base = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
  $base = trim((string)$base, '._-');
  if ($base === '') $base = 'upload';
  return 'ingest_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $base . '.pdf';
}

function insertSourceDoc(PDO $pdo, array $cols, string $title, string $savedFile): int {
  $insertable = [];
  $params = [];

  $setIfExists = function (string $col, $val) use (&$insertable, &$params, $cols): void {
    if (isset($cols[$col])) {
      $insertable[] = $col;
      $params[":{$col}"] = $val;
    }
  };

  $setIfExists('source_name', 'manual_upload');
  $setIfExists('title', $title);
  $setIfExists('file_path', $savedFile);
  $setIfExists('ocr_status', 'new');
  $setIfExists('parse_status', 'new');
  $setIfExists('validation_status', 'pending');

  if ($insertable === []) {
    throw new RuntimeException('shuttle_source_doc에 insert 가능한 컬럼을 찾을 수 없습니다.');
  }

  $colsSql = implode(', ', $insertable);
  $valsSql = implode(', ', array_map(fn($c) => ':' . $c, $insertable));
  $sql = "INSERT INTO shuttle_source_doc ({$colsSql}, created_at, updated_at) VALUES ({$valsSql}, NOW(), NOW())";
  if (!isset($cols['created_at']) || !isset($cols['updated_at'])) {
    $sql = "INSERT INTO shuttle_source_doc ({$colsSql}) VALUES ({$valsSql})";
  }

  $st = $pdo->prepare($sql);
  $st->execute($params);
  return (int)$pdo->lastInsertId();
}

/**
 * @return array{ok:bool,error_code:string,message:string,raw:string}
 */
function runBatchParseForDoc(int $docId): array {
  $script = realpath(__DIR__ . '/../../scripts/php/run_parse_match_batch.php');
  if ($script === false || !is_file($script)) {
    return [
      'ok' => false,
      'error_code' => 'BATCH_SCRIPT_NOT_FOUND',
      'message' => '배치 스크립트를 찾을 수 없습니다.',
      'raw' => '',
    ];
  }
  $phpBin = PHP_BINARY;
  if ($phpBin === '' || !is_file($phpBin)) {
    return [
      'ok' => false,
      'error_code' => 'PHP_BINARY_NOT_FOUND',
      'message' => 'PHP 실행 경로를 확인할 수 없습니다.',
      'raw' => '',
    ];
  }
  $cmd = escapeshellarg($phpBin) . ' ' . escapeshellarg($script) . ' --source_doc_id=' . (int)$docId;
  $output = [];
  $exitCode = 1;
  exec($cmd, $output, $exitCode);

  $raw = trim(implode("\n", $output));
  if ($exitCode !== 0) {
    return [
      'ok' => false,
      'error_code' => 'BATCH_EXEC_EXIT_NONZERO',
      'message' => '배치 실행 실패(exit=' . $exitCode . ')',
      'raw' => $raw,
    ];
  }

  $jsonLine = '';
  for ($i = count($output) - 1; $i >= 0; $i--) {
    $line = trim((string)$output[$i]);
    if ($line !== '' && str_starts_with($line, '{')) {
      $jsonLine = $line;
      break;
    }
  }
  if ($jsonLine === '') {
    return [
      'ok' => false,
      'error_code' => 'BATCH_JSON_NOT_FOUND',
      'message' => '배치 결과(JSON)를 해석하지 못했습니다.',
      'raw' => $raw,
    ];
  }

  $decoded = json_decode($jsonLine, true);
  if (!is_array($decoded)) {
    return [
      'ok' => false,
      'error_code' => 'BATCH_JSON_INVALID',
      'message' => '배치 결과(JSON 형식)가 올바르지 않습니다.',
      'raw' => $raw,
    ];
  }

  $success = (int)($decoded['success'] ?? 0);
  $failed = (int)($decoded['failed'] ?? 0);
  if ($success > 0 && $failed === 0) {
    return [
      'ok' => true,
      'error_code' => '',
      'message' => '파싱/매칭 성공',
      'raw' => $raw,
    ];
  }

  $failCode = 'BATCH_DOC_FAILED';
  $failedDetails = $decoded['failed_details'] ?? [];
  if (is_array($failedDetails) && isset($failedDetails[0]['error_code'])) {
    $failCode = (string)$failedDetails[0]['error_code'];
  }
  return [
    'ok' => false,
    'error_code' => $failCode,
    'message' => '파싱/매칭 실패',
    'raw' => $raw,
  ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    $action = (string)($_POST['action'] ?? 'upload');
    if ($action === 'run_batch_parse') {
      @set_time_limit(300);
      $idsRaw = $_POST['source_doc_ids'] ?? [];
      if (!is_array($idsRaw) || $idsRaw === []) {
        throw new RuntimeException('일괄 실행할 source_doc_id가 없습니다.');
      }

      $docIds = [];
      foreach ($idsRaw as $v) {
        $id = (int)$v;
        if ($id > 0) $docIds[$id] = true;
      }
      $docIdList = array_keys($docIds);
      if ($docIdList === []) {
        throw new RuntimeException('유효한 source_doc_id가 없습니다.');
      }
      if (count($docIdList) > 300) {
        throw new RuntimeException('일괄 실행은 최대 300건까지 허용됩니다.');
      }

      $successCount = 0;
      $failCount = 0;
      foreach ($docIdList as $docId) {
        $ret = runBatchParseForDoc((int)$docId);
        $batchParseResults[] = [
          'doc_id' => (int)$docId,
          'status' => $ret['ok'] ? 'success' : 'failed',
          'error_code' => (string)$ret['error_code'],
          'message' => (string)$ret['message'],
          'raw' => (string)$ret['raw'],
        ];
        if ($ret['ok']) $successCount++;
        else $failCount++;
      }

      $flash = "일괄 파싱/매칭 완료: 대상 " . count($docIdList) . "건 / 성공 {$successCount}건 / 실패 {$failCount}건";
      $flashType = $failCount > 0 ? 'err' : 'ok';
    } else {
      $cols = getTableColumns($pdo, 'shuttle_source_doc');
      $uploadsDir = ensureUploadsDir();

      $zipFile = $_FILES['zip_file'] ?? null;
      $pdfFile = $_FILES['pdf_file'] ?? null;
      $zipOk = is_array($zipFile) && (int)($zipFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
      $pdfOk = is_array($pdfFile) && (int)($pdfFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

      if ($zipOk) {
        if (!class_exists('ZipArchive')) {
          throw new RuntimeException('ZIP 처리를 위한 ZipArchive 확장이 필요합니다.');
        }

        $origZipName = (string)($zipFile['name'] ?? '');
        $tmpZip = (string)($zipFile['tmp_name'] ?? '');
        $zipSize = (int)($zipFile['size'] ?? 0);
        $zipExt = strtolower((string)pathinfo($origZipName, PATHINFO_EXTENSION));
        if ($zipExt !== 'zip') {
          throw new RuntimeException('ZIP 일괄 업로드는 .zip 파일만 허용됩니다.');
        }
        if ($zipSize <= 0 || $zipSize > 50 * 1024 * 1024) {
          throw new RuntimeException('ZIP 파일 크기는 50MB 이하만 허용됩니다.');
        }
        if (!is_uploaded_file($tmpZip)) {
          throw new RuntimeException('유효하지 않은 ZIP 업로드 파일입니다.');
        }

        $zip = new ZipArchive();
        if ($zip->open($tmpZip) !== true) {
          throw new RuntimeException('ZIP 파일 열기 실패');
        }

        $pdfEntryCount = 0;
        $successCount = 0;
        $failCount = 0;
        $maxEntry = min($zip->numFiles, 300);

        for ($i = 0; $i < $maxEntry; $i++) {
          $entryName = (string)$zip->getNameIndex($i);
          if ($entryName === '' || str_ends_with($entryName, '/')) {
            continue;
          }
          $entryExt = strtolower((string)pathinfo($entryName, PATHINFO_EXTENSION));
          if ($entryExt !== 'pdf') {
            continue;
          }
          $pdfEntryCount++;

          try {
            $stream = $zip->getStream($entryName);
            if ($stream === false) {
              throw new RuntimeException('ZIP 내부 파일 스트림 열기 실패');
            }

            $safeName = buildSafePdfFileName((string)basename($entryName));
            $destPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $safeName;
            $out = fopen($destPath, 'wb');
            if ($out === false) {
              fclose($stream);
              throw new RuntimeException('저장 파일 열기 실패');
            }
            stream_copy_to_stream($stream, $out);
            fclose($stream);
            fclose($out);

            $title = (string)basename($entryName);
            $docId = insertSourceDoc($pdo, $cols, $title !== '' ? $title : $safeName, $safeName);
            $batchResults[] = [
              'name' => $entryName,
              'status' => 'success',
              'doc_id' => $docId,
              'saved_file' => $safeName,
              'message' => '등록 완료',
            ];
            $successCount++;
          } catch (Throwable $e) {
            $batchResults[] = [
              'name' => $entryName,
              'status' => 'failed',
              'doc_id' => 0,
              'saved_file' => '',
              'message' => mb_substr($e->getMessage(), 0, 180),
            ];
            $failCount++;
          }
        }
        $zip->close();

        if ($pdfEntryCount === 0) {
          throw new RuntimeException('ZIP 내부에 PDF 파일이 없습니다.');
        }
        $flash = "ZIP 처리 완료: PDF {$pdfEntryCount}건 / 성공 {$successCount}건 / 실패 {$failCount}건";
        $flashType = $failCount > 0 ? 'err' : 'ok';
      } elseif ($pdfOk) {
        $origName = (string)($pdfFile['name'] ?? '');
        $tmpName = (string)($pdfFile['tmp_name'] ?? '');
        $size = (int)($pdfFile['size'] ?? 0);
        $ext = strtolower((string)pathinfo($origName, PATHINFO_EXTENSION));
        if ($ext !== 'pdf') {
          throw new RuntimeException('PDF 파일만 업로드 가능합니다.');
        }
        if ($size <= 0 || $size > 10 * 1024 * 1024) {
          throw new RuntimeException('파일 크기는 10MB 이하만 허용됩니다.');
        }
        if (!is_uploaded_file($tmpName)) {
          throw new RuntimeException('유효하지 않은 업로드 파일입니다.');
        }

        $safeName = buildSafePdfFileName($origName);
        $destPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $safeName;
        if (!move_uploaded_file($tmpName, $destPath)) {
          throw new RuntimeException('파일 저장 실패');
        }

        $newDocId = insertSourceDoc($pdo, $cols, $origName !== '' ? $origName : $safeName, $safeName);
        $savedFile = $safeName;
        $flash = "업로드 완료: {$safeName} / source_doc_id={$newDocId}";
        $flashType = 'ok';
      } else {
        throw new RuntimeException('업로드할 PDF 또는 ZIP 파일을 선택해 주세요.');
      }
    }
  } catch (Throwable $e) {
    $flash = '업로드 실패: ' . mb_substr($e->getMessage(), 0, 240);
    $flashType = 'err';
  }
}
?>
<!doctype html>
<html lang="ko">
<head>
  <meta charset="utf-8" />
  <title>관리자 - PDF 업로드</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="<?= APP_BASE ?>/public/assets/css/gilaime_ui.css" />
</head>
<body class="gilaime-app">
  <main class="container-fluid py-4">
  <?php render_admin_nav(); ?>
  <?php render_admin_header([
    ['label' => '문서 허브', 'url' => 'index.php'],
    ['label' => 'PDF 업로드', 'url' => null],
  ], false); ?>

  <div class="g-page-head">
    <h2>PDF 업로드 (v1.7-17 최소 수집)</h2>
    <p class="helper mb-0">업로드 → source_doc 생성 → 문서 화면에서 파싱/매칭 실행. 저장 위치: <code>public/uploads</code></p>
  </div>

  <?php if ($flash !== null): ?>
    <div class="alert <?= $flashType === 'ok' ? 'alert-success' : 'alert-danger' ?> py-2"><?= h($flash) ?></div>
  <?php endif; ?>

  <div class="card g-card g-max-760 mb-3">
    <div class="card-body">
    <form method="post" enctype="multipart/form-data" data-loading-msg="PDF 업로드 중... 잠시만 기다려 주세요.">
      <div class="mb-3">
        <label class="form-label" for="pdf_file">PDF 파일 선택</label>
        <input id="pdf_file" class="form-control" type="file" name="pdf_file" accept=".pdf,application/pdf" />
      </div>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-gilaime-primary" type="submit">업로드</button>
        <span class="text-muted-g small">허용: .pdf, 최대 10MB</span>
      </div>
    </form>

    <?php if ($newDocId > 0): ?>
      <hr class="my-3" />
      <p><strong>source_doc_id:</strong> <?= (int)$newDocId ?></p>
      <p><strong>저장 파일:</strong> <?= h($savedFile) ?></p>
      <p>
        <a class="btn btn-outline-secondary" href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$newDocId ?>">파싱·매칭 현황 보기</a>
      </p>
      <form method="post" action="<?= APP_BASE ?>/admin/run_job.php" class="mt-2" data-loading-msg="파싱/매칭 처리 중... 잠시만 기다려 주세요.">
        <input type="hidden" name="source_doc_id" value="<?= (int)$newDocId ?>" />
        <button class="btn btn-gilaime-primary" type="submit">지금 파싱/매칭 실행</button>
      </form>
    <?php endif; ?>
    </div>
  </div>

  <div class="card g-card g-max-760">
    <div class="card-body">
      <h3 class="h5 mb-3">ZIP 일괄 업로드</h3>
      <form method="post" enctype="multipart/form-data" data-loading-msg="ZIP 처리 중... 잠시만 기다려 주세요.">
        <div class="mb-3">
          <label class="form-label" for="zip_file">ZIP 파일 선택</label>
          <input id="zip_file" class="form-control" type="file" name="zip_file" accept=".zip,application/zip" />
          <div class="form-text">ZIP 내부 PDF를 각각 source_doc으로 등록합니다. (최대 300개 처리)</div>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button class="btn btn-outline-secondary" type="submit">ZIP 처리 실행</button>
          <span class="text-muted-g small">허용: .zip, 최대 50MB</span>
        </div>
      </form>
    </div>
  </div>

  <?php if ($batchResults !== []): ?>
  <div class="card g-card mt-3">
    <div class="card-body">
      <h3 class="h5 mb-3">ZIP 처리 결과</h3>
      <div class="table-responsive">
      <table class="table table-hover align-middle g-table g-table-dense mb-0">
        <thead><tr><th>원본 항목</th><th>결과</th><th>source_doc_id</th><th>저장 파일</th><th>메시지</th></tr></thead>
        <tbody>
          <?php foreach ($batchResults as $r): ?>
          <tr>
            <td><?= h((string)$r['name']) ?></td>
            <td><?= $r['status'] === 'success' ? '성공' : '실패' ?></td>
            <td>
              <?php if ((int)$r['doc_id'] > 0): ?>
                <a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['doc_id'] ?>"><?= (int)$r['doc_id'] ?></a>
              <?php else: ?>
                —
              <?php endif; ?>
            </td>
            <td><?= h((string)$r['saved_file']) ?></td>
            <td><?= h((string)$r['message']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>

      <?php
      $successDocIds = [];
      foreach ($batchResults as $r) {
        if (($r['status'] ?? '') === 'success' && (int)($r['doc_id'] ?? 0) > 0) {
          $successDocIds[] = (int)$r['doc_id'];
        }
      }
      ?>
      <?php if ($successDocIds !== []): ?>
      <form method="post" class="mt-3" data-loading-msg="일괄 파싱/매칭 처리 중... 잠시만 기다려 주세요.">
        <input type="hidden" name="action" value="run_batch_parse" />
        <?php foreach ($successDocIds as $sid): ?>
          <input type="hidden" name="source_doc_ids[]" value="<?= (int)$sid ?>" />
        <?php endforeach; ?>
        <div class="d-flex align-items-center gap-2">
          <button type="submit" class="btn btn-gilaime-primary">성공 문서 일괄 파싱/매칭 실행</button>
          <span class="text-muted-g small"><?= count($successDocIds) ?>건 대상</span>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($batchParseResults !== []): ?>
  <div class="card g-card mt-3">
    <div class="card-body">
      <h3 class="h5 mb-3">일괄 파싱/매칭 결과</h3>
      <div class="table-responsive">
      <table class="table table-hover align-middle g-table g-table-dense mb-0">
        <thead><tr><th>source_doc_id</th><th>결과</th><th>오류 코드</th><th>메시지</th><th>문서 이동</th></tr></thead>
        <tbody>
          <?php foreach ($batchParseResults as $r): ?>
          <tr>
            <td><?= (int)$r['doc_id'] ?></td>
            <td><?= $r['status'] === 'success' ? '성공' : '실패' ?></td>
            <td><?= h((string)$r['error_code']) ?></td>
            <td><?= h((string)$r['message']) ?></td>
            <td><a href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$r['doc_id'] ?>">문서 보기</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
  </main>
  <?php render_admin_tutorial_modal(); ?>

  <div id="g-loading-overlay" class="g-loading-overlay" hidden aria-live="polite">
    <div class="g-loading-spinner" aria-hidden="true"></div>
    <span id="g-loading-msg">처리 중...</span>
  </div>
  <script>
  (function(){
    var overlay = document.getElementById('g-loading-overlay');
    var msgEl = document.getElementById('g-loading-msg');
    document.querySelectorAll('form[data-loading-msg]').forEach(function(f){
      f.addEventListener('submit', function(){
        var msg = f.getAttribute('data-loading-msg') || '처리 중...';
        if (msgEl) msgEl.textContent = msg;
        overlay.removeAttribute('hidden');
        var btn = f.querySelector('button[type="submit"]');
        if (btn) { btn.disabled = true; btn.textContent = '처리 중...'; }
      });
    });
  })();
  </script>
</body>
</html>

