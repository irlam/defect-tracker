<?php
/**
 * Push Notification Sender for DefectTracker
 * 
 * This file handles sending notifications via Firebase Cloud Messaging (FCM)
 */

/**
 * Sends push notifications to users and/or contractors
 * 
 * @param string $title Notification title
 * @param string $body Notification message
 * @param string $targetType 'all', 'user', 'contractor', 'all_users', 'all_contractors'
 * @param int|null $userId User ID if targeting specific user
 * @param int|null $contractorId Contractor ID if targeting specific contractor
 * @param int|null $defectId Optional defect ID to link to
 * @return array Result with success status and message
 */
function sendNotification($title, $body, $targetType = 'all', $userId = null, $contractorId = null, $defectId = null) {
    // Get Firebase Server Key from environment or config
    $serverKey = getenv('FIREBASE_SERVER_KEY') ?: 'YOUR_FIREBASE_SERVER_KEY';
    
    try {
        // Include database configuration if not already included
        if (!class_exists('Database')) {
            require_once __DIR__ . '/../config/database.php';
        }
        
        // Connect to database
        $database = new Database();
        $db = $database->getConnection();
        
        // Get recipients (users and/or contractors) with their FCM tokens and platform info
        $recipients = getNotificationRecipients($db, $targetType, $userId, $contractorId);
        
        if (empty($recipients)) {
            return [
                'success' => false,
                'error' => 'No registered devices found for selected recipients'
            ];
        }
        
        // Log this notification in the database
        $stmt = $db->prepare(
            "INSERT INTO notification_log (title, message, target_type, user_id, contractor_id, defect_id, delivery_status, sent_at) 
             VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$title, $body, $targetType, $userId, $contractorId, $defectId]);
        $logId = $db->lastInsertId();
        
        // Count successful and failed notifications
        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        
        // Send to each recipient
        foreach ($recipients as $recipient) {
            $result = sendFCMNotification($serverKey, $recipient['fcm_token'], $title, $body, $defectId);
            
            // Log individual recipient delivery status
            $recipientStatus = $result['success'] ? 'sent' : 'failed';
            $recipientStmt = $db->prepare(
                "INSERT INTO notification_recipients 
                (notification_log_id, user_id, contractor_id, fcm_token, platform, delivery_status, sent_at, failed_at, error_message, fcm_response) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?, ?)"
            );
            $recipientStmt->execute([
                $logId,
                $recipient['user_id'] ?? null,
                $recipient['contractor_id'] ?? null,
                $recipient['fcm_token'],
                $recipient['platform'] ?? 'unknown',
                $recipientStatus,
                $result['success'] ? null : date('Y-m-d H:i:s'),
                $result['error'] ?? null,
                $result['fcm_response'] ?? null
            ]);
            
            if ($result['success']) {
                $successCount++;
            } else {
                $failedCount++;
                $errors[] = $result['error'] ?? 'Unknown error';
            }
        }
        
        // Update log with success/failure counts and overall status
        $overallStatus = $successCount > 0 ? 'sent' : 'failed';
        $errorMessage = !empty($errors) ? implode('; ', array_unique($errors)) : null;
        
        $stmt = $db->prepare(
            "UPDATE notification_log 
             SET success_count = ?, failed_count = ?, delivery_status = ?, error_message = ? 
             WHERE id = ?"
        );
        $stmt->execute([$successCount, $failedCount, $overallStatus, $errorMessage, $logId]);
        
        // Log activity
        $senderUserId = $_SESSION['user_id'] ?? null;
        if ($senderUserId) {
            $stmt = $db->prepare(
                "INSERT INTO activity_log (user_id, action_type, description, created_at) 
                VALUES (?, 'sent_notification', ?, NOW())"
            );
            $description = "Sent push notification: '$title' to " . getTargetDescription($targetType, count($recipients));
            $stmt->execute([$senderUserId, $description]);
        }
        
        return [
            'success' => $successCount > 0,
            'recipients' => $successCount,
            'failed' => $failedCount,
            'total' => count($recipients),
            'log_id' => $logId,
            'errors' => $errors
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
 * Get notification recipients based on target type
 * 
 * @param PDO $db Database connection
 * @param string $targetType Target type (all, user, contractor, all_users, all_contractors)
 * @param int|null $userId Specific user ID
 * @param int|null $contractorId Specific contractor ID
 * @return array Array of recipients with fcm_token, user_id, contractor_id, platform
 */
function getNotificationRecipients($db, $targetType, $userId = null, $contractorId = null) {
    $recipients = [];
    
    try {
        switch ($targetType) {
            case 'all':
                // Get all users with FCM tokens (using UNION to avoid duplicates)
                $stmt = $db->prepare(
                    "SELECT DISTINCT u.id as user_id, u.contractor_id, u.fcm_token, u.device_platform as platform 
                     FROM users u
                     WHERE u.fcm_token IS NOT NULL AND u.fcm_token != ''"
                );
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'user':
                if ($userId) {
                    $stmt = $db->prepare(
                        "SELECT id as user_id, NULL as contractor_id, fcm_token, device_platform as platform 
                         FROM users 
                         WHERE id = ? AND fcm_token IS NOT NULL AND fcm_token != ''"
                    );
                    $stmt->execute([$userId]);
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
                
            case 'contractor':
                if ($contractorId) {
                    // Get all users associated with this contractor
                    $stmt = $db->prepare(
                        "SELECT u.id as user_id, u.contractor_id, u.fcm_token, u.device_platform as platform 
                         FROM users u
                         WHERE u.contractor_id = ? AND u.fcm_token IS NOT NULL AND u.fcm_token != ''"
                    );
                    $stmt->execute([$contractorId]);
                    $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                }
                break;
                
            case 'all_users':
                // Only regular users (non-contractors)
                $stmt = $db->prepare(
                    "SELECT id as user_id, NULL as contractor_id, fcm_token, device_platform as platform 
                     FROM users 
                     WHERE fcm_token IS NOT NULL AND fcm_token != '' 
                     AND (contractor_id IS NULL OR contractor_id = 0)"
                );
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
                
            case 'all_contractors':
                // Only contractor users
                $stmt = $db->prepare(
                    "SELECT u.id as user_id, u.contractor_id, u.fcm_token, u.device_platform as platform 
                     FROM users u
                     WHERE u.fcm_token IS NOT NULL AND u.fcm_token != '' 
                     AND u.contractor_id IS NOT NULL AND u.contractor_id > 0"
                );
                $stmt->execute();
                $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;
        }
    } catch (Exception $e) {
        error_log("Error getting notification recipients: " . $e->getMessage());
    }
    
    return $recipients;
}

/**
 * Get human-readable description of notification target
 * 
 * @param string $targetType Target type
 * @param int $count Number of recipients
 * @return string Description
 */
function getTargetDescription($targetType, $count) {
    switch ($targetType) {
        case 'all':
            return "$count users and contractors";
        case 'user':
            return "1 specific user";
        case 'contractor':
            return "contractor users";
        case 'all_users':
            return "$count users";
        case 'all_contractors':
            return "$count contractors";
        default:
            return "$count recipients";
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
 * @return array Result with success status and detailed response
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
            'badge' => '1',
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ],
        'data' => [
            'title' => $title,
            'message' => $body,
            'click_action' => 'OPEN_MAIN_ACTIVITY',
            'type' => 'defect_notification'
        ],
        'priority' => 'high',
        'content_available' => true
    ];
    
    // Add defect ID if provided
    if ($defectId !== null) {
        $fields['data']['defectId'] = (string)$defectId;
        $fields['notification']['click_action'] = 'view_defect';
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        error_log("FCM Curl Error: " . $error);
        return [
            'success' => false,
            'error' => 'Connection error: ' . $error,
            'fcm_response' => null
        ];
    }
    
    curl_close($ch);
    
    // Parse the JSON response
    $data = json_decode($result, true);
    
    // Check for successful delivery
    if (isset($data['success']) && $data['success'] >= 1) {
        return [
            'success' => true,
            'fcm_response' => $result,
            'message_id' => $data['results'][0]['message_id'] ?? null
        ];
    } elseif (isset($data['failure']) && $data['failure'] > 0) {
        $errorReason = 'Unknown FCM error';
        if (isset($data['results'][0]['error'])) {
            $errorReason = $data['results'][0]['error'];
        }
        error_log("FCM Delivery Failed: " . $errorReason . " - Response: " . $result);
        return [
            'success' => false,
            'error' => 'Delivery failed: ' . $errorReason,
            'fcm_response' => $result
        ];
    } else {
        error_log("FCM Unexpected Response (HTTP $httpCode): " . $result);
        return [
            'success' => false,
            'error' => 'Unexpected response from FCM (HTTP ' . $httpCode . ')',
            'fcm_response' => $result
        ];
    }
}