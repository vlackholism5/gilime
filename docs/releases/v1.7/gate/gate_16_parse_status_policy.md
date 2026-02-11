# v1.7-16 Gate: parse_status 정책/배치 선별

## 체크리스트
- [ ] `run_parse_match_batch.php` 선별 정책이 문서와 일치
- [ ] `only_failed=1`에서 failed + legacy_failed_job 포함
- [ ] 기본 실행(`only_failed=0`)에서 success만 포함
- [ ] `dry_run=1` 3줄 요약 출력 확인

## 최소 검증
- `php -l scripts/run_parse_match_batch.php`
- `--only_failed=1 --dry_run=1` 실행
- `--dry_run=1` 실행(기본 정책 확인)

## PASS 기준
- dry-run 출력만으로 선별 기준을 운영자가 즉시 해석 가능
- selected_by_status / selected_reason_top이 JSON 결과에 포함

## Non-goals
- parse_status 타입/DDL 변경 없음
- 대량 데이터 정리 SQL 없음

