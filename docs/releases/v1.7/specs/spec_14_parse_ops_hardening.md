# v1.7-14: PARSE_MATCH 운영형 고도화 (PHP only)

## 목표
- 공공데이터 PDF 처리에서 운영 안정성 강화
- 실패 원인을 코드화하여 재처리/분석 가능 상태로 전환
- PHP+MySQL만으로 배치 재처리 경로 제공

## 변경 사항

### 1) 파서 반환 계약 표준화
- 파일: `app/inc/parse/pdf_parser.php`
- 반환 필드 추가:
  - `error_code`
  - `warning_codes`
  - `parser_version`
  - `parsed_at_ms`
- 오류 코드 표준:
  - `DEP_MISSING`
  - `FILE_NOT_FOUND`
  - `FILE_READ_FAILED`
  - `INVALID_FILE_TYPE`
  - `FILE_TOO_LARGE`
  - `NO_TEXT`
  - `ROUTE_NOT_FOUND`
  - `STOPS_NOT_FOUND`
  - `PARSE_EXCEPTION`

### 2) 입력/경로 정책 강화
- 파일: `public/admin/run_job.php`
- 정책:
  - `public/uploads` 루트 내부 파일만 허용
  - `.pdf` 확장자만 허용
  - 최대 10MB 제한
  - path traversal (`..`) 차단

### 3) parse_status 상태 전이 일관화
- 파일: `public/admin/run_job.php`
- 전이:
  - 실행 시작: `running`
  - 성공: `success`
  - 실패: `failed`
- **실패 시 항상** `shuttle_doc_job_log`에 `result_note`에 `error_code=XXX` 포함하여 기록

### 4) 관측성 강화
- 파일: `public/admin/run_job.php`
- `observability.php` 연동 이벤트:
  - `parse_job_start`
  - `parse_pdf_done`
  - `candidate_insert_done`
  - `parse_job_end`
- `result_note`에 파서 버전/지연시간/라우트/행수 기록

### 5) 배치 재처리 경로 추가
- 파일: `scripts/php/run_parse_match_batch.php`
- 지원 옵션:
  - `--source_doc_id=<id>`
  - `--limit=<n>`
  - `--only_failed=1` (failed만)
  - `--dry_run=1`
- 기본 선택: `parse_status IN (pending, failed)` 문서 재처리
- 출력: JSON 요약 + 실패 코드 TopN

### 6) 운영 UI 보강
- 파일: `public/admin/doc.php`
- 추가 표시:
  - `last_parse_status`
  - `last_parse_error_code`
  - `last_parse_duration_ms`
  - `last_parse_route_label`
  - `PARSE_MATCH Failure TopN (recent 50 jobs)`
  - 재실행 버튼 주변: 처리시간, 오류코드, 배치 명령 안내, 실패건 보기 링크
- 파일: `public/admin/index.php`
  - `?parse_status=failed` 필터로 실패건 빠른 필터

## Non-goals
- OCR 엔진(Tesseract/클라우드 OCR) 도입
- 워커/큐(비동기) 아키텍처 도입

