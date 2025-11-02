<?php
// functional_tests.php - Test actual functionality with mock data
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== Functional Tests for Defect Tracker ===\n\n";

// Use test database
require_once 'config/test_database.php';
require_once 'classes/Auth.php';
require_once 'classes/RBAC.php';
require_once 'classes/Logger.php';

$database = new TestDatabase();
$db = $database->getConnection();
$results = [];

function functionalTest($testName, $callable) {
    global $results;
    try {
        $result = $callable();
        $status = $result ? "✓ PASS" : "✗ FAIL";
        $results[$testName] = $result;
        echo sprintf("%-50s %s\n", $testName, $status);
        return $result;
    } catch (Exception $e) {
        echo sprintf("%-50s ✗ FAIL - %s\n", $testName, $e->getMessage());
        $results[$testName] = false;
        return false;
    }
}

echo "Phase 1: Authentication Functions\n";
echo str_repeat("=", 50) . "\n";

// Test authentication
functionalTest("Auth Class Instantiation", function() use ($db) {
    $auth = new Auth($db);
    return $auth instanceof Auth;
});

functionalTest("User Login Validation", function() use ($db) {
    $auth = new Auth($db);
    // Test with valid credentials (admin/admin123 from test data)
    $result = $auth->login('admin', 'admin123');
    return $result === true;
});

functionalTest("User Login Invalid Credentials", function() use ($db) {
    $auth = new Auth($db);
    $result = $auth->login('admin', 'wrongpassword');
    return $result === false;
});

functionalTest("User Role Check", function() use ($db) {
    $auth = new Auth($db);
    // First login to set session
    $auth->login('admin', 'admin123');
    return $auth->hasRole('admin');
});

echo "\nPhase 2: RBAC Functions\n";
echo str_repeat("=", 50) . "\n";

functionalTest("RBAC Class Instantiation", function() use ($db) {
    $rbac = new RBAC($db);
    return $rbac instanceof RBAC;
});

functionalTest("RBAC Get Roles", function() use ($db) {
    $rbac = new RBAC($db);
    $roles = $rbac->getRoles();
    return is_array($roles);
});

echo "\nPhase 3: Database Operations\n";
echo str_repeat("=", 50) . "\n";

functionalTest("Create New Defect", function() use ($db) {
    $stmt = $db->prepare("
        INSERT INTO defects (project_id, title, description, category, severity, status, created_by_user, created_by, created_at) 
        VALUES (1, 'Test Defect', 'Test Description', 'General', 'medium', 'new', 1, 'testuser', datetime('now'))
    ");
    $result = $stmt->execute();
    return $result && $db->lastInsertId() > 0;
});

functionalTest("Retrieve Defects", function() use ($db) {
    $stmt = $db->query("SELECT * FROM defects");
    $defects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    return count($defects) > 0;
});

functionalTest("Update Defect Status", function() use ($db) {
    $stmt = $db->prepare("UPDATE defects SET status = 'in_progress' WHERE id = 1");
    $result = $stmt->execute();
    
    // Verify the update
    $checkStmt = $db->prepare("SELECT status FROM defects WHERE id = 1");
    $checkStmt->execute();
    $status = $checkStmt->fetchColumn();
    
    return $result && $status === 'in_progress';
});

echo "\nPhase 4: File Operations\n";
echo str_repeat("=", 50) . "\n";

functionalTest("Upload Directory Writable", function() {
    return is_writable('uploads/');
});

functionalTest("Create Test Image File", function() {
    $testImage = imagecreate(100, 100);
    $background = imagecolorallocate($testImage, 255, 255, 255);
    $textColor = imagecolorallocate($testImage, 0, 0, 0);
    imagestring($testImage, 5, 20, 40, "TEST", $textColor);
    
    $result = imagepng($testImage, 'uploads/test_image.png');
    imagedestroy($testImage);
    
    return $result && file_exists('uploads/test_image.png');
});

functionalTest("Process Image File", function() {
    if (!file_exists('uploads/test_image.png')) return false;
    
    // Test basic image processing
    $imageInfo = getimagesize('uploads/test_image.png');
    return $imageInfo !== false && $imageInfo[0] == 100 && $imageInfo[1] == 100;
});

echo "\nPhase 5: API-like Functions\n";
echo str_repeat("=", 50) . "\n";

functionalTest("JSON Response Formation", function() {
    $data = [
        'success' => true,
        'message' => 'Test successful',
        'data' => ['id' => 1, 'name' => 'Test']
    ];
    
    $json = json_encode($data);
    $decoded = json_decode($json, true);
    
    return $decoded['success'] === true && $decoded['data']['id'] === 1;
});

functionalTest("Input Validation", function() {
    // Test various input validation scenarios
    $testInputs = [
        'valid_email@test.com' => filter_var('valid_email@test.com', FILTER_VALIDATE_EMAIL),
        'invalid_email' => filter_var('invalid_email', FILTER_VALIDATE_EMAIL),
        '123' => is_numeric('123'),
        'abc' => is_numeric('abc')
    ];
    
    return $testInputs['valid_email@test.com'] !== false && 
           $testInputs['invalid_email'] === false &&
           $testInputs['123'] === true &&
           $testInputs['abc'] === false;
});

echo "\nPhase 6: System Health Functions\n";
echo str_repeat("=", 50) . "\n";

functionalTest("Database Health Check", function() use ($db) {
    $stmt = $db->query("SELECT 1");
    return $stmt->fetchColumn() === 1;
});

functionalTest("Required Extensions Available", function() {
    $requiredExtensions = ['pdo', 'gd', 'json'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) return false;
    }
    return true;
});

functionalTest("Memory Usage Check", function() {
    $memoryUsage = memory_get_usage();
    $memoryLimit = ini_get('memory_limit');
    return $memoryUsage > 0 && $memoryLimit !== false;
});

// Cleanup
if (file_exists('uploads/test_image.png')) {
    unlink('uploads/test_image.png');
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "FUNCTIONAL TESTS SUMMARY\n";
echo str_repeat("=", 70) . "\n";

$totalTests = count($results);
$passedTests = count(array_filter($results));
$failedTests = $totalTests - $passedTests;

echo "Total Tests: $totalTests\n";
echo "Passed: $passedTests\n";
echo "Failed: $failedTests\n";
echo "Success Rate: " . round(($passedTests / $totalTests) * 100, 2) . "%\n\n";

if ($failedTests > 0) {
    echo "FAILED TESTS:\n";
    echo str_repeat("-", 50) . "\n";
    foreach ($results as $test => $result) {
        if (!$result) {
            echo "• $test\n";
        }
    }
}

echo "\nFunctional testing completed!\n";
?>