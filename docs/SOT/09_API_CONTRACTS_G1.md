# SoT 09 — API contracts (G1 station lines)

## Purpose

Define the runtime API contract for station line candidates **before** implementation. No public/* changes in STEP8; this is the spec for a future implementation.

**SoT:** This document. Query bindings: `sql/validate/v0.8-10_api_query_bindings_g1.sql`.

---

## Endpoints

| Id | Method | Path | Description |
|----|--------|------|-------------|
| **E1** | GET | `/api/g1/station-lines/by-name?station_name=...` | Lookup by 역명 |
| **E2** | GET | `/api/g1/station-lines/by-code?station_cd=...` | Lookup by 역코드 |

---

## Request

### E1

| Param | Required | Type | Note |
|-------|----------|------|------|
| `station_name` | yes | string | Exact 역명 (e.g. 서울역, 청량리) |

### E2

| Param | Required | Type | Note |
|-------|----------|------|------|
| `station_cd` | yes | string | Station code (e.g. 0150, 0158) |

---

## Response JSON schema (both E1 and E2)

Single object when found; 404 or empty when not found (implementation choice).

```json
{
  "station_name": "string",
  "station_cd": "string | null",
  "master_line_code": "string | null",
  "line_codes": ["1", "4"],
  "line_codes_source": "view | master | none",
  "degree_edges": 0,
  "meta": {
    "line_code_source": "string | null",
    "line_code_candidates": ["1", "4"] | null
  }
}
```

- **line_codes:** Derived set for display/routing. From view when available, else single from master, else [].
- **line_codes_source:** `view` = edge-derived set; `master` = only master single value; `none` = unresolved.
- **degree_edges:** Number of edge rows for this station (0 when no view row).
- **meta:** From `subway_stations_master.meta_json` when present (ambiguous/unresolved provenance).

---

## Example responses

### E1 / E2 — edges_unique (청량리)

```json
{
  "station_name": "청량리",
  "station_cd": "0158",
  "master_line_code": "1",
  "line_codes": ["1"],
  "line_codes_source": "view",
  "degree_edges": 2,
  "meta": {
    "line_code_source": "edges_unique",
    "line_code_candidates": null
  }
}
```

### E1 / E2 — ambiguous (서울역)

```json
{
  "station_name": "서울역",
  "station_cd": "0150",
  "master_line_code": null,
  "line_codes": ["1", "4"],
  "line_codes_source": "view",
  "degree_edges": 4,
  "meta": {
    "line_code_source": "ambiguous",
    "line_code_candidates": ["1", "4"]
  }
}
```

### E1 / E2 — not_found / unresolved

When no row in master (by-name: no matching name; by-code: no matching code), or master exists but no view row and master_line_code blank:

- **not_found:** No master row → 404 or `{"line_codes": [], "line_codes_source": "none", "degree_edges": 0}` (implementation choice).
- **unresolved:** Master row exists, no view row, blank master_line_code → 200 with `line_codes_source: "none"`, `line_codes: []`, `degree_edges: 0`.

Example (unresolved). Use a real `station_cd` from `sql/validate/v0.8-06_station_line_code_issue_list.sql` (run after STEP5 import) where `line_code_source = 'unresolved'`; below is a placeholder (1453 과천) until confirmed:

```json
{
  "station_name": "과천",
  "station_cd": "1453",
  "master_line_code": null,
  "line_codes": [],
  "line_codes_source": "none",
  "degree_edges": 0,
  "meta": {
    "line_code_source": "unresolved",
    "line_code_candidates": null
  }
}
```

---

## Error handling

- **not_found:** No master row (by-name or by-code에 해당하는 역 없음) → 404. 구현에서는 404 + `error: { code, message }` 반환.
- **unresolved:** Master 행은 있으나 view 없고 master_line_code 공백 → 200 + `data` with `line_codes_source: "none"`, `line_codes: []`, `degree_edges: 0` (성공 응답).

### Error response taxonomy (클라이언트 계약)

| HTTP | error.code | message (요지) | When |
|------|------------|----------------|------|
| 400 | `bad_request` | station_name required (1-60 chars) / station_cd required (2-10 digits) | 필수 파라미터 누락·공백·길이/형식 위반 |
| 404 | `not_found` | Station not found | master에 해당 역 없음 (by-name 또는 by-code 조회 결과 0건) |
| 500 | (구현에 따라 문자열 또는 객체) | Generic message only; internal detail 미노출 | DB/서버 예외 |

- 400/404 시 응답 본문: `{ "ok": false, "error": { "code": "<code>", "message": "<message>" }, "trace_id": "..." }`.
- 500 시: 내부 오류 메시지·스택 노출 금지; 클라이언트에는 일반화된 메시지만 반환.
