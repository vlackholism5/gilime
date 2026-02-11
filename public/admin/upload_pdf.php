<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/inc/auth.php';
require_admin();

$pdo = pdo();
$flash = null;
$flashType = 'ok';
$newDocId = 0;
$savedFile = '';

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (!isset($_FILES['pdf_file'])) {
      throw new RuntimeException('파일이 전송되지 않았습니다.');
    }
    $f = $_FILES['pdf_file'];
    if (!is_array($f) || (int)($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      throw new RuntimeException('업로드 실패: error=' . (string)($f['error'] ?? 'unknown'));
    }

    $origName = (string)($f['name'] ?? '');
    $tmpName = (string)($f['tmp_name'] ?? '');
    $size = (int)($f['size'] ?? 0);
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

    $uploadsDir = realpath(__DIR__ . '/../uploads');
    if ($uploadsDir === false) {
      $uploadsDir = __DIR__ . '/../uploads';
      if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
        throw new RuntimeException('uploads 디렉토리를 생성할 수 없습니다.');
      }
      $uploadsDir = realpath($uploadsDir);
      if ($uploadsDir === false) throw new RuntimeException('uploads 경로 확인 실패');
    }

    $base = preg_replace('/[^A-Za-z0-9._-]/', '_', pathinfo($origName, PATHINFO_FILENAME));
    $base = trim((string)$base, '._-');
    if ($base === '') $base = 'upload';
    $safeName = 'ingest_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '_' . $base . '.pdf';
    $destPath = rtrim($uploadsDir, '/\\') . DIRECTORY_SEPARATOR . $safeName;

    if (!move_uploaded_file($tmpName, $destPath)) {
      throw new RuntimeException('파일 저장 실패');
    }

    $cols = getTableColumns($pdo, 'shuttle_source_doc');
    $insertable = [];
    $params = [];

    $setIfExists = function (string $col, $val) use (&$insertable, &$params, $cols): void {
      if (isset($cols[$col])) {
        $insertable[] = $col;
        $params[":{$col}"] = $val;
      }
    };

    $setIfExists('source_name', 'manual_upload');
    $setIfExists('title', $origName !== '' ? $origName : $safeName);
    $setIfExists('file_path', $safeName);
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

    $newDocId = (int)$pdo->lastInsertId();
    $savedFile = $safeName;
    $flash = "업로드 완료: {$safeName} / source_doc_id={$newDocId}";
    $flashType = 'ok';
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
  <div class="top d-flex justify-content-between align-items-center mb-3">
    <a href="<?= APP_BASE ?>/admin/index.php">← 뒤로</a>
    <a href="<?= APP_BASE ?>/admin/logout.php">로그아웃</a>
  </div>

  <div class="g-page-head mb-3">
    <h2>PDF 업로드 (v1.7-17 최소 ingest)</h2>
    <p class="helper mb-0">업로드 → source_doc 생성 → doc 화면에서 Run Parse/Match 실행. OCR/워커는 이번 범위에 포함하지 않습니다.</p>
  </div>

  <?php if ($flash !== null): ?>
    <div class="alert <?= $flashType === 'ok' ? 'alert-success' : 'alert-danger' ?> py-2"><?= h($flash) ?></div>
  <?php endif; ?>

  <div class="card g-card g-max-760">
    <div class="card-body">
    <form method="post" enctype="multipart/form-data">
      <div class="mb-3">
        <label class="form-label" for="pdf_file">PDF 파일 선택</label>
        <input id="pdf_file" class="form-control" type="file" name="pdf_file" accept=".pdf,application/pdf" required />
      </div>
      <div class="d-flex align-items-center gap-2">
        <button class="btn btn-gilaime-primary" type="submit">Upload</button>
        <span class="text-muted-g small">허용: .pdf, 최대 10MB</span>
      </div>
    </form>

    <?php if ($newDocId > 0): ?>
      <hr class="my-3" />
      <p><strong>source_doc_id:</strong> <?= (int)$newDocId ?></p>
      <p><strong>saved_file:</strong> <?= h($savedFile) ?></p>
      <p>
        <a class="btn btn-outline-secondary" href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$newDocId ?>">Doc 상세 보기</a>
      </p>
      <form method="post" action="<?= APP_BASE ?>/admin/run_job.php" class="mt-2">
        <input type="hidden" name="source_doc_id" value="<?= (int)$newDocId ?>" />
        <button class="btn btn-gilaime-primary" type="submit">Run Parse/Match now</button>
      </form>
    <?php endif; ?>
    </div>
  </div>
  </main>
</body>
</html>

