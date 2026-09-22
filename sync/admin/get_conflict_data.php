<?php
/**
 * Sync System - Get Conflict Data API
 * Created: 2025-02-26 11:48:16
 * Updated by: irlam
 */

// Include authentication and initialization
require_once __DIR__ . '/../init.php';

// Check if user is logged in and has admin privileges
session_start();
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Load config explicitly
if (!isset($config)) {
    $config = include __DIR__ . '/../config.php';
}

// Initialize response
$response = ['error' => 'Invalid request'];

// Validate conflict ID
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        // Establish database connection
        $db = new PDO("mysql:host={$config['db_host']};dbname={$config['db_name']}", $config['db_user'], $config['db_pass']);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Get conflict data
        $stmt = $db->prepare("SELECT * FROM sync_conflicts WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $conflict = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($conflict) {
            // Return conflict data
            $response = $conflict;
        } else {
            $response = ['error' => 'Conflict not found'];
        }
    } catch (PDOException $e) {
        $response = ['error' => 'Database error: ' . $e->getMessage()];
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);