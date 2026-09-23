# Defect Tracker

Construction defect-management platform for project teams, contractors and clients.

**Release:** 3.0.0  
**Runtime:** PHP 8.2+ with MySQL/MariaDB  
**Deployment:** PHP application. Node.js is not required.

## Core features
- defect creation, assignment and lifecycle review
- mobile contractor task workflow with completion photos
- floor-plan/drawing defect locations
- projects, contractors, users, roles and permissions
- reports and PDF/Excel exports
- notifications and configurable SMTP
- backup/restore and administrator-only project reset

## Secure deployment
1. Copy .env.example to .env and replace every deployment value.
2. Import database/schema.sql and apply newer files in database/migrations/.
3. Use a unique database account/password and HTTPS.
4. Keep .env and private mail settings outside public source control.
5. Keep runtime upload/log folders writable by PHP but not world-writable.
6. Use production PHP settings with display_errors disabled.
7. Create or verify an administrator account with a unique password.

Never commit database dumps, runtime logs, uploads, backups or credentials.

## Release checks
Run:
    php tests/configuration-regression.php constants-first
    php tests/configuration-regression.php database-first
    php tests/functional-regressions.php
    php tests/defect-lifecycle-regression.php
    php tests/site-mail.php
    node tests/service-worker-cache.cjs

GitHub Actions also runs syntax and regression checks on pushes and pull requests.

## New-project reset
See CLEANUP_README.md. The reset preserves administrator accounts generically and recursively removes project runtime files.

## Plesk
Configure the site as PHP. Disable unused Node.js application mode unless a future Node service is deliberately added.

## Before promoting rc1 to v3.0.0
- complete an admin -> project -> contractor -> defect -> completion -> manager review smoke test
- verify backup/restore on staging
- run the project reset on staging and confirm project data/uploads are gone
- rotate credentials that were previously exposed outside the server secret store
