from pathlib import Path
from PIL import Image, ImageDraw, ImageFont
from pdf2image import convert_from_path


root = Path(__file__).parent
pdf_path = root / "MJJD CRM.pdf"
output_dir = root / "contact-sheets"
output_dir.mkdir(exist_ok=True)
poppler = Path.home() / ".cache/codex-runtimes/codex-primary-runtime/dependencies/native/poppler/Library/bin"

page_count = 155
columns = 4
rows = 4
pages_per_sheet = columns * rows
page_width = 612
page_height = 792
label_height = 28
margin = 12

for first in range(1, page_count + 1, pages_per_sheet):
    last = min(page_count, first + pages_per_sheet - 1)
    images = convert_from_path(
        str(pdf_path),
        dpi=72,
        first_page=first,
        last_page=last,
        poppler_path=str(poppler),
        fmt="png",
        thread_count=2,
    )
    sheet = Image.new(
        "RGB",
        (
            columns * page_width + (columns + 1) * margin,
            rows * (page_height + label_height) + (rows + 1) * margin,
        ),
        "#d5d9e0",
    )
    draw = ImageDraw.Draw(sheet)
    for offset, page in enumerate(images):
        column = offset % columns
        row = offset // columns
        x = margin + column * (page_width + margin)
        y = margin + row * (page_height + label_height + margin)
        sheet.paste(page.convert("RGB"), (x, y + label_height))
        draw.rectangle((x, y, x + page_width, y + label_height), fill="#1f2937")
        draw.text((x + 8, y + 6), f"Page {first + offset}", fill="white")
    output_path = output_dir / f"pages-{first:03d}-{last:03d}.jpg"
    sheet.save(output_path, quality=88, optimize=True)
    print(output_path)
