#!/usr/bin/env python3
"""
WALK edges station ↔ bus stop v1.
Inputs: station master (from match_v1 best rows CSV), 서울시 정류장마스터 CSV (path resolver).
Haversine distance. <=400m create; 401~600m create with low_confidence flag.
time_sec = ceil(distance_m / 1.2) + 60
Output: data/derived/seoul/bus/walk_edges_station_to_bus_v1.csv
Path resolver: data/public/서울시_정류장마스터_정보.csv then data/inbound/seoul/bus/stop_master/서울시_정류장마스터_정보.csv
"""
from __future__ import annotations

import csv
import math
import os
import sys
from pathlib import Path

REPO_ROOT = Path(__file__).resolve().parents[2]


def _resolve_bus_stop_master_csv() -> Path:
    tried = []
    for name in [
        "data/public/서울시_정류장마스터_정보.csv",
        "data/public/서울시 정류장마스터_정보.csv",
    ]:
        p = REPO_ROOT / name.replace("/", os.sep)
        tried.append(str(p))
        if p.exists():
            return p
    p = REPO_ROOT / "data" / "inbound" / "seoul" / "bus" / "stop_master" / "서울시_정류장마스터_정보.csv"
    tried.append(str(p))
    if p.exists():
        return p
    print("ERROR: Bus stop master CSV not found. Tried:", file=sys.stderr)
    for t in tried:
        print("  -", t, file=sys.stderr)
    sys.exit(1)


def _resolve_station_match_csv() -> Path:
    p = REPO_ROOT / "data" / "derived" / "seoul" / "subway" / "subway_station_match_v1.csv"
    if p.exists():
        return p
    print("ERROR: Run subway_match_v1.py first. Expected:", p, file=sys.stderr)
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
    return list(csv.DictReader(text.splitlines()))


def _haversine_m(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    R = 6371000
    phi1, phi2 = math.radians(lat1), math.radians(lat2)
    dphi = math.radians(lat2 - lat1)
    dlam = math.radians(lon2 - lon1)
    a = math.sin(dphi / 2) ** 2 + math.cos(phi1) * math.cos(phi2) * math.sin(dlam / 2) ** 2
    return 2 * R * math.asin(math.sqrt(a))


def main() -> None:
    station_path = _resolve_station_match_csv()
    bus_path = _resolve_bus_stop_master_csv()
    station_rows = _read_csv(station_path)
    bus_rows = _read_csv(bus_path)

    def get(row: dict, *keys: str) -> str:
        for k in keys:
            v = row.get(k)
            if v is not None and str(v).strip():
                return str(v).strip()
        return ""

    # Station: osm_lat, osm_lon, station_cd, station_name, line_code
    # Bus: 정류장ID/정류장명칭/위도/경도 or stop_id, stop_name, lat, lng
    bus_id_key = next((k for k in (bus_rows[0].keys() if bus_rows else []) if "정류장" in k and "ID" in k or "id" in k.lower()), "stop_id")
    bus_name_key = next((k for k in (bus_rows[0].keys() if bus_rows else []) if "명칭" in k or "name" in k.lower()), "stop_name")
    bus_lat_key = next((k for k in (bus_rows[0].keys() if bus_rows else []) if "위도" in k or "lat" in k.lower()), "lat")
    bus_lon_key = next((k for k in (bus_rows[0].keys() if bus_rows else []) if "경도" in k or "lon" in k.lower() or "lng" in k.lower()), "lon")

    out_rows: list[dict] = []
    for st in station_rows:
        slat_s = get(st, "osm_lat", "lat")
        slon_s = get(st, "osm_lon", "lon")
        try:
            slat = float(slat_s)
            slon = float(slon_s)
        except (ValueError, TypeError):
            continue
        station_cd = get(st, "station_cd")
        station_name = get(st, "station_name")
        line_code = get(st, "line_code")
        for b in bus_rows:
            blat_s = get(b, bus_lat_key, "위도")
            blon_s = get(b, bus_lon_key, "경도", "lng")
            try:
                blat = float(blat_s)
                blon = float(blon_s)
            except (ValueError, TypeError):
                continue
            dist = _haversine_m(slat, slon, blat, blon)
            if dist > 600:
                continue
            low_confidence = "1" if 401 <= dist <= 600 else "0"
            time_sec = int(math.ceil(dist / 1.2) + 60)
            out_rows.append({
                "station_cd": station_cd,
                "station_name": station_name,
                "line_code": line_code,
                "bus_stop_id": get(b, bus_id_key, "정류장ID"),
                "bus_stop_name": get(b, bus_name_key, "정류장명칭"),
                "distance_m": round(dist, 2),
                "time_sec": time_sec,
                "low_confidence": low_confidence,
            })

    out_dir = REPO_ROOT / "data" / "derived" / "seoul" / "bus"
    out_dir.mkdir(parents=True, exist_ok=True)
    out_csv = out_dir / "walk_edges_station_to_bus_v1.csv"
    with open(out_csv, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=[
            "station_cd", "station_name", "line_code", "bus_stop_id", "bus_stop_name",
            "distance_m", "time_sec", "low_confidence",
        ])
        w.writeheader()
        w.writerows(out_rows)
    print("OK: wrote", out_csv, "rows=", len(out_rows))


if __name__ == "__main__":
    main()
