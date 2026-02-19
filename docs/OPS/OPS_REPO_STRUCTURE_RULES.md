# OPS — Repo structure rules

## Folder roles

| Area | Path | Role |
|------|------|------|
| **Scripts** | `scripts/php/` | PHP CLI scripts |
| | `scripts/python/` | Python scripts |
| | `scripts/ps1/` | PowerShell scripts |
| | `scripts/node/` | Node.js scripts (e.g. ensure-data-dirs.js) |
| **SQL** | `sql/migrations/` | Schema / one-time DDL |
| | `sql/views/` | CREATE VIEW |
| | `sql/ingest/` | Load/import SQL |
| | `sql/validate/` | Read-only validation/query SQL |
| | `sql/archive/` | Deprecated/legacy |
| **Unchanged** | `app/`, `public/`, `data/`, `docs/` | Application, web root, data, documentation |

## Naming rules

- **SQL:** `v<ver>-<nn>_<topic>.sql` (e.g. `v0.8-07_view_station_lines_g1.sql`).
- **Scripts:** `<verb>_<subject>.php` or `<topic>_vN.php`; PS1/Node by purpose.
- **Docs:** `OPS_*`, `SOT_*` in `docs/OPS/`, `docs/SOT/`.

## Where new MVP+ files go

- New PHP CLI → `scripts/php/`
- New Python → `scripts/python/`
- New PowerShell → `scripts/ps1/`
- New Node → `scripts/node/`
- New SQL migrations (DDL) → `sql/migrations/`
- New validation/query SQL → `sql/validate/`
- New VIEWs → `sql/views/`
- New ingest SQL → `sql/ingest/`
