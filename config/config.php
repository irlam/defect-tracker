<?php
return [
    'app_name' => 'User Management System',
    'version' => '1.0.0',
    'timezone' => 'UTC',
    'date_format' => 'd-m-Y H:i:s',
    'session_timeout' => 1800, // 30 minutes
    'max_login_attempts' => 5,
    'lockout_duration' => 1800, // 30 minutes
    'password_reset_timeout' => 3600, // 1 hour
    'audit_log_retention' => 90, // days
];