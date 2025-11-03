<?php
/**
 * User Management Page - Defect Tracker System
 *
 * @version 1.2
 * @author irlam (Original), Gemini (Modifications & Comments)
 * @last-modified 2025-04-12 10:39:35 UTC
 *
 * --- File Description ---
 * This script provides the primary interface for administrators and managers to manage user accounts
 * within the Defect Tracker system. It allows authorized users to:
 *
 * 1.  **View Users:** Displays a comprehensive list of registered users, including their username, full name,
 *     email address, assigned user type (e.g., Admin, Manager, Contractor), account status (Active/Inactive),
 *     associated contractor details (if applicable for Manager or Contractor types), and the timestamp of their
 *     last login (formatted for UK display: d/m/Y H:i).
 * 2.  **Responsive Display:** Offers two distinct views for optimal user experience:
 *     - A detailed HTML table suitable for larger desktop screens.
 *     - A series of cards, each representing a user, optimized for smaller screens like tablets and mobile devices.
 * 3.  **Search & Filter:** Includes a search bar to quickly find users by name, username, or email. The mobile/card
 *     view also provides dropdown filters to narrow the list by user status or type.
 * 4.  **User Actions (via AJAX):** Enables performing key management actions without requiring a full page reload, using
 *     asynchronous JavaScript calls (AJAX) to the backend logic within this same file:
 *     - **Edit User Details:** Modify a user's username, first name, last name, and email address through a modal form.
 *     - **Change User Type/Role:** Alter a user's assigned type (Admin, Manager, etc.) and associated role. This action
 *       also handles the association with a specific contractor:
 *         - *Required* for users assigned the 'Contractor' type.
 *         - *Optional* for users assigned the 'Manager' type (allowing managers who work for a contractor to be linked).
 *     - **Change User Status:** Activate or deactivate a user's account.
 * 5.  **Add New User:** Provides a button linking to `add_user.php` (which should contain the logic for creating new users,
 *     including appropriate contractor association based on type).
 * 6.  **Security:**
 *     - **Authentication & Authorization:** Ensures only logged-in users with 'admin' or 'manager' privileges can access the page.
 *     - **CSRF Protection:** Implements CSRF tokens on all forms and AJAX actions that modify data to prevent cross-site request forgery attacks.
 * 7.  **Auditing:** Logs significant user management actions (status changes, type changes, edits) to the `user_logs`
 *     database table, recording who performed the action, when, and relevant details.
 * 8.  **Technology:** Built using PHP, interacts with a MySQL/MariaDB database (via PDO), styled using Bootstrap 5,
 *     and leverages JavaScript for dynamic interactions and AJAX requests.
 * 9.  **User Feedback:** Provides clear visual feedback through dynamic alert messages (success/error) for user actions.
 *
 * --- Current User Context ---
 * Current Date and Time (UTC): 2025-04-12 10:39:35
 * Current User's Login: irlam
 */

// --- PHP Setup and Security ---

// Error Reporting Configuration:
// Display all errors on screen and log them to a file during development.
// IMPORTANT: In a production environment, 'display_errors' should be set to 0 for security.
error_reporting(E_ALL); // Report all levels of PHP errors.
ini_set('display_errors', 1); // Display errors directly in the browser output (DEVELOPMENT ONLY).
ini_set('log_errors', 1); // Enable logging of errors to a file.
ini_set('error_log', __DIR__ . '/logs/error.log'); // Specify the path to the error log file.

// Session Management:
// Start a new session or resume the existing one if not already active. Necessary for storing login state and CSRF tokens.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check:
// Verify that a user is logged in by checking session variables.
if (!isset($_SESSION['username']) || !isset($_SESSION['user_type'])) {
    // If 'username' or 'user_type' is not set in the session, redirect the user to the login page.
    header("Location: login.php");
    exit(); // Terminate script execution immediately after redirection.
}

// Authorization Check:
// Verify that the logged-in user has the required permissions ('admin' or 'manager') to access this page.
if (!in_array($_SESSION['user_type'], ['admin', 'manager'])) {
    // Log the unauthorized access attempt for security monitoring.
    error_log("Unauthorized access attempt to user_management.php by user: " . $_SESSION['username'] . " (User Type: " . $_SESSION['user_type'] . ")");
    // Redirect the user to their dashboard with an 'unauthorized' error message in the URL.
    header("Location: dashboard.php?error=unauthorized");
    exit(); // Terminate script execution.
}

// --- Includes ---

// Include necessary PHP files containing shared functionality and classes.
require_once 'includes/functions.php'; // General helper functions (e.g., input sanitization, date formatting - ensure these exist).
require_once 'config/database.php'; // Contains the Database class for establishing a connection using PDO.
require_once 'includes/navbar.php'; // Contains the Navbar class responsible for rendering the site's navigation menu.

// --- Constants and Global Variables ---

// Define user types available in the system. Used for dropdown menus and validation logic.
// The keys should match the values stored in the database `user_type` column.
define('USER_TYPES', [
    'admin' => 'Administrator',
    'manager' => 'Manager',
    'contractor' => 'Contractor',
    'viewer' => 'Viewer',
    'client' => 'Client'
]);

// Page-specific variables initialized for the HTML rendering phase.
$pageTitle = 'User Management'; // Sets the <title> tag and main heading of the page.
$currentUser = $_SESSION['username']; // Stores the username of the currently logged-in user for potential display or logging.
$success_message = ''; // Placeholder for success feedback messages (e.g., after a redirect).
$error_message = ''; // Placeholder for error feedback messages (e.g., if data fetching fails).

// Define an array of valid user types based on the database schema or application logic.
// Used primarily for server-side validation of user type changes.
$userTypes = [
    'admin',
    'manager',
    'contractor',
    'viewer',
    'client'
];

// =========================================================================
// AJAX Request Handling Block
// Executes only if the request method is POST and includes 'ajax=true'.
// Handles background actions triggered by JavaScript without full page reloads.
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax']) && $_POST['ajax'] === 'true') {

    // Set the HTTP response header to indicate that JSON data is being returned.
    header('Content-Type: application/json');

    // --- CSRF Token Validation ---
    // Crucial security measure to prevent Cross-Site Request Forgery attacks.
    // Compares the token sent in the AJAX request payload with the token stored in the user's session.
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Log the CSRF token mismatch for security auditing.
        $actionAttempted = isset($_POST['action']) ? $_POST['action'] : 'unknown'; // Get action if available
        error_log("CSRF token mismatch during AJAX action '{$actionAttempted}' for user: " . $_SESSION['username']);
        // Return a JSON error response to the client.
        echo json_encode([
            'success' => false,
            'message' => 'Security token validation failed. Please refresh the page and try again.'
        ]);
        exit(); // Halt script execution.
    }

    // --- AJAX Action Processing ---
    // Initialize the default JSON response structure.
    $response = ['success' => false, 'message' => 'Unknown action or invalid parameters provided.'];

    try {
        // Establish a database connection using the Database class.
        $database = new Database();
        $db = $database->getConnection(); // Get the PDO connection object.

        // Check if the 'action' parameter, specifying the requested operation, is present in the POST data.
        if (isset($_POST['action'])) {

            // Use a switch statement to route the request to the appropriate action handler.
            switch ($_POST['action']) {

                // --- Action: Change User Status (Activate/Deactivate) ---
                case 'change_status':
                    // Validate that required parameters ('user_id', 'new_status') are present and 'new_status' is valid.
                    if (isset($_POST['user_id'], $_POST['new_status']) && in_array($_POST['new_status'], ['active', 'inactive'])) {

                        // Prepare the SQL statement to update the user's status in the 'users' table.
                        $stmt = $db->prepare("
                            UPDATE users
                            SET
                                status = :status,                  -- Parameter for the new status ('active' or 'inactive').
                                updated_at = UTC_TIMESTAMP(),      -- Automatically set the update timestamp to current UTC time.
                                updated_by = :updated_by           -- Record the username of the admin/manager performing the action.
                            WHERE id = :user_id                    -- Target the specific user record by their ID.
                        ");

                        // Bind the parameters to the prepared statement and execute the query.
                        $stmt->execute([
                            ':status' => $_POST['new_status'],
                            ':updated_by' => $_SESSION['username'], // Get username from the session.
                            ':user_id' => $_POST['user_id']
                        ]);

                        // Log this action in the 'user_logs' table for auditing purposes.
                        $stmtLog = $db->prepare("
                            INSERT INTO user_logs (user_id, action, action_by, action_at, ip_address, details)
                            VALUES (:user_id, 'status_changed', :action_by, UTC_TIMESTAMP(), :ip_address, :details)
                        ");

                        // Prepare the details of the change as a JSON string for the log.
                        $logDetails = json_encode([
                            'new_status' => $_POST['new_status'],
                            'changed_by_username' => $_SESSION['username'],
                            'timestamp_utc' => gmdate('Y-m-d H:i:s') // Record the precise UTC time of the action.
                        ]);

                        // Execute the log insertion query.
                        $stmtLog->execute([
                            ':user_id' => $_POST['user_id'],         // The ID of the user being modified.
                            ':action_by' => $_SESSION['user_id'],    // The ID of the admin/manager performing the action.
                            // Use isset() ternary for PHP 5.x+ compatibility, even though server is PHP 8.1 (safer if effective version differs).
                            ':ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown', // Get user's IP, handle if not set
                            ':details' => $logDetails              // The JSON string containing change details.
                        ]);

                        // Fetch the complete updated user record to send back to the client-side JavaScript.
                        // This allows the UI to be updated accurately without a page refresh.
                        $stmtFetch = $db->prepare("
                            SELECT id, username, email, first_name, last_name,
                                   user_type, status, contractor_id, contractor_name,
                                   contractor_trade, last_login -- Include all relevant fields for UI updates.
                            FROM users
                            WHERE id = :user_id
                        ");
                        $stmtFetch->execute([':user_id' => $_POST['user_id']]);
                        $user = $stmtFetch->fetch(PDO::FETCH_ASSOC); // Fetch the single user row.

                        // Prepare a successful JSON response containing the updated user data.
                        $response = [
                            'success' => true,
                            'message' => 'User status updated successfully.',
                            'user' => $user // Pass the updated user object back to the JavaScript.
                        ];
                    } else {
                        // If required parameters are missing or invalid for this action.
                        $response['message'] = 'Invalid parameters provided for changing user status.';
                        $userIdParam = isset($_POST['user_id']) ? $_POST['user_id'] : 'N/A';
                        $newStatusParam = isset($_POST['new_status']) ? $_POST['new_status'] : 'N/A';
                        error_log("Invalid parameters for change_status action. User ID: {$userIdParam}, New Status: {$newStatusParam}");
                    }
                    break; // Exit the 'change_status' case.

                // --- Action: Change User Type/Role (Handles Contractor Association) ---
                case 'change_type':
                    // Validate that required parameters ('user_id', 'new_type') are present.
                    if (isset($_POST['user_id'], $_POST['new_type'])) {
                        try {
                            // Validate that the provided 'new_type' is one of the allowed types defined earlier.
                            if (!in_array($_POST['new_type'], $userTypes)) {
                                throw new Exception("Invalid user type selected ('{$_POST['new_type']}').");
                            }

                            // --- Database Transaction ---
                            // Begin a transaction to ensure all related database updates (users, user_roles, user_logs)
                            // either succeed together or fail together, maintaining data integrity.
                            $db->beginTransaction();

                            // --- Determine Role and Contractor Association ---
                            // Map the selected user type (e.g., 'manager') to the corresponding internal role name (e.g., 'project_manager').
                            // Ensure these role names accurately reflect your application's role system.
                            $roleMapping = [
                                'admin' => 'admin',
                                'manager' => 'project_manager',
                                'contractor' => 'contractor',
                                'viewer' => 'viewer',
                                'client' => 'client'
                            ];
                            // Use isset() ternary for compatibility. Default to 'viewer'.
                            $role = isset($roleMapping[$_POST['new_type']]) ? $roleMapping[$_POST['new_type']] : 'viewer';

                            // Initialize variables to store contractor details.
                            $contractorId = null;
                            $contractorName = null;
                            $contractorTrade = null;

                            // --- Contractor Association Logic ---
                            // Check if the new type is 'contractor' OR 'manager'. Contractor association only applies to these types.
                            if ($_POST['new_type'] === 'contractor' || $_POST['new_type'] === 'manager') {
                                // Check if a contractor ID was submitted via the form.
                                if (isset($_POST['contractor_id']) && !empty($_POST['contractor_id'])) {
                                    // If a contractor ID is provided, validate it and fetch details.
                                    $stmtContractor = $db->prepare("
                                        SELECT id, company_name, trade
                                        FROM contractors
                                        WHERE id = :contractor_id AND status = 'active' -- Crucially, only allow association with ACTIVE contractors.
                                    ");
                                    $stmtContractor->execute([':contractor_id' => $_POST['contractor_id']]);
                                    $contractor = $stmtContractor->fetch(PDO::FETCH_ASSOC);

                                    // If the contractor ID is invalid or the contractor is inactive, reject the change.
                                    if (!$contractor) {
                                        throw new Exception("The selected contractor is invalid or inactive.");
                                    }
                                    // If valid, store the contractor's details.
                                    $contractorId = $contractor['id'];
                                    $contractorName = $contractor['company_name'];
                                    $contractorTrade = $contractor['trade'];
                                } else {
                                    // If no contractor ID was provided:
                                    // - If the new type is 'Contractor', this is an error (association is required).
                                    if ($_POST['new_type'] === 'contractor') {
                                        throw new Exception("A contractor must be selected when assigning the 'Contractor' user type.");
                                    }
                                    // - If the new type is 'Manager', no contractor ID is fine (association is optional).
                                    //   $contractorId, $contractorName, $contractorTrade remain null.
                                }
                            }
                            // If the new type is not 'contractor' or 'manager', any contractor association is cleared
                            // because $contractorId, $contractorName, $contractorTrade remain null.

                            // --- Update 'users' Table ---
                            // Prepare the SQL statement to update the user's record.
                            $stmtUpdateUser = $db->prepare("
                                UPDATE users
                                SET
                                    user_type = :new_type,          -- Set the new user type.
                                    role = :role,                   -- Set the corresponding role name.
                                    contractor_id = :contractor_id, -- Set the associated contractor ID (will be NULL if none).
                                    contractor_name = :contractor_name, -- Set the contractor name (NULL if none).
                                    contractor_trade = :contractor_trade, -- Set the contractor trade (NULL if none).
                                    updated_at = UTC_TIMESTAMP(),   -- Record update time.
                                    updated_by = :updated_by        -- Record who made the change.
                                WHERE id = :user_id
                            ");

                            // Execute the update query for the 'users' table.
                            if (!$stmtUpdateUser->execute([
                                ':new_type' => $_POST['new_type'],
                                ':role' => $role,
                                ':contractor_id' => $contractorId, // Use the determined ID (null if not applicable/selected).
                                ':contractor_name' => $contractorName,
                                ':contractor_trade' => $contractorTrade,
                                ':updated_by' => $_SESSION['username'],
                                ':user_id' => $_POST['user_id']
                            ])) {
                                // If the update fails, throw an exception to trigger rollback. Include DB error info.
                                throw new Exception("Database error updating user record: " . implode(" ", $stmtUpdateUser->errorInfo()));
                            }

                            // --- Update 'user_roles' Table (Optional - depends on your permission system) ---
                            // If you use a separate table (like 'user_roles') to link users to roles via IDs.
                            // Map the user type to the corresponding role ID in your 'roles' table. Adjust these IDs as necessary.
                            $roleIdMapping = [
                                'admin' => 1, 'manager' => 2, 'contractor' => 3, 'viewer' => 4, 'client' => 5
                            ];
                            // Use isset() ternary for compatibility. Default to viewer role ID (e.g., ID 4).
                            $roleId = isset($roleIdMapping[$_POST['new_type']]) ? $roleIdMapping[$_POST['new_type']] : 4;

                            // Prepare SQL to insert a new role mapping or update an existing one for the user.
                            // `ON DUPLICATE KEY UPDATE` assumes `user_id` is a unique key or primary key in `user_roles`.
                            $stmtUpdateRole = $db->prepare("
                                INSERT INTO user_roles (user_id, role_id, created_by, created_at, updated_at)
                                VALUES (:user_id, :role_id, :created_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                                ON DUPLICATE KEY UPDATE
                                    role_id = VALUES(role_id), -- Update the role_id if the user already has a role entry.
                                    updated_by = :updated_by,  -- Record who performed this update.
                                    updated_at = UTC_TIMESTAMP() -- Update the timestamp for the role mapping.
                            ");

                            // Execute the `user_roles` update/insert query.
                            if (!$stmtUpdateRole->execute([
                                ':user_id' => $_POST['user_id'],
                                ':role_id' => $roleId,
                                ':created_by' => $_SESSION['user_id'], // ID of user creating/updating the mapping.
                                ':updated_by' => $_SESSION['user_id']  // ID of user performing the update.
                            ])) {
                                // If this fails, throw an exception to trigger rollback.
                                throw new Exception("Database error updating user role mapping: " . implode(" ", $stmtUpdateRole->errorInfo()));
                            }
                            // If you don't use a separate user_roles table, you can remove this block.

                            // --- Log the Action ---
                            // Log the successful type change in the 'user_logs' table.
                            $stmtLog = $db->prepare("
                                INSERT INTO user_logs (user_id, action, action_by, action_at, ip_address, details)
                                VALUES (:user_id, 'type_changed', :action_by, UTC_TIMESTAMP(), :ip_address, :details)
                            ");

                            // Prepare detailed log information in JSON format.
                            $logDetails = json_encode([
                                'new_type' => $_POST['new_type'],
                                'new_role' => $role, // The mapped role name.
                                'new_role_id' => $roleId, // The mapped role ID (if using user_roles table).
                                'associated_contractor_id' => $contractorId, // Log the associated contractor ID (or null).
                                'associated_contractor_name' => $contractorName, // Log the name (or null).
                                'changed_by_username' => $_SESSION['username'],
                                'timestamp_utc' => gmdate('Y-m-d H:i:s')
                            ]);

                            // Execute the log insertion query.
                            $stmtLog->execute([
                                ':user_id' => $_POST['user_id'],
                                ':action_by' => $_SESSION['user_id'],
                                // Use isset() ternary for compatibility.
                                ':ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown',
                                ':details' => $logDetails
                            ]);

                            // --- Commit Transaction ---
                            // If all database operations within the try block were successful, commit the transaction.
                            $db->commit();

                            // --- Fetch Updated Data for Response ---
                            // Get the complete, updated user record to send back to the client.
                            $stmtFetch = $db->prepare("
                                SELECT id, username, email, first_name, last_name,
                                       user_type, status, contractor_id, contractor_name,
                                       contractor_trade, last_login
                                FROM users
                                WHERE id = :user_id
                            ");
                            $stmtFetch->execute([':user_id' => $_POST['user_id']]);
                            $user = $stmtFetch->fetch(PDO::FETCH_ASSOC);

                            // --- Prepare Success Response ---
                            $response = [
                                'success' => true,
                                'message' => 'User type, role, and contractor association updated successfully.',
                                'user' => $user // Include updated user data for UI refresh.
                            ];

                        } catch (Exception $e) {
                            // --- Handle Errors and Rollback ---
                            // If any exception was thrown within the 'try' block:
                            // Check if a transaction is active and roll it back to undo any partial changes.
                            if ($db->inTransaction()) {
                                $db->rollBack();
                            }
                            // Prepare an error response containing the exception message.
                            $response = [
                                'success' => false,
                                'message' => "Error changing user type: " . $e->getMessage() // Provide specific error.
                            ];
                            // Log the error for server-side debugging.
                            $userIdParam = isset($_POST['user_id']) ? $_POST['user_id'] : 'N/A';
                            error_log("Error in change_type AJAX (User ID: {$userIdParam}): " . $e->getMessage());
                        }
                    } else {
                        // If required parameters ('user_id', 'new_type') were missing.
                        $response['message'] = 'Invalid parameters provided for changing user type.';
                        $userIdParam = isset($_POST['user_id']) ? $_POST['user_id'] : 'N/A';
                        $newTypeParam = isset($_POST['new_type']) ? $_POST['new_type'] : 'N/A';
                        error_log("Missing parameters for change_type action. User ID: {$userIdParam}, New Type: {$newTypeParam}");
                    }
                    break; // Exit the 'change_type' case.

                // --- Action: Edit Basic User Details (Name, Username, Email) ---
                // This action modifies only the specified fields. Type/Role/Contractor changes are handled by 'change_type'.
                case 'edit_user':
                    // Validate that all required parameters are present.
                    if (isset($_POST['user_id'], $_POST['username'], $_POST['email'], $_POST['first_name'], $_POST['last_name'])) {
                        try {
                            // Prepare the SQL statement to update the user's details.
                            $stmtUpdate = $db->prepare("
                                UPDATE users
                                SET
                                    username = :username,         -- Update username.
                                    email = :email,               -- Update email address.
                                    first_name = :first_name,     -- Update first name.
                                    last_name = :last_name,       -- Update last name.
                                    updated_at = UTC_TIMESTAMP(), -- Record the update timestamp.
                                    updated_by = :updated_by      -- Record who made the change.
                                WHERE id = :user_id
                            ");

                            // Execute the update query, trimming whitespace from input values for cleanliness.
                            if (!$stmtUpdate->execute([
                                ':username' => trim($_POST['username']),
                                ':email' => trim($_POST['email']),
                                ':first_name' => trim($_POST['first_name']),
                                ':last_name' => trim($_POST['last_name']),
                                ':updated_by' => $_SESSION['username'],
                                ':user_id' => $_POST['user_id']
                            ])) {
                                 // If execute fails, throw an exception.
                                throw new PDOException("Failed to execute user update query.");
                            }


                            // Log the edit action in 'user_logs'.
                            $stmtLog = $db->prepare("
                                INSERT INTO user_logs (user_id, action, action_by, action_at, ip_address, details)
                                VALUES (:user_id, 'user_edited', :action_by, UTC_TIMESTAMP(), :ip_address, :details)
                            ");

                            // Prepare log details (can be enhanced to include old/new values for better auditing if required).
                            $logDetails = json_encode([
                                'edited_fields' => ['username', 'email', 'first_name', 'last_name'], // List fields modified by this action.
                                'edited_by_username' => $_SESSION['username'],
                                'timestamp_utc' => gmdate('Y-m-d H:i:s')
                            ]);

                            // Execute the log insertion query.
                            $stmtLog->execute([
                                ':user_id' => $_POST['user_id'],
                                ':action_by' => $_SESSION['user_id'],
                                // Use isset() ternary for compatibility.
                                ':ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown',
                                ':details' => $logDetails
                            ]);

                            // Fetch the updated user data to send back to the client for UI refresh.
                            $stmtFetch = $db->prepare("
                                SELECT id, username, email, first_name, last_name,
                                       user_type, status, contractor_id, contractor_name,
                                       contractor_trade, last_login
                                FROM users
                                WHERE id = :user_id
                            ");
                            $stmtFetch->execute([':user_id' => $_POST['user_id']]);
                            $user = $stmtFetch->fetch(PDO::FETCH_ASSOC);

                            // Prepare a successful JSON response.
                            $response = [
                                'success' => true,
                                'message' => 'User details updated successfully.',
                                'user' => $user // Include updated user data.
                            ];
                        } catch (PDOException $e) {
                            // Handle potential database errors during update, especially unique constraint violations.
                            $errorMessage = "Database error updating user details.";
                            // Check for specific duplicate entry error code (e.g., 1062 for MySQL).
                            if ($e->getCode() == '23000') { // General SQLSTATE for integrity constraint violation
                                // More specific check might be needed depending on DB driver
                                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                                     $errorMessage = "Failed to update user: The chosen username or email address is already taken.";
                                }
                            }
                            $response = [
                                'success' => false,
                                'message' => $errorMessage
                            ];
                             $userIdParam = isset($_POST['user_id']) ? $_POST['user_id'] : 'N/A';
                            error_log("Database error in edit_user AJAX (User ID: {$userIdParam}): " . $e->getMessage());
                        } catch (Exception $e) {
                            // Handle other unexpected errors during the update process.
                            $response = [
                                'success' => false,
                                'message' => "An unexpected error occurred while updating user details: " . $e->getMessage()
                            ];
                             $userIdParam = isset($_POST['user_id']) ? $_POST['user_id'] : 'N/A';
                            error_log("Error in edit_user AJAX (User ID: {$userIdParam}): " . $e->getMessage());
                        }
                    } else {
                        // If required parameters were missing for this action.
                        $response['message'] = 'Missing required fields for editing user details.';
                         $userIdParam = isset($_POST['user_id']) ? $_POST['user_id'] : 'N/A';
                        error_log("Missing parameters for edit_user action. User ID: {$userIdParam}");
                    }
                    break; // Exit the 'edit_user' case.

                // --- Default Case ---
                // Handles situations where the 'action' parameter is unrecognized.
                default:
                    $unrecognizedAction = isset($_POST['action']) ? $_POST['action'] : 'Not specified';
                    $response['message'] = "Invalid action '{$unrecognizedAction}' specified.";
                    error_log("Invalid AJAX action '{$unrecognizedAction}' requested by user: " . $_SESSION['username']);
                    break;

            } // End switch statement on 'action'.

        } else {
            // If the 'action' parameter itself was missing from the POST request.
            $response['message'] = 'No action specified in the request.';
            error_log("AJAX request received without 'action' parameter from user: " . $_SESSION['username']);
        } // End check for 'action' parameter.

    } catch (PDOException $dbException) {
        // --- Database Connection Error Handling ---
        // Catch errors specifically related to establishing the database connection.
        $response = [
            'success' => false,
            'message' => 'Database connection error. Please try again later or contact support.'
        ];
        // Log the detailed connection error for administrators.
        error_log("FATAL: Database connection error in user_management.php AJAX handler: " . $dbException->getMessage());
    } catch (Exception $e) {
        // --- General Error Handling ---
        // Catch any other unexpected exceptions that might occur during AJAX processing.
        $response = [
            'success' => false,
            'message' => 'An unexpected server error occurred. Please try again.'
        ];
        // Log the detailed error message.
        error_log("Unexpected error during AJAX request processing in user_management.php: " . $e->getMessage());
    }

    // --- Send JSON Response ---
    // Encode the final $response array (containing success status, message, and potentially data) into JSON format
    // and send it back to the client-side JavaScript that made the AJAX call.
    echo json_encode($response);
    exit(); // Terminate PHP script execution after handling the AJAX request.

} // End of AJAX Request Handling Block.

// =========================================================================
// HTML Page Rendering Logic (Executes for GET requests or non-AJAX POSTs)
// Fetches data needed to build and display the user management page.
// =========================================================================
try {
    // Establish database connection required for fetching data to display on the page.
    $database = new Database();
    $db = $database->getConnection();

    // --- Check for Feedback Messages from Redirects ---
    // Look for a 'success' message passed as a URL parameter (e.g., after successfully adding a user on add_user.php).
    if (isset($_GET['success']) && $_GET['success'] === 'user_created') {
        $success_message = "User account was successfully created.";
    }
    // Add checks for other potential messages (e.g., ?error=...) if needed.

    // --- Fetch Data for Dropdowns ---
    // Get a list of currently active contractors to populate the 'Associate Contractor' dropdown in the 'Change Type' modal.
    $stmtContractors = $db->prepare("
        SELECT id, company_name, trade
        FROM contractors
        WHERE status = 'active' -- Only allow associating users with active contractors.
        ORDER BY company_name ASC -- Order alphabetically for easier selection in the dropdown.
    ");
    $stmtContractors->execute();
    // Fetch all results into an associative array.
    $contractors = $stmtContractors->fetchAll(PDO::FETCH_ASSOC);

    // --- Fetch Main User List ---
    // Prepare the main query to retrieve the list of users to display on the page.
    // Includes essential user details, status, type, contractor info, and last login time.
    // COALESCE is used to provide default values ('viewer', 'inactive', 0) for fields that might be NULL in the database, preventing errors.
    $queryUsers = "
        SELECT
            u.id,                                          -- User's unique ID.
            u.username,                                    -- User's login name.
            u.email,                                       -- User's email address.
            u.first_name,                                  -- User's first name.
            u.last_name,                                   -- User's last name.
            COALESCE(u.user_type, 'viewer') as user_type,  -- User's assigned type (default to 'viewer' if NULL).
            COALESCE(u.status, 'inactive') as status,      -- User's account status (default to 'inactive' if NULL).
            u.last_login,                                  -- Timestamp of the user's last login (can be NULL).
            u.is_active,                                   -- Legacy field? Verify if still in use or if 'status' is primary. Assuming 1 means not soft-deleted.
            u.contractor_id,                               -- ID of the contractor the user is associated with (NULL if none).
            u.contractor_name,                             -- Name of the associated contractor (likely populated via trigger or logic).
            u.contractor_trade,                            -- Trade of the associated contractor.
            COALESCE(s.active_sessions, 0) as active_sessions -- Count of currently active (not logged out) sessions for the user.
        FROM users u
        LEFT JOIN (
            -- Subquery to efficiently count active sessions per user.
            SELECT
                user_id,
                COUNT(*) as active_sessions
            FROM user_sessions
            WHERE logged_out_at IS NULL -- A session is considered active if 'logged_out_at' is NULL.
            GROUP BY user_id
        ) s ON u.id = s.user_id
        -- WHERE u.is_active = 1 -- Re-evaluate this filter. If 'status' ('active'/'inactive') is the primary indicator,
                                -- filtering by 'is_active' might be redundant or incorrect if it represents soft deletion differently.
                                -- If you only want to show non-deleted users, this might be correct. Confirm its purpose.
        ORDER BY u.created_at DESC -- Order users by creation date, showing the newest first.
    ";

    $stmtUsers = $db->prepare($queryUsers);
    $stmtUsers->execute();
    // Fetch all user records matching the query into an associative array.
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $dbException) {
    // --- Database Error Handling (Page Load) ---
    // Handle errors that occur during database connection or data fetching for the page render.
    $error_message = "A database error occurred while loading user data. Please refresh the page or contact support if the problem persists.";
    error_log("FATAL: Database error on user_management.php page load: " . $dbException->getMessage());
    // Initialize $users and $contractors as empty arrays to prevent fatal errors in the HTML rendering part.
    $users = [];
    $contractors = [];
} catch (Exception $e) {
    // --- General Error Handling (Page Load) ---
    // Catch any other unexpected exceptions during the page preparation phase.
    $error_message = "An unexpected error occurred while preparing the page. Please try again.";
    error_log("Error on user_management.php page load: " . $e->getMessage());
    $users = [];
    $contractors = [];
}

// --- CSRF Token Generation ---
// Generate a new, unique CSRF token for this page load. This token will be embedded in forms
// and used in subsequent AJAX requests to validate their origin.
$_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Creates a cryptographically secure 64-character hex token.

// --- HTML Output Starts Here ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php // --- HTML Head Section --- ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Manage users, user types, statuses, and contractor associations within the Defect Tracker System.">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); // Display logged-in user as author ?>">
    <meta name="last-modified" content="<?php echo gmdate('Y-m-d H:i:s', filemtime(__FILE__)); ?> UTC"> <!-- Dynamically get last modified time -->
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>

    <?php // --- CSS Includes --- ?>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <!-- Boxicons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">

    <?php // --- Favicons (replace with your actual favicon paths) --- ?>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />

    <?php // --- Internal CSS Styles --- ?>
    <style>
        /* Define CSS variables for consistent theming (using Bootstrap's color names) */
        :root {
            --bs-primary-rgb: 13, 110, 253;
            --bs-success-rgb: 25, 135, 84;
            --bs-danger-rgb: 220, 53, 69;
            --bs-warning-rgb: 255, 193, 7;
            --bs-info-rgb: 13, 202, 240;
            --bs-secondary-rgb: 108, 117, 125;
            /* Custom colors if needed */
        }

        /* Basic body styling */
        body {
            background-color: #f8f9fa; /* Light grey background */
            padding-top: 70px; /* Increased padding for potentially taller fixed navbar */
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; /* Standard system font stack */
            font-size: 0.95rem; /* Slightly smaller base font size */
        }

        /* Container adjustments */
        .container-fluid {
            max-width: 1400px; /* Limit content width on large screens */
            padding: 1rem 1.5rem; /* Add horizontal padding */
        }

        /* Page header styling */
        .page-header {
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6; /* Slightly darker border */
        }

        /* Sticky search bar container */
        .search-container {
            position: sticky; /* Make it stick to the top when scrolling */
            top: 60px; /* Position below the fixed navbar (adjust if navbar height changes) */
            z-index: 100; /* Ensure it stays above table content */
            background-color: #f8f9fa; /* Match body background */
            padding: 0.75rem 0; /* Vertical padding */
            margin-bottom: 1.5rem; /* Space below search bar */
            transition: box-shadow 0.3s ease-in-out; /* Smooth shadow transition */
        }
        /* Add shadow when search bar is scrolled past its original position */
        .search-container.scrolled {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.075);
        }
        /* Style search input group elements */
        .search-container .input-group-text {
             background-color: #fff;
             border-right: 0; /* Remove border between icon and input */
             color: #6c757d; /* Secondary color for icon */
        }
        .search-container .form-control {
            border-left: 0; /* Remove border between icon and input */
        }
         .search-container .form-control:focus {
             box-shadow: none; /* Remove default focus glow */
             border-color: #86b7fe; /* Bootstrap focus border color */
             z-index: 3; /* Ensure focus border overlaps icon */
         }


        /* User status indicator (colored circle) */
        .user-status {
            width: 11px; /* Slightly larger */
            height: 11px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
            vertical-align: middle;
            border: 1px solid rgba(0,0,0,0.1); /* Subtle border */
        }
        /* Use CSS variables for colors */
        .user-status.active { background-color: rgb(var(--bs-success-rgb)); border-color: rgb(var(--bs-success-rgb)); }
        .user-status.inactive { background-color: rgb(var(--bs-danger-rgb)); border-color: rgb(var(--bs-danger-rgb)); }

        /* User type badge styling */
        .type-badge {
            text-transform: capitalize;
            color: white;
            font-size: 0.8em;
            padding: 0.3em 0.6em; /* Adjust padding */
            border-radius: 0.25rem; /* Standard Bootstrap badge radius */
            vertical-align: middle; /* Align better with text */
        }
        /* Define background colors for each badge type using data attributes */
        .type-badge[data-type="admin"] { background-color: #dc3545 !important; } /* Bootstrap Danger */
        .type-badge[data-type="manager"] { background-color: #198754 !important; } /* Bootstrap Success */
        .type-badge[data-type="contractor"] { background-color: #0d6efd !important; } /* Bootstrap Primary */
        .type-badge[data-type="viewer"] { background-color: #6c757d !important; } /* Bootstrap Secondary */
        .type-badge[data-type="client"] { background-color: #ffc107 !important; color: #343a40 !important; } /* Bootstrap Warning, dark text */

        /* Animation for highlighting changes in table rows or cards */
        .changing {
            animation: highlight-fade 1.2s ease-out;
        }
        @keyframes highlight-fade {
            0% { background-color: rgba(var(--bs-warning-rgb), 0.3); } /* Start with a light yellow highlight */
            100% { background-color: transparent; } /* Fade to normal background */
        }

        /* Contractor information styling (small text) */
        .contractor-info {
            font-size: 0.85em;
            color: #6c757d; /* Bootstrap secondary text color */
            margin-top: 0.1rem;
            display: block; /* Ensure it appears below the name in cards */
            line-height: 1.2;
        }
        /* Adjust for inline display within table cells */
        td .contractor-info { margin-top: 0; display: inline; margin-left: 0.25rem; }


        /* Table styling */
        .table { font-size: 0.9rem; /* Slightly smaller table font */ }
        .table td, .table th {
            vertical-align: middle; /* Vertically center cell content */
            padding: 0.6rem 0.5rem; /* Adjust cell padding */
        }
        .table th {
            white-space: nowrap; /* Prevent table headers from wrapping unnecessarily */
            font-weight: 500; /* Slightly bolder headers */
            background-color: #f8f9fa; /* Light header background */
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0,0,0,.03); /* Subtle hover effect */
        }

        /* Fixed alert container positioning */
        #alertContainer {
            position: fixed;
            top: 75px; /* Adjust based on final navbar/search bar height */
            right: 20px;
            z-index: 1055; /* Ensure alerts are above modals */
            width: 380px; /* Slightly wider alerts */
            max-width: 95%;
        }
        /* Style individual alerts */
        #alertContainer .alert {
            box-shadow: 0 0.25rem 0.75rem rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            padding: 0.75rem 1rem;
        }
        #alertContainer .alert .btn-close {
            padding: 0.75rem 1rem; /* Ensure close button has adequate click area */
        }

        /* Card Styles - for mobile view */
        .card-view .dropdown { margin-bottom: 1rem; } /* Add space below mobile filter */
        .user-card {
            border: 1px solid #dee2e6; /* Add border matching table */
            border-radius: 0.375rem; /* Standard Bootstrap card radius */
            overflow: hidden;
            margin-bottom: 1rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.05); /* Softer shadow */
            transition: transform 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
            background-color: #fff;
        }
        /* Subtle press effect on mobile */
        .user-card:active {
            transform: translateY(1px);
            box-shadow: none;
        }
        /* Card header styling */
        .user-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        .user-card .card-header strong { font-weight: 500; } /* Match table header weight */
        /* Card body styling */
        .user-card .card-body { padding: 0.5rem 1rem; } /* Reduce vertical padding */
        /* Styling for data rows within the card body */
        .user-card .data-row {
            display: flex;
            border-bottom: 1px solid #e9ecef; /* Lighter border inside card */
            padding: 0.7rem 0; /* Adjust padding */
            align-items: flex-start;
        }
        .user-card .data-row:last-child { border-bottom: none; }
        /* Styling for labels in card rows */
        .user-card .data-label {
            flex: 0 0 110px; /* Fixed width for labels */
            font-weight: 500;
            color: #495057;
            font-size: 0.875rem;
            padding-right: 0.5rem;
            white-space: nowrap; /* Prevent labels wrapping */
        }
        /* Styling for values in card rows */
        .user-card .data-value {
            flex: 1;
            color: #212529;
            font-size: 0.875rem;
            word-break: break-word; /* Prevent long text overflow */
        }
         /* Ensure links in card values are styled correctly */
        .user-card .data-value a {
            text-decoration: none;
            color: var(--bs-link-color);
        }
        .user-card .data-value a:hover {
            text-decoration: underline;
            color: var(--bs-link-hover-color);
        }
        /* Styling for the actions footer in cards */
        .user-card .card-actions {
            display: flex;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background-color: #f8f9fa; /* Match header background */
            border-top: 1px solid #dee2e6;
        }
        /* Styling for action buttons within cards */
        .action-btn {
            padding: 0.375rem 0.6rem; /* Adjust padding */
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem; /* Space between icon and text */
        }
         /* Control visibility of button text on small screens using a specific class */
        .action-btn .button-text { display: none; } /* Hide text by default on smallest screens */
        @media (min-width: 400px) { /* Show text on slightly larger small screens and up */
             .action-btn .button-text { display: inline; }
        }


        /* Responsive display logic: Hide table on smaller screens, hide cards on larger screens */
        @media (max-width: 991.98px) { /* Applies below Bootstrap's 'lg' breakpoint */
            .table-view { display: none; }
            .card-view { display: block; }
            .search-container { top: 56px; } /* Adjust sticky top if navbar shrinks */
            #alertContainer { top: 60px; }
        }
        @media (min-width: 992px) { /* Applies at Bootstrap's 'lg' breakpoint and above */
            .table-view { display: block; }
            .card-view { display: none; }
        }

        /* Fullscreen modal behavior on small screens for better usability */
        @media (max-width: 576px) { /* Applies below Bootstrap's 'sm' breakpoint */
            .modal-fullscreen-sm-down {
                width: 100vw;
                max-width: none;
                height: 100%;
                margin: 0;
            }
            .modal-fullscreen-sm-down .modal-content {
                height: 100%;
                border: 0;
                border-radius: 0;
            }
            .modal-fullscreen-sm-down .modal-body {
                overflow-y: auto; /* Enable scrolling within the modal body if content overflows */
            }
        }

        /* Utility class for required field indicator */
        .text-danger { color: #dc3545 !important; }

        /* Ensure dropdown menus are above table content if needed */
        .dropdown-menu { z-index: 1021; /* Bootstrap default is 1000, ensure above sticky headers etc. */ }

    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
<?php
// --- Navigation Bar Rendering ---
// Instantiate the Navbar class and render the navigation menu.
// Handles potential errors during navbar generation.
try {
    // Assumes Navbar constructor requires DB connection, current user's ID, and username.
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    $navbar->render();
} catch (Exception $e) {
    // Log the error for debugging.
    error_log("Error rendering navbar in user_management.php: " . $e->getMessage());
    // Display a user-friendly error message on the page.
    echo '<div class="alert alert-danger m-3" role="alert">Error loading navigation bar. Please try refreshing the page.</div>';
}
?>
<div style="height: 10px;"></div> <?php // Small buffer space below navbar ?>

<div class="container-fluid">

    <?php // --- Alert Container ---
          // This div will hold dynamic success/error messages generated by PHP (on page load) or JavaScript (after AJAX actions). ?>
    <div id="alertContainer">
        <?php // Display messages passed via URL parameters (e.g., after redirect from add_user.php) ?>
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bx bx-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bx bx-error-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php // JavaScript will insert AJAX feedback alerts here ?>
    </div>

    <?php // --- Page Header ---
          // Contains the main title and the primary action button ("Add New User"). ?>
    <div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <h1 class="h3 mb-sm-0 text-nowrap"><?php echo htmlspecialchars($pageTitle); ?></h1>
        <a href="add_user.php" class="btn btn-primary d-flex align-items-center flex-shrink-0">
            <i class="bx bx-user-plus me-2"></i>Add New User
        </a>
    </div>

    <?php // --- Sticky Search Bar ---
          // Allows users to filter the list by typing keywords. ?>
    <div class="search-container sticky-top">
        <div class="input-group">
            <span class="input-group-text" id="search-addon"><i class="bx bx-search"></i></span>
            <input type="text" class="form-control" id="searchInput" placeholder="Search users by name, username, or email..." aria-label="Search users" aria-describedby="search-addon">
        </div>
    </div>

    <?php // --- Table View (Displayed on Larger Screens) --- ?>
    <div class="table-view">
        <div class="card shadow-sm"> <?php // Wrap table in a card for consistent styling ?>
            <div class="card-header bg-light border-bottom d-none d-lg-block"> <?php // Optional header for the card ?>
                <h5 class="card-title mb-0 d-flex align-items-center">
                    <i class="bx bx-list-ul me-2"></i>Users List
                </h5>
            </div>
            <div class="card-body p-0"> <?php // Remove card body padding to let table fill it ?>
                <div class="table-responsive"> <?php // Make table horizontally scrollable on smaller viewports if needed ?>
                    <table class="table table-hover mb-0"> <?php // Use table-hover for row highlighting ?>
                        <thead class="table-light">
                            <tr>
                                <?php // Table Headers ?>
                                <th scope="col" style="width: 5%;">Status</th>
                                <th scope="col">Username</th>
                                <th scope="col">Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">User Type</th>
                                <th scope="col">Associated Contractor</th>
                                <th scope="col">Last Login (UK)</th>
                                <th scope="col" style="width: 8%;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php // --- User Row Loop (Table View) --- ?>
                            <?php if (empty($users)): ?>
                                <?php // Display message if no users are found ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bx bx-user-x me-1 fs-4 align-middle"></i>No users found in the system.
                                        <a href="add_user.php" class="ms-2">Add the first user?</a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php // Loop through each user and display their data in a table row ?>
                                <?php foreach ($users as $user): ?>
                                <tr data-user-id="<?php echo htmlspecialchars($user['id']); // Crucial for targeting row with JS ?>">
                                    <td>
                                        <?php // Status indicator dot ?>
                                        <span class="user-status <?php echo htmlspecialchars($user['status']); ?>" title="<?php echo ucfirst(htmlspecialchars($user['status'])); // Tooltip shows status ?>"></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></td>
                                    <td>
                                        <?php // Clickable email link ?>
                                        <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a>
                                    </td>
                                    <td>
                                        <?php // User type badge ?>
                                        <span class="badge type-badge" data-type="<?php echo htmlspecialchars($user['user_type']); ?>">
                                            <?php echo htmlspecialchars(ucfirst($user['user_type'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php // Associated contractor info (if applicable) ?>
                                        <?php if (($user['user_type'] === 'contractor' || $user['user_type'] === 'manager') && !empty($user['contractor_name'])): ?>
                                            <?php echo htmlspecialchars($user['contractor_name']); ?>
                                            <?php if (!empty($user['contractor_trade'])): ?>
                                                <span class="contractor-info">(<?php echo htmlspecialchars($user['contractor_trade']); ?>)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span> <?php // Hyphen if no association ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php // Last login time (UK format) or 'Never' ?>
                                        <?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '<span class="text-muted">Never</span>'; ?>
                                    </td>
                                    <td>
                                        <?php // Actions Dropdown Menu ?>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="dropdownMenuButton_<?php echo $user['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                Actions
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="dropdownMenuButton_<?php echo $user['id']; ?>">
                                                <li>
                                                    <?php // Edit Details action ?>
                                                    <a class="dropdown-item" href="#" onclick="showEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($user['email']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($user['first_name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($user['last_name']), ENT_QUOTES); ?>')">
                                                        <i class="bx bx-edit me-2"></i>Edit Details
                                                    </a>
                                                </li>
                                                <li>
                                                    <?php // Change Type/Role action ?>
                                                    <a class="dropdown-item" href="#" onclick="showChangeTypeModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['user_type']); ?>', <?php echo $user['contractor_id'] ? $user['contractor_id'] : 'null'; // Pass contractor ID or literal null ?>)">
                                                        <i class="bx bx-transfer-alt me-2"></i>Change Type/Role
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <?php // Activate/Deactivate action ?>
                                                    <a class="dropdown-item <?php echo $user['status'] === 'active' ? 'text-danger' : 'text-success'; // Dynamic class for color ?>"
                                                       href="#"
                                                       onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['status']); // Pass current status ?>')">
                                                        <i class="bx bx-power-off me-2"></i>
                                                        <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; // Dynamic text ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; // End of the user loop ?>
                            <?php endif; // End of check for empty $users ?>
                        </tbody>
                    </table>
                </div><?php // /.table-responsive ?>
            </div><?php // /.card-body ?>
        </div><?php // /.card ?>
    </div><?php // /.table-view ?>

    <?php // --- Card View (Displayed on Smaller Screens) --- ?>
    <div class="card-view">
        <?php // Filter controls specifically for the card view (mobile) ?>
        <div class="mb-3 d-flex justify-content-between align-items-center">
            <span class="text-muted small">
                <i class="bx bx-filter me-1"></i>Filter by:
            </span>
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="filterDropdownMobile" data-bs-toggle="dropdown" aria-expanded="false">
                    All Users <?php // Default text, will be updated by JS ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="filterDropdownMobile">
                    <?php // Filter options - JS will handle the filtering logic ?>
                    <li><a class="dropdown-item filter-users active" href="#" data-filter="all" data-label="All Users">All Users</a></li>
                    <li><a class="dropdown-item filter-users" href="#" data-filter="active" data-label="Active Only">Active Only</a></li>
                    <li><a class="dropdown-item filter-users" href="#" data-filter="inactive" data-label="Inactive Only">Inactive Only</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php // Dynamically add filter options for each user type defined in PHP ?>
                    <?php foreach (USER_TYPES as $type => $label): ?>
                        <li><a class="dropdown-item filter-users" href="#" data-filter="<?php echo htmlspecialchars($type); ?>" data-label="<?php echo htmlspecialchars($label); ?>s"><?php echo htmlspecialchars($label); ?>s</a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>

        <?php // --- User Card Loop (Card View) --- ?>
        <?php if (empty($users)): ?>
            <?php // Display message if no users are found ?>
            <div class="alert alert-info text-center" role="alert">
                <i class="bx bx-info-circle me-1"></i>No users found in the system.
            </div>
        <?php else: ?>
            <?php // Loop through each user and create a card for them ?>
            <?php foreach ($users as $user): ?>
            <div class="user-card card"
                 data-user-id="<?php echo htmlspecialchars($user['id']); // ID for JS targeting ?>"
                 data-user-status="<?php echo htmlspecialchars($user['status']); // Status for JS filtering ?>"
                 data-user-type="<?php echo htmlspecialchars($user['user_type']); // Type for JS filtering ?>">
                <?php // Card Header: Status, Username, Type Badge ?>
                <div class="card-header">
                    <div class="d-flex align-items-center">
                        <span class="user-status <?php echo htmlspecialchars($user['status']); ?> me-2" title="<?php echo ucfirst(htmlspecialchars($user['status'])); ?>"></span>
                        <strong class="text-truncate"><?php echo htmlspecialchars($user['username']); ?></strong>
                    </div>
                    <span class="badge type-badge" data-type="<?php echo htmlspecialchars($user['user_type']); ?>">
                        <?php echo htmlspecialchars(ucfirst($user['user_type'])); ?>
                    </span>
                </div>
                <?php // Card Body: Detailed user information in rows ?>
                <div class="card-body">
                    <div class="user-data">
                        <div class="data-row">
                            <div class="data-label">Name</div>
                            <div class="data-value"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></div>
                        </div>
                        <div class="data-row">
                            <div class="data-label">Email</div>
                            <div class="data-value text-break"><a href="mailto:<?php echo htmlspecialchars($user['email']); ?>"><?php echo htmlspecialchars($user['email']); ?></a></div>
                        </div>
                        <?php // Conditionally display contractor row ?>
                        <?php if (($user['user_type'] === 'contractor' || $user['user_type'] === 'manager') && !empty($user['contractor_name'])): ?>
                        <div class="data-row">
                            <div class="data-label">Contractor</div>
                            <div class="data-value">
                                <?php echo htmlspecialchars($user['contractor_name']); ?>
                                <?php if (!empty($user['contractor_trade'])): ?>
                                    <span class="contractor-info d-block">(<?php echo htmlspecialchars($user['contractor_trade']); ?>)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <div class="data-row">
                            <div class="data-label">Status</div>
                            <div class="data-value">
                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                    <?php echo ucfirst(htmlspecialchars($user['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="data-row">
                            <div class="data-label">Last Login (UK)</div>
                            <div class="data-value"><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : '<span class="text-muted">Never</span>'; ?></div>
                        </div>
                    </div>
                </div>
                <?php // Card Actions Footer: Buttons for user actions ?>
                <div class="card-actions">
                    <button class="btn btn-sm btn-outline-primary action-btn" onclick="showEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($user['email']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($user['first_name']), ENT_QUOTES); ?>', '<?php echo htmlspecialchars(addslashes($user['last_name']), ENT_QUOTES); ?>')">
                        <i class="bx bx-edit"></i> <span class="button-text">Edit</span>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary action-btn" onclick="showChangeTypeModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['user_type']); ?>', <?php echo $user['contractor_id'] ? $user['contractor_id'] : 'null'; ?>)">
                        <i class="bx bx-transfer-alt"></i> <span class="button-text">Type</span>
                    </button>
                    <button class="btn btn-sm btn-outline-<?php echo $user['status'] === 'active' ? 'danger' : 'success'; ?> action-btn ms-auto" onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['status']); ?>')">
                        <i class="bx bx-power-off"></i> <?php // Display text dynamically based on status ?>
                        <?php echo $user['status'] === 'active' ? '<span class="button-text">Deactivate</span>' : '<span class="button-text">Activate</span>'; ?>
                    </button>
                </div>
            </div><?php // /.user-card ?>
            <?php endforeach; // End user loop ?>
        <?php endif; // End check for empty users ?>
        <?php // Message shown in card view when search/filter yields no results ?>
        <div id="noResultsCard" class="alert alert-warning text-center" style="display: none;" role="alert">
            <i class="bx bx-search-alt me-1"></i>No users match your current search or filter criteria.
        </div>
    </div><?php // /.card-view ?>

</div><?php // /.container-fluid ?>

<?php // --- Modals ---
      // These are hidden by default and shown via JavaScript when action buttons/links are clicked. ?>

<!-- Change Type Modal -->
<div class="modal fade" id="changeTypeModal" tabindex="-1" aria-labelledby="changeTypeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down"> <?php // Centered, fullscreen on small screens ?>
        <div class="modal-content">
            <form id="changeTypeForm" novalidate> <?php // Disable default browser validation ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="changeTypeModalLabel">Change User Type/Role</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php // --- Hidden fields for AJAX request --- ?>
                    <input type="hidden" name="action" value="change_type">
                    <input type="hidden" name="ajax" value="true">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); // Embed current CSRF token ?>">
                    <input type="hidden" name="user_id" id="typeUserId"> <?php // User ID will be set by JS ?>

                    <?php // --- User Type Selection --- ?>
                    <div class="mb-3">
                        <label for="newType" class="form-label">New User Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="new_type" id="newType" required aria-describedby="newTypeFeedback">
                            <option value="" disabled selected>Select new type...</option>
                            <?php // Populate options from the USER_TYPES constant ?>
                            <?php foreach (USER_TYPES as $type => $label): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="newTypeFeedback" class="invalid-feedback">Please select a valid user type.</div>
                    </div>

                    <?php // --- Contractor Selection (Conditional) --- ?>
                    <div class="mb-3 contractor-fields" style="display: none;"> <?php // Initially hidden ?>
                        <label for="contractorId" class="form-label">Associate Contractor</label> <?php // Label might be updated by JS ?>
                        <select class="form-select" name="contractor_id" id="contractorId" aria-describedby="contractorIdFeedback contractorHelp">
                            <option value="">None (or select a contractor)</option>
                            <?php // Populate options with active contractors ?>
                            <?php if (!empty($contractors)): ?>
                                <?php foreach ($contractors as $contractor): ?>
                                    <option value="<?php echo htmlspecialchars($contractor['id']); ?>">
                                        <?php echo htmlspecialchars($contractor['company_name']); ?>
                                        (<?php echo htmlspecialchars($contractor['trade']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No active contractors available</option>
                            <?php endif; ?>
                        </select>
                        <div id="contractorHelp" class="form-text">Select the contractor company if applicable.</div>
                        <div id="contractorIdFeedback" class="invalid-feedback">Contractor selection is required for the 'Contractor' user type.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php // Spinner icon for loading state ?>
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content">
            <form id="editUserForm" novalidate> <?php // Disable default browser validation ?>
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php // --- Hidden fields for AJAX request --- ?>
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="ajax" value="true">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <input type="hidden" name="user_id" id="editUserId"> <?php // User ID set by JS ?>

                    <?php // --- Username Input --- ?>
                    <div class="mb-3">
                        <label for="editUsername" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="editUsername" required minlength="3" pattern="^[a-zA-Z0-9_.-]+$" title="Username can contain letters, numbers, periods, hyphens, and underscores.">
                        <div class="invalid-feedback">Please enter a valid username (at least 3 characters; letters, numbers, ., _, - allowed).</div>
                    </div>

                    <?php // --- Email Input --- ?>
                    <div class="mb-3">
                        <label for="editEmail" class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" id="editEmail" required>
                        <div class="invalid-feedback">Please enter a valid email address.</div>
                    </div>

                    <?php // --- First Name Input --- ?>
                    <div class="mb-3">
                        <label for="editFirstName" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                         <div class="invalid-feedback">Please enter the user's first name.</div>
                    </div>

                    <?php // --- Last Name Input --- ?>
                    <div class="mb-3">
                        <label for="editLastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="last_name" id="editLastName" required>
                        <div class="invalid-feedback">Please enter the user's last name.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?php // Spinner icon for loading state ?>
                        <span class="spinner-border spinner-border-sm d-none me-1" role="status" aria-hidden="true"></span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php // --- JavaScript Includes and Inline Script --- ?>

<!-- Bootstrap 5 Bundle JS (includes Popper.js for tooltips, dropdowns, etc.) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Inline JavaScript for page-specific functionality -->
<script>
    /**
     * Inline JavaScript for User Management Page (user_management.php)
     * Handles:
     * - Sticky search bar behavior (adding shadow on scroll).
     * - Live search filtering of users in both table and card views.
     * - Filtering users based on type/status in the mobile (card) view.
     * - Populating and triggering Bootstrap modals for editing user details and changing user type/role.
     * - Handling AJAX form submissions for user edits, type changes, and status changes, including validation and feedback.
     * - Updating the UI dynamically after successful AJAX operations without requiring a page refresh.
     * - Displaying dynamic alert messages for success or error feedback.
     * - Utility functions for managing button loading states and applying visual animations.
     */

    // --- Global Variables & Constants ---
    const csrfToken = '<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>'; // CSRF token from PHP session for securing AJAX requests.
    const userTypes = <?php echo json_encode(array_keys(USER_TYPES)); ?>; // Array of valid user type strings (e.g., ['admin', 'manager', ...]).
    const searchInput = document.getElementById('searchInput'); // The main search input field element.
    const alertContainer = document.getElementById('alertContainer'); // The div where feedback alerts are displayed.
    const tableBody = document.querySelector('.table-view tbody'); // The tbody element of the user list table.
    const cardContainer = document.querySelector('.card-view'); // The container holding all user cards for mobile view.
    const noResultsCard = document.getElementById('noResultsCard'); // The specific card shown when no users match filters/search in card view.
    const filterDropdownButton = document.getElementById('filterDropdownMobile'); // The button displaying the current filter in mobile view.
    const filterLinks = document.querySelectorAll('.filter-users'); // NodeList of all filter links in the mobile dropdown.
    let currentFilter = 'all'; // String variable tracking the currently active filter ('all', 'active', 'inactive', or a user type). Initialized to 'all'.

    // --- Event Listeners Setup ---

    /**
     * Event Listener: Adds a 'scrolled' class to the search bar container when the page
     * is scrolled down, used by CSS to apply a box-shadow.
     */
    window.addEventListener('scroll', function() {
        const searchContainer = document.querySelector('.search-container');
        if (!searchContainer) return; // Exit if search container element is not found.
        // Add 'scrolled' class if window's vertical scroll position is greater than the search bar's top offset (or a fallback value).
        if (window.scrollY > (searchContainer.offsetTop || 60)) { // Use 60px as a reasonable fallback offset.
            searchContainer.classList.add('scrolled');
        } else {
            searchContainer.classList.remove('scrolled');
        }
    });

    /**
     * Event Listener: Dynamically manages the 'Associate Contractor' dropdown in the 'Change Type' modal.
     * Shows/hides the dropdown, sets the 'required' attribute, and updates help text based on the
     * selected 'New User Type'.
     */
    document.getElementById('newType')?.addEventListener('change', function() {
        const modal = this.closest('.modal'); // Find the parent modal element.
        if (!modal) return; // Exit if not within a modal context.

        // Get references to the relevant elements within the modal.
        const contractorFields = modal.querySelector('.contractor-fields');
        const contractorSelect = modal.querySelector('#contractorId');
        const helpText = modal.querySelector('#contractorHelp');
        const contractorLabel = contractorFields?.querySelector('label[for="contractorId"]'); // Get the label element.

        // Ensure all necessary elements were found before proceeding.
        if (!contractorFields || !contractorSelect || !helpText || !contractorLabel) {
            console.error("Could not find all contractor field elements in the modal.");
            return;
        }

        const selectedType = this.value; // Get the value of the selected user type (e.g., 'manager', 'contractor').

        // --- Logic to control the contractor dropdown ---
        if (selectedType === 'contractor' || selectedType === 'manager') {
            // Show the contractor selection section if type is 'Contractor' or 'Manager'.
            contractorFields.style.display = 'block';
            const isRequired = (selectedType === 'contractor'); // Selection is required only if type is 'Contractor'.
            contractorSelect.required = isRequired; // Set the HTML 'required' attribute accordingly.

            // Update the label to include a red asterisk '*' if required.
            contractorLabel.innerHTML = `Associate Contractor ${isRequired ? '<span class="text-danger">*</span>' : ''}`;
            // Update the help text to clarify if selection is required or optional.
            helpText.textContent = isRequired
                ? 'Required: Select the contractor company for this user.'
                : 'Optional: Associate this manager with a contractor company.';

        } else {
            // Hide the contractor selection section for all other user types.
            contractorFields.style.display = 'none';
            contractorSelect.required = false; // Ensure it's not marked as required.
            contractorSelect.value = ''; // Reset the dropdown selection.
        }
        // --- End of contractor dropdown logic ---

        // Clear any previous validation styling on the contractor select when the type changes.
         contractorSelect.classList.remove('is-invalid');
         contractorSelect.classList.remove('is-valid');
    });

    /**
     * Event Listener: Handles the 'input' event on the search field.
     * Filters the displayed users (both table and cards) in real-time as the user types.
     */
    searchInput?.addEventListener('input', function() {
        // Get the current search value, convert to lowercase, and remove leading/trailing whitespace.
        const searchValue = this.value.toLowerCase().trim();
        // Call the main filtering function, passing the search term and the currently active filter type.
        filterUsers(searchValue, currentFilter);
    });

    /**
     * Event Listener: Handles clicks on the filter options in the mobile view's dropdown menu.
     * Updates the `currentFilter` state and triggers a re-filtering of the user list.
     */
    filterLinks.forEach(filterLink => {
        filterLink.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent the default anchor link behavior (page jump).

            // Get the filter type (e.g., 'active') and display label (e.g., 'Active Only') from the clicked link's data attributes.
            currentFilter = this.getAttribute('data-filter');
            const filterLabel = this.getAttribute('data-label');

            // Update the text content of the main filter dropdown button to reflect the selection.
            if(filterDropdownButton) filterDropdownButton.textContent = filterLabel;

            // Visually update the active state in the dropdown list: remove 'active' class from all, add to the clicked one.
            filterLinks.forEach(link => link.classList.remove('active'));
            this.classList.add('active');

            // Re-apply the filtering logic using the newly selected filter type and the current value in the search input.
            filterUsers(searchInput.value.toLowerCase().trim(), currentFilter);
        });
    });

    /**
     * Event Listener: Attaches submit handlers to the modal forms (#editUserForm, #changeTypeForm).
     * Prevents default submission, performs client-side validation using Bootstrap's API,
     * and initiates an AJAX request if the form is valid. Handles success and error responses.
     */
    document.querySelectorAll('#editUserForm, #changeTypeForm').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault(); // Stop the browser from performing a standard form submission (which would reload the page).

            // --- Client-Side Validation ---
            // Use Bootstrap's built-in form validation check.
            if (!form.checkValidity()) {
                e.stopPropagation(); // Stop the event from propagating further if validation fails.
                form.classList.add('was-validated'); // Apply Bootstrap's visual feedback classes for invalid fields.
                return; // Exit the function; do not proceed with AJAX submission.
            }
            // If checkValidity() returns true, the form is considered valid according to HTML5 constraints (required, pattern, etc.).

            // --- AJAX Submission ---
            const submitButton = form.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, true); // Disable the button and show the loading spinner.

            const formData = new FormData(form); // Create a FormData object to easily collect all form field values.

            // Perform the AJAX request using the Fetch API.
            fetch('user_management.php', { // POST the data back to this same PHP file.
                method: 'POST',
                body: formData // The FormData object is automatically sent with the correct content type.
            })
            .then(response => {
                // --- Handle HTTP Response Status ---
                // Check if the server responded with an error status code (e.g., 404, 500).
                if (!response.ok) {
                    // If there's an HTTP error, create an error object to be caught by the .catch block.
                    throw new Error(`Server responded with status: ${response.status} (${response.statusText})`);
                }
                // If the status is OK (e.g., 200), attempt to parse the response body as JSON.
                return response.json();
            })
            .then(data => {
                // --- Process Server's JSON Response ---
                // Check the 'success' flag and presence of 'user' data in the parsed JSON response from the PHP script.
                if (data.success && data.user) {
                    // --- Handle Success ---
                    showAlert('success', data.message || 'Operation completed successfully.'); // Display a success message.

                    // Call the appropriate JavaScript function to update the UI based on which form was submitted.
                    if (form.id === 'editUserForm') {
                        updateUserInfoUI(data.user); // Update name, email, username displays.
                    } else if (form.id === 'changeTypeForm') {
                        updateUserTypeUI(data.user); // Update type badge, contractor info.
                    }

                    // Close the modal window since the operation was successful.
                    const modalElement = form.closest('.modal'); // Find the modal element containing the form.
                    const modalInstance = bootstrap.Modal.getInstance(modalElement); // Get the Bootstrap Modal instance associated with the element.
                    modalInstance?.hide(); // Call Bootstrap's hide method.
                } else {
                    // --- Handle Failure ---
                    // If the server indicates failure ('success' is false), display the error message provided by the server.
                    showAlert('danger', data.message || 'An error occurred. Please review the form and try again.');
                    // Optionally, keep the modal open for the user to correct errors.
                    // Re-applying 'was-validated' might highlight fields again if server-side validation failed.
                    form.classList.add('was-validated');
                }
            })
            .catch(error => {
                // --- Handle Network or Parsing Errors ---
                // Catch errors related to the network connection itself (e.g., server down) or issues parsing the JSON response.
                console.error('AJAX Form Submission Error:', error); // Log the detailed error to the console for debugging.
                showAlert('danger', `A network or server error occurred: ${error.message}. Please check your connection and try again.`); // Show a user-friendly error message.
            })
            .finally(() => {
                // --- Cleanup ---
                // This block executes regardless of whether the request succeeded or failed.
                setButtonLoading(submitButton, false); // Always re-enable the submit button and hide the spinner.
            });
        });
    });

    /**
     * Event Listener: Attaches a listener to each modal ('editUserModal', 'changeTypeModal').
     * When a modal is fully hidden ('hidden.bs.modal' event), it resets the form inside it
     * (clears fields, removes validation styles) and performs any modal-specific cleanup.
     */
    ['editUserModal', 'changeTypeModal'].forEach(modalId => {
        const modalElement = document.getElementById(modalId);
        modalElement?.addEventListener('hidden.bs.modal', function () { // Event fires after modal is hidden.
            const form = this.querySelector('form');
            if (form) {
                form.reset(); // Reset all input fields to their default state.
                form.classList.remove('was-validated'); // Remove Bootstrap's validation styling classes.
            }
            // Specific cleanup needed only for the 'Change Type' modal:
            if (modalId === 'changeTypeModal') {
                // Ensure the contractor fields section is hidden when the modal is closed.
                const contractorFields = this.querySelector('.contractor-fields');
                if (contractorFields) contractorFields.style.display = 'none';
                // Ensure the contractor select is not marked as required.
                const contractorSelect = this.querySelector('#contractorId');
                if (contractorSelect) contractorSelect.required = false;
            }
            // Ensure the submit button is reset (spinner hidden, button enabled).
            const submitButton = form?.querySelector('button[type="submit"]');
            setButtonLoading(submitButton, false);
        });
    });

    /**
     * Function: Automatically dismisses alerts that were rendered initially on page load
     * (e.g., from PHP redirects) after a predefined delay.
     */
    const autoDismissStaticAlerts = () => {
        // Select all dismissible alerts currently present in the alert container.
        document.querySelectorAll('#alertContainer .alert-dismissible').forEach(alert => {
            // Check if the alert hasn't already been removed from the DOM.
            if (alert.parentNode === alertContainer) {
                setTimeout(() => {
                    // Double-check parentage before attempting to close, in case it was manually closed in the meantime.
                    if (alert.parentNode === alertContainer) {
                        // Get or create a Bootstrap Alert instance for the element.
                        const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                        bsAlert?.close(); // Use Bootstrap's API to close the alert smoothly.
                    }
                }, 5000); // 5000 milliseconds = 5 seconds delay.
            }
        });
    };
    // Execute this function once when the script loads to handle any initial alerts.
    autoDismissStaticAlerts();


    // --- Modal Trigger Functions ---
    // These functions are typically called by 'onclick' attributes in the HTML
    // (e.g., on buttons or links in the user list) to open the appropriate modal
    // and pre-populate it with the relevant user's data.

    /**
     * Opens and populates the 'Change User Type/Role' modal with the specified user's current data.
     * Configures the dropdowns and contractor field based on the user's current type and association.
     * @param {number} userId - The ID of the user being modified.
     * @param {string} currentType - The user's current type (e.g., 'manager').
     * @param {number|null} currentContractorId - The user's current associated contractor ID, or null if none.
     */
    function showChangeTypeModal(userId, currentType, currentContractorId) {
        const modalElement = document.getElementById('changeTypeModal');
        if (!modalElement) {
            console.error("Change Type Modal element (#changeTypeModal) not found in the DOM!");
            return; // Prevent errors if the modal is missing.
        }

        // Get or create a Bootstrap Modal instance for the element.
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        // Get references to the form and key input elements within the modal.
        const form = document.getElementById('changeTypeForm');
        const typeSelect = document.getElementById('newType');
        const contractorSelect = document.getElementById('contractorId');
        const contractorFields = modalElement.querySelector('.contractor-fields');
        const helpText = modalElement.querySelector('#contractorHelp');
        const contractorLabel = contractorFields?.querySelector('label[for="contractorId"]');

        // --- Reset Modal State Before Populating ---
        form.reset(); // Clear any values from previous openings.
        form.classList.remove('was-validated'); // Remove validation styling.

        // --- Populate Hidden User ID ---
        document.getElementById('typeUserId').value = userId;

        // --- Configure User Type Dropdown ---
        typeSelect.value = ''; // Ensure no type is pre-selected by default.
        Array.from(typeSelect.options).forEach(option => {
            // Disable the option that matches the user's current type (can't change to the same type).
            option.disabled = (option.value === currentType);
            // Remove any '(Current)' text appended in previous openings.
            option.textContent = option.textContent.replace(' (Current)', '');
            // Append '(Current)' to the text of the option matching the user's current type for clarity.
            if (option.value === currentType) {
                option.textContent += ' (Current)';
            }
        });

        // --- Configure Contractor Dropdown (Initial State) ---
        // Set the initial visibility, selected value, and requirement based on the *user being edited*.
        if (contractorFields && contractorSelect && helpText && contractorLabel) {
            if (currentType === 'contractor' || currentType === 'manager') {
                // If the current user is a Contractor or Manager:
                contractorFields.style.display = 'block'; // Show the contractor section.
                // Select the user's current contractor (or empty string if none/null).
                contractorSelect.value = currentContractorId || '';
                // Set 'required' attribute only if the *current* type is 'Contractor'.
                const isRequired = (currentType === 'contractor');
                contractorSelect.required = isRequired;
                // Set the label and help text appropriate for the *current* type.
                contractorLabel.innerHTML = `Associate Contractor ${isRequired ? '<span class="text-danger">*</span>' : ''}`;
                helpText.textContent = isRequired
                    ? 'Required: Select the contractor company for this user.'
                    : 'Optional: Associate this manager with a contractor company.';
            } else {
                // If the current user is not a Contractor or Manager:
                contractorFields.style.display = 'none'; // Hide the contractor section.
                contractorSelect.required = false; // Ensure it's not required.
                contractorSelect.value = ''; // Reset the selection.
            }
        } else {
            console.error("Contractor field elements not found in Change Type modal.");
        }

        // --- Show the Modal ---
        modalInstance.show(); // Use Bootstrap's API to display the modal.
    }

    /**
     * Opens and populates the 'Edit User Details' modal with the specified user's current information.
     * @param {number} userId - The ID of the user being edited.
     * @param {string} username - User's current username.
     * @param {string} email - User's current email.
     * @param {string} firstName - User's current first name.
     * @param {string} lastName - User's current last name.
     */
    function showEditUserModal(userId, username, email, firstName, lastName) {
        const modalElement = document.getElementById('editUserModal');
        if (!modalElement) {
            console.error("Edit User Modal element (#editUserModal) not found in the DOM!");
            return; // Prevent errors if the modal is missing.
        }

        // Get or create a Bootstrap Modal instance.
        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalElement);
        // Get a reference to the form within the modal.
        const form = document.getElementById('editUserForm');

        // --- Reset Modal State Before Populating ---
        form.reset(); // Clear any values from previous openings.
        form.classList.remove('was-validated'); // Remove validation styling.

        // --- Populate Form Fields ---
        // Set the values of the input fields using the data passed to the function.
        document.getElementById('editUserId').value = userId;
        document.getElementById('editUsername').value = username;
        document.getElementById('editEmail').value = email;
        document.getElementById('editFirstName').value = firstName;
        document.getElementById('editLastName').value = lastName;

        // --- Show the Modal ---
        modalInstance.show(); // Use Bootstrap's API to display the modal.
    }

    // --- AJAX Action Functions ---
    // These functions are called directly by user interactions (e.g., button clicks)
    // to initiate specific actions via AJAX requests to the backend.

    /**
     * Initiates an AJAX request to toggle a user's status between 'active' and 'inactive'.
     * Prompts the user for confirmation before proceeding with the request.
     * Handles success and error responses by showing alerts and updating the UI.
     * @param {number} userId - The ID of the user whose status needs to be toggled.
     * @param {string} currentStatus - The user's current status ('active' or 'inactive').
     */
    function toggleUserStatus(userId, currentStatus) {
        // Determine the action verb for the confirmation message and the target status value.
        const actionVerb = currentStatus === 'active' ? 'deactivate' : 'activate';
        const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

        // Display a confirmation dialog to the user.
        if (!confirm(`Are you sure you want to ${actionVerb} this user account?`)) {
            return; // If the user clicks 'Cancel', abort the operation.
        }

        // --- Prepare AJAX Request Data ---
        const formData = new FormData();
        formData.append('action', 'change_status'); // Specify the backend action handler.
        formData.append('ajax', 'true'); // Flag for the PHP backend to identify AJAX request.
        formData.append('csrf_token', csrfToken); // Include the CSRF token for security validation.
        formData.append('user_id', userId); // Identify the target user.
        formData.append('new_status', newStatus); // Specify the desired new status.

        // Optional: Implement a visual loading indicator (e.g., overlay, global spinner) here if desired.
        // showGlobalLoader(true);

        // --- Perform AJAX Request ---
        fetch('user_management.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
             // Check for network/server errors first.
             if (!response.ok) throw new Error(`Server responded with status: ${response.status}`);
             // Attempt to parse the response as JSON.
             return response.json();
        })
        .then(data => {
            // --- Handle Server Response ---
            if (data.success && data.user) {
                // On success: show success alert and update the UI.
                showAlert('success', data.message || `User ${actionVerb}d successfully.`);
                updateUserStatusUI(data.user); // Call function to refresh relevant UI parts.
            } else {
                // On failure: show error alert with message from server.
                showAlert('danger', data.message || `Failed to ${actionVerb} user.`);
            }
        })
        .catch(error => {
            // --- Handle Fetch/Network Errors ---
            console.error('Toggle User Status Error:', error); // Log detailed error.
            showAlert('danger', `An error occurred while updating status: ${error.message}. Please try again.`); // Show user-friendly error.
        })
        .finally(() => {
            // Optional: Hide the global loading indicator here.
            // showGlobalLoader(false);
        });
    }

    // --- UI Update Functions ---
    // These functions are called *after* a successful AJAX operation to reflect
    // the changes in the user interface (table rows, cards) without needing a full page reload.

    /**
     * Updates the visual representation of a user's status (indicator dot, badge, action buttons)
     * in both the table view and the card view after a status change.
     * @param {object} user - The updated user data object (containing at least id, status) received from the server.
     */
    function updateUserStatusUI(user) {
        const { id, status } = user;
        if (id === undefined || status === undefined) {
            console.error("updateUserStatusUI called with incomplete user data:", user);
            return;
        }

        // --- Determine derived values based on the new status ---
        const statusLabel = status.charAt(0).toUpperCase() + status.slice(1); // "Active" or "Inactive"
        const isNowActive = status === 'active';
        const statusIndicatorClass = status; // CSS class 'active' or 'inactive' for the dot.
        const statusBadgeBgClass = isNowActive ? 'bg-success' : 'bg-danger'; // Bootstrap background class for the badge.
        const actionButtonOutlineClass = isNowActive ? 'outline-danger' : 'outline-success'; // Button style (red to deactivate, green to activate).
        const actionButtonText = isNowActive ? 'Deactivate' : 'Activate'; // Text for the action button/link.
        const actionIconHtml = '<i class="bx bx-power-off"></i>'; // Icon HTML.

        // --- Update Table Row (if table view is present) ---
        const tableRow = tableBody?.querySelector(`tr[data-user-id="${id}"]`);
        if (tableRow) {
            // Update status indicator dot (class and title).
            const statusIndicator = tableRow.querySelector('.user-status');
            if (statusIndicator) {
                statusIndicator.className = `user-status ${statusIndicatorClass}`;
                statusIndicator.title = statusLabel;
            }
            // Update status badge (assuming it's in the 7th column, index 6).
            const statusCell = tableRow.cells[6];
            const statusBadge = statusCell?.querySelector('.badge');
            if (statusBadge) {
                statusBadge.className = `badge ${statusBadgeBgClass}`;
                statusBadge.textContent = statusLabel;
            }
            // Update the Activate/Deactivate link in the dropdown actions menu.
            const statusActionLink = tableRow.querySelector('.dropdown-menu a[onclick*="toggleUserStatus"]');
            if (statusActionLink) {
                // Update link color class.
                statusActionLink.className = `dropdown-item ${isNowActive ? 'text-danger' : 'text-success'}`;
                // Update link text and icon.
                statusActionLink.innerHTML = `<i class="bx bx-power-off me-2"></i> ${actionButtonText}`;
                // CRITICAL: Update the onclick attribute to pass the *new* current status for the *next* time it's clicked.
                statusActionLink.setAttribute('onclick', `toggleUserStatus(${id}, '${status}')`);
            }
            // Apply a visual animation to highlight the changed row.
            animateChange(tableRow);
        }

        // --- Update Card (if card view is present) ---
        const cardElement = cardContainer?.querySelector(`.user-card[data-user-id="${id}"]`);
        if (cardElement) {
            // Update the data-user-status attribute (used by JavaScript filtering).
            cardElement.setAttribute('data-user-status', status);
            // Update status indicator dot in the card header.
            const statusIndicator = cardElement.querySelector('.card-header .user-status');
            if (statusIndicator) {
                statusIndicator.className = `user-status ${statusIndicatorClass} me-2`;
                statusIndicator.title = statusLabel;
            }
            // Update status badge within the card body (find the correct row first).
            const statusRow = Array.from(cardElement.querySelectorAll('.data-row')).find(row => row.querySelector('.data-label')?.textContent === 'Status');
            const statusBadge = statusRow?.querySelector('.badge');
            if (statusBadge) {
                statusBadge.className = `badge ${statusBadgeBgClass}`;
                statusBadge.textContent = statusLabel;
            }
            // Update the Activate/Deactivate button in the card actions footer.
            const statusButton = cardElement.querySelector('.card-actions button[onclick*="toggleUserStatus"]');
            if (statusButton) {
                // Update button style class.
                statusButton.className = `btn btn-sm ${actionButtonOutlineClass} action-btn ms-auto`;
                // Update button icon and text (including the span for responsive text).
                statusButton.innerHTML = `${actionIconHtml} <span class="button-text">${actionButtonText}</span>`;
                 // CRITICAL: Update the onclick attribute for the next click.
                statusButton.setAttribute('onclick', `toggleUserStatus(${id}, '${status}')`);
            }
            // Apply visual animation to highlight the changed card.
            animateChange(cardElement);
        }
        // --- End of UI Updates ---

        // Re-apply the current search/filter criteria to ensure the updated item is correctly shown or hidden based on the new status.
        filterUsers(searchInput.value.toLowerCase().trim(), currentFilter);
    }

    /**
     * Updates UI elements related to user type and contractor association (badges, text, links)
     * in both table and card views after a type/role change.
     * @param {object} user - The updated user data object from the server response.
     */
    function updateUserTypeUI(user) {
        const { id, user_type, contractor_id, contractor_name, contractor_trade } = user;
         if (id === undefined || user_type === undefined) {
            console.error("updateUserTypeUI called with incomplete user data:", user);
            return;
        }
        const typeLabel = user_type.charAt(0).toUpperCase() + user_type.slice(1); // e.g., "Manager"

        // --- Update Table Row ---
        const tableRow = tableBody?.querySelector(`tr[data-user-id="${id}"]`);
        if (tableRow) {
            // Update user type badge (assuming 5th column, index 4).
            const typeCell = tableRow.cells[4];
            const typeBadge = typeCell?.querySelector('.badge');
            if (typeBadge) {
                typeBadge.setAttribute('data-type', user_type); // Update data attribute for CSS styling.
                typeBadge.textContent = typeLabel; // Update displayed text.
                typeBadge.className = `badge type-badge`; // Reset class to rely on data-type CSS selector for color.
            }
            // Update contractor cell content (assuming 6th column, index 5).
            const contractorCell = tableRow.cells[5];
            if (contractorCell) {
                updateContractorInfo(contractorCell, user); // Use helper function for consistent formatting.
            }
            // Update the 'Change Type/Role' link in the dropdown menu's onclick attribute.
            const changeTypeLink = tableRow.querySelector('.dropdown-menu a[onclick*="showChangeTypeModal"]');
            if (changeTypeLink) {
                // Pass the *new* type and contractor ID for the next time the modal is opened for this user.
                changeTypeLink.setAttribute('onclick', `showChangeTypeModal(${id}, '${user_type}', ${contractor_id || 'null'})`);
            }
            animateChange(tableRow); // Apply visual feedback animation.
        }

        // --- Update Card ---
        const cardElement = cardContainer?.querySelector(`.user-card[data-user-id="${id}"]`);
        if (cardElement) {
            // Update the data-user-type attribute (used by JavaScript filtering).
            cardElement.setAttribute('data-user-type', user_type);
            // Update the type badge in the card header.
            const typeBadge = cardElement.querySelector('.card-header .badge');
            if (typeBadge) {
                typeBadge.setAttribute('data-type', user_type);
                typeBadge.textContent = typeLabel;
                typeBadge.className = `badge type-badge`; // Reset class for CSS data-type selector.
            }

            // --- Update Contractor Row in Card Body (Add/Remove/Modify) ---
            const userDataDiv = cardElement.querySelector('.user-data');
            if (userDataDiv) {
                // Find the existing contractor row by its label, if it exists.
                let contractorRow = Array.from(userDataDiv.querySelectorAll('.data-row')).find(row => row.querySelector('.data-label')?.textContent === 'Contractor');
                // Determine if contractor info *should* be displayed based on the new type and data.
                const shouldDisplayContractor = (user_type === 'contractor' || user_type === 'manager') && contractor_name;

                if (shouldDisplayContractor) {
                    // If it should be displayed:
                    if (!contractorRow) {
                        // If the row doesn't exist, create it dynamically.
                        contractorRow = document.createElement('div');
                        contractorRow.className = 'data-row';
                        // Basic structure with label and empty value div.
                        contractorRow.innerHTML = `<div class="data-label">Contractor</div><div class="data-value"></div>`;
                        // Try to insert it logically, e.g., after the 'Email' row.
                        const emailRow = Array.from(userDataDiv.querySelectorAll('.data-row')).find(row => row.querySelector('.data-label')?.textContent === 'Email');
                        if (emailRow && emailRow.nextSibling) {
                            userDataDiv.insertBefore(contractorRow, emailRow.nextSibling);
                        } else { // Fallback: append to the end if insertion point not found.
                            userDataDiv.appendChild(contractorRow);
                        }
                    }
                    // Update the content of the contractor row's value div using the helper function.
                    updateContractorInfo(contractorRow.querySelector('.data-value'), user);
                } else if (contractorRow) {
                    // If contractor info should *not* be displayed, but the row exists, remove it.
                    contractorRow.remove();
                }
            } // --- End of Contractor Row Update ---

            // Update the 'Type' button's onclick attribute in the card actions footer.
            const changeTypeButton = cardElement.querySelector('.card-actions button[onclick*="showChangeTypeModal"]');
            if (changeTypeButton) {
                 // Pass the *new* type and contractor ID for the next modal opening.
                 changeTypeButton.setAttribute('onclick', `showChangeTypeModal(${id}, '${user_type}', ${contractor_id || 'null'})`);
            }
            animateChange(cardElement); // Apply visual feedback animation.
        }
        // --- End of UI Updates ---

        // Re-apply filtering to ensure visibility is correct after the update.
        filterUsers(searchInput.value.toLowerCase().trim(), currentFilter);
    }

    /**
     * Updates UI elements related to basic user information (username, name, email)
     * in both table and card views after a successful edit.
     * @param {object} user - The updated user data object from the server response.
     */
    function updateUserInfoUI(user) {
        const { id, username, email, first_name, last_name } = user;
         if (id === undefined || username === undefined || email === undefined) {
            console.error("updateUserInfoUI called with incomplete user data:", user);
            return;
        }
        // Construct full name safely, handling potentially empty first/last names.
        const fullName = `${first_name || ''} ${last_name || ''}`.trim();

        // --- Update Table Row ---
        const tableRow = tableBody?.querySelector(`tr[data-user-id="${id}"]`);
        if (tableRow) {
            // Update cell content directly (assuming specific column order: 2:Username, 3:Name, 4:Email).
            const usernameCell = tableRow.cells[1];
            const nameCell = tableRow.cells[2];
            const emailCell = tableRow.cells[3];
            if (usernameCell) usernameCell.textContent = username;
            if (nameCell) nameCell.textContent = fullName;
            // Update email cell, preserving the mailto: link structure.
            if (emailCell) emailCell.innerHTML = `<a href="mailto:${email}">${email}</a>`;

            // Update the 'Edit Details' link's onclick attribute in the dropdown menu.
            const editLink = tableRow.querySelector('.dropdown-menu a[onclick*="showEditUserModal"]');
            if (editLink) {
                 // Escape single quotes in data passed to the JS function call to prevent syntax errors.
                 const safeUsername = username.replace(/'/g, "\\'");
                 const safeEmail = email.replace(/'/g, "\\'");
                 const safeFirstName = (first_name || '').replace(/'/g, "\\'");
                 const safeLastName = (last_name || '').replace(/'/g, "\\'");
                 // Set the updated onclick attribute with the new, escaped data.
                 editLink.setAttribute('onclick', `showEditUserModal(${id}, '${safeUsername}', '${safeEmail}', '${safeFirstName}', '${safeLastName}')`);
            }
            animateChange(tableRow); // Apply visual feedback animation.
        }

        // --- Update Card ---
        const cardElement = cardContainer?.querySelector(`.user-card[data-user-id="${id}"]`);
        if (cardElement) {
            // Update username in the card header.
            const usernameHeader = cardElement.querySelector('.card-header strong');
            if (usernameHeader) usernameHeader.textContent = username;
            // Update name and email in the card body rows (find rows by label for robustness).
            const nameRow = Array.from(cardElement.querySelectorAll('.data-row')).find(row => row.querySelector('.data-label')?.textContent === 'Name');
            const emailRow = Array.from(cardElement.querySelectorAll('.data-row')).find(row => row.querySelector('.data-label')?.textContent === 'Email');
            const nameValue = nameRow?.querySelector('.data-value');
            const emailValue = emailRow?.querySelector('.data-value');
            if (nameValue) nameValue.textContent = fullName;
            // Update email value, preserving the mailto: link structure.
            if (emailValue) emailValue.innerHTML = `<a href="mailto:${email}">${email}</a>`;

            // Update the 'Edit' button's onclick attribute in the card actions footer.
            const editButton = cardElement.querySelector('.card-actions button[onclick*="showEditUserModal"]');
            if (editButton) {
                 // Escape single quotes for the onclick attribute string.
                 const safeUsername = username.replace(/'/g, "\\'");
                 const safeEmail = email.replace(/'/g, "\\'");
                 const safeFirstName = (first_name || '').replace(/'/g, "\\'");
                 const safeLastName = (last_name || '').replace(/'/g, "\\'");
                 // Set the updated onclick attribute.
                 editButton.setAttribute('onclick', `showEditUserModal(${id}, '${safeUsername}', '${safeEmail}', '${safeFirstName}', '${safeLastName}')`);
            }
            animateChange(cardElement); // Apply visual feedback animation.
        }
         // --- End of UI Updates ---

         // Re-apply filtering, as name/username/email changes might affect search results.
         filterUsers(searchInput.value.toLowerCase().trim(), currentFilter);
    }

    /**
     * Helper function: Formats and updates the HTML content of an element designated
     * to display contractor information (name and optionally trade). Handles cases where
     * info is present, absent, or not applicable for the user type.
     * @param {HTMLElement} element - The target HTML element (e.g., a <td> or a .data-value <div>).
     * @param {object} user - The user data object containing `user_type`, `contractor_name`, `contractor_trade`.
     */
    function updateContractorInfo(element, user) {
        if (!element) return; // Exit if the target element is invalid.

        // Check if contractor info should be displayed (user is Manager or Contractor AND has a name assigned).
        if ((user.user_type === 'contractor' || user.user_type === 'manager') && user.contractor_name) {
            // Format the trade part (e.g., "(Plumbing)") if trade exists.
            let tradeInfoHtml = user.contractor_trade ? `<span class="contractor-info">(${user.contractor_trade})</span>` : '';
            // Set the innerHTML. Adjust formatting slightly based on whether it's a table cell or card value div.
             if (element.tagName === 'TD') {
                 // For table cells, keep trade info generally inline with the name.
                 element.innerHTML = `${user.contractor_name} ${tradeInfoHtml}`;
            } else {
                 // For card value divs, ensure trade info appears on a new line using d-block.
                 element.innerHTML = `
                    ${user.contractor_name}
                    ${user.contractor_trade ? `<span class="contractor-info d-block">(${user.contractor_trade})</span>` : ''}
                 `;
            }
        } else {
            // If no contractor info should be displayed, show a muted hyphen.
            element.innerHTML = '<span class="text-muted">-</span>';
        }
    }

    /**
     * Filters the displayed user list (both table rows and cards) based on the current
     * search term and the selected filter type (status or user role).
     * Manages the visibility of rows/cards and the "No results" message in the card view.
     * @param {string} searchTerm - The lowercased, trimmed text from the search input field.
     * @param {string} filter - The currently active filter type ('all', 'active', 'inactive', or a user type like 'manager').
     */
    function filterUsers(searchTerm, filter) {
        let tableHasVisibleRows = false; // Flag to track if any rows remain visible in the table.
        let cardHasVisibleRows = false; // Flag for the card view.

        // --- Filter Table View ---
        if (tableBody) {
            // Iterate over each row (<tr>) in the table body.
            Array.from(tableBody.querySelectorAll('tr')).forEach(row => {
                // Check 1: Does the row's text content (lowercase) include the search term?
                const rowText = row.textContent.toLowerCase();
                const matchesSearch = rowText.includes(searchTerm);

                // Check 2: Does the row match the currently selected filter?
                const userStatus = row.querySelector('.user-status')?.classList.contains('active') ? 'active' : 'inactive';
                const userType = row.querySelector('.badge[data-type]')?.getAttribute('data-type');
                let matchesFilter = (filter === 'all') || // 'all' matches everything.
                                    (filter === 'active' && userStatus === 'active') || // Match active status.
                                    (filter === 'inactive' && userStatus === 'inactive') || // Match inactive status.
                                    (userTypes.includes(filter) && userType === filter); // Match specific user type.

                // Determine final visibility: row must match both search term AND filter.
                const isVisible = matchesSearch && matchesFilter;
                row.style.display = isVisible ? '' : 'none'; // Show row if visible, hide otherwise.
                if (isVisible) tableHasVisibleRows = true; // Set flag if at least one row is visible.
            });
            // TODO (Optional): Implement a "No results found" row for the table view.
            // This would involve adding a specific <tr> to the HTML (e.g., in <tfoot> or <tbody>)
            // and toggling its visibility here based on `tableHasVisibleRows`.
        }

        // --- Filter Card View ---
        if (cardContainer) {
             // Iterate over each user card (<div class="user-card">).
             Array.from(cardContainer.querySelectorAll('.user-card')).forEach(card => {
                // Check 1: Does the card's text content (lowercase) include the search term?
                const cardText = card.textContent.toLowerCase();
                const matchesSearch = cardText.includes(searchTerm);

                // Check 2: Does the card match the currently selected filter using its data attributes?
                const userStatus = card.getAttribute('data-user-status');
                const userType = card.getAttribute('data-user-type');
                let matchesFilter = (filter === 'all') ||
                                    (filter === 'active' && userStatus === 'active') ||
                                    (filter === 'inactive' && userStatus === 'inactive') ||
                                    (userTypes.includes(filter) && userType === filter);

                // Determine final visibility: card must match both search term AND filter.
                const isVisible = matchesSearch && matchesFilter;
                card.style.display = isVisible ? '' : 'none'; // Show card if visible, hide otherwise.
                 if (isVisible) cardHasVisibleRows = true; // Set flag if at least one card is visible.
            });
             // Show or hide the dedicated "No results" message card based on the flag.
            if(noResultsCard) noResultsCard.style.display = cardHasVisibleRows ? 'none' : 'block';
        }
    }


    // --- Utility Functions ---

    /**
     * Displays a Bootstrap alert message dynamically in the designated alert container (#alertContainer).
     * Allows specifying alert type (color/icon) and message content.
     * @param {'success' | 'danger' | 'warning' | 'info'} type - The type of alert, determining its appearance.
     * @param {string} message - The text message to be displayed within the alert.
     * @param {boolean} [autoDismiss=true] - If true, the alert will automatically close after a 5-second delay.
     */
    function showAlert(type, message, autoDismiss = true) {
        if (!alertContainer) {
             console.error("Alert container not found. Cannot display alert:", message);
             return; // Don't proceed if the container element doesn't exist.
        }
        // Map the alert type string to the corresponding Boxicons icon class name.
        const iconMap = { success: 'bx-check-circle', danger: 'bx-error-circle', warning: 'bx-error-alt', info: 'bx-info-circle' };
        const icon = iconMap[type] || 'bx-info-circle'; // Default to 'info' icon if type is unrecognized.

        // Create the new alert div element.
        const alertDiv = document.createElement('div');
        // Apply Bootstrap classes for styling, dismissal, and fade animation. Added margin bottom.
        alertDiv.className = `alert alert-${type} alert-dismissible fade show mb-2`;
        alertDiv.setAttribute('role', 'alert');
        // Set the inner HTML, including the icon, message, and a Bootstrap close button.
        // Using d-flex for better alignment of icon and text.
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                 <i class="bx ${icon} fs-4 me-2 flex-shrink-0"></i> <?php // Icon ?>
                 <div class="flex-grow-1">${message}</div> <?php // Message text ?>
                 <button type="button" class="btn-close ms-2" data-bs-dismiss="alert" aria-label="Close"></button> <?php // Close button ?>
            </div>
        `;

        // Prepend the new alert to the container, so the most recent alert appears at the top.
        alertContainer.prepend(alertDiv);

        // If autoDismiss is enabled, set a timeout to close the alert after a delay.
        if (autoDismiss) {
            setTimeout(() => {
                // Check if the alert element still exists in the DOM before attempting to close it
                // (it might have been manually closed by the user already).
                if (alertDiv.parentNode === alertContainer) {
                    // Get or create a Bootstrap Alert instance associated with the element.
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alertDiv);
                    bsAlert?.close(); // Use Bootstrap's API method to close the alert smoothly.
                }
            }, 5000); // 5000 milliseconds = 5 seconds delay.
        }
    }

    /**
     * Sets the visual loading state for a button, typically used during AJAX requests.
     * Disables the button and shows/hides an embedded spinner icon.
     * @param {HTMLButtonElement|null} button - The button element to modify. Can be null.
     * @param {boolean} isLoading - True to activate the loading state, false to restore the normal state.
     */
    function setButtonLoading(button, isLoading) {
        if (!button) return; // Exit safely if the button element is not valid.
        // Find the spinner element (e.g., <span class="spinner-border...">) within the button.
        const spinner = button.querySelector('.spinner-border');
        if (isLoading) {
            // Activate loading state:
            button.disabled = true; // Disable the button to prevent multiple clicks.
            spinner?.classList.remove('d-none'); // Show the spinner element (remove 'd-none' class).
        } else {
            // Restore normal state:
            button.disabled = false; // Re-enable the button.
            spinner?.classList.add('d-none'); // Hide the spinner element (add 'd-none' class).
        }
    }

    /**
     * Applies a brief visual animation (by adding a CSS class) to an HTML element
     * to provide feedback that it has been updated (e.g., after an AJAX change).
     * @param {HTMLElement} element - The HTML element to apply the animation to.
     */
    function animateChange(element) {
        if (!element) return; // Exit safely if the element is not valid.
        // Add the CSS class (e.g., 'changing') that triggers the defined animation.
        element.classList.add('changing');
        // Set a timeout to remove the animation class after the animation completes.
        // This allows the animation to be re-triggered if the element is changed again later.
        // The timeout duration should match the animation duration defined in the CSS (e.g., 1200ms = 1.2s).
        setTimeout(() => element.classList.remove('changing'), 1200);
    }

</script> <?php // End of inline JavaScript ?>

</body>
</html>