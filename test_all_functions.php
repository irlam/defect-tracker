<?php
// test_all_functions.php
// Comprehensive test script for all system functions

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Defect Tracker System Function Tests ===\n\n";

// Use test database for testing
require_once 'config/test_database.php';

$results = [];
$errors = [];

function testResult($testName, $success, $message = '') {
    global $results, $errors;
    $results[$testName] = $success;
    $status = $success ? "✓ PASS" : "✗ FAIL";
    echo sprintf("%-50s %s", $testName, $status);
    if ($message) {
        echo " - " . $message;
    }
    if (!$success) {
        $errors[] = "$testName: $message";
    }
    echo "\n";
}

// Test 1: Database Connection
echo "Phase 1: Database Connection Tests\n";
echo "=" . str_repeat("=", 50) . "\n";

try {
    $database = new TestDatabase();
    $db = $database->getConnection();
    if ($db) {
        testResult("Database Connection", true, "SQLite test database connected");
        
        // Test basic query
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        testResult("Database Query", true, "Found $userCount users");
        
        // Test table existence
        $requiredTables = ['users', 'projects', 'floor_plans', 'defects', 'defect_images', 'comments'];
        foreach ($requiredTables as $table) {
            $stmt = $db->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$table'");
            $exists = $stmt->fetchColumn() > 0;
            testResult("Table: $table", $exists);
        }
    } else {
        testResult("Database Connection", false, "Failed to connect");
    }
} catch (Exception $e) {
    testResult("Database Connection", false, $e->getMessage());
}

echo "\nPhase 2: Core Class Tests\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test 2: Authentication Class
try {
    if (file_exists('classes/Auth.php')) {
        require_once 'classes/Auth.php';
        testResult("Auth Class File", true, "File exists");
        
        if (class_exists('Auth')) {
            testResult("Auth Class", true, "Class loaded");
            
            // Test instantiation
            $auth = new Auth($db);
            testResult("Auth Instantiation", true);
            
        } else {
            testResult("Auth Class", false, "Class not found");
        }
    } else {
        testResult("Auth Class File", false, "File not found");
    }
} catch (Exception $e) {
    testResult("Auth Class", false, $e->getMessage());
}

// Test 3: RBAC Class
try {
    if (file_exists('classes/RBAC.php')) {
        require_once 'classes/RBAC.php';
        testResult("RBAC Class File", true);
        
        if (class_exists('RBAC')) {
            testResult("RBAC Class", true);
            
            $rbac = new RBAC($db);
            testResult("RBAC Instantiation", true);
        } else {
            testResult("RBAC Class", false, "Class not found");
        }
    } else {
        testResult("RBAC Class File", false, "File not found");
    }
} catch (Exception $e) {
    testResult("RBAC Class", false, $e->getMessage());
}

// Test 4: Logger Class
try {
    if (file_exists('classes/Logger.php')) {
        require_once 'classes/Logger.php';
        testResult("Logger Class File", true);
        
        if (class_exists('Logger')) {
            testResult("Logger Class", true);
        } else {
            testResult("Logger Class", false, "Class not found");
        }
    } else {
        testResult("Logger Class File", false, "File not found");
    }
} catch (Exception $e) {
    testResult("Logger Class", false, $e->getMessage());
}

// Test 5: EmailService Class
try {
    if (file_exists('classes/EmailService.php')) {
        require_once 'classes/EmailService.php';
        testResult("EmailService Class File", true);
        
        if (class_exists('EmailService')) {
            testResult("EmailService Class", true);
        } else {
            testResult("EmailService Class", false, "Class not found");
        }
    } else {
        testResult("EmailService Class File", false, "File not found");
    }
} catch (Exception $e) {
    testResult("EmailService Class", false, $e->getMessage());
}

echo "\nPhase 3: Main PHP Script Tests\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test main PHP scripts for syntax errors
$mainScripts = [
    'login.php',
    'dashboard.php',
    'create_defect.php',
    'view_defect.php',
    'edit_defect.php',
    'projects.php',
    'user_management.php',
    'contractors.php'
];

foreach ($mainScripts as $script) {
    if (file_exists($script)) {
        testResult("Script: $script", true, "File exists");
        
        // Test syntax
        $output = shell_exec("php -l $script 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        testResult("Syntax: $script", $syntaxOk, $syntaxOk ? '' : trim($output));
    } else {
        testResult("Script: $script", false, "File not found");
    }
}

echo "\nPhase 4: API Endpoint Tests\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test API endpoints
$apiEndpoints = glob('api/*.php');
foreach ($apiEndpoints as $api) {
    $basename = basename($api);
    
    if (file_exists($api)) {
        testResult("API: $basename", true, "File exists");
        
        // Test syntax
        $output = shell_exec("php -l $api 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        testResult("API Syntax: $basename", $syntaxOk, $syntaxOk ? '' : trim($output));
    } else {
        testResult("API: $basename", false, "File not found");
    }
}

echo "\nPhase 5: Image Processing Tests\n";
echo "=" . str_repeat("=", 50) . "\n";

// Test GD extension
$gdEnabled = extension_loaded('gd');
testResult("GD Extension", $gdEnabled);

if ($gdEnabled) {
    $gdInfo = gd_info();
    testResult("JPEG Support", $gdInfo['JPEG Support'] ?? false);
    testResult("PNG Support", $gdInfo['PNG Support'] ?? false);
    testResult("WebP Support", $gdInfo['WebP Support'] ?? false);
}

// Test ImageMagick
$imagickEnabled = extension_loaded('imagick');
testResult("ImageMagick Extension", $imagickEnabled);

// Test image processing scripts
$imageScripts = [
    'processDefectImages.php',
    'upload_completed_images.php',
    'upload_floor_plan.php'
];

foreach ($imageScripts as $script) {
    if (file_exists($script)) {
        testResult("Image Script: $script", true, "File exists");
        
        $output = shell_exec("php -l $script 2>&1");
        $syntaxOk = strpos($output, 'No syntax errors') !== false;
        testResult("Image Script Syntax: $script", $syntaxOk);
    } else {
        testResult("Image Script: $script", false, "File not found");
    }
}

echo "\nPhase 6: Directory Permissions Tests\n";
echo "=" . str_repeat("=", 50) . "\n";

$directories = [
    'uploads/',
    'uploads/defect_images/',
    'uploads/logos/',
    'logs/',
    'pdf_exports/'
];

foreach ($directories as $dir) {
    $exists = is_dir($dir);
    testResult("Directory: $dir", $exists, $exists ? "Exists" : "Missing");
    
    if ($exists) {
        $writable = is_writable($dir);
        testResult("Writable: $dir", $writable, $writable ? "Writable" : "Not writable");
    }
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "SUMMARY\n";
echo str_repeat("=", 70) . "\n";

$totalTests = count($results);
$passedTests = count(array_filter($results));
$failedTests = $totalTests - $passedTests;

echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

if (!empty($errors)) {
    echo "FAILED TESTS:\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($errors as $error) {
        echo "• $error\n";
    }
}

echo "\nTest completed!\n";
?>