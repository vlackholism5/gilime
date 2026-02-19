# OCR Fallback 아키텍처 설계서 (v1.7)

## 목적
- 텍스트 기반 PDF 파싱 실패 시 **스캔 PDF** 판별 및 OCR fallback 경로 표준화
- 차기 구현 착수 시 입출력 계약, 재시도/대체 경로가 명확하도록 설계

---

## 1. 스캔 PDF 판별 기준

| 조건 | 판별 | 동작 |
|------|------|------|
| smalot `getText()` 결과 비어 있음 | 스캔 PDF 가능성 높음 | OCR fallback 진입 |
| `getText()` 결과 비어 있지 않음 | 디지털 PDF | 기존 파싱 로직 진행 |
| OCR 추출 후 유의미한 한글 토큰 < N개 | 저품질 OCR | `PARSE_OCR_LOW_QUALITY` (향후) |
| OCR 추출 후 정류장/노선 패턴 매칭 실패 | 형식 불일치 | `PARSE_NO_ROUTE` / `PARSE_NO_STOPS` |

### 판별 시점
1. **1차:** `parse_shuttle_pdf()` 내부에서 `$pdf->getText()` 호출 직후
2. **2차(향후):** 품질 점수 기반 (예: 추출 텍스트에서 한글/숫자 비율, 정류장 패턴 등)

---

## 2. OCR 입력/출력 계약 (JSON 스키마)

### 2.1 OCR 입력 (extract_text.py CLI → JSON 호환)

| 필드 | 타입 | 필수 | 설명 |
|------|------|------|------|
| input_file | string | Y | PDF 절대 경로 |
| output | string | Y | 출력 txt 경로 |
| output_format | string | Y | `text_only` (gilime용) |
| lang | string | N | 기본 `kor+eng` |
| tesseract_cmd | string | N | Tesseract 실행 경로 (Windows) |

### 2.2 OCR 출력 (run_ocr_extract 반환)

| 타입 | 설명 |
|------|------|
| string | 추출된 텍스트 (UTF-8) |
| null | 실패 시 (exec 실패, 파일 미생성, 빈 텍스트 등) |

### 2.3 parse_shuttle_pdf 결과 확장 (OCR 사용 시)

```json
{
  "success": true,
  "error": null,
  "error_code": null,
  "warning_codes": ["ocr_used"],
  "parser_version": "v1.0.0-ocr",
  "parsed_at_ms": 1200,
  "route_label": "R1",
  "stops": [{"seq": 1, "raw_stop_name": "강남역"}, ...]
}
```

- `warning_codes`에 `ocr_used` 포함 시 OCR 경로 사용됨

---

## 3. 실패 시 재시도/대체 경로

### 3.1 재시도 정책 (향후)

| 단계 | 동작 | 시간 |
|------|------|------|
| 1회 | `run_ocr_extract()` 호출 | - |
| 2회 | exec 실패/타임아웃 시 3초 대기 후 재시도 | +3s |
| 3회 | 2회 실패 시 10초 대기 후 재시도 | +10s |
| 실패 | `error_code=PARSE_OCR_FAILED` 반환 | - |

### 3.2 대체 경로

| 순서 | 경로 | 조건 |
|------|------|------|
| 1 | smalot `getText()` | 항상 시도 |
| 2 | `run_ocr_extract()` (Python + Tesseract) | 1번 텍스트 없음 |
| 3 (향후) | 관리형 OCR API (클라우드) | 2번 실패 + 설정 활성화 |
| 4 (향후) | 수동 업로드 대체 (UI) | 사용자 개입 |

### 3.3 에러코드

| 코드 | 설명 |
|------|------|
| PARSE_OCR_FAILED | OCR 추출 실패 (exec 오류, Tesseract 미설치 등) |
| PARSE_OCR_TIMEOUT | exec 타임아웃 (향후) |
| PARSE_OCR_LOW_QUALITY | 추출 텍스트 품질 부족 (향후) |

---

## 4. 현재 구현 (v1.7-20)

- `app/inc/parse/pdf_parser.php::run_ocr_extract()`
- `scripts/python/extract_text.py` (exec)
- 설정: `config.local.php` → `OCR_PYTHON_CMD`, `OCR_TESSERACT_CMD`
- 운영 가이드: `docs/operations/PDF_OCR_SETUP_v1_7.md`

---

## 5. 차기 구현 착수 체크리스트

- [ ] exec 타임아웃 적용 (예: 60초)
- [ ] OCR 재시도 로직 (3회, 백오프)
- [ ] 품질 점수 기반 `PARSE_OCR_LOW_QUALITY` 분기
- [ ] 관리형 OCR (선택) 검토
