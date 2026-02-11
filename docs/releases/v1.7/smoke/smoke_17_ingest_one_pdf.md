# v1.7-17 Smoke: upload -> run_job 최소 ingest

1. 문법 체크
```powershell
c:\xampp\php\php.exe -l public/admin/upload_pdf.php
c:\xampp\php\php.exe -l public/admin/index.php
```

2. 브라우저에서 `/admin/upload_pdf.php` 접속

3. PDF 1건 업로드
- 기대: `업로드 완료 ... source_doc_id=...` 표시

4. 같은 화면의 `Run Parse/Match now` 클릭
- 기대: `doc.php?id=...` 이동 후 파싱 결과 반영

5. `doc.php`에서 확인
- `parse_status`, `last_parse_status`, `last_parse_duration_ms`, `last_parse_route_label`

6. (선택) Workbench 1쿼리
```sql
SELECT id, job_status, result_note, updated_at
FROM shuttle_doc_job_log
WHERE source_doc_id = <doc_id>
ORDER BY id DESC
LIMIT 5;
```

