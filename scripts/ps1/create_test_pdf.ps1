# 테스트용 PDF 생성 스크립트 (텍스트 → PDF 변환)
# 실행 (프로젝트 루트에서): .\scripts\ps1\create_test_pdf.ps1

Write-Host "=== 테스트 PDF 생성 ===" -ForegroundColor Green
Write-Host ""
Write-Host "주의: 이 스크립트는 텍스트 파일만 생성합니다." -ForegroundColor Yellow
Write-Host "      실제 PDF 생성은 Word, 온라인 도구 등을 사용하세요." -ForegroundColor Yellow
Write-Host ""

$uploadsDir = "c:\xampp\htdocs\gilime_mvp_01\public\uploads"
$txtFile = "$uploadsDir\test_route_r1.txt"

# 테스트용 텍스트 파일 생성
$content = @"
셔틀버스 R1

정류장 목록:

1. 강남역
2. 역삼역
3. 선릉역
4. 삼성역
5. 종합운동장역

"@

$content | Out-File -FilePath $txtFile -Encoding UTF8

Write-Host "✓ 텍스트 파일 생성: $txtFile" -ForegroundColor Green
Write-Host ""
Write-Host "다음 단계:" -ForegroundColor Cyan
Write-Host "  1. 위 파일을 Word나 온라인 도구로 PDF 변환"
Write-Host "  2. test_route_r1.pdf로 저장 (같은 폴더)"
Write-Host "  3. CLI 테스트: php app/inc/parse/pdf_parser.php public/uploads/test_route_r1.pdf"
Write-Host ""
Write-Host "온라인 변환 도구:" -ForegroundColor Yellow
Write-Host "  - https://www.ilovepdf.com/txt_to_pdf"
Write-Host "  - https://smallpdf.com/txt-to-pdf"
