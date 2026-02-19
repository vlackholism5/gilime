# SoT 06 — Data Paths and Files

## Purpose

Single source of truth for repository data folder conventions: where to place OSM, public, and generated assets. Do not commit large binary/CSV files; keep repo controllable.

## Definitions

- **Canonical path:** Repository-root-relative path that all scripts and docs use.
- **Version suffix:** File or folder name segment indicating data version (e.g. `_v1`, `_20260220`). 형식 확정 확인 필요.

## Canonical repo paths (do not commit large data files)

| Path | Contents | Git |
|------|----------|-----|
| **data/osm/** | PBF, poly, GeoJSON; OSM station/master outputs | Large files excluded via .gitignore |
| **data/public/** | Official CSV/JSON (e.g. Seoul Metro, bus master) | Large files excluded; small samples optional (확인 필요) |
| **data/generated/** | Match outputs, QA reports, derived artifacts | Committable if small; large outputs excluded (확인 필요) |

### data/osm/

- **Inputs:** `.pbf`, `.poly`, `.geojson` (OSM extracts, boundaries).
- **Outputs:** Station master outputs, graph-related exports from OSM.
- **Naming:** `region_description.pbf`, `region.poly`, `stations_v1.geojson` (snake_case, version suffix when applicable).

### data/public/

- **Inputs:** Official open data — Seoul Metro, bus master CSV/JSON.
- **Note:** Existing bus data uses `data/inbound/seoul/bus/` (see data/README_DATA_DIRS.md). Relationship between `data/public/` and `data/inbound/` 통합 여부 확인 필요.

### data/generated/

- **Contents:** Match outputs (e.g. node/edge match results), QA reports, any script-generated files.
- **Naming:** `match_output_YYYYMMDD.csv`, `qa_report_*.json` (snake_case, date or version suffix).

## File naming rules

- **Snake_case** for file and folder names: `station_master_v1.csv`, `seoul_metro_stations.json`.
- **Version suffix** when multiple versions exist: `_v1`, `_20260220`, or `_YYYYMMDD`. 구체 규칙 확인 필요.
- **No spaces.** Use `_` or `-` only. Avoid special characters.

## .gitignore recommendations (exact patterns)

Add or ensure the following under project root `.gitignore` (do not remove existing entries):

```
# Data — large/binary (SoT: docs/SOT/06_DATA_PATHS_AND_FILES.md)
data/osm/*.pbf
data/osm/*.poly
data/osm/*.geojson
data/public/**/*.csv
data/public/**/*.json
data/generated/*.csv
data/generated/*.json
data/generated/*.pdf
data/inbound/
```

- **Note:** `data/inbound/` is already ignored. Above patterns lock data/osm, data/public, data/generated for large files. Small sample or schema files may be committed if explicitly allowed (확인 필요).

## "How to place the files" checklist for operators

1. **Before adding OSM data:** Create `data/osm/` if missing. Place `.pbf`/`.poly`/`.geojson` there. Do not commit; confirm listed .gitignore patterns.
2. **Before adding public data:** Use `data/public/` (or existing `data/inbound/...` per README_DATA_DIRS). Prefer `data/public/` for new canonical placement (확인 필요). Do not commit large CSVs/JSONs.
3. **Before generating outputs:** Write match/QA outputs under `data/generated/`. Use snake_case and date/version suffix. If files are large, ensure .gitignore excludes them.
4. **After adding files:** Run `git status` and confirm no large data files are staged. Use `git check-ignore -v <path>` to verify ignore rules.
5. **Document one-off paths:** If a script expects a path not in this list, add it to this doc or mark "확인 필요".

## Assumptions

- Scripts and docs should reference these paths so that operators have a single place to look.
- Existing `data/inbound/`, `data/raw/`, `data/derived/` (README_DATA_DIRS.md) remain in use for bus/import flows; this doc extends conventions for OSM/graph/public and generated outputs.

## Open Questions (확인 필요)

- Version suffix format (e.g. `_v1` vs `_YYYYMMDD`) and who assigns versions.
- Whether `data/public/` should replace or coexist with `data/inbound/` for new data.
- Maximum committable size for `data/generated/` (e.g. small JSON/CSV samples).
- OSM station master output schema and exact filenames.
