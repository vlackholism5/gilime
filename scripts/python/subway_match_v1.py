#!/usr/bin/env python3
"""
Subway station matching v1: OSM ↔ official Seoul Metro.
Outputs: data/derived/seoul/subway/subway_station_match_v1.csv, _qa_station_match_v1.json, _qa_station_unmatched_top30.csv
Path resolver: tries data/osm/, data/public/ then fallback data/inbound/seoul/subway/, data/inbound/seoul/bus/stop_master/.
Encoding: try utf-8-sig then cp949.
"""
from __future__ import annotations

import csv
import json
import os
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]

# --- Path resolver ---
def _resolve_osm_station_csv() -> Path:
    tried = []
    p = REPO_ROOT / "data" / "osm" / "subway_station_master.csv"
    tried.append(str(p))
    if p.exists():
        return p
    for d in (REPO_ROOT / "data" / "inbound" / "seoul" / "subway").rglob("*"):
        if d.is_file() and d.suffix.lower() == ".csv" and "station" in d.name.lower():
            tried.append(str(d))
            return d
    print("ERROR: OSM station CSV not found. Tried:", file=sys.stderr)
    for t in tried:
        print("  -", t, file=sys.stderr)
    sys.exit(1)

def _resolve_official_metro_station_csv() -> Path:
    tried = []
    for name in [
        "data/public/서울교통공사_노선별 지하철역 정보.csv",
        "data/public/서울교통공사_노선별_지하철역_정보.csv",
    ]:
        p = REPO_ROOT / name.replace("/", os.sep)
        tried.append(str(p))
        if p.exists():
            return p
    inbound = REPO_ROOT / "data" / "inbound" / "seoul" / "subway"
    if inbound.exists():
        for f in inbound.rglob("*.csv"):
            if "지하철역" in f.name or "station" in f.name.lower():
                tried.append(str(f))
                return f
    print("ERROR: Official Metro station CSV not found. Tried:", file=sys.stderr)
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
        raise RuntimeError(f"Could not decode {path} with utf-8-sig or cp949")
    reader = csv.DictReader(text.splitlines())
    return list(reader)

def _normalize_name(s: str) -> str:
    s = (s or "").strip()
    while "(" in s and ")" in s:
        i, j = s.find("("), s.find(")")
        if i != -1 and j != -1 and j > i:
            s = (s[:i] + s[j + 1 :]).strip()
    s = " ".join(s.split())
    if s.endswith("역") and len(s) > 1:
        s = s[:-1].strip()
    return s

def _alias_name(s: str) -> str:
    s = _normalize_name(s)
    if s == "서울대입구역":
        return "서울대입구"
    if s == "홍대 입구" or s == "홍대입구역":
        return "홍대입구"
    return s

def main() -> None:
    osm_path = _resolve_osm_station_csv()
    off_path = _resolve_official_metro_station_csv()

    try:
        osm_rows = _read_csv(osm_path)
        off_rows = _read_csv(off_path)
    except Exception as e:
        print("ERROR reading CSV:", e, file=sys.stderr)
        sys.exit(1)

    # Detect column names (flexible)
    def get(row: dict, *keys: str, default: str = "") -> str:
        for k in keys:
            v = row.get(k) or row.get(k.strip())
            if v is not None and str(v).strip():
                return str(v).strip()
        return default

    first_osm = osm_rows[0] if osm_rows else {}
    osm_name_key = "name"
    for k in ["name", "osm_name", "station_name", "name_ko"]:
        if k in first_osm:
            osm_name_key = k
            break
    osm_lat_key = next((k for k in first_osm if "lat" in k.lower() or "위도" in k), "lat")
    osm_lon_key = next((k for k in first_osm if "lon" in k.lower() or "경도" in k or "lng" in k.lower()), "lon")
    osm_id_key = next((k for k in first_osm if "id" in k.lower() or "osm" in k.lower() or "full_id" in k.lower()), "osm_id")

    first_off = off_rows[0] if off_rows else {}
    off_cd = next((k for k in first_off if "station_cd" in k or "역코드" in k or "코드" in k), "station_cd")
    off_name = next((k for k in first_off if "station_name" in k or "역명" in k or "지하철역" in k or "역이름" in k), "station_name")
    off_line = next((k for k in first_off if "line" in k.lower() or "노선" in k), "line_code")
    off_fr = next((k for k in first_off if "fr_code" in k or "역번호" in k or "순번" in k), "fr_code")

    off_by_norm: dict[str, list[dict]] = {}
    off_by_alias: dict[str, list[dict]] = {}
    for r in off_rows:
        name = get(r, off_name, "station_name", "역명")
        n = _normalize_name(name)
        a = _alias_name(name)
        if n not in off_by_norm:
            off_by_norm[n] = []
        off_by_norm[n].append(r)
        if a not in off_by_alias:
            off_by_alias[a] = []
        off_by_alias[a].append(r)

    def lat_lon(row: dict) -> tuple[float | None, float | None]:
        lat = get(row, osm_lat_key, "lat", "위도")
        lon = get(row, osm_lon_key, "lon", "lng", "경도")
        try:
            la = float(lat) if lat else None
        except ValueError:
            la = None
        try:
            lo = float(lon) if lon else None
        except ValueError:
            lo = None
        return la, lo

    def haversine_m(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
        import math
        R = 6371000
        phi1, phi2 = math.radians(lat1), math.radians(lat2)
        dphi = math.radians(lat2 - lat1)
        dlam = math.radians(lon2 - lon1)
        a = math.sin(dphi / 2) ** 2 + math.cos(phi1) * math.cos(phi2) * math.sin(dlam / 2) ** 2
        return 2 * R * math.asin(math.sqrt(a))

    out_rows: list[dict] = []
    qa_matched = 0
    qa_unmatched: list[dict] = []

    for o in osm_rows:
        osm_name = get(o, osm_name_key, "name")
        osm_lat, osm_lon = lat_lon(o)
        osm_id = get(o, osm_id_key, "osm_id", "full_id")
        norm = _normalize_name(osm_name)
        alias = _alias_name(osm_name)

        best: dict | None = None
        match_level = "NONE"
        confidence = 0.0
        reason = "no_candidate"

        candidates = off_by_norm.get(norm) or off_by_alias.get(alias) or []
        if candidates:
            if len(candidates) == 1 and norm == _normalize_name(get(candidates[0], off_name, "station_name")):
                best = candidates[0]
                match_level = "HIGH"
                confidence = 0.98
                reason = "exact_normalized"
            elif len(candidates) == 1:
                best = candidates[0]
                match_level = "MED"
                confidence = 0.85
                reason = "alias"
            else:
                # Multiple: choose by distance if coords available
                if osm_lat is not None and osm_lon is not None:
                    best_dist = float("inf")
                    for c in candidates:
                        lat_c = get(c, "lat", "위도", "latitude")
                        lon_c = get(c, "lon", "경도", "lng", "longitude")
                        try:
                            lc = float(lat_c)
                            lc2 = float(lon_c)
                        except (ValueError, TypeError):
                            continue
                        d = haversine_m(osm_lat, osm_lon, lc, lc2)
                        if d <= 250 and d < best_dist:
                            best_dist = d
                            best = c
                            match_level = "LOW"
                            confidence = max(0.60, 0.79 - (d / 250) * 0.19)
                            reason = "nearest_within_250m"
                    if best is None:
                        best = candidates[0]
                        match_level = "LOW"
                        confidence = 0.65
                        reason = "multi_candidate_first"
                else:
                    best = candidates[0]
                    match_level = "LOW"
                    confidence = 0.65
                    reason = "multi_candidate_no_coord"
        if best:
            qa_matched += 1
            out_rows.append({
                "osm_name": osm_name,
                "osm_lat": osm_lat if osm_lat is not None else "",
                "osm_lon": osm_lon if osm_lon is not None else "",
                "osm_full_id": osm_id,
                "station_cd": get(best, off_cd, "station_cd", "역코드"),
                "station_name": get(best, off_name, "station_name", "역명"),
                "line_code": get(best, off_line, "line_code", "노선"),
                "fr_code": get(best, off_fr, "fr_code", "역번호"),
                "match_level": match_level,
                "confidence": round(confidence, 4),
                "reason": reason,
            })
        else:
            qa_unmatched.append({"osm_name": osm_name, "osm_lat": osm_lat, "osm_lon": osm_lon, "osm_full_id": osm_id})

    out_dir = REPO_ROOT / "data" / "derived" / "seoul" / "subway"
    out_dir.mkdir(parents=True, exist_ok=True)

    out_csv = out_dir / "subway_station_match_v1.csv"
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=[
            "osm_name", "osm_lat", "osm_lon", "osm_full_id",
            "station_cd", "station_name", "line_code", "fr_code",
            "match_level", "confidence", "reason",
        ])
        w.writeheader()
        w.writerows(out_rows)

    by_level: dict[str, int] = {}
    for r in out_rows:
        lev = r.get("match_level", "NONE")
        by_level[lev] = by_level.get(lev, 0) + 1
    qa_json = out_dir / "_qa_station_match_v1.json"
    total = len(osm_rows)
    with open(qa_json, "w", encoding="utf-8") as f:
        json.dump({
            "total_osm": total,
            "matched": qa_matched,
            "unmatched": len(qa_unmatched),
            "match_rate_pct": round(100 * qa_matched / total, 2) if total else 0,
            "by_level": by_level,
        }, f, ensure_ascii=False, indent=2)

    un_top = out_dir / "_qa_station_unmatched_top30.csv"
    with open(un_top, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=["osm_name", "osm_lat", "osm_lon", "osm_full_id"])
        w.writeheader()
        w.writerows(qa_unmatched[:30])

    print("OK: wrote", out_csv, "rows=", len(out_rows), "qa_matched=", qa_matched, "unmatched=", len(qa_unmatched))


if __name__ == "__main__":
    main()
