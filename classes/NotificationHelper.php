<?php
/**
 * NotificationHelper Class
 * Handles creating and managing notifications for the defect tracking system
 */

require_once __DIR__ . '/TestNotificationRouter.php';

class NotificationHelper {
    private $db;
    private $testRouter;

    public function __construct($db) {
        $this->db = $db;
        $this->testRouter = new TestNotificationRouter($db);
    }

    /**
     * Create a notification for a user
     *
     * @param int $userId User ID to notify
     * @param string $type Notification type (defect_created, defect_assigned, etc.)
     * @param string $message Notification message
     * @param string|null $linkUrl Optional link URL
     * @return bool Success status
     */
    public function createNotification($userId, $type, $message, $linkUrl = null) {
        if ($this->testRouter->active()) return false;
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (user_id, type, message, link_url, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ");
            return $stmt->execute([$userId, $type, $message, $linkUrl]);
        } catch (Exception $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify relevant users when a defect is created
     *
     * @param int $defectId Defect ID
     * @param int $createdBy User who created the defect
     * @param int|null $assignedTo User the defect is assigned to
     * @param int|null $contractorId Contractor ID if applicable
     */
    public function notifyDefectCreated($defectId, $createdBy, $assignedTo = null, $contractorId = null) {
        if ($this->testRouter->route($defectId, 'created') !== null) return;
        try {
            // Get defect details
            $defectStmt = $this->db->prepare("
                SELECT d.title, p.name as project_name, c.company_name as contractor_name
                FROM defects d
                LEFT JOIN projects p ON d.project_id = p.id
                LEFT JOIN contractors c ON d.contractor_id = c.id
                WHERE d.id = ?
            ");
            $defectStmt->execute([$defectId]);
            $defect = $defectStmt->fetch(PDO::FETCH_ASSOC);

            if (!$defect) return;

            $message = "New defect created: {$defect['title']}";
            if ($defect['project_name']) {
                $message .= " in project {$defect['project_name']}";
            }
            $linkUrl = "view_defect.php?id={$defectId}";

            // Notify assigned user if different from creator
            if ($assignedTo && $assignedTo != $createdBy) {
                $this->createNotification($assignedTo, 'defect_created', $message, $linkUrl);
            }

            // Notify contractor users if contractor is assigned
            if ($contractorId) {
                $contractorUsersStmt = $this->db->prepare("
                    SELECT id FROM users WHERE contractor_id = ?
                ");
                $contractorUsersStmt->execute([$contractorId]);
                $contractorUsers = $contractorUsersStmt->fetchAll(PDO::FETCH_COLUMN);

                foreach ($contractorUsers as $userId) {
                    if ($userId != $createdBy) {
                        $this->createNotification($userId, 'defect_created', $message, $linkUrl);
                    }
                }
            }

            // Notify managers and admins
            $managersStmt = $this->db->prepare("
                SELECT id FROM users WHERE user_type IN ('admin', 'manager')
            ");
            $managersStmt->execute();
            $managers = $managersStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($managers as $managerId) {
                if ($managerId != $createdBy) {
                    $this->createNotification($managerId, 'defect_created', $message, $linkUrl);
                }
            }

        } catch (Exception $e) {
            error_log("Error in notifyDefectCreated: " . $e->getMessage());
        }
    }

    /**
     * Notify when a defect is assigned to a user
     *
     * @param int $defectId Defect ID
     * @param int $assignedTo User ID the defect was assigned to
     * @param int $assignedBy User ID who did the assignment
     */
    public function notifyDefectAssigned($defectId, $assignedTo, $assignedBy) {
        if ($this->testRouter->route($defectId, 'assigned') !== null) return;
        try {
            // Get defect details
            $defectStmt = $this->db->prepare("
                SELECT title FROM defects WHERE id = ?
            ");
            $defectStmt->execute([$defectId]);
            $defect = $defectStmt->fetch(PDO::FETCH_ASSOC);

            if (!$defect) return;

            $message = "Defect assigned to you: {$defect['title']}";
            $linkUrl = "view_defect.php?id={$defectId}";

            $this->createNotification($assignedTo, 'defect_assigned', $message, $linkUrl);

        } catch (Exception $e) {
            error_log("Error in notifyDefectAssigned: " . $e->getMessage());
        }
    }

    /**
     * Notify when a defect status changes
     *
     * @param int $defectId Defect ID
     * @param string $newStatus New lifecycle status
     * @param int $changedBy User who changed the status
     * @param int|null $assignedTo User the defect is assigned to
     */
    public function notifyDefectStatusChanged($defectId, $newStatus, $changedBy, $assignedTo = null) {
        if ($this->testRouter->route($defectId, 'status_changed') !== null) return;
        try {
            // Get defect details
            $defectStmt = $this->db->prepare("
                SELECT d.title, d.reported_by, d.contractor_id
                FROM defects d
                WHERE d.id = ?
            ");
            $defectStmt->execute([$defectId]);
            $defect = $defectStmt->fetch(PDO::FETCH_ASSOC);

            if (!$defect) return;

            $statusMessages = [
                'in_progress' => 'Work started on defect: ',
                'pending' => 'Defect submitted for review: ',
                'accepted' => 'Defect accepted: ',
                'rejected' => 'Defect rejected: ',
                'reopened' => 'Defect reopened: '
            ];

            $types = [
                'in_progress' => 'defect_in_progress',
                'pending' => 'defect_pending_review',
                'accepted' => 'defect_accepted',
                'rejected' => 'defect_rejected',
                'reopened' => 'defect_reopened'
            ];

            if (!isset($statusMessages[$newStatus])) return;

            $message = $statusMessages[$newStatus] . $defect['title'];
            $linkUrl = "view_defect.php?id={$defectId}";

            $recipients = [(int) $defect['reported_by']];
            if ($assignedTo) {
                $recipients[] = (int) $assignedTo;
            }

            $participantStmt = $this->db->prepare("
                SELECT DISTINCT u.id
                FROM users u
                LEFT JOIN defect_assignments da
                  ON da.user_id = u.id
                 AND da.defect_id = :defect_id
                 AND da.status = 'active'
                WHERE u.status = 'active'
                  AND (
                    da.id IS NOT NULL
                    OR (u.contractor_id IS NOT NULL AND u.contractor_id = :contractor_id)
                    OR u.user_type IN ('admin', 'manager')
                  )
            ");
            $participantStmt->execute([
                ':defect_id' => $defectId,
                ':contractor_id' => $defect['contractor_id'],
            ]);
            $recipients = array_unique(array_merge($recipients, array_map('intval', $participantStmt->fetchAll(PDO::FETCH_COLUMN) ?: [])));

            foreach ($recipients as $recipientId) {
                if ($recipientId > 0 && $recipientId !== (int) $changedBy) {
                    $this->createNotification($recipientId, $types[$newStatus], $message, $linkUrl);
                }
            }

        } catch (Exception $e) {
            error_log("Error in notifyDefectStatusChanged: " . $e->getMessage());
        }
    }

    /**
     * Notify when a comment is added to a defect
     *
     * @param int $defectId Defect ID
     * @param int $commentedBy User who added the comment
     * @param string $commentText The comment text
     */
    public function notifyCommentAdded($defectId, $commentedBy, $commentText) {
        if ($this->testRouter->route($defectId, 'comment_added') !== null) return;
        try {
            // Get defect details and participants
            $stmt = $this->db->prepare("
                SELECT d.title, d.reported_by, d.assigned_to,
                       GROUP_CONCAT(DISTINCT dc.user_id) as comment_users
                FROM defects d
                LEFT JOIN defect_comments dc ON d.id = dc.defect_id
                WHERE d.id = ?
                GROUP BY d.id, d.title, d.reported_by, d.assigned_to
            ");
            $stmt->execute([$defectId]);
            $defect = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$defect) return;

            $message = "New comment on defect: {$defect['title']}";
            $linkUrl = "view_defect.php?id={$defectId}";

            $notifiedUsers = [];

            // Notify defect reporter
            if ($defect['reported_by'] && $defect['reported_by'] != $commentedBy) {
                $this->createNotification($defect['reported_by'], 'comment_added', $message, $linkUrl);
                $notifiedUsers[] = $defect['reported_by'];
            }

            // Notify assigned user
            if ($defect['assigned_to'] && $defect['assigned_to'] != $commentedBy && !in_array($defect['assigned_to'], $notifiedUsers)) {
                $this->createNotification($defect['assigned_to'], 'comment_added', $message, $linkUrl);
                $notifiedUsers[] = $defect['assigned_to'];
            }

            // Notify other comment participants
            if ($defect['comment_users']) {
                $commentUserIds = explode(',', $defect['comment_users']);
                foreach ($commentUserIds as $userId) {
                    if ($userId && $userId != $commentedBy && !in_array($userId, $notifiedUsers)) {
                        $this->createNotification($userId, 'comment_added', $message, $linkUrl);
                        $notifiedUsers[] = $userId;
                    }
                }
            }

        } catch (Exception $e) {
            error_log("Error in notifyCommentAdded: " . $e->getMessage());
        }
    }
}
?>
