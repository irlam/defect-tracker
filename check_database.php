<?php
// check_database.php
// Script to verify database setup
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');


require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Check if tables exist
    $required_tables = ['users', 'projects', 'floor_plans', 'defects', 'defect_images', 'comments'];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (!empty($missing_tables)) {
        echo "Missing tables: " . implode(", ", $missing_tables);
        echo "\nPlease run the database.sql script to create the required tables.";
    } else {
        echo "Database setup is correct.";
    }
    
} catch (PDOException $e) {
    echo "Database connection error: " . $e->getMessage();
}
?>