#!/usr/bin/env python3
"""
gilime v1.7-20: PDF 텍스트 추출 (digital + OCR fallback)
- 디지털 텍스트 추출 시도 → 부족/손상 시 Tesseract OCR (kor+eng)
- 단일 PDF 또는 디렉터리 배치 처리 지원

Usage:
  python extract_text.py --input-file path/to/file.pdf --output path/to/output.txt
  python extract_text.py --input . --output extracted_text --tesseract-cmd "C:\\Program Files\\Tesseract-OCR\\tesseract.exe"
"""
from __future__ import annotations

import argparse
import os
import re
import shutil
import sys
from pathlib import Path
from typing import List, Tuple

import cv2
import numpy as np
import pytesseract
from pypdf import PdfReader
import pypdfium2 as pdfium


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Extract text from PDFs (digital + OCR fallback, kor+eng)"
    )
    grp = parser.add_mutually_exclusive_group(required=True)
    grp.add_argument("--input", type=Path, help="Directory to scan for PDFs")
    grp.add_argument("--input-file", type=Path, help="Single PDF file path")
    parser.add_argument(
        "--output",
        type=Path,
        required=True,
        help="Output file (single) or directory (batch)",
    )
    parser.add_argument("--lang", default="kor+eng", help="Tesseract lang (default: kor+eng)")
    parser.add_argument("--dpi", type=int, default=300, help="OCR DPI (default: 300)")
    parser.add_argument(
        "--min-text-len",
        type=int,
        default=25,
        help="Min chars for accepting digital text (default: 25)",
    )
    parser.add_argument(
        "--max-replacement-ratio",
        type=float,
        default=0.15,
        help="Max ratio of � in digital text before OCR (default: 0.15)",
    )
    parser.add_argument("--force-ocr", action="store_true", help="Skip digital, OCR every page")
    parser.add_argument(
        "--tesseract-cmd",
        help="Full path to tesseract.exe (Windows)",
    )
    parser.add_argument(
        "--tess-config",
        default="--psm 6",
        help="pytesseract config (default: --psm 6)",
    )
    parser.add_argument(
        "--output-format",
        choices=["full", "text_only"],
        default="text_only",
        help="full=page headers, text_only=raw text only (default: text_only for gilime)",
    )
    return parser.parse_args()


def ensure_tesseract_available(custom_cmd: str | None) -> None:
    if custom_cmd:
        cmd_path = Path(custom_cmd)
        if not cmd_path.exists():
            raise FileNotFoundError(f"tesseract not found: {cmd_path}")
        pytesseract.pytesseract.tesseract_cmd = str(cmd_path)
        return
    if os.environ.get("TESSERACT_CMD"):
        cmd_path = Path(os.environ["TESSERACT_CMD"])
        if cmd_path.exists():
            pytesseract.pytesseract.tesseract_cmd = str(cmd_path)
            return
    if shutil.which("tesseract") is None:
        raise RuntimeError(
            "Tesseract not found. Install it and/or set --tesseract-cmd or TESSERACT_CMD"
        )


def normalize_text(text: str) -> str:
    lines = [re.sub(r"\s+", " ", line).strip() for line in text.splitlines()]
    filtered = [line for line in lines if line]
    return "\n".join(filtered)


def digital_text_is_usable(text: str, min_len: int, max_replacement_ratio: float) -> bool:
    cleaned = text.strip()
    if len(cleaned) < min_len:
        return False
    total = len(cleaned)
    replacement_count = cleaned.count("�")
    if total == 0:
        return False
    if (replacement_count / total) > max_replacement_ratio:
        return False
    printable = sum(ch.isprintable() for ch in cleaned)
    return printable / total > 0.6


def render_page_to_image(doc: pdfium.PdfDocument, page_index: int, dpi: int) -> np.ndarray:
    page = doc[page_index]
    scale = dpi / 72.0
    bitmap = page.render(scale=scale, rotation=0)
    pil_image = bitmap.to_pil()
    return cv2.cvtColor(np.array(pil_image), cv2.COLOR_RGB2BGR)


def preprocess_for_ocr(image_bgr: np.ndarray) -> np.ndarray:
    gray = cv2.cvtColor(image_bgr, cv2.COLOR_BGR2GRAY)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    enhanced = clahe.apply(gray)
    denoised = cv2.bilateralFilter(enhanced, d=9, sigmaColor=75, sigmaSpace=75)
    thresh = cv2.adaptiveThreshold(
        denoised, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, cv2.THRESH_BINARY, 31, 2
    )
    return thresh


def ocr_page(
    doc: pdfium.PdfDocument,
    page_index: int,
    dpi: int,
    lang: str,
    tess_config: str,
) -> str:
    image = render_page_to_image(doc, page_index, dpi)
    processed = preprocess_for_ocr(image)
    text = pytesseract.image_to_string(processed, lang=lang, config=tess_config)
    return normalize_text(text)


def extract_pdf(
    pdf_path: Path,
    args: argparse.Namespace,
) -> Tuple[str, List[Tuple[int, str, int]]]:
    """Returns (full_text, stats)."""
    reader = PdfReader(str(pdf_path))
    doc = pdfium.PdfDocument(str(pdf_path))
    output_lines: List[str] = []
    text_only_parts: List[str] = []
    stats: List[Tuple[int, str, int]] = []

    for page_index, page in enumerate(reader.pages):
        source = "digital"
        text = ""
        if not args.force_ocr:
            digital_text = page.extract_text() or ""
            if digital_text_is_usable(
                digital_text, args.min_text_len, args.max_replacement_ratio
            ):
                text = normalize_text(digital_text)
            else:
                source = "ocr"
                text = ocr_page(
                    doc, page_index, args.dpi, args.lang, args.tess_config
                )
        else:
            source = "ocr"
            text = ocr_page(
                doc, page_index, args.dpi, args.lang, args.tess_config
            )

        page_header = f"===== Page {page_index + 1} ({source}) ====="
        output_lines.append(page_header)
        output_lines.append(text if text else "[No text detected]")
        text_only_parts.append(text if text else "")
        stats.append((page_index + 1, source, len(text)))

    doc.close()

    full_output = "\n\n".join(output_lines)
    text_only = "\n\n".join(text_only_parts)

    return (text_only if args.output_format == "text_only" else full_output, stats)


def run_single(args: argparse.Namespace) -> bool:
    pdf_path = args.input_file.resolve()
    if not pdf_path.exists():
        print(f"Error: file not found: {pdf_path}", file=sys.stderr)
        return False
    if not pdf_path.suffix.lower() == ".pdf":
        print(f"Error: not a PDF: {pdf_path}", file=sys.stderr)
        return False

    out_path = args.output.resolve()
    text, stats = extract_pdf(pdf_path, args)

    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text(text, encoding="utf-8")

    print(f"Processed {pdf_path.name}: {len(stats)} pages -> {out_path}")
    for page_no, src, length in stats:
        print(f"  page {page_no:02d} | {src:<7} | {length:5d} chars")
    return True


def run_batch(args: argparse.Namespace) -> bool:
    root = args.input.resolve()
    if not root.is_dir():
        print(f"Error: not a directory: {root}", file=sys.stderr)
        return False

    pdfs = sorted(p for p in root.glob("*.pdf") if p.is_file())
    if not pdfs:
        print(f"No PDFs in {root}", file=sys.stderr)
        return False

    out_dir = args.output.resolve()
    out_dir.mkdir(parents=True, exist_ok=True)

    for pdf in pdfs:
        text, stats = extract_pdf(pdf, args)
        rel_out = out_dir / f"{pdf.stem}.txt"
        rel_out.write_text(text, encoding="utf-8")
        print(f"Processed {pdf.name}: {len(stats)} pages -> {rel_out}")
        for page_no, src, length in stats:
            print(f"  page {page_no:02d} | {src:<7} | {length:5d} chars")
    return True


def main() -> None:
    args = parse_args()
    ensure_tesseract_available(args.tesseract_cmd)

    if args.input_file:
        ok = run_single(args)
    else:
        ok = run_batch(args)

    sys.exit(0 if ok else 1)


if __name__ == "__main__":
    main()
