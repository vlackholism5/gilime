# UX 플로우 LOCK (G1 v1)

현행 유저 플로우·UI 범위·G1 API 사용 위치·ambiguous/unresolved 표시 정책을 한 문서에 고정한다. 출처: 와이어프레임 v1.8, SOT 07/08/09, OPS_DB_MIGRATIONS STEP9.

---

## 1. 유저 플로우 LOCK

- **"이슈 먼저 선택 vs 길찾기 먼저" 결정:** **듀얼 진입** (와이어프레임 v1.8과 동일).
  - (A) 길찾기부터 시작: 메인에서 출발/도착 입력 후 경로 찾기.
  - (B) 이슈 클릭 후 이슈 컨텍스트 기반 길찾기: 이슈 상세에서 [이슈 기반 길찾기] CTA로 진입.
- 출처: docs/ux/WIREFRAME_ISSUE_FIRST_ROUTE_FINDER_v1_8.md.

---

## 2. MVP / MVP+ / Roadmap 단계별 UI 범위

| 단계 | UI 범위 |
|------|---------|
| **MVP** | 출발/도착 입력, 경로 목록(버스+임시 셔틀), 이슈 Top3·이슈 기반 길찾기 CTA, 구독·알림. |
| **MVP+** | 2~3 결과, 추가 경로 옵션 등. (docs/SOT/04_MVP_PRD.md 참조) |
| **Roadmap(Phase-2)** | 지도 기반 UI, 경로 폴리라인, 길찾기 엔진. docs/operations/PLAN_UX_OPERATIONS_ROUTE_FINDER_v1_7.md Part 3, docs/ux/NAVER_MAP_UI_ADOPTION_v1_8.md. |

---

## 3. G1 E1/E2 API가 UI에서 쓰이는 위치

- **U-JNY-01 출발/도착 입력:** 역명·역코드 자동완성/검증, 지하철 노선 후보 표시(향후 연동).
- **경로 결과(route_finder.php step=result):** 출발/도착 역명으로 E1 by-name 호출, 지하철 노선 표시. 현재 구현됨.
- **구현:** GET `.../api/index.php?path=g1/station-lines/by-name` 또는 `path=g1/station-lines/by-code`.
- **계약:** docs/SOT/09_API_CONTRACTS_G1.md.

---

## 4. ambiguous / unresolved 처리 방식 (표시 정책)

| line_codes_source / meta.line_code_source | UI 표시 권장 |
|-------------------------------------------|--------------|
| view + ambiguous | 예: "1, 4호선 환승" (후보 집합 표시) |
| master only (edges_unique) | 예: "1호선" |
| none (unresolved) | "노선 미연결" 또는 비표시. not_found(404)와 구분. |

- SoT 07 R3, SoT 09 예시와 일치. blank line_code는 에러가 아님.

---

## 5. 확인 필요 항목

- 지도 렌더링 라이브러리/API 선택.
- 폴리라인 좌표 소스(정류장 위경도).
- 지하철 구간 데이터 제공 방식.

이 항목들은 Phase-2에서 결정.

---

## 6. Roadmap에서 지도 기반 UI + 길찾기 엔진 위치

- **Phase-2(로드맵).** 기존 과거 문서의 지도/길찾기 언급은 삭제하지 않고 "Phase-2(로드맵)"로 정합.
- docs/references/ROADMAP_v1_7.md에 Phase-2 링크 1~2문장 추가됨.
