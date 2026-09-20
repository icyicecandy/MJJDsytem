# Mobile Filter Drawers and Responsive Table Cards

Date: 2026-09-20

## Scope

This update changes authenticated list pages only at small viewport widths. Existing controllers, routes, query parameter names, authorization, database behavior, ticket actions, and desktop layouts remain unchanged.

## Mobile filter drawers

At widths below 768px, the existing GET filter form is moved into an accessible bottom sheet and replaced in the page flow by one **Filters** button. At 768px and wider, the original form remains in its original DOM position and the generated mobile controls stay hidden.

Covered pages:

- Admin applications
- Admin customers
- Admin technicians
- Admin tickets
- Technician tickets
- Customer tickets

Behavior included:

- Existing form action, method, inputs, values, and query names are reused.
- Active query fields are shown as a count on the Filters button.
- Filter and Reset perform their existing navigation.
- The sheet closes from its close button, backdrop, or Escape.
- Escape restores focus to the Filters button.
- Keyboard focus is contained inside the open dialog.
- Body scrolling is locked only while the sheet is open.
- Controls meet a minimum 44px touch target and the sheet supports short viewports and safe-area padding.

## Responsive table cards

At widths below 640px, selected operational tables become label/value cards. At 640px and wider, they keep the existing table layout. JavaScript derives each mobile label from the existing table header, preventing duplicated label text from drifting out of sync.

Covered tables:

- Admin applications, customers, technicians, installations, and tickets
- Technician dashboard tickets, installations, and tickets list
- Customer dashboard tickets and tickets list

The cards retain each row's reference or tracking number, customer where applicable, status, schedule where applicable, and action. All additional columns remain readable. Ticket appointment schedules are rendered in a mobile-only field and stay hidden on desktop.

## Verification

- `node --check public/js/dashboard.js`: passed.
- `php artisan view:cache`: passed.
- Focused Laravel suite: 40 tests passed, 239 assertions.
- Full Laravel suite: 291 tests passed, 1,686 assertions.
- Browser audit: 65 checks passed, 0 failures, 0 JavaScript errors.
- Browser sizes included 1440px, 768px, 767px, 640px, 639px, 390px, and a 390x500 short viewport.
- The audit covered all six drawers, real Filter and Reset navigation, active query preservation, Escape and backdrop closing, focus restoration, breakpoint boundaries, card field visibility, page overflow, and the existing admin ticket action.
- `git diff --check`: passed.

Browser audit details are stored in `.qa/mobile-filter-cards-results.json`. Visual captures are stored in `.qa/mobile-filter-drawer-390.png` and `.qa/mobile-installation-cards-390.png`.

## Rollback

The pre-change checkpoint is `.checkpoints/before-mobile-filter-cards-20260920-205413.zip`. It contains the source files and the pre-change Git status needed to restore this task without removing earlier work.