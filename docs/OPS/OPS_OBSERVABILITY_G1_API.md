# OPS — Observability (G1 station-lines API)

## Purpose

E1/E2는 STEP9에서 구현 완료. 본 문서는 해당 API의 로그·트레이스 계약이다.

**SoT:** `docs/SOT/09_API_CONTRACTS_G1.md`.

---

## Trace propagation

- If the stack already has **trace_id** (e.g. from gateway or request middleware), propagate it to the G1 API handler and to all log events for the request.
- If not present, generate a request-scoped id (e.g. `request_id`) for the duration of the request.

---

## Log events

| Event | When | Required fields |
|-------|------|------------------|
| **api_enter** | On entry to E1 or E2 handler | endpoint (by-name | by-code), station_name or station_cd (from query param), trace_id (if any) |
| **db_query_start** | Before executing the bound SQL | endpoint, bind_key (station_name | station_cd), bind_value |
| **db_query_end** | After SQL returns | endpoint, rows_returned, query_ms |
| **api_exit** | Before returning response | endpoint, status (2xx | 4xx | 5xx), trace_id (if any) |

Additional recommended fields: **line_codes_source** (view | master | none) on api_exit for analytics.

---

## Required fields (summary)

- **station_name** or **station_cd** (which param was used)
- **rows_returned** (0 or 1 for E1/E2)
- **query_ms** (elapsed time of the single DB query)

---

## 필수 필드 체크리스트 (운영 검증용)

E1/E2 요청 1건당 아래 4개 이벤트가 순서대로 기록되는지 확인할 때 사용. 런타임은 `safe_log(event, trace_id, fields)` 사용; `app/inc/lib/observability.php` 참조.

| Event | 필수 필드 | 확인 |
|-------|-----------|------|
| api_enter | endpoint, station_name 또는 station_cd, trace_id(전파 시) | |
| db_query_start | endpoint, bind_key, bind_value(마스킹 적용됨) | |
| db_query_end | endpoint, rows_returned, query_ms | |
| api_exit | endpoint, status(2xx/4xx/5xx), trace_id(전파 시) | |

- **마스킹:** bind_value 등 문자열 값은 observability 모듈에서 len+preview(50자)만 기록; raw SQL·비밀·PII는 로그에 넣지 않음.

---

## 샘플 로그 생성 규칙 (문서/증거용)

운영 검증 또는 문서에 “로그 샘플”을 남길 때 준수할 규칙.

1. **환경:** 개발/스테이징에서 `GILIME_DEBUG=1` 설정 후 E1 또는 E2를 **1회** 호출 (예: curl by-name 서울역).
2. **캡처:** 해당 요청에 대응하는 `error_log` 출력 중 `[TRACE ...]` 로그 라인만 복사.
3. **문서에 넣을 때:** (a) **원문 SQL은 넣지 않는다.** (b) **비밀·토큰·전체 파라미터 값은 넣지 않는다.** (c) 이미 `safe_log`가 적용한 **len+preview** 형태만 사용 가능. (d) trace_id는 그대로 두어도 됨.
4. **금지:** 실제 DB 쿼리 문장, 비밀번호, API 키, 개인식별 정보(PII)를 문서나 아티팩트에 기록하지 않는다.

문서에 샘플을 넣을 경우 예시 형식: `[TRACE trc_20260219_120000_abc123] api_enter {"endpoint":"by-name","station_name":"len=6 preview=서울역"}` (실제 값은 마스킹 규칙에 따라 len+preview만).

---

## PASS criteria (가설)

- **query_ms p95:** Target &lt; 200 ms for a single E1/E2 query under normal load. (가설 — no baseline data yet; adjust after measurement.)
- **Baseline:** p50/p95 shall be measured after E1/E2 implementation and real traffic; the 200 ms target is a hypothesis until then.

---

## 확인 필요

- Whether trace_id is already part of the request context in this project.
- Actual p50/p95 once the API is implemented and traffic exists.
