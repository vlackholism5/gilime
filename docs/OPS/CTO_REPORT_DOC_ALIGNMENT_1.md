# CTO 문서 정합성 감사 리포트 (Doc Alignment 1)

문서 전용 정렬 워크숍 결과. 코드/DB 변경 없음. 최소 diff만 적용.

---

## 1) 개요

- **목적:** 현재 SoT(07/08/09, OPS_DB_MIGRATIONS STEP4~STEP9, OPS_OBSERVABILITY_G1_API)와 일치하도록 UIUX/와이어프레임/규칙/운영 문서의 충돌을 제거하고, 현행 UX 플로우를 한 문서에 고정.
- **SoT 목록:** docs/SOT/07_GRAPH_QUERY_RULES_G1.md, 08_RUNTIME_QUERY_CONTRACTS_G1.md, 09_API_CONTRACTS_G1.md, sql/validate/v0.8-10_api_query_bindings_g1.sql, docs/OPS/OPS_OBSERVABILITY_G1_API.md, docs/OPS/OPS_DB_MIGRATIONS.md (STEP4~STEP9).
- **원칙:** 코드/DB 변경 금지. 문서만 1~2문장 또는 표 1칸 단위 최소 수정. SoT 문서는 의미 변경 금지, 극소량 정리만. 신규 문서 2개만 허용(UX_FLOW_LOCK_G1_v1.md, 본 리포트).

---

## 2) 충돌 목록 (11건)

| 번호 | 문서 | Why | Risk | Minimal Fix | Verification |
|------|------|-----|------|-------------|--------------|
| 1 | DEBUG_OBSERVABILITY.md | E1/E2 구현 완료 후에도 "API 진입점 없음" 기재 | API 존재 오인·누락 | 표 셀: 현재 api/index.php + path= (debug, subscription, g1/by-name, by-code) 반영 | grep "g1", "api/index.php" 존재 |
| 2 | CTO_POST_IMPL_REVIEW_MEETING_MINUTES.md | 회의록이 "E1/E2 spec-only" 등 구현 전 스냅샷 | E1/E2 미구현으로 오인 | 상단 "문서 상태" 1문단 추가. 본문 삭제 없음 | 상단에 "구현 완료", "STEP9" 참조 |
| 3 | (여러 문서) | 이슈 vs 길찾기 진입 결정이 흩어짐 | 잘못된 플로우 해석 | UX_FLOW_LOCK_G1_v1.md 신규, 듀얼 진입 LOCK | UX_FLOW_LOCK에 듀얼 진입·MVP/Roadmap·E1/E2 위치·표시 정책 포함 |
| 4 | SOT 04, ROADMAP 등 | 지도·길찾기 엔진 로드맵 위치 미명시 | Phase-2 위치 찾기 어려움 | UX_FLOW_LOCK + ROADMAP_v1_7.md Phase-2 절 1~2문장 추가 | "지도", "길찾기 엔진", "Phase-2" 검색 시 1곳 이상 |
| 5 | SOT_GILAIME_UI_SYSTEM.md | G1 E1/E2 UI 사용 위치 미문서화 | 프론트 연동 시 참조 부재 | 참조 절에 UX_FLOW_LOCK 1줄 추가 | "E1", "E2", "station-lines" 검색 시 명시 |
| 6 | (SoT 07/09) | ambiguous/unresolved UI 표시 규칙 한 곳 없음 | "노선 없음" 오표시·unresolved 혼동 | UX_FLOW_LOCK에 표 1개 추가. SoT 수정 없음 | UX_FLOW_LOCK 내 "ambiguous", "unresolved", "표시" 존재 |
| 7 | SOT 09 | Path만 기술, 실제 구현은 index.php?path= | 클라이언트 404 오인 | Endpoints 표 아래 구현 경로 1줄 추가 | SOT 09에 "index.php", "path=" 존재 |
| 8 | OPS_OBSERVABILITY_G1_API.md | "future implementation", "STEP8" 톤 | Observability 검증 누락 | 서두 1문장: E1/E2 STEP9 구현 완료, 본 문서는 로그·트레이스 계약 | 상단 "구현 완료" 또는 "STEP9" |
| 9 | INDEX.md | G1 SoT·OPS 링크 없음 | SoT/운영 문서 발견 어려움 | 참조 문서 절에 G1 SoT·G1 운영 불릿 2~3개 추가 | "07", "09", "OPS_DB_MIGRATIONS", "G1" 검색 시 링크 |
| 10 | RESET/01_INVENTORY.md | API 목록에 E1/E2 없음 | 테스트·점검 시 E1/E2 누락 | API 테이블에 E1/E2 2행 추가 | "g1", "station-lines" 2건 이상 |
| 11 | OPS_DB_MIGRATIONS.md | UX 문서 참조 없음 | 현행 UX 기준 문서 찾기 어려움 | STEP9 끝에 UX_FLOW_LOCK 링크 1줄 추가 | "UX_FLOW_LOCK" 또는 "UX" 1건 |

---

## 3) 우선순위

- **P0 (SoT 직접 모순·API 존재 부정):** 충돌 1, 2
- **P1 (회의록/인벤토리/인덱스·SoT 정리):** 충돌 7, 8, 9, 10, 11
- **P2 (UX 정리·로드맵 링크):** 충돌 3, 4, 5, 6

---

## 4) Decision Log (LOCK)

- **이슈 vs 길찾기 진입:** 듀얼 진입 LOCK (와이어프레임 v1.8). (A) 길찾기부터 (B) 이슈 클릭 후 이슈 기반 길찾기.
- **E1/E2 구현 완료.** DEBUG_OBSERVABILITY·CTO 회의록은 과거 스냅샷이며, 상단/표 수정으로 현재 상태만 반영. 본문 삭제 없음.
- **지도/길찾기 엔진:** Phase-2(로드맵). 기존 Phase 2 문구 삭제하지 않고 링크로 정합.

---

## 5) Action Items

- [x] 문서 정합성 감사 반영(위 Minimal Fix 실행)
- [x] docs/UX/UX_FLOW_LOCK_G1_v1.md 생성
- [x] OPS_DB_MIGRATIONS에 UX 문서 링크 1줄 추가
- [x] 본 CTO_REPORT 작성

---

## 6) 변경 파일 목록 및 Diff 요약

| 파일 | 유형 | diff summary |
|------|------|--------------|
| docs/operations/DEBUG_OBSERVABILITY.md | 수정 | API 진입점 표: "없음" → 현재 api/index.php + path= (debug, subscription, g1/by-name, by-code) 반영 |
| docs/OPS/CTO_POST_IMPL_REVIEW_MEETING_MINUTES.md | 수정 | 상단 "문서 상태" 1문단: E1/E2 구현 완료(STEP9) 명시. 본문 삭제 없음 |
| docs/UX/UX_FLOW_LOCK_G1_v1.md | 신규 | 유저 플로우 LOCK(듀얼 진입), MVP/MVP+/Roadmap, E1/E2 UI 위치, ambiguous/unresolved 표시 표, 확인 필요, Roadmap Phase-2 |
| docs/references/ROADMAP_v1_7.md | 수정 | Phase-2(지도·길찾기 엔진) 절 1~2문장 + 링크 추가 |
| docs/ui/SOT_GILAIME_UI_SYSTEM.md | 수정 | 참조 절에 G1 API UX 1줄: UX_FLOW_LOCK_G1_v1.md |
| docs/SOT/09_API_CONTRACTS_G1.md | 수정 | Endpoints 표 아래 구현 경로 1줄: index.php?path=g1/station-lines/... |
| docs/OPS/OPS_OBSERVABILITY_G1_API.md | 수정 | Purpose 서두 1문장: E1/E2 STEP9 구현 완료, 본 문서는 로그·트레이스 계약 |
| docs/INDEX.md | 수정 | 참조 문서 절에 G1 SoT 07/08/09, G1 운영(OPS_DB_MIGRATIONS, OPS_G1_*) 불릿 추가 |
| docs/RESET/01_INVENTORY.md | 수정 | API 테이블에 E1/E2 2행 추가 |
| docs/OPS/OPS_DB_MIGRATIONS.md | 수정 | STEP9 끝에 현행 UX 플로우 링크 1줄: docs/UX/UX_FLOW_LOCK_G1_v1.md |
| docs/OPS/CTO_REPORT_DOC_ALIGNMENT_1.md | 신규 | 충돌 목록, 우선순위, Decision Log, Action Items, 변경 파일 표·diff summary |
