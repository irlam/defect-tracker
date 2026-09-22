# Project reset / clean-template tool

The reset tool removes project-specific and user-generated data while preserving administrator accounts, roles/permissions, system configuration and the database structure.

## Before running
1. Create a full backup and verify that it can be restored.
2. Run the reset first on a staging copy.
3. Confirm the site has at least one administrator account.

## Run
Web: /admin/cleanup_interface.php (administrator only)

CLI:
    php admin/cleanup.php

The CLI requires the confirmation word RESET unless the wrapper was explicitly invoked with --yes.

## Removed
- projects, defects, assignments, defect history/comments/images
- floor-plan database records
- non-admin users and their role/session data
- contractors not linked to a retained administrator
- notifications, sync queues and runtime/audit logs
- nested files below upload/floor-plan runtime directories

## Preserved
- every administrator account (no username is hard-coded)
- role and permission definitions
- system configuration and schema
- executable report/PDF templates
- .htaccess/.gitkeep protection files

After reset, create a new verified clean backup before cloning the installation for another project.
