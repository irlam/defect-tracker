<?php
// api/edit_category.php
// Current Date and Time (UTC): 2025-01-18 10:47:56
// Current User: irlam

header('Content-Type: application/json');
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Check if user is authenticated
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access. Please log in.');
    }

    // Validate POST data
    if (!isset($_POST['id']) || !isset($_POST['name'])) {
        throw new Exception('Missing required fields (id or name)');
    }

    // Include required files
    if (!file_exists('../config/database.php')) {
        throw new Exception('Database configuration file not found');
    }
    require_once '../config/database.php';

    if (!file_exists('../includes/functions.php')) {
        throw new Exception('Functions file not found');
    }
    require_once '../includes/functions.php';

    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();

    // Sanitize and validate inputs
    $categoryId = filter_var($_POST['id'], FILTER_VALIDATE_INT);
    if ($categoryId === false) {
        throw new Exception('Invalid category ID');
    }

    $name = trim($_POST['name']);
    if (empty($name)) {
        throw new Exception('Category name cannot be empty');
    }

    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Check if category exists
    $stmt = $db->prepare("
        SELECT id, name 
        FROM categories 
        WHERE id = ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        throw new Exception('Category not found or has been deleted');
    }

    // Check for duplicate name
    $stmt = $db->prepare("
        SELECT id 
        FROM categories 
        WHERE LOWER(name) = LOWER(?) 
        AND id != ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([strtolower($name), $categoryId]);
    if ($stmt->fetch()) {
        throw new Exception('A category with this name already exists');
    }

    // Begin transaction
    $db->beginTransaction();

    // Update the category
    $stmt = $db->prepare("
        UPDATE categories 
        SET 
            name = :name,
            description = :description,
            updated_at = UTC_TIMESTAMP(),
            updated_by = :updated_by
        WHERE id = :id
    ");

    $result = $stmt->execute([
        ':id' => $categoryId,
        ':name' => $name,
        ':description' => $description,
        ':updated_by' => $_SESSION['user_id']
    ]);

    if (!$result) {
        throw new Exception('Failed to update category: ' . implode(', ', $stmt->errorInfo()));
    }

    if ($stmt->rowCount() === 0) {
        throw new Exception('No changes were made to the category');
    }

    // Log the update
    $stmt = $db->prepare("
        INSERT INTO activity_logs (
            action_type,
            table_name,
            record_id,
            action_by,
            action_at,
            details
        ) VALUES (
            'UPDATE',
            'categories',
            :record_id,
            :action_by,
            UTC_TIMESTAMP(),
            :details
        )
    ");

    $logResult = $stmt->execute([
        ':record_id' => $categoryId,
        ':action_by' => $_SESSION['user_id'],
        ':details' => json_encode([
            'message' => 'Category updated',
            'old_name' => $category['name'],
            'new_name' => $name,
            'description' => $description
        ])
    ]);

    if (!$logResult) {
        throw new Exception('Failed to log category update: ' . implode(', ', $stmt->errorInfo()));
    }

    // Commit transaction
    $db->commit();

    $response['success'] = true;
    $response['message'] = 'Category updated successfully';
    $response['data'] = [
        'id' => $categoryId,
        'name' => $name,
        'description' => $description
    ];

} catch (PDOException $e) {
    // Handle database errors
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $response['success'] = false;
    $response['message'] = 'Database error: ' . $e->getMessage();
    error_log('PDO Error: ' . $e->getMessage());

} catch (Exception $e) {
    // Handle other errors
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    error_log('Error updating category: ' . $e->getMessage());

} finally {
    // Log the response for debugging
    error_log('Edit category response: ' . json_encode($response));
    
    // Send response
    echo json_encode($response);
}