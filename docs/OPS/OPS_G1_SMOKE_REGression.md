# OPS — G1 API Smoke / 정합성 체크 (회귀 검증)

STEP9 curl 스모크 외에, 데이터 정합성 확인을 위한 **정합성 체크 2개**를 문서화. (코드 테스트 프레임워크 미도입; 문서 + SQL/curl 예시만.)

---

## 정합성 체크 1: by-name vs by-code 동일 역 동일 data

동일 역을 역명(E1)과 역코드(E2)로 조회했을 때 `data` 내용이 일치해야 함.

### 예시 케이스

- **서울역:** by-name `station_name=서울역` ↔ by-code `station_cd=0150` → 동일한 station_name, station_cd, line_codes, line_codes_source, degree_edges, meta.
- **청량리:** by-name `station_name=청량리` ↔ by-code `station_cd=0158` → 동일.

### 실행 예시 (복붙)

```bash
# by-name 서울역
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-name&station_name=서울역" | jq -S .data

# by-code 0150 (서울역)
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-code&station_cd=0150" | jq -S .data
```

**검증:** 위 두 응답의 `data`를 비교하여 station_name, station_cd, line_codes, line_codes_source, degree_edges, meta 가 동일한지 확인. (jq 없으면 JSON을 수동 비교.)

---

## 정합성 체크 2: ambiguous 역에서 view line_codes ↔ meta.line_code_candidates 일치

`line_codes_source: "view"` 이고 `meta.line_code_source: "ambiguous"` 인 역에서는, `data.line_codes`(뷰에서 유도)와 `data.meta.line_code_candidates`(마스터 meta_json)가 동일한 집합이어야 함.

### SQL로 기대값 확인 (v0.8-09 / v0.8-10 기반)

ambiguous 역의 line_codes(뷰)와 meta_json 내 line_code_candidates를 비교하는 read-only 검증은 기존 뷰·마스터 데이터로 가능. 예: 서울역(0150)에 대해 v0.8-10 바인딩으로 1행 조회 시 `line_codes`(JSON)와 `JSON_EXTRACT(meta_json, '$.line_code_candidates')` 내용이 일치하는지 확인.

```sql
-- 예: 서울역 station_cd=0150 한 건에 대해
-- v0.8-10 E2 쿼리 실행 후 반환된 line_codes vs meta_json.line_code_candidates 비교
-- (실행은 sql/validate/v0.8-10_api_query_bindings_g1.sql 참고)
```

### API 응답으로 확인 (샘플 N건)

1. ambiguous 역 목록은 `sql/validate/v0.8-06_station_line_code_issue_list.sql`에서 `line_code_source = 'ambiguous'` 로 조회 가능.
2. 해당 역 중 몇 건(예: 서울역 0150)에 대해 E1 또는 E2 호출 후, 응답에서 `data.line_codes`와 `data.meta.line_code_candidates`를 비교 (순서 무관, 집합으로 동일하면 PASS).

**curl 예시 (서울역 1건):**

```bash
curl -s "http://localhost/gilime_mvp_01/api/index.php?path=g1/station-lines/by-code&station_cd=0150" | jq '{ line_codes: .data.line_codes, candidates: .data.meta.line_code_candidates }'
```

**기대:** line_codes와 candidates가 동일한 요소 집합 (예: ["1","4"]).

---

## 요약

| 체크 | 목적 | 방법 |
|------|------|------|
| 1. by-name vs by-code | 동일 역이 경로만 다르게 조회될 때 data 일치 | curl E1 + E2, data 비교 |
| 2. ambiguous line_codes↔candidates | 뷰 유도 line_codes와 meta 정합성 | v0.8-06로 ambiguous 샘플 확보 후 API 또는 v0.8-10 SQL로 비교 |
