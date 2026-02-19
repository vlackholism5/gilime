# SoT 08 — Runtime query contracts (G1)

## Purpose

Stable read-contract for "station → line candidates" and blank-station handling. Runtime-agnostic: canonical SQL patterns and JSON shapes. No requirement to change public/* until integration is decided.

**SoT:** This document. Canonical queries: `sql/validate/v0.8-09_station_line_candidates_queries.sql`.

---

## Canonical queries

| Id | Input | Source | Output columns |
|----|--------|--------|----------------|
| **Q1** | `station_name` | `vw_subway_station_lines_g1` | station_name, line_codes_csv, line_codes_json, degree_edges |
| **Q2** | `station_cd` | master + view (LEFT JOIN) | station_cd, station_name, master_line_code, view_line_codes_csv, view_line_codes_json |
| **Q3** | — | master + view (actionable blanks) | station_cd, station_name, master_line_code, view_line_codes_*, line_code_source, line_code_candidates |

---

## Contract JSON shapes

### Q1 response (single row or empty)

```json
{
  "station_name": "서울역",
  "line_codes_csv": "1,4",
  "line_codes_json": "[\"1\", \"4\"]",
  "degree_edges": 4
}
```

- **Empty result:** Station has no edges in G1 → treat as unresolved.

### Q2 response (single row or empty)

```json
{
  "station_cd": "0150",
  "station_name": "서울역",
  "master_line_code": "",
  "view_line_codes_csv": "1,4",
  "view_line_codes_json": "[\"1\", \"4\"]"
}
```

- `master_line_code` may be `""` or `null` when blank.
- `view_line_codes_*` may be `null` when LEFT JOIN finds no view row (unresolved).

### Q3 response (array of rows)

```json
[
  {
    "station_cd": "0150",
    "station_name": "서울역",
    "master_line_code": "",
    "view_line_codes_csv": "1,4",
    "view_line_codes_json": "[\"1\", \"4\"]",
    "line_code_source": "ambiguous",
    "line_code_candidates": ["1", "4"]
  }
]
```

- Only stations where master line_code is blank **and** view has candidates.

---

## Precedence rules

1. **Master has line_code and consistent with view**  
   Use `master_line_code` as the single line. View set can still be used for validation or display (e.g. "1호선" and "1,4 환승").

2. **Master blank but view has candidates**  
   Treat as **multi-line station (set)**. Use `view_line_codes_csv` or `view_line_codes_json` (or `line_code_candidates` from meta_json). Do not show as "노선 없음".

3. **No view candidates**  
   **Unresolved.** Station has no edges in G1 or not in view. Display/route only with explicit mapping if added later.

---

## Error handling expectations

- **Empty result for Q1/Q2:** Not an error. Return empty object `{}` or 404-style "not found"; do not assume every station_name/station_cd exists in the view.
- **Q3 empty list:** No actionable blanks; valid.
- **Invalid/missing param:** Application responsibility (validate before bind). SQL is read-only; no write errors.

---

## Assumptions / 확인 필요

- **Character set:** station_name is UTF-8; DB uses utf8mb4. Binding must use same encoding.
- **Null vs empty string:** `master_line_code` may be NULL or `''`; treat both as blank.
- **API layer:** This contract does not define HTTP or public/* endpoints; it defines the data shape once a read path is implemented.
