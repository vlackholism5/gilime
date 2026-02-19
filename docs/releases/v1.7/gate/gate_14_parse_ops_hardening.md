# v1.7-14 Gate: PARSE_MATCH 운영형 고도화

## 체크리스트
- [ ] `app/inc/parse/pdf_parser.php` 입력 검증/에러코드(PARSE_*)/정규화 강화
- [ ] `public/admin/run_job.php` 상태 전이(running→success|failed), result_note 표준화
- [ ] `scripts/php/run_parse_match_batch.php` 배치 재처리 동작 확인
- [ ] `public/admin/doc.php` 운영 정보/실패 TopN/재처리 안내 표시 확인
- [ ] `app/inc/lib/observability.php` PARSE_MATCH 이벤트 표준 필드 (parse_job_start, parse_pdf_done, candidate_insert_done, parse_job_end)

## 증거 수집 포맷 (고정)

| 시나리오 | SQL | 로그 | 화면 |
|----------|-----|------|------|
| 성공 | `shuttle_source_doc.parse_status=success` | `parse_job_end result=success elapsed_ms stop_cnt auto_matched_ratio_pct` | flash "파싱/매칭 성공" |
| 실패 | `shuttle_doc_job_log.result_note`에 `error_code=PARSE_XXX` | `parse_job_end result=failed error_code=PARSE_XXX` | `최근 파싱 오류코드=PARSE_XXX` |
| 재처리 | `shuttle_stop_candidate.is_active=0`(이전), `created_job_id=최신`(새) | - | doc.php "이전 후보 비활성화" 안내 |

## 필수 검증 명령
```powershell
c:\xampp\php\php.exe -l app/inc/parse/pdf_parser.php
c:\xampp\php\php.exe -l public/admin/run_job.php
c:\xampp\php\php.exe -l scripts/php/run_parse_match_batch.php
c:\xampp\php\php.exe -l public/admin/doc.php
```

## 배치 검증 명령
```powershell
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --only_failed=1 --limit=20 --dry_run=1
c:\xampp\php\php.exe scripts/php/run_parse_match_batch.php --limit=10 --dry_run=1
```

## PASS 기준
- 문법 체크 4개 모두 OK
- Web 실행 경로에서 `parse_status` 전이가 일관적임 (pending|running|success|failed)
- 실패 케이스에서 `job_log.result_note`에 `error_code=PARSE_XXX` 포함 기록
- 배치 JSON 출력에 `fail_topn` 포함
- `admin/index.php?parse_status=failed`로 실패건 필터 동작

