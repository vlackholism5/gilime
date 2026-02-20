# gilime Python 스크립트 (v1.7-20)

## extract_text.py — PDF OCR

Python + Tesseract 기반 PDF 텍스트 추출. PHP parse_shuttle_pdf에서 텍스트 없을 때 자동 호출.

## 설치

```powershell
pip install -r requirements.txt
```

**필수:** Tesseract OCR 설치 + Korean 언어팩
- Windows: `C:\Program Files\Tesseract-OCR\tesseract.exe`

## 실행 예시

```powershell
# 단일 PDF (gilime PHP 연동용)
python extract_text.py --input-file "path/to/file.pdf" --output "out.txt" --tesseract-cmd "C:\Program Files\Tesseract-OCR\tesseract.exe"

# 디렉터리 배치
python extract_text.py --input . --output extracted_text --tesseract-cmd "C:\Program Files\Tesseract-OCR\tesseract.exe"
```

## 출력

- `--output-format text_only` (기본): 추출 텍스트만 (PHP 파싱용)
- `--output-format full`: 페이지 헤더 포함

---

## gpt_review_pipeline.py — GPT 검수 파이프라인

후보 JSON → OpenAPI(GPT) 호출 → 검수 결과 JSON → import_candidate_review 업로드.

```powershell
pip install -r requirements-gpt.txt
$env:OPENAI_API_KEY = "sk-..."
python gpt_review_pipeline.py --input candidates.json --output review_results.json --route-label "운행구간"
```

설정: `gpt_review_config.example.env` 참고.

---

## parse_shuttle_pdf_to_structured.py — PDF 1회 구조화 파싱 (v1.7-21)

PDF 텍스트를 1회 파싱해 고정 스키마 CSV로 출력. 정류장명·정류장ID는 이미지/수동 보정용으로 null 허용.

```powershell
pip install pypdf   # 또는 requirements.txt
python parse_shuttle_pdf_to_structured.py -i path/to/file.pdf -o parsed.csv
```

- **연동**: run_job(파싱·매칭 실행) 시 스크립트가 있으면 우선 사용. 실패 시 기존 pdf_parser 폴백.
- **설계**: docs/operations/PDF_STRUCTURED_PARSE_DESIGN.md
