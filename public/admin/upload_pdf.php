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
  <title>Admin - Upload PDF</title>
  <style>
    body{font-family:system-ui, -apple-system, Segoe UI, Roboto, sans-serif; padding:24px;}
    a{color:#0b57d0;text-decoration:none;}
    .top{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;}
    .box{max-width:760px;border:1px solid #e7e7e7;border-radius:12px;padding:16px;background:#fff;}
    .row{margin:10px 0;}
    .muted{color:#666;font-size:12px;}
    .flash{margin:12px 0;padding:10px 12px;border:1px solid #ddd;border-radius:10px;}
    .ok{background:#f6ffed;border-color:#b7eb8f;}
    .err{background:#fff2f0;border-color:#ffccc7;}
    .btn{display:inline-block;border:1px solid #d9d9d9;border-radius:8px;padding:8px 12px;background:#fff;cursor:pointer;}
  </style>
</head>
<body>
  <div class="top">
    <a href="<?= APP_BASE ?>/admin/index.php">← Back</a>
    <a href="<?= APP_BASE ?>/admin/logout.php">Logout</a>
  </div>

  <h2>Upload PDF (v1.7-17 minimal ingest)</h2>
  <p class="muted">업로드 → source_doc 생성 → doc 화면에서 Run Parse/Match 실행. OCR/워커는 이번 범위에 포함하지 않습니다.</p>

  <?php if ($flash !== null): ?>
    <div class="flash <?= $flashType === 'ok' ? 'ok' : 'err' ?>"><?= h($flash) ?></div>
  <?php endif; ?>

  <div class="box">
    <form method="post" enctype="multipart/form-data">
      <div class="row">
        <label for="pdf_file">PDF 파일 선택</label><br />
        <input id="pdf_file" type="file" name="pdf_file" accept=".pdf,application/pdf" required />
      </div>
      <div class="row">
        <button class="btn" type="submit">Upload</button>
        <span class="muted" style="margin-left:8px;">허용: .pdf, 최대 10MB</span>
      </div>
    </form>

    <?php if ($newDocId > 0): ?>
      <hr style="margin:16px 0;border:none;border-top:1px solid #eee;" />
      <p><strong>source_doc_id:</strong> <?= (int)$newDocId ?></p>
      <p><strong>saved_file:</strong> <?= h($savedFile) ?></p>
      <p>
        <a class="btn" href="<?= APP_BASE ?>/admin/doc.php?id=<?= (int)$newDocId ?>">Doc 상세 보기</a>
      </p>
      <form method="post" action="<?= APP_BASE ?>/admin/run_job.php" style="margin-top:8px;">
        <input type="hidden" name="source_doc_id" value="<?= (int)$newDocId ?>" />
        <button class="btn" type="submit">Run Parse/Match now</button>
      </form>
    <?php endif; ?>
  </div>
</body>
</html>

