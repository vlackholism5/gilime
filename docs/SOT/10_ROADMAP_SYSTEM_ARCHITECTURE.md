# SoT 10 — Roadmap 시스템 아키텍처

## 목적

MVP+ 이후 ~ Phase-2(지도 기반 UI, 길찾기 엔진) 로드맵 기준의 전체 시스템 아키텍처를 단일 문서로 확정한다. **SoT 05는 현재 운영 기준**이며, 본 문서(10)는 **MVP+~Phase-2 로드맵 기준 확장**을 기술한다. SoT 05의 의미는 변경하지 않는다.

---

## SoT 05와의 관계

- **docs/SOT/05_SYSTEM_ARCHITECTURE.md:** 현재 운영 중인 시스템 구조·데이터 플로우의 단일 기준(SoT → DB → API → UI → Ops).
- **본 문서(10):** 동일 흐름을 전제로, G1 데이터·E1/E2 API·Phase-2 지도/길찾기 엔진 확장 위치를 추가로 정의. 05를 수정하지 않고 "추가 문서"로만 확장.

---

## 컴포넌트 개요

| 계층 | 구성 요소 | 비고 |
|------|-----------|------|
| SoT/문서 | docs/SOT/*, STATUS_FOR_GPT, INDEX, ui/SOT_GILAIME_UI_SYSTEM, UX_FLOW_LOCK_G1_v1 | 05와 동일 + UX LOCK |
| DB | app_*, shuttle_*, seoul_bus_* (05와 동일) + **subway_stations_master, subway_edges_g1**, **vw_subway_station_lines_g1** | G1 테이블·뷰 |
| API | public/api/index.php (path=: debug/ping, subscription/toggle, **g1/station-lines/by-name, by-code**), public/api/route/suggest_stops.php | E1/E2 추가 |
| UI | Admin: public/admin/*, User: public/user/* (home, route_finder, issues 등), Assets: gilaime_ui.css | 05와 동일. 지도는 Phase-2에서만 |
| Ops | scripts/, docs/releases/v1.7, sql/validate, docs/OPS (OPS_DB_MIGRATIONS, OPS_G1_RUNBOOK 등) | 검증·롤백 문서 포함 |

---

## 데이터 플로우 (G1)

1. **G1 지하철:** CSV(STEP4~5) → subway_stations_master, subway_edges_g1 → vw_subway_station_lines_g1(VIEW) → E1/E2 API (by-name, by-code). 계약: docs/SOT/07, 08, 09.
2. **경로 검색(버스/셔틀):** 기존 플로우 유지. 구독·승격된 route_stop, suggest_stops API, seoul_bus_stop_master 등.
3. **Phase-2 확장(가정):** 지도 API 연동, 폴리라인·정류장 좌표 소스 확정 시 동일 DB·API 위에 시각화 레이어 추가.

---

## 런타임 플로우 (E1/E2)

- User/Client 요청 → public/api/index.php (path=) 또는 public/user/*.
- path가 g1/station-lines/by-name 또는 by-code 이면: app/inc/auth/db.php, app/inc/api/g1_station_lines.php 로드 → g1_station_lines_lookup(PDO, endpoint, bind_key, bind_value) → v0.8-10 SELECT 바인딩 → PDO 실행 + observability(api_enter, db_query_start, db_query_end, api_exit).
- 응답: 200 + data(SOT/09 스키마) 또는 404 not_found / 400 bad_request. trace_id 포함.

---

## MVP / MVP+ / Phase-2 경계

| 단계 | 범위 | UI | API/데이터 |
|------|------|-----|------------|
| MVP | 출발/도착, 경로 목록(버스+임시셔틀), 이슈 Top3, 구독·알림 | 지도 없음. 목록·입력만 | E1/E2 구현됨. route_finder 기존 |
| MVP+ | 2~3 결과, 경로 옵션, 정합성/회귀 검증 | 동일(지도 없음) | G1 스모크·정합성 체크 문서화 완료 |
| Phase-2 | 지도 기반 UI, 경로 폴리라인, 길찾기 엔진 | 지도 렌더링, 폴리라인·마커 | 지도 API 연동, 좌표 소스 확정 |

---

## G1 API(E1/E2) → UX 연결점

- **U-JNY-01 출발/도착 입력:** 역명·역코드 자동완성/검증, 지하철 노선 후보 표시(향후 연동). 구현: GET .../api/index.php?path=g1/station-lines/by-name 또는 by-code. 계약: docs/SOT/09_API_CONTRACTS_G1.md.
- **경로 결과 화면:** 역명 기준 지하철 노선 표시(구현됨). route_finder.php step=result에서 E1 by-name 사용.
- **현행 UX 기준:** docs/UX/UX_FLOW_LOCK_G1_v1.md (듀얼 진입, ambiguous/unresolved 표시 정책).

---

## 추후 확장 위치 (Phase-3)

- **버스/셔틀/OSM/도보 네트워크:** 다중 수단·데이터 소스 확장은 Phase-3로 두고, 상세 설계는 Phase-2 착수 후 결정. 현재는 SOT/10에서 "추후 확장"으로만 위치를 명시.
