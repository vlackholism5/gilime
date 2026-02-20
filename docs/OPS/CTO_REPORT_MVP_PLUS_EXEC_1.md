# CTO 리포트 — MVP+ 유저 길찾기 실행 1

## 0. 개요

MVP+ 유저 길찾기 핵심 플로우 실행: E1/E2를 UI(경로 결과)에서 사용하고, 구독 최소 연결 및 문서 정합 유지. 지도/폴리라인/외부 길찾기 API는 미구현(Phase-2).

---

## 1. FACT / ASSUMPTION

**FACT**

- E1/E2 구현 완료: public/api/index.php, app/inc/api/g1_station_lines.php.
- 경로 검색: route_finder_resolve_stop + route_finder_search(버스+셔틀). 자동완성: suggest_stops만 사용.
- 경로 결과(route_finder.php step=result)에서 출발/도착 역명으로 E1 by-name(서버 사이드 g1_station_lines_lookup) 호출 후 지하철 노선 문구 표시 반영함.
- 구독 안내: 경로 결과 카드 상단에 "구독 노선은 마이노선에서 확인할 수 있습니다." 문구 및 my_routes 링크 추가함.

**ASSUMPTION**

- E1 "UI에서 1회 이상 사용"은 경로 결과(step=result)에서 서버 사이드 g1_station_lines_lookup 호출로 충족.
- Observability 검증은 기존 E1 curl 스모크(public/api/index.php 경로)로 수행. UI에서 서버 사이드 호출 시 해당 요청에서는 index.php를 타지 않음.

---

## 2. Decision Log (D1~)

| ID | 결정 | 비고 |
|----|------|------|
| D1 | E1/E2 UI 연동은 경로 결과 페이지에서 출발/도착 역명으로 E1 by-name(서버 사이드 lookup) 호출 후 지하철 노선 문구 표시로 수행 | 단일 파일 route_finder.php에서 처리 |
| D2 | 지도/폴리라인/외부 길찾기 API는 Phase-2까지 미구현(LOCK-2) | 변경 없음 |
| D3 | 구독 최소 연결은 "구독 노선은 마이노선에서 확인" 문구+링크로 수행 | 구독 뱃지는 미적용 |
| D4 | SoT 07/08/09/10, UX_FLOW_LOCK 의미 변경 없이 "현재 구현됨" 문구만 추가(LOCK-1) | 적용 완료 |

---

## 3. Action Items

| ID | 작업 | 상태 |
|----|------|------|
| A1 | route_finder.php에 g1_station_lines require 및 result 시 from/to E1 lookup, 노선 표시 | 완료 |
| A2 | route_finder.php에 구독 안내 1줄 + my_routes 링크 | 완료 |
| A3 | UX_FLOW_LOCK_G1_v1.md "G1 E1/E2 API가 UI에서 쓰이는 위치"에 경로 결과·구현됨 반영 | 완료 |
| A4 | SOT/10 "G1 API(E1/E2)→UX 연결점"에 경로 결과·구현됨 1문장 | 완료 |
| A5 | CTO_REPORT_MVP_PLUS_EXEC_1.md 작성 및 검증 체크리스트 포함 | 완료 |

---

## 4. Verification checklist (API/UI 기본)

**E1 by-name (API 직접)**

- `curl -s "http://localhost/gilime_mvp_01/public/api/index.php?path=g1/station-lines/by-name&station_name=서울역"`
- 기대: 200, ok: true, data.line_codes 배열, data.line_codes_source, trace_id 존재.

**E2 by-code**

- `curl -s "http://localhost/gilime_mvp_01/public/api/index.php?path=g1/station-lines/by-code&station_cd=0150"`
- 기대: 200, 서울역 데이터.

**화면(유저 플로우)**

1. 홈에서 출발 "서울역", 도착 "청량리" 입력 후 경로 찾기.
2. 경로 결과 페이지에서 "출발: 서울역 (지하철 …)" "도착: 청량리 (지하철 …)" 문구 노출.
3. "구독 노선은 마이노선에서 확인할 수 있습니다" 문구 및 링크 노출.

**Observability (API 직접)**

- GILIME_DEBUG=1로 E1 curl 1회 후 error_log에 api_enter, db_query_start, db_query_end, api_exit 및 trace_id 확인.

**Not found / Unresolved**

- by-name "NonExistentStation" → 404.
- 문서상 unresolved 역코드(예: v0.8-06) by-code → 200, line_codes_source none 또는 동일 계약.

**문서**

- UX_FLOW_LOCK_G1_v1.md에 "경로 결과", "구현됨" 반영 확인.
- SOT/10에 "경로 결과 화면", "구현됨" 반영 확인.
- 본 CTO_REPORT 존재 및 체크리스트 포함 확인.

---

## 5. MVP+ 하드닝 추가 체크리스트 (UI 경유)

**입력값/라벨 처리**

- `public/user/route_finder.php`에서 `from`/`to`는 `trim` + 1~60자 제한 후 사용된다.
- `format_g1_line_label(null 또는 line_codes 없음/none)`은 `"노선 미연결"` 같은 고정 fallback 라벨을 반환해, E1 미적중 시에도 "출발: {역명} (지하철 노선 미연결)"처럼 깨지지 않는 문구로 노출된다.

**UI 플로우**

- `.../public/user/route_finder.php?step=result&from=서울역&to=청량리`
  - 출발/도착 옆에 노선 라벨이 표시된다.
  - "구독 노선은 마이노선에서 확인할 수 있습니다" 문구 및 링크가 경로 카드 상단에 노출된다.
- `.../public/user/route_finder.php?step=result&from=없는역&to=청량리`
  - 화면이 깨지지 않고, 출발/도착 옆에 fallback 라벨("노선 미연결" 등)이 표시된다.

**UI 경유 Observability**

- route_finder.php에서 E1 by-name은 `public/api/index.php`를 경유하지 않고 `g1_station_lines_lookup(PDO, 'by-name', 'station_name', $from/$to)`를 직접 호출한다.
- 따라서 **UI 경유 호출에서는** `api_enter` / `db_query_start` / `db_query_end` / `api_exit` 로그가 남지 않고, trace_id도 API 엔트리 기준으로는 부여되지 않는다.
- Observability 검증은 여전히 `public/api/index.php`를 통한 curl 스모크(E1/E2)로 수행하며, UI 경유 호출에 대해서는 "로그 없음(설계상)" 상태를 전제로 운영한다.

---

## 6. 경로 나오는 URL 예시 (구독 안내 문구 확인용)

경로 카드가 1건 이상 나와야 "구독 노선은 마이노선에서 확인할 수 있습니다" 문구가 노출된다. 버스 노선 데이터(`seoul_bus_route_stop_master`) 기준으로 **경로가 나오는** 출발/도착 쌍은 아래 스크립트로 조회한다.

**스크립트 실행 (실제 쌍·URL 출력)**

```bash
php scripts/php/list_route_finder_pairs_with_routes.php
```

**URL 형식**

- 기본: `http://localhost/gilime_mvp_01/public/user/route_finder.php?step=result&from={출발정류장명}&to={도착정류장명}`
- 한글은 URL 인코딩해도 되고, 브라우저 주소창에 그대로 입력해도 된다.

**예시 (스크립트 출력 결과를 그대로 사용 권장)**

- 스크립트가 정상 실행되면 상위 10개 쌍에 대한 URL이 출력된다. 예:
  - `http://localhost/gilime_mvp_01/public/user/route_finder.php?step=result&from=○○정류장&to=△△정류장`
- 데이터가 없거나 DB 오류 시 "(목록 없음)"이 나오면, 먼저 `import_seoul_bus_*` 시리즈로 버스 정류장·노선·경유 데이터를 적재한 뒤 다시 실행한다.

**정류장 ID 쌍으로 URL 만들기 (경로 있는 쌍)**

- 아래 쌍은 경로가 나오는 출발/도착으로 확인된 ID이다. 정류장명은 `seoul_bus_stop_master`에서 조회한다.
- **쌍 (from_stop_id, to_stop_id 또는 to_name):**
  - 232001137 → 232000291
  - 232001137 → 232000854
  - 232001137 → 232000856
  - 232000857 → 개화역광역환승센터
- **정류장명 조회 SQL:**  
  `SELECT stop_id, stop_name FROM seoul_bus_stop_master WHERE stop_id IN (232001137, 232000291, 232000854, 232000856, 232000857);`
- 조회한 `stop_name`을 아래 형식에 넣으면 된다.  
  `http://localhost/gilime_mvp_01/public/user/route_finder.php?step=result&from={출발정류장명}&to={도착정류장명}`  
  예: 도착이 "개화역광역환승센터"인 쌍은 위 SQL에서 232000857의 `stop_name`을 from에 넣고, to에는 `개화역광역환승센터`를 넣으면 된다.
- 스크립트: `php scripts/php/route_finder_urls_from_stop_ids.php` (위 ID 쌍으로 이름 조회 후 URL 출력 시도).

**지하철만 있는 구간 (경로 카드 0건)**

- 서울역↔청량리: 지하철 노선 문구(E1)만 나오고, 버스 경로는 없어 "경로를 찾을 수 없습니다"가 나올 수 있다. 이 경우 구독 안내 문구는 노출되지 않는다.

---

## 7. Rollback (가능 범위)

- route_finder.php에서 g1_station_lines require 및 G1 lookup·노선 표시 블록 제거 시 E1 UI 연동만 제거. API(index.php) 및 DB/뷰 변경 없음.
- 구독 안내 1줄 제거 시 해당 문구·링크만 삭제.

---

## 8. 변경 파일 요약

| 파일 | 변경 내용 |
|------|-----------|
| public/user/route_finder.php | require g1_station_lines.php; step=result 시 from/to로 g1_station_lines_lookup 2회, format_g1_line_label, 결과 문단에 지하철 노선 표시; 구독 안내 1줄 + my_routes 링크 |
| docs/UX/UX_FLOW_LOCK_G1_v1.md | 섹션 3에 "경로 결과(route_finder.php step=result) … 현재 구현됨." 1문장 추가 |
| docs/SOT/10_ROADMAP_SYSTEM_ARCHITECTURE.md | G1→UX 연결점에 "경로 결과 화면 … 구현됨" 1문장 추가 |
| docs/OPS/CTO_REPORT_MVP_PLUS_EXEC_1.md | 신규 생성 |
