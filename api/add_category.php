<?php
// api/add_category.php
// Current Date and Time (UTC): 2025-01-18 10:40:41
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

    // Verify user has permission to add categories
    if (!$auth->hasPermission($_SESSION['user_id'], 'manage_categories')) {
        throw new Exception('Permission denied');
    }

    // Validate input
    if (!isset($_POST['name']) || empty(trim($_POST['name']))) {
        throw new Exception('Category name is required');
    }

    $name = trim($_POST['name']);
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';

    // Check for existing category (including soft-deleted ones)
    $stmt = $db->prepare("
        SELECT id, name, deleted_at 
        FROM categories 
        WHERE LOWER(name) = LOWER(?)
    ");
    $stmt->execute([strtolower($name)]);
    $existingCategory = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingCategory) {
        if ($existingCategory['deleted_at'] === null) {
            throw new Exception('A category with this name already exists');
        } else {
            // If the category exists but was soft-deleted, restore it
            $stmt = $db->prepare("
                UPDATE categories 
                SET 
                    deleted_at = NULL,
                    deleted_by = NULL,
                    description = :description,
                    updated_at = UTC_TIMESTAMP(),
                    updated_by = :updated_by
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $existingCategory['id'],
                ':description' => $description,
                ':updated_by' => $_SESSION['user_id']
            ]);

            $response['success'] = true;
            $response['message'] = 'Category restored successfully';
            $response['data'] = [
                'id' => $existingCategory['id'],
                'name' => $existingCategory['name'],
                'description' => $description
            ];
            echo json_encode($response);
            exit;
        }
    }

    // Begin transaction
    $db->beginTransaction();

    // Insert new category
    $stmt = $db->prepare("
        INSERT INTO categories (
            name, 
            description, 
            created_by, 
            created_at,
            updated_by,
            updated_at
        ) VALUES (
            :name, 
            :description, 
            :created_by, 
            UTC_TIMESTAMP(),
            :updated_by,
            UTC_TIMESTAMP()
        )
    ");

    $stmt->execute([
        ':name' => $name,
        ':description' => $description,
        ':created_by' => $_SESSION['user_id'],
        ':updated_by' => $_SESSION['user_id']
    ]);

    $categoryId = $db->lastInsertId();

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
            'INSERT',
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
            'message' => 'Category created',
            'name' => $name,
            'description' => $description
        ])
    ]);

    // Commit transaction
    $db->commit();

    $response['success'] = true;
    $response['message'] = 'Category added successfully';
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
    error_log("Error adding category: " . $e->getMessage());
} finally {
    echo json_encode($response);
}