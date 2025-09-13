<?php
/**
 * Push Notification Sender for DefectTracker
 * 
 * This file handles sending notifications via Firebase Cloud Messaging (FCM)
 */

/**
 * Sends push notifications to users
 * 
 * @param string $title Notification title
 * @param string $body Notification message
 * @param string $targetType 'all' or 'user'
 * @param int|null $userId User ID if targeting specific user
 * @param int|null $defectId Optional defect ID to link to
 * @return array Result with success status and message
 */
function sendNotification($title, $body, $targetType = 'all', $userId = null, $defectId = null) {
    // Your Firebase Server Key from Firebase Console > Project Settings > Cloud Messaging
    $serverKey = 'YOUR_FIREBASE_SERVER_KEY';
    
    try {
        // Include database configuration if not already included
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        // Connect to database
        $database = new Database();
        $db = $database->getConnection();
        
        // Get FCM tokens based on target type
        if ($targetType === 'all') {
            $stmt = $db->prepare("SELECT fcm_token FROM users WHERE fcm_token IS NOT NULL AND fcm_token != ''");
            $stmt->execute();
        } else {
            $stmt = $db->prepare("SELECT fcm_token FROM users WHERE id = ? AND fcm_token IS NOT NULL AND fcm_token != ''");
            $stmt->execute([$userId]);
        }
        
        $tokens = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($tokens)) {
            return [
                'success' => false,
                'error' => 'No registered devices found for selected recipients'
            ];
        }
        
        // Log this notification in the database
        $stmt = $db->prepare(
            "INSERT INTO notification_log (title, message, target_type, user_id, defect_id, sent_at) 
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$title, $body, $targetType, $userId, $defectId]);
        $logId = $db->lastInsertId();
        
        // Count successful notifications
        $successCount = 0;
        
        // Send to each token
        foreach ($tokens as $token) {
            $result = sendFCMNotification($serverKey, $token, $title, $body, $defectId);
            if ($result['success']) {
                $successCount++;
            }
        }
        
        // Update log with success count
        $stmt = $db->prepare("UPDATE notification_log SET success_count = ? WHERE id = ?");
        $stmt->execute([$successCount, $logId]);
        
        // Log activity
        $senderUserId = $_SESSION['user_id'] ?? null;
        if ($senderUserId) {
            $stmt = $db->prepare(
                "INSERT INTO activity_log (user_id, action_type, description, created_at) 
                VALUES (?, 'sent_notification', ?, NOW())"
            );
            $description = "Sent push notification: '$title' to " . ($targetType === 'all' ? 'all users' : 'specific user');
            $stmt->execute([$senderUserId, $description]);
        }
        
        return [
            'success' => true,
            'recipients' => $successCount,
            'total' => count($tokens)
        ];
        
    } catch (PDOException $e) {
        error_log("Push Notification Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ];
    } catch (Exception $e) {
        error_log("Push Notification Error: " . $e->getMessage());
        return [
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Sends a single FCM message to a device token
 * 
 * @param string $serverKey Firebase Server Key
 * @param string $token FCM device token
 * @param string $title Notification title
 * @param string $body Notification message
 * @param int|null $defectId Optional defect ID to link
 * @return array Result with success status
 */
function sendFCMNotification($serverKey, $token, $title, $body, $defectId = null) {
    $url = 'https://fcm.googleapis.com/fcm/send';
    
    // Prepare notification payload
    $fields = [
        'to' => $token,
        'notification' => [
            'title' => $title,
            'body' => $body,
            'sound' => 'default',
            'badge' => '1'
        ],
        'data' => [
            'title' => $title,
            'message' => $body,
            'click_action' => 'OPEN_MAIN_ACTIVITY'
        ]
    ];
    
    // Add defect ID if provided
    if ($defectId !== null) {
        $fields['data']['defectId'] = $defectId;
    }
    
    // HTTP headers
    $headers = [
        'Authorization: key=' . $serverKey,
        'Content-Type: application/json'
    ];
    
    // Send the request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
    
    $result = curl_exec($ch);
    
    if (curl_errno($ch)) {
        curl_close($ch);
        error_log("FCM Curl Error: " . curl_error($ch));
        return [
            'success' => false,
            'error' => 'Curl error: ' . curl_error($ch)
        ];
    }
    
    curl_close($ch);
    
    // Parse the JSON response
    $data = json_decode($result, true);
    
    if (isset($data['success']) && $data['success'] == 1) {
        return [
            'success' => true
        ];
    } else {
        error_log("FCM Error Response: " . $result);
        return [
            'success' => false,
            'error' => 'FCM Error: ' . $result
        ];
    }
}