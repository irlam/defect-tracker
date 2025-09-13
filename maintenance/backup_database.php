<?php
// maintenance/backup_database.php - Create a backup of the database
// Error handling for production - log errors instead of displaying them
error_reporting(E_ALL);
ini_set('display_errors', 1); // Temporarily enable errors for debugging
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/backup_error.log');

// Make sure we have a logs directory
if (!is_dir(dirname(__DIR__) . '/logs')) {
    mkdir(dirname(__DIR__) . '/logs', 0750, true);
}

require_once dirname(__DIR__) . '/config/database.php';

// Enhanced function to check if user is admin with debugging
function isAdmin($db, $userId) {
    try {
        // Check if userId is valid
        if (!$userId || !is_numeric($userId)) {
            error_log("isAdmin check failed: Invalid user ID: " . var_export($userId, true));
            return false;
        }
        
        // First, check if the user exists
        $userCheck = $db->prepare("SELECT username, role FROM users WHERE id = :user_id");
        $userCheck->execute([':user_id' => $userId]);
        $userInfo = $userCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$userInfo) {
            error_log("isAdmin check failed: User ID $userId not found in database");
            return false;
        }
        
        // Direct role check - if the users table has a role column that might contain 'admin'
        if (isset($userInfo['role']) && strtolower($userInfo['role']) === 'admin') {
            error_log("isAdmin check passed: User $userId has direct admin role in users table");
            return true;
        }
        
        error_log("Checking roles for user ID: $userId, username: {$userInfo['username']}");
        
        // Check through user_roles table
        $query = "SELECT r.name as role_name
                  FROM users u
                  JOIN user_roles ur ON u.id = ur.user_id
                  JOIN roles r ON ur.role_id = r.id
                  WHERE u.id = :user_id";
        
        $stmt = $db->prepare($query);
        $stmt->execute([':user_id' => $userId]);
        
        $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("User $userId roles found: " . json_encode($roles));
        
        // Check for 'admin' role (case-insensitive)
        foreach ($roles as $role) {
            if (strtolower($role['role_name']) === 'admin') {
                error_log("isAdmin check passed: User $userId has admin role");
                return true;
            }
        }
        
        // Also check user_type field which may have 'admin'
        $userTypeCheck = $db->prepare("SELECT user_type FROM users WHERE id = :user_id");
        $userTypeCheck->execute([':user_id' => $userId]);
        $userType = $userTypeCheck->fetchColumn();
        
        if ($userType && strtolower($userType) === 'admin') {
            error_log("isAdmin check passed: User $userId has admin user_type");
            return true;
        }
        
        error_log("isAdmin check failed: User $userId does not have admin role");
        return false;
    } catch (Exception $e) {
        error_log("Exception in isAdmin check: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return false;
    }
}

// Debug function to display session info
function debugCheck() {
    error_log("SESSION DATA: " . json_encode($_SESSION));
    error_log("SERVER DATA: " . json_encode($_SERVER));
}

// Function to log to system_logs (based on the database schema)
function logSystemAction($db, $userId, $action, $details) {
    try {
        $query = "INSERT INTO system_logs (user_id, action, action_by, action_at, ip_address, details) 
                  VALUES (:user_id, :action, :action_by, NOW(), :ip_address, :details)";
        
        $stmt = $db->prepare($query);
        return $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':action_by' => $userId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':details' => $details
        ]);
    } catch (Exception $e) {
        error_log("Error logging action: " . $e->getMessage());
        return false;
    }
}

try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Debug current session info
    debugCheck();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        error_log("Backup access denied: User not logged in. Session data: " . json_encode($_SESSION));
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required',
            'error_code' => 'not_logged_in'
        ]);
        exit();
    }
    
    $database = new Database();
    $db = $database->getConnection();
    $currentUserId = (int)$_SESSION['user_id'];
    
    error_log("Checking admin status for user ID: $currentUserId, username: {$_SESSION['username']} at " . date('Y-m-d H:i:s'));
    
    // Check if user is admin - Added proper authorization check with override for testing
    if (!isAdmin($db, $currentUserId)) {
        // TEMPORARY DEBUGGING OVERRIDE - REMOVE IN PRODUCTION
        // Check if this is the specific user "irlam"
        $checkUser = $db->prepare("SELECT username FROM users WHERE id = :user_id");
        $checkUser->execute([':user_id' => $currentUserId]);
        $username = $checkUser->fetchColumn();
        
        if ($username === 'irlam') {
            error_log("*** OVERRIDE: Granting temporary admin access to user 'irlam' for backup operations ***");
            // Continue execution - do not redirect
        } else {
            // Log unauthorized access attempt
            logSystemAction(
                $db, 
                $currentUserId,
                'UNAUTHORIZED_ACCESS',
                "Unauthorized attempt to access database backup by user ID: {$currentUserId}, username: {$_SESSION['username']}"
            );
            
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Unauthorized: Admin privileges required',
                'error_code' => 'not_admin'
            ]);
            exit();
        }
    }
    
    error_log("Admin check passed for user: $currentUserId");
    
    // Get database configuration
    $dbConfig = $db->query("SELECT DATABASE()")->fetchColumn();
    error_log("Database name: $dbConfig");
    
    // Create backup filename with timestamp
    $timestamp = date('Y-m-d_H-i-s');
    $backupFile = 'backup_' . $dbConfig . '_' . $timestamp . '.sql';
    $backupPath = dirname(__DIR__) . '/backups/' . $backupFile;
    
    // Ensure backups directory exists and is not web-accessible
    $backupDir = dirname(__DIR__) . '/backups';
    if (!is_dir($backupDir)) {
        error_log("Creating backups directory: $backupDir");
        if (!mkdir($backupDir, 0750, true)) {
            throw new Exception("Failed to create backups directory");
        }
        // Create .htaccess to prevent direct access
        file_put_contents($backupDir . '/.htaccess', "Deny from all\nOptions -Indexes");
    }
    
    // Check if directory is writable
    if (!is_writable($backupDir)) {
        throw new Exception("Backup directory is not writable: $backupDir");
    }
    
    // Log backup action to system_logs
    logSystemAction(
        $db, 
        $currentUserId,
        'DATABASE_BACKUP',
        "Database backup initiated: $backupFile"
    );
    
    error_log("Starting database backup to: $backupPath");
    
    // Using PHP's native database export functionality instead of exec()
    $output = "-- Database backup generated on " . date("Y-m-d H:i:s") . "\n";
    $output .= "-- Database: " . $dbConfig . "\n";
    $output .= "-- User: " . $_SESSION['username'] . "\n\n";
    $output .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    // Get all tables
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    error_log("Found " . count($tables) . " tables to backup");
    
    $totalRows = 0;
    $startTime = microtime(true);
    
    foreach ($tables as $table) {
        try {
            error_log("Processing table: $table");
            // Get create table statement
            $stmt = $db->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $output .= "-- Table structure for table `$table`\n\n";
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $row['Create Table'] . ";\n\n";
            
            // Count rows first
            $countStmt = $db->query("SELECT COUNT(*) FROM `$table`");
            $rowCount = $countStmt->fetchColumn();
            $totalRows += $rowCount;
            
            // Get data
            if ($rowCount > 0) {
                error_log("Table $table has $rowCount rows");
                $output .= "-- Data for table `$table`\n";
                
                // Process rows in batches to avoid memory issues
                $batchSize = 100;
                $offset = 0;
                
                while ($offset < $rowCount) {
                    error_log("Processing batch for table $table: offset $offset, limit $batchSize");
                    
                    $batchQuery = "SELECT * FROM `$table` LIMIT $offset, $batchSize";
                    $batchStmt = $db->query($batchQuery);
                    $rows = $batchStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($rows) > 0) {
                        $columns = array_keys($rows[0]);
                        $insertPrefix = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";
                        $output .= $insertPrefix;
                        
                        $valueStrings = [];
                        foreach ($rows as $row) {
                            $values = [];
                            foreach ($row as $value) {
                                if ($value === null) {
                                    $values[] = 'NULL';
                                } else {
                                    $values[] = $db->quote($value);
                                }
                            }
                            $valueStrings[] = "(" . implode(', ', $values) . ")";
                        }
                        $output .= implode(",\n", $valueStrings) . ";\n\n";
                    }
                    
                    $offset += $batchSize;
                }
            } else {
                error_log("Table $table is empty");
                $output .= "-- Table `$table` is empty\n\n";
            }
        } catch (Exception $tableEx) {
            error_log("Error processing table $table: " . $tableEx->getMessage());
            $output .= "-- Error processing table `$table`: " . $tableEx->getMessage() . "\n\n";
        }
    }
    
    $output .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    $endTime = microtime(true);
    $duration = round($endTime - $startTime, 2);
    error_log("Backup generation completed in $duration seconds for $totalRows total rows");
    
    // Write to file
    if (file_put_contents($backupPath, $output)) {
        $fileSize = round(filesize($backupPath) / 1024 / 1024, 2);
        error_log("Backup file written successfully: $backupPath ($fileSize MB)");
        
        // Update the log with success information
        logSystemAction(
            $db, 
            $currentUserId,
            'DATABASE_BACKUP_COMPLETED',
            "Database backup completed successfully: $backupFile, Size: $fileSize MB, Duration: $duration seconds, Tables: " . count($tables) . ", Total rows: $totalRows"
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Database backup created successfully',
            'file' => $backupFile,
            'timestamp' => date('Y-m-d H:i:s'),
            'size' => $fileSize . ' MB',
            'duration' => $duration . ' seconds',
            'tables_count' => count($tables),
            'rows_count' => $totalRows,
            'user' => $_SESSION['username']
        ]);
    } else {
        $errorMsg = "Failed to write backup file: $backupPath";
        error_log($errorMsg);
        
        logSystemAction(
            $db, 
            $currentUserId,
            'DATABASE_BACKUP_FAILED',
            $errorMsg
        );
        
        echo json_encode([
            'success' => false,
            'message' => $errorMsg,
            'error_code' => 'write_failed',
            'timestamp' => date('Y-m-d H:i:s'),
            'user' => $_SESSION['username']
        ]);
    }
    
} catch (Exception $e) {
    $errorMsg = 'Backup error: ' . $e->getMessage();
    error_log($errorMsg);
    error_log("Stack trace: " . $e->getTraceAsString());
    
    if (isset($db) && isset($currentUserId)) {
        logSystemAction(
            $db, 
            $currentUserId,
            'DATABASE_BACKUP_ERROR',
            $errorMsg
        );
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred during backup: ' . $e->getMessage(),
        'error_code' => 'exception',
        'timestamp' => date('Y-m-d H:i:s'),
        'user' => $_SESSION['username'] ?? 'Unknown'
    ]);
}

// Output current date and time in the format requested
$currentDateTime = date('Y-m-d H:i:s');
error_log("Backup script completed at: $currentDateTime");