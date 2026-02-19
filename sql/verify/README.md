# sql/verify — Aiven MySQL 검증 스크립트 대상

이 디렉터리의 `*.sql` 파일은 `scripts/ps1/run_verify.ps1`에서 **파일명 순**으로 실행됩니다.

- 실행 결과는 `logs/verify_latest.md`에 덮어써 집니다.
- 접속 정보는 환경변수 또는 프로젝트 루트 `.env`에서 읽습니다 (비밀번호 등 하드코딩 금지).

필요한 검증용 SQL 파일을 이 디렉터리에 추가한 뒤 `run_verify.ps1`을 실행하세요.
