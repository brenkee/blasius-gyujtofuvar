# Delivery Deadline Feature Checks

This document captures manual sanity checks for the delivery deadline functionality that
was introduced in the "Add configurable delivery deadline field and indicators" update.

## Default indicator thresholds
1. Start the PHP development server: `php -S 0.0.0.0:8000`.
2. Open http://127.0.0.1:8000/.
3. Verify that an address with a deadline at least seven days in the future displays the
   green `pic/hatarido.svg` icon next to the title in the left panel header.
4. Confirm that the same deadline value appears in the details panel, map popup, exported
   data, and printing view.

## Near-term deadline (amber)
1. Update an address so that its deadline is between three and six days in the future.
2. Reload the application and confirm that the icon next to the header changes to the
   amber colour defined in `config/config.json`.

## Overdue deadline (red)
1. Set an address deadline to a date that is within the past three days (or earlier).
2. Confirm that the icon changes to the configured red colour and that the formatted
   deadline value continues to render across the UI and exports.

## Disabled deadlines
1. In `config/config.json`, set `deadline.enabled` to `false`.
2. Refresh the application and verify that the deadline input, icon, and data exports no
   longer display deadline data.
3. Re-enable the feature after the check.

## Invalid dates
1. Enter an invalid date (for example, `2025-02-30`) in the deadline input.
2. Confirm that the client-side validation prevents saving the malformed value and keeps
   the previously valid date.
