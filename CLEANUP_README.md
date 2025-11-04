# Website Cleanup Tool

## Quick Start

This repository now includes a cleanup tool to remove all user-generated data while preserving the admin account and system configuration.

### Purpose

- Create a fresh, clean backup template
- Reset the website to initial state
- Remove legacy/redundant data before deployment

### Access

**Web Interface (Recommended):**
- Navigate to: `/admin/cleanup_interface.php`
- Requires admin login
- Step-by-step guided process

**Command Line:**
```bash
cd admin
php cleanup.php
```

### What Gets Preserved

✓ Admin account (username: `irlam`)  
✓ System configuration  
✓ Database structure  
✓ Roles and permissions  

### What Gets Deleted

✗ All defects and related data  
✗ All projects  
✗ All floor plans  
✗ All non-admin users  
✗ All logs and notifications  
✗ All uploaded files  

### Important Notes

1. **ALWAYS create a full backup first** using the Backup Manager
2. This operation is **permanent and cannot be undone**
3. Test on a development environment first
4. After cleanup, create a fresh backup to use as a template

### Documentation

See `/admin/CLEANUP_GUIDE.md` for detailed instructions, troubleshooting, and best practices.

---

For the main project documentation, see the original README.md
