# GILIME MVP — 상태 요약 (GPT/운영 참고)

## 현재 버전

- **v0.6-22** 반영 완료 (코드·검증 쿼리, SQL/DDL/Import 실행은 Cursor PC 앱에서만).

## SoT (변경 금지)

1. latest PARSE_MATCH 스냅샷 기준 후보 표시 (created_job_id=latest).
2. stale 후보 승인/거절 차단 (UI+서버).
3. promote는 latest PARSE_MATCH만 허용.
4. route_stop 스냅샷 누적 (기존 active is_active=0, 신규만 is_active=1).
5. alias 등록 시 latest 후보 1건 live rematch (가능할 때만).
6. 추천 canonical 계산은 only_unmatched=1에서만 (캐시 포함) — v0.6-17 고정.
7. 매칭 신뢰도(HIGH/MED/LOW/NONE) 표시는 표시/집계 전용, 매칭 로직 변경 금지 — v0.6-18 고정.
8. PARSE_MATCH 품질 지표는 DB에 저장(shuttle_parse_metrics). job_id + route_label 단위 집계 — v0.6-22 추가.

## v0.6-22 요약

- **테이블 추가:** shuttle_parse_metrics (PARSE_MATCH job_id + route_label 단위로 매칭 품질 저장). UNIQUE(parse_job_id, route_label).
- **자동 저장:** run_job.php에서 PARSE_MATCH 성공 후 DB 집계 쿼리로 metrics 계산 → UPSERT. 실패해도 PARSE_MATCH는 성공 유지(비치명적).
- **화면 추가:** doc.php에 "PARSE_MATCH Metrics (latest job)" 테이블. route별 품질 지표(cand_total, auto_matched, LOW, NONE, alias, HIGH/MED/LOW/NONE) 표시.
- **검증:** sql/v0.6-22_validation.sql (8개 쿼리: 테이블 존재, metrics row count, candidate 집계 일치, UPSERT idempotent).
- **매칭 로직/SoT 불변:** 품질 지표 저장만 추가. route_review 변경 없음.

## v0.6-21 요약

- **LOW 승인 게이트:** match_method='like_prefix' pending 후보는 체크박스 "LOW(like_prefix) 확인함" 체크 후에만 Approve. 미체크 시 서버 차단.
- **alias 검증 강화:** (a) canonical이 stop_master에 존재해야만 저장. (b) alias_text 길이 <=2 차단. 검증 실패 시 alias 저장 금지.
- **검증:** sql/v0.6-21_validation.sql (8개 쿼리: LOW pending/approved, alias canonical 존재, alias_text 길이, 회귀)
- **매칭 로직/SoT 불변:** 승인/등록 게이트만 강화.

## v0.6-20 요약

- **Import 스크립트:** scripts/import_seoul_bus_stop_master_full.php (euc-kr CSV → UTF-8, UPSERT, idempotent)
- **입력:** data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv (Git 커밋 금지)
- **검증:** sql/v0.6-20_validation.sql (9개 쿼리: 건수, 인덱스, EXPLAIN, match_method 분포)
- **인덱스:** stop_name 인덱스 확인만 (v0.6-10 기존). 추가 인덱스는 v0.6-21로 미룸.
- **실행:** Cursor PC 앱(터미널)에서만. 매칭 로직/SoT 불변.

## v0.6-19 요약

- **only_low 필터:** GET only_low=1 시 match_method='like_prefix'만 표시. only_unmatched와 동시 사용 가능.
- **토글 링크:** "LOW만 보기" / "LOW 해제" 추가. only_unmatched와 파라미터 조합 유지.
- **Promote 경고:** low_confidence_cnt/auto_matched_cnt >= 30% 시 경고 문구. "주의: like_prefix(LOW) 비중이 높습니다. Promote 전 후보를 재검토하세요."
- **SQL 없음:** 화면 필터/표시만. 매칭 로직/SoT 불변.

## v0.6-18 요약

- **매칭 신뢰도 표시:** route_review Candidates에 컬럼 1개 추가. exact/alias_live_rematch/alias_exact → HIGH, normalized/alias_normalized → MED, like_prefix → LOW, 그 외/NULL → NONE. 텍스트만 표시.
- **summary 집계:** latest 스냅샷 기준 auto_matched_cnt, low_confidence_cnt(like_prefix), none_matched_cnt, alias_used_cnt. only_unmatched=1일 때도 동일 기준.
- **검증:** sql/v0.6-18_validation.sql 에 검증 쿼리 7개 (주석 블록, Workbench 실행용). 매칭 로직/SoT 변경 없음.

## SQL 검증 (GPT 대화에서)

- **GPT는 SQL을 실행할 수 없음.** 사용자가 Workbench(또는 Cursor 터미널)에서 실행 후, **실행 결과를 GPT 대화창에 붙여넣으면** GPT가 결과를 해석하고 다음 지시(정상/추가 점검 등)를 낸다.
- 절차: (1) GPT에게 "검증 쿼리 알려줘" 요청 → (2) GPT가 sql/v0.6-18_validation.sql 기준으로 쿼리+치환값 제시 → (3) 사용자 실행 → (4) 결과 붙여넣기 → (5) GPT가 다음 지시.

## 제약

- 새 테이블/페이지 추가 금지 (지시 없는 한).
- 풀스캔·LIKE %...% 금지.
- SoT·stale 차단·promote 규칙 훼손 금지.
