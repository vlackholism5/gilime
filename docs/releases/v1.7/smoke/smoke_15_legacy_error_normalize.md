# v1.7-15 Smoke: Legacy error normalization

1. 관리자 페이지 접속: `/admin/doc.php?id=1`
2. `Jobs` 표에서 레거시 실패 문구(`error: SQLSTATE...`)가 있는지 확인
3. 같은 화면의 `PARSE_MATCH Failure TopN`에서 해당 케이스가 `UNKNOWN`이 아닌 표준 코드(`PARSE_EXCEPTION`)로 집계되는지 확인
4. `error_code=FILE_NOT_FOUND ...` 형태 로그가 `FILE_NOT_FOUND`로 집계되는지 확인
5. `last_parse_error_code`가 실패 최신 job 기준으로 정규화 코드 표시되는지 확인

예상 결과:
- TopN 키가 표준 코드 중심으로 표시됨
- 레거시 텍스트 에러가 가능한 범위에서 코드화됨

