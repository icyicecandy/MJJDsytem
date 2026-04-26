# MJJD Telecom CRM

Laravel-based capstone system for MJJD Telecom focused on:
- customer registration and login
- installation application intake with map pinning and valid ID upload
- admin review, manual serviceability assessment, approval or rejection
- technician assignment and installation scheduling
- ticket submission and status tracking
- live chat with AI fallback when staff are unavailable
- public tracking for applications and tickets using reference numbers
- in-system and email notifications

## Core Modules
- Customer portal
- Admin application and ticket management
- Technician installation and ticket workspace
- Public application and ticket tracking
- Notification center and support chat

## Local Setup
1. Install PHP and Composer dependencies.
2. Create `.env` from `.env.example` and configure database and mail settings.
3. Run `php artisan key:generate`.
4. Run `php artisan migrate`.
5. Run `php artisan storage:link` if public uploads are needed.
6. Start the app with `php artisan serve`.

## Notes
- Ticket public tracking uses a dedicated `tracking_number` column.
- Application approval and rejection send customer email notifications.
- Local mail delivery can use Laravel's `log` mailer during development.
