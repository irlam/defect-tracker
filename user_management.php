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
ini_set('display_errors', 0); // Keep diagnostics out of production responses.
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
                                'ßž:öÚ$z{-®éÜj×æÖW2à¢6öç7BgVÆÄæÖRÒG¶f—'7EöæÖRÇÂrwÒG¶Æ7EöæÖRÇÂrwÖçG&–Ò‚“° ¢òòÒÒÒWFFRF&ÆR&÷rÒÒÐ¢6öç7BF&ÆU&÷rÒF&ÆT&öG“òçVW'•6VÆV7F÷"†G%¶FF×W6W"Ö–CÒ"G¶–GÒ%Ö“°¢–b‡F&ÆU&÷r’°¢òòWFFR6VÆÂ6öçFVçBF—&V7FÇ’†77VÖ–ær7V6–f–26öÇVÖâ÷&FW#¢#¥W6W&æÖRÂ3¤æÖRÂC¤VÖ–Â’à¢6öç7BW6W&æÖT6VÆÂÒF&ÆU&÷ræ6VÆÇ5³Ó°¢6öç7BæÖT6VÆÂÒF&ÆU&÷ræ6VÆÇ5³%Ó°¢6öç7BVÖ–Ä6VÆÂÒF&ÆU&÷ræ6VÆÇ5³5Ó°¢–b‡W6W&æÖT6VÆÂ’W6W&æÖT6VÆÂçFW‡D6öçFVçBÒW6W&æÖS°¢–b†æÖT6VÆÂ’æÖT6VÆÂçFW‡D6öçFVçBÒgVÆÄæÖS°¢òòWFFRVÖ–Â6VÆÂÂ&W6W'f–ærF†RÖ–ÇFó¢Æ–æ²7G'V7GW&Rà¢–b†VÖ–Ä6VÆÂ’VÖ–Ä6VÆÂæ–ææW$…DÔÂÒÆ‡&VcÒ&Ö–ÇFó¢G¶VÖ–ÇÒ#âG¶VÖ–ÇÓÂöæ° ¢òòWFFRF†RtVF—BFWF–Ç2rÆ–æ²w2öæ6Æ–6²GG&–'WFR–âF†RG&÷F÷vâÖVçRà¢6öç7BVF—DÆ–æ²ÒF&ÆU&÷rçVW'•6VÆV7F÷"‚ræG&÷F÷vâÖÖVçR¶öæ6Æ–6²£Ò'6†÷tVF—EW6W$ÖöFÂ%Òr“°¢–b†VF—DÆ–æ²’°¢òòW66R6–ævÆRV÷FW2–âFF76VBFòF†R¥2gVæ7F–öâ6ÆÂFò&WfVçB7–çF‚W'&÷'2à¢6öç7B6fUW6W&æÖRÒW6W&æÖRç&WÆ6R‚òrörÂ%ÅÂr"“°¢6öç7B6fTVÖ–ÂÒVÖ–Âç&WÆ6R‚òrörÂ%ÅÂr"“°¢6öç7B6fTf—'7DæÖRÒ†f—'7EöæÖRÇÂrr’ç&WÆ6R‚òrörÂ%ÅÂr"“°¢6öç7B6fTÆ7DæÖRÒ†Æ7EöæÖRÇÂrr’ç&WÆ6R‚òrörÂ%ÅÂr"“°¢òò6WBF†RWFFVBöæ6Æ–6²GG&–'WFRv—F‚F†RæWrÂW66VBFFà¢VF—DÆ–æ²ç6WDGG&–'WFR‚vöæ6Æ–6²rÂ6†÷tVF—EW6W$ÖöFÂ‚G¶–GÒÂrG·6fUW6W&æÖWÒrÂrG·6fTVÖ–ÇÒrÂrG·6fTf—'7DæÖWÒrÂrG·6fTÆ7DæÖWÒr–“°¢Ð¢æ–ÖFT6†ævR‡F&ÆU&÷r“²òòÇ’f—7VÂfVVF&6²æ–ÖF–öâà¢Ð ¢òòÒÒÒWFFR6&BÒÒÐ¢6öç7B6&DVÆVÖVçBÒ6&D6öçF–æW#òçVW'•6VÆV7F÷"†çW6W"Ö6&E¶FF×W6W"Ö–CÒ"G¶–GÒ%Ö“°¢–b†6&DVÆVÖVçB’°¢òòWFFRW6W&æÖR–âF†R6&B†VFW"à¢6öç7BW6W&æÖT†VFW"Ò6&DVÆVÖVçBçVW'•6VÆV7F÷"‚ræ6&BÖ†VFW"7G&öærr“°¢–b‡W6W&æÖT†VFW"’W6W&æÖT†VFW"çFW‡D6öçFVçBÒW6W&æÖS°¢òòWFFRæÖRæBVÖ–Â–âF†R6&B&öG’&÷w2†f–æB&÷w2'’Æ&VÂf÷"&ö'W7FæW72’à¢6öç7BæÖU&÷rÒ'&’æg&öÒ†6&DVÆVÖVçBçVW'•6VÆV7F÷$ÆÂ‚ræFF×&÷rr’’æf–æB‡&÷rÓâ&÷rçVW'•6VÆV7F÷"‚ræFFÖÆ&VÂr“òçFW‡D6öçFVçBÓÓÒtæÖRr“°¢6öç7BVÖ–Å&÷rÒ'&’æg&öÒ†6&DVÆVÖVçBçVW'•6VÆV7F÷$ÆÂ‚ræFF×&÷rr’’æf–æB‡&÷rÓâ&÷rçVW'•6VÆV7F÷"‚ræFFÖÆ&VÂr“òçFW‡D6öçFVçBÓÓÒtVÖ–Âr“°¢6öç7BæÖUfÇVRÒæÖU&÷sòçVW'•6VÆV7F÷"‚ræFF×fÇVRr“°¢6öç7BVÖ–ÅfÇVRÒVÖ–Å&÷sòçVW'•6VÆV7F÷"‚ræFF×fÇVRr“°¢–b†æÖUfÇVR’æÖUfÇVRçFW‡D6öçFVçBÒgVÆÄæÖS°¢òòWFFRVÖ–ÂfÇVRÂ&W6W'f–ærF†RÖ–ÇFó¢Æ–æ²7G'V7GW&Rà¢–b†VÖ–ÅfÇVR’VÖ–ÅfÇVRæ–ææW$…DÔÂÒÆ‡&VcÒ&Ö–ÇFó¢G¶VÖ–ÇÒ#âG¶VÖ–ÇÓÂöæ° ¢òòWFFRF†RtVF—Br'WGFöâw2öæ6Æ–6²GG&–'WFR–âF†R6&B7F–öç2fö÷FW"à¢6öç7BVF—D'WGFöâÒ6&DVÆVÖVçBçVW'•6VÆV7F÷"‚ræ6&BÖ7F–öç2'WGFöå¶öæ6Æ–6²£Ò'6†÷tVF—EW6W$ÖöFÂ%Òr“°¢–b†VF—D'WGFöâ’°¢òòW66R6–ævÆRV÷FW2f÷"F†Röæ6Æ–6²GG&–'WFR7G&–ærà¢6öç7B6fUW6W&æÖRÒW6W&æÖRç&WÆ6R‚òrörÂ%ÅÂr"“°¢6öç7B6fTVÖ–ÂÒVÖ–Âç&WÆ6R‚òrörÂ%ÅÂr"“°¢6öç7B6fTf—'7DæÖRÒ†f—'7EöæÖRÇÂrr’ç&WÆ6R‚òrörÂ%ÅÂr"“°¢6öç7B6fTÆ7DæÖRÒ†Æ7EöæÖRÇÂrr’ç&WÆ6R‚òrörÂ%ÅÂr"“°¢òò6WBF†RWFFVBöæ6Æ–6²GG&–'WFRà¢VF—D'WGFöâç6WDGG&–'WFR‚vöæ6Æ–6²rÂ6†÷tVF—EW6W$ÖöFÂ‚G¶–GÒÂrG·6fUW6W&æÖWÒrÂrG·6fTVÖ–ÇÒrÂrG·6fTf—'7DæÖWÒrÂrG·6fTÆ7DæÖWÒr–“°¢Ð¢æ–ÖFT6†ævR†6&DVÆVÖVçB“²òòÇ’f—7VÂfVVF&6²æ–ÖF–öâà¢Ð¢òòÒÒÒVæBöbT’WFFW2ÒÒÐ ¢òò&RÖÇ’f–ÇFW&–ærÂ2æÖR÷W6W&æÖRöVÖ–Â6†ævW2Ö–v‡BffV7B6V&6‚&W7VÇG2à¢f–ÇFW%W6W'2‡6V&6„–çWBçfÇVRçFôÆ÷vW$66R‚’çG&–Ò‚’Â7W'&VçDf–ÇFW"“°¢Ð ¢ò¢ ¢¢†VÇW"gVæ7F–öã¢f÷&ÖG2æBWFFW2F†R…DÔÂ6öçFVçBöbâVÆVÖVçBFW6–væFV@¢¢FòF—7Æ’6öçG&7F÷"–æf÷&ÖF–öâ†æÖRæB÷F–öæÆÇ’G&FR’â†æFÆW266W2v†W&P¢¢–æfò—2&W6VçBÂ'6VçBÂ÷"æ÷BÆ–6&ÆRf÷"F†RW6W"G—Rà¢¢&Ò´…DÔÄVÆVÖVçGÒVÆVÖVçBÒF†RF&vWB…DÔÂVÆVÖVçB†RærâÂÇFCâ÷"æFF×fÇVRÆF—câ’à¢¢&Ò¶ö&¦V7GÒW6W"ÒF†RW6W"FFö&¦V7B6öçF–æ–ærW6W%÷G—VÂ6öçG&7F÷%öæÖVÂ6öçG&7F÷%÷G&FVà¢¢ð¢gVæ7F–öâWFFT6öçG&7F÷$–æfò†VÆVÖVçBÂW6W"’°¢–b‚VÆVÖVçB’&WGW&ã²òòW†—B–bF†RF&vWBVÆVÖVçB—2–çfÆ–Bà ¢òò6†V6²–b6öçG&7F÷"–æfò6†÷VÆB&RF—7Æ–VB‡W6W"—2ÖævW"÷"6öçG&7F÷"äB†2æÖR76–væVB’à¢–b‚‡W6W"çW6W%÷G—RÓÓÒv6öçG&7F÷"rÇÂW6W"çW6W%÷G—RÓÓÒvÖævW"r’bbW6W"æ6öçG&7F÷%öæÖR’°¢òòf÷&ÖBF†RG&FR'B†RærâÂ"…ÇVÖ&–ær’"’–bG&FRW†—7G2à¢ÆWBG&FT–æfô‡FÖÂÒW6W"æ6öçG&7F÷%÷G&FRòÇ7â6Æ73Ò&6öçG&7F÷"Ö–æfò#â‚G·W6W"æ6öçG&7F÷%÷G&FWÒ“Â÷7ãæ¢rs°¢òò6WBF†R–ææW$…DÔÂâF§W7Bf÷&ÖGF–ær6Æ–v‡FÇ’&6VBöâv†WF†W"—Bw2F&ÆR6VÆÂ÷"6&BfÇVRF—bà¢–b†VÆVÖVçBçFtæÖRÓÓÒuDBr’°¢òòf÷"F&ÆR6VÆÇ2Â¶VWG&FR–æfòvVæW&ÆÇ’–æÆ–æRv—F‚F†RæÖRà¢VÆVÖVçBæ–ææW$…DÔÂÒG·W6W"æ6öçG&7F÷%öæÖWÒG·G&FT–æfô‡FÖÇÖ°¢ÒVÇ6R°¢òòf÷"6&BfÇVRF—g2ÂVç7W&RG&FR–æfòV'2öâæWrÆ–æRW6–ærBÖ&Æö6²à¢VÆVÖVçBæ–ææW$…DÔÂÒ ¢G·W6W"æ6öçG&7F÷%öæÖWÐ¢G·W6W"æ6öçG&7F÷%÷G&FRòÇ7â6Æ73Ò&6öçG&7F÷"Ö–æfòBÖ&Æö6²#â‚G·W6W"æ6öçG&7F÷%÷G&FWÒ“Â÷7ãæ¢rwÐ¢°¢Ð¢ÒVÇ6R°¢òò–bæò6öçG&7F÷"–æfò6†÷VÆB&RF—7Æ–VBÂ6†÷r×WFVB‡—†Vâà¢VÆVÖVçBæ–ææW$…DÔÂÒsÇ7â6Æ73Ò'FW‡BÖ×WFVB#âÓÂ÷7ãâs°¢Ð¢Ð ¢ò¢ ¢¢f–ÇFW'2F†RF—7Æ–VBW6W"Æ—7B†&÷F‚F&ÆR&÷w2æB6&G2’&6VBöâF†R7W'&Vç@¢¢6V&6‚FW&ÒæBF†R6VÆV7FVBf–ÇFW"G—R‡7FGW2÷"W6W"&öÆR’à¢¢ÖævW2F†Rf—6–&–Æ—G’öb&÷w2ö6&G2æBF†R$æò&W7VÇG2"ÖW76vR–âF†R6&Bf–Wrà¢¢&Ò·7G&–æwÒ6V&6…FW&ÒÒF†RÆ÷vW&66VBÂG&–ÖÖVBFW‡Bg&öÒF†R6V&6‚–çWBf–VÆBà¢¢&Ò·7G&–æwÒf–ÇFW"ÒF†R7W'&VçFÇ’7F—fRf–ÇFW"G—R‚vÆÂrÂv7F—fRrÂv–æ7F—fRrÂ÷"W6W"G—RÆ–¶RvÖævW"r’à¢¢ð¢gVæ7F–öâf–ÇFW%W6W'2‡6V&6…FW&ÒÂf–ÇFW"’°¢ÆWBF&ÆT†5f—6–&ÆU&÷w2ÒfÇ6S²òòfÆrFòG&6²–bç’&÷w2&VÖ–âf—6–&ÆR–âF†RF&ÆRà¢ÆWB6&D†5f—6–&ÆU&÷w2ÒfÇ6S²òòfÆrf÷"F†R6&Bf–Wrà ¢òòÒÒÒf–ÇFW"F&ÆRf–WrÒÒÐ¢–b‡F&ÆT&öG’’°¢òò—FW&FR÷fW"V6‚&÷rƒÇG#â’–âF†RF&ÆR&öG’à¢'&’æg&öÒ‡F&ÆT&öG’çVW'•6VÆV7F÷$ÆÂ‚wG"r’’æf÷$V6‚‡&÷rÓâ°¢òò6†V6²¢FöW2F†R&÷rw2FW‡B6öçFVçB†Æ÷vW&66R’–æ6ÇVFRF†R6V&6‚FW&Óð¢6öç7B&÷uFW‡BÒ&÷rçFW‡D6öçFVçBçFôÆ÷vW$66R‚“°¢6öç7BÖF6†W56V&6‚Ò&÷uFW‡Bæ–æ6ÇVFW2‡6V&6…FW&Ò“° ¢òò6†V6²#¢FöW2F†R&÷rÖF6‚F†R7W'&VçFÇ’6VÆV7FVBf–ÇFW#ð¢6öç7BW6W%7FGW2Ò&÷rçVW'•6VÆV7F÷"‚rçW6W"×7FGW2r“òæ6Æ74Æ—7Bæ6öçF–ç2‚v7F—fRr’òv7F—fRr¢v–æ7F—fRs°¢6öç7BW6W%G—RÒ&÷rçVW'•6VÆV7F÷"‚ræ&FvU¶FF×G—UÒr“òævWDGG&–'WFR‚vFF×G—Rr“°¢ÆWBÖF6†W4f–ÇFW"Ò†f–ÇFW"ÓÓÒvÆÂr’ÇÂòòvÆÂrÖF6†W2WfW'—F†–ærà¢†f–ÇFW"ÓÓÒv7F—fRrbbW6W%7FGW2ÓÓÒv7F—fRr’ÇÂòòÖF6‚7F—fR7FGW2à¢†f–ÇFW"ÓÓÒv–æ7F—fRrbbW6W%7FGW2ÓÓÒv–æ7F—fRr’ÇÂòòÖF6‚–æ7F—fR7FGW2à¢‡W6W%G—W2æ–æ6ÇVFW2†f–ÇFW"’bbW6W%G—RÓÓÒf–ÇFW"“²òòÖF6‚7V6–f–2W6W"G—Rà ¢òòFWFW&Ö–æRf–æÂf—6–&–Æ—G“¢&÷r×W7BÖF6‚&÷F‚6V&6‚FW&ÒäBf–ÇFW"à¢6öç7B—5f—6–&ÆRÒÖF6†W56V&6‚bbÖF6†W4f–ÇFW#°¢&÷rç7G–ÆRæF—7Æ’Ò—5f—6–&ÆRòrr¢væöæRs²òò6†÷r&÷r–bf—6–&ÆRÂ†–FR÷F†W'v—6Rà¢–b†—5f—6–&ÆR’F&ÆT†5f—6–&ÆU&÷w2ÒG'VS²òò6WBfÆr–bBÆV7BöæR&÷r—2f—6–&ÆRà¢Ò“°¢òòDôDò„÷F–öæÂ“¢–×ÆVÖVçB$æò&W7VÇG2f÷VæB"&÷rf÷"F†RF&ÆRf–Wrà¢òòF†—2v÷VÆB–çföÇfRFF–ær7V6–f–2ÇG#âFòF†R…DÔÂ†RærâÂ–âÇFfö÷Câ÷"ÇF&öG“â¢òòæBFövvÆ–ær—G2f—6–&–Æ—G’†W&R&6VBöâF&ÆT†5f—6–&ÆU&÷w6à¢Ð ¢òòÒÒÒf–ÇFW"6&Bf–WrÒÒÐ¢–b†6&D6öçF–æW"’°¢òò—FW&FR÷fW"V6‚W6W"6&BƒÆF—b6Æ73Ò'W6W"Ö6&B#â’à¢'&’æg&öÒ†6&D6öçF–æW"çVW'•6VÆV7F÷$ÆÂ‚rçW6W"Ö6&Br’’æf÷$V6‚†6&BÓâ°¢òò6†V6²¢FöW2F†R6&Bw2FW‡B6öçFVçB†Æ÷vW&66R’–æ6ÇVFRF†R6V&6‚FW&Óð¢6öç7B6&EFW‡BÒ6&BçFW‡D6öçFVçBçFôÆ÷vW$66R‚“°¢6öç7BÖF6†W56V&6‚Ò6&EFW‡Bæ–æ6ÇVFW2‡6V&6…FW&Ò“° ¢òò6†V6²#¢FöW2F†R6&BÖF6‚F†R7W'&VçFÇ’6VÆV7FVBf–ÇFW"W6–ær—G2FFGG&–'WFW3ð¢6öç7BW6W%7FGW2Ò6&BævWDGG&–'WFR‚vFF×W6W"×7FGW2r“°¢6öç7BW6W%G—RÒ6&BævWDGG&–'WFR‚vFF×W6W"×G—Rr“°¢ÆWBÖF6†W4f–ÇFW"Ò†f–ÇFW"ÓÓÒvÆÂr’ÇÀ¢†f–ÇFW"ÓÓÒv7F—fRrbbW6W%7FGW2ÓÓÒv7F—fRr’ÇÀ¢†f–ÇFW"ÓÓÒv–æ7F—fRrbbW6W%7FGW2ÓÓÒv–æ7F—fRr’ÇÀ¢‡W6W%G—W2æ–æ6ÇVFW2†f–ÇFW"’bbW6W%G—RÓÓÒf–ÇFW"“° ¢òòFWFW&Ö–æRf–æÂf—6–&–Æ—G“¢6&B×W7BÖF6‚&÷F‚6V&6‚FW&ÒäBf–ÇFW"à¢6öç7B—5f—6–&ÆRÒÖF6†W56V&6‚bbÖF6†W4f–ÇFW#°¢6&Bç7G–ÆRæF—7Æ’Ò—5f—6–&ÆRòrr¢væöæRs²òò6†÷r6&B–bf—6–&ÆRÂ†–FR÷F†W'v—6Rà¢–b†—5f—6–&ÆR’6&D†5f—6–&ÆU&÷w2ÒG'VS²òò6WBfÆr–bBÆV7BöæR6&B—2f—6–&ÆRà¢Ò“°¢òò6†÷r÷"†–FRF†RFVF–6FVB$æò&W7VÇG2"ÖW76vR6&B&6VBöâF†RfÆrà¢–b†æõ&W7VÇG46&B’æõ&W7VÇG46&Bç7G–ÆRæF—7Æ’Ò6&D†5f—6–&ÆU&÷w2òvæöæRr¢v&Æö6²s°¢Ð¢Ð  ¢òòÒÒÒWF–Æ—G’gVæ7F–öç2ÒÒÐ ¢ò¢ ¢¢F—7Æ—2&ö÷G7G&ÆW'BÖW76vRG–æÖ–6ÆÇ’–âF†RFW6–væFVBÆW'B6öçF–æW"‚6ÆW'D6öçF–æW"’à¢¢ÆÆ÷w27V6–g––ærÆW'BG—R†6öÆ÷"ö–6öâ’æBÖW76vR6öçFVçBà¢¢&Ò²w7V66W72rÂvFævW"rÂwv&æ–ærrÂv–æfòwÒG—RÒF†RG—RöbÆW'BÂFWFW&Ö–æ–ær—G2V&æ6Rà¢¢&Ò·7G&–æwÒÖW76vRÒF†RFW‡BÖW76vRFò&RF—7Æ–VBv—F†–âF†RÆW'Bà¢¢&Ò¶&ööÆVçÒ¶WFôF—6Ö—73×G'VUÒÒ–bG'VRÂF†RÆW'Bv–ÆÂWFöÖF–6ÆÇ’6Æ÷6RgFW"R×6V6öæBFVÆ’à¢¢ð¢gVæ7F–öâ6†÷tÆW'B‡G—RÂÖW76vRÂWFôF—6Ö—72ÒG'VR’°¢–b‚ÆW'D6öçF–æW"’°¢6öç6öÆRæW'&÷"‚$ÆW'B6öçF–æW"æ÷Bf÷VæBâ6ææ÷BF—7Æ’ÆW'C¢"ÂÖW76vR“°¢&WGW&ã²òòFöâwB&ö6VVB–bF†R6öçF–æW"VÆVÖVçBFöW6âwBW†—7Bà¢Ð¢òòÖF†RÆW'BG—R7G&–ærFòF†R6÷'&W7öæF–ær&÷†–6öç2–6öâ6Æ72æÖRà¢6öç7B–6öäÖÒ²7V66W73¢v'‚Ö6†V6²Ö6—&6ÆRrÂFævW#¢v'‚ÖW'&÷"Ö6—&6ÆRrÂv&æ–æs¢v'‚ÖW'&÷"ÖÇBrÂ–æfó¢v'‚Ö–æfòÖ6—&6ÆRrÓ°¢6öç7B–6öâÒ–6öäÖ·G—UÒÇÂv'‚Ö–æfòÖ6—&6ÆRs²òòFVfVÇBFòv–æfòr–6öâ–bG—R—2Vç&V6övæ—¦VBà ¢òò7&VFRF†RæWrÆW'BF—bVÆVÖVçBà¢6öç7BÆW'DF—bÒFö7VÖVçBæ7&VFTVÆVÖVçB‚vF—br“°¢òòÇ’&ö÷G7G&6Æ76W2f÷"7G–Æ–ærÂF—6Ö—76ÂÂæBfFRæ–ÖF–öââFFVBÖ&v–â&÷GFöÒà¢ÆW'DF—bæ6Æ74æÖRÒÆW'BÆW'BÒG·G—WÒÆW'BÖF—6Ö—76–&ÆRfFR6†÷rÖ"Ó&°¢ÆW'DF—bç6WDGG&–'WFR‚w&öÆRrÂvÆW'Br“°¢òò6WBF†R–ææW"…DÔÂÂ–æ6ÇVF–ærF†R–6öâÂÖW76vRÂæB&ö÷G7G&6Æ÷6R'WGFöâà¢òòW6–ærBÖfÆW‚f÷"&WGFW"Æ–væÖVçBöb–6öâæBFW‡Bà¢ÆW'DF—bæ–ææW$…DÔÂÒ ¢ÆF—b6Æ73Ò&BÖfÆW‚Æ–vâÖ—FV×2Ö6VçFW"#à¢Æ’6Æ73Ò&'‚G¶–6öçÒg2ÓBÖRÓ"fÆW‚×6‡&–æ²Ó#ãÂö“âÃ÷‡òò–6öâóà¢ÆF—b6Æ73Ò&fÆW‚Öw&÷rÓ#âG¶ÖW76vWÓÂöF—câÃ÷‡òòÖW76vRFW‡Bóà¢Æ'WGFöâG—SÒ&'WGFöâ"6Æ73Ò&'FâÖ6Æ÷6R×2Ó""FFÖ'2ÖF—6Ö—73Ò&ÆW'B"&–ÖÆ&VÃÒ$6Æ÷6R#ãÂö'WGFöãâÃ÷‡òò6Æ÷6R'WGFöâóà¢ÂöF—cà¢° ¢òò&WVæBF†RæWrÆW'BFòF†R6öçF–æW"Â6òF†RÖ÷7B&V6VçBÆW'BV'2BF†RF÷à¢ÆW'D6öçF–æW"ç&WVæB†ÆW'DF—b“° ¢òò–bWFôF—6Ö—72—2Væ&ÆVBÂ6WBF–ÖV÷WBFò6Æ÷6RF†RÆW'BgFW"FVÆ’à¢–b†WFôF—6Ö—72’°¢6WEF–ÖV÷WB‚‚’Óâ°¢òò6†V6²–bF†RÆW'BVÆVÖVçB7F–ÆÂW†—7G2–âF†RDôÒ&Vf÷&RGFV×F–ærFò6Æ÷6R—@¢òò†—BÖ–v‡B†fR&VVâÖçVÆÇ’6Æ÷6VB'’F†RW6W"Ç&VG’’à¢–b†ÆW'DF—bç&VçDæöFRÓÓÒÆW'D6öçF–æW"’°¢òòvWB÷"7&VFR&ö÷G7G&ÆW'B–ç7Fæ6R76ö6–FVBv—F‚F†RVÆVÖVçBà¢6öç7B'4ÆW'BÒ&ö÷G7G&äÆW'BævWD÷$7&VFT–ç7Fæ6R†ÆW'DF—b“°¢'4ÆW'Còæ6Æ÷6R‚“²òòW6R&ö÷G7G&w2’ÖWF†öBFò6Æ÷6RF†RÆW'B6Öö÷F†Ç’à¢Ð¢ÒÂS“²òòSÖ–ÆÆ—6V6öæG2ÒR6V6öæG2FVÆ’à¢Ð¢Ð ¢ò¢ ¢¢6WG2F†Rf—7VÂÆöF–ær7FFRf÷"'WGFöâÂG—–6ÆÇ’W6VBGW&–ær¤‚&WVW7G2à¢¢F—6&ÆW2F†R'WGFöâæB6†÷w2ö†–FW2âVÖ&VFFVB7–ææW"–6öâà¢¢&Ò´…DÔÄ'WGFöäVÆVÖVçGÆçVÆÇÒ'WGFöâÒF†R'WGFöâVÆVÖVçBFòÖöF–g’â6â&RçVÆÂà¢¢&Ò¶&ööÆVçÒ—4ÆöF–ærÒG'VRFò7F—fFRF†RÆöF–ær7FFRÂfÇ6RFò&W7F÷&RF†Ræ÷&ÖÂ7FFRà¢¢ð¢gVæ7F–öâ6WD'WGFöäÆöF–ær†'WGFöâÂ—4ÆöF–ær’°¢–b‚'WGFöâ’&WGW&ã²òòW†—B6fVÇ’–bF†R'WGFöâVÆVÖVçB—2æ÷BfÆ–Bà¢òòf–æBF†R7–ææW"VÆVÖVçB†RærâÂÇ7â6Æ73Ò'7–ææW"Ö&÷&FW"âââ#â’v—F†–âF†R'WGFöâà¢6öç7B7–ææW"Ò'WGFöâçVW'•6VÆV7F÷"‚rç7–ææW"Ö&÷&FW"r“°¢–b†—4ÆöF–ær’°¢òò7F—fFRÆöF–ær7FFS ¢'WGFöâæF—6&ÆVBÒG'VS²òòF—6&ÆRF†R'WGFöâFò&WfVçB×VÇF—ÆR6Æ–6·2à¢7–ææW#òæ6Æ74Æ—7Bç&VÖ÷fR‚vBÖæöæRr“²òò6†÷rF†R7–ææW"VÆVÖVçB‡&VÖ÷fRvBÖæöæRr6Æ72’à¢ÒVÇ6R°¢òò&W7F÷&Ræ÷&ÖÂ7FFS ¢'WGFöâæF—6&ÆVBÒfÇ6S²òò&RÖVæ&ÆRF†R'WGFöâà¢7–ææW#òæ6Æ74Æ—7BæFB‚vBÖæöæRr“²òò†–FRF†R7–ææW"VÆVÖVçB†FBvBÖæöæRr6Æ72’à¢Ð¢Ð ¢ò¢ ¢¢Æ–W2'&–Vbf—7VÂæ–ÖF–öâ†'’FF–ær5526Æ72’Fòâ…DÔÂVÆVÖVç@¢¢Fò&÷f–FRfVVF&6²F†B—B†2&VVâWFFVB†RærâÂgFW"â¤‚6†ævR’à¢¢&Ò´…DÔÄVÆVÖVçGÒVÆVÖVçBÒF†R…DÔÂVÆVÖVçBFòÇ’F†Ræ–ÖF–öâFòà¢¢ð¢gVæ7F–öâæ–ÖFT6†ævR†VÆVÖVçB’°¢–b‚VÆVÖVçB’&WGW&ã²òòW†—B6fVÇ’–bF†RVÆVÖVçB—2æ÷BfÆ–Bà¢òòFBF†R5526Æ72†RærâÂv6†æv–ærr’F†BG&–vvW'2F†RFVf–æVBæ–ÖF–öâà¢VÆVÖVçBæ6Æ74Æ—7BæFB‚v6†æv–ærr“°¢òò6WBF–ÖV÷WBFò&VÖ÷fRF†Ræ–ÖF–öâ6Æ72gFW"F†Ræ–ÖF–öâ6ö×ÆWFW2à¢òòF†—2ÆÆ÷w2F†Ræ–ÖF–öâFò&R&R×G&–vvW&VB–bF†RVÆVÖVçB—26†ævVBv–âÆFW"à¢òòF†RF–ÖV÷WBGW&F–öâ6†÷VÆBÖF6‚F†Ræ–ÖF–öâGW&F–öâFVf–æVB–âF†R552†RærâÂ#×2Òã'2’à¢6WEF–ÖV÷WB‚‚’ÓâVÆVÖVçBæ6Æ74Æ—7Bç&VÖ÷fR‚v6†æv–ærr’Â#“°¢Ð £Â÷67&—CâÃ÷‡òòVæBöb–æÆ–æR¦f67&—Bóà £Âö&öG“à£Âö‡FÖÃà