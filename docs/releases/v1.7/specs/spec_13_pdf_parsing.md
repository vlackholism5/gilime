# v1.7-13: PDF Parsing (실제 PDF 파싱 구현)

## 목표
- 더미 파서를 제거하고 실제 PDF 파일에서 노선·정류장 정보를 추출
- smalot/pdfparser 라이브러리를 사용한 텍스트 기반 PDF 파싱
- OCR이 필요한 스캔 PDF는 향후 확장 (현재는 텍스트 추출 가능한 PDF만 지원)

## 구현 내용

### 1) Composer 의존성 추가
- `composer.json` 생성
- `smalot/pdfparser` ^2.0 추가

### 2) PDF 파싱 모듈 (`app/inc/parse/pdf_parser.php`)
- **parse_shuttle_pdf($filePath)**: PDF에서 노선·정류장 추출
  - 반환값: `['success', 'error', 'route_label', 'stops']`
- **extract_route_label($text)**: 노선 라벨 추출 (예: "R1", "셔틀버스 R2")
- **extract_stops_from_text($text)**: 정류장 목록 추출
  - 지원 형식:
    - `1. 강남역` (번호 + 점)
    - `- 강남역` (대시/불릿)
    - `강남역` ("역" 또는 "정류장"으로 끝나는 줄)

### 3) run_job.php 수정
- 더미 데이터 제거
- `shuttle_source_doc.file_path` 조회
- `parse_shuttle_pdf()` 호출
- 파싱 실패 시 사용자에게 에러 메시지 표시

### 4) .gitignore 추가
- `vendor/` (Composer)
- `public/uploads/*.pdf` (업로드된 PDF)

## 제약사항
- **텍스트 추출 가능한 PDF만 지원** (스캔 이미지 PDF는 OCR 필요)
- 노선 라벨 추출 실패 시 전체 파싱 실패 처리
- 정류장이 하나도 추출되지 않으면 실패 처리

## 향후 확장 가능
- Tesseract OCR 연동 (스캔 PDF 지원)
- 다양한 PDF 레이아웃 대응 (표, 다단 구성 등)
- 신뢰도별 후보 분류 (고/중/저 신뢰도)
