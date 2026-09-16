# Customer-visible reschedule reasons

Completed September 16, 2026.

Customers now receive the new installation appointment time, timezone, and public reason in their **in-app notification**. The explanation and update time also appear on their application page. Admins must enter **Reason shown to customer** before rescheduling or approving a customer reschedule request. Approval forms prefill the customer's own request explanation for review, never internal technician remarks. Rejection reasons retain their existing behavior.

Changing or removing an existing ticket appointment requires **Reason for appointment change**. This appears in the customer's notification, conversation history, and appointment details. Initial assignments do not require a change reason. Reopening clears the old appointment explanation so it is not displayed for a new assignment.

Reasons are limited to 1,000 characters, escaped for display, and wrapped on mobile screens. Private staff notes are not automatically published. A failed/conflicting change preserves the previous appointment/reason and creates no change notification. Shared scheduling, future-date, and disabled-technician checks remain intact.

Notifications are created when an admin confirms the change. This feature does not add SMS/email delivery or publish a technician's internal remarks when they flag a job for rescheduling.

## Migration and verification

Migration `2026_09_16_000003_add_customer_appointment_reasons` adds nullable reason and update-time fields to installations and tickets. It was applied to configured MySQL. All original fields/timestamps were compared: **5 applications, 3 installations, and 4 tickets remained unchanged**. Historical reasons were not invented.

- Full suite: **275 tests passed, 1,617 assertions**.
- Focused reason/scheduling/workflow suite: **82 tests passed, 374 assertions**.
- Chrome: **18 assertions and 12 layout checks passed**, including required reasons, customer notification/detail pages, long text at mobile widths, stale-slot protection, and existing start/resolve/close controls. No detected JavaScript exceptions or horizontal overflow.
- `git diff --check` passed.

Evidence: `.qa/reschedule-reason-full-tests.txt`, `.qa/reschedule-reason-tests.txt`, `.qa/reschedule-reason-browser-results.json`, `.qa/reschedule-reasons.diff`.

## Rollback

Before: `.checkpoints/before-reschedule-reasons-20260916.zip`.
After: `.checkpoints/implemented-reschedule-reasons-20260916.zip`.
Scope and hashes: `.checkpoints/reschedule-reasons-20260916-manifest.json`.

Back up current data and pause writes before rollback. If this is still the latest migration, run:

`php artisan migrate:rollback --path=database/migrations/2026_09_16_000003_add_customer_appointment_reasons.php --step=1 --force`

If later migrations exist, review their dependencies first. Restore only the manifest's modified files from the before archive, remove only its added feature files, clear compiled views/caches, and resume availability. Restore code and schema together.

Rollback removes the new reason/update-time columns, not appointments, installations, or tickets. Already-sent notifications and history remain. The checkpoint preserves earlier scheduling, contact-duplicate, draft, camera, and profile features.
