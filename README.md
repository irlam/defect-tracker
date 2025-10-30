# McGoff Construction Defect Tracker

A comprehensive, enterprise-grade PHP-based defect tracking system specifically designed for construction projects. Built with modern web technologies and security best practices to streamline defect management, improve communication, and enhance project quality control.

## 🎯 What It Does

The McGoff Defect Tracker is a complete construction management solution that enables construction teams to:

- **Track & Manage Defects**: Create, assign, monitor, and resolve construction defects with complete audit trails
- **Visual Defect Mapping**: Pin defects directly on floor plans and construction drawings for precise location tracking
- **Team Collaboration**: Real-time notifications and communication between contractors, project managers, and stakeholders
- **Quality Assurance**: Comprehensive reporting and analytics for construction quality management
- **Mobile-First Design**: Full responsive design for on-site defect reporting and management

## ✨ Key Features

### 🏗️ Core Defect Management
- **Defect Lifecycle Tracking**: Complete workflow from creation → assignment → acceptance/rejection → resolution
- **Visual Floor Plan Integration**: Click-to-pin defect locations on uploaded construction drawings
- **Multi-Image Support**: Attach multiple photos per defect with automatic optimization
- **Priority Management**: Critical, High, Medium, Low priority levels with due dates
- **Status Tracking**: Real-time status updates (Created, Assigned, Accepted, Rejected, Reopened, Resolved)

### 👥 User Management & RBAC
- **Role-Based Access Control**: Admin, Manager, Contractor, Client, Viewer roles with granular permissions
- **Contractor Management**: Dedicated contractor profiles with company information and contact details
- **User Activity Logging**: Complete audit trails of all user actions and system changes
- **Secure Authentication**: Advanced session management with CSRF protection and secure password hashing

### 🔔 Real-Time Notifications (Latest Feature)
- **Instant Notifications**: Server-Sent Events for real-time defect updates
- **Smart Targeting**: Automatic notifications to relevant stakeholders (assignees, contractors, managers)
- **Notification Center**: Dedicated UI for viewing and managing all notifications
- **Toast Alerts**: Non-intrusive popup notifications for immediate awareness
- **Mark as Read**: Individual and bulk notification management

### 📊 Reporting & Analytics
- **Comprehensive Reports**: Filter by date, status, contractor, project, and priority
- **PDF Export**: Professional PDF reports with charts and defect details
- **System Metrics**: Real-time dashboard with project statistics and KPIs
- **Excel Export**: Data export for external analysis and reporting

### 🖼️ Visual & Media Management
- **Floor Plan Upload**: Support for PDF and image floor plans with zoom and pan
- **Image Processing**: Automatic image optimization and thumbnail generation
- **Drawing Tools**: Markup capabilities for defect annotation
- **File Organization**: Structured storage with automatic cleanup

### 🔧 System Administration
- **Backup & Restore**: Automated database backups with point-in-time recovery
- **System Health Monitoring**: Built-in diagnostics and performance monitoring
- **Configuration Management**: Environment-based configuration with secure credential storage
- **Log Management**: Centralized logging with automatic rotation

## 🏛️ Architecture & Technology

### Backend Architecture
- **PHP 7.4+**: Server-side processing with modern PHP features
- **MySQL/MariaDB**: Robust relational database with optimized queries
- **PDO**: Secure database abstraction with prepared statements
- **MVC Pattern**: Separation of concerns with classes, views, and controllers

### Security Features
- **CSRF Protection**: Token-based protection against cross-site request forgery
- **Input Sanitization**: Comprehensive input validation and sanitization
- **Secure Sessions**: Advanced session management with IP/user-agent validation
- **Environment Variables**: Secure configuration management
- **File Upload Security**: Strict validation and secure file handling

### Frontend Technologies
- **Bootstrap 5**: Responsive, mobile-first CSS framework
- **JavaScript ES6+**: Modern JavaScript with async/await support
- **Server-Sent Events**: Real-time notifications without WebSockets
- **PDF.js**: Client-side PDF rendering for floor plans
- **SweetAlert2**: Enhanced user notifications and confirmations

## 📱 User Roles & Permissions

### 👑 Administrator
- Full system access and configuration
- User management and role assignment
- System settings and maintenance
- Backup and restore operations
- Complete audit trail access

### 👔 Manager
- Project oversight and defect assignment
- User management within their projects
- Comprehensive reporting access
- Contractor management
- Quality control monitoring

### 🏗️ Contractor
- Defect assignment and status updates
- Access to assigned defects only
- Image upload and documentation
- Communication with project managers
- Personal dashboard and task tracking

### 🏢 Client
- View defects for their projects
- Comment and provide feedback
- Access to project reports
- Communication with contractors
- Read-only access to most features

### 👁️ Viewer
- Read-only access to assigned projects
- View defect details and progress
- Access to reports and analytics
- No modification capabilities

## 🚀 Getting Started

### Prerequisites
- **Web Server**: Apache/Nginx with PHP support
- **PHP**: Version 7.4 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.0+
- **Extensions**: GD, PDO, mbstring, fileinfo
- **Storage**: Write permissions for uploads/ and logs/ directories

### Quick Installation

1. **Clone the Repository**
   ```bash
   git clone https://github.com/irlam/defect-tracker.git
   cd defect-tracker
   ```

2. **Configure Environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

3. **Database Setup**
   ```sql
   -- Create database
   CREATE DATABASE defect_tracker CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

   -- Import schema (if available) or run setup scripts
   ```

4. **File Permissions**
   ```bash
   chmod 600 .env
   chmod -R 755 uploads/ logs/
   ```

5. **Access the Application**
   - Navigate to your domain in a web browser
   - Default login: `irlam` / `subaru555` (change immediately!)

### Default Admin Credentials
**⚠️ CHANGE THESE IMMEDIATELY AFTER INSTALLATION**
- Username: `irlam`
- Password: `subaru555`
- Role: Administrator

## 📋 Usage Guide

### Creating a Defect
1. Navigate to "Create Defect" from the main menu
2. Select project and fill in defect details
3. Upload floor plan and pin defect location
4. Attach supporting images
5. Assign to contractor and set priority
6. Submit - automatic notifications sent to stakeholders

### Managing Projects
1. Access "Projects" section (Admin/Manager)
2. Create new projects with details
3. Upload floor plans for each project
4. Assign team members and contractors
5. Monitor project progress and defect counts

### Contractor Management
1. Navigate to "Contractors" (Admin/Manager)
2. Add contractor companies with contact information
3. Create user accounts for contractor personnel
4. Assign contractors to specific projects
5. Monitor contractor performance and defect resolution rates

### Reporting & Analytics
1. Access "Reports" section
2. Apply filters (date range, project, contractor, status)
3. Generate PDF reports or export to Excel
4. View system metrics and KPIs
5. Schedule automated reports (future enhancement)

## 🔧 API Endpoints

The system provides REST-like API endpoints for integrations:

### Defect Management
- `POST /api/create_defect.php` - Create new defect
- `GET /api/get_defects.php` - Retrieve defect list
- `PUT /api/update_defect.php` - Update defect details
- `DELETE /api/delete_defect.php` - Remove defect

### User Management
- `POST /api/create_user.php` - Create user account
- `GET /api/get_users.php` - List users
- `PUT /api/update_user.php` - Update user information

### System Operations
- `GET /api/get_system_metrics.php` - System statistics
- `POST /api/create_backup.php` - Database backup
- `POST /api/restore_backup.php` - Database restore

## 🛠️ Development & Maintenance

### Code Structure
```
defect-tracker/
├── api/                    # API endpoints and integrations
├── assets/                 # Static assets (images, icons)
├── classes/                # Business logic classes
│   ├── Auth.php           # Authentication handling
│   ├── RBAC.php           # Role-based access control
│   ├── NotificationHelper.php # Notification management
│   └── Logger.php         # System logging
├── config/                # Configuration files
├── css/                   # Stylesheets and themes
├── includes/              # Shared PHP includes
├── js/                    # JavaScript utilities
├── uploads/               # User-uploaded files
├── logs/                  # System and error logs
└── _my-tools/             # Development utilities
```

### Development Tools
- **System Health Check**: `_my-tools/system_health.php`
- **Database Optimizer**: `_my-tools/database_optimizer.php`
- **Function Testing**: `test_all_functions.php`
- **Performance Monitor**: Built-in performance tracking

### Security Best Practices
- Regular security updates and patches
- Monitor access logs for suspicious activity
- Implement SSL/TLS certificates
- Regular database backups
- Secure file permissions
- Environment variable usage for secrets

## 📈 Performance & Scalability

### Optimization Features
- **Database Query Optimization**: Indexed queries and prepared statements
- **Image Optimization**: Automatic compression and thumbnail generation
- **Caching**: Built-in performance monitoring and optimization tools
- **Lazy Loading**: Efficient loading of large datasets
- **CDN Ready**: Static asset optimization for external CDN usage

### Monitoring & Maintenance
- **Performance Tracking**: Built-in benchmarking and monitoring
- **Log Rotation**: Automatic cleanup of old log files
- **Database Maintenance**: Automated optimization and cleanup
- **Backup Automation**: Scheduled backup procedures
- **Health Checks**: System status monitoring

## 🔄 Recent Enhancements (2025)

### ✅ Real-Time Notification System
- Server-Sent Events for instant notifications
- Notification center with read/unread management
- Toast notifications for immediate awareness
- Automated triggers for defect lifecycle events

### ✅ Security Hardening
- CSRF token implementation across all forms
- Enhanced input validation and sanitization
- Secure session management improvements
- Environment-based configuration management

### ✅ Performance Improvements
- Database query optimization
- Image processing enhancements
- Memory usage optimization
- Caching layer improvements

### ✅ User Experience
- Mobile-responsive design improvements
- Enhanced accessibility features
- Improved navigation and workflows
- Better error handling and user feedback

## 🤝 Contributing

### Development Guidelines
1. Follow PSR-12 coding standards
2. Use meaningful commit messages
3. Test all changes thoroughly
4. Update documentation for new features
5. Maintain backward compatibility

### Testing
- Run `test_all_functions.php` for syntax validation
- Use `functional_tests.php` for integration testing
- Test on multiple browsers and devices
- Validate security features

## 📞 Support & Documentation

### Documentation Files
- `IMPROVEMENTS.md` - Detailed enhancement documentation
- `TESTING_SUMMARY.md` - Comprehensive testing results
- `_my-tools/user_roles_explained.html` - User role documentation
- `.github/copilot-instructions.md` - Development guidelines

### Getting Help
- Check system health: `_my-tools/system_health.php`
- Review error logs in `logs/` directory
- Use database diagnostics: `_my-tools/database_optimizer.php`

## 📄 License

This project is proprietary software. Please contact the owner for licensing information and commercial usage rights.

## 🙏 Acknowledgments

Built with modern web technologies and best practices for construction industry defect management. Special thanks to the development team for their dedication to quality and security.

---

**Version**: 2.5.0 (2025)  
**Last Updated**: October 30, 2025  
**PHP Version**: 7.4+  
**Database**: MySQL 5.7+/MariaDB 10.0+