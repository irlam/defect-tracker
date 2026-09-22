<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

// Check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo "event: error\ndata: {\"message\": \"Unauthorized access\"}\n\n";
    exit();
}

$userId = $_SESSION['user_id'];
session_write_close();

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(503);
    echo "event: error\ndata: {\"message\": \"Notification service unavailable\"}\n\n";
    exit();
}

// Set headers for Server-Sent Events
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

// Function to send SSE data
function sendSSE(string $event, array $data, ?int $id = null): void {
    if ($id !== null) {
        echo "id: $id\n";
    }
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    flush();
}

// Get the last event ID from the client
$lastEventId = isset($_SERVER['HTTP_LAST_EVENT_ID']) ? (int) $_SERVER['HTTP_LAST_EVENT_ID'] : 0;

echo "retry: 5000\n\n";

// Send initial connection confirmation
sendSSE('connected', ['status' => 'connected', 'user_id' => $userId]);

// On a fresh connection, start at the current newest notification so the
// stream only delivers notifications created after the page was loaded.
$lastNotificationId = $lastEventId;
if ($lastNotificationId === 0) {
    $latestStmt = $db->prepare('SELECT COALESCE(MAX(id), 0) FROM notifications WHERE user_id = ?');
    $latestStmt->execute([$userId]);
    $lastNotificationId = (int) $latestStmt->fetchColumn();
}

// Keep each request bounded so PHP workers are released regularly. EventSource
// reconnects automatically and supplies the last event ID.
$deadline = time() + 25;
while (time() < $deadline && !connection_aborted()) {
    try {
        // Check for new notifications
        $stmt = $db->prepare("
            SELECT id, type, message, link_url, created_at
            FROM notifications
            WHERE user_id = ? AND id > ?
            ORDER BY id ASC
        ");
        $stmt->execute([$userId, $lastNotificationId]);
        $newNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($newNotifications)) {
            foreach ($newNotifications as $notification) {
                $notificationId = (int) $notification['id'];
                sendSSE('notification', [
                    'id' => $notification['id'],
                    'type' => $notification['type'],
                    'message' => $notification['message'],
                    'link_url' => $notification['link_url'],
                    'created_at' => $notification['created_at']
                ], $notificationId);
                $lastNotificationId = $notificationId;
            }
        }

        // Check for updated read status (in case notifications are marked as read elsewhere)
        $unreadStmt = $db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
        $unreadStmt->execute([$userId]);
        $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);

        sendSSE('unread_count', ['count' => $unreadResult['unread_count']]);

    } catch (Throwable $e) {
        error_log("SSE Error: " . $e->getMessage());
        sendSSE('error', ['message' => 'Database error occurred']);
        break;
    }

    // Wait before checking again (reduce server load)
    sleep(5);
}
?>
