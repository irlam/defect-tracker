# Website Cleanup Guide

The cleanup tool creates a fresh project template by removing project/user-generated data while preserving administrator accounts, roles/permissions, configuration and database structure.

## Safety
- Create and verify a full backup first.
- Test on staging before production.
- Web access requires an administrator session and CSRF confirmation.
- CLI requires the word RESET unless explicitly invoked through the --yes wrapper.
- The database cleanup uses DELETE statements inside a transaction; it does not rely on TRUNCATE rollback.
- Runtime upload folders are cleaned recursively while .htaccess and .gitkeep are preserved.

## What is removed
Projects, defects, assignments, histories, comments, images, floor-plan records, non-admin users, unrelated contractors, notifications, sync queues, sessions/logs for removed users, and nested runtime uploads.

## What is preserved
All administrator accounts, role/permission definitions, system/company configuration, database structure, report/PDF templates and protection files.

## Run
Web: /admin/cleanup_interface.php

CLI:
    php admin/cleanup.php

After completion, verify admin login, create a test project/defect, then create and verify a fresh backup template.
