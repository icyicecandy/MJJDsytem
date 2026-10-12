from pathlib import Path
from zipfile import ZipFile
import json

from docx import Document


source = Path(__file__).with_name("MJJD CRM.docx")
document = Document(source)

lines = []
headings = []
for index, paragraph in enumerate(document.paragraphs, start=1):
    text = paragraph.text.strip()
    if not text:
        continue
    style = paragraph.style.name if paragraph.style else ""
    lines.append(f"[P{index:04d}][{style}] {text}")
    if style.lower().startswith(("heading", "title", "subtitle")):
        headings.append({"paragraph": index, "style": style, "text": text})

for table_index, table in enumerate(document.tables, start=1):
    lines.append(f"\n[TABLE {table_index}]")
    for row_index, row in enumerate(table.rows, start=1):
        values = [" ".join(cell.text.split()) for cell in row.cells]
        lines.append(f"[R{row_index:03d}] " + " | ".join(values))

with ZipFile(source) as archive:
    names = set(archive.namelist())
    comments = "word/comments.xml" in names
    document_xml = archive.read("word/document.xml").decode("utf-8", errors="replace")
    metadata = {
        "paragraphs": len(document.paragraphs),
        "tables": len(document.tables),
        "inline_shapes": len(document.inline_shapes),
        "sections": len(document.sections),
        "headings": len(headings),
        "comments_present": comments,
        "tracked_insertions": document_xml.count("<w:ins"),
        "tracked_deletions": document_xml.count("<w:del"),
    }

Path(__file__).with_name("capstone-extracted.txt").write_text(
    "\n".join(lines), encoding="utf-8"
)
Path(__file__).with_name("capstone-headings.json").write_text(
    json.dumps(headings, indent=2, ensure_ascii=False), encoding="utf-8"
)
Path(__file__).with_name("capstone-metadata.json").write_text(
    json.dumps(metadata, indent=2), encoding="utf-8"
)

print(json.dumps(metadata, indent=2))
