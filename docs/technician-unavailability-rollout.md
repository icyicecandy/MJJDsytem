# Technician unavailability workflow rollout

## Behavior

- Technicians submit a dated unavailability request with a required explanation and optional private JPG, PNG, WebP, or PDF proof.
- Pending, clarification-requested, and approved periods prevent new installation assignments and live ticket routing during the affected time.
- Existing installations are preserved and listed as coverage items. The administrator can keep the appointment by reassigning it or move it to the existing reschedule queue.
- Administrators receive an in-system notification and email when a request is submitted or resubmitted.
- Technicians receive an in-system notification and email when an administrator approves, rejects, or requests clarification.
- Customers never receive the technician's private reason or proof. They receive a general operational notice only if their installation is moved to the reschedule queue.
- All request decisions, delivery attempts, and coverage changes are recorded in System Logs or Email Delivery.

## Local checkpoint

The pre-change source checkpoint is:

`C:\Projects\mjjd-telecom-system\.checkpoints\before-20261012-technician-unavailability-workflow.zip`

The prepared local rollback script is:

`C:\Projects\mjjd-telecom-system\.checkpoints\rollback-technician-unavailability.ps1`

Run it only if the entire feature must be removed. It places the application in maintenance mode, rolls back the feature migration, restores the checkpoint, removes files introduced by the feature, clears Laravel caches, and brings the application back online.

## Hostinger deployment

1. Export the live database and download a copy of the current deployed files before uploading this release.
2. Upload every changed and newly added file listed below while preserving its directory.
3. From Hostinger Terminal, run `php artisan migrate --force`.
4. Run `php artisan optimize:clear`.
5. Confirm `php artisan migrate:status` shows `2026_10_12_000400_create_technician_unavailability_requests_table` as **Ran**.
6. Test one technician submission and one administrator decision. Confirm the in-system notifications, email delivery records, and System Logs entries.

No `.env` value is added by this feature. It uses the existing mail configuration and private local storage disk.

## Files to upload

- `app/Http/Controllers/Admin/TechnicianController.php`
- `app/Http/Controllers/Admin/TechnicianUnavailabilityController.php`
- `app/Http/Controllers/Admin/SystemLogController.php`
- `app/Http/Controllers/Technician/UnavailabilityController.php`
- `app/Http/Controllers/TechnicianDashboardController.php`
- `app/Mail/StaffWorkflowUpdateMail.php`
- `app/Models/TechnicianUnavailabilityRequest.php`
- `app/Models/User.php`
- `app/Services/TechnicianSchedule.php`
- `app/Support/CustomerEmailSender.php`
- `app/Support/StaffWorkflowEmailSender.php`
- `database/migrations/2026_10_12_000400_create_technician_unavailability_requests_table.php`
- `resources/views/admin/system-logs/index.blade.php`
- `resources/views/admin/technicians/index.blade.php`
- `resources/views/admin/unavailability/index.blade.php`
- `resources/views/admin/unavailability/show.blade.php`
- `resources/views/components/sidebar.blade.php`
- `resources/views/emails/staff-workflow-update.blade.php`
- `resources/views/technician/dashboard.blade.php`
- `resources/views/technician/unavailability/index.blade.php`
- `routes/web.php`

The test file and this document do not need to be uploaded for the application to run, but keeping them with the source is recommended:

- `tests/Feature/TechnicianUnavailabilityWorkflowTest.php`
- `docs/technician-unavailability-rollout.md`

## Hostinger rollback

1. Put the live application in maintenance mode with `php artisan down`.
2. Run `php artisan migrate:rollback --path=database/migrations/2026_10_12_000400_create_technician_unavailability_requests_table.php --force`.
3. Restore the pre-deployment file backup. Ensure the new feature-only files listed in the local rollback script are removed if the file restore does not delete added files.
4. Run `php artisan optimize:clear`.
5. Bring the application back with `php artisan up`.

Rolling back the migration removes unavailability request records and their private proof references. The physical private proof files should also be removed during a permanent rollback after the database and file backups are secured.
