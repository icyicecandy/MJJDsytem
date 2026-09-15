# Integrity, conflicting requests, and responsive layout review

Date: 2026-09-10

## Rollback checkpoint

- Original commit: fbcc569 (the working tree was clean before this task).
- Git tag: checkpoint/before-integrity-responsive-20260910.
- Original tracked source ZIP: ../.checkpoints/before-integrity-responsive-20260910.zip.
- Rollback helper: ../.checkpoints/restore-before-integrity-responsive.ps1.
- The helper previews by default. Run it with -Apply only when you intend to restore.
- Before applying a restore, the helper copies every affected current file to a timestamped directory in .checkpoints. It restores only the original files listed in the change manifest and removes only the newly added files listed there.
- No application database migrations or production/customer data edits were made. This checkpoint restores code and appearance; it does not reverse customer transactions that happen after the code changes.

Preview from the project directory:

    powershell -NoProfile -File .checkpoints/restore-before-integrity-responsive.ps1

Restore when requested:

    powershell -NoProfile -File .checkpoints/restore-before-integrity-responsive.ps1 -Apply

## What changed

### Database consistency

WorkflowTransaction now groups the related database writes for 27 application, installation, and ticket actions into transactions. A failure rolls back related state, history entries, audit records, and in-system notifications together.

Existing controllers retain their validation, route names, forms, and response behavior. Their write actions delegate through small transaction wrappers.

Customer email delivery runs after commit. A rolled-back change does not send a success email. Uploaded files created by a failed workflow are cleaned up, while existing files are retained; failed cleanup is logged. Successful uploads continue to use the existing storage configuration.

### Conflicting requests

- Fresh application, installation, and ticket records are loaded under database locks rather than trusting an earlier route-binding snapshot.
- Scheduling, reassignment, and rescheduling lock the selected technician before checking availability, including an initially empty calendar.
- Conflict queries use locking reads so they see the latest committed schedule.
- Installation changes lock the parent application before the installation record.
- Customer application submission locks the customer before checking for an existing application, preventing duplicate submissions from that customer.
- Deadlocks are retried up to three attempts. SQLite reserves its writer before workflow reads because SQLite does not implement SELECT FOR UPDATE.
- A pending customer reschedule request cannot revive a cancelled or completed installation.
- A pending cancellation blocks starting or reassigning an installation until the administrator reviews it.

No schema migration is required for these changes. Application entry points use database locks; raw SQL/manual database edits do not go through these safeguards.

### Responsive layout and controls

A shared public/css/responsive.css stylesheet is loaded by the application layout.

- Long names, emails, references, descriptions, and messages wrap within their containers.
- Grid and flex items can shrink to the available width.
- Table headings, statuses, and action controls wrap instead of overlapping adjacent cells.
- Technician tables retain sufficient column width and scroll within their table container.
- Customer and technician dashboard tables now have keyboard-focusable scrolling containers.
- Filters wrap on narrow screens.
- The application progress tracker stacks vertically on phones.
- Dialog heights use the available viewport height.
- The application map overlay is above the fixed navigation, keeping its close button accessible.
- The new-application wizard sets the map position without animation while the location step is hidden, avoiding invalid-coordinate errors.
- Password reset uses the existing MJJD account-recovery layout while preserving its form action, reset token, and field names.

The existing color scheme and JavaScript action handlers were retained.

## Verification

- Full Pest suite: 149 passed, 823 assertions.
- Added 13 regression tests covering forced rollback, stale models, duplicate scheduling/activation, occupied-slot reassignment, cancellation conflicts, upload cleanup, and email commit behavior.
- Four simultaneous-request scenarios passed using separate PHP processes and an isolated SQLite database: the same application, competing applications for one slot, reassignment, and rescheduling. Exactly one request succeeded in each pair; the conflicting request received validation feedback.
- 135 authenticated page/viewport combinations passed overflow and JavaScript-exception checks in headless Chrome: 27 routes across admin, customer, and technician roles at 360, 390, 768, 1024, and 1440 pixels.
- Synthetic data included deliberately long unbroken names, emails, addresses, ticket subjects, messages, and history notes.
- Short-screen interaction checks cover sidebar toggles, profile/notification dropdowns, installation-history dialogs, the application map dialog, technician creation, and ticket creation.
- 30 additional public-page and new-application-form viewport checks passed, for 165 page/viewport combinations in total.
- All 16 navigation/dialog interaction checks passed, including login and registration, with real mouse clicks and dialog-fit checks on a 390 x 568 viewport.
- Results are saved in .qa/browser-results.json, .qa/guest-results.json, and .qa/interaction-results.json.
- Browser evidence, screenshots, and concurrency results are kept locally in .qa, which is excluded from Git.

Re-run the application tests with the repository's testing configuration:

    php vendor/bin/pest --compact

## Practical limits

The automated checks cover the exercised workflows and viewport sizes; they cannot prove that every possible input, device, or interruption is defect-free. Browser checks used desktop Chrome with resized viewports, not physical phones or Safari/Firefox. Concurrent-request checks used SQLite; repeat them on the intended production database engine during staging verification.

Private document storage, production backups, hosting setup, external email/service delivery, and load testing remain separate deployment work.
