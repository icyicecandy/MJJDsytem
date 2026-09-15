# Single ID-photo application requirement

Date: 2026-09-15

## Changes

New applications require only one clear photo of the customer holding their valid ID beside their face. The photo of an ID on paper with three signatures was removed from the form, review step, browser validation, backend validation/storage, and support chatbot instructions. The retained photo still accepts JPEG, PNG, and WebP, up to 5 MB. Existing consent, address, plan, duplicate-application, cancellation/reapplication, scheduling, and notification behavior is retained. The legacy database column is kept for existing records; no schema migration is required.

New ID photos are stored on the local private disk under application-ids/private. An authenticated route serves them inline with no-store cache headers. Active administrators and the owning customer can retrieve them; other customers, technicians, and guests cannot. The admin gallery uses this route for thumbnails, its full viewer, and Open original. ApplicationIdUpload registers each newly stored file with the existing workflow rollback mechanism, so a failed submission removes that new file while preserving existing files.

Existing uploaded ID photos remain readable through the authorized viewer. Their existing public-disk files have not been moved or deleted. Their old public URLs therefore remain accessible if known; migrating legacy public files is separate from protecting new uploads. A code-only rollback must account for private photos submitted after this change: retain a compatible protected viewer or plan a safe storage migration before restoring the older public-only viewer. Do not copy private ID photos into public storage as a rollback shortcut.

The shared gallery still supports its original public-photo inputs. It additionally accepts server-provided photo URLs. Its overlay now appears above navigation and scrolls within the available screen height, keeping the close control usable. Browser testing also exposed a cached zero-size Leaflet map error during wizard navigation. The wizard now stops map animations before hiding the location step and refreshes the map size before starting a visible animation.

## Verification

- Full Pest suite: 166 passed, 955 assertions.
- Ten new cases test single-photo JPEG/PNG/WebP submissions, exact returned image bytes, admin/owner access, missing/invalid/oversized files, guest/other-customer/technician denial, legacy viewing, missing files/path traversal, and transaction rollback cleanup.
- Existing application and support-chat tests were updated for the single-photo requirement.
- Real image fixtures avoid relying on JPEG/WebP encoding support in the local PHP GD build.
- Final isolated Chrome run: 12 layout checks passed across upload/review steps at widths 360, 390, 768, 1024 and 1440, plus admin gallery pages at 390 and 1440.
- The actual browser wizard rejected an absent photo, accepted one upload, displayed its filename in review, and submitted successfully. The saved file existed privately and was absent from the public disk.
- Admin thumbnail and original image loaded; photo dialogs opened, fit a 568-pixel-high viewport, and closed at phone and desktop widths. Owner retrieval returned image/png; technician retrieval returned 403.
- No final checked layout overflow or JavaScript exceptions. The phone-sized review was visually inspected.
- PHP syntax checks and git diff --check passed.
- Browser evidence: ../.qa/single-id-browser-results.json. Exact update diff: ../.qa/single-id-upload.diff.

Tests used isolated SQLite records and storage, plus Chrome. They do not establish defect-free behavior on every device or browser. Actual customer records and existing uploads were not modified by testing.

## Checkpoint

Original archive: ../.checkpoints/before-single-id-upload-20260915.zip
Manifest: ../.checkpoints/single-id-upload-20260915-manifest.json
Updated archive: ../.checkpoints/implemented-single-id-upload-20260915.zip

The verified original archive contains the nine previously existing files changed by this update. The manifest separately lists the new helper, controller, test, fixture images, and this report. Back up current versions before restoring. This checkpoint preserves earlier integrity, responsive-table, ticket-spacing and Operations Overview improvements. It restores code, not subsequent customer submissions or their uploaded files.

## Future Take photo option

Not implemented as part of this change. A mobile capture input can ask supported devices to open a camera. An in-page preview and capture control can use the browser getUserMedia API; no third-party paid service or API key is required. The in-page camera requires user permission and a secure context such as HTTPS (localhost is allowed for development). Keep the existing file-upload option for devices/browsers without camera support or when permission is declined.

References: https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/capture and https://developer.mozilla.org/en-US/docs/Web/API/MediaDevices/getUserMedia
