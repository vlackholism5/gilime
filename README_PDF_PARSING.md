# PDF Parsing 설정 및 사용 가이드

## 1. 설치 (최초 1회)

### Composer 라이브러리 설치
```powershell
cd c:\xampp\htdocs\gilime_mvp_01
composer install
```

이 명령은 `smalot/pdfparser` 라이브러리를 `vendor/` 디렉토리에 설치합니다.

**확인:**
- `c:\xampp\htdocs\gilime_mvp_01\vendor\smalot\pdfparser` 디렉토리 존재 확인

**PowerShell (선택):** 프로젝트 루트에서 `.\scripts\ps1\INSTALL_PDF_PARSING.ps1` 실행 시 Composer 확인 후 `composer install` 수행. 테스트용 텍스트 파일 생성: `.\scripts\ps1\create_test_pdf.ps1`.

---

## 2. PDF 파일 업로드

### 업로드 위치
```
c:\xampp\htdocs\gilime_mvp_01\public\uploads\
```

### PDF 파일 형식 요구사항
1. **텍스트 추출 가능한 PDF**만 지원 (Word → PDF, 웹 → PDF 등)
2. **스캔 이미지 PDF는 현재 지원하지 않음** (향후 OCR 추가 예정)

### PDF 내용 예시
```
셔틀버스 R1

정류장:
1. 강남역
2. 역삼역
3. 선릉역
4. 삼성역
```

또는

```
Route: R2

- 서울역
- 시청역
- 종각역
```

---

## 3. 사용 방법

### A. CLI에서 직접 테스트
```powershell
cd c:\xampp\htdocs\gilime_mvp_01
c:\xampp\php\php.exe app/inc/parse/pdf_parser.php public/uploads/your_file.pdf
```

**출력 예시 (성공):**
```
Parsing PDF: public/uploads/test_route_r1.pdf
------------------------------------------------------------
✓ Success!
Route Label: R1
Stops found: 3

  1. 강남역
  2. 역삼역
  3. 선릉역
```

**출력 예시 (실패):**
```
✗ Error: Could not detect route label from PDF
```

### B. 웹 UI에서 사용
1. **shuttle_source_doc에 레코드 추가**
   ```sql
   INSERT INTO shuttle_source_doc
     (file_path, ocr_status, parse_status, created_at, updated_at)
   VALUES
     ('your_file.pdf', 'not_needed', 'pending', NOW(), NOW());
   ```

2. **doc.php에서 PARSE_MATCH 실행**
   - `/admin/doc.php?id=1` 접속 (생성된 source_doc_id)
   - **"Run Parse/Match"** 버튼 클릭
   - 성공 시: `PARSE_MATCH success (rows=3), job_id=...` 메시지
   - 실패 시: `PDF parsing failed: ...` 에러 메시지

3. **결과 확인**
   ```sql
   SELECT route_label, seq_in_route, raw_stop_name,
          matched_stop_name, match_score
   FROM shuttle_stop_candidate
   WHERE source_doc_id = 1
   ORDER BY seq_in_route;
   ```

---

## 4. PDF 파싱 동작 원리

### 파싱 흐름
1. `run_job.php`가 `shuttle_source_doc.file_path` 조회
2. `parse_shuttle_pdf()` 함수가 PDF 텍스트 추출
3. **노선 라벨** 추출 (예: "R1", "셔틀버스 R2")
4. **정류장 목록** 추출 (번호, 대시, 또는 "역"으로 끝나는 줄)
5. `shuttle_stop_candidate`에 저장
6. 자동 매칭 (`matchStopFromMaster()`)으로 `seoul_bus_stop_master` 연결

### 지원하는 PDF 패턴
| 패턴 | 예시 | 설명 |
|------|------|------|
| 번호 + 점 | `1. 강남역` | 가장 일반적 |
| 대시/불릿 | `- 강남역` | Markdown 스타일 |
| 텍스트만 | `강남역` | "역" 또는 "정류장"으로 끝나는 줄 |

### 노선 라벨 추출 패턴
| 패턴 | 예시 | 추출 결과 |
|------|------|----------|
| 노선: | `노선: R1` | `R1` |
| Route: | `Route: R1` | `R1` |
| 라벨 + 노선 | `R1 노선` | `R1` |
| 셔틀버스 | `셔틀버스 R2` | `R2` |

---

## 5. 문제 해결

### "PDF contains no extractable text"
- **원인:** 스캔 이미지 PDF
- **해결:** 텍스트 추출 가능한 PDF로 변환 (OCR 기능은 향후 추가 예정)

### "Could not detect route label"
- **원인:** PDF 내용이 노선 라벨 패턴과 맞지 않음
- **해결:**
  1. PDF에 "노선: R1" 또는 "Route: R1" 추가
  2. `pdf_parser.php`의 `extract_route_label()` 함수에 새 패턴 추가

### "No stops found in PDF"
- **원인:** 정류장 목록 형식이 지원되지 않음
- **해결:**
  1. 번호 또는 대시로 정류장 나열
  2. `pdf_parser.php`의 `extract_stops_from_text()` 함수에 새 패턴 추가

### Composer 라이브러리 누락
```powershell
Fatal error: Uncaught Error: Class 'Smalot\PdfParser\Parser' not found
```
- **해결:** `composer install` 실행

---

## 6. 확장 계획
- ✅ **v1.7-13**: 기본 PDF 파싱 (텍스트 추출)
- ⏳ **향후**: Tesseract OCR 연동 (스캔 PDF 지원)
- ⏳ **향후**: 표 형식 PDF 파싱 (다단, 복잡한 레이아웃)
- ⏳ **향후**: 신뢰도 기반 자동 검증

---

## 7. 관련 파일
- `app/inc/parse/pdf_parser.php` - 파싱 로직
- `public/admin/run_job.php` - PARSE_MATCH 실행
- `scripts/php/run_parse_match_batch.php` - 실패건 배치 재처리
- `docs/releases/v1.7/specs/spec_13_pdf_parsing.md` - 스펙 문서
- `docs/releases/v1.7/smoke/smoke_13_pdf_parsing.md` - 테스트 가이드
- `docs/releases/v1.7/gate/gate_13_pdf_parsing.md` - 릴리스 게이트

---

## 8. 운영 배치 재처리 (v1.7-14)

### Dry-run
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --only_failed=1 --limit=20 --dry_run=1
```

### 실행
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --only_failed=1 --limit=20
```

### 특정 문서 1건 재실행
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --source_doc_id=123
```

출력은 JSON이며 `fail_topn`으로 실패코드 상위 집계를 확인할 수 있습니다.
