# McGoff Defect Tracker Audit — 20 September 2026

## Executive summary

The live application is a PHP/MySQL system. The repository contains no
`app.js`, `package.json`, or Node service, so Plesk's Node.js feature should be
disabled for `mcgoff.defecttracker.uk`. The missing-`app.js` warning is a
hosting configuration mismatch, not an application dependency.

The live login, authenticated dashboard, core admin pages, logout, and
protected-page redirect were tested successfully. The login page itself did
not produce a browser console error. A repeatable JavaScript error was found on
`projects.php`: the script attached an event handler to a missing
`#projectsSearch` element.

The live static assets match commit
`ea5337a3de40230e70ddf0dfd981a687f23af16a` after normalising Windows/Unix line
endings. This strongly indicates that the current deployment is based on the
latest `main` branch, although server-side PHP cannot be proven byte-for-byte
through HTTP.

## Priority findings

### P0 — rotate exposed credentials before deployment

Production and legacy database credentials, plus default application
credentials, were committed to the public repository. One tracked text file
under `system-tools/` was also directly downloadable from the live site and
contained a legacy database credential. Removing secrets from the current tree
does not remove them from Git history.

Actions:

1. Rotate every database password that has appeared in the repository.
2. Rotate the live administrator password and invalidate existing sessions.
3. Review database and hosting access logs for unexpected connections.
4. Enable GitHub secret scanning and rewrite repository history if the owner
   accepts the coordination cost; rotation remains mandatory either way.

### P0 — prevent direct access to private project data

Uploaded defect photographs and drawings are directly addressable without an
authenticated application session. Move private uploads outside the document
root and stream them through an authorised PHP endpoint. As an interim control,
add an authenticated internal redirect rule at the web-server layer.

### P1 — complete authorisation and CSRF coverage

Several state-changing PHP routes accept POST requests without an obvious CSRF
check. The audit identified 26 first-party PHP files requiring individual
review (the login route is a special case). UI visibility is not an
authorisation boundary: each route must enforce role permissions server-side.

The prepared changes add an admin-only guard to `admin.php`, an admin/manager
guard to `add_user.php`, and role plus CSRF checks to project mutations.
Continue the same pattern across user, role, contractor, notification, profile,
defect-status, image-processing, and sync administration routes.

### P1 — disable production error display

Many first-party scripts explicitly enable `display_errors`. Configure Plesk
PHP settings with `display_errors=Off`, `log_errors=On`, and a log path outside
the public document root. Remove per-page development overrides over time.

### P1 — add login abuse controls

`login.php` uses prepared statements, `password_verify`, secure/HttpOnly/Lax
session cookies, and session-ID regeneration. It does not implement effective
login rate limiting. Add database-backed attempt tracking and a Cloudflare or
Plesk rate rule. Use generic errors and record security events without logging
passwords.

### P2 — finish browser security headers

The live login response did not send CSP, HSTS, X-Frame-Options,
X-Content-Type-Options, Referrer-Policy, or Permissions-Policy. The prepared
`.htaccess` adds conservative non-CSP headers. Build a CSP in report-only mode
first because the application currently contains many inline scripts. Enable
HSTS only after confirming that HTTPS is permanent for the domain and required
subdomains.

### P2 — reduce public and production-only clutter

Database dumps, logs, backups, uploaded customer material, a test SQLite
database, old copies, development prompts, and diagnostic utilities are tracked
in the application repository. Existing Apache rules block common dump/log
extensions on the tested live host, but these artefacts should not be shipped at
all. Remove them from the deployable artefact and use private backup storage.

## Fixes prepared in this checkout

- Fixed the Projects-page null dereference.
- Corrected the class-brace error in `includes/generate_defect_image.php`.
- Moved database, sync, backup, and legacy connection settings to the untracked
  environment file.
- Removed known plaintext credentials from the current tracked tree and README.
- Made the password-hash helper CLI-only and removed its embedded password.
- Denied web access to the historical `_ai-prompt.txt` file.
- Added role/CSRF guards to project mutations and tighter admin/user-management
  route guards.
- Added conservative response security headers.

## Plesk and deployment sequence

1. In **Websites & Domains → mcgoff.defecttracker.uk → Node.js**, click
   **Disable Node.js**. Do not create a dummy `app.js`.
2. Keep PHP enabled (the live health page reported PHP 8.4.24) and use the
   Plesk-managed PHP-FPM handler.
3. Rotate the database user's password, then create/update the production
   `.env` before deploying the refactored PHP files. Restrict it to the site
   owner (`0600`) and confirm HTTP requests to `.env` return 403.
4. Deploy the reviewed diff. Do not copy `.git`, SQL dumps, logs, test databases,
   development prompts, or backup archives into `httpdocs`.
5. If Plesk serves static files through nginx, mirror the Apache deny rules in
   **Apache & nginx Settings**; otherwise nginx may bypass `.htaccess`.
6. Set production PHP error display off, keep error logging on, and keep logs
   outside `httpdocs`.
7. Purge any Cloudflare cache, then verify login, dashboard, Projects filtering,
   admin authorisation, logout/session redirect, and the protected-file 403s.
8. Change the live administrator password manually and sign out all other
   sessions.

## Verification performed

- Successful live admin login and dashboard load.
- Authenticated GET checks for Defects, Projects, Users, Reports, Admin, System
  Health, and Profile.
- Successful logout and redirect from a protected page back to login.
- Browser console review; one reproducible Projects error found.
- Static-asset comparison against repository `main`.
- 290 first-party PHP files linted after changes: zero syntax failures.
- Configuration include-order regression tests passed.
- Service-worker cache, notification routing, and mail safety tests passed.
