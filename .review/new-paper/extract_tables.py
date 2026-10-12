from __future__ import annotations

import json
import zipfile
import xml.etree.ElementTree as ET
from pathlib import Path


source = Path(__file__).with_name(
    "CUSTOMER RELATIONSHIP MANAGEMENT SYSTEM FOR MJJD TELECOM.docx"
)
with zipfile.ZipFile(source) as archive:
    root = ET.fromstring(archive.read("word/document.xml"))

ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
tables = []

for index, table in enumerate(root.findall(".//w:tbl", ns), 1):
    rows = []
    for row in table.findall("./w:tr", ns):
        cells = []
        for cell in row.findall("./w:tc", ns):
            paragraphs = []
            for paragraph in cell.findall(".//w:p", ns):
                text = "".join(
                    node.text or "" for node in paragraph.findall(".//w:t", ns)
                ).strip()
                if text:
                    paragraphs.append(text)
            cells.append(" | ".join(paragraphs))
        rows.append(cells)
    item = {
        "index": index,
        "rows": len(rows),
        "cols": max((len(row) for row in rows), default=0),
        "data": rows,
    }
    tables.append(item)
    preview = rows[0] if rows else []
    print(f"{index:02d} {item['rows']}x{item['cols']} :: {preview}")

source.with_suffix(".tables.json").write_text(
    json.dumps(tables, ensure_ascii=False, indent=2), encoding="utf-8"
)
