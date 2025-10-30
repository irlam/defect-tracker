<?php
/**
 * NotificationHelper Class
 * Handles creating and managing notifications for the defect tracking system
 */

class NotificationHelper {
    private $db;

    public function __construct($db) {
        $this->db = $db;
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
     * @param string $newStatus New status (accepted, rejected, reopened)
     * @param int $changedBy User who changed the status
     * @param int|null $assignedTo User the defect is assigned to
     */
    public function notifyDefectStatusChanged($defectId, $newStatus, $changedBy, $assignedTo = null) {
        try {
            // Get defect details
            $defectStmt = $this->db->prepare("
                SELECT d.title, u.username as changed_by_username
                FROM defects d
                JOIN users u ON d.reported_by = u.id
                WHERE d.id = ?
            ");
            $defectStmt->execute([$defectId]);
            $defect = $defectStmt->fetch(PDO::FETCH_ASSOC);

            if (!$defect) return;

            $statusMessages = [
                'accepted' => 'Defect accepted: ',
                'rejected' => 'Defect rejected: ',
                'reopened' => 'Defect reopened: '
            ];

            $types = [
                'accepted' => 'defect_accepted',
                'rejected' => 'defect_rejected',
                'reopened' => 'defect_reopened'
            ];

            if (!isset($statusMessages[$newStatus])) return;

            $message = $statusMessages[$newStatus] . $defect['title'];
            $linkUrl = "view_defect.php?id={$defectId}";

            // Notify the defect reporter
            $reporterStmt = $this->db->prepare("SELECT reported_by FROM defects WHERE id = ?");
            $reporterStmt->execute([$defectId]);
            $reporterId = $reporterStmt->fetchColumn();

            if ($reporterId && $reporterId != $changedBy) {
                $this->createNotification($reporterId, $types[$newStatus], $message, $linkUrl);
            }

            // Notify assigned user if different from reporter and changer
            if ($assignedTo && $assignedTo != $changedBy && $assignedTo != $reporterId) {
                $this->createNotification($assignedTo, $types[$newStatus], $message, $linkUrl);
            }

            // Notify managers and admins
            $managersStmt = $this->db->prepare("
                SELECT id FROM users WHERE user_type IN ('admin', 'manager') AND id != ?
            ");
            $managersStmt->execute([$changedBy]);
            $managers = $managersStmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($managers as $managerId) {
                $this->createNotification($managerId, $types[$newStatus], $message, $linkUrl);
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