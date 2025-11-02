# Copilot Instructions for new-defect-tracker

## Project Overview
This is a PHP-based defect tracking system for construction projects. The codebase is organized by functional areas, with major components including:
- **Root PHP scripts**: Handle main user flows (defect creation, assignment, dashboard, user management)
- **`api/`**: REST-like endpoints for CRUD operations, system metrics, and integrations
- **`classes/`**: Core business logic (auth, email, logging, RBAC)
- **`assets/`**: Images, icons, and floor plans
- **`admin/`**: System settings and admin tools
- **`includes/`**: Shared libraries and third-party code

## Key Architectural Patterns
- **Separation of concerns**: UI logic in root PHP files, business logic in `classes/`, API endpoints in `api/`
- **RBAC (Role-Based Access Control)**: Managed via `classes/RBAC.php` and related files
- **Defect lifecycle**: Defects move through states (created, assigned, accepted, rejected, reopened) via dedicated scripts and API endpoints
- **Contractor/user management**: Modular scripts for adding, editing, and viewing users/contractors
- **Image/file handling**: Uploads managed in `uploads/`, with processing in scripts like `processDefectImages.php` and `upload_floor_plan.php`

## Developer Workflows
- **No build step required**: PHP is interpreted; changes are live
- **Debugging**: Use `system_health.php` and `check_database.php` in `system-tools/` for diagnostics
- **Backups**: Managed via `api/create_backup.php` and `api/restore_backup.php`
- **Testing**: Manual via browser; no automated test suite detected
- **Database**: MySQL/MariaDB (connection details in `config/`)

## Project-Specific Conventions
- **API endpoints**: All in `api/`, named for their function (e.g., `update_defect.php`, `get_system_metrics.php`)
- **Class files**: One class per file in `classes/`, named after the class
- **Error logging**: Centralized via `classes/Logger.php` and `api/log-image-error.php`
- **User roles**: Explained in `system-tools/user_roles_explained.html`
- **Floor plan images**: Stored in `assets/floor_plans/`, referenced by scripts and APIs

## Integration Points
- **Google API**: Used for uploads (`google_upload/`)
- **PDF generation**: Via TCPDF (`tcpdf/` and `includes/tcpdf/`)
- **Push notifications**: Managed in `push_notifications/`

## Examples
- To add a new API endpoint, create a PHP file in `api/` and follow the pattern in `BaseAPI.php`
- To add a new user role, update `classes/RBAC.php` and reference in UI scripts
- To debug image uploads, use `_my-tools/check_gd.php` and review `processDefectImages.php`

## References
- `classes/Auth.php`, `classes/RBAC.php`, `api/BaseAPI.php`, `_my-tools/system_health.php`, `config/`

---
**Update this file if you add new major features, change conventions, or introduce new workflows.**

- @azure Rule - Use Azure Best Practices: When generating code for Azure, running terminal commands for Azure, or performing operations related to Azure, invoke your `azure_development-get_best_practices` tool if available.
