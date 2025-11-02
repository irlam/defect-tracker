<?php
/**
 * fix_pin_paths.php
 * Maintenance script to fix incorrect pin image paths in the database
 * Current Date and Time (UTC): 2025-01-30 16:05:00
 * Current User's Login: irlam
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once 'config/database.php';
require_once 'includes/functions.php';

// Define constants for URLs and paths
define('SITE_URL', 'https://defects.dvntracker.site');
define('UPLOAD_PATH', 'uploads/pins/');
define('FULL_UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/' . UPLOAD_PATH);

// Initialize counters
$totalDefects = 0;
$updatedDefects = 0;
$errors = [];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Start transaction
    $db->beginTransaction();

    // Get all defects with pin images
    $stmt = $db->query("
        SELECT id, pin_image_path 
        FROM defects 
        WHERE pin_image_path IS NOT NULL 
        AND deleted_at IS NULL
    ");

    echo "Starting pin path fix process...\n";
    echo "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    echo "----------------------------------------\n";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $totalDefects++;
        $defectId = $row['id'];
        $oldPath = $row['pin_image_path'];
        
        // Skip if path is already correct
        if (strpos($oldPath, UPLOAD_PATH) === 0) {
            echo "Defect #$defectId: Path already correct - $oldPath\n";
            continue;
        }

        try {
            // Extract filename from old path
            $filename = basename($oldPath);
            
            // Construct new path
            $newPath = UPLOAD_PATH . $filename;
            
            // Check if file exists in old location
            $oldFullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($oldPath, '/');
            $newFullPath = FULL_UPLOAD_PATH . $filename;

            // Move file if it exists and destinations are different
            if (file_exists($oldFullPath) && $oldFullPath !== $newFullPath) {
                if (!is_dir(FULL_UPLOAD_PATH)) {
                    mkdir(FULL_UPLOAD_PATH, 0755, true);
                }
                
                if (!rename($oldFullPath, $newFullPath)) {
                    throw new Exception("Failed to move file from $oldFullPath to $newFullPath");
                }
            }

            // Update database path
            $updateStmt = $db->prepare("
                UPDATE defects 
                SET 
                    pin_image_path = :new_path,
                    updated_at = UTC_TIMESTAMP()
                WHERE id = :id
            ");

            $updateStmt->execute([
                ':new_path' => $newPath,
                ':id' => $defectId
            ]);

            // Log the change
            $logStmt = $db->prepare("
                INSERT INTO defect_history (
                    defect_id, 
                    description, 
                    created_at,
                    updated_by
                ) VALUES (
                    :defect_id,
                    :description,
                    UTC_TIMESTAMP(),
                    'system'
                )
            ");

            $logStmt->execute([
                ':defect_id' => $defectId,
                ':description' => "Pin image path updated from '$oldPath' to '$newPath'"
            ]);

            $updatedDefects++;
            echo "Defect #$defectId: Updated path from '$oldPath' to '$newPath'\n";

        } catch (Exception $e) {
            $errors[] = "Error processing defect #$defectId: " . $e->getMessage();
            error_log("Error in fix_pin_paths.php for defect #$defectId: " . $e->getMessage());
        }
    }

    // Commit transaction if no errors
    if (empty($errors)) {
        $db->commit();
        echo "\nSuccess! All changes committed.\n";
    } else {
        $db->rollBack();
        echo "\nErrors occurred. Rolling back changes.\n";
        foreach ($errors as $error) {
            echo "- $error\n";
        }
    }

    // Print summary
    echo "\n----------------------------------------\n";
    echo "Summary:\n";
    echo "Total defects processed: $totalDefects\n";
    echo "Updated defects: $updatedDefects\n";
    echo "Errors encountered: " . count($errors) . "\n";
    echo "Time completed: " . date('Y-m-d H:i:s') . "\n";

} catch (Exception $e) {
    echo "Critical error: " . $e->getMessage() . "\n";
    error_log("Critical error in fix_pin_paths.php: " . $e->getMessage());
    
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
        echo "Transaction rolled back.\n";
    }
}

// Create verification report
try {
    $report = fopen('pin_path_fix_report_' . date('Ymd_His') . '.txt', 'w');
    fwrite($report, "Pin Path Fix Report\n");
    fwrite($report, "Generated: " . date('Y-m-d H:i:s') . "\n");
    fwrite($report, "----------------------------------------\n\n");
    
    // Check all paths after fix
    $verifyStmt = $db->query("
        SELECT id, pin_image_path 
        FROM defects 
        WHERE pin_image_path IS NOT NULL 
        AND deleted_at IS NULL
    ");

    $incorrectPaths = 0;
    $missingFiles = 0;

    while ($row = $verifyStmt->fetch(PDO::FETCH_ASSOC)) {
        $defectId = $row['id'];
        $path = $row['pin_image_path'];
        
        // Check path format
        if (strpos($path, UPLOAD_PATH) !== 0) {
            fwrite($report, "Incorrect path format for defect #$defectId: $path\n");
            $incorrectPaths++;
        }
        
        // Check file existence
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
        if (!file_exists($fullPath)) {
            fwrite($report, "Missing file for defect #$defectId: $fullPath\n");
            $missingFiles++;
        }
    }

    fwrite($report, "\n----------------------------------------\n");
    fwrite($report, "Verification Results:\n");
    fwrite($report, "Incorrect paths found: $incorrectPaths\n");
    fwrite($report, "Missing files found: $missingFiles\n");
    
    fclose($report);
    
    echo "\nVerification report generated.\n";

} catch (Exception $e) {
    echo "Error generating verification report: " . $e->getMessage() . "\n";
    error_log("Error generating verification report in fix_pin_paths.php: " . $e->getMessage());
}