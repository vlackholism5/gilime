# CTO 리포트 — 로드맵 설계 Plan 1

## 1. 개요

- **목적:** MVP+ ~ Phase-2 설계 확정. 전체 시스템 아키텍처, UI/UX(네이버지도 방향), 운영/배포를 문서로 확정하고 7역할 리뷰 후 Decision Log 및 본 리포트로 마무리한다.
- **제약:** LOCK-1(SoT 07/08/09, OPS_DB_MIGRATIONS 의미 변경 금지), LOCK-2(과거 스냅샷 문서 삭제/리라이트 금지), LOCK-3(전체 리라이트 금지, 제안은 Why/Risk/Minimal Fix/Verification 형태). **신규 문서 4개만 추가.**

---

## 2. FACT / ASSUMPTION

**FACT (확정)**

- STEP4~STEP8 파이프라인 및 E1/E2 API 구현 완료. public/api/index.php, app/inc/api/g1_station_lines.php.
- docs/UX/UX_FLOW_LOCK_G1_v1.md: 듀얼 진입, MVP/MVP+/Phase-2 범위, E1/E2 UI 위치, ambiguous/unresolved 표시 정책 고정.
- docs/references/ROADMAP_v1_7.md Phase-2 절: 지도 기반 UI, 경로 폴리라인, 길찾기 엔진 = Phase-2. docs/operations/PLAN_UX_OPERATIONS_ROUTE_FINDER_v1_7.md Part 3, docs/ux/NAVER_MAP_UI_ADOPTION_v1_8.md 참조.
- docs/SOT/05_SYSTEM_ARCHITECTURE.md: SoT to DB to API to UI to Ops 다이어그램 존재. G1/E1/E2는 "추가 문서"로만 확장(LOCK-1).
- docs/OPS/OPS_G1_RUNBOOK.md: 뷰 롤백(DROP VIEW), G1 비활성화(가설) 명시.

**ASSUMPTION (확인 필요)**

- Phase-2 지도 API는 네이버/Kakao 중 선택; 라이선스·비용은 Phase-2 착수 전 결정.
- 폴리라인·정류장 좌표 소스: subway_edges_g1 또는 별도 위경도 테이블; "확인 필요"로 문서에만 기재.
- 배포: 로컬 to 단일 서버 가정(스케일아웃·CI는 Roadmap 이후).

---

## 3. Roadmap 단계

| 단계 | 범위 | UI | API/데이터 | 비고 |
|------|------|-----|------------|------|
| MVP | 출발/도착, 경로 목록(버스+임시셔틀), 이슈 Top3, 구독·알림 | 지도 없음. 목록·입력만 | E1/E2 구현됨. route_finder 기존 | UX_FLOW_LOCK_G1_v1 |
| MVP+ | 2~3 결과, 경로 옵션, 정합성/회귀 검증 | 동일(지도 없음) | G1 스모크·정합성 체크 문서화 완료 | OPS_G1_SMOKE_REGression 등 |
| Phase-2 | 지도 기반 UI, 경로 폴리라인, 길찾기 엔진 | 지도 렌더링, 폴리라인·마커 | 지도 API 연동, 좌표 소스 확정 | NAVER_MAP_UI_ADOPTION Phase 2 |
| Phase-3 | 버스/셔틀/OSM/도보 네트워크 확장 | Phase-2 확장 | 다중 수단·데이터 소스 | 설계만 언급, 상세는 추후 |

---

## 4. 시스템 아키텍처 요약

- **단일 기준:** docs/SOT/10_ROADMAP_SYSTEM_ARCHITECTURE.md. SoT 05는 수정하지 않음.
- **구성:** SoT/문서 to DB(기존 + subway_*, vw_subway_station_lines_g1) to API(기존 + E1/E2) to UI(Admin/User) to Ops(스크립트·검증).
- **데이터 플로우:** G1 CSV to STEP4~5 to subway_stations_master, subway_edges_g1 to VIEW to E1/E2. 경로 검색(버스/셔틀) 기존 유지.
- **런타임:** User 요청 to public/api 또는 public/user to E1/E2 path 시 g1_station_lines to PDO + observability.

---

## 5. UI/UX 요약

- 네이버지도 방향 채택: docs/UX/NAVER_MAP_UI_SYSTEM_APPLY_v1_0.md에서 채택 원칙 요약, 검색·경로 카드·필터 탭 참조, 색상·토큰은 길라임 SOT 유지.
- **지도 없는 MVP 규칙:** 출발/도착 입력 + 경로 찾기 + 경로 목록(텍스트/카드)만으로 완결. "지도 보기"는 Phase-2 전까지 비노출 또는 비활성. 지도는 Phase-2에서만 렌더링.

---

## 6. Gap 리스트

| 우선순위 | Gap | 위험 | 최소 수정 | 검증 | 담당 |
|----------|-----|------|-----------|------|------|
| P0 | 로드맵 전체 아키텍처가 한 문서에 없음 | Phase-2 착수 시 범위·의존성 불명확 | SOT/10 신규 작성 | 10에 MVP/MVP+/Phase-2 표 + G1 연결점 문단 존재 | CTO |
| P0 | "지도 없는 MVP" UX가 공식 규칙으로 없음 | 지도 미구현 시 UI 일관성 훼손 | NAVER_MAP_UI_SYSTEM_APPLY_v1_0.md 신규, 무지도 규칙 절 | "지도 없이도 UX 무너지지 않음" 문구 존재 | UX Lead |
| P1 | 배포·운영이 문서로 정리되지 않음 | 로컬 to 서버·롤백 시 절차 불명 | OPS_DEPLOYMENT_ROADMAP.md 신규(가설 허용) | 배포 절차·rollback·trace_id/검증 1곳 이상 | SRE/Release |
| P1 | Phase-2 리스크(지도/폴리라인/데이터)가 결정 로그에 없음 | 착수 후 재작업 | CTO_REPORT_ROADMAP_PLAN_1에 리스크·가설 명시 | 리포트에 리스크 표 또는 문단 존재 | CTO |
| P2 | Phase-3(버스/셔틀/OSM/도보) 위치만 언급 | 장기 로드맵 참조 부재 | SOT/10에 Phase-3 1~2문장 | "Phase-3" 검색 시 1곳 이상 | PM |

---

## 7. Decision Log (LOCK)

| ID | 결정 | 근거 | 상태 |
|----|------|------|------|
| D1 | SoT 07/08/09, OPS_DB_MIGRATIONS(STEP4~STEP9), OPS_OBSERVABILITY_G1_API의 의미를 바꾸는 수정 금지. 필요 시 "추가 문서"로만 확장 | LOCK-1 | LOCK |
| D2 | 과거 스냅샷 문서는 삭제/리라이트 금지. 상단 1문단 "현재 상태" 정정 또는 1~2문장 최소 수정만 | LOCK-2 | LOCK |
| D3 | 전체 코드 교체·파일 단위 전체 리라이트 금지. 모든 제안은 (Why)(Risk)(Minimal Fix)(Verification) | LOCK-3 | LOCK |
| D4 | Roadmap 단계: MVP(현행) to MVP+(검증 강화) to Phase-2(지도·폴리라인·길찾기 엔진) to Phase-3(다중 수단 확장) | UX_FLOW_LOCK, ROADMAP_v1_7 Phase-2 절 | LOCK |
| D5 | 지도 기반 UI는 Phase-2에서만 렌더링. MVP/MVP+는 지도 없이 출발/도착·경로 목록으로 완결 | NAVER_MAP_UI_ADOPTION, PLAN_UX Part 3 | LOCK |
| D6 | 아키텍처 확장은 docs/SOT/10_ROADMAP_SYSTEM_ARCHITECTURE.md로 단일 기준. SoT 05는 수정하지 않음 | 단일 기준, SoT 의미 유지 | LOCK |

---

## 8. Action Items

| ID | 작업 | 파일 | 변경 요약 | 검증 |
|----|------|------|-----------|------|
| A1 | Roadmap 전체 시스템 아키텍처 문서 신규 | docs/SOT/10_ROADMAP_SYSTEM_ARCHITECTURE.md | SoT 05와 관계 1문단, 컴포넌트/데이터·런타임 플로우, MVP/MVP+/Phase-2 표, G1 to UX 연결점, Phase-3 1~2문장 | 10 존재, "Phase-2", "E1/E2" 검색 시 명시 |
| A2 | 네이버지도 UX 적용 + 지도 없는 MVP 규칙 문서 신규 | docs/UX/NAVER_MAP_UI_SYSTEM_APPLY_v1_0.md | 채택 원칙 요약, 화면 구성, 지도 Phase-2만, "지도 없이도 UX 무너지지 않음" 규칙 | 문서 존재, "지도 없이" 문구 있음 |
| A3 | 배포·운영 로드맵 문서 신규 | docs/OPS/OPS_DEPLOYMENT_ROADMAP.md | MVP/MVP+ 배포 방식(가설 OK), 운영 로그/trace_id/검증 절차, rollback(뷰/엔드포인트/데이터) | 문서 존재, "rollback" 또는 "배포" 검색 시 1곳 이상 |
| A4 | 로드맵 설계 CTO 리포트 신규 | docs/OPS/CTO_REPORT_ROADMAP_PLAN_1.md | 왜 이 순서인지, 리스크(지도/폴리라인/데이터 부족), 최소 실행 플랜, 검증 체크리스트 | Decision Log, Action Items, Verification checklist 포함 |

---

## 9. 리스크

- **지도 API 비용·라이선스:** 네이버/Kakao 선택 및 비용이 Phase-2 착수 전에 확정되지 않으면 일정·범위 변동 가능.
- **폴리라인 좌표 소스 미확정:** subway_edges_g1 또는 별도 위경도 테이블 미정 시 Phase-2 지도 뷰 구현 지연 가능.
- **데이터 부족:** 정류장·노선 데이터 품질 또는 커버리지 부족 시 Phase-2 길찾기 엔진 품질 저하 또는 지연 가능.

---

## 10. 최소 실행 플랜

- **순서:** A1 to A2 to A3 to A4.
- **A1 완료 기준:** docs/SOT/10_ROADMAP_SYSTEM_ARCHITECTURE.md 존재, SoT 05와 관계·컴포넌트·플로우·표·G1 to UX·Phase-3 문단 포함.
- **A2 완료 기준:** docs/UX/NAVER_MAP_UI_SYSTEM_APPLY_v1_0.md 존재, 채택 원칙·화면 구성·지도 Phase-2만·"지도 없이도 UX 무너지지 않음" 규칙 포함.
- **A3 완료 기준:** docs/OPS/OPS_DEPLOYMENT_ROADMAP.md 존재, 배포 방식·trace_id/검증·rollback 절 포함.
- **A4 완료 기준:** docs/OPS/CTO_REPORT_ROADMAP_PLAN_1.md 존재, 개요·FACT/ASSUMPTION·Roadmap 표·아키텍처·UI/UX 요약·Gap·Decision Log·Action Items·리스크·최소 실행 플랜·Verification 체크리스트 포함.

---

## 11. Verification 체크리스트

**문서 존재**

- docs/SOT/10_ROADMAP_SYSTEM_ARCHITECTURE.md 존재. "Phase-2", "E1/E2", "G1" 검색 시 문단 또는 표 있음.
- docs/UX/NAVER_MAP_UI_SYSTEM_APPLY_v1_0.md 존재. "지도 없이", "Phase-2" 검색 시 문단 있음.
- docs/OPS/OPS_DEPLOYMENT_ROADMAP.md 존재. "배포" 또는 "rollback" 검색 시 절 있음.
- docs/OPS/CTO_REPORT_ROADMAP_PLAN_1.md 존재. "Decision Log", "Action Items", "Verification" 검색 시 절 있음.

**SoT 비변경**

- docs/SOT/07_GRAPH_QUERY_RULES_G1.md, 08_*.md, 09_*.md 내용 비교(의미 변경 없음). docs/OPS/OPS_DB_MIGRATIONS.md STEP4~STEP9 문단 변경 없음.

**기존 동작 유지(선택)**

- E1 by-name: `curl -s "http://localhost/gilime_mvp_01/public/api/index.php?path=g1/station-lines/by-name&station_name=서울역"` to 200, data.line_codes 존재.
- E2 by-code: `curl -s "http://localhost/gilime_mvp_01/public/api/index.php?path=g1/station-lines/by-code&station_cd=0150"` to 200.
