# Aiven MySQL 검증 실행기 (run_verify)

로컬에서 `sql/verify/*.sql`을 Aiven MySQL에 순서대로 실행하고 결과를 `logs/verify_latest.md`에 저장합니다.

## 1) What to change: 파일 경로/함수

| 대상 | 변경 내용 |
|------|-----------|
| **scripts/ps1/run_verify.ps1** | 신규. .env/환경변수 로드, mysql CLI 호출, 결과 마크다운 저장. |
| **sql/verify/** | 신규 디렉터리. 실행 대상 `*.sql`을 여기에 둠. |
| **sql/verify/README.md** | 신규. 디렉터리 용도 안내. |
| **.gitignore** | 1줄 추가. `logs/verify_latest.md` (실행 결과 미커밋). |

**함수/로직:**  
- 스크립트 상단: 프로젝트 루트 `.env` 읽어 Process 환경변수로 설정(이미 있으면 덮어쓰지 않음).  
- 필수 env: `AIVEN_MYSQL_HOST`, `AIVEN_MYSQL_DB`, `AIVEN_MYSQL_USER`.  
- 선택 env: `AIVEN_MYSQL_PORT`(기본 3306), `AIVEN_MYSQL_PASSWORD`, `AIVEN_MYSQL_SSL_CA`(CA 인증서 경로).  
- `mysql` 인자: `-h`, `-P`, `-u`, `-p`, DB명, `--ssl-mode=VERIFY_CA`, `--ssl-ca=<path>`(SSL_CA 있을 때만).  
- `sql/verify/*.sql`을 이름 순으로 `source <파일>` 로 실행, stdout/stderr를 취합해 `logs/verify_latest.md`에 덮어씀. 실패 시 해당 파일 블록에 ERROR와 메시지 기록.

## 2) Why

- Aiven MySQL에 대해 스키마/검증 SQL을 **한 번에 실행**하고 결과를 **한 파일**로 남기기 위함.  
- 비밀번호/접속 정보는 **코드에 넣지 않고** .env 또는 환경변수만 사용.  
- SSL 연결이 필요할 때 `AIVEN_MYSQL_SSL_CA`로 CA 경로 지정.

## 3) How to verify

**실행 명령 (로컬, 프로젝트 루트 또는 scripts에서):**

```powershell
# 프로젝트 루트에서
.\scripts\run_verify.ps1
```

**필요 사전 설정:**  
- MySQL 클라이언트(`mysql`)가 PATH에 있음.  
- 프로젝트 루트에 `.env` 파일을 두거나, 셸에서 환경변수 설정:

```text
AIVEN_MYSQL_HOST=your-aiven-host.aivencloud.com
AIVEN_MYSQL_PORT=12345
AIVEN_MYSQL_DB=defaultdb
AIVEN_MYSQL_USER=avnadmin
AIVEN_MYSQL_PASSWORD=your-password
AIVEN_MYSQL_SSL_CA=C:\path\to\ca.pem
```

**예상 출력 위치:**  
- 성공: 콘솔에 `Verify output written to logs/verify_latest.md` 출력.  
- `logs/verify_latest.md`: 상단에 타임스탬프, 각 `sql/verify/*.sql`별로 `## 파일명` + 결과 코드블록.

**실패 케이스 확인:**  
1. **필수 env 없음:** 스크립트가 에러로 종료하고, `logs/verify_latest.md`에 "Verify failed (config)" 및 누락된 변수 안내 기록.  
2. **SQL 실행 실패:** 해당 파일 블록에 `**ERROR**`와 mysql stderr 내용이 `logs/verify_latest.md`에 기록되고, 스크립트가 non-zero 종료.  
3. **mysql 미설치/미PATH:** 예외 메시지가 `logs/verify_latest.md`의 ERROR 블록에 기록.

## 4) Rollback

- **scripts/ps1/run_verify.ps1** 삭제.  
- **sql/verify/** 디렉터리 삭제(sql/verify/README.md 포함).  
- **.gitignore**에서 추가한 `logs/verify_latest.md` 1줄 제거.  
- 사용했던 환경변수 또는 `.env` 내 AIVEN_MYSQL_* 항목 제거(선택).

---

## 나중에 필요 시 (검증 자동화 마감 / 실무 보강)

다음 작업이 필요해지면 이 섹션을 참고해 진행하면 됩니다.

1. **리턴코드:** 실패 시 스크립트에서 `exit 1` 명시 (config 누락, mysql 예외, SQL 실행 실패 시). CI/배치에서 실패 여부 판단용.
2. **요약:** `logs/verify_latest.md` 상단에 `Summary: N files, Y OK, Z failed` 한 줄 추가. 운영 시 결과만 빠르게 보기 위함.
3. **샘플 verify SQL:** `sql/verify/`에 read-only 샘플 1개(예: `SELECT 1 AS verify_ok;`) 추가. 연결·실행 확인용.
4. **문서:** "MySQL Client 8.0+ 권장" (구버전 CLI 호환성), Aiven CA 다운로드 후 `AIVEN_MYSQL_SSL_CA` 설정 안내 보강.
