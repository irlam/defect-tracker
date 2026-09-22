# Cleanup implementation - v3

The v3 cleanup path is implemented by admin/cleanup_website.php and can be invoked from the administrator web interface or CLI wrapper.

Key release-hardening changes:
- administrator preservation is role-based, not tied to a named username
- database rows are removed with DELETE inside a transaction instead of TRUNCATE
- foreign-key checks are restored on both success and failure
- nested upload/floor-plan runtime files are removed recursively
- executable PDF/report templates are preserved; only generated PDF/tmp output is removed
- .htaccess and .gitkeep files are preserved
- cleanup stops if no administrator exists to retain

This is a destructive operation. A verified backup is required before use and the reset should be exercised on staging as part of the v3 release smoke test.
