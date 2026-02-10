# v1.0 RC 게이트 체크리스트

**원칙:** 검증은 이 문서로 1회만 수행한다.

## 게이트 6개

1. **stop_master 실데이터 + 인덱스** — v0.6-20. 건수 11,461, stop_name 인덱스, EXPLAIN type/key 적절.
2. **LOW 승인 게이트** — v0.6-21. like_prefix pending 시 "LOW 확인" 체크 없이 Approve 시 서버 차단.
3. **alias 검증** — v0.6-21. canonical 미존재/alias_text<=2 시 저장 차단. (기존 alias_text<=2 3건은 Known Issues)
4. **PARSE_MATCH 품질 지표** — v0.6-22. run_job 후 shuttle_parse_metrics 저장, doc.php Metrics 표 노출.
5. **운영 동선·필터** — v0.6-24~38. route_review show_advanced 기본 숨김, doc→route_review 진입 시 단순뷰(show_advanced=0).
6. **SoT 유지** — stale 후보 승인/거절 차단, promote는 latest만, 매칭 로직/순서/점수 변경 없음.

## 확인 방법

- **브라우저 3개:** (1) route_review 진입 → 화면 로딩·Candidates 표. (2) doc.php에서 route_label 클릭 → route_review 진입 시 show_advanced=0(기본 문구 "기본(고급숨김)"). (3) Approve 1회 → redirect 후 필터 유지.
- **SQL 1개:** v0.6-20/v0.6-21 검증 쿼리 중 건수·인덱스·alias_text 길이 분포 등 1회 샘플 실행 후 결과 확인.
