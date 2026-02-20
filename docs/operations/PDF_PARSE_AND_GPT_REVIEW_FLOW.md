# PDF 파싱 ↔ GPT 검수 흐름 및 장애 지점

관리자 화면에서 **파싱이 안 되거나**, **GPT 검수가 안 되는** 경우 참고용으로, 구현된 흐름과 실패 원인을 정리한 문서입니다.

---

## 1. 전체 흐름 (End-to-End)

```
[PDF 업로드] → shuttle_source_doc 등록 (file_path, ocr_status 등)
       ↓
[문서 상세 doc.php] → "파싱·매칭 실행" 클릭 → run_job.php (PARSE_MATCH)
       ↓
[run_job.php]
  - pdf_parser.php 의 parse_shuttle_pdf(파일경로) 호출
  - 여기서 PDF 텍스트 추출 (아래 2절)
  - 추출된 노선 라벨 + 정류장 목록 → shuttle_stop_candidate INSERT (created_job_id = PARSE_MATCH job_id)
  - job_log: PARSE_MATCH success / failed 기록
       ↓
[노선 검수 route_review.php] → 후보 목록 표시 (latest PARSE_MATCH job 기준)
       ↓
[GPT 검수 실행] → run_gpt_review.php
  - 전제: PARSE_MATCH 가 이미 success 이고, 해당 job_id 로 후보가 있어야 함
  - 후보 JSON → Python gpt_review_pipeline.py 실행 → 결과 DB 반영 (approve/reject)
```

**정리:**  
- **PDF에서 텍스트를 뽑는 것(디지털 + OCR)** 은 **파싱(PARSE_MATCH)** 단계 안에 들어 있습니다.  
- **GPT 검수** 는 “이미 파싱되어 DB에 들어온 후보”를 GPT API로 검수하는 단계라, **파싱이 성공한 뒤**에만 동작합니다.

---

## 2. PDF 텍스트 추출이 어떻게 구현되어 있는가 (OCR 위치)

- **파일:** `app/inc/parse/pdf_parser.php`
- **진입:** `run_job.php` → `parse_shuttle_pdf($absolutePath)` 호출

### 2.1 순서 (파서 내부)

1. **디지털 텍스트 시도**  
   - `smalot/pdfparser` 로 PDF 열고 `$pdf->getText()` 호출  
   - 텍스트가 있으면 → 그대로 노선 라벨·정류장 추출로 진행

2. **텍스트가 비어 있으면 → Python OCR 경로**  
   - `run_ocr_extract($filePath)` 호출  
   - 내부에서 **`scripts/python/extract_text.py`** 를 `exec()` 로 실행  
   - 인자: `--input-file <PDF경로>` , `--output <임시 txt>` , `--output-format text_only` , `--lang kor+eng`  
   - Tesseract 경로는 `OCR_TESSERACT_CMD` (config.local.php 등) 로 전달 가능  
   - OCR 이 성공하면 추출된 텍스트를 반환 → **같은 파서**가 그 텍스트로 `extract_route_label()` / `extract_stops_from_text()` 호출 (노선·정류장 추출)

3. **OCR 까지 실패하면**  
   - `error_code = PARSE_OCR_FAILED`  
   - "PDF contains no extractable text. OCR failed or not configured."  
   - run_job.php 에서 파싱 실패로 처리되고, **PARSE_MATCH job 은 failed** 로 남음

즉, **“PDF에서 텍스트만 추출하는 파이썬 OCR”은 “파싱” 과정 안에 배치**되어 있고,  
**디지털 텍스트가 없을 때만** `extract_text.py` 가 실행되도록 되어 있습니다.

### 2.2 OCR 관련 설정

- `OCR_PYTHON_CMD`: Python 실행어 (기본 `python`, Windows 에서는 `py` 또는 풀경로 가능)
- `OCR_TESSERACT_CMD`: Tesseract 실행 파일 경로 (예: `C:\Program Files\Tesseract-OCR\tesseract.exe`)
- `scripts/python/extract_text.py` 존재 여부 및 의존성 (pypdf2, pytesseract, opencv 등)  
  → `scripts/python/README.md`, `docs/operations/PDF_OCR_SETUP_v1_7.md` 참고

---

## 3. 파싱이 “제대로 안 된다”고 느껴질 때

- **doc.php** 에서 해당 문서의 **파싱 상태(parse_status)** 와 **last_parse_error_code** 를 확인하세요.
- run_job.php / pdf_parser.php 는 실패 시 `error_code` 를 남깁니다 (job_log.result_note 또는 doc.php 표시).

| 상황 | 가능 원인 | 확인 방법 |
|------|-----------|-----------|
| parse_status = failed, error_code 없음 | job_log 에 result_note 미기록 등 이슈 | doc.php 에서 “최근 PARSE_MATCH 실패” 로그 확인, B2 Build(doc.php error_code 표시 보강) 적용 여부 |
| PARSE_FILE_NOT_FOUND | DB의 file_path 와 실제 파일 위치 불일치 | uploads 루트와 file_path 값 비교 |
| PARSE_NO_TEXT / PARSE_OCR_FAILED | 디지털 텍스트 없음 + OCR 실패 | Python/Tesseract 설치, `extract_text.py` 수동 실행, config OCR_* 설정 |
| PARSE_NO_ROUTE / PARSE_NO_STOPS | 텍스트는 나왔지만 노선/정류장 패턴 매칭 실패 | PDF 레이아웃·표 형식이 extract_route_label / extract_stops_from_text 패턴과 맞는지 확인 |

---

## 4. GPT 검수가 “안 된다”고 느껴질 때

- **run_gpt_review.php** 는 **“이미 PARSE_MATCH 가 success 인 문서 + 해당 노선의 후보가 있을 때”** 만 의미 있게 동작합니다.

| 화면/메시지 | 의미 |
|-------------|------|
| "PARSE_MATCH success job이 없습니다." | 해당 문서에 성공한 파싱 작업이 없음 → **먼저 파싱·매칭 실행** 필요 |
| "검수할 후보가 없습니다." | 해당 (source_doc_id, route_label) 에 대해 created_job_id=latest PARSE_MATCH 인 후보가 0건 |
| "GPT 검수 사용 시 config.local.php에 GPT_OPENAPI_API_KEY 설정이 필요합니다." | API 키 미설정 |
| "gpt_review_pipeline.py 스크립트를 찾을 수 없습니다." | `scripts/python/gpt_review_pipeline.py` 없음 또는 경로 오류 |
| "GPT 검수 실행 실패: ..." | Python 실행 실패 (exec 반환코드 ≠ 0). 출력 앞 3줄이 플래시에 포함됨 → 터미널에서 동일 인자로 수동 실행해 보면 원인 파악에 도움 |

정리하면, **파싱이 실패하거나 후보가 없으면 GPT 검수는 설계상 동작하지 않습니다.**  
그래서 “GPT 검수도 안 되고 파싱도 제대로 안 되는 것 같다”면, **먼저 파싱 단계**를 확인하는 것이 좋습니다.

---

## 5. 요약

- **PDF 텍스트 추출(디지털 + Python OCR)** 은 **파싱(PARSE_MATCH)** 안에 들어 있고,  
  `pdf_parser.php` → `getText()` 실패 시 `run_ocr_extract()` → `extract_text.py` 순서로 호출됩니다.
- **GPT 검수** 는 “DB에 이미 들어온 후보”를 GPT API로 검수하는 단계라,  
  **파싱이 성공해 후보가 생긴 뒤**에만 동작합니다.
- 파싱이 안 되면: doc.php 의 parse_status / error_code, 파일 경로, OCR 설정(Python/Tesseract, extract_text.py) 순으로 확인하면 됩니다.
- GPT 검수가 안 되면: 위 표의 메시지로 원인(파싱 미완료 vs 후보 없음 vs API 키/스크립트)을 구분할 수 있습니다.
