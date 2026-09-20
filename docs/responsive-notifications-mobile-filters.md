# Responsive Notifications and Mobile Chat Filters

Date: 2026-09-20

## Changes

### Notification bell

- At 1024px and below, the bell opens the existing notification dialog instead of the wide anchored dropdown.
- At 768px and below, the dialog becomes a bottom sheet contained within the viewport.
- The notification list scrolls inside the dialog on short screens.
- Long titles and messages wrap without clipping or page-wide overflow.
- The dialog supports close button, backdrop, Escape, focus containment, focus return to the bell, and background scroll locking.
- At widths above 1024px, the bell keeps the existing desktop dropdown.

### Notification filters

- Mobile and tablet notification dialogs use a compact search input and All/Unread dropdown.
- The full notifications page uses the same mobile search/dropdown layout at 1024px and below.
- The desktop All/Unread chip layout remains unchanged.
- Server-side notification search checks notification titles and messages, preserves pagination query parameters, and caps search input at 100 characters.
- Empty search and unread states now describe the current filter accurately.

### Chat queue filters

- At 768px and below, the existing conversation search and status dropdown use a compact two-row layout.
- Changing the status dropdown applies the existing GET filter automatically on mobile.
- Search, Filter, Reset, routes, and query parameter names are unchanged.
- Above 768px, the existing desktop grid and interaction remain unchanged.

## Verification

- JavaScript syntax check passed.
- Blade compilation passed and compiled views were cleared afterward.
- Focused notification/chat suite: 40 tests passed, 203 assertions.
- Full Laravel suite: 291 tests passed, 1,686 assertions.
- Browser audit: 20 checks passed, 0 failures, 0 JavaScript errors.
- Browser widths included 390px, 768px, 1024px, and 1366px, plus a 390x500 short viewport.
- Browser checks covered bell behavior, fit and overflow, search, All/Unread filtering, no-match state, Escape and focus restoration, internal scrolling, full-page query preservation, mobile chat layout, dropdown auto-apply, and unchanged desktop dropdown/filter layouts.
- `git diff --check` passed.

Browser evidence is stored in `.qa/notification-responsive-results.json`, `.qa/notifications-mobile-dialog-390.png`, `.qa/notifications-mobile-page-390.png`, and `.qa/chat-mobile-compact-filter-390.png`.

## Rollback

The pre-change checkpoint is `.checkpoints/before-notification-responsive-20260920-212128.zip`.