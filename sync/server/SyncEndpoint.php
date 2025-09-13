<?php
namespace Sync;
require_once __DIR__ . '/../init.php';

// This file handles API requests for synchronization
header('Content-Type: application/json');

// Database connection
try {
    $db = new \PDO('mysql:host=localhost;dbname=your_database', 'username', 'password');
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Check authentication
session_start();
if (!isset($_SESSION['user']) && !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = isset($_SESSION['user']) ? $_SESSION['user'] : $_SESSION['username'];

// Process the request
$syncManager = new SyncManager($db);
$request = json_decode(file_get_contents('php://input'), true);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        // Handle incoming sync queue
        if (isset($request['queue']) && is_array($request['queue'])) {
            $results = $syncManager->processSyncQueue($request['queue'], $user);
            echo json_encode(['status' => 'success', 'results' => $results]);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid sync queue data']);
        }
        break;
        
    case 'GET':
        // Handle status check
        echo json_encode(['status' => 'online', 'timestamp' => date('d-m-Y H:i:s')]);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
}