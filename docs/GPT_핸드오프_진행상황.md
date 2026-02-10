# GPT 전달용 — 지금까지 진행상황

**사용 방법:** Cursor에서 작업한 뒤, 아래 **전체 블록**을 복사해 GPT 대화창에 붙여넣고, 이어서 할 작업이나 "다음 지시서 줘"라고 요청하면 GPT가 다음 프롬프트/지시를 줍니다.

## 최신 업데이트 (v0.6-22)

- `shuttle_parse_metrics` 테이블 추가: PARSE_MATCH 성공 `job_id + route_label` 단위로 품질 지표 저장.
- `public/admin/run_job.php`에서 PARSE_MATCH 성공 직후 DB 집계로 metrics UPSERT 저장.
- `public/admin/doc.php`에 **PARSE_MATCH Metrics (latest job)** 섹션 추가.
- `sql/v0.6-22_validation.sql` 추가 및 콜레이션 충돌 대응 반영.
- 검증 결과: latest job 기준 route_cnt=metrics_cnt, metrics/candidate 집계 일치, 중복 0건.

---

## SQL 검증을 GPT 대화에서 하고 싶을 때

- **GPT는 SQL을 직접 실행할 수 없습니다.** 사용자 PC에서만 실행 가능(Workbench, Cursor 터미널, mysql 클라이언트 등).
- **절차:**  
  1) GPT에게 "v0.6-18 검증 쿼리 실행해서 결과 알려줘" 또는 "검증 쿼리 나눠줘"라고 요청  
  2) GPT가 `sql/v0.6-18_validation.sql` 등에서 쿼리(및 :doc, :rl 등 치환값)를 알려줌  
  3) **사용자**가 Workbench에서 해당 쿼리 실행  
  4) 실행 결과(행 수, 결과 표 일부, 또는 에러 메시지)를 **GPT 대화창에 그대로 붙여넣기**  
  5) GPT가 결과를 해석하고 "정상" / "이상 있음, 다음 단계 ~" 등 **다음 지시**를 줌  
- 이 절차를 GPT에게 미리 알려두려면 아래 블록에 포함된 "[SQL 검증]" 문단을 함께 전달하면 됩니다.

---

아래 블록 전체를 복사해 GPT에 붙여넣으면 됩니다.

---

```
[프로젝트] GILIME_MVP_01 (PHP + MySQL, XAMPP)
[레포] https://github.com/vlackholism5/gilime (main 브랜치, push 완료)

[버전] v0.6-19 반영 완료 (이전 v0.6-18 포함)
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

[v0.6-19 신규 기능]
- only_low 필터: GET only_low=1 시 match_method='like_prefix' 후보만 표시. only_unmatched와 동시 사용 가능.
- 토글 링크: "LOW만 보기" / "LOW 해제". only_unmatched와 파라미터 조합 유지.
- Promote 경고: low_confidence_cnt/auto_matched_cnt >= 30% 시 주황색 경고 문구 표시.
- UI 개선 (v0.6-18 포함): summary 카드 그리드, 배지(status/신뢰도), action 버튼 세로 배치, 색상·간격 개선.

[마이그레이션]
- v0.6-8 ~ v0.6-18 통합 마이그레이션 완료 (scripts/migrate_v06_8_to_18.php).
- 테이블: seoul_bus_stop_master(10건 더미), shuttle_stop_alias(3건 더미), shuttle_stop_normalize_rule.
- 컬럼: shuttle_stop_candidate(자동매칭 4개), shuttle_doc_job_log(base_job_id, route_label).

[SQL 검증 — GPT 대화에서 하는 방법]
- SQL은 사용자 PC(Workbench/터미널)에서만 실행 가능. GPT는 실행 불가.
- 절차: GPT가 검증 쿼리(및 :doc/:rl/:latest_job_id 치환 예시)를 알려주면 → 사용자가 Workbench에서 실행 → 실행 결과(행 수, 표, 에러)를 GPT 대화에 붙여넣기 → GPT가 결과 해석 후 다음 지시(정상/추가 점검 등) 제시.
- 검증 쿼리 파일: sql/v0.6-18_validation.sql (주석 해제 후 placeholder 치환).
```

---

위 내용을 GPT에 붙여넣은 뒤, 이어서 하고 싶은 작업(예: "다음 지시서 줘", "v0.6-18 검증 쿼리 실행 절차 알려줘")을 적어 주면 됩니다.
