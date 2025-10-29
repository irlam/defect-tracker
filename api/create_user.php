<?php
// api/create_user.php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

// Check authentication and admin privileges
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Start transaction
    $db->beginTransaction();

    // Hash password
    $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

    // Insert user
    $stmt = $db->prepare("
        INSERT INTO users (
            username, 
            email, 
            password_hash, 
            user_type, 
            status, 
            created_by, 
            created_at
        ) VALUES (
            :username,
            :email,
            :password_hash,
            :user_type,
            'active',
            :created_by,
            :created_at
        )
    ");

    $stmt->execute([
        ':username' => $_POST['username'],
        ':email' => $_POST['email'],
        ':password_hash' => $hashedPassword,
        ':user_type' => $_POST['user_type'],
        ':created_by' => 'irlam',
        ':created_at' => '2025-01-14 21:11:42'
    ]);

    $userId = $db->lastInsertId();

    // If user is a contractor, add to contractors table
    if ($_POST['user_type'] === 'contractor' && !empty($_POST['company_name'])) {
        $stmt = $db->prepare("
            INSERT INTO contractors (
                user_id,
                company_name,
                created_at
            ) VALUES (
                :user_id,
                :company_name,
                :created_at
            )
        ");

        $stmt->execute([
            ':user_id' => $userId,
            ':company_name' => $_POST['company_name'],
            ':created_at' => '2025-01-14 21:11:42'
        ]);
    }

    // Commit transaction
    $db->commit();

    // Send welcome email
    sendWelcomeEmail($_POST['email'], $_POST['username']);

    echo json_encode(['success' => true, 'message' => 'User created successfully']);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Error creating user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error creating user']);
}