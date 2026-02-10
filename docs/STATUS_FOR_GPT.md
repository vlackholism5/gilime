# GILIME MVP — 상태 요약 (GPT/운영 참고)

## 현재 버전

- **v0.6-18** 반영 완료.

## SoT (변경 금지)

1. latest PARSE_MATCH 스냅샷 기준 후보 표시 (created_job_id=latest).
2. stale 후보 승인/거절 차단 (UI+서버).
3. promote는 latest PARSE_MATCH만 허용.
4. route_stop 스냅샷 누적 (기존 active is_active=0, 신규만 is_active=1).
5. alias 등록 시 latest 후보 1건 live rematch (가능할 때만).
6. 추천 canonical 계산은 only_unmatched=1에서만 (캐시 포함) — v0.6-17 고정.

## v0.6-18 요약

- **매칭 신뢰도 표시:** route_review Candidates에 컬럼 1개 추가. exact/alias_live_rematch/alias_exact → HIGH, normalized/alias_normalized → MED, like_prefix → LOW, 그 외/NULL → NONE. 텍스트만 표시.
- **summary 집계:** latest 스냅샷 기준 auto_matched_cnt, low_confidence_cnt(like_prefix), none_matched_cnt, alias_used_cnt. only_unmatched=1일 때도 동일 기준.
- **검증:** sql/v0.6-18_validation.sql 에 검증 쿼리 7개 (주석 블록, Workbench 실행용). 매칭 로직/SoT 변경 없음.

## 제약

- 새 테이블/페이지 추가 금지 (지시 없는 한).
- 풀스캔·LIKE %...% 금지.
- SoT·stale 차단·promote 규칙 훼손 금지.
