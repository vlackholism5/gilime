# v1.7-15: Legacy error normalization (UNKNOWN 축소)

## 목적
- `doc.php`의 Failure TopN 집계에서 레거시 실패 메시지를 표준 error_code로 정규화해 `UNKNOWN` 비중을 낮춘다.

## 변경 내용
- 신규 파일: `app/inc/lib/error_normalize.php`
  - `normalize_error_code($rawMessageOrNote): string`
  - 우선순위:
    1) `error_code=...` 토큰이 있으면 그대로 채택
    2) 레거시 문구 패턴 매핑
    3) 미매핑 시 `UNKNOWN`
- 수정 파일: `public/admin/doc.php`
  - Failure TopN 집계 key를 `normalize_error_code(result_note)`로 통일
  - `last_parse_error_code`도 실패 로그일 때 정규화 결과로 표시

## 표준 코드
- `DEP_MISSING`
- `FILE_NOT_FOUND`
- `FILE_READ_FAILED`
- `INVALID_FILE_TYPE`
- `FILE_TOO_LARGE`
- `NO_TEXT`
- `ROUTE_NOT_FOUND`
- `STOPS_NOT_FOUND`
- `PARSE_EXCEPTION`

## Evidence
- 레거시 실패 문구 `error: SQLSTATE...`가 `PARSE_EXCEPTION`으로 분류되는지 화면/출력으로 확인.
- `error_code=FILE_NOT_FOUND ...` 로그는 기존과 동일하게 `FILE_NOT_FOUND`로 집계되는지 확인.

## Non-goals
- 과거 DB 로그를 UPDATE로 대량 치환하지 않는다.
- 새 테이블/컬럼을 추가하지 않는다.

