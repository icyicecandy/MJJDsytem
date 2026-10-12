from pathlib import Path
import json

from pypdf import PdfReader


pdf_path = Path(__file__).with_name("MJJD CRM.pdf")
reader = PdfReader(str(pdf_path))
extracted = [(page.extract_text() or "") for page in reader.pages]

page_texts = []
for page_number, text in enumerate(extracted, start=1):
    page_texts.append(f"\n===== PAGE {page_number} =====\n{text}")

Path(__file__).with_name("capstone-by-page.txt").write_text(
    "\n".join(page_texts), encoding="utf-8"
)

terms = [
    "backup", "recovery", "two-factor", "2fa", "totp", "trusted device",
    "google", "email verification", "live chat", "ticket", "installation",
    "application", "analytics", "report", "sales", "inventory", "billing",
    "administrator", "technician", "customer", "system log", "id verification",
    "ocr", "hostinger", "security", "password", "disable", "notification",
]

index = {}
for term in terms:
    pages = []
    for number, text in enumerate(extracted, start=1):
        text = text.lower()
        if term in text:
            pages.append(number)
    index[term] = pages

Path(__file__).with_name("term-page-index.json").write_text(
    json.dumps(index, indent=2), encoding="utf-8"
)

print(json.dumps({"pages": len(reader.pages), "indexed_terms": index}, indent=2))
