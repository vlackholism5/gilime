# 길라임 플랜 방향 — 구현 체크·To-do 오더리스트·빌드 실행·검증

기준: [길라임_대화_맥락_정리](c:\Users\pv\.cursor\plans\길라임_대화_맥락_정리_7eb0844d.plan.md) 방향성.

---

## 1. 이미 구현된 것 (체크만)

### 1.1 RESET·SoT·인프라
- [x] RESET STEP 1~3 (Snapshot, Inventory, SoT 7개, 데이터 경로, v0.8-01, OPS_DB_MIGRATIONS)
- [x] STEP4 (subway_match, subway_edges_ingest, walk_edges, OPS_STATION_NAME_NORMALIZE, v0.8-02)
- [x] STEP5 (DB import, subway_stations_master, subway_edges_g1, line_code 정책 D5)
- [x] STEP6~8 (vw_subway_station_lines_g1, SOT 07/08/09, v0.8-09/10, OPS_OBSERVABILITY_G1_API)
- [x] Directory Cleanup #1 (scripts/php|ps1|node|python, sql 분류, OPS_REPO_STRUCTURE_RULES)
- [x] G1 E1/E2 API 구현 (STEP9): `path=g1/station-lines/by-name`, `by-code`, g1_station_lines.php, observability
- [x] 문서 정합성 (UX_FLOW_LOCK_G1_v1, CTO_REPORT_DOC_ALIGNMENT_1, INDEX·01_INVENTORY·SOT 09 등 반영)

### 1.2 유저 플로우 (MVP 6단계)
- [x] **Screen 1 홈:** public/user/home.php — 출발/도착 입력, 이슈 Top3, 길찾기 진입
- [x] **Screen 2 이슈 상세:** public/user/issue.php — 이슈 기반 길찾기 CTA → route_finder.php?issue_id=
- [x] **Screen 3 출발/도착 입력:** public/user/route_finder.php — 정류장 자동완성, include_shuttle
- [x] **Screen 4 계산:** route_finder.php step=result 로 경로 계산·표시
- [x] **Screen 5 결과:** route_finder.php step=result — 버스/셔틀 경로, G1 역 노선 표시(g1_station_lines_lookup)
- [x] **Screen 6 구독·알림:** public/user/my_routes.php, alerts.php

### 1.3 듀얼 진입 (UX_FLOW_LOCK_G1_v1)
- [x] **(A) 길찾기 먼저:** 홈/route_finder에서 출발/도착 입력 후 경로 찾기
- [x] **(B) 이슈 먼저:** issue.php에서 [이슈 기반 길찾기] → route_finder.php?issue_id=*

### 1.4 API
- [x] GET /api/route/suggest_stops (정류장 자동완성)
- [x] GET api/index.php?path=g1/station-lines/by-name&station_name=*
- [x] GET api/index.php?path=g1/station-lines/by-code&station_cd=*
- [x] POST api/index.php?path=subscription/toggle

### 1.5 관리자
- [x] 문서 허브, PDF 업로드, run_job, route_review, review_queue, alias_audit, ops_dashboard, alert_ops 등 (01_INVENTORY 기준)

---

## 2. To-do 오더리스트 (방향 → build까지)

| 순서 | To-do | 완료 기준 | 산출물/검증 |
|------|--------|-----------|-------------|
| T1 | **E1/E2 UI 연동 보강** | 출발/도착 입력 화면에서 역명·역코드 자동완성/검증 시 G1 호출 사용, 결과 화면에서 line_codes_source별 표시(환승/1호선/노선 미연결) | route_finder.php·JS, UX_FLOW_LOCK 표 준수 |
| T2 | **VERIFY_SCREEN_VS_PLAN.md 작성** | 플랜 기반 화면 검증 체크리스트 1개 문서 존재, MVP 플로우·G1·듀얼 진입 항목 포함 | docs/OPS/VERIFY_SCREEN_VS_PLAN.md |
| T3 | **MVP DoD E2E 1회** | 업로드→파싱/매칭→검수→승격→경로 안내→감사 1회 수동 실행, 단계별 결과 기록 | 회의록 또는 RUNBOOK에 증거(스크린샷/로그 요약) |
| T4 | **PDF 파이프라인 재구조화(선택)** | manifest·영등포구 샘플·폴더 구조 SoT 확정; OCR→구조화 파이프라인 설계 | docs/SOT 07A/07B/07C 또는 OPS 문서 |
| T5 | **Phase-2 지도/길찾기(로드맵)** | 지도 라이브러리·폴리라인·길찾기 엔진은 Phase-2로 문서 고정, 코드 변경 없음 | ROADMAP_v1_7·NAVER_MAP_UI 등 링크 유지 |

**빌드 실행 순서 (Cursor/로컬):**
1. T1 → 코드 diff 적용 후 로컬에서 페이지 접속으로 확인.
2. T2 → 문서 1개 생성.
3. T3 → 관리자·사용자 흐름 수동 실행, 스크린샷 또는 로그 요약 저장.
4. T4, T5 → 문서/로드맵 정리만 (필요 시).

---

## 3. 검증 방법 (스크린샷 + 링크)

### 3.1 베이스 URL
- **로컬 기본 예 (APP_BASE=/gilime_mvp_01 기준):** `http://localhost/gilime_mvp_01/public`  
  - 사용자 페이지: `http://localhost/gilime_mvp_01/public/user/home.php`  
  - API: `http://localhost/gilime_mvp_01/public/api/index.php?path=...`
- 아래 링크에서 `{BASE}`를 위와 같이 `http://localhost/gilime_mvp_01/public` 로 두거나, 실제 베이스 URL로 치환.

### 3.2 스크린샷으로 검증할 화면·링크

| 검증 항목 | URL (GET) | 기대 화면/동작 | 스크린샷 포착 시점 |
|-----------|-----------|----------------|--------------------|
| **홈 (길찾기 진입)** | `{BASE}/user/home.php` | 출발/도착 입력란, 이슈 Top3 카드, [길찾기] 네비 | 페이지 로드 직후 |
| **이슈 목록** | `{BASE}/user/issues.php` | published 이슈 목록 | 목록 노출 후 |
| **이슈 상세 (이슈 기반 길찾기)** | `{BASE}/user/issue.php?id={이슈ID}` | 이슈 제목·본문, [이슈 기반 길찾기] 버튼 | 버튼 노출 후 |
| **길찾기 입력** | `{BASE}/user/route_finder.php` | 출발/도착, 정류장 자동완성, (선택) 임시셔틀 포함 | 입력 폼 노출 후 |
| **길찾기 결과** | `{BASE}/user/route_finder.php?step=result&from=서울역&to=청량리` | 경로 목록, 지하철 노선( G1 연동 시 ) 표시 | 결과 영역 노출 후 |
| **이슈 컨텍스트 길찾기** | `{BASE}/user/route_finder.php?issue_id={이슈ID}` | 동일 길찾기 화면, issue_id 유지된 상태 | 폼·결과 확인 |
| **마이노선** | `{BASE}/user/my_routes.php` | 구독 노선 목록 | 로그인 후 |
| **알림** | `{BASE}/user/alerts.php` | 알림 이벤트 목록 | 로그인 후 |

### 3.3 API 검증 (curl·브라우저)

| 검증 항목 | URL (GET) | 기대 응답 | 검증 방법 |
|-----------|-----------|-----------|-----------|
| **G1 E1 by-name** | `{BASE}/api/index.php?path=g1/station-lines/by-name&station_name=서울역` | 200, JSON `ok: true`, `data.station_name`, `data.line_codes` | curl 또는 브라우저 → JSON 확인 |
| **G1 E2 by-code** | `{BASE}/api/index.php?path=g1/station-lines/by-code&station_cd=0150` | 200, `data` 존재 | 동일 |
| **G1 not found** | `{BASE}/api/index.php?path=g1/station-lines/by-name&station_name=NonExistentStation` | 404, `ok: false`, `error.code: not_found` | 동일 |
| **정류장 자동완성** | `{BASE}/api/route/suggest_stops.php?q=서울` | 200, `items` 배열 | 동일 |

### 3.4 복사용 검증 링크 (로컬 기본)

```
http://localhost/gilime_mvp_01/public/user/home.php
http://localhost/gilime_mvp_01/public/user/issues.php
http://localhost/gilime_mvp_01/public/user/route_finder.php
http://localhost/gilime_mvp_01/public/user/route_finder.php?step=result&from=서울역&to=청량리
http://localhost/gilime_mvp_01/public/user/my_routes.php
http://localhost/gilime_mvp_01/public/user/alerts.php
http://localhost/gilime_mvp_01/public/api/index.php?path=g1/station-lines/by-name&station_name=서울역
http://localhost/gilime_mvp_01/public/api/index.php?path=g1/station-lines/by-code&station_cd=0150
http://localhost/gilime_mvp_01/public/api/route/suggest_stops.php?q=서울
```

(이슈 상세·이슈 기반 길찾기는 실제 이슈 ID 필요: `.../user/issue.php?id=1`, `.../user/route_finder.php?issue_id=1`)

### 3.5 완료 시 검증 체크
- [ ] 위 표의 각 URL을 실제 환경에서 열어, **스크린샷 1장씩** 저장 (파일명 예: `verify_home.png`, `verify_route_finder_result.png`, `verify_g1_by_name.png`).
- [ ] 저장 위치: `docs/OPS/verify_screenshots/` (폴더 없으면 생성). 또는 회의록·RUNBOOK에 “검증 일자 + URL + 통과/실패”만 표로 정리해도 됨.
- [ ] 실패한 항목은 VERIFY_SCREEN_VS_PLAN.md 또는 이 문서의 “실패 메모”란에 URL·증상·예상 원인만 적어 두면, 이후 수정 시 참고 가능.

---

## 4. 요약

- **이미 구현된 것:** 플랜 섹션 6 전부 + 유저 6단계 플로우 + 듀얼 진입 + G1 E1/E2 API → **체크만 하면 됨.**
- **To-do:** T1(E1/E2 UI 보강) → T2(VERIFY 문서) → T3(MVP E2E 1회) → T4/T5(문서·로드맵). 빌드는 T1 코드 적용 후 로컬에서 위 링크로 확인.
- **검증:** 위 3.2·3.3 링크를 열어 스크린샷으로 남기거나, 통과/실패 표로 기록. 베이스 URL만 환경에 맞게 치환하면 됨.
