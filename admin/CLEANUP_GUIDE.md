# Website Cleanup Guide

This guide explains how to use the website cleanup scripts to create a fresh, clean backup template of the defect tracker system.

## Overview

The cleanup scripts remove all user-generated data while preserving the admin account and system configuration. This is useful for:

- Creating a clean backup template for new installations
- Resetting the system to a fresh state
- Removing legacy/redundant data before creating a deployment backup

## What Gets Deleted

The cleanup process will **permanently delete**:

- ✗ All defects and related data (images, comments, history, assignments)
- ✗ All projects
- ✗ All floor plans and uploaded files
- ✗ All contractors (except those linked to the admin user)
- ✗ All non-admin users
- ✗ All logs and notifications
- ✗ All activity history
- ✗ Sync data (conflicts, devices, logs, queue)
- ✗ Export logs
- ✗ User sessions and recent descriptions

## What Gets Preserved

The cleanup process will **preserve**:

- ✓ Admin account (username: `irlam`)
- ✓ System configuration and settings
- ✓ Database structure (all tables and views)
- ✓ System roles and permissions
- ✓ Company settings
- ✓ Categories (optional - can be modified in the script)

## Usage Methods

### Method 1: Web Interface (Recommended)

1. **Create a full backup first** (IMPORTANT!)
   - Go to the Backup Manager
   - Create a backup of the current system
   - Download and store it safely

2. **Access the cleanup interface**
   - Navigate to: `/admin/cleanup_interface.php`
   - Only accessible to admin users

3. **Run the cleanup**
   - Read all warnings carefully
   - Type `DELETE ALL DATA` in the confirmation field
   - Check both confirmation checkboxes
   - Click "Run Cleanup"

4. **Create a fresh backup template**
   - After cleanup completes, click "Create Fresh Backup Template"
   - This backup will be your clean starting point
   - Download and store this backup for future use

### Method 2: Command Line

1. **Navigate to the admin directory**
   ```bash
   cd /path/to/defect-tracker/admin
   ```

2. **Run the cleanup script**
   ```bash
   php cleanup.php
   ```

3. **Confirm the action**
   - Type `yes` when prompted
   - The script will execute the cleanup

4. **Skip confirmation (use with caution!)**
   ```bash
   php cleanup.php --yes
   ```

### Method 3: Direct PHP Script

1. **Include the script in your code**
   ```php
   require_once '/path/to/admin/cleanup_website.php';
   ```

2. **The script will execute automatically** when included

## Safety Features

The cleanup scripts include several safety features:

1. **Admin-only access**: Web interface requires admin login
2. **Confirmation prompts**: Multiple confirmations required
3. **Transaction rollback**: Database changes are rolled back on error
4. **Detailed logging**: All operations are logged to the output
5. **File preservation**: System files (.gitkeep, .htaccess) are not deleted

## Post-Cleanup Steps

After running the cleanup:

1. **Verify the admin login**
   - Username: `irlam`
   - The password remains unchanged

2. **Create a fresh backup**
   - Use the Backup Manager or the web interface button
   - Label it as "Fresh Template" or similar
   - Store it securely for future use

3. **Test the system**
   - Log in as admin
   - Verify all pages load correctly
   - Check that you can create new projects/defects

4. **Document the template**
   - Note the date and version
   - Record any custom configurations
   - Store credentials securely

## Using the Fresh Backup

To use your fresh backup template:

1. **Upload to new hosting**
   - Upload all website files
   - Import the database from the backup

2. **Configure environment**
   - Update `.env` or `config/database.php` with new database credentials
   - Update `BASE_URL` if necessary

3. **Restore the backup**
   - Use the Backup Manager to restore the fresh template
   - Or manually import the SQL file

4. **Update admin credentials**
   - Log in with username `irlam`
   - Change the password immediately
   - Update email and profile information

## Troubleshooting

### Error: "Database connection failed"
- Check database credentials in `config/database.php`
- Ensure MySQL/MariaDB is running
- Verify database user has sufficient privileges

### Error: "Unauthorized access"
- Ensure you're logged in as admin
- Clear browser cache and cookies
- Try logging out and back in

### Error: "Could not truncate table"
- Some tables might be views, not tables (this is normal)
- Check the output for specific table names
- Verify database user has DROP/TRUNCATE privileges

### Files not deleted
- Check file permissions on upload directories
- Ensure web server has write permissions
- Manually delete remaining files if needed

## Important Notes

1. **Always create a backup before cleanup**: You cannot undo this operation
2. **The cleanup is immediate and permanent**: There is no "undo" function
3. **Test on a development environment first**: Don't run on production without testing
4. **Update documentation**: Record when and why cleanup was performed
5. **Store backups securely**: Keep multiple copies in different locations

## Script Files

- `admin/cleanup_website.php` - Core cleanup logic
- `admin/cleanup_interface.php` - Web interface
- `admin/cleanup.php` - Command line interface
- `admin/CLEANUP_GUIDE.md` - This documentation

## Security Considerations

- Scripts require admin authentication
- Web interface includes CSRF protection
- Confirmation codes prevent accidental execution
- All actions are logged
- Database transactions ensure data integrity

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the script output for specific errors
3. Consult the database error logs
4. Contact the system administrator

---

**Version**: 1.0  
**Created**: 2025-11-04  
**Author**: GitHub Copilot  
**Last Updated**: 2025-11-04
