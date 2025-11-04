# Quick Start: Website Cleanup

## 🚀 Fastest Way to Use

### Option 1: Web Interface (Recommended)

1. Log in as admin
2. Go to **Admin Dashboard** → **Monitoring & Maintenance** → **Website Cleanup**
3. Or visit: `https://your-domain.com/admin/cleanup_interface.php`
4. Follow the on-screen instructions

### Option 2: Command Line

```bash
# Navigate to admin directory
cd /path/to/defect-tracker/admin

# Run with confirmation
php cleanup.php

# Run without confirmation (automation)
php cleanup.php --yes
```

## ⚠️ Important: Do This FIRST!

**Create a backup before cleanup!**

1. Go to: `/backups/index.php`
2. Click "Create New Backup"
3. Download and save the backup file

## 📋 What Happens

### Gets Deleted ❌
- All defects
- All projects
- All contractors
- All users (except admin)
- All uploaded files
- All logs

### Gets Kept ✅
- Admin login (username: `irlam`)
- System settings
- Database structure

## 🎯 After Cleanup

1. **Test the admin login**
   - Username: `irlam`
   - Password: (unchanged)

2. **Create a fresh backup template**
   - Go to backup manager
   - Create a new backup
   - Label it "Fresh Template"
   - Download and store safely

3. **Use the template for new installations**
   - Upload the fresh backup to new hosting
   - Restore it using backup manager
   - Update admin password

## 📚 Need More Help?

- **Full Guide**: See `admin/CLEANUP_GUIDE.md`
- **Technical Details**: See `CLEANUP_IMPLEMENTATION.md`
- **Quick Reference**: See `CLEANUP_README.md`

## ⚡ Common Commands

```bash
# Check syntax
php -l admin/cleanup.php

# Run cleanup
php admin/cleanup.php

# Run cleanup without confirmation
php admin/cleanup.php --yes
```

## 🆘 Troubleshooting

| Problem | Solution |
|---------|----------|
| "Database connection failed" | Check `config/database.php` credentials |
| "Unauthorized access" | Make sure you're logged in as admin |
| Can't access web interface | Check file permissions, should be 644 |
| CLI script won't run | Make it executable: `chmod +x admin/cleanup.php` |

## 📞 Support

For detailed troubleshooting, see the **Troubleshooting** section in `admin/CLEANUP_GUIDE.md`.

---

**Need to restore data?** Use a backup created before cleanup!
