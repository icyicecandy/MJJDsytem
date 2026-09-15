# Customer application camera capture

Implemented 2026-09-15.

## Customer flow

In Customer > Apply > Upload Valid ID, Take photo now appears beside Choose File (below it on narrow screens). It opens a camera preview with Capture photo, Retake, Use this photo and Close controls. Use this photo attaches a JPEG to the existing holding-ID input, shows a preview and updates the review filename. The application uploads that photo only when submitted.

Choose File remains available. Closing or cancelling the camera preserves the previously selected file. Camera tracks stop after capture, cancellation, navigation away from the upload step, page hiding, or a late permission response after the dialog has closed. The captured image is unmirrored to keep ID text readable.

The existing server image validation, 5 MB limit and private storage/authorized viewer apply to camera captures. This change adds no database migration, backend route or external service. It requires browser camera permission and HTTPS in deployment (localhost works for development). Unsupported browsers, denied permission and missing cameras receive an upload fallback message. No third-party API key is required.

## Files

- resources/views/customer/apply.blade.php: upload action, selected preview, accessible native camera dialog, versioned CSS/JS assets.
- public/js/application-camera.js: permission handling, preview/capture/retake, File attachment, validation and camera cleanup.
- public/css/application-camera.css: styles scoped to these controls, wrapping actions and a viewport-constrained dialog.

## Verification

- Full PHP regression suite: 166 tests passed, 955 assertions.
- Camera browser run: 17 layout checks passed at widths 360, 390, 768, 1024 and 1440; no detected overflow or JavaScript exceptions in the checked pages.
- Captured a simulated Chrome camera frame, retook it, attached it, advanced through review and submitted the application. Stored JPEG existed privately and owner retrieval returned image/jpeg.
- Verified cancellation preserves a selected photo and releases the camera; denied permission, missing camera, and a late stream after closing are handled. Choose File remained usable after camera errors.
- Admin thumbnail, original image and gallery controls worked at phone and desktop widths; technician photo retrieval returned 403.
- Separate normal-file browser run: all 12 layout checks passed; a PNG submitted, was stored privately and loaded in the authorized viewer.
- Mobile camera review screenshot visually inspected. JavaScript syntax and git diff --check passed.

Browser tests used headless Chrome with a simulated camera and isolated SQLite accounts/storage. Physical webcams, mobile Safari and production HTTPS permissions still need device testing. This evidence does not guarantee absence of every bug. Production customer records and uploaded documents were not changed by these checks.

Local browser evidence: .qa/application-camera-results.json, .qa/application-camera-390.png and .qa/single-id-browser-results.json. Test scripts: .qa/application-camera-check.mjs and .qa/single-id-check.mjs.

## Rollback checkpoint

- Original: .checkpoints/before-application-camera-20260915.zip
- Manifest: .checkpoints/application-camera-20260915-manifest.json
- Updated copy: .checkpoints/implemented-application-camera-20260915.zip
- Camera-only patch: .qa/application-camera.diff

The original archive was verified and contains the application form immediately before camera controls were added, retaining the earlier single-ID upload work. To undo only this feature, first save any subsequent edits, restore resources/views/customer/apply.blade.php from that archive and remove the added camera JS/CSS and this report listed in the manifest. Rollback changes code only; submitted photos and application records are retained. Avoid restoring the older single-ID checkpoint when only camera removal is intended.

This report supersedes the future-camera note in single-id-upload-review.md. The broader email/phone duplicate-validation audit remains separate from this camera implementation.
