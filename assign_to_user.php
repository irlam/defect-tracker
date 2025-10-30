<?php
/**
 * Assign Defects to User Page - Construction Defect Tracker
 *
 * @version 1.1
 * @author irlam (Original), Gemini (Modifications & Comments)
 * @last-modified 2025-04-12 11:45:00 UTC
 *
 * --- File Description ---
 * This script provides an interface for administrators or managers to assign specific defects
 * to individual users (typically contractors or internal staff responsible for fixing defects).
 *
 * Key Functionality:
 * 1.  **Authentication & Session Management:**
 *     - Ensures a user is logged in; otherwise, redirects to `login.php`.
 *     - Starts or resumes the PHP session.
 * 2.  **Defect Listing & Filtering:**
 *     - Displays a paginated list of defects.
 *     - Provides filter options based on Project, Contractor (assigned to the defect), Status, and Priority.
 *     - Includes a search bar to filter defects by title, description, project name, or contractor name.
 *     - Remembers filter/search criteria across page loads using GET parameters.
 * 3.  **Single Defect Assignment:**
 *     - For each defect listed, it shows the currently assigned user (if any).
 *     - Provides a dropdown list of available active users (filtered by role, e.g., 'contractor').
 *     - Allows assigning a single defect to a selected user via a POST request.
 *     - Updates the `defect_assignments` table (deleting old, inserting new).
 *     - Logs the assignment action to the `activity_logs` table with details.
 * 4.  **Bulk Defect Assignment:**
 *     - Allows users to select multiple defects using checkboxes.
 *     - Provides a bulk action section (initially hidden) that appears when defects are selected.
 *     - Includes a dropdown to select a user to assign all selected defects to.
 *     - Processes bulk assignment via a POST request.
 *     - Iterates through selected defect IDs, updates `defect_assignments`, and logs each assignment in `activity_logs`.
 * 5.  **User Interface:**
 *     - Uses Bootstrap 5 for styling and layout.
 *     - Displays defects in a responsive table.
 *     - Includes a hidden details section for each defect (description, images) toggleable via a button.
 *     - Provides visual feedback (success/error messages) after assignment actions.
 *     - Implements pagination for navigating through large lists of defects.
 *     - Includes a loading overlay during form submissions.
 *     - Uses JavaScript for dynamic interactions (toggling details, image modal, bulk selection, filter submission).
 * 6.  **Data Fetching:**
 *     - Retrieves lists of active projects, contractors, defect statuses, and priorities for filter dropdowns.
 *     - Fetches the list of available users (e.g., active contractors) for assignment dropdowns.
 *     - Constructs dynamic SQL queries based on applied filters to fetch the relevant defects.
 *     - Calculates total records for pagination based on filters.
 * 7.  **Time Formatting:**
 *     - Sets the default timezone to 'Europe/London'.
 *     - Displays all relevant dates (`created_at`, `due_date`) in UK format (`d/m/Y H:i`).
 *     - Highlights past due dates.
 *
 * --- Current User Context ---
 * Current Date and Time (UTC): 2025-04-12 11:45:00
 * Current User's Login: irlam (User performing the actions on this page)
 */

// --- PHP Setup ---

// Error Reporting: Display all errors and log them. Adjust display_errors for production.
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors on screen (for development)
ini_set('log_errors', 1); // Log errors to a file
ini_set('error_log', __DIR__ . '/logs/error.log'); // Path to error log file

// Session Management: Start session if not already active. Needed for login state and user ID.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Authentication Check ---
// Redirect to login page if the user is not authenticated.
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit(); // Stop script execution.
}

// Define a constant to check in included files if direct access is attempted (optional security measure).
define('INCLUDED', true);

// --- Include Required Files ---
require_once 'includes/functions.php'; // General helper functions (like formatUKDateTime, BASE_URL).
require_once 'config/database.php'; // Database connection class.
require_once 'includes/navbar.php'; // Navbar rendering class.

// --- Page Configuration & Initialization ---
$pageTitle = 'Assign Defects to a User';
$currentUser = $_SESSION['username']; // Username of the logged-in user.
$message = ''; // Variable for success/error messages after POST actions.
$messageType = ''; // Type of message ('success' or 'danger').

// Set default timezone for date/time functions.
date_default_timezone_set('Europe/London');
// Get the current date and time in SQL format (YYYY-MM-DD HH:MM:SS) for database inserts/updates.
$currentDateTime = date('Y-m-d H:i:s');

// --- Initialize Filtering Variables ---
// Get filter values from GET parameters, defaulting to 'all' or empty.
$projectFilter = $_GET['project'] ?? 'all';
$contractorFilter = $_GET['contractor'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$searchTerm = $_GET['search'] ?? '';

// --- Initialize Pagination Variables ---
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1; // Current page number, ensuring it's at least 1.
$recordsPerPage = 10; // Number of defects to display per page.
$offset = ($page - 1) * $recordsPerPage; // Calculate the offset for SQL LIMIT clause.

// --- Main Logic Block (Database interactions, POST handling) ---
try {
    // Establish database connection.
    $database = new Database();
    $db = $database->getConnection();

    // --- POST Request Handling ---

    // --- Process Single Defect Assignment ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'assign_single') {
        // Validate required POST data.
        if (isset($_POST['defect_id']) && isset($_POST['assigned_to']) && !empty($_POST['assigned_to'])) {
            $defectId = filter_var($_POST['defect_id'], FILTER_VALIDATE_INT);
            $assignedToUserId = filter_var($_POST['assigned_to'], FILTER_VALIDATE_INT); // Renamed for clarity

            // Ensure IDs are valid integers.
            if (!$defectId || !$assignedToUserId) {
                throw new Exception("Invalid defect ID or user ID provided for assignment.");
            }

            // --- Database Transaction for Single Assignment ---
            $db->beginTransaction();
            try {
                // 1. Delete any existing assignment for this defect.
                $deleteQuery = "DELETE FROM defect_assignments WHERE defect_id = :defect_id";
                $deleteStmt = $db->prepare($deleteQuery);
                $deleteStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                $deleteStmt->execute();

                // 2. Insert the new assignment record.
                $insertQuery = "INSERT INTO defect_assignments
                              (defect_id, user_id, assigned_by, assigned_at)
                              VALUES
                              (:defect_id, :user_id, :assigned_by, :assigned_at)";
                $insertStmt = $db->prepare($insertQuery);
                $insertStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                $insertStmt->bindParam(":user_id", $assignedToUserId, PDO::PARAM_INT);
                $insertStmt->bindParam(":assigned_by", $_SESSION['user_id'], PDO::PARAM_INT); // ID of user performing the assignment
                $insertStmt->bindParam(":assigned_at", $currentDateTime); // Use current server time (UK timezone)

                // Execute insertion and check for success.
                if ($insertStmt->execute()) {
                    // --- Fetch Details for Logging ---
                    // Get details of the user being assigned to.
                    $userQuery = "SELECT u.username, u.first_name, u.last_name, c.company_name as contractor_name
                                  FROM users u
                                  LEFT JOIN contractors c ON u.contractor_id = c.id
                                  WHERE u.id = :user_id";
                    $userStmt = $db->prepare($userQuery);
                    $userStmt->bindParam(":user_id", $assignedToUserId, PDO::PARAM_INT);
                    $userStmt->execute();
                    $assignedUser = $userStmt->fetch(PDO::FETCH_ASSOC); // Renamed for clarity

                    // Get details of the defect being assigned (specifically contractor company name).
                    $defectQuery = "SELECT c.company_name
                                    FROM defects d
                                    LEFT JOIN contractors c ON d.contractor_id = c.id
                                    WHERE d.id = :defect_id";
                    $defectStmt = $db->prepare($defectQuery);
                    $defectStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                    $defectStmt->execute();
                    $defectDetails = $defectStmt->fetch(PDO::FETCH_ASSOC);

                    // --- Log Activity ---
                    $activityQuery = "INSERT INTO activity_logs
                                    (defect_id, action, user_id, action_type, details, created_at)
                                    VALUES
                                    (:defect_id, :action, :user_id, 'ASSIGN', :details, :created_at)";
                    $activityStmt = $db->prepare($activityQuery);
                    $actionDescription = "Defect assigned to user"; // Description of the action

                    // Prepare user and contractor names safely for the details string.
                    $user_first_name = htmlspecialchars((string)($assignedUser['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $user_last_name = htmlspecialchars((string)($assignedUser['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                    $user_contractor_name = htmlspecialchars((string)($assignedUser['contractor_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                    $defect_company_name = htmlspecialchars((string)($defectDetails['company_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');

                    // Construct detailed log message.
                    $logDetails = "Defect #{$defectId} assigned to user {$user_first_name} {$user_last_name} ({$user_contractor_name}) " .
                                  "by user ID {$_SESSION['user_id']}. Defect originally associated with contractor: {$defect_company_name}.";

                    // Bind parameters for activity log.
                    $activityStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                    $activityStmt->bindParam(":action", $actionDescription);
                    $activityStmt->bindParam(":user_id", $_SESSION['user_id'], PDO::PARAM_INT); // User who performed the action
                    $activityStmt->bindParam(":details", $logDetails);
                    $activityStmt->bindParam(":created_at", $currentDateTime);
                    $activityStmt->execute();

                    // --- Commit Transaction and Set Success Message ---
                    $db->commit(); // Commit all changes if everything succeeded.
                    $message = "Defect #{$defectId} successfully assigned to {$user_first_name} {$user_last_name} ({$user_contractor_name}).";
                    $messageType = "success";

                } else {
                    // Throw exception if insertion failed.
                    throw new Exception("Database error: Failed to insert defect assignment.");
                }
            } catch (Exception $e) {
                // --- Rollback and Set Error Message ---
                $db->rollBack(); // Undo changes if any error occurred during the transaction.
                $message = "Error assigning defect #{$defectId}: " . $e->getMessage();
                $messageType = "danger";
                error_log("Single Assign Error (Defect ID: {$defectId}, User ID: {$assignedToUserId}): " . $e->getMessage());
            }
        } else {
             // Handle missing POST data.
            $message = "Missing required information for assignment.";
            $messageType = "warning";
        }
    } // --- End Single Assignment Processing ---

    // --- Process Bulk Defect Assignment ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_assign') {
        // Validate required POST data.
        if (isset($_POST['defect_ids']) && is_array($_POST['defect_ids']) && isset($_POST['bulk_assigned_to']) && !empty($_POST['bulk_assigned_to'])) {
            $defectIds = $_POST['defect_ids']; // Array of defect IDs from checkboxes.
            $bulkAssignedToUserId = filter_var($_POST['bulk_assigned_to'], FILTER_VALIDATE_INT); // User to assign to.

            // Ensure at least one defect is selected and the user ID is valid.
            if (empty($defectIds) || !$bulkAssignedToUserId) {
                throw new Exception("No defects selected or invalid user selected for bulk assignment.");
            }

            // --- Database Transaction for Bulk Assignment ---
            $db->beginTransaction();
            $successCount = 0; // Counter for successfully assigned defects.
            try {
                // --- Fetch Details of User Being Assigned To (for logging) ---
                $userQuery = "SELECT u.username, u.first_name, u.last_name, c.company_name as contractor_name
                              FROM users u
                              LEFT JOIN contractors c ON u.contractor_id = c.id
                              WHERE u.id = :user_id";
                $userStmt = $db->prepare($userQuery);
                $userStmt->bindParam(":user_id", $bulkAssignedToUserId, PDO::PARAM_INT);
                $userStmt->execute();
                $assignedUser = $userStmt->fetch(PDO::FETCH_ASSOC); // Renamed for clarity
                if (!$assignedUser) {
                    throw new Exception("Selected user for bulk assignment not found.");
                }
                // Prepare user names for logging.
                $user_first_name = htmlspecialchars((string)($assignedUser['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $user_last_name = htmlspecialchars((string)($assignedUser['last_name'] ?? ''), ENT_QUOTES, 'UTF-8');
                $user_contractor_name = htmlspecialchars((string)($assignedUser['contractor_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');

                // --- Prepare Statements for Efficiency ---
                // Prepare delete, insert, defect details, and activity log statements once outside the loop.
                $deleteStmt = $db->prepare("DELETE FROM defect_assignments WHERE defect_id = :defect_id");
                $insertStmt = $db->prepare("INSERT INTO defect_assignments (defect_id, user_id, assigned_by, assigned_at) VALUES (:defect_id, :user_id, :assigned_by, :assigned_at)");
                $defectStmt = $db->prepare("SELECT c.company_name FROM defects d LEFT JOIN contractors c ON d.contractor_id = c.id WHERE d.id = :defect_id");
                $activityStmt = $db->prepare("INSERT INTO activity_logs (defect_id, action, user_id, action_type, details, created_at) VALUES (:defect_id, :action, :user_id, 'ASSIGN', :details, :created_at)");
                $actionDescription = "Defect assigned to user (Bulk)"; // Action description for log.

                // --- Loop Through Each Selected Defect ID ---
                foreach ($defectIds as $defectIdInput) {
                    $defectId = filter_var($defectIdInput, FILTER_VALIDATE_INT); // Sanitize each ID.
                    if (!$defectId) continue; // Skip if the ID is invalid.

                    // 1. Delete existing assignment for this defect.
                    $deleteStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                    $deleteStmt->execute();

                    // 2. Insert new assignment record.
                    $insertStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                    $insertStmt->bindParam(":user_id", $bulkAssignedToUserId, PDO::PARAM_INT);
                    $insertStmt->bindParam(":assigned_by", $_SESSION['user_id'], PDO::PARAM_INT);
                    $insertStmt->bindParam(":assigned_at", $currentDateTime);

                    // Execute insertion and check for success.
                    if ($insertStmt->execute()) {
                        $successCount++; // Increment success counter.

                        // --- Log Activity for this specific defect ---
                        // Fetch defect's original contractor for log details.
                        $defectStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                        $defectStmt->execute();
                        $defectDetails = $defectStmt->fetch(PDO::FETCH_ASSOC);
                        $defect_company_name = htmlspecialchars((string)($defectDetails['company_name'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');

                        // Construct detailed log message.
                        $logDetails = "Defect #{$defectId} assigned to user {$user_first_name} {$user_last_name} ({$user_contractor_name}) " .
                                      "by user ID {$_SESSION['user_id']} via bulk assignment. Defect originally associated with contractor: {$defect_company_name}.";

                        // Bind parameters and execute activity log insert.
                        $activityStmt->bindParam(":defect_id", $defectId, PDO::PARAM_INT);
                        $activityStmt->bindParam(":action", $actionDescription);
                        $activityStmt->bindParam(":user_id", $_SESSION['user_id'], PDO::PARAM_INT);
                        $activityStmt->bindParam(":details", $logDetails);
                        $activityStmt->bindParam(":created_at", $currentDateTime);
                        $activityStmt->execute();
                    } else {
                        // Log error if a specific defect assignment failed within the bulk operation.
                        error_log("Bulk Assign Error: Failed to insert assignment for Defect ID {$defectId}.");
                        // Optionally: throw new Exception to stop the whole bulk process on first failure.
                    }
                } // --- End Loop Through Defect IDs ---

                // --- Commit Transaction and Set Success Message ---
                $db->commit(); // Commit all successful changes.
                $message = "{$successCount} defect(s) successfully assigned to {$user_first_name} {$user_last_name} ({$user_contractor_name}).";
                $messageType = "success";

            } catch (Exception $e) {
                // --- Rollback and Set Error Message ---
                $db->rollBack(); // Undo all changes if any error occurred during the transaction.
                $message = "Error during bulk assignment: " . $e->getMessage();
                $messageType = "danger";
                error_log("Bulk Assign Error (User ID: {$bulkAssignedToUserId}): " . $e->getMessage());
            }
        } else {
             // Handle missing POST data for bulk action.
            $message = "Missing required information for bulk assignment.";
            $messageType = "warning";
        }
    } // --- End Bulk Assignment Processing ---

    // --- Data Fetching for Display ---

    // --- Get Filter Options for Dropdowns ---
    // Fetch active projects.
    $projectsQuery = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name";
    $projectsStmt = $db->prepare($projectsQuery);
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch active contractors.
    $contractorsQuery = "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name";
    $contractorsStmt = $db->prepare($contractorsQuery);
    $contractorsStmt->execute();
    $contractors = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch distinct defect statuses.
    $statusesQuery = "SELECT DISTINCT status FROM defects WHERE status IS NOT NULL AND status != '' ORDER BY status";
    $statusesStmt = $db->prepare($statusesQuery);
    $statusesStmt->execute();
    $statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN);

    // Fetch distinct defect priorities.
    $prioritiesQuery = "SELECT DISTINCT priority FROM defects WHERE priority IS NOT NULL AND priority != '' ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low')";
    $prioritiesStmt = $db->prepare($prioritiesQuery);
    $prioritiesStmt->execute();
    $priorities = $prioritiesStmt->fetchAll(PDO::FETCH_COLUMN);

    // --- Build Base Query for Fetching Defects ---
    // Select all necessary defect details along with related project, contractor, and assignment info.
    $baseDefectsQuery = "SELECT
                        d.id, d.title, d.status, d.priority, d.created_at, d.due_date, d.description,
                        p.name as project_name, p.id as project_id,
                        c.company_name as contractor_name, c.id as contractor_id,
                        da.user_id as assigned_user_id, -- ID of the user the defect is currently assigned to
                        -- Use COALESCE to handle cases where user/contractor might be NULL
                        COALESCE(u.first_name, 'Unassigned') as assigned_first_name,
                        COALESCE(u.last_name, '') as assigned_last_name,
                        COALESCE(cn.company_name, '') as assigned_contractor_name -- Contractor name of the assigned user
                     FROM defects d
                     LEFT JOIN projects p ON d.project_id = p.id
                     LEFT JOIN contractors c ON d.contractor_id = c.id -- Contractor associated with the defect itself
                     LEFT JOIN defect_assignments da ON d.id = da.defect_id -- Current assignment link
                     LEFT JOIN users u ON da.user_id = u.id -- User assigned via defect_assignments
                     LEFT JOIN contractors cn ON u.contractor_id = cn.id -- Contractor of the assigned user
                     WHERE d.deleted_at IS NULL"; // Exclude soft-deleted defects

    // --- Apply Filters to the Query ---
    $queryParams = []; // Array to hold parameters for prepared statement.
    if ($projectFilter != 'all') {
        $baseDefectsQuery .= " AND d.project_id = :project_id"; // Use d.project_id
        $queryParams[':project_id'] = $projectFilter;
    }
    if ($contractorFilter != 'all') {
        $baseDefectsQuery .= " AND d.contractor_id = :contractor_id"; // Filter by defect's contractor
        $queryParams[':contractor_id'] = $contractorFilter;
    }
    if ($statusFilter != 'all') {
        $baseDefectsQuery .= " AND d.status = :status";
        $queryParams[':status'] = $statusFilter;
    }
    if ($priorityFilter != 'all') {
        $baseDefectsQuery .= " AND d.priority = :priority";
        $queryParams[':priority'] = $priorityFilter;
    }
    // Apply search term filter across multiple relevant fields.
    if (!empty($searchTerm)) {
        $baseDefectsQuery .= " AND (d.title LIKE :search OR d.description LIKE :search OR p.name LIKE :search OR c.company_name LIKE :search)";
        $queryParams[':search'] = '%' . $searchTerm . '%';
    }

    // --- Count Total Records for Pagination ---
    // Need to count rows matching the filters *before* applying LIMIT.
    $countQuery = "SELECT COUNT(*) FROM ({$baseDefectsQuery}) as filtered_defects";
    $countStmt = $db->prepare($countQuery);
    // Bind filter parameters to the count query.
    foreach ($queryParams as $param => $value) {
        $countStmt->bindValue($param, $value);
    }
    $countStmt->execute();
    $totalRecords = (int)$countStmt->fetchColumn(); // Get the total count.
    $totalPages = ceil($totalRecords / $recordsPerPage); // Calculate total pages.

    // Adjust current page if it's out of bounds after filtering.
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $recordsPerPage; // Recalculate offset.
    } elseif ($page < 1) {
        $page = 1;
        $offset = 0;
    }

    // --- Fetch Paginated Defects ---
    // Add ORDER BY and LIMIT clauses to the base query.
    $defectsQuery = $baseDefectsQuery . " ORDER BY d.created_at DESC LIMIT :offset, :records_per_page";
    $defectsStmt = $db->prepare($defectsQuery);

    // Bind filter parameters again for the main query.
    foreach ($queryParams as $param => $value) {
        $defectsStmt->bindValue($param, $value);
    }
    // Bind pagination parameters (LIMIT and OFFSET must be integers).
    $defectsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $defectsStmt->bindValue(':records_per_page', $recordsPerPage, PDO::PARAM_INT);

    $defectsStmt->execute();
    $defects = $defectsStmt->fetchAll(PDO::FETCH_ASSOC); // Fetch the defects for the current page.

    // --- Get Available Users for Assignment Dropdowns ---
    // Fetch active users who have the 'contractor' role (adjust role filter if needed).
    $usersQuery = "SELECT u.id, u.username, u.first_name, u.last_name, u.role,
                          c.company_name as contractor_name, u.contractor_id
                   FROM users u
                   LEFT JOIN contractors c ON u.contractor_id = c.id -- Join to get contractor name
                   WHERE u.status = 'active' -- Only active users
                   -- Filter by role if necessary, e.g., AND u.role = 'contractor' or u.user_type = 'contractor'
                   -- Adjust this condition based on how roles/types define assignable users.
                   -- For now, let's assume any active user is potentially assignable for flexibility.
                   ORDER BY c.company_name, u.first_name, u.last_name"; // Order for dropdown readability.

    $usersStmt = $db->prepare($usersQuery);
    $usersStmt->execute();
    $availableUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Store Current Filters for Pagination Links ---
    // Build a query string containing the current filter parameters to append to pagination links.
    $filterParams = http_build_query([
        'project' => $projectFilter,
        'contractor' => $contractorFilter,
        'status' => $statusFilter,
        'priority' => $priorityFilter,
        'search' => $searchTerm
    ]);

    // --- Logged-in User Info (already in session) ---
    $loggedInUserId = $_SESSION['user_id'];
    $loggedInUsername = $_SESSION['username'];

} catch (Exception $e) {
    // --- Catch-all Exception Handler for Page Load ---
    $message = "Error loading page data: " . $e->getMessage();
    $messageType = "danger";
    error_log("Assign To User Page Error: " . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
    // Initialize arrays to prevent errors in HTML rendering.
    $projects = $contractors = $statuses = $priorities = $defects = $availableUsers = [];
    $totalRecords = $totalPages = 0;
    $filterParams = '';
}

// --- Initialize Navbar ---
// Needs DB connection and user info, done after potential session updates.
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);

// --- Helper function for UK Date Time Formatting (if not already in functions.php) ---
if (!function_exists('formatUKDateTime')) {
    function formatUKDateTime($dateString) {
        if (empty($dateString) || $dateString === null) {
            return '<span class="text-muted">Not set</span>';
        }
        try {
            $date = new DateTime($dateString);
            // Set timezone specifically for formatting output, if needed, though default is already set.
            // $date->setTimezone(new DateTimeZone('Europe/London'));
            return $date->format('d/m/Y H:i');
        } catch (Exception $e) {
            error_log("Error formatting date '{$dateString}': " . $e->getMessage());
            return '<span class="text-danger">Invalid Date</span>';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php // --- HTML Head --- ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <?php // --- CSS Includes --- ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <?php // --- Favicons --- ?>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />

    <?php // --- Internal CSS Styles --- ?>
    <style>
        :root { /* Define color variables */
            --primary-color: #3498db;
            --secondary-color: #9b59b6;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-muted: #6c757d;
            --card-border-radius: 12px;
            --box-shadow: 0 2px 10px 0 rgba(0, 0, 0, 0.075);
        }
        body {
            min-height: 100vh;
            background-color: #f4f7f6; /* Match dashboard background */
            padding-top: 70px; /* Adjust for fixed navbar height */
        }
        /* Apply card styling to the main content container */
        .main-content-card {
             background-color: #ffffff;
             border-radius: var(--card-border-radius);
             box-shadow: var(--box-shadow);
             margin-top: 1.5rem; /* Space below navbar/alerts */
             border: 1px solid #e9ecef;
        }
        .main-content-card .card-header { /* Style header within the card */
             background-color: #ffffff;
             border-bottom: 1px solid #e9ecef;
             padding: 1rem 1.25rem;
             border-radius: var(--card-border-radius) var(--card-border-radius) 0 0;
        }
         .main-content-card .card-header h1 { font-size: 1.5rem; font-weight: 600; } /* Adjust header title */

         .main-content-card .card-body { /* Style body within the card */
             padding: 1.25rem;
         }

        /* Style the filter section like a card header or distinct block */
        .filter-section {
            background-color: #f8f9fa; /* Light background */
            padding: 1rem 1.25rem;
            border-radius: 8px;
            border: 1px solid #dee2e6; /* Slightly darker border */
            margin-bottom: 1.5rem;
        }
         .filter-section .form-label { font-size: 0.8rem; font-weight: 500; color: var(--text-muted); }

        /* Bulk actions section styling */
        .bulk-actions {
            background-color: #e9ecef; /* Slightly darker background */
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: none; /* Initially hidden */
            border: 1px solid #ced4da;
        }

        /* Table styles */
        .table { font-size: 0.875rem; }
        .table thead th {
            background-color: #f8f9fa; /* Light header */
            position: sticky; top: 56px; /* Make header sticky below navbar */
            z-index: 10; /* Ensure above table content */
            border-bottom-width: 1px;
            white-space: nowrap;
            vertical-align: middle;
            font-weight: 600;
        }
        .table td { vertical-align: middle; }
        .table-hover tbody tr:hover { background-color: rgba(0,0,0,0.03); }
        .checkbox-column { width: 40px; text-align: center; }
        .table-actions { min-width: 220px; /* Wider to fit dropdown + button */ }

        /* Assign form styling within table */
        .single-assign-form { display: flex; align-items: center; gap: 0.5rem; }
        .single-assign-form .form-select-sm { flex-grow: 1; } /* Allow dropdown to grow */
        .assign-btn { flex-shrink: 0; } /* Prevent button from shrinking */

        /* Hidden defect details row */
        .defect-detail-row > td { padding: 0 !important; border-top: none !important; }
        .defect-form {
            background-color: #fdfdfd;
            padding: 1.5rem; /* More padding */
            margin-top: 0; /* Remove margin */
            border-radius: 0;
            border: none;
            border-top: 1px dashed #dee2e6; /* Dashed separator */
        }
        .defect-images img {
            max-width: 120px; /* Smaller thumbnails */
            height: 80px; /* Fixed height */
            object-fit: cover;
            margin-right: 0.5rem; margin-bottom: 0.5rem;
            border: 1px solid #ccc; padding: 2px; border-radius: 4px;
            cursor: pointer; transition: all 0.2s;
        }
        .defect-images img:hover { transform: scale(1.05); box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .img-thumbnail { padding: 0.2rem; background-color: #fff; border: 1px solid #dee2e6; }

        /* Pagination styling */
        .pagination-container { margin: 1.5rem 0; }
        .pagination .page-link { font-size: 0.875rem; }
        .pagination .page-item.active .page-link { background-color: var(--primary-color); border-color: var(--primary-color); }

        /* Loading overlay */
        .loading-overlay { /* Style as before */ }
        .spinner { /* Style as before */ }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .table thead { position: static; } /* Disable sticky header on mobile */
            .filter-section .col-md-2, .bulk-actions .col-md-4, .bulk-actions .col-md-2 { margin-bottom: 0.75rem; }
            .table-actions { min-width: auto; }
            .single-assign-form { flex-direction: column; align-items: stretch; }
            .single-assign-form .form-select-sm { margin-bottom: 0.5rem; margin-right: 0 !important; }
        }

        /* Highlight user from same contractor in dropdown */
        select option.fw-bold {
             background-color: #e9f5ff; /* Light blue background */
        }
    </style>
</head>
<body>
    <?php // --- Render Navbar ---
        $navbar->render();
    ?>
    <div style="height: 10px;"></div> <?php // Buffer below navbar ?>

    <!-- Loading Overlay (Initially Hidden) -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

    <?php // --- Main Content Section with Card Styling --- ?>
    <div class="container-fluid">
        <div class="main-content-card">
            <div class="card-header">
                 <?php // --- Page Header --- ?>
                <div>
                    <h1 class="h3 mb-1"><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                    <p class="text-muted mb-0 small">
                        Assign defects to specific users for resolution. Use filters to narrow down the list.
                    </p>
                </div>
                <div>
                    <?php // Optional: Add other header buttons if needed ?>
                </div>
            </div>

            <div class="card-body">
                <?php // --- Display Success/Error Messages --- ?>
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php // --- Filters Section --- ?>
                <div class="filter-section">
                    <form id="filterForm" method="GET" class="row g-3 align-items-end">
                        <?php // Project Filter Dropdown ?>
                        <div class="col-md-2 col-sm-6">
                            <label for="projectFilter" class="form-label">Project</label>
                            <select id="projectFilter" name="project" class="form-select form-select-sm">
                                <option value="all">All Projects</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo $project['id']; ?>" <?php echo ($projectFilter == $project['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($project['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php // Contractor Filter Dropdown ?>
                        <div class="col-md-2 col-sm-6">
                            <label for="contractorFilter" class="form-label">Contractor</label>
                            <select id="contractorFilter" name="contractor" class="form-select form-select-sm">
                                <option value="all">All Contractors</option>
                                <?php foreach ($contractors as $contractor): ?>
                                    <option value="<?php echo $contractor['id']; ?>" <?php echo ($contractorFilter == $contractor['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php // Status Filter Dropdown ?>
                        <div class="col-md-2 col-sm-6">
                            <label for="statusFilter" class="form-label">Status</label>
                            <select id="statusFilter" name="status" class="form-select form-select-sm">
                                <option value="all">All Statuses</option>
                                <?php foreach ($statuses as $status): ?>
                                    <option value="<?php echo $status; ?>" <?php echo ($statusFilter == $status) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php // Priority Filter Dropdown ?>
                        <div class="col-md-2 col-sm-6">
                            <label for="priorityFilter" class="form-label">Priority</label>
                            <select id="priorityFilter" name="priority" class="form-select form-select-sm">
                                <option value="all">All Priorities</option>
                                <?php foreach ($priorities as $priority): ?>
                                    <option value="<?php echo $priority; ?>" <?php echo ($priorityFilter == $priority) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($priority); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php // Search Input ?>
                        <div class="col-md-2 col-sm-6">
                            <label for="searchInput" class="form-label">Search</label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control" id="searchInput" name="search"
                                       placeholder="Keyword..." value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>">
                                <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Clear Search">
                                    <i class='bx bx-x'></i>
                                </button>
                            </div>
                        </div>
                        <?php // Filter Submit Button ?>
                        <div class="col-md-2 col-sm-6">
                            <button type="submit" class="btn btn-primary btn-sm w-100 btn-filter">
                                <i class='bx bx-filter-alt'></i> Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <?php // --- Bulk Actions Section (Initially Hidden) --- ?>
                <div class="bulk-actions" id="bulkActionsSection">
                    <form id="bulkAssignForm" method="POST" class="row g-3 align-items-center">
                        <input type="hidden" name="action" value="bulk_assign">
                        <?php // Selected Count Display ?>
                        <div class="col-md-5">
                            <div class="d-flex align-items-center">
                                <span class="me-2"><i class='bx bx-check-square fs-5 text-primary'></i></span>
                                <span id="selectedCount" class="fw-bold">0</span>&nbsp;defects selected
                            </div>
                        </div>
                        <?php // Bulk Assign User Dropdown ?>
                        <div class="col-md-5">
                            <select name="bulk_assigned_to" class="form-select form-select-sm" required aria-label="Select user for bulk assignment">
                                <option value="">Select User to Assign...</option>
                                <?php foreach ($availableUsers as $user): ?>
                                    <option value="<?php echo $user['id']; ?>">
                                        <?php // Display user name and their contractor ?>
                                        <?php echo htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($user['contractor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . ")"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php // Bulk Assign Submit Button ?>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-success btn-sm w-100">
                                <i class='bx bx-group'></i> Assign Selected
                            </button>
                        </div>
                        <?php // Hidden div to store selected defect IDs for POST submission ?>
                        <div id="selectedDefectIds"></div>
                    </form>
                </div>

                <?php // --- Results Stats and Select All Button --- ?>
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                    <div>
                        <span class="text-muted small">
                            Showing <?php echo min(($page - 1) * $recordsPerPage + 1, $totalRecords); ?> - <?php echo min($page * $recordsPerPage, $totalRecords); ?> of <?php echo $totalRecords; ?> defects
                        </span>
                    </div>
                    <div>
                        <button id="toggleSelectAllBtn" class="btn btn-sm btn-outline-secondary">
                            <i class='bx bx-select-multiple'></i> Select/Deselect All on Page
                        </button>
                    </div>
                </div>

                <?php // --- Defects Table --- ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="sticky-header">
                            <tr>
                                <th class="checkbox-column">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="selectAllCheckbox" title="Select/Deselect all on this page">
                                    </div>
                                </th>
                                <th>Defect</th>
                                <th>Project</th>
                                <th>Contractor</th>
                                <th>Priority</th>
                                <th>Due Date (UK)</th>
                                <th>Status</th>
                                <th>Currently Assigned To</th>
                                <th class="table-actions">Assign To User</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($defects)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        <i class='bx bx-info-circle fs-4 align-middle me-1'></i> No defects match the current filters.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($defects as $defect): ?>
                                    <tr>
                                        <?php // Checkbox for Bulk Selection ?>
                                        <td class="checkbox-column">
                                            <div class="form-check">
                                                <input class="form-check-input defect-checkbox" type="checkbox" value="<?php echo $defect['id']; ?>" id="defect_<?php echo $defect['id']; ?>">
                                            </div>
                                        </td>
                                        <?php // Defect Title and Creation Date ?>
                                        <td>
                                            <div class="fw-bold">
                                                <a href="view_defect.php?id=<?php echo $defect['id']; ?>" target="_blank" title="View Defect Details">
                                                    #<?php echo htmlspecialchars($defect['id'], ENT_QUOTES, 'UTF-8'); ?>: <?php echo htmlspecialchars($defect['title'], ENT_QUOTES, 'UTF-8'); ?>
                                                </a>
                                            </div>
                                            <small class="text-muted">Created: <?php echo formatUKDateTime($defect['created_at']); ?></small>
                                        </td>
                                        <?php // Project Name ?>
                                        <td><?php echo htmlspecialchars($defect['project_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php // Contractor Name (Associated with Defect) ?>
                                        <td><?php echo htmlspecialchars($defect['contractor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <?php // Priority Badge ?>
                                        <td>
                                            <?php
                                            $priorityClassMap = ['critical' => 'danger', 'high' => 'danger', 'medium' => 'warning', 'low' => 'success'];
                                            $priorityClass = $priorityClassMap[strtolower($defect['priority'] ?? '')] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $priorityClass; ?>"><?php echo ucfirst(htmlspecialchars($defect['priority'] ?? 'N/A')); ?></span>
                                        </td>
                                        <?php // Due Date (UK Format) with Overdue Highlighting ?>
                                        <td>
                                            <?php
                                            $dueDateFormatted = formatUKDateTime($defect['due_date']);
                                            $isPastDue = false;
                                            if (!empty($defect['due_date']) && ($dueDateTimestamp = strtotime($defect['due_date'])) !== false) {
                                                $nowTimestamp = strtotime($currentDateTime); // Use current server time
                                                $isPastDue = $dueDateTimestamp < $nowTimestamp && !in_array($defect['status'], ['completed', 'closed', 'verified', 'rejected']); // Check if past due and not closed/rejected
                                            }
                                            echo '<span class="' . ($isPastDue ? 'text-danger fw-bold' : '') . '">' . $dueDateFormatted . '</span>';
                                            if ($isPastDue) {
                                                echo ' <span class="badge bg-danger ms-1">Overdue</span>';
                                            }
                                            ?>
                                        </td>
                                        <?php // Status Badge ?>
                                        <td>
                                            <?php
                                            $statusClassMap = ['open' => 'warning', 'pending' => 'info', 'accepted' => 'success', 'rejected' => 'secondary', 'closed' => 'secondary', 'verified' => 'primary', 'in_progress' => 'primary'];
                                            $statusClass = $statusClassMap[strtolower($defect['status'] ?? '')] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($defect['status'] ?? 'N/A'))); ?></span>
                                        </td>
                                        <?php // Currently Assigned User ?>
                                        <td>
                                            <?php
                                            $assigned_user_display = '<span class="text-muted fst-italic">Unassigned</span>';
                                            if (!empty($defect['assigned_user_id'])) {
                                                $assigned_user_display = htmlspecialchars($defect['assigned_first_name'] ?? '', ENT_QUOTES, 'UTF-8') . ' ' .
                                                                         htmlspecialchars($defect['assigned_last_name'] ?? '', ENT_QUOTES, 'UTF-8') .
                                                                         (!empty($defect['assigned_contractor_name']) ? " (" . htmlspecialchars($defect['assigned_contractor_name'], ENT_QUOTES, 'UTF-8') . ")" : '');
                                            }
                                            echo $assigned_user_display;
                                            ?>
                                        </td>
                                        <?php // Actions Column: Assign Form and View Details Button ?>
                                        <td class="table-actions">
                                            <?php // Form for single assignment ?>
                                            <form action="assign_to_user.php?<?php echo $filterParams; ?>&page=<?php echo $page; // Preserve filters/page ?>" method="POST" class="single-assign-form mb-1">
                                                <input type="hidden" name="defect_id" value="<?php echo $defect['id']; ?>">
                                                <input type="hidden" name="action" value="assign_single">
                                                <select name="assigned_to" class="form-select form-select-sm" required aria-label="Assign user to defect <?php echo $defect['id']; ?>">
                                                    <option value="">Assign to...</option>
                                                    <?php foreach ($availableUsers as $user): ?>
                                                        <?php
                                                        $userDisplay = htmlspecialchars($user['first_name'] ?? '', ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($user['last_name'] ?? '', ENT_QUOTES, 'UTF-8') . " (" . htmlspecialchars($user['contractor_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . ")";
                                                        // Highlight user if they belong to the same contractor as the defect
                                                        $isSameContractor = ($user['contractor_id'] == $defect['contractor_id']);
                                                        ?>
                                                        <option value="<?php echo $user['id']; ?>"
                                                                <?php echo ($user['id'] == $defect['assigned_user_id']) ? 'selected' : ''; // Pre-select current user ?>
                                                                <?php echo $isSameContractor ? 'class="fw-bold"' : ''; // Add class to highlight ?>
                                                        >
                                                            <?php echo $userDisplay; ?>
                                                            <?php echo $isSameContractor ? ' ⭐' : ''; // Add star indicator ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" class="btn btn-primary btn-sm assign-btn" title="Assign selected user">
                                                    <i class='bx bx-user-check'></i>
                                                </button>
                                            </form>
                                            <?php // Button to toggle hidden details row ?>
                                            <button type="button" class="btn btn-outline-secondary btn-sm mt-1 w-100" onclick="toggleDefectForm(<?php echo $defect['id']; ?>)" title="View defect details and images">
                                                <i class='bx bx-detail'></i> Details
                                            </button>
                                        </td>
                                    </tr>
                                    <?php // --- Hidden Row for Defect Details --- ?>
                                    <tr id="defectFormRow_<?php echo $defect['id']; ?>" class="defect-detail-row" style="display: none;">
                                        <td colspan="9"> <?php // Span across all columns ?>
                                            <div class="defect-form">
                                                <div class="row">
                                                    <?php // Left side: Description and Images ?>
                                                    <div class="col-lg-8 mb-3 mb-lg-0">
                                                        <h6 class="mb-2 fw-bold">Description</h6>
                                                        <div class="p-3 bg-light rounded border mb-3">
                                                            <?php echo nl2br(htmlspecialchars($defect['description'] ?? 'No description provided.', ENT_QUOTES, 'UTF-8')); ?>
                                                        </div>

                                                        <h6 class="mb-2 fw-bold">Attachments</h6>
                                                        <div class="defect-images d-flex flex-wrap">
                                                            <?php
                                                            // Fetch images associated with this defect
                                                            $imagesQuery = "SELECT file_path FROM defect_images WHERE defect_id = :defect_id";
                                                            $imageStmt = $db->prepare($imagesQuery);
                                                            $imageStmt->bindParam(":defect_id", $defect['id'], PDO::PARAM_INT);
                                                            $imageStmt->execute();
                                                            $images = $imageStmt->fetchAll(PDO::FETCH_ASSOC);

                                                            if (!empty($images)) {
                                                                foreach ($images as $image) {
                                                                    $imgSrc = (!empty($image['file_path'])) ? htmlspecialchars($image['file_path'], ENT_QUOTES, 'UTF-8') : '';
                                                                    if (!empty($imgSrc)) {
                                                                        echo '<div class="position-relative me-2 mb-2">';
                                                                        // Make image clickable to open modal
                                                                        echo '<img src="' . $imgSrc . '" alt="Defect Image" onclick="showImageModal(\'' . $imgSrc . '\')" class="img-thumbnail">';
                                                                        echo '</div>';
                                                                    }
                                                                }
                                                            } else {
                                                                echo '<p class="text-muted small">No images attached.</p>';
                                                            }
                                                            ?>
                                                        </div>
                                                    </div>
                                                    <?php // Right side: Meta Details ?>
                                                    <div class="col-lg-4">
                                                         <div class="card h-100">
                                                            <div class="card-header bg-light py-2">
                                                                <h6 class="mb-0 small text-uppercase fw-bold">Defect Info</h6>
                                                            </div>
                                                            <div class="card-body p-3">
                                                                <ul class="list-unstyled small">
                                                                    <li class="mb-2 d-flex justify-content-between"><span>ID:</span> <span class="fw-medium">#<?php echo $defect['id']; ?></span></li>
                                                                    <li class="mb-2 d-flex justify-content-between"><span>Project:</span> <span class="text-end"><?php echo htmlspecialchars($defect['project_name'] ?? 'N/A'); ?></span></li>
                                                                    <li class="mb-2 d-flex justify-content-between"><span>Contractor:</span> <span class="text-end"><?php echo htmlspecialchars($defect['contractor_name'] ?? 'N/A'); ?></span></li>
                                                                    <li class="mb-2 d-flex justify-content-between"><span>Status:</span> <span class="badge bg-<?php echo $statusClass; ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($defect['status'] ?? 'N/A'))); ?></span></li>
                                                                    <li class="mb-2 d-flex justify-content-between"><span>Priority:</span> <span class="badge bg-<?php echo $priorityClass; ?>"><?php echo ucfirst(htmlspecialchars($defect['priority'] ?? 'N/A')); ?></span></li>
                                                                    <li class="mb-2 d-flex justify-content-between"><span>Assigned:</span> <span class="text-end"><?php echo $assigned_user_display; ?></span></li>
                                                                    <li class="mb-2 d-flex justify-content-between"><span>Created:</span> <span class="text-end"><?php echo formatUKDateTime($defect['created_at']); ?></span></li>
                                                                    <li class="d-flex justify-content-between"><span>Due:</span> <span class="text-end <?php echo $isPastDue ? 'text-danger fw-bold' : ''; ?>"><?php echo formatUKDateTime($defect['due_date']); ?></span></li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <?php // Close and View Full Details buttons for the details section ?>
                                                <div class="text-end mt-3">
                                                    <a href="view_defect.php?id=<?php echo $defect['id']; ?>" class="btn btn-info btn-sm" target="_blank" title="Open full defect details in new tab">
                                                        <i class='bx bx-link-external'></i> View Full Details
                                                    </a>
                                                    <button type="button" class="btn btn-secondary btn-sm" onclick="toggleDefectForm(<?php echo $defect['id']; ?>)">
                                                        <i class='bx bx-x'></i> Close Details
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div> <?php // End table-responsive ?>

                <?php // --- Pagination Controls --- ?>
                <?php if ($totalPages > 1): ?>
                    <div class="pagination-container mt-4">
                        <nav aria-label="Defect list page navigation">
                            <ul class="pagination justify-content-center flex-wrap">
                                <?php // First Page Link ?>
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=1&<?php echo $filterParams; ?>" aria-label="First">
                                        <i class='bx bx-chevrons-left'></i> First
                                    </a>
                                </li>
                                <?php // Previous Page Link ?>
                                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>&<?php echo $filterParams; ?>" aria-label="Previous">
                                        <i class='bx bx-chevron-left'></i> Prev
                                    </a>
                                </li>

                                <?php
                                // Determine the range of page numbers to display.
                                $range = 2; // Number of pages to show before and after the current page.
                                $startPage = max(1, $page - $range);
                                $endPage = min($totalPages, $page + $range);

                                // Show ellipsis (...) if startPage is far from 1.
                                if ($startPage > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1&' . $filterParams . '">1</a></li>';
                                    if ($startPage > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                // Display page numbers within the calculated range.
                                for ($i = $startPage; $i <= $endPage; $i++) {
                                    echo '<li class="page-item ' . ($page == $i ? 'active' : '') . '">
                                        <a class="page-link" href="?page=' . $i . '&' . $filterParams . '">' . $i . '</a>
                                    </li>';
                                }

                                // Show ellipsis (...) if endPage is far from totalPages.
                                if ($endPage < $totalPages) {
                                    if ($endPage < $totalPages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $totalPages . '&' . $filterParams . '">' . $totalPages . '</a></li>';
                                }
                                ?>

                                <?php // Next Page Link ?>
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>&<?php echo $filterParams; ?>" aria-label="Next">
                                        Next <i class='bx bx-chevron-right'></i>
                                    </a>
                                </li>
                                <?php // Last Page Link ?>
                                <li class="page-item <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?>&<?php echo $filterParams; ?>" aria-label="Last">
                                        Last <i class='bx bx-chevrons-right'></i>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?> <?php // End Pagination ?>

            </div> <?php // End card-body ?>
        </div> <?php // End main content card ?>
    </div> <?php // End container-fluid ?>

    <?php // --- Bootstrap Modal for Image Zoom --- ?>
    <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-xl"> <?php // Use larger modal ?>
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="imageModalLabel">Defect Image</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body text-center p-0"> <?php // Remove padding ?>
            <img id="modalImage" src="" alt="Zoomed Defect Image" class="img-fluid" style="max-height: 85vh;"> <?php // Ensure image scales ?>
          </div>
        </div>
      </div>
    </div>

    <?php // --- JavaScript Includes --- ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <?php // --- Inline JavaScript for Page Interactivity --- ?>
    <script>
        /**
         * Inline JavaScript for Assign Defects Page
         * Handles:
         * - Toggling visibility of defect detail rows.
         * - Showing defect images in a modal.
         * - Displaying a loading overlay during form submissions.
         * - Handling filter form changes and search input with debounce.
         * - Managing bulk defect selection (checkboxes, count display, bulk action form visibility).
         */
        document.addEventListener('DOMContentLoaded', function() {

            /**
             * Toggles the visibility of the hidden details row for a specific defect.
             * @param {number} defectId - The ID of the defect whose details row should be toggled.
             */
            window.toggleDefectForm = function(defectId) {
                var formRow = document.getElementById("defectFormRow_" + defectId);
                if (formRow) {
                    var isHidden = formRow.style.display === "none" || formRow.style.display === "";
                    // Close all other open detail rows first
                    document.querySelectorAll('.defect-detail-row').forEach(row => {
                        if (row.id !== "defectFormRow_" + defectId) {
                            row.style.display = "none";
                        }
                    });
                    // Toggle the target row
                    formRow.style.display = isHidden ? "table-row" : "none";
                } else {
                    console.error("Details row not found for defect ID:", defectId);
                }
            };

            /**
             * Displays the clicked image in a Bootstrap modal.
             * @param {string} imgSrc - The source URL of the image to display.
             */
            window.showImageModal = function(imgSrc) {
                var modalImg = document.getElementById("modalImage");
                if (modalImg) {
                    modalImg.src = imgSrc; // Set the image source in the modal
                    var imageModal = new bootstrap.Modal(document.getElementById("imageModal")); // Get modal instance
                    imageModal.show(); // Show the modal
                } else {
                    console.error("Image modal element not found.");
                }
            };

            /**
             * Shows the loading overlay. Typically called before form submission.
             */
            function showLoading() {
                const overlay = document.getElementById('loadingOverlay');
                if (overlay) overlay.style.display = 'flex';
            }

            /**
             * Hides the loading overlay. Typically called after page load or AJAX completion.
             */
            function hideLoading() {
                 const overlay = document.getElementById('loadingOverlay');
                 if (overlay) overlay.style.display = 'none';
            }
            // Hide loading on initial page load (in case it was somehow visible)
            hideLoading();

            // Add event listener to show loading on *any* form submission on the page.
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', showLoading);
            });

            // --- Filter Form Handling ---

            // Auto-submit filter form when a dropdown value changes.
            document.querySelectorAll('#filterForm select').forEach(select => {
                select.addEventListener('change', function() {
                    showLoading(); // Show loading indicator when filter changes
                    document.getElementById('filterForm').submit();
                });
            });

            // Handle search input with debounce to avoid submitting on every keystroke.
            const searchInput = document.getElementById('searchInput');
            let searchTimeout;
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout); // Clear previous timeout
                    searchTimeout = setTimeout(() => {
                        showLoading(); // Show loading before submitting search
                        document.getElementById('filterForm').submit();
                    }, 500); // Wait 500ms after user stops typing
                });
            }

            // Handle the clear search button.
            const clearSearchBtn = document.getElementById('clearSearchBtn');
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function() {
                    const searchInput = document.getElementById('searchInput');
                    if (searchInput && searchInput.value !== '') { // Only submit if there's text to clear
                        searchInput.value = ''; // Clear the input field
                        showLoading(); // Show loading
                        document.getElementById('filterForm').submit(); // Resubmit form with empty search
                    }
                });
            }

            // --- Bulk Selection Functionality ---
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const defectCheckboxes = document.querySelectorAll('.defect-checkbox');
            const bulkActionsSection = document.getElementById('bulkActionsSection');
            const selectedCountSpan = document.getElementById('selectedCount');
            const selectedDefectIdsDiv = document.getElementById('selectedDefectIds'); // Div to hold hidden inputs
            const toggleSelectAllBtn = document.getElementById('toggleSelectAllBtn');
            const bulkAssignForm = document.getElementById('bulkAssignForm');

            /**
             * Updates the display of the selected count and shows/hides the bulk actions section.
             * Also updates hidden input fields within the bulk assign form.
             */
            function updateBulkActionsVisibility() {
                const checkedBoxes = document.querySelectorAll('.defect-checkbox:checked');
                const count = checkedBoxes.length;

                if (selectedCountSpan) selectedCountSpan.textContent = count; // Update displayed count
                if (bulkActionsSection) bulkActionsSection.style.display = count > 0 ? 'block' : 'none'; // Show/hide section

                // Update the hidden input fields for the bulk assign form submission.
                if (selectedDefectIdsDiv && bulkAssignForm) {
                    // Clear previous hidden inputs
                    selectedDefectIdsDiv.innerHTML = '';
                    if (count > 0) {
                        // Add a hidden input for each checked checkbox
                        checkedBoxes.forEach(checkbox => {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'defect_ids[]'; // Use array notation for PHP
                            input.value = checkbox.value;
                            selectedDefectIdsDiv.appendChild(input);
                        });
                    }
                }

                // Update "Select/Deselect All" button text based on current state
                 if (toggleSelectAllBtn) {
                     const allVisibleChecked = count === document.querySelectorAll('.defect-checkbox:visible').length && count > 0;
                     toggleSelectAllBtn.innerHTML = allVisibleChecked ?
                         '<i class="bx bx-checkbox-minus"></i> Deselect All on Page' :
                         '<i class="bx bx-select-multiple"></i> Select All on Page';
                 }

                 // Update "Select All" checkbox state
                 if (selectAllCheckbox) {
                     const allVisible = document.querySelectorAll('.defect-checkbox:visible');
                     selectAllCheckbox.checked = allVisible.length > 0 && count === allVisible.length;
                     selectAllCheckbox.indeterminate = count > 0 && count < allVisible.length;
                 }

            } // End updateBulkActionsVisibility

            // Event listener for the main "Select All" checkbox in the table header.
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    // Check/uncheck all *visible* defect checkboxes on the current page.
                    document.querySelectorAll('.defect-checkbox:visible').forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updateBulkActionsVisibility(); // Update UI accordingly.
                });
            }

            // Event listeners for individual defect checkboxes.
            defectCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateBulkActionsVisibility); // Update UI on change.
            });

            // Event listener for the "Select/Deselect All" button.
            if (toggleSelectAllBtn) {
                toggleSelectAllBtn.addEventListener('click', function() {
                    // Determine if all visible checkboxes are currently checked.
                    const visibleCheckboxes = document.querySelectorAll('.defect-checkbox:visible');
                    const allVisibleChecked = document.querySelectorAll('.defect-checkbox:visible:checked').length === visibleCheckboxes.length;

                    // Check/uncheck all visible checkboxes based on the current state.
                    visibleCheckboxes.forEach(checkbox => {
                        checkbox.checked = !allVisibleChecked;
                    });

                    updateBulkActionsVisibility(); // Update UI.
                });
            }

            // Initial check to set visibility on page load (e.g., if returning after failed validation).
            updateBulkActionsVisibility();

        }); // End DOMContentLoaded
    </script>

</body>
</html>