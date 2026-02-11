# v1.7-15 Gate: Legacy error normalization

## 체크리스트
- [ ] `app/inc/error_normalize.php` 추가
- [ ] `doc.php` Failure TopN 집계가 정규화 함수를 사용
- [ ] `last_parse_error_code`가 실패 로그 기준 정규화 코드 표시

## 최소 검증
- `php -l app/inc/error_normalize.php`
- `php -l public/admin/doc.php`
- 브라우저 `/admin/doc.php?id=1`에서 TopN 코드 확인

## PASS 조건
- `UNKNOWN`만 의존하지 않고, 레거시 문구가 표준 코드로 최소 1건 이상 매핑됨
- 기존 `error_code=...` 로그 집계가 회귀 없이 유지됨

## Non-goals
- 과거 로그 데이터 마이그레이션 없음

