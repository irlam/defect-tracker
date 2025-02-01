<?php
// api/create_project.php
session_start();
require_once '../config/database.php';
date_default_timezone_set('UTC');

// Set specific values as requested
$currentUser = 'irlam';
$currentDateTime = '2025-01-14 12:59:10';

// Check authentication
if (!isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate required fields
    $required_fields = ['name', 'description', 'status', 'start_date'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Field '$field' is required");
        }
    }

    // Simplified SQL to match exact table structure
    $sql = "INSERT INTO projects (
        name, 
        description, 
        status, 
        start_date, 
        end_date,
        created_at,
        created_by,
        updated_at,
        updated_by
    ) VALUES (
        :name, 
        :description, 
        :status, 
        :start_date, 
        :end_date,
        :created_at,
        :created_by,
        :updated_at,
        :updated_by
    )";

    $stmt = $db->prepare($sql);

    // Bind parameters with specific values
    $params = [
        ':name' => $_POST['name'],
        ':description' => $_POST['description'],
        ':status' => $_POST['status'],
        ':start_date' => $_POST['start_date'],
        ':end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null,
        ':created_at' => $currentDateTime,
        ':created_by' => $currentUser,
        ':updated_at' => $currentDateTime,
        ':updated_by' => $currentUser
    ];

    $stmt->execute($params);
    $projectId = $db->lastInsertId();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Project created successfully',
        'projectId' => $projectId
    ]);

} catch (Exception $e) {
    error_log("Create Project Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error creating project: ' . $e->getMessage()
    ]);
}
?>