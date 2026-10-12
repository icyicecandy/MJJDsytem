from __future__ import annotations

import json
import re
from pathlib import Path


source = Path(__file__).with_name(
    "CUSTOMER RELATIONSHIP MANAGEMENT SYSTEM FOR MJJD TELECOM.tables.json"
)
tables = json.loads(source.read_text(encoding="utf-8"))[:8]
issues: list[str] = []
summary: list[tuple[str, float]] = []

for table in tables:
    name = table["data"][0][0]
    means: list[float] = []
    for row_number, row in enumerate(table["data"][1:6], 1):
        counts = []
        for cell in row[1:6]:
            match = re.match(r"\s*(\d+)", cell)
            counts.append(int(match.group(1)) if match else 0)
        total = sum(counts)
        computed = sum(count * rating for count, rating in zip(counts, [5, 4, 3, 2, 1])) / total
        reported = float(row[6])
        means.append(reported)
        if total != 6:
            issues.append(f"{name} row {row_number}: frequency total {total}, expected 6")
        if round(computed, 1) != reported:
            issues.append(
                f"{name} row {row_number}: computed {computed:.3f}, reported {reported:.1f}"
            )
    reported_average = float(table["data"][6][1])
    computed_average = sum(means) / len(means)
    if round(computed_average, 1) != reported_average:
        issues.append(
            f"{name}: computed average {computed_average:.3f}, reported {reported_average:.1f}"
        )
    summary.append((name, reported_average))

overall = sum(value for _, value in summary) / len(summary)
print("CATEGORY_AVERAGES")
for name, value in summary:
    print(f"{name}: {value:.1f}")
print(f"OVERALL_FROM_REPORTED_CATEGORY_MEANS: {overall:.3f} -> {overall:.1f}")
print("ISSUES")
print("None" if not issues else "\n".join(issues))
