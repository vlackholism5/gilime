# v1.7-17 Gate: upload -> run_job 최소 ingest

## 체크리스트
- [ ] `upload_pdf.php`에서 PDF 업로드/저장 성공
- [ ] 업로드 후 source_doc 생성 확인
- [ ] same page에서 `Run Parse/Match now`로 파싱 실행 가능
- [ ] `doc.php` last_parse_* 값 확인

## 최소 검증
- `php -l public/admin/upload_pdf.php`
- `php -l public/admin/index.php`
- 브라우저 업로드 1건 + run_job 실행

## PASS 기준
- 운영자가 UI만으로 업로드→파싱 실행을 1회 완주 가능
- 결과가 기존 `doc.php` 운영 지표와 연결됨

## Non-goals
- OCR/워커 구현 없음
- 업로드 히스토리 고도화 없음

