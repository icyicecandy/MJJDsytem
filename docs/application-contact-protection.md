# Application contact duplicate protection

Implemented and checked on September 16, 2026.

## Behavior

- Application emails are trimmed and compared without case differences. Internal whitespace is rejected, rather than silently changing an address. Gmail dots and plus aliases remain distinct.
- Philippine mobile formats `09171234567`, `639171234567`, `+639171234567`, and formatted variants such as `+63 (917) 123-4567` share an identity. New or edited Philippine numbers are stored in local `09...` format. The existing 10-to-15-digit international-number allowance remains; an optional plus prefix does not create a different identity.
- Live checks and final submission use the same normalization rules. Invalid arrays, letters in phone numbers, and invalid email formats produce validation errors.
- The database stores indexed canonical contact keys. Final customer submissions acquire one shared database row lock before reading application/contact records, so submissions from different customers cannot both pass the duplicate check for the same contact. SQLite uses the existing transaction writer reservation; MySQL uses `FOR UPDATE`.
- The shared lock lasts only for the submission transaction. Normal browsing and draft saves do not take it. This serializes final submissions and is appropriate for the current CRM workload; it has not been load-tested at high submission volumes.
- Failed, timed-out, malformed, or outdated live-check responses cannot approve the wizard's Next step. Inputs stay available for retry or draft saving. Validation messages are announced politely to assistive technology.
- Drafts do not reserve contacts. Rejected/cancelled applicants can reuse their own application record and contacts, provided another application does not already use them. Other applications continue to reserve their contacts regardless of status, matching the previous policy.
- ID uploads, camera controls, private-image authorization, installation workflows, and user-account/profile contact policies retain their existing behavior.

## Existing data and deployment

Migration `2026_09_16_000001_add_application_contact_keys` was applied to the configured MySQL database. Writes were briefly paused during migration and the previous availability state restored. All five existing application records were compared before and after: every original field and timestamp remained unchanged. Only derived columns and the guard table were added.

Existing applications `APP-2026-6470` and `APP-2026-5668` share a phone identity. Both were preserved. An authorized administrator must verify ownership before correcting either customer's contact details; no record was deleted, merged, or assigned priority.

Run `php artisan applications:audit-contacts` to report conflicts by field and application reference, without printing contact values or modifying records. Exit code 1 means conflicts were found; 0 means none were found.

Because historical duplicates are preserved, the application keys have ordinary indexes, not unique constraints. Concurrency protection is enforced by the shared lock plus server validation in the customer submission workflow. Any future import, API, or admin contact editor must use the same lock/check policy and maintain canonical keys. Direct SQL inserts or bulk contact updates are not covered by Eloquent saving hooks or the submission lock.

For another environment, pause application writes while deploying the code and applying the migration, then resume. Back up that environment's database first.

## Verification

- Complete automated suite: **225 tests passed, 1,457 assertions**.
- New focused coverage: **18 tests, 110 assertions**, including alternate contact formats, malformed inputs, owner reapplication, drafts, private ID access, migration rollback/reapply with historical duplicates, and lock ordering.
- Chrome browser: **18 interaction checks and 10 layout checks passed**, using widths 360, 390, 768, 1024, and 1440. Covered duplicate messages, offline retry, stale responses, save/resume/discard drafts, simulated camera capture, final submission, and admin access to the saved private photo. No detected JavaScript exceptions or layout overflows.
- Concurrent requests from two synthetic customers against isolated SQLite: first submitted successfully (302); second received both duplicate field errors (422).
- Two independent connections to configured MySQL: the competing transaction waited 2,211 ms for the shared guard. Both verification transactions rolled back; no business records were written.
- `git diff --check` passed.

Local evidence: `.qa/contact-tests-final.txt`, `.qa/contact-browser-results.json`, `.qa/contact-race-results.json`, `.qa/contact-mysql-results.json`, and `.qa/contact-protection.diff`. Browser checks used an isolated database and synthetic accounts; they do not establish compatibility with every browser/device or production load level.

## Checkpoint and rollback

Before-change archive: `.checkpoints/before-contact-protection-20260916.zip`.
After-change archive: `.checkpoints/implemented-contact-protection-20260916.zip`.
Exact scope and source hashes: `.checkpoints/contact-protection-20260916-manifest.json`.

To undo only this feature:

1. Pause application writes. Verify that the contact migration is still the latest migration before using `php artisan migrate:rollback --path=database/migrations/2026_09_16_000001_add_application_contact_keys.php --step=1 --force`. If later migrations exist, review their dependencies and select a targeted rollback instead.
2. Restore only the four `modified` files listed in the manifest from the before-change archive. The archive also contains baseline test files and routes for reference; do not restore those unnecessarily.
3. Remove only this feature's `added` source/test/migration files listed in the manifest; retain the report and checkpoints if desired.
4. Clear compiled application/view caches and resume availability. Recheck application submission.

The migration rollback removes derived keys and the guard table, not application/customer records. It preserves applications submitted after this feature was enabled. Earlier draft, camera, profile, and certificate fixes are included in the baseline and must remain intact.
