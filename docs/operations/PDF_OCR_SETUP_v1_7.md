# PDF OCR 설정 (v1.7-20)

## 개요
PDF에 텍스트가 없거나破损(scanned PDF)일 때 **Python + Tesseract OCR**으로 텍스트를 추출합니다.

## 사전 준비

### 1. Tesseract OCR 설치
- Windows: https://github.com/UB-Mannheim/tesseract/wiki
- `C:\Program Files\Tesseract-OCR\tesseract.exe` 설치
- 설치 시 **Korean** 언어팩 선택 (kor)

### 2. Python 환경
- Python 3.10~3.12 권장 (3.14에서 Pillow wheel 미지원 시 오류 가능)
- 가상환경 사용 권장

```powershell
cd c:\xampp\htdocs\gilime_mvp_01\scripts\python
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
```

### 3. config.local.php 설정 (선택)
```php
// Python 경로 (venv 활성화 시 'python'으로 충분)
define('OCR_PYTHON_CMD', 'python');
// 또는 venv 전체 경로: define('OCR_PYTHON_CMD', 'c:\\xampp\\htdocs\\gilime_mvp_01\\scripts\\python\\.venv\\Scripts\\python.exe');

define('OCR_TESSERACT_CMD', 'C:\\Program Files\\Tesseract-OCR\\tesseract.exe');
```

## 동작 흐름

1. **PHP parse_shuttle_pdf()** → smalot/pdfparser로 텍스트 추출 시도
2. 텍스트 없음 → **run_ocr_extract()** 호출
3. Python `extract_text.py` 실행
   - pypdf로 digital 텍스트 추출 시도
   - 부족/손상 시 Tesseract OCR (kor+eng)
4. 추출된 텍스트로 기존 파싱 로직(노선 라벨, 정류장 목록) 계속

## CLI 테스트

```powershell
# 단일 PDF
python scripts/python/extract_text.py --input-file public/uploads/your.pdf --output extracted.txt --tesseract-cmd "C:\Program Files\Tesseract-OCR\tesseract.exe"

# 디렉터리 배치
python scripts/python/extract_text.py --input . --output extracted_text --tesseract-cmd "C:\Program Files\Tesseract-OCR\tesseract.exe"
```

## 문제 해결

| 증상 | 확인 |
|------|------|
| OCR 실패 | Tesseract 설치 경로, kor 언어팩 설치 여부 |
| Python 미실행 | OCR_PYTHON_CMD, venv 활성화 여부 |
| Pillow 빌드 오류 | Python 3.14 사용 시 3.12로 다운그레이드 또는 Pillow>=11.3 wheel 사용 |
