<?php
// api/edit_category.php
// Current Date and Time (UTC): 2025-01-18 10:43:20
// Current User: irlam

header('Content-Type: application/json');
session_start();

// Include required files
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

try {
    // Check if user is authenticated
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized access');
    }

    // Initialize database connection
    $database = new Database();
    $db = $database->getConnection();
    
    // Create Auth instance
    $auth = new Auth($db);

    // Verify user has permission to edit categories
    if (!$auth->hasPermission($_SESSION['user_id'], 'manage_categories')) {
        throw new Exception('Permission denied');
    }

    // Validate input
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('Invalid category ID');
    }
    if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
        throw new Exception('Category name is required');
    }

    $categoryId = (int)$_POST['id'];
    $name = trim($_POST['name']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Check if category exists and is not deleted
    $stmt = $db->prepare("
        SELECT id 
        FROM categories 
        WHERE id = ? 
        AND deleted_at IS NULL
    ");
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) {
        throw new Exception('Category not found');
    }

    // Check if the new name already exists for a different category
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

    // Update category
    $stmt = $db->prepare("
        UPDATE categories 
        SET 
            name = :name,
            description = :description,
            updated_at = UTC_TIMESTAMP(),
            updated_by = :updated_by
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $categoryId,
        ':name' => $name,
        ':description' => $description,
        ':updated_by' => $_SESSION['user_id']
    ]);

    // Log the action
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

    $stmt->execute([
        ':record_id' => $categoryId,
        ':action_by' => $_SESSION['user_id'],
        ':details' => json_encode([
            'message' => 'Category updated',
            'name' => $name,
            'description' => $description
        ])
    ]);

    // Commit transaction
    $db->commit();

    $response['success'] = true;
    $response['message'] = 'Category updated successfully';
    $response['data'] = [
        'id' => $categoryId,
        'name' => $name,
        'description' => $description
    ];

} catch (Exception $e) {
    // Rollback transaction if active
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    
    $response['success'] = false;
    $response['message'] = $e->getMessage();
    
    // Log the error
    error_log("Error updating category: " . $e->getMessage());
} finally {
    echo json_encode($response);
}