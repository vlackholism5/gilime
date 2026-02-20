#!/usr/bin/env python3
"""
gilime v1.7-21: PDF 1회 구조화 파싱 → 고정 스키마 CSV

- 텍스트에서 추출 가능한 필드만 채우고, 정류장명·정류장ID는 null 허용 (이미지/수동 보정용)
- 출력 CSV로 후속 보정·Import 가능

Usage:
  python parse_shuttle_pdf_to_structured.py --input path/to/file.pdf --output parsed.csv
  python parse_shuttle_pdf_to_structured.py -i file.pdf -o parsed.csv
"""
from __future__ import annotations

import argparse
import csv
import re
import sys
from pathlib import Path
from typing import Any

try:
    from pypdf import PdfReader
except ImportError:
    PdfReader = None  # type: ignore[misc, assignment]


CSV_COLUMNS = [
    "route_label",
    "연번",
    "자치구",
    "운행대수",
    "운행시간",
    "운행구간_텍스트",
    "운행거리",
    "배정대수",
    "운행횟수",
    "배차간격",
    "seq_in_route",
    "raw_stop_name",
    "정류장명",
    "정류장ID",
]


def extract_text_from_pdf(pdf_path: Path) -> str:
    if PdfReader is None:
        raise RuntimeError("pypdf required: pip install pypdf")
    reader = PdfReader(str(pdf_path))
    parts = []
    for page in reader.pages:
        t = page.extract_text()
        if t:
            parts.append(t)
    return "\n\n".join(parts)


def normalize_route_label(raw: str | None) -> str:
    if not raw or not raw.strip():
        return "운행구간"
    s = re.sub(r"\s+", " ", raw.strip())
    return s[:255] if len(s) > 255 else s


def split_route_section(text: str) -> list[str]:
    """운행구간 텍스트를 ~, →, ↔ 등으로 분할. 앞뒤 ~ 제거."""
    if not text or not text.strip():
        return []
    s = text.strip()
    s = re.sub(r"^\s*~\s*", "", s)
    s = re.sub(r"\s*~\s*$", "", s)
    parts = re.split(r"\s*[~→↔←⇒⇔]\s*", s)
    out = []
    for p in parts:
        p = p.strip()
        if not p:
            continue
        if re.match(r"^\d+\s*(km|대|회)\s*$", p):
            continue
        if re.match(r"^임시\s*\d+\s*번", p):
            continue
        if re.match(r"^노선\s*\d+", p):
            continue
        out.append(p)
    return out


def parse_structured(text: str, default_route_label: str = "운행구간") -> list[dict[str, Any]]:
    """텍스트에서 구조화된 행 목록 추출."""
    rows: list[dict[str, Any]] = []
    lines = [ln.strip() for ln in text.splitlines() if ln.strip()]

    # 자치구: "금천구" 등 (단독 줄)
    자치구 = ""
    for ln in lines:
        if re.match(r"^[가-힣]+구\s*$", ln):
            자치구 = ln.strip()
            break

    # 운행대수: "○ 투입대수: 22대" 등
    운행대수 = ""
    for ln in lines:
        if "투입대수" in ln or "운행대수" in ln:
            m = re.search(r"(\d+대[^\)]*(?:\([^)]*\))?)", ln)
            if m:
                운행대수 = m.group(1).strip()
            break

    # 운행시간: "05:00～22:00"
    운행시간 = ""
    for ln in lines:
        if re.search(r"\d{1,2}\s*:\s*\d{2}\s*[～~\-]\s*\d{1,2}\s*:\s*\d{2}", ln):
            운행시간 = ln.strip()
            break

    # 연번 + 운행구간: "1 석수역~..." 다음 줄에 "~ 구로디지털단지역 12.6km", "8대", "10분~20분" 등
    route_label = default_route_label
    i = 0
    while i < len(lines):
        ln = lines[i]
        m = re.match(r"^(\d+)\s+(.*)$", ln)
        if not m:
            i += 1
            continue
        연번 = m.group(1).strip()
        block_lines = [m.group(2).strip()] if m.group(2).strip() else []
        i += 1
        while i < len(lines) and not re.match(r"^\d+\s+", lines[i]):
            block_lines.append(lines[i])
            i += 1
        block_text = " ".join(block_lines)
        # 운행거리: 12.6km, 10km
        운행거리 = ""
        km_m = re.search(r"(\d+\.?\d*\s*km)", block_text)
        if km_m:
            운행거리 = km_m.group(1).strip()
        # 배정대수: 8대, 14대
        배정대수 = ""
        dae_m = re.search(r"(\d+대(?:\s*\([^)]*\))?)", block_text)
        if dae_m:
            배정대수 = dae_m.group(1).strip()
        # 배차간격: 10분~20분
        배차간격 = ""
        min_m = re.search(r"(\d+\s*분\s*[~～\-]\s*\d+\s*분)", block_text)
        if min_m:
            배차간격 = min_m.group(1).strip()
        # 운행구간: km 앞까지 또는 전체에서 ~/→ 구간만
        운행구간_텍스트 = block_text
        if "km" in block_text:
            운행구간_텍스트 = block_text.split("km")[0].strip()
        # 괄호·km·대 등 메타 제거한 구간만
        for sep in ["12.6km", "10km", "8대", "14대", "대당", "16회"]:
            if sep in 운행구간_텍스트:
                운행구간_텍스트 = 운행구간_텍스트.split(sep)[0].strip()
        운행횟수 = "대당 16회" if "대당" in block_text and "회" in block_text else ""

        stops = split_route_section(운행구간_텍스트)
        for seq, raw_name in enumerate(stops, start=1):
            rows.append({
                "route_label": route_label,
                "연번": 연번,
                "자치구": 자치구,
                "운행대수": 운행대수,
                "운행시간": 운행시간,
                "운행구간_텍스트": 운행구간_텍스트,
                "운행거리": 운행거리,
                "배정대수": 배정대수,
                "운행횟수": 운행횟수,
                "배차간격": 배차간격,
                "seq_in_route": str(seq),
                "raw_stop_name": raw_name,
                "정류장명": "",
                "정류장ID": "",
            })

    # 연번 패턴으로 못 찾은 경우: 전체 텍스트에서 "~" 구간만 추출
    if not rows:
        for ln in lines:
            if "~" in ln or "→" in ln:
                stops = split_route_section(ln)
                for seq, raw_name in enumerate(stops, start=1):
                    rows.append({
                        "route_label": default_route_label,
                        "연번": "",
                        "자치구": 자치구,
                        "운행대수": 운행대수,
                        "운행시간": 운행시간,
                        "운행구간_텍스트": ln.strip(),
                        "운행거리": "",
                        "배정대수": "",
                        "운행횟수": "",
                        "배차간격": "",
                        "seq_in_route": str(seq),
                        "raw_stop_name": raw_name,
                        "정류장명": "",
                        "정류장ID": "",
                    })
                break

    return rows


def main() -> int:
    parser = argparse.ArgumentParser(description="PDF 1회 구조화 파싱 → CSV (고정 스키마, null 허용)")
    parser.add_argument("--input", "-i", type=Path, required=True, help="PDF 파일 경로")
    parser.add_argument("--output", "-o", type=Path, help="출력 CSV 경로 (미지정 시 stdout)")
    parser.add_argument("--route-label", type=str, default="운행구간", help="기본 route_label")
    args = parser.parse_args()

    if not args.input.exists():
        print(f"Error: file not found: {args.input}", file=sys.stderr)
        return 1

    try:
        text = extract_text_from_pdf(args.input)
    except Exception as e:
        print(f"Error: PDF text extraction failed: {e}", file=sys.stderr)
        return 1

    if not text.strip():
        print("Error: no text extracted from PDF", file=sys.stderr)
        return 1

    rows = parse_structured(text, default_route_label=args.route_label)
    if not rows:
        print("Warning: no structured rows parsed; output will be empty", file=sys.stderr)

    out_path = args.output
    if out_path:
        out_path.parent.mkdir(parents=True, exist_ok=True)
        with open(out_path, "w", newline="", encoding="utf-8-sig") as f:
            w = csv.DictWriter(f, fieldnames=CSV_COLUMNS, extrasaction="ignore")
            w.writeheader()
            w.writerows(rows)
        print(f"Wrote {len(rows)} rows to {out_path}", file=sys.stderr)
    else:
        import io
        buf = io.StringIO()
        w = csv.DictWriter(buf, fieldnames=CSV_COLUMNS, extrasaction="ignore")
        w.writeheader()
        w.writerows(rows)
        print(buf.getvalue())

    return 0


if __name__ == "__main__":
    sys.exit(main())
