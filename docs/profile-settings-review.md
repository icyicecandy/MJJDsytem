# Profile settings verification and fixes

Reviewed 2026-09-15. Scope: the Basic Information and Password panels shared by admin, technician and customer accounts.

## Verified behavior

- All three roles can save name, username, email, phone, address, date of birth and gender. Saved values are shown again after a fresh request. Email normalization and duplicate email/username validation work; protected fields such as role and password cannot be changed through a profile-details payload.
- Profile photos accept JPG, PNG and WebP up to 2 MB. Add, replacement, removal and reload worked. The saved image bytes matched the uploaded fixtures. The actual browser loaded the image URL, including after replacement and reload.
- Password changes require the correct current password and matching confirmation and enforce the existing strength rules. After changing the password, each role could log in with the new password and was rejected with the old one. Rejected changes kept the stored password unchanged.
- Existing tests also cover setting an initial password for a Google account and requiring the current password once one has been set.
- Cancel restores the saved profile, including after a failed validation response. Password Clear resets both inputs and strength/match feedback. Photo removal can be cancelled before saving.

## Corrections made

1. Avatar replacement/removal previously deleted the existing file before saving the user. Profile updates now use the existing transaction helper with a fresh locked user row. New uploads are tracked for rollback; the old avatar is deleted only after the update transaction succeeds. A storage write failure produces a photo error and preserves the existing record and file. Failed cleanup of an obsolete avatar is logged rather than turning a successful save into an error.
2. Phone normalization previously discarded arbitrary non-digits, accepting text such as a valid number followed by letters. It now strips formatting characters only, so invalid text and misplaced plus signs reach validation. The browser checks the same 10-to-15-digit format. This is format validation; no new uniqueness policy for profile phone numbers was introduced.
3. Save-button state now compares against persisted values, rather than treating rejected old input as already saved. Cancel restores those persisted values and clears validation state.
4. Photo preview callbacks ignore obsolete file reads after another selection, removal or cancellation. Initials remain available after removing a saved picture. Unsupported/oversized uploads receive client feedback in addition to server validation.
5. Pending username checks cannot overwrite newer results or reappear after Cancel. Server validation remains authoritative.
6. Password Clear resets stale strength/match indicators. Photo size/type and password requirements are shown before submission. Existing date/name/username/address limits are also reflected in the form controls.
7. Success banners show readable profile/password confirmations instead of internal status keys. Profile names and filenames wrap within their panels; updated assets use versioned URLs.

## Test evidence

- Full regression run: **193 tests passed, 1,241 assertions**.
- New ProfileSettingsTest: **27 cases passed, 286 assertions**, including all three roles, all three image formats, add/replace/remove, database failure recovery, storage failure recovery, invalid fields, duplicate email/username, password rejection and new-password login. The leaked-password rule is exercised with deterministic clean and breached service responses.
- After the final success-banner change, existing profile/password tests: **15 passed, 87 assertions**.
- Isolated Chrome browser: **45 interaction checks passed**, covering all three roles. Real form submissions verified persistence, image URLs, old-file cleanup, duplicate email feedback, Cancel after rejection, malformed phone feedback, Clear, wrong current password, mismatched confirmation, successful password change, and old/new login attempts.
- **9 layout checks passed**: each role at widths 360, 768 and 1440. Interactive phone-width tests ran at 390 pixels. No detected page overflow or JavaScript exceptions in recorded layouts; the phone screenshot was visually inspected.
- PHP/JavaScript syntax and git diff formatting checked.

Evidence: .qa/profile-browser-results.json, .qa/profile-mobile.png, .qa/profile-full-suite.txt, .qa/profile-tests.txt and .qa/profile-messages-tests.txt. The browser harness and fresh synthetic account setup are .qa/profile-check.mjs and .qa/profile-setup.php. Tests used isolated SQLite accounts and storage; they did not edit real user passwords, profile details or pictures. Browser asset serving used an isolated storage mapping. The project's actual public/storage junction was checked and points to storage/app/public.

## Certificate issue resolved in a follow-up

The original PHP cURL error 60 is resolved for the password breach checker. On this Windows runtime, its HTTP client now uses the existing Windows trusted CA store with certificate verification enabled. Other HTTP clients and system PHP settings are unchanged.

Live service requests returned HTTP 200. The real verifier rejected a known leaked test password and accepted a generated clean one. Further browser tests against the live service passed for all three roles, including rejection without changing the old password, successful clean-password submission and subsequent login. The updated full regression suite passed 195 tests and 1,252 assertions.

See [password-breach-certificate-fix.md](password-breach-certificate-fix.md) for the implementation, live evidence, scope and dedicated rollback checkpoint. The original certificate limitation is retained in the pre-fix archive for history.

Profile settings contain the two panels reviewed above. Other account routes and the broader application contact-duplicate audit are not newly implemented features of this change.

## Rollback

Original archive: .checkpoints/before-profile-settings-20260915.zip
Manifest: .checkpoints/profile-settings-20260915-manifest.json
Updated archive: .checkpoints/implemented-profile-settings-20260915.zip
Exact patch: .qa/profile-settings.diff

The original archive contains the eight files modified in this task and retains the earlier application camera/single-ID work. Before restoring, save any later edits; restore only the manifest's modified files and remove only its added test/report files. Code rollback does not revert subsequent user profile changes or restore obsolete avatars already removed by successful saves. No production records or files were changed during verification.
