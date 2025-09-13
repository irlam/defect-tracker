# McGoff Defect Tracker

A comprehensive PHP-based construction defect tracking system designed for managing and monitoring defects throughout construction projects.

## Features

- **Defect Management**: Create, assign, and track defects with detailed information
- **Floor Plan Integration**: Visual defect placement on uploaded floor plans
- **User Management**: Role-based access control (Admin, Manager, Contractor, Client, Viewer)
- **Email Notifications**: Automated notifications for defect assignments and updates
- **Reporting**: PDF exports and comprehensive reporting system
- **Mobile Responsive**: Works on desktop, tablet, and mobile devices
- **Image Upload**: Support for multiple image attachments per defect
- **Activity Tracking**: Comprehensive audit trails and user activity logs

## Recent Improvements (2025)

✅ **Security Enhancements**
- Environment-based configuration management
- CSRF protection implementation
- Secure session management with activity logging
- Enhanced input validation and sanitization
- Centralized error handling and logging

✅ **Performance Optimizations**
- Database query optimization tools
- Performance monitoring utilities
- Image optimization capabilities
- Automatic cache cleanup
- SQL query improvements

✅ **Code Quality**
- JavaScript utilities for enhanced UX
- Responsive CSS framework
- Accessibility improvements
- Mobile-first design approach

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- GD extension for image processing
- PDO extension for database connectivity

## Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/irlam/defect-tracker.git
   cd defect-tracker
   ```

2. **Configure environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials and settings
   ```

3. **Set up the database**
   - Create a new MySQL database
   - Import the database schema (if available)
   - Update database credentials in `.env`

4. **Set permissions**
   ```bash
   chmod 600 .env
   chmod -R 755 uploads/
   chmod -R 755 logs/
   ```

5. **Configure web server**
   - Point document root to the project directory
   - Ensure `.htaccess` files are processed (Apache)
   - Configure SSL certificate for production

## Configuration

### Environment Variables (.env)
```env
# Database Configuration
DB_HOST=localhost:3306
DB_NAME=defect_tracker
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Application Settings
BASE_URL=https://your-domain.com/
ENVIRONMENT=production

# Email Configuration
EMAIL_FROM=noreply@your-domain.com
```

### Default Admin User
- Username: `irlam`
- Password: `subaru555`
- Role: Admin

*Change these credentials immediately after installation*

## Usage

### Creating Defects
1. Navigate to "Create Defect" in the main menu
2. Fill out defect details (title, description, priority, etc.)
3. Upload floor plan (if available)
4. Place pin on floor plan to mark defect location
5. Upload supporting images
6. Assign to contractor or user
7. Submit the defect

### Managing Users
1. Access "User Management" (Admin/Manager only)
2. Create new users with appropriate roles
3. Assign permissions based on user type
4. Monitor user activity through activity logs

### Reporting
1. Navigate to "Reports" section
2. Apply filters (date range, status, contractor, etc.)
3. Generate PDF reports
4. Export data to Excel for further analysis

## Development

### Performance Monitoring
Use the built-in performance monitoring tools:
```php
Performance::start();
// Your code here
Performance::checkpoint('operation_name');
$report = Performance::getReport();
```

### Database Optimization
Run the database optimizer to analyze performance:
```bash
php _my-tools/database_optimizer.php
```

## File Structure

```
defect-tracker/
├── api/                    # API endpoints
├── assets/                 # Images, floor plans, icons
├── classes/                # Core business logic classes
├── config/                 # Configuration files
├── css/                    # Stylesheets
├── includes/               # Shared PHP includes
├── js/                     # JavaScript files
├── logs/                   # Application logs
├── uploads/                # User uploaded files
├── _my-tools/              # Development and maintenance tools
└── *.php                   # Main application pages
```

## Security

- Always use HTTPS in production
- Keep database credentials secure
- Regularly update dependencies
- Monitor security logs
- Implement proper backup procedures

For detailed improvement documentation, see `IMPROVEMENTS.md`.

## License

This project is proprietary. Please contact the owner for licensing information.