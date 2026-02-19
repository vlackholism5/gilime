#!/usr/bin/env python3
"""
gilime v1.7-20: GPT 검수 파이프라인

후보 JSON/CSV → OpenAI/OpenAPI 호출 → 검수 결과 JSON 생성 → import_candidate_review 업로드

Usage:
  python gpt_review_pipeline.py --input candidates.json --output review_results.json
  python gpt_review_pipeline.py --input candidates.csv --output review_results.json

환경변수 (필수):
  OPENAI_API_KEY  또는  GILIME_OPENAPI_API_KEY  — API 키

환경변수 (선택):
  OPENAI_BASE_URL — 커스텀 API 엔드포인트 (예: https://api.openai.com/v1)
  OPENAI_MODEL   — 모델명 (기본: gpt-4o-mini)
"""
from __future__ import annotations

import argparse
import csv
import json
import os
import sys
from pathlib import Path
from typing import Any


def load_candidates(path: Path) -> list[dict]:
    """Load candidates from JSON or CSV."""
    text = path.read_text(encoding="utf-8")
    ext = path.suffix.lower()

    if ext == ".json":
        data = json.loads(text)
        return data.get("candidates", data) if isinstance(data, dict) else data

    if ext == ".csv":
        rows = list(csv.DictReader(text.splitlines()))
        return [dict(r) for r in rows]

    raise ValueError(f"Unsupported format: {ext}. Use .json or .csv")


def build_prompt(candidates: list[dict], route_label: str = "") -> str:
    """Build GPT prompt for batch review."""
    return f"""당신은 서울시 버스 정류장 데이터 검수자입니다.
아래 후보 목록에서 각 행을 검토하여 approve 또는 reject를 결정하세요.

**규칙:**
- raw_stop_name이 유효한 정류장명이면 approve
- 노선 메타(예: "3km 5대 40회"), 숫자만, 빈값/노이즈면 reject
- approve 시 matched_stop_id 필수: suggested_stop_id가 있으면 우선 사용, 없으면 정류장명으로 추정
- matched_stop_name은 선택 (없으면 raw_stop_name 사용)

**route_label:** {route_label or "(미지정)"}

**후보 목록 (JSON):**
```json
{json.dumps(candidates, ensure_ascii=False, indent=2)}
```

**응답 형식 (반드시 아래 JSON만 반환, 다른 텍스트 없이):**
```json
[
  {{"candidate_id": 101, "action": "approve", "matched_stop_id": "12345", "matched_stop_name": "성동세무서"}},
  {{"candidate_id": 102, "action": "reject"}}
]
```
"""


def call_openapi(
    candidates: list[dict],
    api_key: str,
    base_url: str | None,
    model: str,
    route_label: str = "",
) -> list[dict]:
    """Call OpenAI-compatible API for review."""
    try:
        from openai import OpenAI
    except ImportError:
        print("openai 패키지가 필요합니다: pip install -r requirements-gpt.txt", file=sys.stderr)
        sys.exit(1)

    client = OpenAI(api_key=api_key, base_url=base_url) if base_url else OpenAI(api_key=api_key)
    prompt = build_prompt(candidates, route_label)

    resp = client.chat.completions.create(
        model=model,
        messages=[
            {
                "role": "system",
                "content": "당신은 서울시 버스 정류장 검수 전문가입니다. JSON 형식으로만 응답하세요.",
            },
            {"role": "user", "content": prompt},
        ],
        temperature=0.1,
    )
    content = (resp.choices[0].message.content or "").strip()

    # Parse JSON from response (handle markdown code block)
    if "```json" in content:
        content = content.split("```json")[1].split("```")[0].strip()
    elif "```" in content:
        content = content.split("```")[1].split("```")[0].strip()

    return json.loads(content)


def run_review(
    candidates: list[dict],
    api_key: str,
    base_url: str | None,
    model: str,
    route_label: str = "",
    batch_size: int = 50,
) -> list[dict]:
    """Run review in batches and merge results."""
    results: list[dict] = []
    for i in range(0, len(candidates), batch_size):
        batch = candidates[i : i + batch_size]
        batch_results = call_openapi(candidates=batch, api_key=api_key, base_url=base_url, model=model, route_label=route_label)
        results.extend(batch_results)
    return results


def main() -> None:
    # Load .env if python-dotenv available
    try:
        from dotenv import load_dotenv
        load_dotenv()
    except ImportError:
        pass

    parser = argparse.ArgumentParser(description="GPT 검수 파이프라인 — 후보 → 검수 결과")
    parser.add_argument("--input", "-i", type=Path, required=True, help="후보 JSON/CSV (export_candidates 출력)")
    parser.add_argument("--output", "-o", type=Path, required=True, help="검수 결과 JSON (import_candidate_review 업로드용)")
    parser.add_argument("--route-label", type=str, default="", help="노선 라벨 (GPT 컨텍스트용)")
    parser.add_argument("--batch-size", type=int, default=50, help="요청당 후보 수 (기본: 50)")
    parser.add_argument("--dry-run", action="store_true", help="API 호출 없이 입력 파일만 검증")
    args = parser.parse_args()

    api_key = os.environ.get("OPENAI_API_KEY") or os.environ.get("GILIME_OPENAPI_API_KEY")
    base_url = os.environ.get("OPENAI_BASE_URL") or None
    model = os.environ.get("OPENAI_MODEL") or "gpt-4o-mini"

    if not args.input.exists():
        print(f"입력 파일 없음: {args.input}", file=sys.stderr)
        sys.exit(1)

    candidates = load_candidates(args.input)

    # Normalize: ensure candidate_id
    for c in candidates:
        if "candidate_id" not in c and "id" in c:
            c["candidate_id"] = c["id"]

    if not candidates:
        print("후보가 없습니다.", file=sys.stderr)
        sys.exit(1)

    route_label = args.route_label
    if not route_label and isinstance(candidates[0].get("route_label"), str):
        route_label = candidates[0]["route_label"]

    if args.dry_run:
        print(f"[DRY-RUN] 후보 {len(candidates)}건, route_label={route_label}")
        sys.exit(0)

    if not api_key:
        print("OPENAI_API_KEY 또는 GILIME_OPENAPI_API_KEY 환경변수를 설정하세요.", file=sys.stderr)
        sys.exit(1)

    print(f"후보 {len(candidates)}건 검수 중... (model={model})")
    results = run_review(
        candidates=candidates,
        api_key=api_key,
        base_url=base_url,
        model=model,
        route_label=route_label,
        batch_size=args.batch_size,
    )

    out = {"candidates": results, "meta": {"route_label": route_label, "count": len(results)}}
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(out, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"검수 결과 저장: {args.output}")


if __name__ == "__main__":
    main()
