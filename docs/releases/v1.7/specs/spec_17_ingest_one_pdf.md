# v1.7-17: 실데이터 ingest 1종 최소 연결 (upload -> run_job)

## 목적
- 운영자가 PDF 1건을 업로드하고 바로 파싱 실행까지 연결되는 최소 ingest 동선을 제공한다.

## 구현 범위
- 신규 페이지: `public/admin/upload_pdf.php`
  - PDF 업로드(.pdf, 최대 10MB)
  - `public/uploads`에 안전 파일명으로 저장
  - `shuttle_source_doc`에 최소 메타 레코드 생성(기존 테이블 재사용)
  - 업로드 완료 후:
    - `doc.php?id=...` 링크
    - `run_job.php` 즉시 실행 버튼(POST)
- 관리자 목록 링크 추가: `public/admin/index.php`에 `Upload PDF`

## 운영 정책
- 파일 저장 경로는 `public/uploads`로 고정
- OCR/워커는 본 버전에 포함하지 않음(설계 문서만 유지)

## 검증 포인트
- 업로드 성공 시 source_doc_id 생성
- `Run Parse/Match now` 실행 후 `doc.php`의 `last_parse_*` 반영 확인

## Non-goals
- 다중 파일 버전관리/히스토리 고도화
- 외부 스토리지 연동
- OCR 엔진 연동, 워커 큐 도입

