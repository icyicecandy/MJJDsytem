# Ticket table spacing

Date: 2026-09-14

The technician ticket list reused positional widths from the eight-column admin table despite having seven columns. This left an oversized Action column while ticket subjects wrapped unnecessarily.

The three ticket lists now use a shared ticket-list-table style and role-specific colgroups. Reference, urgency, status, submitted date and action columns have compact reserved widths; person columns take a proportion of the table; Subject takes the remaining space. View buttons align centrally in a six-rem column at the right edge. Cells are vertically centered with consistent padding. Small screens retain scrolling within the table card, and long content continues to wrap.

Changed application files: public/css/responsive.css and the admin, technician and customer tickets/index.blade.php templates. Existing routes, data attributes, button handlers and application logic were preserved.

Verification: 84 layout/status checks passed across three roles and seven widths (360, 390, 768, 1024, 1440, 1536, 1920). This includes ordinary synthetic records plus cancellation-requested, reopen-requested and in-progress status labels. Six real View interactions passed: admin and technician dialogs opened and closed, and customer View links opened ticket details, at phone and desktop widths. The action column measured no more than 100 pixels in each tested layout. git diff --check passed. The technician screenshot was visually inspected. Tests used isolated SQLite data and desktop Chrome; physical devices and other browsers were not checked.

Results: ../.qa/ticket-spacing-results.json

Rollback archive: ../.checkpoints/before-ticket-spacing-20260914.zip
File manifest: ../.checkpoints/ticket-spacing-20260914-manifest.json

The archive was verified and contains all four files as they were before this spacing adjustment, including the earlier readability improvements. To undo only this adjustment, back up the current versions, then restore the four manifest files from this archive.
