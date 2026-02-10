# GPT 전달용 — 지금까지 진행상황

아래 블록 전체를 복사해 GPT에 붙여넣으면 됩니다.

---

```
[프로젝트] GILIME_MVP_01 (PHP + MySQL, XAMPP)
[레포] https://github.com/vlackholism5/gilime (main 브랜치, push 완료)

[버전] v0.6-17 반영 완료
- SoT: latest PARSE_MATCH 스냅샷, stale 후보 차단, promote는 latest만, route_stop 스냅샷(is_active).
- 자동매칭(seoul_bus_stop_master), alias/정규화, Quick Search, 추천 canonical(only_unmatched+캐시), 매칭 실패만 보기.

[GitHub 비밀 제거 (완료)]
- 원인: Push Protection이 app/inc/config.php 내 Aiven 비밀 감지로 push 차단.
- 조치: config.php에서 비밀 제거 → config.local.php 로드 + getenv() fallback. config.local.php.example 템플릿 추가. .gitignore에 config.local.php, .env, config.safe.php 추가.
- 히스토리: git filter-branch로 모든 커밋의 config.php를 비밀 없는 버전으로 교체 후 push 성공.

[DB 설정 — 로컬 전용, GitHub 미포함]
- 비밀/접속 정보는 app/inc/config.local.php 에만 둠. 이 파일은 .gitignore 대상이라 커밋·푸시되지 않음.
- 현재 운영: Aiven 사용 중 (DB_HOST=mysql-301f0b59-minif.g.aivencloud.com, PORT=28220, USER=avnadmin, DB_PASS는 config.local.php에만 입력).
- 로컬 XAMPP만 쓸 때: DB_HOST=127.0.0.1, DB_PORT=3306, DB_USER=root, DB_PASS='' 또는 실제 root 비밀번호.

[참고 문서]
- 전체 SoT·DB·제약: docs/STATUS_FOR_GPT.md (있으면)
- 비밀 로컬 보관·접속 테스트: docs/LOCAL_CONFIG.md
- DB 접속 테스트 URL: http://localhost/gilime_mvp_01/app/inc/test_db_connection.php

[최근 이슈 정리]
- Access denied (root@localhost): Aiven 쓰는 중인데 일시적으로 로컬 설정으로 바뀌었다가, 다시 Aiven 설정으로 복구함. config.local.php에 Aiven 비밀 입력해 두면 됨.
- ERR_CONNECTION_REFUSED: 웹 서버(Apache) 미실행. XAMPP 제어판에서 Apache Start 후 재접속.

[제약 유지]
- 새 페이지/테이블 추가 금지(지시 없는 한). 풀스캔·LIKE %...% 금지. SoT·stale 차단·promote 규칙 훼손 금지.
```

---

위 내용을 GPT에 붙여넣은 뒤, 이어서 하고 싶은 작업(예: "다음으로 v0.6-18에서 ~ 구현해줘")을 적어 주면 됩니다.
