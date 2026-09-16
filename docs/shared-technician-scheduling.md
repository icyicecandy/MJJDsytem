# Shared technician scheduling protection

Completed September 16, 2026. Every new ticket assignment requires an appointment slot, as requested.

## Implemented behavior

- Installation scheduling, customer reschedule approval, admin rescheduling, installation reassignment, and ticket assignment/reassignment reject disabled or non-technician accounts on the server. Disabled technicians are excluded from assignment choices.
- Every new or changed appointment must start strictly after the current application time. Both past calendar dates and elapsed times today are rejected. Dates use the configured application timezone (normally Asia/Manila), not the browser's timezone.
- Installations and tickets share one availability service. A technician cannot have overlapping active installation and ticket appointments, including partially overlapping legacy times. Bookings last 60 minutes, matching the existing installation policy. Adjacent appointments such as 09:00-10:00 and 10:00-11:00 remain allowed; travel allowances and variable durations are outside this change.
- All booking writes lock the target technician before reading and checking bookings. Both job types use the same user-row lock, including when the technician initially has no bookings. Conflict queries read locked/current records. SQLite retains its transaction writer reservation.
- Admin installation slot filtering and customer reschedule availability include ticket appointments. Ticket forms show unavailable installation/ticket slots. The server rechecks before saving, so an outdated page cannot overwrite a newly reserved slot.
- Tickets have appointment date/time controls in both the admin list overlay and full ticket page. Failed overlay submissions reopen with the technician/date restored; an unavailable time must be replaced. The customer and assigned technician can see the appointment in ticket details.
- Reassignment and rescheduling validate the destination before saving. A rejected change preserves the original booking. Removing the technician clears the ticket appointment. Pending ticket cancellations keep reserving their slot until approval; resolved, closed, and cancelled tickets release it.
- Reopened tickets return to the unassigned queue with the previous appointment cleared, rather than reclaiming a potentially reused slot. They require a fresh appointment before work restarts. Existing ticket history remains, with appointment change messages and notifications for affected users.
- Historical assigned tickets without appointments remain visible. Their Start Work action is unavailable until an admin schedules them. Existing in-progress tickets can still be resolved.
- Overdue installations that are still Scheduled can be moved to a future appointment through the admin application page. Pending cancellation requests still require review first. In-progress/completed installations cannot use this overdue rescheduling exception.

These protections govern planned appointment intervals. They do not automatically extend reservations if real work runs longer than one hour. Future direct database imports or new assignment endpoints must use the same locking, eligibility, and availability rules.

## Existing records and migration

Applied `2026_09_16_000002_add_ticket_appointments` to the configured MySQL database. It adds nullable appointment date/time columns and a technician/date/status index. Application writes were briefly paused for migration and availability restored afterward.

Compared every original field and timestamp before and after migration: all **5 applications, 3 installations, and 4 tickets** remained unchanged. Existing dates and technician assignments were not guessed, shifted, or deleted.

The read-only audit found:

- **TKT-2026-0011:** already assigned but has no appointment. An admin needs to choose a future slot before the technician starts it.
- **JOB-2026-0007:** overdue. An admin needs to choose its new date/time through Reschedule Installation.
- **No overlaps among existing scheduled records.** Tickets without appointment times cannot be included in a calendar overlap comparison.

## Verification

- Full automated suite: **267 tests passed, 1,568 assertions**.
- Focused scheduling and ticket lifecycle suite: **89 tests passed, 380 assertions**. The new shared-scheduling file adds 42 cases covering all five assignment/rescheduling paths, past times, disabled accounts, all cross-type overlap directions, partial overlaps, adjacent/free slots, malformed inputs, cancellation behavior, reopens, and historical unscheduled tickets.
- Four independent-process concurrency scenarios against isolated SQLite: ticket/installation, installation/ticket, ticket/ticket, and installation/installation. Each ended with exactly one booking and validation errors for the competing request. Installation forms use validation redirects; ticket JSON requests use HTTP 422.
- Independent connections to configured MySQL: ticket and installation workflows on different records shared the target-technician lock. The competing workflow waited **2,209 ms**. Verification transactions rolled back and did not alter business records.
- Chrome end-to-end workflow passed: assignment from the overlay, shared slot filtering, stale slot rejection and preserved form data, overdue installation rescheduling, customer/technician appointment visibility, technician start/resolve, admin close, and released-slot availability.
- **14 browser assertions and 8 responsive layout checks passed**, with the assignment overlay checked at 360, 390, 768, 1024, and 1440 pixels. A mobile screenshot was visually reviewed; the date field was aligned with the existing dialog theme. No detected JavaScript exceptions or horizontal layout overflow.
- `git diff --check` passed. These checks do not establish behavior under every browser, device, or production workload.

Evidence: `.qa/scheduling-full-tests.txt`, `.qa/scheduling-focused-tests.txt`, `.qa/scheduling-race-results.json`, `.qa/scheduling-mysql-results.json`, `.qa/scheduling-browser-results.json`, `.qa/scheduling-existing-audit.json`, `.qa/shared-scheduling.diff`.

## Checkpoints and rollback

- Before: `.checkpoints/before-shared-scheduling-20260916.zip`
- After: `.checkpoints/implemented-shared-scheduling-20260916.zip`
- Exact changed-file list and hashes: `.checkpoints/shared-scheduling-20260916-manifest.json`

To undo this feature, first back up the current database (including appointment values created after deployment), pause writes, and verify that the ticket appointment migration is still the latest migration. Then use:

`php artisan migrate:rollback --path=database/migrations/2026_09_16_000002_add_ticket_appointments.php --step=1 --force`

If later migrations have been applied, review dependencies before choosing a targeted rollback. Restore only the manifest's modified files from the before archive; remove only the manifest's newly added feature files. Preserve reports/checkpoints if desired. Clear compiled views/application caches and resume availability.

Rollback removes the appointment columns, not the ticket/customer records. Restore the code and schema together. The checkpoint includes the earlier contact-duplicate, draft, camera, and profile work, so those prior features remain intact. Do not restore the entire archive over unrelated later edits.
