# v1.7-13: PDF Parsing GATE

## 체크리스트

### 1. 스키마 (N/A)
- ❌ 이 버전에서는 DDL 변경 없음

### 2. 파일 생성/수정
- ✅ `composer.json` 생성
- ✅ `app/inc/parse/pdf_parser.php` 생성 (파싱 로직)
- ✅ `public/admin/run_job.php` 수정 (더미 → 실제 파싱)
- ✅ `.gitignore` 업데이트 (vendor/, uploads/*.pdf)
- ✅ `public/uploads/` 디렉토리 생성

### 3. Composer 설치
```powershell
cd c:\xampp\htdocs\gilime_mvp_01
composer install
```
- ✅ `vendor/smalot/pdfparser` 설치 완료

### 4. 스모크 테스트
- ✅ CLI PDF 파싱 테스트 성공
- ✅ 웹 UI PARSE_MATCH 실행 성공
- ✅ shuttle_stop_candidate에 정류장 저장 확인

### 5. Evidence (실행 결과)
```
[여기에 실제 테스트 결과를 첨부하세요]

예시:
Parsing PDF: public/uploads/test_route_r1.pdf
✓ Success!
Route Label: R1
Stops found: 3
  1. 강남역
  2. 역삼역
  3. 선릉역
```

## GATE PASS 조건
1. Composer 라이브러리 설치 완료
2. CLI 테스트 통과 (PDF → 정류장 추출)
3. 웹 UI PARSE_MATCH 성공
4. DB에 파싱 결과 저장 확인

## Known Issues
- 스캔 이미지 PDF는 지원하지 않음 (OCR 필요)
- 노선 라벨 추출 실패 시 전체 파싱 실패
- 비정형 PDF 레이아웃은 추가 패턴 작업 필요
