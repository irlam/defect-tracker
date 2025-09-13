<?php
/**
 * maintenance.php - Enhanced Database Maintenance Tool with improved security
 * 
 * This script provides database maintenance operations including:
 * - Table optimization
 * - Database repair
 * - Database backup
 * - System statistics
 */

// Error handling for production
error_reporting(E_ALL);
ini_set('display_errors', 0); 
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/maintenance_error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the database configuration file
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';

// Create database connection using the Database class from database.php
$database = new Database();
$db = $database->getConnection();

// Verify connection
if (!$db) {
    die("Database connection failed. Please check your configuration.");
}

// Anti-CSRF token validation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        // Log potential CSRF attempt
        error_log("CSRF attempt detected from IP: " . $_SERVER['REMOTE_ADDR']);
        die("Security error: Invalid request token. Please try again.");
    }
}

/**
 * Enhanced function to check if user is admin
 * 
 * @param PDO $db Database connection
 * @param int $userId User ID to check
 * @return bool True if user is admin, false otherwise
 */
function isAdmin($db, $userId) {
    try {
        // Check if userId is valid
        if (!$userId || !is_numeric($userId)) {
            error_log("isAdmin check failed: Invalid user ID: " . var_export($userId, true));
            return false;
        }
        
        // First, check if the user exists
        $userCheck = $db->prepare("SELECT username, role FROM users WHERE id = :user_id");
        $userCheck->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $userCheck->execute();
        $userInfo = $userCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$userInfo) {
            error_log("isAdmin check failed: User ID $userId not found in database");
            return false;
        }
        
        // Direct role check
        if (isset($userInfo['role']) && strtolower($userInfo['role']) === 'admin') {
            return true;
        }
        
        // Check through user_roles table
        $query = "SELECT r.name as role_name
                  FROM users u
                  JOIN user_roles ur ON u.id = ur.user_id
                  JOIN roles r ON ur.role_id = r.id
                  WHERE u.id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check for 'admin' role (case-insensitive)
        foreach ($roles as $role) {
            if (strtolower($role['role_name']) === 'admin') {
                return true;
            }
        }
        
        return false;
    } catch (PDOException $e) {
        error_log("isAdmin check error: " . $e->getMessage());
        return false;
    }
}

// Check if user is authenticated and has admin privileges
if (!isset($_SESSION['user_id']) || !isAdmin($db, $_SESSION['user_id'])) {
    // Log unauthorized access attempt
    error_log("Unauthorized maintenance access attempt from IP: " . $_SERVER['REMOTE_ADDR'] . 
              (isset($_SESSION['user_id']) ? " by user ID: " . $_SESSION['user_id'] : " (not logged in)"));
    
    // Redirect to login page
    header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

// Generate new CSRF token if needed
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Get list of database tables
 * 
 * @param PDO $db Database connection
 * @return array List of tables
 */
function getDatabaseTables($db) {
    try {
        $tables = [];
        $result = $db->query("SHOW TABLES");
        
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        
        return $tables;
    } catch (PDOException $e) {
        error_log("Error fetching database tables: " . $e->getMessage());
        return [];
    }
}

/**
 * Perform database optimization on selected tables
 * 
 * @param PDO $db Database connection
 * @param array $selectedTables Tables to optimize
 * @return array Operation result
 */
function optimizeDatabaseTables($db, $selectedTables) {
    if (!empty($selectedTables) && is_array($selectedTables)) {
        try {
            // Validate and sanitize table names
            $validatedTables = [];
            foreach ($selectedTables as $table) {
                // Basic validation - allow only alphanumeric and underscore
                if (preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    $validatedTables[] = $table;
                }
            }
            
            if (empty($validatedTables)) {
                return [
                    'status' => 'error',
                    'message' => 'No valid tables selected for optimization'
                ];
            }
            
            // Process each table individually
            $results = [];
            foreach ($validatedTables as $table) {
                $stmt = $db->prepare("OPTIMIZE TABLE " . $table);
                $stmt->execute();
                // Immediately fetch all results to prevent unbuffered query issues
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor(); // Close the cursor to free the connection
                
                $results[] = "$table optimized";
            }
            
            $tablesToOptimize = implode(', ', $validatedTables);
            
            // Log successful optimization
            $logMessage = "Tables optimized successfully: " . $tablesToOptimize;
            error_log($logMessage);
            
            // Record maintenance activity
            $activityStmt = $db->prepare("INSERT INTO maintenance_log 
                (user_id, action, tables_affected, execution_time, ip_address) 
                VALUES (:user_id, 'optimize', :tables, :exec_time, :ip)");
            
            $activityStmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':tables' => $tablesToOptimize,
                ':exec_time' => date('d/m/Y H:i:s'),
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            return [
                'status' => 'success',
                'message' => 'Tables optimized successfully',
                'details' => $tablesToOptimize
            ];
        } catch (PDOException $e) {
            // Log the error
            error_log("Database optimization error: " . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'Failed to optimize tables',
                'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Database error occurred'
            ];
        }
    } else {
        return [
            'status' => 'error',
            'message' => 'No tables selected for optimization'
        ];
    }
}

/**
 * Perform database repair on selected tables
 * 
 * @param PDO $db Database connection
 * @param array $selectedTables Tables to repair
 * @return array Operation result
 */
function repairDatabaseTables($db, $selectedTables) {
    if (!empty($selectedTables) && is_array($selectedTables)) {
        try {
            // Validate and sanitize table names
            $validatedTables = [];
            foreach ($selectedTables as $table) {
                // Basic validation - allow only alphanumeric and underscore
                if (preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    $validatedTables[] = $table;
                }
            }
            
            if (empty($validatedTables)) {
                return [
                    'status' => 'error',
                    'message' => 'No valid tables selected for repair'
                ];
            }
            
            // Process each table individually
            $results = [];
            foreach ($validatedTables as $table) {
                $stmt = $db->prepare("REPAIR TABLE " . $table);
                $stmt->execute();
                // Immediately fetch all results to prevent unbuffered query issues
                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $stmt->closeCursor(); // Close the cursor to free the connection
                
                $results[] = "$table repaired";
            }
            
            $tablesToRepair = implode(', ', $validatedTables);
            
            // Log successful repair
            $logMessage = "Tables repaired successfully: " . $tablesToRepair;
            error_log($logMessage);
            
            // Record maintenance activity
            $activityStmt = $db->prepare("INSERT INTO maintenance_log 
                (user_id, action, tables_affected, execution_time, ip_address) 
                VALUES (:user_id, 'repair', :tables, :exec_time, :ip)");
                
            $activityStmt->execute([
                ':user_id' => $_SESSION['user_id'],
                ':tables' => $tablesToRepair,
                ':exec_time' => date('d/m/Y H:i:s'),
                ':ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            return [
                'status' => 'success',
                'message' => 'Tables repaired successfully',
                'details' => $tablesToRepair
            ];
        } catch (PDOException $e) {
            // Log the error
            error_log("Database repair error: " . $e->getMessage());
            
            return [
                'status' => 'error',
                'message' => 'Failed to repair tables',
                'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Database error occurred'
            ];
        }
    } else {
        return [
            'status' => 'error',
            'message' => 'No tables selected for repair'
        ];
    }
}
/**
 * Get recent database backups
 * 
 * @param string $backupPath Path to backup directory
 * @return array List of backups with metadata
 */
function getRecentBackups($backupPath) {
    try {
        if (!is_dir($backupPath)) {
            return [];
        }
        
        $backups = [];
        $files = scandir($backupPath);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            // Only include SQL files
            if (pathinfo($file, PATHINFO_EXTENSION) !== 'sql') {
                continue;
            }
            
            $filePath = $backupPath . '/' . $file;
            $backups[] = [
                'filename' => $file,
                'size' => filesize($filePath),
                'date' => date('d/m/Y H:i:s', filemtime($filePath))
            ];
        }
        
        // Sort by most recent first
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        
        return $backups;
    } catch (Exception $e) {
        error_log("Error fetching backups: " . $e->getMessage());
        return [];
    }
}
/**
 * Reset database by emptying all tables except admin user
 * 
 * @param PDO $db Database connection
 * @return array Operation result
 */
function resetDatabase($db) {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get all tables
        $tables = [];
        $tablesQuery = $db->query("SHOW TABLES");
        while($row = $tablesQuery->fetch(PDO::FETCH_NUM)) {
            // Check if table actually exists before adding to the list
            $tableName = $row[0];
            $checkTable = $db->query("SHOW TABLES LIKE '$tableName'");
            if ($checkTable && $checkTable->rowCount() > 0) {
                $tables[] = $tableName;
            }
        }
        
        $preservedTables = ['maintenance_log']; // We'll keep maintenance logs
        $emptiedTables = [];
        
        // Save admin user IDs before truncating
        $adminQuery = $db->query("SELECT id, username FROM users WHERE role = 'admin' OR id IN (
                                 SELECT user_id FROM user_roles 
                                 JOIN roles ON user_roles.role_id = roles.id 
                                 WHERE roles.name = 'admin')");
        $adminUsers = $adminQuery->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($adminUsers)) {
            throw new Exception("No admin users found. Database reset aborted for safety.");
        }
        
        $adminIds = array_column($adminUsers, 'id');
        $adminIdsList = implode(',', $adminIds);
        
        // Process each table
        foreach($tables as $table) {
            // Skip maintenance log
            if (in_array($table, $preservedTables)) {
                continue;
            }
            
            // Special handling for users table - keep admin users
            if ($table === 'users') {
                $db->exec("DELETE FROM users WHERE id NOT IN ($adminIdsList)");
                $emptiedTables[] = "$table (preserved admin users)";
            } 
            // Special handling for user_roles table - keep admin relationships
            else if ($table === 'user_roles') {
                $db->exec("DELETE FROM user_roles WHERE user_id NOT IN ($adminIdsList)");
                $emptiedTables[] = "$table (preserved admin relationships)";
            }
            // For all other tables, truncate them
            else {
                // Disable foreign key checks temporarily
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                $db->exec("TRUNCATE TABLE `$table`");
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                $emptiedTables[] = $table;
            }
        }
        
        // Log the reset action
        $adminUsernames = array_column($adminUsers, 'username');
        $preservedAdmins = implode(', ', $adminUsernames);
        
        $logStmt = $db->prepare("INSERT INTO maintenance_log 
            (user_id, action, details, execution_time, ip_address) 
            VALUES (:user_id, 'database_reset', :details, :exec_time, :ip)");
            
        $logStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':details' => "Database reset completed. Preserved admin users: $preservedAdmins",
            ':exec_time' => date('d/m/Y H:i:s'),
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // Commit transaction
        $db->commit();
        
        return [
            'status' => 'success',
            'message' => 'Database reset completed successfully',
            'details' => [
                'emptied_tables' => $emptiedTables,
                'preserved_admins' => $adminUsernames
            ]
        ];
    } catch (Exception $e) {
        // Roll back transaction in case of error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Database reset error: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'Failed to reset database',
            'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Database operation failed'
        ];
    }
}

/**
 * Create database backup
 * 
 * @param PDO $db Database connection
 * @param string $backupPath Path to store backup file
 * @return array Operation result
 */
function createDatabaseBackup($db, $backupPath) {
    try {
        // Get database class instance to access connection parameters
        $database = new Database();
        
        // Ensure backup directory exists and is writable
        if (!is_dir($backupPath)) {
            if (!mkdir($backupPath, 0750, true)) {
                throw new Exception("Could not create backup directory");
            }
        }
        
        if (!is_writable($backupPath)) {
            throw new Exception("Backup directory is not writable");
        }
        
        // Create unique backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $filename = $backupPath . "/backup_{$timestamp}.sql";
        
        // Get database name (use reflection to access private properties)
        $reflection = new ReflectionClass('Database');
        
        $hostProperty = $reflection->getProperty('host');
        $hostProperty->setAccessible(true);
        $host = $hostProperty->getValue($database);
        
        $dbNameProperty = $reflection->getProperty('db_name');
        $dbNameProperty->setAccessible(true);
        $dbName = $dbNameProperty->getValue($database);
        
        $usernameProperty = $reflection->getProperty('username');
        $usernameProperty->setAccessible(true);
        $username = $usernameProperty->getValue($database);
        
        $passwordProperty = $reflection->getProperty('password');
        $passwordProperty->setAccessible(true);
        $password = $passwordProperty->getValue($database);
        
        // Extract host without port for mysqldump
        $hostParts = explode(':', $host);
        $mysqldumpHost = $hostParts[0];
        $port = isset($hostParts[1]) ? $hostParts[1] : '3306';
        
        // Create backup using mysqldump
        $command = sprintf(
            'mysqldump --no-tablespaces -h %s -P %s -u %s -p%s %s > %s',
            escapeshellarg($mysqldumpHost),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($password),
            escapeshellarg($dbName),
            escapeshellarg($filename)
        );
        
        // For security, create a logging version with password masked
        $logCommand = sprintf(
            'mysqldump --no-tablespaces -h %s -P %s -u %s -p***** %s > %s',
            $mysqldumpHost,
            $port,
            $username,
            $dbName,
            $filename
        );
        
        // Execute backup command
        $output = [];
        $returnVar = 0;
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new Exception("Backup command execution failed with code: $returnVar");
        }
        
        // Log successful backup
        $logMessage = "Database backup created successfully: " . $filename;
        error_log($logMessage);
        
        // Record maintenance activity
        $activityStmt = $db->prepare("INSERT INTO maintenance_log 
            (user_id, action, details, execution_time, ip_address) 
            VALUES (:user_id, 'backup', :details, :exec_time, :ip)");
            
        $activityStmt->execute([
            ':user_id' => $_SESSION['user_id'],
            ':details' => $filename,
            ':exec_time' => date('d/m/Y H:i:s'),
            ':ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        return [
            'status' => 'success',
            'message' => 'Database backup created successfully',
            'filename' => basename($filename)
        ];
    } catch (Exception $e) {
        error_log("Database backup error: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'Failed to create database backup',
            'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Backup operation failed'
        ];
    }
}

/**
 * Get system statistics
 * 
 * @param PDO $db Database connection
 * @return array System statistics
 */
function getSystemStats($db) {
    try {
        $stats = [];
        
        // Database size
        $sizeQuery = $db->query("SELECT table_schema AS 'Database', 
                                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
                                FROM information_schema.tables 
                                GROUP BY table_schema");
        $stats['database_size'] = $sizeQuery->fetchAll(PDO::FETCH_ASSOC);
        
        // Tables count
        $tableQuery = $db->query("SELECT COUNT(*) as table_count FROM information_schema.tables 
                                 WHERE table_schema = DATABASE()");
        $stats['table_count'] = $tableQuery->fetch(PDO::FETCH_ASSOC)['table_count'];
        
        // Users count
        $userQuery = $db->query("SELECT COUNT(*) as user_count FROM users");
        $stats['user_count'] = $userQuery->fetch(PDO::FETCH_ASSOC)['user_count'];
        
        // Server information (sanitized for security)
        $stats['php_version'] = phpversion();
        $stats['server_software'] = isset($_SERVER['SERVER_SOFTWARE']) ? 
                                   preg_replace('/[^a-zA-Z0-9\._\/ -]/', '', $_SERVER['SERVER_SOFTWARE']) : 
                                   'Unknown';
        
        // Last maintenance operations
        $maintQuery = $db->query("SELECT action, execution_time, tables_affected 
                                 FROM maintenance_log 
                                 ORDER BY execution_time DESC 
                                 LIMIT 10");
        $stats['recent_maintenance'] = $maintQuery->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'status' => 'success',
            'data' => $stats
        ];
    } catch (PDOException $e) {
        error_log("Error fetching system stats: " . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'Failed to retrieve system statistics',
            'error' => defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Database error occurred'
        ];
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $response = ['status' => 'error', 'message' => 'Invalid action'];
    
    // Find this section in your code around line 490
switch ($_POST['action']) {
    case 'get_tables':
        $response = ['status' => 'success', 'tables' => getDatabaseTables($db)];
        break;
        
    case 'optimize_tables':
        if (isset($_POST['tables']) && is_array($_POST['tables'])) {
            $response = optimizeDatabaseTables($db, $_POST['tables']);
        } else {
            $response = ['status' => 'error', 'message' => 'No tables selected'];
        }
        break;
        
    case 'repair_tables':
        if (isset($_POST['tables']) && is_array($_POST['tables'])) {
            $response = repairDatabaseTables($db, $_POST['tables']);
        } else {
            $response = ['status' => 'error', 'message' => 'No tables selected'];
        }
        break;
        
    case 'create_backup':
        $backupPath = dirname(__DIR__) . '/backups';
        $response = createDatabaseBackup($db, $backupPath);
        break;
        
    case 'get_stats':
        $response = getSystemStats($db);
        break;
    
	case 'get_backups':
    $backupPath = dirname(__DIR__) . '/backups';
    $response = [
        'status' => 'success', 
        'backups' => getRecentBackups($backupPath)
    ];
    break;	
    // Add this case inside the switch statement
    case 'reset_database':
        // Double-check that user is admin as this is dangerous
        if (isAdmin($db, $_SESSION['user_id'])) {
            $response = resetDatabase($db);
        } else {
            $response = ['status' => 'error', 'message' => 'Unauthorized access'];
        }
        break;
}
    
    echo json_encode($response);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Maintenance</title>
    <style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --primary-light: #dbeafe;
        --secondary: #8b5cf6;
        --success: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-400: #9ca3af;
        --gray-500: #6b7280;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
        --white: #ffffff;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        --radius: 0.375rem;
    }

    * {
        box-sizing: border-box;
        margin: 0;
        padding: 0;
    }
    
    body {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        background-color: var(--gray-50);
        color: var(--gray-800);
        line-height: 1.6;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 1.5rem;
    }
    
    h1 {
        color: var(--gray-900);
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 1.5rem;
        position: relative;
        padding-bottom: 0.75rem;
    }
    
    h1:after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 80px;
        height: 4px;
        background: var(--primary);
        border-radius: 2px;
    }
    
    h2 {
        color: var(--gray-800);
        font-size: 1.5rem;
        font-weight: 600;
        margin-bottom: 1rem;
    }
    
    h3 {
        color: var(--gray-700);
        font-size: 1.25rem;
        font-weight: 600;
        margin: 1.5rem 0 1rem;
    }
    
    .card {
        background-color: var(--white);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        margin-bottom: 1.5rem;
        padding: 1.5rem;
        transition: box-shadow 0.2s ease-in-out;
        border: 1px solid var(--gray-200);
    }
    
    .card:hover {
        box-shadow: var(--shadow-md);
    }
    
    .card h2 {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        position: relative;
    }
    
    .card h2::before {
        content: '';
        display: block;
        width: 0.5rem;
        height: 1.5rem;
        background-color: var(--primary);
        border-radius: 0.25rem;
    }
    
    p {
        margin-bottom: 0.75rem;
        color: var(--gray-700);
    }
    
    p strong {
        color: var(--gray-800);
        font-weight: 600;
    }
    
    .success {
        color: var(--success);
        font-weight: 600;
    }
    
    button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
        font-weight: 500;
        line-height: 1.5;
        border-radius: var(--radius);
        border: none;
        transition: all 0.15s ease-in-out;
        cursor: pointer;
        color: var(--white);
        background-color: var(--primary);
        box-shadow: var(--shadow-sm);
        gap: 0.5rem;
        margin-right: 0.5rem;
        margin-top: 0.75rem;
        margin-bottom: 0.75rem;
    }
    
    button:hover {
        background-color: var(--primary-dark);
        box-shadow: var(--shadow);
        transform: translateY(-1px);
    }
    
    button:focus {
        outline: 2px solid var(--primary-light);
        outline-offset: 2px;
    }
    
    button:active {
        transform: translateY(0);
        box-shadow: var(--shadow-sm);
    }
    
    button#repairBtn {
        background-color: var(--warning);
    }
    
    button#repairBtn:hover {
        background-color: #e67e22;
    }
    
    button#backupBtn {
        background-color: var(--secondary);
    }
    
    button#backupBtn:hover {
        background-color: #7950f2;
    }
    
    button:disabled {
        background-color: var(--gray-400);
        cursor: not-allowed;
        transform: none;
    }

    .select-all {
        margin-bottom: 1rem;
        padding: 0.75rem;
        background: var(--gray-100);
        border-radius: var(--radius);
        display: flex;
        align-items: center;
    }
    
    .select-all label {
        display: flex;
        align-items: center;
        font-weight: 500;
        color: var(--gray-700);
        cursor: pointer;
    }
    
    .select-all input[type="checkbox"] {
        margin-right: 0.5rem;
        width: 1rem;
        height: 1rem;
    }
    
    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        margin-bottom: 1.25rem;
        border-radius: var(--radius);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }
    
    table thead {
        background-color: var(--gray-100);
    }
    
    table th {
        padding: 0.75rem 1rem;
        font-weight: 600;
        text-align: left;
        color: var(--gray-700);
        border-bottom: 2px solid var(--gray-200);
        white-space: nowrap;
    }
    
    table td {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid var(--gray-200);
        color: var(--gray-600);
    }
    
    table tr:last-child td {
        border-bottom: none;
    }
    
    table tr:nth-child(even) {
        background-color: var(--gray-50);
    }
    
    table tr:hover {
        background-color: var(--gray-100);
    }
    
    .progress-bar {
        height: 0.5rem;
        background-color: var(--gray-200);
        border-radius: 9999px;
        margin: 1rem 0;
        overflow: hidden;
    }
    
    .progress {
        height: 100%;
        background: linear-gradient(90deg, var(--success), #34d399);
        border-radius: 9999px;
        transition: width 0.3s ease;
        width: 0%;
    }
    
    .action-result {
        margin: 1rem 0;
        padding: 1rem;
        border-radius: var(--radius);
        font-size: 0.875rem;
        display: none;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .result-success {
        background-color: #d1fae5;
        color: #065f46;
        border: 1px solid #a7f3d0;
    }
    
    .result-error {
        background-color: #fee2e2;
        color: #991b1b;
        border: 1px solid #fecaca;
    }
    
    input[type="checkbox"] {
        appearance: none;
        -webkit-appearance: none;
        width: 1.125rem;
        height: 1.125rem;
        border: 1.5px solid var(--gray-300);
        border-radius: 0.25rem;
        margin-right: 0.5rem;
        position: relative;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
        vertical-align: middle;
    }
    
    input[type="checkbox"]:checked {
        background-color: var(--primary);
        border-color: var(--primary);
    }
    
    input[type="checkbox"]:checked::after {
        content: '✓';
        position: absolute;
        color: white;
        font-size: 0.75rem;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        font-weight: bold;
    }
    
    input[type="checkbox"]:focus {
        outline: 2px solid var(--primary-light);
        outline-offset: 2px;
    }
    
    #statistics {
        padding-top: 0.5rem;
    }
    
    #tableList {
        max-height: 300px;
        overflow-y: auto;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius);
        margin-bottom: 1rem;
        background-color: var(--white);
    }
    
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    
    /* Status indicators */
    .status-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
    }
    
    .status-indicator {
        width: 0.75rem;
        height: 0.75rem;
        border-radius: 50%;
        margin-right: 0.5rem;
    }
    
    .status-active {
        background-color: var(--success);
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }
    
    /* Responsive styles */
    @media (max-width: 768px) {
        .container {
            padding: 1rem;
        }
        
        h1 {
            font-size: 1.75rem;
        }
        
        h2 {
            font-size: 1.375rem;
        }
        
        .card {
            padding: 1.25rem;
        }
        
        .actions {
            flex-direction: column;
            align-items: stretch;
        }
        
        button {
            width: 100%;
            margin-right: 0;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <h1>Database Maintenance Tool</h1>
        
        <div class="card">
    <h2>System Status</h2>
    <div class="status-item">
        <span class="status-indicator status-active"></span>
        <p><strong>Server Status:</strong> Online</p>
    </div>
    <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
    <p><strong>Database Connection:</strong> <span class="success">Active</span></p>
    <p><strong>Server Time (UK):</strong> <?php echo date('d/m/Y H:i:s'); ?></p>
    <p><strong>Last Maintenance:</strong> <span id="lastMaintenance">Loading...</span></p>
    <p><strong>Current User:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Unknown'); ?></p>
</div>
        <div class="card">
    <h2>Database Reset</h2>
    <div class="alert-danger" style="padding: 1rem; margin-bottom: 1rem; border-radius: var(--radius);">
        <strong>⚠️ Warning:</strong> This is a destructive operation that will empty all tables in the database except admin users.
        This action cannot be undone. All data will be permanently deleted.
    </div>
    <p>Use this tool only when you need to completely reset your database to a clean state while preserving admin accounts.</p>
    <button type="button" id="resetDbBtn" class="btn-danger" style="background-color: var(--danger);">
        Reset Database
    </button>
    <div class="progress-bar">
        <div class="progress" id="resetProgress"></div>
    </div>
    <div class="action-result" id="resetResult"></div>
</div>

<!-- Confirmation Modal -->
<div id="confirmModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7);">
    <div style="background-color: white; margin: 15% auto; padding: 20px; border-radius: var(--radius); max-width: 500px; box-shadow: var(--shadow-lg);">
        <h3 style="color: var(--danger);">⚠️ Confirm Database Reset</h3>
        <p>This will permanently delete all data in your database except admin users. This action CANNOT be undone.</p>
        <p style="font-weight: bold; margin: 1rem 0;">Type "RESET" to confirm:</p>
        <input type="text" id="confirmText" style="width: 100%; padding: 0.5rem; margin-bottom: 1rem; border: 1px solid var(--gray-300); border-radius: var(--radius);">
        <div style="display: flex; justify-content: flex-end; gap: 1rem;">
            <button id="cancelReset" style="background-color: var(--gray-500);">Cancel</button>
            <button id="confirmReset" style="background-color: var(--danger);">Reset Database</button>
        </div>
    </div>
</div>
        <div class="card">
            <h2>Table Management</h2>
            <div class="select-all">
                <label><input type="checkbox" id="selectAllTables"> Select/Deselect All Tables</label>
            </div>
            <form id="tablesForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div id="tableList">
                    <p>Loading tables...</p>
                </div>
                <div class="actions">
    <button type="button" id="optimizeBtn">
        Optimize Selected Tables
    </button>
    <button type="button" id="repairBtn">
        Repair Selected Tables
    </button>
</div>

                <div class="progress-bar">
                    <div class="progress" id="tableProgress"></div>
                </div>
                <div class="action-result" id="tableActionResult"></div>
            </form>
        </div>
        
        <div class="card">
            <h2>Database Backup</h2>
            <p>Create a full backup of the database. This process may take a few minutes for large databases.</p>
            <button type="button" id="backupBtn">
    Create Database Backup
</button>
            <div class="progress-bar">
                <div class="progress" id="backupProgress"></div>
            </div>
            <div class="action-result" id="backupResult"></div>
            <div id="backupsList">
                <h3>Recent Backups</h3>
                <p>Loading backups...</p>
            </div>
        </div>
        
        <div class="card">
            <h2>System Statistics</h2>
            <button type="button" id="statsBtn">
    Refresh Statistics
</button>
            <div id="statistics">
                <p>Loading statistics...</p>
            </div>
        </div>
    </div>
    
    <script>
        // JavaScript for database maintenance operations
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize
            fetchTables();
            fetchStats();
			fetchBackups();
            setupEventListeners();
            
            function setupEventListeners() {
                // Select/deselect all tables
                document.getElementById('selectAllTables').addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('#tableList input[type="checkbox"]');
                    for (let i = 0; i < checkboxes.length; i++) {
                        checkboxes[i].checked = this.checked;
                    }
                });
                
                // Handle optimize button
                document.getElementById('optimizeBtn').addEventListener('click', function() {
                    const selectedTables = getSelectedTables();
                    if (selectedTables.length === 0) {
                        showResult('tableActionResult', 'Please select at least one table to optimize.', false);
                        return;
                    }
                    
                    optimizeTables(selectedTables);
                });
                
                // Handle repair button
                document.getElementById('repairBtn').addEventListener('click', function() {
                    const selectedTables = getSelectedTables();
                    if (selectedTables.length === 0) {
                        showResult('tableActionResult', 'Please select at least one table to repair.', false);
                        return;
                    }
                    
                    repairTables(selectedTables);
                });
                
                // Handle backup button
                document.getElementById('backupBtn').addEventListener('click', function() {
                    createBackup();
                });
                
                // Handle stats refresh button
                document.getElementById('statsBtn').addEventListener('click', function() {
                    fetchStats();
                });
            }
            
            function getSelectedTables() {
                const checkboxes = document.querySelectorAll('#tableList input[type="checkbox"]:checked');
                const tables = [];
                
                for (let i = 0; i < checkboxes.length; i++) {
                    tables.push(checkboxes[i].value);
                }
                
                return tables;
            }
            
            function fetchTables() {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'maintenance.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (this.readyState === 4) {
                        if (this.status === 200) {
                            try {
                                const response = JSON.parse(this.responseText);
                                if (response.status === 'success') {
                                    renderTableList(response.tables);
                                } else {
                                    document.getElementById('tableList').innerHTML = 
                                        '<p class="error">Error loading tables: ' + response.message + '</p>';
                                }
                            } catch (e) {
                                document.getElementById('tableList').innerHTML = 
                                    '<p class="error">Error parsing response from server.</p>';
                            }
                        } else {
                            document.getElementById('tableList').innerHTML = 
                                '<p class="error">Error: Server returned status ' + this.status + '</p>';
                        }
                    }
                };
                xhr.send('action=get_tables&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
            }
            
            function renderTableList(tables) {
                if (!tables || tables.length === 0) {
                    document.getElementById('tableList').innerHTML = '<p>No tables found.</p>';
                    return;
                }
                
                let html = '<table>';
                html += '<thead><tr><th>Select</th><th>Table Name</th></tr></thead><tbody>';
                
                for (let i = 0; i < tables.length; i++) {
                    html += '<tr>';
                    html += '<td><input type="checkbox" name="tables[]" value="' + tables[i] + '"></td>';
                    html += '<td>' + tables[i] + '</td>';
                    html += '</tr>';
                }
                
                html += '</tbody></table>';
                document.getElementById('tableList').innerHTML = html;
            }
            
            function optimizeTables(tables) {
                showProgress('tableProgress', 10);
                showResult('tableActionResult', 'Optimizing tables...', true);
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'maintenance.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (this.readyState === 4) {
                        if (this.status === 200) {
                            try {
                                showProgress('tableProgress', 100);
                                const response = JSON.parse(this.responseText);
                                if (response.status === 'success') {
                                    showResult('tableActionResult', 'Tables optimized successfully: ' + response.details, true);
                                    fetchStats(); // Refresh stats after optimization
                                } else {
                                    showResult('tableActionResult', 'Error optimizing tables: ' + response.message, false);
                                }
                            } catch (e) {
                                showResult('tableActionResult', 'Error parsing response from server.', false);
                            }
                        } else {
                            showProgress('tableProgress', 0);
                            showResult('tableActionResult', 'Error: Server returned status ' + this.status, false);
                        }
                    } else if (this.readyState === 3) {
                        showProgress('tableProgress', 60);
                    } else if (this.readyState === 2) {
                        showProgress('tableProgress', 30);
                    }
                };
                
                let formData = 'action=optimize_tables&csrf_token=' + document.querySelector('input[name="csrf_token"]').value;
                for (let i = 0; i < tables.length; i++) {
                    formData += '&tables[]=' + encodeURIComponent(tables[i]);
                }
                
                xhr.send(formData);
            }
            
            function repairTables(tables) {
                showProgress('tableProgress', 10);
                showResult('tableActionResult', 'Repairing tables...', true);
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'maintenance.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (this.readyState === 4) {
                        if (this.status === 200) {
                            try {
                                showProgress('tableProgress', 100);
                                const response = JSON.parse(this.responseText);
                                if (response.status === 'success') {
                                    showResult('tableActionResult', 'Tables repaired successfully: ' + response.details, true);
                                    fetchStats(); // Refresh stats after repair
                                } else {
                                    showResult('tableActionResult', 'Error repairing tables: ' + response.message, false);
                                }
                            } catch (e) {
                                showResult('tableActionResult', 'Error parsing response from server.', false);
                            }
                        } else {
                            showProgress('tableProgress', 0);
                            showResult('tableActionResult', 'Error: Server returned status ' + this.status, false);
                        }
                    } else if (this.readyState === 3) {
                        showProgress('tableProgress', 60);
                    } else if (this.readyState === 2) {
                        showProgress('tableProgress', 30);
                    }
                };
                
                let formData = 'action=repair_tables&csrf_token=' + document.querySelector('input[name="csrf_token"]').value;
                for (let i = 0; i < tables.length; i++) {
                    formData += '&tables[]=' + encodeURIComponent(tables[i]);
                }
                
                xhr.send(formData);
            }
            
            function createBackup() {
                showProgress('backupProgress', 10);
                showResult('backupResult', 'Creating backup...', true);
                document.getElementById('backupBtn').disabled = true;
                
                const xhr = new XMLHttpRequest();
                xhr.open('POST', 'maintenance.php', true);
                xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                xhr.onreadystatechange = function() {
                    if (this.readyState === 4) {
                        document.getElementById('backupBtn').disabled = false;
                        if (this.status === 200) {
                            try {
                                showProgress('backupProgress', 100);
                                const response = JSON.parse(this.responseText);
                                if (response.status === 'success') {
                                    showResult('backupResult', 'Backup created successfully: ' + response.filename, true);
                                    fetchStats(); // Refresh stats after backup
									fetchBackups();
                                } else {
                                    showResult('backupResult', 'Error creating backup: ' + response.message, false);
                                }
                            } catch (e) {
                                showResult('backupResult', 'Error parsing response from server.', false);
                            }
                        } else {
                            showProgress('backupProgress', 0);
                            showResult('backupResult', 'Error: Server returned status ' + this.status, false);
                        }
                    } else if (this.readyState === 3) {
                        showProgress('backupProgress', 60);
                    } else if (this.readyState === 2) {
                        showProgress('backupProgress', 30);
                    }
                };
                
                xhr.send('action=create_backup&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
            }
            
            function fetchStats() {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'maintenance.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                if (response.status === 'success') {
                    renderStatistics(response.data);
                    
                    // Update last maintenance info if available
                    if (response.data.recent_maintenance && response.data.recent_maintenance.length > 0) {
                        const lastAction = response.data.recent_maintenance[0];
                        document.getElementById('lastMaintenance').textContent = 
                            lastAction.action + ' at ' + lastAction.execution_time;
                    }
                } else {
                    document.getElementById('statistics').innerHTML = 
                        '<p class="error">Error loading statistics: ' + response.message + '</p>';
                }
            } catch (e) {
                document.getElementById('statistics').innerHTML = 
                    '<p class="error">Error parsing statistics data.</p>';
            }
        }
    };
    xhr.send('action=get_stats&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

function fetchBackups() {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'maintenance.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            try {
                const response = JSON.parse(this.responseText);
                if (response.status === 'success') {
                    renderBackupsList(response.backups);
                } else {
                    document.getElementById('backupsList').innerHTML = 
                        '<h3>Recent Backups</h3><p class="error">Error loading backups</p>';
                }
            } catch (e) {
                document.getElementById('backupsList').innerHTML = 
                    '<h3>Recent Backups</h3><p class="error">Error parsing backup data</p>';
            }
        }
    };
    xhr.send('action=get_backups&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

function renderBackupsList(backups) {
    let html = '<h3>Recent Backups</h3>';
    
    if (!backups || backups.length === 0) {
        html += '<p>No backups found</p>';
    } else {
        html += '<table><thead><tr><th>Filename</th><th>Size</th><th>Date</th></tr></thead><tbody>';
        for (let i = 0; i < backups.length; i++) {
            const backup = backups[i];
            const sizeInMB = (backup.size / (1024 * 1024)).toFixed(2);
            html += '<tr>';
            html += '<td>' + backup.filename + '</td>';
            html += '<td>' + sizeInMB + ' MB</td>';
            html += '<td>' + backup.date + '</td>';
            html += '</tr>';
        }
        html += '</tbody></table>';
    }
    
    document.getElementById('backupsList').innerHTML = html;
}

            function renderStatistics(stats) {
                let html = '';
                
                // User count
                html += '<p><strong>Total Users:</strong> ' + stats.user_count + '</p>';
                
                // Table count
                html += '<p><strong>Total Tables:</strong> ' + stats.table_count + '</p>';
                
                // Database size
                html += '<h3>Database Size:</h3>';
                html += '<table><thead><tr><th>Database</th><th>Size (MB)</th></tr></thead><tbody>';
                for (let i = 0; i < stats.database_size.length; i++) {
                    html += '<tr><td>' + stats.database_size[i].Database + '</td><td>' + 
                            stats.database_size[i]['Size (MB)'] + '</td></tr>';
                }
                html += '</tbody></table>';
                
                // Recent maintenance
                if (stats.recent_maintenance && stats.recent_maintenance.length > 0) {
                    html += '<h3>Recent Maintenance Operations:</h3>';
                    html += '<table><thead><tr><th>Action</th><th>Time</th><th>Tables</th></tr></thead><tbody>';
                    for (let i = 0; i < stats.recent_maintenance.length; i++) {
                        const action = stats.recent_maintenance[i];
                        html += '<tr><td>' + action.action + '</td><td>' + 
                                action.execution_time + '</td><td>' + 
                                (action.tables_affected || 'N/A') + '</td></tr>';
                    }
                    html += '</tbody></table>';
                }
                
                // Server info
                html += '<h3>Server Information:</h3>';
                html += '<p><strong>PHP Version:</strong> ' + stats.php_version + '</p>';
                html += '<p><strong>Server Software:</strong> ' + stats.server_software + '</p>';
                
                document.getElementById('statistics').innerHTML = html;
            }
            
            function showProgress(elementId, percentage) {
                const progressElement = document.getElementById(elementId);
                progressElement.style.width = percentage + '%';
            }
            
            function showResult(elementId, message, isSuccess) {
                const resultElement = document.getElementById(elementId);
                resultElement.innerHTML = message;
                resultElement.style.display = 'block';
                
                if (isSuccess) {
                    resultElement.className = 'action-result result-success';
                } else {
                    resultElement.className = 'action-result result-error';
                }
            }
        });
		
document.getElementById('resetDbBtn').addEventListener('click', function() {
    // Show confirmation modal
    document.getElementById('confirmModal').style.display = 'block';
    document.getElementById('confirmText').value = '';
    document.getElementById('confirmReset').disabled = true;
    
    // Focus on input
    setTimeout(() => document.getElementById('confirmText').focus(), 100);
});

document.getElementById('confirmText').addEventListener('input', function() {
    // Only enable reset button if "RESET" is typed correctly
    document.getElementById('confirmReset').disabled = (this.value !== 'RESET');
});

document.getElementById('cancelReset').addEventListener('click', function() {
    document.getElementById('confirmModal').style.display = 'none';
});

document.getElementById('confirmReset').addEventListener('click', function() {
    // Hide modal
    document.getElementById('confirmModal').style.display = 'none';
    
    // Show progress
    showProgress('resetProgress', 10);
    showResult('resetResult', 'Resetting database...', true);
    
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'maintenance.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onreadystatechange = function() {
        if (this.readyState === 4) {
            if (this.status === 200) {
                try {
                    showProgress('resetProgress', 100);
                    const response = JSON.parse(this.responseText);
                    if (response.status === 'success') {
                        let details = '';
                        if (response.details) {
                            details = '<br><br><strong>Tables emptied:</strong> ' + 
                                      response.details.emptied_tables.join(', ') +
                                      '<br><strong>Admin users preserved:</strong> ' + 
                                      response.details.preserved_admins.join(', ');
                        }
                        showResult('resetResult', 'Database reset completed successfully.' + details, true);
                        fetchStats(); // Refresh stats after reset
                    } else {
                        showResult('resetResult', 'Error resetting database: ' + response.message, false);
                    }
                } catch (e) {
                    showResult('resetResult', 'Error parsing response from server.', false);
                }
            } else {
                showProgress('resetProgress', 0);
                showResult('resetResult', 'Error: Server returned status ' + this.status, false);
            }
        } else if (this.readyState === 3) {
            showProgress('resetProgress', 60);
        } else if (this.readyState === 2) {
            showProgress('resetProgress', 30);
        }
    };
    
    xhr.send('action=reset_database&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
});

// Define global versions of the utility functions
function showProgress(elementId, percentage) {
    const progressElement = document.getElementById(elementId);
    if (progressElement) {
        progressElement.style.width = percentage + '%';
    }
}

function showResult(elementId, message, isSuccess) {
    const resultElement = document.getElementById(elementId);
    if (resultElement) {
        resultElement.innerHTML = message;
        resultElement.style.display = 'block';
        
        if (isSuccess) {
            resultElement.className = 'action-result result-success';
        } else {
            resultElement.className = 'action-result result-error';
        }
    }
}
    </script>
</body>
</html>