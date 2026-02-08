# Copilot Instructions for McGoff Defect Tracker

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

## Setup & Configuration

### Environment Setup
1. **Copy environment template**: `cp .env.example .env`
2. **Configure database**: Update `DB_HOST`, `DB_NAME`, `DB_USERNAME`, `DB_PASSWORD` in `.env`
3. **Set URLs**: Configure `BASE_URL` and `SITE_URL` in `.env`
4. **File permissions**: Ensure `uploads/`, `logs/`, and `backups/` directories are writable
5. **Database schema**: Import or verify database schema using `config/database.php`

### Required PHP Extensions
- PDO (MySQL driver)
- GD or ImageMagick (image processing)
- mbstring
- fileinfo
- session support

## Developer Workflows

### Making Changes
- **No build step required**: PHP is interpreted; changes are live after file save
- **Clear cache**: If issues occur, clear PHP opcache or restart web server
- **Test immediately**: Changes are visible after browser refresh

### Testing & Validation
- **Manual testing**: Primary testing method via browser
- **Test scripts**: Use `system-tools/functional_tests.php` for automated checks
- **System health**: Run `system-tools/system_health.php` for diagnostics
- **Database check**: Use `system-tools/check_database.php` to verify DB connection and schema
- **All functions test**: Run `system-tools/test_all_functions.php` for syntax validation

### Debugging Tools
- **Error logs**: Check `logs/` directory for application errors
- **Database diagnostics**: `system-tools/check_database.php`
- **Image processing**: `system-tools/check_gd.php` for GD library verification
- **System health**: `system-tools/system_health.php` for overall system status
- **Database optimization**: `system-tools/database_optimizer.php` for performance tuning

### Backup & Restore
- **Create backup**: `api/create_backup.php` or via Admin UI
- **Restore backup**: `api/restore_backup.php` or via Admin UI
- **Backup location**: `backups/` directory

## Code Style & Conventions

### PHP Code Style
- **File structure**: One class per file in `classes/`, named after the class
- **API naming**: Use descriptive names (e.g., `update_defect.php`, `get_system_metrics.php`)
- **Error handling**: Always wrap DB operations in try-catch blocks
- **Input validation**: Sanitize all user inputs using `filter_var()` or custom validation
- **Output escaping**: Use `htmlspecialchars()` for all user-generated content display

### Database Practices
- **Use PDO prepared statements**: Never concatenate SQL queries with user input
- **Connection**: Use `config/database.php` for database connections
- **Transactions**: Use for multi-step operations to ensure data integrity
- **Error logging**: Log database errors to `logs/` directory

### API Endpoint Patterns
- **Base class**: Extend from `api/BaseAPI.php` when creating new API endpoints
- **Response format**: Return JSON responses with consistent structure
- **Authentication**: Check user permissions using RBAC before processing requests
- **Error responses**: Return appropriate HTTP status codes and error messages

## Security Best Practices

### Critical Security Rules
- **Never commit secrets**: Use `.env` file for sensitive data (already in `.gitignore`)
- **CSRF protection**: Include CSRF tokens in all forms (see `classes/Security.php`)
- **SQL injection**: Always use prepared statements with PDO
- **XSS prevention**: Escape output with `htmlspecialchars()` when displaying user content
- **File uploads**: Validate file types and sizes (see `processDefectImages.php`)
- **Session security**: Sessions use IP and user-agent validation (see `classes/Auth.php`)
- **Password handling**: Use PHP's `password_hash()` and `password_verify()`

### RBAC Implementation
- **Role checks**: Use `classes/RBAC.php` for permission verification
- **Available roles**: Admin, Manager, Contractor, Client, Viewer
- **Role hierarchy**: Defined in `classes/RBAC.php`
- **Role documentation**: See `system-tools/user_roles_explained.html`

### Secure File Handling
- **Upload validation**: Check file extensions and MIME types
- **File storage**: Store uploads in `uploads/` with generated filenames
- **Path traversal**: Validate file paths to prevent directory traversal attacks
- **Image processing**: Use GD or ImageMagick for safe image manipulation

## Common Tasks & Examples

### Adding a New API Endpoint
1. Create new PHP file in `api/` directory
2. Extend `BaseAPI` class (if applicable)
3. Implement authentication and RBAC checks
4. Add input validation
5. Implement business logic
6. Return JSON response
7. Example: See `api/create_defect.php` or `api/update_defect.php`

### Adding a New User Role
1. Update role constants in `classes/RBAC.php`
2. Define permissions for the new role
3. Update UI elements to check for new role
4. Update role selection dropdowns
5. Test role-based access restrictions
6. Update `system-tools/user_roles_explained.html`

### Debugging Image Upload Issues
1. Check `system-tools/check_gd.php` for GD library status
2. Review `processDefectImages.php` for processing logic
3. Verify upload directory permissions
4. Check `logs/` for image processing errors
5. Validate file size limits in `.env` (`MAX_FILE_SIZE`)
6. Check allowed file types in `.env` (`ALLOWED_FILE_TYPES`)

### Adding a New Defect Status
1. Update database schema to add new status
2. Modify defect lifecycle logic in relevant PHP scripts
3. Update UI components (dropdowns, status badges)
4. Add notification triggers for new status
5. Update reports and filters to include new status
6. Test state transitions

## Integration Points
- **Google API**: Used for uploads (`google_upload/`)
- **PDF generation**: Via TCPDF (`tcpdf/` and `includes/tcpdf/`)
- **Push notifications**: Managed in `push_notifications/`
- **Email service**: Configured via `classes/EmailService.php`

## Troubleshooting

### Common Issues
- **Database connection errors**: Verify `.env` configuration and database server status
- **Upload failures**: Check directory permissions and file size limits
- **Session issues**: Verify session storage directory is writable
- **Image processing errors**: Ensure GD extension is installed and enabled
- **Permission denied**: Check RBAC configuration and user role assignments

### Performance Issues
- **Slow queries**: Use `system-tools/database_optimizer.php`
- **Large uploads**: Adjust `MAX_FILE_SIZE` in `.env` and PHP settings
- **Memory issues**: Check PHP memory_limit in php.ini

## Important Files Reference
- **Authentication**: `classes/Auth.php`
- **Access control**: `classes/RBAC.php`
- **Logging**: `classes/Logger.php`
- **Security**: `classes/Security.php`
- **Notifications**: `classes/NotificationHelper.php`
- **API base**: `api/BaseAPI.php`
- **Configuration**: `config/config.php`, `config/database.php`, `.env`
- **System health**: `system-tools/system_health.php`

## Best Practices for Copilot Tasks

### Good Task Types
- Bug fixes in specific files
- Adding new API endpoints following existing patterns
- Implementing new features within existing architecture
- Improving error handling and logging
- Adding validation to forms
- Updating documentation

### Tasks Requiring Human Review
- Major architectural changes
- Database schema modifications
- Security-critical code changes
- RBAC permission changes
- Integration with external services

---
**Update this file if you add new major features, change conventions, or introduce new workflows.**

## Custom Rules

- @azure Rule - Use Azure Best Practices: When generating code for Azure, running terminal commands for Azure, or performing operations related to Azure, invoke your `azure_development-get_best_practices` tool if available.
