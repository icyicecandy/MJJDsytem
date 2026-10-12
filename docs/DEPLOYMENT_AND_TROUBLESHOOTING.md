# Deployment and Troubleshooting Guide

## Before deployment

1. Back up the live database and current application files using Hostinger's backup tools.
2. Record the current application version or archive name.
3. Confirm the local test suite passes.
4. Upload the changed application files without uploading the local `.env`, test database, cache files, or development dependencies.

## Deploy the operational-hardening update

1. Upload the changed files listed in the release handoff.
2. Keep the live `.env` and its mail, database, Google authentication, and application URL values.
3. Run:

   ```text
   php artisan migrate --force
   php artisan optimize:clear
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

4. Confirm the new `email_delivery_logs` table exists.
5. Test one admin login, one technician login, one customer email verification, an application review, a ticket update, and a report print.

## Rollback

The local pre-change checkpoint is the Git tag:

```text
checkpoint-before-operational-hardening-20261012
```

Before restoring, preserve the current database and files for investigation. Restore the prior application files and run the migration rollback only if the email-delivery table itself must be removed:

```text
php artisan migrate:rollback --step=1 --force
php artisan optimize:clear
```

Do not roll back unrelated migrations. On Hostinger, the safer recovery path is normally restoring the pre-deployment file archive and database backup together.

## Common errors

- **Database connection refused:** Verify MySQL is running and that `DB_HOST`, `DB_PORT`, database name, username, and password match Hostinger.
- **403 on an emailed verification link:** Confirm `APP_URL` is the public HTTPS domain, clear cached configuration, and request a new link. Signed links generated for another host or an expired link are invalid.
- **419 session expired:** Refresh, sign in again, and retry. Check HTTPS and session cookie configuration if it repeats.
- **500 after deployment:** Review `storage/logs/laravel.log`, confirm writable `storage` and `bootstrap/cache`, run migrations, and clear caches.
- **Email marked sent but missing:** Check Spam, sender-domain SPF/DKIM/DMARC, and the System Logs email-delivery view.
- **Changes do not appear:** Clear Laravel caches and the browser cache, then confirm the uploaded file path matches the deployed document root.
- **Modal or list does not scroll:** Verify the latest CSS and JavaScript files were uploaded, then test at 375 px and desktop width.

## Post-deployment verification

- Buttons are reachable at 375 px, 768 px, and desktop widths.
- Long tables scroll or become cards.
- Modals scroll without hiding their action buttons.
- Forms retain validation input and show a single saving state.
- Approval, rejection, disable, resolution, completion, and activation rules are enforced by the server.
- Status text remains understandable without color alone.
