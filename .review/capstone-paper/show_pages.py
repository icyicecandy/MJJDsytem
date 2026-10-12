from pathlib import Path
import sys

from pypdf import PdfReader

sys.stdout.reconfigure(encoding="utf-8", errors="replace")


def parse_pages(spec: str) -> list[int]:
    pages: list[int] = []
    for part in spec.split(","):
        if "-" in part:
            start, end = (int(value) for value in part.split("-", 1))
            pages.extend(range(start, end + 1))
        else:
            pages.append(int(part))
    return pages


reader = PdfReader(str(Path(__file__).with_name("MJJD CRM.pdf")))
for number in parse_pages(sys.argv[1]):
    print(f"\n===== PAGE {number} =====")
    print(reader.pages[number - 1].extract_text() or "")
