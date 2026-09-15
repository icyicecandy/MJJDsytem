# Table readability update

Date: 2026-09-14

## Changes

The installation screenshot showed a narrow status column breaking Reschedule required into partial words. Fixed percentage columns and the earlier shared overflow-wrap rule caused this.

- Shared record tables now use automatic column sizing with minimum cell widths.
- Status badges wrap between whole words with compact rounded corners; they no longer break into letters.
- Application, job, and ticket references stay together. Action button labels stay together too.
- Long names, emails, addresses, subjects, and descriptions still wrap inside their cells; rows grow to show the full text.
- Admin and technician installation columns reserve more space for contact details, names, and schedules. Addresses wrap instead of being clipped.
- Wide tables scroll inside their own cards on smaller screens. All affected record-table containers can receive keyboard focus for scrolling.
- The admin installation pagination card appears only when there are pages to navigate.
- The responsive stylesheet URL changes when the file changes, so refreshing the page picks up the updated styles.

These changes cover the shared tables used by admin, technician, and customer pages. Controller logic, form actions, route targets, event handlers, and database records were not edited during this update.

## Verification

- Application suite: 149 tests passed, 823 assertions.
- Browser checks use an isolated SQLite database with synthetic records and headless Chrome, at widths 360, 390, 768, 1024, 1440, and 1920 pixels.
- The browser harness checks page and table-cell overflow, JavaScript exceptions, broken words in status labels/references/buttons, and control containment. It also substitutes long status labels in the DOM to check for overflow, without changing database records.
- Final browser pass: 162 page/viewport checks and 14 navigation/dialog interactions passed, with zero reported layout or JavaScript failures.
- Final browser results: ../.qa/table-readability-results.json.
- Screenshot with a representative installation row: ../.qa/table-readability-installations-1920.png.
- Exact diff against the pre-update files: ../.qa/table-readability.diff.
- git diff --check passed.

These checks cover the exercised pages and controls in Chrome; physical-device and other-browser behavior has not been verified for this update.

## Restore point

The existing September 10 integrity improvements were preserved. A separate archive captures the files as they were immediately before this UI update:

- ../.checkpoints/before-table-readability-20260914.zip
- ../.checkpoints/table-readability-20260914-manifest.json lists the 15 changed application files.
- ../.checkpoints/implemented-table-readability-20260914.zip contains their updated versions.

The original archive was verified for ZIP integrity and contains the pre-update version of every changed application file. To undo only this update, first back up the current versions of the manifest files, then restore those specific files from the original archive. Do not use the older September 10 restore helper to undo only this UI update.
