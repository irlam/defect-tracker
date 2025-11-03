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
require_once dirname(__DIR__) . '/includes/navbar.php';

if (!defined('DEBUG_MODE')) {
    define('DEBUG_MODE', false);
}

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

date_default_timezone_set('Europe/London');

$pageTitle = 'Maintenance Console';
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $_SESSION['username'] ?? 'User'));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'User'));
$currentTimestamp = date('d/m/Y H:i');
$navbar = null;
$error_message = '';

try {
    $navbar = new Navbar($db, (int) ($_SESSION['user_id'] ?? 0), $_SESSION['username'] ?? '');
} catch (Throwable $navbarError) {
    error_log('Navbar initialisation error on maintenance.php: ' . $navbarError->getMessage());
    $navbar = null;
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

function formatMaintenanceActionLabel(string $action): string
{
    $clean = str_replace(['_', '-'], ' ', strtolower($action));
    return ucwords($clean);
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

$initialStatsData = null;
$recentBackups = [];
$tableCountRaw = 0;
$userCountRaw = 0;
$databaseFootprintTotal = 0.0;
$databaseFootprintSummary = '—';
$lastMaintenanceLabel = 'No maintenance events recorded';
$lastMaintenanceValue = '—';
$lastMaintenanceActionLabel = 'No maintenance operations logged yet.';
$phpVersion = phpversion();
$serverSoftware = isset($_SERVER['SERVER_SOFTWARE'])
    ? preg_replace('/[^a-zA-Z0-9\._\/ -]/', '', $_SERVER['SERVER_SOFTWARE'])
    : 'Unknown';

$initialStatsResponse = getSystemStats($db);
if (($initialStatsResponse['status'] ?? '') === 'success' && !empty($initialStatsResponse['data'])) {
    $initialStatsData = $initialStatsResponse['data'];
    $tableCountRaw = (int) ($initialStatsData['table_count'] ?? 0);
    $userCountRaw = (int) ($initialStatsData['user_count'] ?? 0);
    $phpVersion = $initialStatsData['php_version'] ?? $phpVersion;
    $serverSoftware = $initialStatsData['server_software'] ?? $serverSoftware;

    if (!empty($initialStatsData['database_size']) && is_array($initialStatsData['database_size'])) {
        foreach ($initialStatsData['database_size'] as $databaseRow) {
            $databaseFootprintTotal += (float) ($databaseRow['Size (MB)'] ?? 0);
        }
    }

    if (!empty($initialStatsData['recent_maintenance'][0])) {
        $lastAction = $initialStatsData['recent_maintenance'][0];
        $actionLabel = formatMaintenanceActionLabel((string) ($lastAction['action'] ?? ''));
        $actionTimeRaw = $lastAction['execution_time'] ?? '';
        $actionTimeDisplay = !empty($actionTimeRaw) ? date('d M Y, H:i', strtotime($actionTimeRaw)) : '—';

        $lastMaintenanceLabel = trim($actionLabel . ' • ' . ($actionTimeRaw ?: '')) ?: 'No maintenance events recorded';
        $lastMaintenanceValue = $actionTimeDisplay;
        $lastMaintenanceActionLabel = $actionLabel ?: 'Maintenance activity logged';
    }
}

if ($databaseFootprintTotal > 0) {
    $databaseFootprintSummary = number_format($databaseFootprintTotal, 2) . ' MB';
}

$recentBackups = getRecentBackups(dirname(__DIR__) . '/backups');

$heroMetrics = [
    [
        'icon' => 'bx-server',
        'tag' => 'Status',
        'title' => 'Platform Health',
         'value' => 'Online',
        'description' => 'Application and database connections healthy.'
    ],
    [
        'icon' => 'bx-table',
        'tag' => 'Maintenance Metric',
        'title' => 'Tables in Scope',
        'value' => $tableCountRaw > 0 ? number_format($tableCountRaw) : '—',
        'description' => 'Structures in active schema.',
        'stat_key' => 'tables'
    ],
    [
        'icon' => 'bx-group',
        'tag' => 'Maintenance Metric',
        'title' => 'Registered Users',
        'value' => $userCountRaw > 0 ? number_format($userCountRaw) : '—',
        'description' => 'Accounts with system access.',
        'stat_key' => 'users'
    ],
    [
        'icon' => 'bx-history',
        'tag' => 'Maintenance Log',
        'title' => 'Last Maintenance',
        'value' => $lastMaintenanceValue,
        'description' => $lastMaintenanceActionLabel,
        'stat_key' => 'last'
    ],
];

$maintenanceBackupsMarkup = (function () use ($recentBackups) {
    ob_start();
    if (!empty($recentBackups)) {
        echo '<div class="table-responsive"><table class="table table-dark table-hover table-sm align-middle mb-0">';
        echo '<thead><tr><th scope="col">Filename</th><th scope="col" class="text-end">Size</th><th scope="col" class="text-end">Created</th></tr></thead><tbody>';
        foreach ($recentBackups as $backup) {
            $sizeMb = ($backup['size'] ?? 0) / (1024 * 1024);
            echo '<tr>';
            echo '<td class="text-break">' . htmlspecialchars($backup['filename'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td class="text-end">' . number_format($sizeMb, 2) . ' MB</td>';
            echo '<td class="text-end">' . htmlspecialchars($backup['date'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p class="maintenance-empty mb-0">No backups found.</p>';
    }
    return ob_get_clean();
})();

$maintenanceSummaryMarkup = (function () use ($initialStatsData, $userCountRaw, $tableCountRaw, $databaseFootprintSummary, $phpVersion, $serverSoftware) {
    ob_start();
    if ($initialStatsData) {
        echo '<div class="maintenance-stat-summaries">';
        echo '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Total Users</span><span class="maintenance-stat-summaries__value">' . ($userCountRaw > 0 ? number_format($userCountRaw) : '—') . '</span></div>';
        echo '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Total Tables</span><span class="maintenance-stat-summaries__value">' . ($tableCountRaw > 0 ? number_format($tableCountRaw) : '—') . '</span></div>';
        echo '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Footprint</span><span class="maintenance-stat-summaries__value" data-maintenance-footprint>' . htmlspecialchars($databaseFootprintSummary, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '</div>';

        if (!empty($initialStatsData['recent_maintenance'])) {
            echo '<ul class="list-unstyled mt-3 mb-0 text-muted small maintenance-summary-timeline">';
            foreach (array_slice($initialStatsData['recent_maintenance'], 0, 3) as $row) {
                $actionLabel = formatMaintenanceActionLabel((string) ($row['action'] ?? ''));
                $timeDisplay = !empty($row['execution_time']) ? date('d M Y, H:i', strtotime($row['execution_time'])) : '—';
                echo '<li class="d-flex justify-content-between gap-3"><span>' . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</span><span class="text-nowrap">' . htmlspecialchars($timeDisplay, ENT_QUOTES, 'UTF-8') . '</span></li>';
            }
            echo '</ul>';
        }

        echo '<div class="maintenance-stat-meta mt-3">';
        echo '<div><span class="maintenance-stat-meta__label">PHP Version</span><span class="maintenance-stat-meta__value">' . htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '<div><span class="maintenance-stat-meta__label">Server Software</span><span class="maintenance-stat-meta__value">' . htmlspecialchars($serverSoftware, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '</div>';
    } else {
        echo '<p class="maintenance-empty mb-0">Statistics currently unavailable.</p>';
    }
    return ob_get_clean();
})();

$maintenanceDetailMarkup = (function () use ($initialStatsData, $userCountRaw, $tableCountRaw, $databaseFootprintSummary, $phpVersion, $serverSoftware) {
    ob_start();
    if ($initialStatsData) {
        echo '<div class="maintenance-stat-summaries">';
        echo '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Total Users</span><span class="maintenance-stat-summaries__value">' . ($userCountRaw > 0 ? number_format($userCountRaw) : '—') . '</span></div>';
        echo '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Total Tables</span><span class="maintenance-stat-summaries__value">' . ($tableCountRaw > 0 ? number_format($tableCountRaw) : '—') . '</span></div>';
        echo '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Footprint</span><span class="maintenance-stat-summaries__value" data-maintenance-footprint>' . htmlspecialchars($databaseFootprintSummary, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '</div>';

        if (!empty($initialStatsData['database_size'])) {
            echo '<div class="table-responsive mt-3"><table class="table table-dark table-hover table-sm align-middle mb-0">';
            echo '<thead><tr><th scope="col">Database</th><th scope="col" class="text-end">Size (MB)</th></tr></thead><tbody>';
            foreach ($initialStatsData['database_size'] as $dbRow) {
                echo '<tr><td class="text-break">' . htmlspecialchars($dbRow['Database'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td><td class="text-end">' . htmlspecialchars($dbRow['Size (MB)'] ?? '0', ENT_QUOTES, 'UTF-8') . '</td></tr>';
            }
            echo '</tbody></table></div>';
        }

        if (!empty($initialStatsData['recent_maintenance'])) {
            echo '<h3 class="maintenance-stat-heading mt-4">Recent Maintenance Operations</h3>';
            echo '<div class="table-responsive"><table class="table table-dark table-hover table-sm align-middle mb-0">';
            echo '<thead><tr><th scope="col">Action</th><th scope="col" class="text-end">Time</th><th scope="col" class="text-end">Tables</th></tr></thead><tbody>';
            foreach ($initialStatsData['recent_maintenance'] as $row) {
                $actionLabel = formatMaintenanceActionLabel((string) ($row['action'] ?? ''));
                echo '<tr>';
                echo '<td>' . htmlspecialchars($actionLabel, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td class="text-end">' . htmlspecialchars($row['execution_time'] ?? '—', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td class="text-end">' . htmlspecialchars($row['tables_affected'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table></div>';
        } else {
            echo '<p class="maintenance-empty mb-0 mt-3">No maintenance operations logged yet.</p>';
        }

        echo '<div class="maintenance-stat-meta mt-4">';
        echo '<div><span class="maintenance-stat-meta__label">PHP Version</span><span class="maintenance-stat-meta__value">' . htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '<div><span class="maintenance-stat-meta__label">Server Software</span><span class="maintenance-stat-meta__value">' . htmlspecialchars($serverSoftware, ENT_QUOTES, 'UTF-8') . '</span></div>';
        echo '</div>';
    } else {
        echo '<p class="maintenance-empty mb-0">Statistics currently unavailable.</p>';
    }
    return ob_get_clean();
})();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <meta name="description" content="Maintenance console for database optimisation, backups, and system insights.">
    <meta name="last-modified" content="<?php echo htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="<?php echo BASE_URL; ?>css/app.css?v=20251103" rel="stylesheet">
    <style>
        .maintenance-page {
            display: grid;
            gap: clamp(1.5rem, 2vw, 2.25rem);
        }

        .maintenance-page .system-tool-card__title {
            font-size: clamp(1rem, 2vw, 1.1rem);
        }

        .maintenance-grid {
            display: grid;
            gap: clamp(1.5rem, 2vw, 2rem);
        }

        @media (min-width: 1200px) {
            .maintenance-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        .maintenance-panel {
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(37, 99, 235, 0.16);
            border-radius: var(--border-radius-xl);
            box-shadow: 0 32px 60px rgba(2, 6, 23, 0.55);
            padding: clamp(1.5rem, 3vw, 2.25rem);
            display: flex;
            flex-direction: column;
            gap: clamp(1rem, 2vw, 1.5rem);
        }

        .maintenance-panel__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: var(--spacing-md);
        }

        .maintenance-panel__title {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }

        .maintenance-panel__title h2 {
            margin: 0;
            font-size: clamp(1.1rem, 2vw, 1.25rem);
            font-weight: 600;
        }

        .maintenance-panel__description {
            color: rgba(148, 163, 184, 0.85);
            font-size: 0.9rem;
        }

        .maintenance-panel__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .maintenance-panel__actions .btn {
            min-width: 160px;
        }

        .maintenance-panel__meta {
            display: flex;
            flex-wrap: wrap;
            gap: clamp(0.75rem, 1.5vw, 1.25rem);
        }

        .maintenance-panel__meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: var(--border-radius-md);
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(37, 99, 235, 0.15);
            color: rgba(226, 232, 240, 0.92);
        }

        .maintenance-panel__meta-item i {
            font-size: 1rem;
            color: rgba(96, 165, 250, 0.9);
        }

        .maintenance-table {
            border-radius: var(--border-radius-lg);
            overflow: hidden;
        }

        .maintenance-table table {
            margin-bottom: 0;
        }

        .maintenance-empty {
            color: rgba(148, 163, 184, 0.8);
        }

        .maintenance-progress {
            height: 0.5rem;
            background: rgba(37, 99, 235, 0.18);
            border-radius: 999px;
            overflow: hidden;
        }

        .maintenance-progress__bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, rgba(14, 165, 233, 0.85), rgba(59, 130, 246, 0.95));
            transition: width 0.3s ease;
        }

        .maintenance-result {
            display: none;
            border-radius: var(--border-radius-lg);
            padding: 0.85rem 1rem;
            font-size: 0.85rem;
        }

        .maintenance-result--success {
            background: rgba(22, 163, 74, 0.18);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: rgba(187, 247, 208, 0.95);
        }

        .maintenance-result--error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(248, 113, 113, 0.35);
            color: rgba(254, 226, 226, 0.95);
        }

        .maintenance-stat-summaries {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .maintenance-stat-summaries__item {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            padding: 0.75rem 1rem;
            border-radius: var(--border-radius-md);
            background: rgba(37, 99, 235, 0.12);
            border: 1px solid rgba(37, 99, 235, 0.2);
        }

        .maintenance-stat-summaries__label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(148, 163, 184, 0.85);
        }

        .maintenance-stat-summaries__value {
            font-size: 1.1rem;
            font-weight: 600;
            color: rgba(226, 232, 240, 0.96);
        }

        .maintenance-stat-heading {
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(148, 163, 184, 0.8);
        }

        .maintenance-stat-meta {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .maintenance-stat-meta__label {
            display: block;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(148, 163, 184, 0.75);
            margin-bottom: 0.25rem;
        }

        .maintenance-stat-meta__value {
            font-weight: 500;
            color: rgba(226, 232, 240, 0.92);
        }

        .maintenance-checkbox {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            padding: 0.55rem 0.85rem;
            border-radius: var(--border-radius-md);
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(37, 99, 235, 0.16);
            color: rgba(226, 232, 240, 0.92);
        }

        .maintenance-checkbox input[type="checkbox"] {
            width: 1.05rem;
            height: 1.05rem;
            border-radius: 6px;
            background: transparent;
            border: 1.5px solid rgba(96, 165, 250, 0.45);
        }

        .maintenance-checkbox input[type="checkbox"]:checked {
            background: rgba(37, 99, 235, 0.85);
            border-color: rgba(37, 99, 235, 0.85);
        }

        .maintenance-checkbox input[type="checkbox"]:checked::after {
            content: '\2713';
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            color: var(--white);
            font-weight: 700;
            font-size: 0.65rem;
        }

        .maintenance-table-selector {
            display: grid;
            gap: 0.75rem;
            max-height: 320px;
            overflow: auto;
            padding-right: 0.5rem;
        }

        .maintenance-table-selector::-webkit-scrollbar {
            width: 6px;
        }

        .maintenance-table-selector::-webkit-scrollbar-thumb {
            background: rgba(37, 99, 235, 0.3);
            border-radius: 999px;
        }

        .maintenance-warning {
            background: rgba(244, 114, 182, 0.08);
            border: 1px dashed rgba(248, 113, 113, 0.35);
            border-radius: var(--border-radius-lg);
            padding: clamp(1rem, 1.5vw, 1.25rem);
            color: rgba(254, 226, 226, 0.92);
        }

        .maintenance-modal {
            position: fixed;
            inset: 0;
            background: rgba(2, 6, 23, 0.82);
            display: none;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            z-index: 1050;
        }

        .maintenance-modal__dialog {
            background: rgba(15, 23, 42, 0.95);
            border-radius: var(--border-radius-xl);
            border: 1px solid rgba(37, 99, 235, 0.2);
            box-shadow: 0 28px 60px rgba(2, 6, 23, 0.65);
            max-width: 520px;
            width: 100%;
            padding: clamp(1.5rem, 2vw, 2rem);
            display: grid;
            gap: 1.25rem;
        }

        .maintenance-modal__title {
            font-size: 1.2rem;
            font-weight: 600;
            color: rgba(252, 165, 165, 0.92);
        }

        .maintenance-modal__actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .maintenance-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.25rem 0.6rem;
            border-radius: 999px;
            background: rgba(22, 163, 74, 0.18);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: rgba(187, 247, 208, 0.95);
            font-size: 0.75rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .maintenance-meta-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }

        .maintenance-meta-item {
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .maintenance-meta-item i {
            font-size: 1.2rem;
            color: rgba(96, 165, 250, 0.85);
        }

        .maintenance-meta-item span {
            color: rgba(148, 163, 184, 0.9);
        }

        @media (max-width: 768px) {
            .maintenance-panel__actions {
                width: 100%;
                flex-direction: column;
            }

            .maintenance-panel__actions .btn {
                width: 100%;
            }

            .maintenance-meta-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>
    <div class="app-content-offset"></div>

    <main class="tool-page container-xl py-4 maintenance-page">
        <header class="tool-header mb-4">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Maintenance Console</h1>
                    <p class="text-muted mb-0">Optimise, repair, and safeguard the data platform powering the defect tracker.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-label me-1'></i><?php echo htmlspecialchars($currentUserRoleSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
                </div>
            </div>
        </header>

        <section class="system-tools-grid">
            <?php foreach ($heroMetrics as $metric): ?>
                <article class="system-tool-card">
                    <div class="system-tool-card__icon"><i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i></div>
                    <div class="system-tool-card__body">
                        <span class="system-tool-card__tag system-tool-card__tag--database"><?php echo htmlspecialchars($metric['tag'] ?? 'Metric', ENT_QUOTES, 'UTF-8'); ?></span>
                        <h2 class="system-tool-card__title mb-1"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
                        <p class="system-tool-card__stat mb-0"<?php echo !empty($metric['stat_key']) ? ' data-maintenance-stat="' . htmlspecialchars($metric['stat_key'], ENT_QUOTES, 'UTF-8') . '"' : ''; ?>><?php echo htmlspecialchars($metric['value'], ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="system-tool-card__description mb-0"><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="maintenance-grid">
            <article class="maintenance-panel">
                <div class="maintenance-panel__header">
                    <div class="maintenance-panel__title">
                        <h2><i class='bx bx-health me-2'></i>System Status</h2>
                        <p class="maintenance-panel__description">Live environment telemetry and the latest maintenance timeline.</p>
                    </div>
                    <span class="maintenance-status-chip"><i class='bx bx-pulse'></i>Online</span>
                </div>
                <div class="maintenance-panel__meta">
                    <div class="maintenance-panel__meta-item"><i class='bx bx-chip'></i><span>PHP <?php echo htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="maintenance-panel__meta-item"><i class='bx bx-server'></i><span><?php echo htmlspecialchars($serverSoftware, ENT_QUOTES, 'UTF-8'); ?></span></div>
                    <div class="maintenance-panel__meta-item"><i class='bx bx-calendar-star'></i><span data-maintenance-last><?php echo htmlspecialchars($lastMaintenanceLabel, ENT_QUOTES, 'UTF-8'); ?></span></div>
                </div>
                <div class="maintenance-table" id="systemStatusSummary">
                    <?php echo $maintenanceSummaryMarkup; ?>
                </div>
            </article>

            <article class="maintenance-panel">
                <div class="maintenance-panel__header">
                    <div class="maintenance-panel__title">
                        <h2><i class='bx bx-reset me-2'></i>Database Reset</h2>
                        <p class="maintenance-panel__description">Reset all tables back to a clean state while preserving administrative accounts.</p>
                    </div>
                </div>
                <div class="maintenance-warning">
                    <strong class="d-block mb-2"><i class='bx bx-error-circle me-2'></i>Destructive operation</strong>
                    <p class="mb-0">This permanently deletes all data except administrator accounts. Confirm with the ops lead before continuing.</p>
                </div>
                <div class="maintenance-panel__actions">
                    <button type="button" id="resetDbBtn" class="btn btn-danger"><i class='bx bx-bomb'></i> Reset Database</button>
                </div>
                <div class="maintenance-progress"><div class="maintenance-progress__bar" id="resetProgress"></div></div>
                <div class="maintenance-result" id="resetResult"></div>
            </article>

            <article class="maintenance-panel">
                <div class="maintenance-panel__header">
                    <div class="maintenance-panel__title">
                        <h2><i class='bx bx-table me-2'></i>Table Management</h2>
                        <p class="maintenance-panel__description">Optimise or repair individual tables to reclaim space and resolve storage anomalies.</p>
                    </div>
                </div>
                <div class="maintenance-panel__meta">
                    <div class="maintenance-panel__meta-item"><i class='bx bx-list-check'></i><span>Select the tables to target below.</span></div>
                </div>
                <form id="tablesForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="maintenance-checkbox">
                        <input type="checkbox" id="selectAllTables">
                        <label class="mb-0" for="selectAllTables">Select all tables</label>
                    </div>
                    <div id="tableList" class="maintenance-table-selector">
                        <p class="maintenance-empty mb-0">Loading tables...</p>
                    </div>
                    <div class="maintenance-panel__actions">
                        <button type="button" id="optimizeBtn" class="btn btn-primary"><i class='bx bx-line-chart'></i> Optimise</button>
                        <button type="button" id="repairBtn" class="btn btn-warning"><i class='bx bx-wrench'></i> Repair</button>
                    </div>
                    <div class="maintenance-progress"><div class="maintenance-progress__bar" id="tableProgress"></div></div>
                    <div class="maintenance-result" id="tableActionResult"></div>
                </form>
            </article>

            <article class="maintenance-panel">
                <div class="maintenance-panel__header">
                    <div class="maintenance-panel__title">
                        <h2><i class='bx bx-cloud-upload me-2'></i>Database Backup</h2>
                        <p class="maintenance-panel__description">Capture a full SQL dump for off-site storage and disaster recovery drills.</p>
                    </div>
                </div>
                <div class="maintenance-panel__actions">
                    <button type="button" id="backupBtn" class="btn btn-info text-dark"><i class='bx bx-save'></i> Create Backup</button>
                </div>
                <div class="maintenance-progress"><div class="maintenance-progress__bar" id="backupProgress"></div></div>
                <div class="maintenance-result" id="backupResult"></div>
                <div id="backupsList" class="maintenance-table">
                    <?php echo $maintenanceBackupsMarkup; ?>
                </div>
            </article>

            <article class="maintenance-panel">
                <div class="maintenance-panel__header">
                    <div class="maintenance-panel__title">
                        <h2><i class='bx bx-bar-chart-alt-2 me-2'></i>System Statistics</h2>
                        <p class="maintenance-panel__description">Review infrastructure metrics and historical maintenance operations.</p>
                    </div>
                    <div class="maintenance-panel__actions">
                        <button type="button" id="statsBtn" class="btn btn-outline-light btn-sm"><i class='bx bx-refresh'></i> Refresh</button>
                    </div>
                </div>
                <div id="statistics" class="maintenance-table">
                    <?php echo $maintenanceDetailMarkup; ?>
                </div>
            </article>
        </section>
    </main>

    <section class="maintenance-modal" id="confirmModal" aria-hidden="true" role="dialog">
        <div class="maintenance-modal__dialog">
            <div>
                <h3 class="maintenance-modal__title"><i class='bx bx-error me-1'></i>Confirm Database Reset</h3>
                <p class="text-muted">This will permanently delete application data while preserving administrator accounts. Ensure a backup exists before continuing.</p>
            </div>
            <div>
                <label for="confirmText" class="maintenance-stat-meta__label">Type "RESET" to continue</label>
                <input type="text" id="confirmText" class="form-control" placeholder="RESET">
            </div>
            <div class="maintenance-modal__actions">
                <button type="button" id="cancelReset" class="btn btn-outline-light"><i class='bx bx-x-circle'></i> Cancel</button>
                <button type="button" id="confirmReset" class="btn btn-danger" disabled><i class='bx bx-bomb'></i> Reset Database</button>
            </div>
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript for database maintenance operations
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize
            fetchTables();
            fetchStats();
		fetchBackups();
            setupEventListeners();

            const numberFormatter = new Intl.NumberFormat('en-GB');
            const sizeFormatter = new Intl.NumberFormat('en-GB', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const dateTimeFormatter = new Intl.DateTimeFormat('en-GB', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            function escapeHtml(value) {
                if (value === null || value === undefined) {
                    return '';
                }
                return String(value).replace(/[&<>"']/g, function(char) {
                    const entities = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                    return entities[char] || char;
                });
            }

            function formatNumber(value) {
                const numeric = Number(value);
                if (!Number.isFinite(numeric)) {
                    return '—';
                }
                return numberFormatter.format(numeric);
            }

            function formatSize(value) {
                const numeric = Number(value);
                if (!Number.isFinite(numeric)) {
                    return '—';
                }
                return sizeFormatter.format(numeric);
            }

            function formatDateTime(value) {
                if (!value) {
                    return '—';
                }
                const date = value instanceof Date ? value : new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return '—';
                }
                return dateTimeFormatter.format(date);
            }

            function formatActionLabel(action) {
                if (!action) {
                    return 'Unknown';
                }
                return String(action)
                    .replace(/[_-]+/g, ' ')
                    .replace(/\b\w/g, function(letter) {
                        return letter.toUpperCase();
                    });
            }

            function calculateFootprint(databaseSize) {
                if (!Array.isArray(databaseSize)) {
                    return 0;
                }
                return databaseSize.reduce(function(total, row) {
                    const value = row && (row['Size (MB)'] ?? row.size ?? 0);
                    const numeric = Number(value);
                    return total + (Number.isFinite(numeric) ? numeric : 0);
                }, 0);
            }

            function buildSummaryMarkup(stats, footprintLabel) {
                const totalUsers = formatNumber(stats.user_count);
                const totalTables = formatNumber(stats.table_count);
                let html = '<div class="maintenance-stat-summaries">';
                html += '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Total Users</span><span class="maintenance-stat-summaries__value">' + totalUsers + '</span></div>';
                html += '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Total Tables</span><span class="maintenance-stat-summaries__value">' + totalTables + '</span></div>';
                html += '<div class="maintenance-stat-summaries__item"><span class="maintenance-stat-summaries__label">Footprint</span><span class="maintenance-stat-summaries__value" data-maintenance-footprint>' + escapeHtml(footprintLabel) + '</span></div>';
                html += '</div>';

                if (Array.isArray(stats.recent_maintenance) && stats.recent_maintenance.length > 0) {
                    html += '<ul class="list-unstyled mt-3 mb-0 text-muted small maintenance-summary-timeline">';
                    stats.recent_maintenance.slice(0, 3).forEach(function(entry) {
                        const label = formatActionLabel(entry.action);
                        const time = formatDateTime(entry.execution_time);
                        html += '<li class="d-flex justify-content-between gap-3"><span>' + escapeHtml(label) + '</span><span class="text-nowrap">' + escapeHtml(time) + '</span></li>';
                    });
                    html += '</ul>';
                } else {
                    html += '<p class="maintenance-empty mb-0 mt-3">No maintenance operations logged yet.</p>';
                }

                html += '<div class="maintenance-stat-meta mt-3">';
                html += '<div><span class="maintenance-stat-meta__label">PHP Version</span><span class="maintenance-stat-meta__value">' + escapeHtml(stats.php_version ?? 'Unknown') + '</span></div>';
                html += '<div><span class="maintenance-stat-meta__label">Server Software</span><span class="maintenance-stat-meta__value">' + escapeHtml(stats.server_software ?? 'Unknown') + '</span></div>';
                html += '</div>';

                return html;
            }

            function buildDetailMarkup(stats, footprintLabel) {
                let html = buildSummaryMarkup(stats, footprintLabel);

                if (Array.isArray(stats.database_size) && stats.database_size.length > 0) {
                    html += '<div class="table-responsive mt-3"><table class="table table-dark table-hover table-sm align-middle mb-0">';
                    html += '<thead><tr><th scope="col">Database</th><th scope="col" class="text-end">Size (MB)</th></tr></thead><tbody>';
                    stats.database_size.forEach(function(row) {
                        html += '<tr><td class="text-break">' + escapeHtml(row.Database ?? 'N/A') + '</td><td class="text-end">' + escapeHtml(row['Size (MB)'] ?? '0') + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="maintenance-empty mb-0 mt-3">Database size data unavailable.</p>';
                }

                if (Array.isArray(stats.recent_maintenance) && stats.recent_maintenance.length > 0) {
                    html += '<h3 class="maintenance-stat-heading mt-4">Recent Maintenance Operations</h3>';
                    html += '<div class="table-responsive"><table class="table table-dark table-hover table-sm align-middle mb-0">';
                    html += '<thead><tr><th scope="col">Action</th><th scope="col" class="text-end">Time</th><th scope="col" class="text-end">Tables</th></tr></thead><tbody>';
                    stats.recent_maintenance.forEach(function(entry) {
                        const label = formatActionLabel(entry.action);
                        const time = formatDateTime(entry.execution_time);
                        const tables = entry.tables_affected ? escapeHtml(entry.tables_affected) : 'N/A';
                        html += '<tr><td>' + escapeHtml(label) + '</td><td class="text-end">' + escapeHtml(time) + '</td><td class="text-end">' + tables + '</td></tr>';
                    });
                    html += '</tbody></table></div>';
                } else {
                    html += '<p class="maintenance-empty mb-0 mt-3">No maintenance operations logged yet.</p>';
                }

                return html;
            }

            function updateHeroMetric(statKey, value) {
                if (!statKey) {
                    return;
                }
                const element = document.querySelector('[data-maintenance-stat="' + statKey + '"]');
                if (element) {
                    element.textContent = value;
                }
            }
            
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
                                        '<p class="maintenance-empty mb-0">Error loading tables: ' + escapeHtml(response.message ?? 'Unknown error') + '</p>';
                                }
                            } catch (e) {
                                document.getElementById('tableList').innerHTML = 
                                    '<p class="maintenance-empty mb-0">Error parsing response from server.</p>';
                            }
                        } else {
                            document.getElementById('tableList').innerHTML = 
                                '<p class="maintenance-empty mb-0">Error: Server returned status ' + escapeHtml(this.status) + '</p>';
                        }
                    }
                };
                xhr.send('action=get_tables&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
            }
            
            function renderTableList(tables) {
                const container = document.getElementById('tableList');
                if (!container) {
                    return;
                }

                if (!tables || tables.length === 0) {
                    container.innerHTML = '<p class="maintenance-empty mb-0">No tables found.</p>';
                    return;
                }

                let html = '';
                for (let i = 0; i < tables.length; i++) {
                    const tableName = escapeHtml(String(tables[i]));
                    html += '<label class="maintenance-checkbox">';
                    html += '<input type="checkbox" name="tables[]" value="' + tableName + '">';
                    html += '<span>' + tableName + '</span>';
                    html += '</label>';
                }

                container.innerHTML = html;
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
                    if (this.readyState === 4) {
                        const statisticsContainer = document.getElementById('statistics');
                        const summaryContainer = document.getElementById('systemStatusSummary');

                        if (this.status === 200) {
                            try {
                                const response = JSON.parse(this.responseText);
                                if (response.status === 'success') {
                                    renderStatistics(response.data);
                                } else {
                                    const message = '<p class="maintenance-empty mb-0">Error loading statistics: ' + escapeHtml(response.message ?? 'Unknown error') + '</p>';
                                    if (statisticsContainer) statisticsContainer.innerHTML = message;
                                    if (summaryContainer) summaryContainer.innerHTML = message;
                                }
                            } catch (e) {
                                const message = '<p class="maintenance-empty mb-0">Error parsing statistics data.</p>';
                                if (statisticsContainer) statisticsContainer.innerHTML = message;
                                if (summaryContainer) summaryContainer.innerHTML = message;
                            }
                        } else {
                            const message = '<p class="maintenance-empty mb-0">Error loading statistics (status ' + escapeHtml(this.status) + ').</p>';
                            if (statisticsContainer) statisticsContainer.innerHTML = message;
                            if (summaryContainer) summaryContainer.innerHTML = message;
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
        if (this.readyState === 4) {
            const container = document.getElementById('backupsList');
            if (!container) {
                return;
            }

            if (this.status === 200) {
                try {
                    const response = JSON.parse(this.responseText);
                    if (response.status === 'success') {
                        renderBackupsList(response.backups);
                    } else {
                        container.innerHTML = '<p class="maintenance-empty mb-0">Error loading backups.</p>';
                    }
                } catch (e) {
                    container.innerHTML = '<p class="maintenance-empty mb-0">Error parsing backup data.</p>';
                }
            } else {
                container.innerHTML = '<p class="maintenance-empty mb-0">Error loading backups (status ' + escapeHtml(this.status) + ').</p>';
            }
        }
    };
    xhr.send('action=get_backups&csrf_token=' + document.querySelector('input[name="csrf_token"]').value);
}

function renderBackupsList(backups) {
    const container = document.getElementById('backupsList');
    if (!container) {
        return;
    }

    if (!Array.isArray(backups) || backups.length === 0) {
        container.innerHTML = '<p class="maintenance-empty mb-0">No backups found.</p>';
        return;
    }

    let html = '<div class="table-responsive"><table class="table table-dark table-hover table-sm align-middle mb-0">';
    html += '<thead><tr><th scope="col">Filename</th><th scope="col" class="text-end">Size</th><th scope="col" class="text-end">Created</th></tr></thead><tbody>';
    backups.forEach(function(backup) {
        const sizeInMb = backup && backup.size ? backup.size / (1024 * 1024) : 0;
        const sizeLabel = formatSize(sizeInMb);
        html += '<tr>';
        html += '<td class="text-break">' + escapeHtml(backup.filename ?? 'Unknown') + '</td>';
        html += '<td class="text-end">' + (sizeLabel === '—' ? '—' : sizeLabel + ' MB') + '</td>';
        html += '<td class="text-end">' + escapeHtml(backup.date ?? '') + '</td>';
        html += '</tr>';
    });
    html += '</tbody></table></div>';

    container.innerHTML = html;
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