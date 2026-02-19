# v1.7-13: PDF Parsing Smoke Test

## 목표
PDF 파싱 기능이 실제로 작동하는지 확인

## 사전 조건
1. **Composer 라이브러리 설치**
   ```powershell
   cd c:\xampp\htdocs\gilime_mvp_01
   composer install
   ```
   - `vendor/smalot/pdfparser` 설치 확인

2. **테스트용 PDF 파일 준비**
   - 파일명: `test_route_r1.pdf`
   - 내용 예시:
     ```
     셔틀버스 R1
     
     1. 강남역
     2. 역삼역
     3. 선릉역
     ```
   - 저장 위치: `c:\xampp\htdocs\gilime_mvp_01\public\uploads\test_route_r1.pdf`

3. **shuttle_source_doc에 테스트 레코드 추가**
   ```sql
   INSERT INTO shuttle_source_doc
     (file_path, ocr_status, parse_status, created_at, updated_at)
   VALUES
     ('test_route_r1.pdf', 'not_needed', 'pending', NOW(), NOW());
   ```
   - 생성된 `id` 확인 (예: `id=1`)

## 테스트 절차

### 1) CLI에서 직접 PDF 파싱 테스트
```powershell
cd c:\xampp\htdocs\gilime_mvp_01
c:\xampp\php\php.exe app/inc/parse/pdf_parser.php public/uploads/test_route_r1.pdf
```

**기대 결과:**
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

### 2) 웹 UI에서 PARSE_MATCH 실행
1. `/admin/doc.php?id=1` 접속 (위에서 생성한 source_doc_id)
2. **"Run Parse/Match"** 버튼 클릭
3. 리다이렉트 후 flash 메시지 확인:
   - 성공: `PARSE_MATCH success (rows=3), job_id=...`
   - 실패: `PDF parsing failed: ...`

### 3) DB 확인
```sql
-- shuttle_stop_candidate에 정류장 3개 생성 확인
SELECT id, route_label, seq_in_route, raw_stop_name,
       matched_stop_id, matched_stop_name, match_score, match_method
FROM shuttle_stop_candidate
WHERE source_doc_id = 1
ORDER BY seq_in_route;
```

**기대 결과:**
- 3개 row (강남역, 역삼역, 선릉역)
- `route_label = 'R1'`
- `match_score`, `match_method` 값 존재 (자동매칭 성공 시)

## PASS 조건
- CLI 테스트에서 ✓ Success 출력
- 웹 UI에서 "PARSE_MATCH success" 메시지
- shuttle_stop_candidate에 파싱된 정류장 3개 저장됨
