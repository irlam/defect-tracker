<?php
require_once '../classes/Logger.php';
require_once '../config/config.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "event: error\ndata: {\"message\": \"Unauthorized access\"}\n\n";
    exit();
}

$userId = $_SESSION['user_id'];

// Set headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Function to send SSE data
function sendSSE($event, $data) {
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    ob_flush();
    flush();
}

// Get the last event ID from the client
$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? $_SERVER['HTTP_LAST_EVENT_ID'] : 0;

// Send initial connection confirmation
sendSSE('connected', ['status' => 'connected', 'user_id' => $userId]);

// Track the last notification ID we've seen
$lastNotificationId = $lastEventId;

// Main loop to check for new notifications
while (true) {
    try {
        // Check for new notifications
        $stmt = $pdo->prepare("
            SELECT id, type, message, link_url, created_at
            FROM notifications
            WHERE user_id = ? AND id > ?
            ORDER BY id ASC
        ");
        $stmt->execute([$userId, $lastNotificationId]);
        $newNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($newNotifications)) {
            foreach ($newNotifications as $notification) {
                sendSSE('notification', [
                    'id' => $notification['id'],
                    'type' => $notification['type'],
                    'message' => $notification['message'],
                    'link_url' => $notification['link_url'],
                    'created_at' => $notification['created_at']
                ]);
                $lastNotificationId = $notification['id'];
            }
        }

        // Check for updated read status (in case notifications are marked as read elsewhere)
        $unreadStmt = $pdo->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        $unreadStmt->execute([$userId]);
        $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);

        sendSSE('unread_count', ['count' => $unreadResult['unread_count']]);

    } catch (Exception $e) {
        error_log("SSE Error: " . $e->getMessage());
        sendSSE('error', ['message' => 'Database error occurred']);
    }

    // Wait before checking again (reduce server load)
    sleep(5);
}
?>