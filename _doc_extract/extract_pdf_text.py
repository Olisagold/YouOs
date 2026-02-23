#!/usr/bin/env python3
"""
Lightweight PDF text extractor for ReportLab-style PDFs.

This avoids external dependencies and extracts text from content streams
that use Tj/TJ/'/" text operators.
"""

from __future__ import annotations

import argparse
import base64
import re
import sys
import zlib
from pathlib import Path


OBJECT_RE = re.compile(rb"(\d+)\s+(\d+)\s+obj(.*?)endobj", re.S)
PAREN_TEXT_RE = re.compile(rb"\((?:\\.|[^\\)])*\)")
TEXT_ARRAY_RE = re.compile(rb"\[(.*?)\]\s*TJ", re.S)
TEXT_TJ_RE = re.compile(rb"\((?:\\.|[^\\)])*\)\s*Tj")
TEXT_QUOTE_RE = re.compile(rb"\((?:\\.|[^\\)])*\)\s*'")
TEXT_DQUOTE_RE = re.compile(rb"\((?:\\.|[^\\)])*\)\s+[-\d.]+\s+[-\d.]+\s+\"", re.S)

ESC_MAP = {
    ord("n"): b"\n",
    ord("r"): b"\r",
    ord("t"): b"\t",
    ord("b"): b"\b",
    ord("f"): b"\f",
    ord("("): b"(",
    ord(")"): b")",
    ord("\\"): b"\\",
}


def _unescape_pdf_string(raw: bytes) -> bytes:
    out = bytearray()
    i = 0
    n = len(raw)

    while i < n:
        ch = raw[i]
        if ch != 0x5C:  # '\'
            out.append(ch)
            i += 1
            continue

        i += 1
        if i >= n:
            break

        esc = raw[i]

        # Line continuation: backslash followed by line break
        if esc in (0x0A, 0x0D):
            if esc == 0x0D and i + 1 < n and raw[i + 1] == 0x0A:
                i += 1
            i += 1
            continue

        if esc in ESC_MAP:
            out.extend(ESC_MAP[esc])
            i += 1
            continue

        # Octal escape \ddd (1-3 octal digits)
        if 0x30 <= esc <= 0x37:
            oct_digits = [esc]
            j = i + 1
            for _ in range(2):
                if j < n and 0x30 <= raw[j] <= 0x37:
                    oct_digits.append(raw[j])
                    j += 1
                else:
                    break
            out.append(int(bytes(oct_digits), 8))
            i = j
            continue

        out.append(esc)
        i += 1

    return bytes(out)


def _decode_pdf_text(raw: bytes) -> str:
    if raw.startswith(b"\xfe\xff"):
        try:
            return raw[2:].decode("utf-16-be", errors="ignore")
        except Exception:
            pass

    if raw.startswith(b"\xff\xfe"):
        try:
            return raw[2:].decode("utf-16-le", errors="ignore")
        except Exception:
            pass

    try:
        return raw.decode("utf-8")
    except Exception:
        return raw.decode("latin-1", errors="ignore")


def _clean_line(text: str) -> str:
    # Common mojibake/control-char cleanup from PDF extraction.
    cleaned = text.replace("\x7f", "-")
    cleaned = cleaned.replace("Â–", "-").replace("Â", "")
    cleaned = cleaned.translate(
        {
            0x95: ord("-"),
            0x96: ord("-"),
            0x97: ord("-"),
            0x91: ord("'"),
            0x92: ord("'"),
            0x93: ord('"'),
            0x94: ord('"'),
            0xAE: ord(">"),
        }
    )
    cleaned = "".join(ch for ch in cleaned if ch == "\t" or ch == "\n" or ord(ch) >= 0x20)
    return cleaned


def _normalize_stream_bytes(stream_data: bytes) -> bytes:
    # Trim EOL immediately after stream marker.
    if stream_data.startswith(b"\r\n"):
        stream_data = stream_data[2:]
    elif stream_data.startswith(b"\n") or stream_data.startswith(b"\r"):
        stream_data = stream_data[1:]

    # Trim trailing EOL before endstream.
    if stream_data.endswith(b"\r\n"):
        stream_data = stream_data[:-2]
    elif stream_data.endswith(b"\n") or stream_data.endswith(b"\r"):
        stream_data = stream_data[:-1]

    return stream_data


def _decode_stream(stream_data: bytes, dict_part: bytes) -> bytes:
    decoded = stream_data

    # Common ReportLab chain: [/ASCII85Decode /FlateDecode]
    if b"/ASCII85Decode" in dict_part:
        decoded = base64.a85decode(decoded, adobe=True)

    if b"/FlateDecode" in dict_part:
        decoded = zlib.decompress(decoded)

    return decoded


def _extract_text_lines_from_stream(decoded: bytes) -> list[str]:
    found: list[str] = []

    for arr_match in TEXT_ARRAY_RE.finditer(decoded):
        arr = arr_match.group(1)
        parts: list[str] = []
        for p in PAREN_TEXT_RE.finditer(arr):
            raw_chunk = p.group(0)[1:-1]
            text = _decode_pdf_text(_unescape_pdf_string(raw_chunk)).strip()
            if text:
                parts.append(text)
        if parts:
            found.append("".join(parts))

    for patt in (TEXT_TJ_RE, TEXT_QUOTE_RE, TEXT_DQUOTE_RE):
        for match in patt.finditer(decoded):
            p = PAREN_TEXT_RE.search(match.group(0))
            if not p:
                continue
            raw_chunk = p.group(0)[1:-1]
            text = _decode_pdf_text(_unescape_pdf_string(raw_chunk)).strip()
            if text:
                found.append(text)

    return found


def extract_pdf_text(pdf_path: Path) -> str:
    data = pdf_path.read_bytes()
    lines: list[str] = []

    for obj in OBJECT_RE.finditer(data):
        content = obj.group(3)
        if b"stream" not in content:
            continue

        dict_part, _, rem = content.partition(b"stream")
        stream_data, _, _ = rem.partition(b"endstream")
        stream_data = _normalize_stream_bytes(stream_data)

        try:
            decoded = _decode_stream(stream_data, dict_part)
        except Exception:
            # Skip unreadable stream instead of failing the whole extraction.
            continue

        lines.extend(_extract_text_lines_from_stream(decoded))

    # Remove consecutive duplicates and clean spacing.
    cleaned: list[str] = []
    for line in lines:
        normalized = _clean_line(" ".join(line.split()))
        if not normalized:
            continue
        if not cleaned or cleaned[-1] != normalized:
            cleaned.append(normalized)

    # Merge orphan bullet markers with the next line.
    merged: list[str] = []
    i = 0
    while i < len(cleaned):
        current = cleaned[i]
        if current in {"-", "•"} and i + 1 < len(cleaned):
            merged.append(f"- {cleaned[i + 1]}")
            i += 2
            continue
        merged.append(current)
        i += 1

    return "\n".join(merged).strip() + "\n"


def main() -> int:
    parser = argparse.ArgumentParser(description="Extract text from a PDF into a .txt file.")
    parser.add_argument("input_pdf", type=Path, help="Path to input PDF file")
    parser.add_argument("output_txt", type=Path, help="Path to output text file")
    args = parser.parse_args()

    if not args.input_pdf.exists():
        print(f"Input PDF not found: {args.input_pdf}", file=sys.stderr)
        return 1

    text = extract_pdf_text(args.input_pdf)
    args.output_txt.write_text(text, encoding="utf-8")
    print(f"Extracted {len(text.splitlines())} lines to {args.output_txt}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
