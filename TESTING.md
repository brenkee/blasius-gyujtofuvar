# Delivery Deadline Feature Checks

This document captures manual sanity checks for the delivery deadline functionality that
was introduced in the "Add configurable delivery deadline field and indicators" update.

## Default indicator thresholds
1. Start the PHP development server: `php -S 0.0.0.0:8000`.
2. Open http://127.0.0.1:8000/.
3. Verify that an address with a deadline at least seven days in the future displays the
   green `hatarido.svg` icon next to the title in the left panel header.
4. Confirm that the same deadline value appears in the details panel, map popup, exported
   data, and printing view.

## Near-term deadline (amber)
1. Update an address so that its deadline is between three and six days in the future.
2. Reload the application and confirm that the icon next to the header changes to the
   amber colour defined in `config.json`.

## Overdue deadline (red)
1. Set an address deadline to a date that is within the past three days (or earlier).
2. Confirm that the icon changes to the configured red colour and that the formatted
   deadline value continues to render across the UI and exports.

## Disabled deadlines
1. In `config.json`, set `deadline.enabled` to `false`.
2. Refresh the application and verify that the deadline input, icon, and data exports no
   longer display deadline data.
3. Re-enable the feature after the check.

## Invalid dates
1. Enter an invalid date (for example, `2025-02-30`) in the deadline input.
2. Confirm that the client-side validation prevents saving the malformed value and keeps
   the previously valid date.

## Authentication and role management
1. Run `php -S 0.0.0.0:8000` from the project root and open `http://127.0.0.1:8000/login.php`.
2. Sign in with the default credentials (`admin` / `admin`). You should be redirected to the admin panel with a mandatory password change banner.
3. Update the admin profile with a new strong password (minimum eight characters, letters and digits). After saving, refresh the page to confirm that the banner disappears.
4. Create a new `editor` user via the admin panel, leaving the "Belépéskor kötelező jelszócsere" checkbox enabled.
5. Sign out, then sign in as the newly created editor. Verify that the forced profile dialog appears and that the hamburger menu in the main UI does **not** contain the "Admin felület" link.
6. Update an existing user to the `viewer` role and sign in. Confirm that write operations (for example, CSV import) are rejected by the API with a permission error toast.
7. Attempt to call a mutating endpoint (such as `/auth_api.php?action=create_user`) in a separate tab without providing the CSRF token. Confirm that the server responds with HTTP 403.
