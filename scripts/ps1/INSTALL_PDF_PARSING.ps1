# PDF Parsing 설치 스크립트
# 실행 (프로젝트 루트에서): .\scripts\ps1\INSTALL_PDF_PARSING.ps1

Write-Host "=== PDF Parsing 설치 시작 ===" -ForegroundColor Green
Write-Host ""

$projectRoot = "c:\xampp\htdocs\gilime_mvp_01"
Set-Location $projectRoot

# 1. Composer 설치 확인
Write-Host "[1/3] Composer 확인 중..." -ForegroundColor Yellow
$composerPath = Get-Command composer -ErrorAction SilentlyContinue
if (-not $composerPath) {
    Write-Host "  ✗ Composer가 설치되어 있지 않습니다." -ForegroundColor Red
    Write-Host "  다운로드: https://getcomposer.org/" -ForegroundColor Cyan
    exit 1
}
Write-Host "  ✓ Composer 발견: $($composerPath.Source)" -ForegroundColor Green

# 2. Composer install 실행
Write-Host ""
Write-Host "[2/3] PHP 라이브러리 설치 중..." -ForegroundColor Yellow
composer install
if ($LASTEXITCODE -ne 0) {
    Write-Host "  ✗ composer install 실패" -ForegroundColor Red
    exit 1
}
Write-Host "  ✓ smalot/pdfparser 설치 완료" -ForegroundColor Green

# 3. vendor 디렉토리 확인
Write-Host ""
Write-Host "[3/3] 설치 검증 중..." -ForegroundColor Yellow
if (Test-Path "$projectRoot\vendor\smalot\pdfparser") {
    Write-Host "  ✓ vendor\smalot\pdfparser 확인 완료" -ForegroundColor Green
} else {
    Write-Host "  ✗ vendor\smalot\pdfparser 없음" -ForegroundColor Red
    exit 1
}

# 완료
Write-Host ""
Write-Host "=== 설치 완료! ===" -ForegroundColor Green
Write-Host ""
Write-Host "다음 단계:" -ForegroundColor Cyan
Write-Host "  1. PDF 파일을 public\uploads\ 에 업로드"
Write-Host "  2. shuttle_source_doc에 레코드 추가 (file_path='파일명.pdf')"
Write-Host "  3. /admin/doc.php?id=... 에서 'Run Parse/Match' 실행"
Write-Host ""
Write-Host "자세한 가이드: README_PDF_PARSING.md" -ForegroundColor Yellow
