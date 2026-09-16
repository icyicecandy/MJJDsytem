# Save-and-resume application drafts

Implemented 2026-09-15; report completed 2026-09-16.

## Customer experience

Customer > Apply now includes Save draft and Discard draft above the wizard. Save draft accepts incomplete details without submitting an application. Returning to Apply after a fresh login restores the saved fields, wizard step and privately stored ID photo. The page reports the save time and makes clear that submission happens only through Submit Application.

Customers can upload a file or use Take photo, save it with the draft, and later submit without selecting it again. The retained-photo message explains why the file picker can say No file chosen while the saved photo remains available. Choosing another image replaces it after a successful save. Discard draft asks for confirmation and removes the saved draft and its photo.

Saving is explicit, not automatic. Select Save draft before leaving. Unsaved changes or an in-progress save trigger the browser's navigation warning where supported. Failed network requests retain the current fields and report that saving could not be confirmed.

## Data protection and integrity

- One draft per customer is enforced by a unique user_id constraint in a separate application_drafts table. Drafts do not create submitted application records, consume reference numbers, change application dashboard counts or notify administrators.
- Draft text uses Laravel's encrypted:array cast. Photos use private local storage and an authenticated, customer-only photo route. Other customers and staff cannot retrieve someone else's draft photo. No application draft data is written to browser localStorage.
- Only allowlisted fields are saved. Partial text is allowed; photos still require JPG, PNG or WebP up to 5 MB. Final submission retains the existing required fields, valid service address/pin, plan and duplicate-contact checks.
- User-row locks and a changing UUID token prevent stale tabs from overwriting, discarding or submitting a newer draft. Conflicts instruct the customer to reload and review the saved version.
- Failed database writes preserve the previous draft and photo. Newly uploaded files are tracked for rollback; replaced/discarded photos are removed after successful database changes.
- Successful submission transfers the retained private photo to the application and removes the draft row in the transaction. Failed final submission preserves the draft. Cleanup state is reset on transaction retries.

## Expiry and operation

Drafts expire 30 days after the last successful save. Expired drafts are excluded from resume/photo access and cannot be submitted using an old token.

The applications:prune-drafts command removes expired rows and their private photos. It is registered daily through Laravel's scheduler with overlapping runs prevented; schedule:list confirmed registration. Automatic physical cleanup requires the hosting environment to run Laravel's scheduler. This task did not install an operating-system scheduled task. Expired draft access is denied even before cleanup runs.

Preserve APP_KEY in secured deployment/backup configuration because encrypted drafts depend on it. Backups need both the database and private storage. Hosting upload limits must support the application's image-size policy.

## Verification

- Focused draft and original application/photo tests: 30 passed, 257 assertions. Twelve new draft cases cover incomplete persistence, encryption, access restrictions, photo reuse, stale requests, final required/address/duplicate validation, replacement/discard cleanup, expiry, role restrictions, invalid/oversized/missing images and rollback.
- Full regression suite: **207 tests passed, 1,347 assertions**, including existing profile/password, application, scheduling and ticket behavior.
- After the final transaction-retry cleanup guard: **25 draft/workflow tests passed, 149 assertions**.
- Chrome: **14 interaction checks passed**. Actual requests saved an incomplete draft, resumed after fresh login, saved a simulated-camera photo, loaded it after reopening Apply, replaced it with a file upload, handled stale-tab and network failures, submitted using the retained photo, displayed the submitted photo for admin and discarded a separate draft.
- Five resumed-draft layout checks passed at widths 360, 390, 768, 1024 and 1440, with no recorded overflow or JavaScript exceptions. The mobile layout was visually inspected.
- PHP/JavaScript syntax, git diff --check and the additive migration preview passed.

Evidence: .qa/draft-browser-results.json, .qa/draft-resume-390.png, .qa/application-draft-tests.txt, .qa/application-draft-full-suite.txt and .qa/draft-final-integrity-tests.txt. Browser harness: .qa/application-draft-check.mjs.

Tests used isolated SQLite accounts/storage and Chrome's simulated camera. Physical-device testing and production load testing were not part of this change. Real customer details, passwords and submitted documents were not modified by testing.

## Migration and rollback

The additive migration database/migrations/2026_09_15_000001_create_application_drafts_table.php was previewed and applied to the configured MySQL database. It created the new draft table and constraints/index; existing application/customer records were not rewritten. The migration was applied separately to the isolated browser-test database.

Original archive: .checkpoints/before-application-drafts-20260915.zip
Manifest: .checkpoints/application-drafts-20260915-manifest.json
Updated archive: .checkpoints/implemented-application-drafts-20260915.zip
Exact patch: .qa/application-drafts.diff

The original archive contains the five previously existing files changed in this task. The manifest lists seven added files, including the migration, model/controller, assets, tests and this report. Earlier profile, certificate, camera and single-ID improvements are retained in the checkpoint.

To revert code, first preserve any later edits, restore only the modified files from this archive and remove the added feature files listed in the manifest. Leave the new table in place until saved draft data is reviewed. Do not blindly roll back unrelated migrations or delete the shared application-ids/private directory: it also contains submitted application IDs. Removing draft data/schema is a separate data-loss decision. Code rollback does not undo applications submitted since this change.
