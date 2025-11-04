<?php
/**
 * Website Cleanup Script
 * 
 * This script cleans all user-generated data from the website while preserving:
 * - Admin user account (irlam)
 * - System configuration and settings
 * - Database structure
 * 
 * After running this script, the website will be in a fresh state ready for new projects.
 * This is useful for creating a clean backup template.
 * 
 * IMPORTANT: This script will DELETE all:
 * - Defects and related data
 * - Projects
 * - Contractors (except those tied to admin)
 * - User-generated files (uploads, floor plans)
 * - Non-admin users
 * - Logs and notifications
 * 
 * Created: 2025-11-04
 * Author: GitHub Copilot
 */

// Only allow execution from command line or admin session
if (php_sapi_name() !== 'cli') {
    // Running from web - check for session
    if (!isset($_SESSION['executing_cleanup'])) {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/session.php';
        
        // Check if user is logged in and is admin
        if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
            die('Unauthorized access. Admin privileges required.');
        }
    }
} else {
    // Running from CLI - load config
    require_once __DIR__ . '/../config/database.php';
}

/**
 * Clean all user-generated data from the database
 */
function cleanDatabase($db) {
    $results = [];
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // 1. Clean defect-related tables
        $tables_to_truncate = [
            'defects',
            'defect_images',
            'defect_comments',
            'defect_history',
            'defect_assignments',
            'activity_logs',
            'acceptance_history'
        ];
        
        foreach ($tables_to_truncate as $table) {
            try {
                $stmt = $db->prepare("TRUNCATE TABLE `$table`");
                $stmt->execute();
                $results[] = "✓ Cleaned table: $table";
            } catch (PDOException $e) {
                // Table might not exist or might be a view
                $results[] = "⚠ Could not truncate $table: " . $e->getMessage();
            }
        }
        
        // 2. Clean projects
        $stmt = $db->prepare("TRUNCATE TABLE `projects`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: projects";
        
        // 3. Clean floor plans
        $stmt = $db->prepare("TRUNCATE TABLE `floor_plans`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: floor_plans";
        
        // 4. Clean categories (optional - these might be system data)
        // Uncomment if you want to clean categories as well
        // $stmt = $db->prepare("TRUNCATE TABLE `categories`");
        // $stmt->execute();
        // $results[] = "✓ Cleaned table: categories";
        
        // 5. Clean contractors except those tied to admin user
        // Keep contractors that might be needed for demo/testing
        $stmt = $db->prepare("DELETE FROM `contractors` WHERE id NOT IN (SELECT DISTINCT contractor_id FROM users WHERE contractor_id IS NOT NULL AND user_type = 'admin')");
        $stmt->execute();
        $deletedContractors = $stmt->rowCount();
        $results[] = "✓ Deleted $deletedContractors contractors (preserved admin-linked contractors)";
        
        // 6. Clean users except admin
        // Keep only the admin user (irlam with ID 22)
        $stmt = $db->prepare("DELETE FROM `users` WHERE user_type != 'admin' OR username != 'irlam'");
        $stmt->execute();
        $deletedUsers = $stmt->rowCount();
        $results[] = "✓ Deleted $deletedUsers non-admin users";
        
        // 7. Clean user-related tables
        $stmt = $db->prepare("DELETE FROM `user_logs` WHERE user_id NOT IN (SELECT id FROM users)");
        $stmt->execute();
        $results[] = "✓ Cleaned orphaned user logs";
        
        $stmt = $db->prepare("DELETE FROM `user_sessions` WHERE user_id NOT IN (SELECT id FROM users)");
        $stmt->execute();
        $results[] = "✓ Cleaned orphaned user sessions";
        
        $stmt = $db->prepare("DELETE FROM `user_permissions` WHERE user_id NOT IN (SELECT id FROM users)");
        $stmt->execute();
        $results[] = "✓ Cleaned orphaned user permissions";
        
        $stmt = $db->prepare("DELETE FROM `user_roles` WHERE user_id NOT IN (SELECT id FROM users)");
        $stmt->execute();
        $results[] = "✓ Cleaned orphaned user roles";
        
        $stmt = $db->prepare("DELETE FROM `user_recent_descriptions` WHERE user_id NOT IN (SELECT id FROM users)");
        $stmt->execute();
        $results[] = "✓ Cleaned orphaned user recent descriptions";
        
        // 8. Clean notifications
        $stmt = $db->prepare("TRUNCATE TABLE `notifications`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: notifications";
        
        $stmt = $db->prepare("TRUNCATE TABLE `notification_log`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: notification_log";
        
        // 9. Clean logs (keep system logs structure but remove old entries)
        $stmt = $db->prepare("TRUNCATE TABLE `system_logs`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: system_logs";
        
        $stmt = $db->prepare("TRUNCATE TABLE `action_log`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: action_log";
        
        $stmt = $db->prepare("TRUNCATE TABLE `audit_logs`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: audit_logs";
        
        $stmt = $db->prepare("TRUNCATE TABLE `export_logs`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: export_logs";
        
        $stmt = $db->prepare("TRUNCATE TABLE `maintenance_log`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: maintenance_log";
        
        // 10. Clean comments
        $stmt = $db->prepare("TRUNCATE TABLE `comments`");
        $stmt->execute();
        $results[] = "✓ Cleaned table: comments";
        
        // 11. Clean sync-related tables
        $sync_tables = [
            'sync_conflicts',
            'sync_devices',
            'sync_logs',
            'sync_queue'
        ];
        
        foreach ($sync_tables as $table) {
            try {
                $stmt = $db->prepare("TRUNCATE TABLE `$table`");
                $stmt->execute();
                $results[] = "✓ Cleaned table: $table";
            } catch (PDOException $e) {
                $results[] = "⚠ Could not truncate $table: " . $e->getMessage();
            }
        }
        
        // Commit transaction
        $db->commit();
        $results[] = "\n✓ Database cleanup completed successfully!";
        
        return [
            'success' => true,
            'results' => $results
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        $db->rollBack();
        $results[] = "\n✗ Error during cleanup: " . $e->getMessage();
        return [
            'success' => false,
            'results' => $results
        ];
    }
}

/**
 * Clean uploaded files and floor plans
 */
function cleanUploadedFiles() {
    $results = [];
    $baseDir = __DIR__ . '/..';
    
    // Directories to clean
    $directories = [
        $baseDir . '/uploads',
        $baseDir . '/assets/floor_plans',
        $baseDir . '/pdf_exports'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            $results[] = "⚠ Directory does not exist: $dir";
            continue;
        }
        
        $files = glob($dir . '/*');
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                // Skip .gitkeep and .htaccess files
                if (basename($file) === '.gitkeep' || basename($file) === '.htaccess') {
                    continue;
                }
                
                if (unlink($file)) {
                    $count++;
                }
            }
        }
        
        $results[] = "✓ Deleted $count files from " . basename($dir);
    }
    
    return [
        'success' => true,
        'results' => $results
    ];
}

/**
 * Main execution
 */
function executeCleanup() {
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  WEBSITE CLEANUP SCRIPT\n";
    echo str_repeat('=', 70) . "\n\n";
    
    echo "WARNING: This will delete ALL user-generated data!\n";
    echo "Only the admin account (irlam) and system configuration will be preserved.\n\n";
    
    // If running from CLI, ask for confirmation
    if (php_sapi_name() === 'cli') {
        echo "Are you sure you want to continue? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $line = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($line) !== 'yes') {
            echo "\nCleanup cancelled.\n";
            return;
        }
    }
    
    echo "\nStarting cleanup...\n\n";
    
    // Initialize database connection
    try {
        $database = new Database();
        $db = $database->getConnection();
    } catch (Exception $e) {
        echo "✗ Database connection failed: " . $e->getMessage() . "\n";
        return;
    }
    
    // Step 1: Clean database
    echo "Step 1: Cleaning database...\n";
    echo str_repeat('-', 70) . "\n";
    $dbResult = cleanDatabase($db);
    foreach ($dbResult['results'] as $result) {
        echo $result . "\n";
    }
    
    if (!$dbResult['success']) {
        echo "\n✗ Database cleanup failed. Aborting.\n";
        return;
    }
    
    // Step 2: Clean uploaded files
    echo "\n\nStep 2: Cleaning uploaded files...\n";
    echo str_repeat('-', 70) . "\n";
    $filesResult = cleanUploadedFiles();
    foreach ($filesResult['results'] as $result) {
        echo $result . "\n";
    }
    
    // Summary
    echo "\n" . str_repeat('=', 70) . "\n";
    echo "  CLEANUP COMPLETE\n";
    echo str_repeat('=', 70) . "\n";
    echo "\nThe website is now in a fresh state.\n";
    echo "Admin login preserved: username 'irlam'\n";
    echo "\nYou can now create a backup using the backup manager.\n";
    echo "This backup will serve as a clean starting point for new projects.\n\n";
}

// Execute if running from command line or as admin
if (php_sapi_name() === 'cli' || (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin')) {
    executeCleanup();
} else {
    echo "This script must be run from command line or by an admin user.";
}
