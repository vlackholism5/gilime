# v1.7-16 Smoke: parse_status 정책/배치 선별

1. 문법 체크
```powershell
c:\xampp\php\php.exe -l scripts/php/run_parse_match_batch.php
```

2. 실패 대상 선별(dry-run)
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --only_failed=1 --limit=20 --dry_run=1
```
- 기대: `[DRY-RUN]` 3줄 + JSON 출력

3. 성공 대상 선별(dry-run)
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --limit=20 --dry_run=1
```
- 기대: `selection_policy=only_failed=0 => parse_status=success`

4. 강제 1건 실행
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --source_doc_id=1 --dry_run=1
```
- 기대: `selection_policy=source_doc_id_override`

5. (선택) Workbench read-only 1쿼리
```sql
SELECT parse_status, COUNT(*) AS cnt
FROM shuttle_source_doc
GROUP BY parse_status;
```

