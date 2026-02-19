# G1 E1/E2 API 구현 계획 (MVP+)

**범위:** G1 역·노선 조회 API 최초 구현 (E1: 역명, E2: 역코드). 라우팅 프레임워크/DB 스키마/UI 변경 없음.

**SoT(진실의 원천):** [SOT/09_API_CONTRACTS_G1.md](../SOT/09_API_CONTRACTS_G1.md), [v0.8-10_api_query_bindings_g1.sql](../../sql/validate/v0.8-10_api_query_bindings_g1.sql), [OPS_OBSERVABILITY_G1_API.md](OPS_OBSERVABILITY_G1_API.md).

---

## 1) 변경 대상 파일

| 파일 | 변경 내용 |
|------|------------|
| [public/api/index.php](../../public/api/index.php) | GET 분기 추가: `path === 'g1/station-lines/by-name'`, `path === 'g1/station-lines/by-code'`. G1 핸들러 로드, 입력 검증, 핸들러 호출 후 JSON 응답 반환. |
| **신규** [app/inc/api/g1_station_lines.php](../../app/inc/api/g1_station_lines.php) | (1) v0.8-10과 동일한 SELECT 문을 prepared statement로 정의. (2) `g1_station_lines_lookup(PDO, endpoint, bind_key, bind_value)` → `['row' => assoc|null, 'query_ms' => float]` 반환. (3) 라우팅/스키마 변경 없음. |
| [OPS_DB_MIGRATIONS.md](OPS_DB_MIGRATIONS.md) | STEP9 추가: "API E1/E2 구현 + 스모크" (엔드포인트, curl 스모크, 마이그레이션 SQL 없음). |
| [SOT/09_API_CONTRACTS_G1.md](../SOT/09_API_CONTRACTS_G1.md) | (선택) 구현 위치 안내 한 줄: public/api/index.php?path=g1/station-lines/by-name\|by-code. 스키마 변경 없음. |

그 외 파일·폴더 이동·신규 테이블/뷰 없음.

---

## 2) 구현 요지 (최소 변경)

### A) API 진입 및 path 분기

- **위치:** [public/api/index.php](../../public/api/index.php)
- **이유:** 기존 `$_GET['path']` 분기 방식 유지. E1/E2는 GET 전용. **trace_id·observability는 repo의 observability 모듈 기준** ([app/inc/lib/observability.php](../../app/inc/lib/observability.php), 가정 금지): 이미 index.php 상단에서 로드됨. `get_trace_id()`, `attach_trace_id_to_response()`, `safe_log()` 사용.
- **위험:** db 미로드 시 오류. **db.php require 경로는 repo에 존재하는 파일 기준으로만 사용** (가정 금지). 현재 [public/api/index.php](../../public/api/index.php)는 `require_once __DIR__ . '/../../app/inc/auth/db.php';` 사용.
- **최소 수정:**
  - `path === 'g1/station-lines/by-name'` 이고 GET일 때: `station_name` 검증(trim, 길이 1~60, 공백 불가). 실패 시 400 + `{ "ok": false, "error": { "code": "bad_request", "message": "station_name 필요 (1–60자)" } }`. 성공 시 G1 모듈 로드 후 **db는 repo 실제 파일 기준으로 로드**([public/api/index.php](../../public/api/index.php)와 동일하게 `require_once __DIR__ . '/../../app/inc/auth/db.php';`), `g1_station_lines_lookup(pdo(), 'by-name', 'station_name', $station_name)` 호출 후 응답 생성.
  - `path === 'g1/station-lines/by-code'` 이고 GET일 때: `station_cd` 검증(trim, 2~10자, 숫자만). 실패 시 400. 성공 시 동일하게 lookup 호출 후 응답.
  - 응답: 항상 **observability 모듈**([app/inc/lib/observability.php](../../app/inc/lib/observability.php))에 정의된 `attach_trace_id_to_response($body, $trace_id)`로 trace_id 포함. 발견 시 200 + `{ "ok": true, "data": { ...SOT/09 스키마... } }`. 마스터 행 없음(not_found) 시 404 + `{ "ok": false, "error": { "code": "not_found", "message": "역을 찾을 수 없습니다" } }`. 마스터는 있으나 view 없고 line_code 공백(unresolved) 시 200 + `data`에 `line_codes_source: "none"`, `line_codes: []`, `degree_edges: 0`.
- **검증:** `curl "…/api/index.php?path=g1/station-lines/by-name&station_name=서울역"` → 200, JSON에 `data.station_name`, `data.line_codes` 배열, `data.line_codes_source` 존재.

### B) G1 핸들러 모듈 (쿼리 바인딩 + 관측)

- **위치:** 신규 [app/inc/api/g1_station_lines.php](../../app/inc/api/g1_station_lines.php)
- **이유:** index.php는 최소화, v0.8-10 SQL과 observability(api_enter, db_query_start, db_query_end, api_exit)를 한 곳에서 처리.
- **위험:** 바인딩 오류로 SQL 삽입, 로그에 비밀 노출.
- **최소 수정:**
  - `g1_station_lines_lookup(PDO $pdo, string $endpoint, string $bind_key, string $bind_value): array` 한 개 함수. v0.8-10 SELECT를 그대로 사용하고 `:station_name` 또는 `:station_cd` 하나만 바인드. 실행 전후 `microtime(true)`로 query_ms 측정. 반환 `['row' => $row ?: null, 'query_ms' => $ms]`.
  - api_enter / db_query_start / db_query_end / api_exit는 이 모듈 또는 index에서 **observability 모듈의 `safe_log(string $event, string $trace_id, array $fields)`** 호출. trace_id는 **같은 모듈의 `get_trace_id()`** 사용(가정 금지, repo [app/inc/lib/observability.php](../../app/inc/lib/observability.php) 기준). 로그에는 raw SQL 넣지 않음.
  - DB에서 오는 `line_codes`, `meta`는 JSON 문자열이므로 응답 전 `json_decode`로 배열/객체로 변환. 실패 시 빈 배열·기본 meta 사용.
- **검증:** by-name 서울역 호출 시 1행 반환, query_ms > 0, GILIME_DEBUG=1 시 error_log에 SQL 없음.

### C) 응답 형식 (SOT/09 준수)

- **이유:** 계약상 "발견 시 단일 객체", not_found는 404 또는 200+line_codes_source "none".
- **최소 수정:** 발견 시 `data`에 station_name, station_cd, master_line_code, line_codes(배열), line_codes_source, degree_edges, meta(객체). 행 없으면 404. 내부 DB 오류는 노출하지 않고 500은 일반 메시지로.
- **검증:** 서울역·청량리 응답을 SOT/09 예시 JSON과 비교.

### D) 입력 검증

- **by-name:** `station_name` trim, 공백 또는 mb_strlen > 60이면 400 (bad_request).
- **by-code:** `station_cd` trim, 길이 2~10, `ctype_digit` 또는 `^[0-9]{2,10}$` 아니면 400.
- **검증:** station_name 생략/공백 → 400; station_cd=1(짧음) → 400.

### E) Observability 연동

- **이유:** OPS_OBSERVABILITY_G1_API에 api_enter, db_query_start, db_query_end, api_exit 필수.
- **최소 수정:** E1/E2 처리 구간에서 **repo에 존재하는 observability 모듈** ([app/inc/lib/observability.php](../../app/inc/lib/observability.php)) 기준으로 trace_id·로그 사용(함수명/위치 가정 금지). index.php 상단에서 이미 해당 모듈 로드됨 → `get_trace_id()`로 trace_id 확보, `safe_log($event, $trace_id, $fields)`로 네 이벤트 순서대로 기록. query_ms는 lookup 반환값 사용.
- **검증:** GILIME_DEBUG=1로 한 번 curl 후 error_log에 [TRACE ...] api_enter, db_query_start, db_query_end, api_exit 확인.

### F) 문서 보강

- **OPS_DB_MIGRATIONS.md:** "STEP9: API E1/E2 구현 + 스모크" 절 추가. 엔드포인트 GET by-name/by-code, curl 예시(베이스 URL + path·파라미터), 마이그레이션 SQL 없음, 스모크는 4개 curl(서울역, 청량리, 0150, 0158) 및 선택적으로 not_found(예: station_cd=99999 또는 v0.8-06 unresolved).
- **SOT/09:** (선택) 구현 위치 한 줄: "구현: public/api/index.php?path=g1/station-lines/by-name 또는 by-code (GET)."
- **검증:** STEP9 절 존재, curl 명령 복사·실행 가능.

---

## 3) 검증 체크리스트 (복사용)

- [ ] **E1 by-name 서울역:** `curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-name&station_name=서울역"` → 200, `ok: true`, `data.station_name: "서울역"`, `data.line_codes` 배열(예: ["1","4"]), `data.line_codes_source: "view"`, `data.meta.line_code_source: "ambiguous"`.
- [ ] **E1 by-name 청량리:** `station_name=청량리` → 200, `data.line_codes: ["1"]`, `data.line_codes_source: "view"`, `data.meta.line_code_source: "edges_unique"`.
- [ ] **E2 by-code 0150:** `path=g1/station-lines/by-code&station_cd=0150` → 200, 서울역(ambiguous) 데이터.
- [ ] **E2 by-code 0158:** `station_cd=0158` → 200, 청량리(edges_unique) 데이터.
- [ ] **Not found:** `station_name=NonExistentStation` 또는 `station_cd=99999` → 404, `ok: false`, `error.code: "not_found"`.
- [ ] **Bad request:** station_name 없음/공백 → 400; station_cd=1(짧음) → 400.
- [ ] **Observability:** GILIME_DEBUG=1 한 요청 → error_log에 api_enter, db_query_start, db_query_end, api_exit + trace_id.
- [ ] **로그에 SQL 없음:** safe_log에 raw SQL 미기록.
- [ ] **문서:** OPS_DB_MIGRATIONS.md에 STEP9 및 curl 스모크 존재; SOT/09는 변경 없거나 구현 안내 한 줄만.

---

## 4) CTO_REPORT (결정·액션 요약)

### 결정 로그

| ID | 결정 | 근거 |
|----|------|------|
| D1 | E1/E2는 public/api/index.php path 분기 + 신규 app/inc/api/g1_station_lines.php로 구현 | 단일 진입점, 신규 라우팅 프레임워크 없음, 변경 파일 최소화. |
| D2 | 응답 래퍼: `ok`, `data`(SOT/09 객체), `error`(ok false일 때만), 모든 응답에 `trace_id` | 기존 API 스타일·SOT/09 스키마 유지. |
| D3 | Not found → 404 + error code; unresolved(마스터만 있고 view 없고 line_code 공백) → 200, line_codes_source "none" | SOT/09의 not_found/unresolved 처리 옵션 준수. |
| D4 | Observability: api_enter, db_query_start, db_query_end, api_exit를 safe_log로 기록; trace_id·함수는 repo의 app/inc/lib/observability.php 기준(가정 금지) | OPS_OBSERVABILITY_G1_API 준수, 신규 인프라 없음. |
| D5 | DB 스키마·뷰 변경 없음; v0.8-10 SELECT를 단일 바인드 파라미터로 그대로 사용 | SoT 준수, 삽입 공격 방지. |

### 액션 아이템

| ID | 작업 | 담당 | 검증 |
|----|------|------|------|
| A1 | g1_station_lines.php 추가(lookup + v0.8-10 SQL) | Backend | 파일 존재, PDO prepared만 사용, row + query_ms 반환. |
| A2 | public/api/index.php에 by-name/by-code 분기 추가 | Backend | path=by-name, by-code 시 200/404/400 위 체크리스트대로 동작. |
| A3 | Observability 4종 이벤트 연동 | Backend | GILIME_DEBUG=1 시 error_log에 4 이벤트. |
| A4 | OPS_DB_MIGRATIONS.md에 STEP9 추가 | Release | "STEP9" 절 및 curl 스모크 존재. |
| A5 | 검증 체크리스트 실행(4 curl + not_found + 400) | QA | 체크리스트 항목 모두 통과. |

---

## 5) 구현 시 준수 사항 (프롬프트)

- **db.php require 경로**는 실제 repo에 존재하는 파일 기준으로 잡는다. (가정 금지)
- **trace_id 함수명/위치**는 repo에 존재하는 observability 모듈 기준으로 잡한다. (가정 금지)

---

## 6) 빌드 순서 (실행 계획)

Build 시 아래 순서대로 진행하면 E1/E2 API가 완성·검증된다.

### 6.1 사전 조건 확인

- [ ] [app/inc/auth/db.php](../../app/inc/auth/db.php), [app/inc/lib/observability.php](../../app/inc/lib/observability.php) 존재.
- [ ] [sql/validate/v0.8-10_api_query_bindings_g1.sql](../../sql/validate/v0.8-10_api_query_bindings_g1.sql) 존재. (E1: `:station_name`, E2: `:station_cd` 바인딩용 SELECT 참고)
- [ ] DB에 `subway_stations_master`, `vw_subway_station_lines_g1` 적용됨 (v0.8-01·v0.8-03·v0.8-07 등 적용 후).

### 6.2 A1 — G1 핸들러 모듈 추가

1. **디렉터리:** `app/inc/api/` 없으면 생성.
2. **파일:** [app/inc/api/g1_station_lines.php](../../app/inc/api/g1_station_lines.php) 신규 작성.
   - v0.8-10과 동일한 SELECT 문을 prepared statement로 구현 (E1: `WHERE s.station_name = :station_name`, E2: `WHERE s.station_cd = :station_cd`).
   - 함수 시그니처: `g1_station_lines_lookup(PDO $pdo, string $endpoint, string $bind_key, string $bind_value): array` → `['row' => assoc|null, 'query_ms' => float]`.
   - `line_codes`(JSON 문자열) → `json_decode`로 배열; `meta`(JSON) → 객체. 실패 시 빈 배열/기본 meta.
   - raw SQL은 로그에 넣지 않음.

### 6.3 A2 — API 진입 분기 추가

1. **파일:** [public/api/index.php](../../public/api/index.php).
2. **위치:** 기존 `if ($path === 'subscription/toggle') { ... }` 블록 **다음**, 최종 `http_response_code(404);` **앞**에 E1/E2 분기 삽입.
3. **E1 (by-name):**
   - 조건: `$path === 'g1/station-lines/by-name'` && GET.
   - `station_name` = trim(`$_GET['station_name']`). 빈값 또는 mb_strlen > 60 → 400, `error.code: "bad_request"`.
   - `require_once __DIR__ . '/../../app/inc/auth/db.php';` (repo 경로 그대로), `require_once __DIR__ . '/../../app/inc/api/g1_station_lines.php';`
   - `get_trace_id()`로 trace_id 확보(이미 상단 `$trace_id` 있으면 재사용 가능). `safe_log('api_enter', $trace_id, ['endpoint' => 'by-name', 'station_name' => $station_name]);`
   - `g1_station_lines_lookup(pdo(), 'by-name', 'station_name', $station_name)` 호출. 반환 row 없으면 404; 있으면 200 + `attach_trace_id_to_response(['ok' => true, 'data' => SOT/09 형태], $trace_id)`. db_query_start / db_query_end는 g1_station_lines.php 내부 또는 index에서 safe_log 호출.
4. **E2 (by-code):**
   - 조건: `$path === 'g1/station-lines/by-code'` && GET.
   - `station_cd` = trim(`$_GET['station_cd']`). 길이 2~10, 숫자만 허용 아니면 400.
   - 동일하게 db + G1 모듈 로드, lookup 호출, 404/200 + attach_trace_id_to_response.

### 6.4 A3 — Observability 4종 이벤트

- E1/E2 처리 구간에서 **repo의** [app/inc/lib/observability.php](../../app/inc/lib/observability.php) `safe_log($event, $trace_id, $fields)` 사용:
  - **api_enter:** 핸들러 진입 시 (endpoint, station_name 또는 station_cd).
  - **db_query_start:** SQL 실행 직전 (endpoint, bind_key, bind_value — 값은 마스킹 규칙 준수).
  - **db_query_end:** SQL 반환 직후 (endpoint, rows_returned, query_ms).
  - **api_exit:** 응답 반환 직전 (endpoint, status 2xx/4xx/5xx).
- trace_id는 동일 파일의 `get_trace_id()` 사용 (index.php에서는 이미 상단에서 할당됨).

### 6.5 A4 — 문서 STEP9 추가

- **파일:** [docs/OPS/OPS_DB_MIGRATIONS.md](OPS_DB_MIGRATIONS.md).
- **내용:** "STEP9: API E1/E2 구현 + 스모크" 절 추가.
  - 엔드포인트: GET `.../api/index.php?path=g1/station-lines/by-name&station_name=...`, GET `.../api/index.php?path=g1/station-lines/by-code&station_cd=...`.
  - curl 스모크 4건: 서울역(by-name), 청량리(by-name), 0150(by-code), 0158(by-code). (선택) not_found 예시: station_cd=99999 등.
  - 마이그레이션 SQL 없음 명시.

### 6.6 A5 — 검증 실행

- [ ] **3) 검증 체크리스트** 항목 순서대로 실행:
  - E1 서울역, E1 청량리, E2 0150, E2 0158 → 200 + data 스키마 일치.
  - Not found → 404, Bad request → 400.
  - GILIME_DEBUG=1 한 요청 → error_log에 api_enter, db_query_start, db_query_end, api_exit + trace_id.
  - safe_log에 raw SQL 없음 확인.
  - OPS_DB_MIGRATIONS.md STEP9 및 curl 존재 확인.

### 6.7 빌드 완료 조건

- 위 6.1~6.6이 모두 통과하면 빌드 완료. 선택 사항: [SOT/09_API_CONTRACTS_G1.md](../SOT/09_API_CONTRACTS_G1.md)에 구현 위치 한 줄 안내 추가.

---

*이 문서는 G1 E1/E2 API 구현 계획의 한글 버전입니다. 영문 원본은 Cursor 계획 저장소에 있을 수 있습니다.*
