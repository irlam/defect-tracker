<?php
/**
 * Database Migration Runner
 * Applies the notification enhancement migration
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database configuration
require_once __DIR__ . '/../../config/database.php';

echo "==================================\n";
echo "Running Notification Enhancements Migration\n";
echo "==================================\n\n";

try {
    // Connect to database
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Connected to database successfully.\n\n";
    
    // Read migration file
    $migrationFile = __DIR__ . '/add_notification_enhancements.sql';
    if (!file_exists($migrationFile)) {
        throw new Exception("Migration file not found: $migrationFile");
    }
    
    $sql = file_get_contents($migrationFile);
    echo "Loaded migration SQL.\n\n";
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && substr($stmt, 0, 2) !== '--';
        }
    );
    
    echo "Found " . count($statements) . " SQL statements to execute.\n\n";
    
    // Execute each statement
    $successCount = 0;
    $errorCount = 0;
    
    foreach ($statements as $index => $statement) {
        echo "Executing statement " . ($index + 1) . "...\n";
        
        try {
            $db->exec($statement);
            echo "✓ Success\n\n";
            $successCount++;
        } catch (PDOException $e) {
            // Check if error is because column/table already exists
            $errorCode = $e->getCode();
            $errorMsg = $e->getMessage();
            
            if (strpos($errorMsg, 'Duplicate column') !== false || 
                strpos($errorMsg, 'already exists') !== false ||
                $errorCode == '42S21' || $errorCode == '42S01') {
                echo "⚠ Skipped (already exists)\n\n";
                $successCount++;
            } else {
                echo "✗ Error: " . $e->getMessage() . "\n\n";
                $errorCount++;
            }
        }
    }
    
    echo "==================================\n";
    echo "Migration Complete\n";
    echo "==================================\n";
    echo "Successful: $successCount\n";
    echo "Errors: $errorCount\n";
    
    if ($errorCount === 0) {
        echo "\n✓ All migrations applied successfully!\n";
    } else {
        echo "\n⚠ Some migrations had errors. Please review the output above.\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
