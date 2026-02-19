#!/usr/bin/env python3
"""
Subway edges ingest v1: 서울교통공사_역간거리.csv → subway_edges_g1_v1.csv
Output: data/derived/seoul/subway/subway_edges_g1_v1.csv, _qa_edges_g1_v1.json
Columns: line_code, from_station_cd, to_station_cd, distance_m, time_sec, meta_json(optional)
Dedupe by (line_code, from_station_cd, to_station_cd). time_sec may be empty.
Single-column mode: if only one station column (e.g. 역명) is present, from = previous row's station (same line), to = current row.
Converts 거리(km) to m; 시간(분) "M:SS" or minutes to seconds.
Path resolver: data/public/서울교통공사_역간거리.csv then data/inbound/seoul/subway/**/
Encoding: utf-8-sig then cp949.
"""
from __future__ import annotations

import csv
import json
import os
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]

# Header alias mapping: logical field -> list of possible CSV column names (order = priority)
LINE_ALIASES = ["line_code", "LINE", "line", "호선", "노선", "노선코드", "노선명"]
FROM_ALIASES = ["from_station_cd", "출발역코드", "출발역명", "출발역", "이전역", "시작역코드", "상행역", "출발", "from_station", "from_station_id"]
TO_ALIASES = ["to_station_cd", "도착역코드", "도착역명", "도착역", "다음역", "역명", "종료역코드", "하행역", "도착", "to_station", "to_station_id"]
DISTANCE_ALIASES = ["distance_m", "거리(m)", "거리(km)", "역간거리", "거리", "distance", "거리(meter)"]
TIME_ALIASES = ["time_sec", "소요시간", "시간(초)", "시간(분)", "시간", "time", "travel_time_sec"]


def _resolve_column(headers: list[str], aliases: list[str]) -> str | None:
    """Return first header that matches any alias (case-insensitive)."""
    h_lower = {h.strip(): h for h in headers if h}
    for a in aliases:
        al = a.lower()
        for k, v in h_lower.items():
            if al in k.lower() or k.lower() == al:
                return v
    return None


def _resolve_station_distance_csv() -> Path:
    tried = []
    for name in [
        "data/public/서울교통공사_역간거리.csv",
    ]:
        p = REPO_ROOT / name.replace("/", os.sep)
        tried.append(str(p))
        if p.exists():
            return p
    inbound = REPO_ROOT / "data" / "inbound" / "seoul" / "subway"
    if inbound.exists():
        for f in inbound.rglob("*.csv"):
            if "역간" in f.name or "distance" in f.name.lower() or "거리" in f.name:
                tried.append(str(f))
                return f
    print("ERROR: Station distance CSV not found. Tried:", file=sys.stderr)
    for t in tried:
        print("  -", t, file=sys.stderr)
    sys.exit(1)


def _read_csv(path: Path) -> list[dict]:
    raw = path.read_bytes()
    for enc in ("utf-8-sig", "utf-8", "cp949"):
        try:
            text = raw.decode(enc)
            break
        except UnicodeDecodeError:
            continue
    else:
        raise RuntimeError(f"Could not decode {path}")
    reader = csv.DictReader(text.splitlines())
    return list(reader)


def main() -> None:
    path = _resolve_station_distance_csv()
    rows = _read_csv(path)
    input_rows = len(rows)
    headers = list(rows[0].keys()) if rows else []
    if not rows:
        print("WARN: no rows in", path, file=sys.stderr)
    line_key = _resolve_column(headers, LINE_ALIASES)
    from_key = _resolve_column(headers, FROM_ALIASES)
    to_key = _resolve_column(headers, TO_ALIASES)
    dist_key = _resolve_column(headers, DISTANCE_ALIASES)
    time_key = _resolve_column(headers, TIME_ALIASES)

    drop_reasons: dict[str, int] = {}
    by_line: dict[str, int] = {}

    def get(r: dict, key: str | None, *fallback: str) -> str:
        if key and r.get(key) is not None and str(r.get(key)).strip():
            return str(r.get(key)).strip()
        for k in fallback:
            if r.get(k) is not None and str(r.get(k)).strip():
                return str(r.get(k)).strip()
        return ""

    seen: set[tuple[str, str, str]] = set()
    out_rows: list[dict] = []
    parsed_rows = 0
    prev_line: str | None = None
    prev_station: str | None = None

    for r in rows:
        line = get(r, line_key, "line_code")
        from_cd = get(r, from_key, "from_station_cd")
        to_cd = get(r, to_key, "to_station_cd")
        if from_key is None and to_key:
            to_cd = get(r, to_key, "to_station_cd")
            if line and line == prev_line and prev_station is not None:
                from_cd = prev_station
            else:
                from_cd = ""
            prev_line = line
            prev_station = to_cd if to_cd else prev_station
        if not line:
            drop_reasons["missing_line"] = drop_reasons.get("missing_line", 0) + 1
            continue
        if not from_cd:
            drop_reasons["missing_from"] = drop_reasons.get("missing_from", 0) + 1
            continue
        if not to_cd:
            drop_reasons["missing_to"] = drop_reasons.get("missing_to", 0) + 1
            continue
        parsed_rows += 1
        key = (line, from_cd, to_cd)
        if key in seen:
            drop_reasons["duplicate"] = drop_reasons.get("duplicate", 0) + 1
            continue
        seen.add(key)
        dist = get(r, dist_key, "distance_m")
        time_s = get(r, time_key, "time_sec")
        if dist and dist_key and "km" in (dist_key or "").lower():
            try:
                dist = str(float(dist) * 1000)
            except ValueError:
                dist = ""
        try:
            if dist:
                float(dist)
        except ValueError:
            dist = ""
        if time_s and time_key and "분" in (time_key or ""):
            try:
                if ":" in str(time_s):
                    parts = str(time_s).strip().split(":")
                    time_s = str(int(float(parts[0]) * 60 + float(parts[1])) if len(parts) >= 2 else int(float(parts[0]) * 60))
                else:
                    time_s = str(int(float(time_s) * 60))
            except (ValueError, TypeError):
                time_s = ""
        try:
            if time_s:
                int(time_s)
        except ValueError:
            time_s = ""
        out_rows.append({
            "line_code": line,
            "from_station_cd": from_cd,
            "to_station_cd": to_cd,
            "distance_m": dist,
            "time_sec": time_s,
            "meta_json": "",
        })
        by_line[line] = by_line.get(line, 0) + 1

    out_dir = REPO_ROOT / "data" / "derived" / "seoul" / "subway"
    out_dir.mkdir(parents=True, exist_ok=True)
    out_csv = out_dir / "subway_edges_g1_v1.csv"
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=["line_code", "from_station_cd", "to_station_cd", "distance_m", "time_sec", "meta_json"])
        w.writeheader()
        w.writerows(out_rows)
    written_rows = len(out_rows)

    qa = {
        "input_path": str(path),
        "input_rows": input_rows,
        "parsed_rows": parsed_rows,
        "written_rows": written_rows,
        "resolved_columns": {
            "line_code": line_key,
            "from_station_cd": from_key,
            "to_station_cd": to_key,
            "distance_m": dist_key,
            "time_sec": time_key,
        },
        "csv_headers": headers,
        "drop_reasons": drop_reasons,
        "by_line": dict(sorted(by_line.items())),
    }
    qa_path = out_dir / "_qa_edges_g1_v1.json"
    with open(qa_path, "w", encoding="utf-8") as f:
        json.dump(qa, f, ensure_ascii=False, indent=2)

    print("OK: wrote", out_csv, "rows=", written_rows, "qa=", qa_path)


if __name__ == "__main__":
    main()
