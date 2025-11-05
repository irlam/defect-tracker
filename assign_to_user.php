<?php
/**
 * assign_to_user.php
 * Assignment operations centre for directing defects to responsible users.
 */

ini_set('output_buffering', '1');
ob_start();

error_reporting(0);
// ini_set('display_errors', 1);
// ini_set('log_errors', 1);
// ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'config/constants.php';
require_once 'includes/navbar.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

date_default_timezone_set('Europe/London');

$pageTitle = 'Assign Defects';
$currentUser = $_SESSION['username'] ?? 'user';
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $currentUser));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'user'));
$currentTimestamp = date('d/m/Y H:i');
$currentDateTimeIso = date('c');
$currentDateTime = date('Y-m-d H:i:s');

$message = '';
$messageType = '';

$sessionSuccessMessage = $_SESSION['success_message'] ?? null;
$sessionErrorMessage = $_SESSION['error_message'] ?? null;
$sessionWarningMessage = $_SESSION['warning_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message'], $_SESSION['warning_message']);

$projectFilter = $_GET['project'] ?? 'all';
$contractorFilter = $_GET['contractor'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$searchTerm = trim($_GET['search'] ?? '');

$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$recordsPerPage = 10;
$offset = ($page - 1) * $recordsPerPage;

$projects = $contractors = $statuses = $priorities = $defects = $availableUsers = [];
$totalRecords = 0;
$totalPages = 0;
$startRecord = 0;
$endRecord = 0;
$lastUpdateDisplay = 'No updates recorded';
$totalDefects = 0;
$assignedDefects = 0;
$unassignedDefects = 0;
$criticalDefects = 0;
$overdueDefects = 0;
$activeDefects = 0;
$projectCount = 0;
$contractorCount = 0;

$db = null;
$navbar = null;

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($currentUserId > 0 && isset($_SESSION['username'])) {
        $navbar = new Navbar($db, $currentUserId, $_SESSION['username']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'assign_single') {
            $defectId = isset($_POST['defect_id']) ? filter_var($_POST['defect_id'], FILTER_VALIDATE_INT) : null;
            $assignedToUserId = isset($_POST['assigned_to']) ? filter_var($_POST['assigned_to'], FILTER_VALIDATE_INT) : null;

            if (!$defectId || !$assignedToUserId) {
                $message = 'Missing or invalid assignment details.';
                $messageType = 'warning';
            } else {
                $db->beginTransaction();

                try {
                    $deleteStmt = $db->prepare('DELETE FROM defect_assignments WHERE defect_id = :defect_id');
                    $deleteStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                    $deleteStmt->execute();

                    $insertStmt = $db->prepare('INSERT INTO defect_assignments (defect_id, user_id, assigned_by, assigned_at) VALUES (:defect_id, :user_id, :assigned_by, :assigned_at)');
                    $insertStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                    $insertStmt->bindValue(':user_id', $assignedToUserId, PDO::PARAM_INT);
                    $insertStmt->bindValue(':assigned_by', $currentUserId, PDO::PARAM_INT);
                    $insertStmt->bindValue(':assigned_at', $currentDateTime);
                    $insertStmt->execute();

                    $userStmt = $db->prepare('SELECT u.username, u.first_name, u.last_name, c.company_name AS contractor_name FROM users u LEFT JOIN contractors c ON u.contractor_id = c.id WHERE u.id = :user_id');
                    $userStmt->bindValue(':user_id', $assignedToUserId, PDO::PARAM_INT);
                    $userStmt->execute();
                    $assignedUser = $userStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                    $defectStmt = $db->prepare('SELECT c.company_name FROM defects d LEFT JOIN contractors c ON d.contractor_id = c.id WHERE d.id = :defect_id');
                    $defectStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                    $defectStmt->execute();
                    $defectDetails = $defectStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                    $activityStmt = $db->prepare('INSERT INTO activity_logs (defect_id, action, user_id, action_type, details, created_at) VALUES (:defect_id, :action, :user_id, :action_type, :details, :created_at)');
                    $actionDescription = 'Defect assigned to user';
                    $details = sprintf(
                        'Defect #%d assigned to user %s %s (%s) by user ID %d. Defect contractor: %s.',
                        $defectId,
                        htmlspecialchars((string) ($assignedUser['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($assignedUser['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($assignedUser['contractor_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
                        $currentUserId,
                        htmlspecialchars((string) ($defectDetails['company_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8')
                    );

                    $activityStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                    $activityStmt->bindValue(':action', $actionDescription, PDO::PARAM_STR);
                    $activityStmt->bindValue(':user_id', $currentUserId, PDO::PARAM_INT);
                    $activityStmt->bindValue(':action_type', 'ASSIGN', PDO::PARAM_STR);
                    $activityStmt->bindValue(':details', $details, PDO::PARAM_STR);
                    $activityStmt->bindValue(':created_at', $currentDateTime, PDO::PARAM_STR);
                    $activityStmt->execute();

                    $db->commit();

                    $message = sprintf(
                        'Defect #%d successfully assigned to %s %s (%s).',
                        $defectId,
                        htmlspecialchars((string) ($assignedUser['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($assignedUser['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($assignedUser['contractor_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8')
                    );
                    $messageType = 'success';
                } catch (Exception $singleError) {
                    $db->rollBack();
                    $message = 'Error assigning defect #' . $defectId . ': ' . $singleError->getMessage();
                    $messageType = 'danger';
                    error_log('Assign Single Error: ' . $singleError->getMessage());
                }
            }
        } elseif ($action === 'bulk_assign') {
            $bulkAssignedToUserId = isset($_POST['bulk_assigned_to']) ? filter_var($_POST['bulk_assigned_to'], FILTER_VALIDATE_INT) : null;
            $defectIds = isset($_POST['defect_ids']) && is_array($_POST['defect_ids']) ? array_map(static function ($value) {
                return filter_var($value, FILTER_VALIDATE_INT);
            }, $_POST['defect_ids']) : [];

            $defectIds = array_filter($defectIds);

            if (empty($defectIds) || !$bulkAssignedToUserId) {
                $message = 'Missing required information for bulk assignment.';
                $messageType = 'warning';
            } else {
                $db->beginTransaction();

                try {
                    $userStmt = $db->prepare('SELECT u.username, u.first_name, u.last_name, c.company_name AS contractor_name FROM users u LEFT JOIN contractors c ON u.contractor_id = c.id WHERE u.id = :user_id');
                    $userStmt->bindValue(':user_id', $bulkAssignedToUserId, PDO::PARAM_INT);
                    $userStmt->execute();
                    $assignedUser = $userStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$assignedUser) {
                        throw new Exception('Selected user for bulk assignment not found.');
                    }

                    $deleteStmt = $db->prepare('DELETE FROM defect_assignments WHERE defect_id = :defect_id');
                    $insertStmt = $db->prepare('INSERT INTO defect_assignments (defect_id, user_id, assigned_by, assigned_at) VALUES (:defect_id, :user_id, :assigned_by, :assigned_at)');
                    $defectInfoStmt = $db->prepare('SELECT c.company_name FROM defects d LEFT JOIN contractors c ON d.contractor_id = c.id WHERE d.id = :defect_id');
                    $activityStmt = $db->prepare('INSERT INTO activity_logs (defect_id, action, user_id, action_type, details, created_at) VALUES (:defect_id, :action, :user_id, :action_type, :details, :created_at)');

                    $successCount = 0;

                    foreach ($defectIds as $defectId) {
                        $deleteStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                        $deleteStmt->execute();

                        $insertStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                        $insertStmt->bindValue(':user_id', $bulkAssignedToUserId, PDO::PARAM_INT);
                        $insertStmt->bindValue(':assigned_by', $currentUserId, PDO::PARAM_INT);
                        $insertStmt->bindValue(':assigned_at', $currentDateTime, PDO::PARAM_STR);
                        $insertStmt->execute();

                        $defectInfoStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                        $defectInfoStmt->execute();
                        $defectCompany = $defectInfoStmt->fetch(PDO::FETCH_ASSOC) ?: [];

                        $logDetails = sprintf(
                            'Defect #%d assigned to user %s %s (%s) by user ID %d via bulk assignment. Defect contractor: %s.',
                            $defectId,
                            htmlspecialchars((string) ($assignedUser['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                            htmlspecialchars((string) ($assignedUser['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                            htmlspecialchars((string) ($assignedUser['contractor_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'),
                            $currentUserId,
                            htmlspecialchars((string) ($defectCompany['company_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8')
                        );

                        $activityStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                        $activityStmt->bindValue(':action', 'Defect assigned to user (Bulk)', PDO::PARAM_STR);
                        $activityStmt->bindValue(':user_id', $currentUserId, PDO::PARAM_INT);
                        $activityStmt->bindValue(':action_type', 'ASSIGN', PDO::PARAM_STR);
                        $activityStmt->bindValue(':details', $logDetails, PDO::PARAM_STR);
                        $activityStmt->bindValue(':created_at', $currentDateTime, PDO::PARAM_STR);
                        $activityStmt->execute();

                        $successCount++;
                    }

                    $db->commit();

                    $message = sprintf(
                        '%d defect(s) successfully assigned to %s %s (%s).',
                        $successCount,
                        htmlspecialchars((string) ($assignedUser['first_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($assignedUser['last_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($assignedUser['contractor_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8')
                    );
                    $messageType = 'success';
                } catch (Exception $bulkError) {
                    $db->rollBack();
                    $message = 'Error during bulk assignment: ' . $bulkError->getMessage();
                    $messageType = 'danger';
                    error_log('Bulk Assign Error: ' . $bulkError->getMessage());
                }
            }
        } elseif ($action === 'assign_contractor_single') {
            $defectId = isset($_POST['defect_id']) ? filter_var($_POST['defect_id'], FILTER_VALIDATE_INT) : null;
            $assignedContractorId = isset($_POST['assigned_contractor']) ? filter_var($_POST['assigned_contractor'], FILTER_VALIDATE_INT) : null;

            if (!$defectId || !$assignedContractorId) {
                $message = 'Missing or invalid contractor assignment details.';
                $messageType = 'warning';
            } else {
                $db->beginTransaction();

                try {
                    $currentContractorStmt = $db->prepare('SELECT contractor_id FROM defects WHERE id = :defect_id FOR UPDATE');
                    $currentContractorStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                    $currentContractorStmt->execute();
                    $currentContractor = $currentContractorStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$currentContractor) {
                        throw new Exception('Defect not found for contractor assignment.');
                    }

                    $previousContractorId = (int) ($currentContractor['contractor_id'] ?? 0);

                    $contractorStmt = $db->prepare("SELECT id, company_name FROM contractors WHERE id = :contractor_id AND status = 'active'");
                    $contractorStmt->bindValue(':contractor_id', $assignedContractorId, PDO::PARAM_INT);
                    $contractorStmt->execute();
                    $newContractor = $contractorStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$newContractor) {
                        throw new Exception('Selected contractor not found or inactive.');
                    }

                    $previousContractorName = 'Unassigned';
                    if ($previousContractorId > 0) {
                        $previousContractorStmt = $db->prepare('SELECT company_name FROM contractors WHERE id = :contractor_id');
                        $previousContractorStmt->bindValue(':contractor_id', $previousContractorId, PDO::PARAM_INT);
                        $previousContractorStmt->execute();
                        $previousContractorName = $previousContractorStmt->fetchColumn() ?: 'Unknown';
                    }

                    $updateContractorStmt = $db->prepare('UPDATE defects SET contractor_id = :contractor_id, updated_at = :updated_at WHERE id = :defect_id');
                    $updateContractorStmt->bindValue(':contractor_id', $assignedContractorId, PDO::PARAM_INT);
                    $updateContractorStmt->bindValue(':updated_at', $currentDateTime, PDO::PARAM_STR);
                    $updateContractorStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                    $updateContractorStmt->execute();

                    $activityStmt = $db->prepare('INSERT INTO activity_logs (defect_id, action, user_id, action_type, details, created_at) VALUES (:defect_id, :action, :user_id, :action_type, :details, :created_at)');
                    $activityDetails = sprintf(
                        'Defect #%d contractor reassigned from %s to %s by user ID %d.',
                        $defectId,
                        htmlspecialchars((string) $previousContractorName, ENT_QUOTES, 'UTF-8'),
                        htmlspecialchars((string) ($newContractor['company_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'),
                        $currentUserId
                    );

                    $activityStmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
                    $activityStmt->bindValue(':action', 'Defect contractor reassigned', PDO::PARAM_STR);
                    $activityStmt->bindValue(':user_id', $currentUserId, PDO::PARAM_INT);
                    $activityStmt->bindValue(':action_type', 'ASSIGN', PDO::PARAM_STR);
                    $activityStmt->bindValue(':details', $activityDetails, PDO::PARAM_STR);
                    $activityStmt->bindValue(':created_at', $currentDateTime, PDO::PARAM_STR);
                    $activityStmt->execute();

                    $db->commit();

                    $message = sprintf(
                        'Defect #%d successfully assigned to contractor %s.',
                        $defectId,
                        htmlspecialchars((string) ($newContractor['company_name'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8')
                    );
                    $messageType = 'success';
                } catch (Exception $contractorError) {
                    $db->rollBack();
                    $message = 'Error assigning contractor for defect #' . $defectId . ': ' . $contractorError->getMessage();
                    $messageType = 'danger';
                    error_log('Assign Contractor Error: ' . $contractorError->getMessage());
                }
            }
        }
    }

    $whereClauses = ['d.deleted_at IS NULL'];
    $queryParams = [];

    if ($projectFilter !== 'all') {
        $whereClauses[] = 'd.project_id = :project_id';
        $queryParams[':project_id'] = (int) $projectFilter;
    }

    if ($contractorFilter !== 'all') {
        $whereClauses[] = 'd.contractor_id = :contractor_id';
        $queryParams[':contractor_id'] = (int) $contractorFilter;
    }

    if ($statusFilter !== 'all') {
        $whereClauses[] = 'd.status = :status';
        $queryParams[':status'] = $statusFilter;
    }

    if ($priorityFilter !== 'all') {
        $whereClauses[] = 'd.priority = :priority';
        $queryParams[':priority'] = $priorityFilter;
    }

    if ($searchTerm !== '') {
        $whereClauses[] = '(d.title LIKE :search OR d.description LIKE :search OR p.name LIKE :search OR c.company_name LIKE :search)';
        $queryParams[':search'] = '%' . $searchTerm . '%';
    }

    $whereSql = implode(' AND ', $whereClauses);

    $countQuery = "SELECT COUNT(*) FROM defects d LEFT JOIN projects p ON d.project_id = p.id LEFT JOIN contractors c ON d.contractor_id = c.id WHERE {$whereSql}";
    $countStmt = $db->prepare($countQuery);
    foreach ($queryParams as $param => $value) {
        $countStmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalRecords = (int) ($countStmt->fetchColumn() ?? 0);
    $totalPages = $totalRecords > 0 ? (int) ceil($totalRecords / $recordsPerPage) : 0;

    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $recordsPerPage;
    }

    if ($page < 1) {
        $page = 1;
        $offset = 0;
    }

    $defectsQuery = "SELECT
                        d.id,
                        d.title,
                        d.status,
                        d.priority,
                        d.created_at,
                        d.due_date,
                        d.description,
                        d.project_id,
                        d.contractor_id,
                        p.name AS project_name,
                        c.company_name AS contractor_name,
                        da.user_id AS assigned_user_id,
                        assigned_user.username AS assigned_username,
                        assigned_user.first_name AS assigned_first_name,
                        assigned_user.last_name AS assigned_last_name,
                        assigned_contractor.company_name AS assigned_contractor_name,
                        creator.username AS created_by_user,
                        creator.first_name AS created_by_first_name,
                        creator.last_name AS created_by_last_name
                      FROM defects d
                      LEFT JOIN projects p ON d.project_id = p.id
                      LEFT JOIN contractors c ON d.contractor_id = c.id
                      LEFT JOIN defect_assignments da ON d.id = da.defect_id
                      LEFT JOIN users assigned_user ON da.user_id = assigned_user.id
                      LEFT JOIN contractors assigned_contractor ON assigned_user.contractor_id = assigned_contractor.id
                      LEFT JOIN users creator ON d.created_by = creator.id
                      WHERE {$whereSql}
                      ORDER BY d.created_at DESC
                      LIMIT :offset, :limit";

    $defectsStmt = $db->prepare($defectsQuery);
    foreach ($queryParams as $param => $value) {
        $defectsStmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $defectsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $defectsStmt->bindValue(':limit', $recordsPerPage, PDO::PARAM_INT);
    $defectsStmt->execute();
    $defects = $defectsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $metricsQuery = "SELECT
                        COUNT(DISTINCT d.id) AS total_defects,
                        SUM(CASE WHEN da.user_id IS NOT NULL THEN 1 ELSE 0 END) AS assigned_defects,
                        SUM(CASE WHEN da.user_id IS NULL THEN 1 ELSE 0 END) AS unassigned_defects,
                        SUM(CASE WHEN d.priority = 'critical' THEN 1 ELSE 0 END) AS critical_defects,
                        SUM(CASE WHEN d.due_date IS NOT NULL AND d.due_date < CURRENT_DATE() AND d.status NOT IN ('closed','accepted','resolved','verified') THEN 1 ELSE 0 END) AS overdue_defects,
                        SUM(CASE WHEN d.status IN ('open','pending','in_progress') THEN 1 ELSE 0 END) AS active_defects,
                        COUNT(DISTINCT d.project_id) AS project_count,
                        COUNT(DISTINCT d.contractor_id) AS contractor_count,
                        MAX(d.updated_at) AS last_update
                      FROM defects d
                      LEFT JOIN projects p ON d.project_id = p.id
                      LEFT JOIN contractors c ON d.contractor_id = c.id
                      LEFT JOIN defect_assignments da ON d.id = da.defect_id
                      WHERE {$whereSql}";

    $metricsStmt = $db->prepare($metricsQuery);
    foreach ($queryParams as $param => $value) {
        $metricsStmt->bindValue($param, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $metricsStmt->execute();
    $metrics = $metricsStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalDefects = (int) ($metrics['total_defects'] ?? 0);
    $assignedDefects = (int) ($metrics['assigned_defects'] ?? 0);
    $unassignedDefects = (int) ($metrics['unassigned_defects'] ?? 0);
    $criticalDefects = (int) ($metrics['critical_defects'] ?? 0);
    $overdueDefects = (int) ($metrics['overdue_defects'] ?? 0);
    $activeDefects = (int) ($metrics['active_defects'] ?? 0);
    $projectCount = (int) ($metrics['project_count'] ?? 0);
    $contractorCount = (int) ($metrics['contractor_count'] ?? 0);

    if (!empty($metrics['last_update'])) {
        $lastUpdateDisplay = date('d M Y, H:i', strtotime($metrics['last_update'])) . ' UK';
    }

    if ($totalRecords > 0) {
        $startRecord = $offset + 1;
        $endRecord = min($offset + count($defects), $totalRecords);
    }

    $projectsQuery = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name";
    $projectsStmt = $db->prepare($projectsQuery);
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $contractorsQuery = "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name";
    $contractorsStmt = $db->prepare($contractorsQuery);
    $contractorsStmt->execute();
    $contractors = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $statusesQuery = "SELECT DISTINCT status FROM defects WHERE status IS NOT NULL AND status != '' ORDER BY status";
    $statusesStmt = $db->prepare($statusesQuery);
    $statusesStmt->execute();
    $statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $prioritiesQuery = "SELECT DISTINCT priority FROM defects WHERE priority IS NOT NULL AND priority != '' ORDER BY FIELD(priority, 'critical','high','medium','low')";
    $prioritiesStmt = $db->prepare($prioritiesQuery);
    $prioritiesStmt->execute();
    $priorities = $prioritiesStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

    $usersQuery = "SELECT u.id, u.username, u.first_name, u.last_name, u.role, u.contractor_id, c.company_name AS contractor_name
                   FROM users u
                   LEFT JOIN contractors c ON u.contractor_id = c.id
                   WHERE u.status = 'active'
                   ORDER BY c.company_name, u.first_name, u.last_name";

    $usersStmt = $db->prepare($usersQuery);
    $usersStmt->execute();
    $availableUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $message = 'Error loading assignment console: ' . $e->getMessage();
    $messageType = 'danger';
    error_log('Assign To User Error: ' . $e->getMessage());
    $projects = $contractors = $statuses = $priorities = $defects = $availableUsers = [];
    $totalRecords = $totalPages = $startRecord = $endRecord = 0;
    $totalDefects = $assignedDefects = $unassignedDefects = $criticalDefects = $overdueDefects = $activeDefects = 0;
    $projectCount = $contractorCount = 0;
    $lastUpdateDisplay = 'No updates recorded';
}

if (!function_exists('formatUkDateTimeDisplay')) {
    function formatUkDateTimeDisplay(?string $value, string $format = 'd M Y, H:i'): string
    {
        if (empty($value)) {
            return 'Not set';
        }

        try {
            $date = new DateTime($value, new DateTimeZone('Europe/London'));
            return $date->format($format);
        } catch (Exception $exception) {
            error_log('Date format error: ' . $exception->getMessage());
            return 'Invalid date';
        }
    }
}

if (!function_exists('correctDefectImagePath')) {
    function correctDefectImagePath(string $path): string
    {
        return (strpos($path, 'uploads/') === 0)
            ? BASE_URL . $path
            : BASE_URL . 'uploads/defect_images/' . ltrim($path, '/');
    }
}

if (!function_exists('fetchDefectImages')) {
    function fetchDefectImages(PDO $db, int $defectId): array
    {
        $stmt = $db->prepare('SELECT file_path FROM defect_images WHERE defect_id = :defect_id');
        $stmt->bindValue(':defect_id', $defectId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }
}

$filterParams = http_build_query([
    'project' => $projectFilter,
    'contractor' => $contractorFilter,
    'status' => $statusFilter,
    'priority' => $priorityFilter,
    'search' => $searchTerm,
]);

$formActionQuery = $filterParams;
if (!empty($formActionQuery)) {
    $formActionQuery .= '&';
}
$formActionQuery .= 'page=' . $page;

$projectsLookup = [];
foreach ($projects as $project) {
    $projectsLookup[$project['id']] = $project['name'];
}

$contractorsLookup = [];
foreach ($contractors as $contractorItem) {
    $contractorsLookup[$contractorItem['id']] = $contractorItem['company_name'];
}

$filtersApplied = $projectFilter !== 'all'
    || $contractorFilter !== 'all'
    || $statusFilter !== 'all'
    || $priorityFilter !== 'all'
    || $searchTerm !== '';

$filterSummaryParts = [];

if ($projectFilter !== 'all' && isset($projectsLookup[(int) $projectFilter])) {
    $filterSummaryParts[] = 'Project: ' . $projectsLookup[(int) $projectFilter];
}

if ($contractorFilter !== 'all' && isset($contractorsLookup[(int) $contractorFilter])) {
    $filterSummaryParts[] = 'Contractor: ' . $contractorsLookup[(int) $contractorFilter];
}

if ($statusFilter !== 'all') {
    $filterSummaryParts[] = 'Status: ' . ucwords(str_replace('_', ' ', $statusFilter));
}

if ($priorityFilter !== 'all') {
    $filterSummaryParts[] = 'Priority: ' . ucfirst($priorityFilter);
}

if ($searchTerm !== '') {
    $filterSummaryParts[] = 'Search: "' . htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') . '"';
}

$filterSummary = $filtersApplied ? implode(' • ', $filterSummaryParts) : 'Showing all defects matching the assignment view';

$assignmentMetrics = [
    [
        'title' => 'Matching Defects',
        'subtitle' => 'Current filtered scope',
        'icon' => 'bx-target-lock',
        'class' => 'report-metric-card--total',
        'value' => $totalDefects,
        'description' => 'Total items in scope',
        'description_icon' => 'bx-slider-alt',
    ],
    [
        'title' => 'Awaiting Assignment',
        'subtitle' => 'Not yet allocated',
        'icon' => 'bx-user-x',
        'class' => 'report-metric-card--overdue',
        'value' => $unassignedDefects,
        'description' => 'Needs handover',
        'description_icon' => 'bx-time-five',
    ],
    [
        'title' => 'Assigned To Users',
        'subtitle' => 'Live workload',
        'icon' => 'bx-user-check',
        'class' => 'report-metric-card--open',
        'value' => $assignedDefects,
        'description' => 'Currently owned',
        'description_icon' => 'bx-group',
    ],
    [
        'title' => 'Critical Priority',
        'subtitle' => 'Highest urgency',
        'icon' => 'bx-error',
        'class' => 'report-metric-card--critical',
        'value' => $criticalDefects,
        'description' => 'Requires rapid action',
        'description_icon' => 'bx-radar',
    ],
    [
        'title' => 'Overdue Items',
        'subtitle' => 'Past due dates',
        'icon' => 'bx-timer',
        'class' => 'report-metric-card--overdue',
        'value' => $overdueDefects,
        'description' => 'Prioritise recovery',
        'description_icon' => 'bx-alarm-exclamation',
    ],
];

$statusBadgeMap = [
    'open' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'pending' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'in_progress' => 'badge rounded-pill bg-info-subtle text-info-emphasis',
    'accepted' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'verified' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
    'closed' => 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis',
    'rejected' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis',
    'completed' => 'badge rounded-pill bg-success-subtle text-success-emphasis',
];

$priorityBadgeMap = [
    'critical' => 'badge rounded-pill bg-danger-subtle text-danger-emphasis',
    'high' => 'badge rounded-pill bg-warning-subtle text-warning-emphasis',
    'medium' => 'badge rounded-pill bg-primary-subtle text-primary-emphasis',
    'low' => 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Assignment console - Defect Tracker">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars($currentDateTimeIso, ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
    <link rel="shortcut icon" href="/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <link href="css/app.css" rel="stylesheet">
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>

    <main class="tool-page container-xl py-4">
        <header class="tool-header mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Assignment Operations Centre</h1>
                    <p class="text-muted mb-0">Prioritise, assign, and balance workload across the delivery team.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-label me-1'></i><?php echo htmlspecialchars($currentUserRoleSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><span data-report-time><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?></span> UK</span>
                </div>
            </div>
        </header>

        <?php
        $calloutQueue = [];

        if ($message) {
            $calloutQueue[] = [
                'type' => $messageType ?: 'info',
                'text' => $message,
            ];
        }
        if ($sessionSuccessMessage) {
            $calloutQueue[] = ['type' => 'success', 'text' => $sessionSuccessMessage];
        }
        if ($sessionErrorMessage) {
            $calloutQueue[] = ['type' => 'danger', 'text' => $sessionErrorMessage];
        }
        if ($sessionWarningMessage) {
            $calloutQueue[] = ['type' => 'warning', 'text' => $sessionWarningMessage];
        }
        ?>
        <?php foreach ($calloutQueue as $callout): ?>
            <?php
            $calloutClass = 'system-callout--info';
            if ($callout['type'] === 'success') {
                $calloutClass = 'system-callout--success';
            } elseif ($callout['type'] === 'danger') {
                $calloutClass = 'system-callout--danger';
            } elseif ($callout['type'] === 'warning') {
                $calloutClass = 'system-callout--warning';
            }
            ?>
            <div class="system-callout <?php echo $calloutClass; ?> mb-4" role="status">
                <div class="system-callout__icon"><i class='bx bx-info-circle'></i></div>
                <div>
                    <p class="system-callout__body mb-0"><?php echo htmlspecialchars($callout['text'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
        <?php endforeach; ?>

        <section class="mb-5">
            <div class="report-metrics-grid">
                <?php foreach ($assignmentMetrics as $metric): ?>
                    <article class="report-metric-card <?php echo htmlspecialchars($metric['class'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="report-metric-card__icon">
                            <i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                        </div>
                        <div class="report-metric-card__content">
                            <h3 class="report-metric-card__title"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <?php if (!empty($metric['subtitle'])): ?>
                                <p class="report-metric-card__subtitle mb-2"><?php echo htmlspecialchars($metric['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                            <p class="report-metric-card__value mb-1"><?php echo number_format((int) $metric['value']); ?></p>
                            <p class="report-metric-card__description mb-0">
                                <i class='bx <?php echo htmlspecialchars($metric['description_icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                                <span><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </p>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="mb-5">
            <div class="row g-4 align-items-stretch">
                <div class="col-xl-5">
                    <article class="system-tool-card h-100">
                        <div class="system-tool-card__icon">
                            <i class='bx bx-pulse'></i>
                        </div>
                        <div class="system-tool-card__body">
                            <span class="system-tool-card__tag system-tool-card__tag--insight">Assignment health</span>
                            <h2 class="system-tool-card__title">Live Work Bank</h2>
                            <p class="system-tool-card__description">Monitor load across projects and delivery partners before assigning anew.</p>
                            <span class="system-tool-card__stat"><?php echo number_format($assignedDefects); ?></span>
                            <p class="text-muted small mb-3">Currently owned by named users.</p>
                            <ul class="list-unstyled text-muted small mb-0 d-flex flex-column gap-1">
                                <li><i class='bx bx-building-house me-1'></i><?php echo number_format($projectCount); ?> projects in view</li>
                                <li><i class='bx bx-user-voice me-1'></i><?php echo number_format($contractorCount); ?> originating contractors</li>
                                <li><i class='bx bx-refresh me-1'></i><?php echo htmlspecialchars($lastUpdateDisplay, ENT_QUOTES, 'UTF-8'); ?></li>
                            </ul>
                        </div>
                    </article>
                </div>
                <div class="col-xl-7">
                    <div class="system-callout system-callout--info h-100" role="status">
                        <div class="system-callout__icon"><i class='bx bx-target-lock'></i></div>
                        <div>
                            <h2 class="system-callout__title">Immediate Focus</h2>
                            <p class="system-callout__body mb-3">Direct attention to items without an owner and high-risk priorities before progressing new work.</p>
                            <div class="d-flex flex-wrap gap-3 text-muted small mb-0">
                                <span><i class='bx bx-user-x me-1'></i><?php echo number_format($unassignedDefects); ?> unassigned</span>
                                <span><i class='bx bx-error me-1'></i><?php echo number_format($criticalDefects); ?> critical</span>
                                <span><i class='bx bx-timer me-1'></i><?php echo number_format($overdueDefects); ?> overdue</span>
                                <span><i class='bx bx-run me-1'></i><?php echo number_format($activeDefects); ?> active</span>
                            </div>
                            <p class="text-muted small mb-0 mt-3"><i class='bx bx-slider-alt me-1'></i><?php echo htmlspecialchars($filterSummary, ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="filter-panel no-print mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <div>
                    <h2 class="h6 mb-1 text-uppercase text-muted">Filters</h2>
                    <p class="text-muted small mb-0">Refine by project, contractor, or progress to target the next assignment.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-sm btn-outline-light" href="assign_to_user.php"><i class='bx bx-reset'></i> Reset</a>
                </div>
            </div>
            <form method="GET" class="filter-panel__form">
                <div class="row g-3">
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label">Project</label>
                        <select name="project" class="form-select">
                            <option value="all">All Projects</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?php echo (int) $project['id']; ?>" <?php echo ((int) $projectFilter === (int) $project['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-3">
                        <label class="form-label">Contractor</label>
                        <select name="contractor" class="form-select">
                            <option value="all">All Contractors</option>
                            <?php foreach ($contractors as $contractor): ?>
                                <option value="<?php echo (int) $contractor['id']; ?>" <?php echo ((int) $contractorFilter === (int) $contractor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="all">All Status</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($statusFilter === $status) ? 'selected' : ''; ?>>
                                    <?php echo ucwords(str_replace('_', ' ', $status)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            <option value="all">All Priorities</option>
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?php echo htmlspecialchars($priority, ENT_QUOTES, 'UTF-8'); ?>" <?php echo ($priorityFilter === $priority) ? 'selected' : ''; ?>>
                                    <?php echo ucfirst($priority); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6 col-xl-2">
                        <label class="form-label">Search</label>
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search defects...">
                            <button type="button" class="btn btn-outline-light" id="clearSearchBtn"><i class='bx bx-x'></i></button>
                        </div>
                    </div>
                </div>
            </form>
        </section>

        <div class="assignment-toolbar d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div class="text-muted small">
                <?php if ($totalRecords > 0): ?>
                    Showing <?php echo number_format($startRecord); ?>–<?php echo number_format($endRecord); ?> of <?php echo number_format($totalRecords); ?> results
                <?php else: ?>
                    No defects found for the current filters
                <?php endif; ?>
                <?php if (!empty($lastUpdateDisplay) && $lastUpdateDisplay !== 'No updates recorded'): ?>
                    <span class="ms-2"><i class='bx bx-refresh me-1'></i>Last update <?php echo htmlspecialchars($lastUpdateDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex gap-2 align-items-center no-print">
                <button id="toggleSelectAllBtn" type="button" class="btn btn-sm btn-outline-light">
                    <i class='bx bx-select-multiple'></i> Select All on Page
                </button>
            </div>
        </div>

        <section id="bulkAssignmentPanel" class="system-callout system-callout--active mb-5 d-none">
            <div class="system-callout__icon"><i class='bx bx-task'></i></div>
            <div class="flex-grow-1">
                <h2 class="system-callout__title mb-2">Bulk Assign Selected Defects</h2>
                <p class="system-callout__body mb-3">Apply the same assignee to multiple defects in one action. Ideal for onboarding a new contractor or clearing a queue.</p>
                <form id="bulkAssignForm" method="POST" action="assign_to_user.php?<?php echo htmlspecialchars($formActionQuery, ENT_QUOTES, 'UTF-8'); ?>" class="row g-3 align-items-center">
                    <input type="hidden" name="action" value="bulk_assign">
                    <div class="col-12 col-lg-4">
                        <div class="text-muted small"><i class='bx bx-check-square me-1'></i><span id="selectedCount">0</span> defects selected</div>
                    </div>
                    <div class="col-12 col-lg-5">
                        <select name="bulk_assigned_to" class="form-select" required>
                            <option value="">Select user...</option>
                            <?php foreach ($availableUsers as $user): ?>
                                <option value="<?php echo (int) $user['id']; ?>">
                                    <?php echo htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($user['contractor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-lg-3 d-grid d-lg-flex gap-2">
                        <button type="submit" class="btn btn-success flex-grow-1"><i class='bx bx-user-plus'></i> Assign Selected</button>
                    </div>
                    <div id="selectedDefectIds"></div>
                </form>
            </div>
        </section>

        <section class="mb-5">
            <div class="card border-0">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                    <div>
                        <h2 class="h5 mb-1"><i class='bx bx-grid-alt me-2'></i>Defect Assignment Register</h2>
                        <p class="text-muted small mb-0">Assign, reassign, and review current ownership.</p>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="text-muted small">Page <?php echo number_format($page); ?> of <?php echo number_format($totalPages); ?></div>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($defects)): ?>
                        <div class="system-callout system-callout--info" role="status">
                            <div class="system-callout__icon"><i class='bx bx-search-alt-2'></i></div>
                            <div>
                                <h2 class="system-callout__title">No Defects Found</h2>
                                <p class="system-callout__body mb-0">Adjust your filters or reset to view the full defect log.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive defect-table">
                            <table class="table table-dark table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th scope="col" class="text-center" style="width: 48px;">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAllCheckbox" title="Select/Deselect all on this page">
                                            </div>
                                        </th>
                                        <th scope="col">Defect</th>
                                        <th scope="col">Project</th>
                                        <th scope="col">Contractor</th>
                                        <th scope="col" class="text-center">Status</th>
                                        <th scope="col" class="text-center">Priority & Due</th>
                                        <th scope="col">Assigned User</th>
                                        <th scope="col" class="text-center no-print">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($defects as $defect): ?>
                                        <?php
                                        $defectId = (int) ($defect['id'] ?? 0);
                                        $statusKey = strtolower($defect['status'] ?? '');
                                        $priorityKey = strtolower($defect['priority'] ?? '');
                                        $statusBadgeClass = $statusBadgeMap[$statusKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
                                        $priorityBadgeClass = $priorityBadgeMap[$priorityKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
                                        $createdDisplay = formatUkDateTimeDisplay($defect['created_at']);
                                        $dueDate = $defect['due_date'] ?? null;
                                        $dueDateDisplay = $dueDate ? formatUkDateTimeDisplay($dueDate, 'd M Y') : 'No due date';
                                        $isOverdue = false;
                                        if (!empty($dueDate)) {
                                            $dueTimestamp = strtotime($dueDate . ' 23:59:59');
                                            $isOverdue = $dueTimestamp !== false && $dueTimestamp < time() && !in_array($statusKey, ['closed', 'accepted', 'verified', 'resolved', 'completed'], true);
                                        }
                                        $assignedUserDisplay = '<span class="text-muted">Unassigned</span>';
                                        if (!empty($defect['assigned_user_id'])) {
                                            $assignedName = trim(($defect['assigned_first_name'] ?? '') . ' ' . ($defect['assigned_last_name'] ?? ''));
                                            if ($assignedName === '') {
                                                $assignedName = $defect['assigned_username'] ?? 'Unknown';
                                            }
                                            $assignedUserDisplay = htmlspecialchars($assignedName, ENT_QUOTES, 'UTF-8');
                                            if (!empty($defect['assigned_contractor_name'])) {
                                                $assignedUserDisplay .= ' <span class="text-muted">(' . htmlspecialchars($defect['assigned_contractor_name'], ENT_QUOTES, 'UTF-8') . ')</span>';
                                            }
                                        }
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <div class="form-check">
                                                    <input class="form-check-input defect-checkbox" type="checkbox" value="<?php echo $defectId; ?>" id="defect_<?php echo $defectId; ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="fw-semibold">#<?php echo $defectId; ?> · <?php echo htmlspecialchars($defect['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="text-muted small">Created <?php echo htmlspecialchars($createdDisplay, ENT_QUOTES, 'UTF-8'); ?></div>
                                            </td>
                                            <td><?php echo htmlspecialchars($defect['project_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td><?php echo htmlspecialchars($defect['contractor_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td class="text-center">
                                                <span class="<?php echo $statusBadgeClass; ?>"><?php echo ucwords(str_replace('_', ' ', $defect['status'] ?? 'Unknown')); ?></span>
                                            </td>
                                            <td class="text-center">
                                                <div class="d-flex flex-column gap-1 align-items-center">
                                                    <span class="<?php echo $priorityBadgeClass; ?>"><?php echo ucfirst($priorityKey ?: 'n/a'); ?></span>
                                                    <span class="badge rounded-pill <?php echo $isOverdue ? 'bg-danger-subtle text-danger-emphasis' : 'bg-secondary-subtle text-secondary-emphasis'; ?>">
                                                        <i class='bx bx-calendar me-1'></i><?php echo htmlspecialchars($dueDateDisplay, ENT_QUOTES, 'UTF-8'); ?>
                                                        <?php if ($isOverdue): ?>
                                                            <i class='bx bx-error-circle ms-1'></i>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><?php echo $assignedUserDisplay; ?></td>
                                            <td class="text-center no-print">
                                                <div class="d-flex flex-column gap-2">
                                                    <form method="POST" action="assign_to_user.php?<?php echo htmlspecialchars($formActionQuery, ENT_QUOTES, 'UTF-8'); ?>" class="d-flex gap-2 flex-column flex-lg-row align-items-stretch">
                                                        <input type="hidden" name="action" value="assign_contractor_single">
                                                        <input type="hidden" name="defect_id" value="<?php echo $defectId; ?>">
                                                        <select name="assigned_contractor" class="form-select form-select-sm" required>
                                                            <option value="">Assign contractor...</option>
                                                            <?php foreach ($contractors as $contractorOption): ?>
                                                                <?php $contractorIdOption = (int) ($contractorOption['id'] ?? 0); ?>
                                                                <option value="<?php echo $contractorIdOption; ?>" <?php echo $contractorIdOption === (int) ($defect['contractor_id'] ?? 0) ? 'selected' : ''; ?>>
                                                                    <?php echo htmlspecialchars($contractorOption['company_name'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-secondary"><i class='bx bx-buildings'></i></button>
                                                    </form>
                                                    <form method="POST" action="assign_to_user.php?<?php echo htmlspecialchars($formActionQuery, ENT_QUOTES, 'UTF-8'); ?>" class="d-flex gap-2 flex-column flex-lg-row align-items-stretch">
                                                        <input type="hidden" name="action" value="assign_single">
                                                        <input type="hidden" name="defect_id" value="<?php echo $defectId; ?>">
                                                        <select name="assigned_to" class="form-select form-select-sm" required>
                                                            <option value="">Assign to...</option>
                                                            <?php foreach ($availableUsers as $user): ?>
                                                                <?php
                                                                $userId = (int) ($user['id'] ?? 0);
                                                                $userDisplayName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                                                                if ($userDisplayName === '') {
                                                                    $userDisplayName = $user['username'] ?? 'User ' . $userId;
                                                                }
                                                                $userContractor = $user['contractor_name'] ?? 'N/A';
                                                                $isSameContractor = !empty($defect['contractor_id']) && (int) $defect['contractor_id'] === (int) ($user['contractor_id'] ?? 0);
                                                                ?>
                                                                <option value="<?php echo $userId; ?>" <?php echo ($userId === (int) ($defect['assigned_user_id'] ?? 0)) ? 'selected' : ''; ?> <?php echo $isSameContractor ? 'data-highlight="true"' : ''; ?>>
                                                                    <?php echo htmlspecialchars($userDisplayName, ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($userContractor, ENT_QUOTES, 'UTF-8'); ?>)<?php echo $isSameContractor ? ' ★' : ''; ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <button type="submit" class="btn btn-sm btn-primary"><i class='bx bx-user-check'></i></button>
                                                    </form>
                                                    <button type="button" class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#defectModal<?php echo $defectId; ?>">
                                                        <i class='bx bx-show-alt'></i> View Details
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Assignment pagination" class="mt-4">
                                <ul class="pagination pagination-sm justify-content-center flex-wrap">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo max(1, $page - 1); ?>&<?php echo htmlspecialchars($filterParams, ENT_QUOTES, 'UTF-8'); ?>" tabindex="-1">Previous</a>
                                    </li>
                                    <?php
                                    $range = 2;
                                    $startPage = max(1, $page - $range);
                                    $endPage = min($totalPages, $page + $range);
                                    if ($startPage > 1) {
                                        echo '<li class="page-item"><a class="page-link" href="?page=1&' . htmlspecialchars($filterParams, ENT_QUOTES, 'UTF-8') . '">1</a></li>';
                                        if ($startPage > 2) {
                                            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                        }
                                    }
                                    for ($i = $startPage; $i <= $endPage; $i++) {
                                        $active = $page === $i ? ' active' : '';
                                        echo '<li class="page-item' . $active . '"><a class="page-link" href="?page=' . $i . '&' . htmlspecialchars($filterParams, ENT_QUOTES, 'UTF-8') . '">' . $i . '</a></li>';
                                    }
                                    if ($endPage < $totalPages) {
                                        if ($endPage < $totalPages - 1) {
                                            echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
                                        }
                                        echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&' . htmlspecialchars($filterParams, ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a></li>';
                                    }
                                    ?>
                                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo min($totalPages, $page + 1); ?>&<?php echo htmlspecialchars($filterParams, ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <?php if (!empty($defects)): ?>
        <?php foreach ($defects as $defect): ?>
            <?php
            $defectId = (int) ($defect['id'] ?? 0);
            $images = isset($db) ? fetchDefectImages($db, $defectId) : [];
            $statusKey = strtolower($defect['status'] ?? '');
            $priorityKey = strtolower($defect['priority'] ?? '');
            $statusBadgeClass = $statusBadgeMap[$statusKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
            $priorityBadgeClass = $priorityBadgeMap[$priorityKey] ?? 'badge rounded-pill bg-secondary-subtle text-secondary-emphasis';
            $assignedUserDisplay = 'Unassigned';
            if (!empty($defect['assigned_user_id'])) {
                $assignedName = trim(($defect['assigned_first_name'] ?? '') . ' ' . ($defect['assigned_last_name'] ?? ''));
                if ($assignedName === '') {
                    $assignedName = $defect['assigned_username'] ?? 'Unknown';
                }
                $assignedUserDisplay = $assignedName;
                if (!empty($defect['assigned_contractor_name'])) {
                    $assignedUserDisplay .= ' (' . $defect['assigned_contractor_name'] . ')';
                }
            }
            ?>
            <div class="modal fade" id="defectModal<?php echo $defectId; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Defect #<?php echo $defectId; ?> Assignment Detail</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-4">
                                <div class="col-xl-8">
                                    <h5><?php echo htmlspecialchars($defect['title'] ?? 'Untitled', ENT_QUOTES, 'UTF-8'); ?></h5>
                                    <p class="text-muted">Status: <span class="<?php echo $statusBadgeClass; ?>"><?php echo ucwords(str_replace('_', ' ', $defect['status'] ?? 'Unknown')); ?></span></p>
                                    <div class="bg-dark-subtle text-dark-emphasis rounded-3 p-3 mb-4">
                                        <h6 class="text-uppercase text-muted small mb-2">Description</h6>
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($defect['description'] ?? 'No description provided.', ENT_QUOTES, 'UTF-8')); ?></p>
                                    </div>
                                    <div>
                                        <h6 class="text-uppercase text-muted small mb-3">Attachments</h6>
                                        <?php if (empty($images)): ?>
                                            <p class="text-muted small">No images attached.</p>
                                        <?php else: ?>
                                            <div class="row g-3">
                                                <?php foreach ($images as $imagePath): ?>
                                                    <div class="col-6 col-md-4">
                                                        <div class="ratio ratio-16x9">
                                                            <img src="<?php echo htmlspecialchars(correctDefectImagePath($imagePath), ENT_QUOTES, 'UTF-8'); ?>" alt="Defect Attachment" class="object-fit-cover rounded-3 border border-secondary-subtle p-1">
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-xl-4">
                                    <div class="card bg-dark border-0 shadow-sm">
                                        <div class="card-body">
                                            <dl class="row mb-0 small text-muted">
                                                <dt class="col-5">Project</dt>
                                                <dd class="col-7"><?php echo htmlspecialchars($defect['project_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></dd>
                                                <dt class="col-5">Contractor</dt>
                                                <dd class="col-7"><?php echo htmlspecialchars($defect['contractor_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8'); ?></dd>
                                                <dt class="col-5">Priority</dt>
                                                <dd class="col-7"><span class="<?php echo $priorityBadgeClass; ?>"><?php echo ucfirst($priorityKey ?: 'n/a'); ?></span></dd>
                                                <dt class="col-5">Due Date</dt>
                                                <dd class="col-7"><?php echo htmlspecialchars($dueDateDisplay, ENT_QUOTES, 'UTF-8'); ?></dd>
                                                <dt class="col-5">Assigned</dt>
                                                <dd class="col-7"><?php echo htmlspecialchars($assignedUserDisplay, ENT_QUOTES, 'UTF-8'); ?></dd>
                                                <dt class="col-5">Created</dt>
                                                <dd class="col-7"><?php echo htmlspecialchars($createdDisplay, ENT_QUOTES, 'UTF-8'); ?></dd>
                                                <dt class="col-5">Created By</dt>
                                                <dd class="col-7"><?php echo htmlspecialchars(trim(($defect['created_by_first_name'] ?? '') . ' ' . ($defect['created_by_last_name'] ?? '')) ?: ($defect['created_by_user'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8'); ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <a href="<?php echo BASE_URL; ?>pdf_exports/pdf-defect.php?defect_id=<?php echo $defectId; ?>" class="btn btn-outline-light" target="_blank"><i class='bx bx-download me-1'></i> Download PDF</a>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const filterForm = document.querySelector('.filter-panel__form');
            if (filterForm) {
                filterForm.querySelectorAll('select').forEach(function(select) {
                    select.addEventListener('change', function() {
                        filterForm.submit();
                    });
                });

                const searchInput = filterForm.querySelector('input[name="search"]');
                let searchTimeout;
                if (searchInput) {
                    searchInput.addEventListener('input', function() {
                        clearTimeout(searchTimeout);
                        searchTimeout = setTimeout(function() {
                            filterForm.submit();
                        }, 500);
                    });
                }

                const clearSearchBtn = document.getElementById('clearSearchBtn');
                if (clearSearchBtn && searchInput) {
                    clearSearchBtn.addEventListener('click', function() {
                        if (searchInput.value !== '') {
                            searchInput.value = '';
                            filterForm.submit();
                        }
                    });
                }
            }

            const defectCheckboxes = Array.from(document.querySelectorAll('.defect-checkbox'));
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const toggleSelectAllBtn = document.getElementById('toggleSelectAllBtn');
            const bulkPanel = document.getElementById('bulkAssignmentPanel');
            const selectedCountSpan = document.getElementById('selectedCount');
            const selectedDefectIdsContainer = document.getElementById('selectedDefectIds');

            function updateBulkPanel() {
                const selected = defectCheckboxes.filter((checkbox) => checkbox.checked);
                const count = selected.length;

                if (selectedCountSpan) {
                    selectedCountSpan.textContent = count;
                }

                if (bulkPanel) {
                    bulkPanel.classList.toggle('d-none', count === 0);
                }

                if (selectedDefectIdsContainer) {
                    selectedDefectIdsContainer.innerHTML = '';
                    selected.forEach((checkbox) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'defect_ids[]';
                        input.value = checkbox.value;
                        selectedDefectIdsContainer.appendChild(input);
                    });
                }

                if (selectAllCheckbox) {
                    if (defectCheckboxes.length === 0) {
                        selectAllCheckbox.checked = false;
                        selectAllCheckbox.indeterminate = false;
                    } else {
                        selectAllCheckbox.checked = count === defectCheckboxes.length;
                        selectAllCheckbox.indeterminate = count > 0 && count < defectCheckboxes.length;
                    }
                }
            }

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    defectCheckboxes.forEach((checkbox) => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updateBulkPanel();
                });
            }

            defectCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', updateBulkPanel);
            });

            if (toggleSelectAllBtn) {
                toggleSelectAllBtn.addEventListener('click', function() {
                    const shouldSelectAll = defectCheckboxes.some((checkbox) => !checkbox.checked);
                    defectCheckboxes.forEach((checkbox) => {
                        checkbox.checked = shouldSelectAll;
                    });
                    updateBulkPanel();
                });
            }

            updateBulkPanel();

            const timestampElement = document.createElement('div');
            timestampElement.className = 'text-muted small mt-4 text-end';
            timestampElement.innerHTML = 'Rendered: <?php echo addslashes($currentTimestamp); ?> UK • User: <?php echo addslashes($displayName); ?>';
            const toolPage = document.querySelector('.tool-page');
            if (toolPage) {
                toolPage.appendChild(timestampElement);
            }
        });
    </script>
</body>
</html>
