# OPS — Station Name Normalization

## Purpose

Single source of truth for normalizing subway and bus stop names before matching (OSM ↔ official Seoul Metro codes). Used by scripts in `scripts/python/` (subway_match_v1, walk_edges).

## Definitions

- **Normalized name:** After applying rules below; used for exact join.
- **Alias:** Alternative spelling/short form that maps to one canonical station name.
- **Ambiguous:** Same name used for multiple stations (e.g. different lines or transfer stations).

## Normalization rules

1. **괄호 제거:** Remove content in parentheses and the parentheses.  
   - Example: `서울대입구(관악구청)` → `서울대입구`, `역촌(중앙)역` → `역촌역` (then apply 역 suffix rule).
2. **공백:** Trim leading/trailing; collapse multiple spaces to one.  
   - Example: `  강남   역  ` → `강남 역`.
3. **'역' 접미어:** Treat consistently — either always add or always remove for comparison.  
   - **Rule:** For matching, normalize to "name without 역" for both sides, then compare. If one side has "OO역" and the other "OO", treat as same.  
   - Example: `서울대입구역` ↔ `서울대입구` → same.
4. **하이픈/중점:** Replace hyphen (`-`), middle dot (`·`), full-width variants with a single space (or remove for comparison).  
   - Example: `선릉·선정릉` → `선릉 선정릉` or `선릉선정릉` (pick one convention; script should use one consistently). 권장: 공백 하나로 통일.

## Alias rules

- Map common variants to one canonical name (for MED confidence match):
  - `서울대입구` ↔ `서울대입구역` (역 제거/추가로 동일 처리)
  - `홍대입구` ↔ `홍대 입구` (공백 제거)
  - 기타: 운영 중 발견 시 `OPS_STATION_NAME_NORMALIZE.md` 또는 스크립트 alias 테이블에 추가. 확인 필요: alias 목록 공식 출처.

## Ambiguous handling (동명이역 / 환승역 / 지선)

- **동명이역:** Multiple stations share the same name (e.g. different lines). Matching must use `(line_code, station_name)` or `(station_cd)` as key; do not match on name only when multiple candidates exist.
- **환승역:** One logical station, multiple line codes. Keep one row per (line_code, station_cd) in master; match can return multiple rows per OSM node with same name — choose "best" by line priority or coordinate distance. 확인 필요: 우선 노선 정의.
- **지선:** Branch lines (e.g. 신분당선). Treat as separate line_code; name may duplicate. Same as 동명이역 — use line_code + name or station_cd.

## Encoding note

- CSV may be **UTF-8** (with or without BOM) or **CP949**. Scripts must:
  1. Try `utf-8-sig` first (UTF-8 with BOM).
  2. If decode error or garbage, try `cp949`.
  3. If still failing, exit with clear error and list the path attempted.

## Assumptions

- Official Seoul Metro station names are the canonical source for `station_name` in DB.
- OSM names may have extra suffixes (역, 괄호). Normalization brings them to a comparable form.
- Match confidence bands: HIGH (exact/alias), MED (alias), LOW (similarity + distance), NONE (unmatched).

## Open Questions (확인 필요)

- Alias list official source or maintenance process.
- Line priority for choosing "best" among multiple candidates at same location.
- Whether to keep parentheses content for display (e.g. "서울대입구(관악구청)") in a separate display_name field.
