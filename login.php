<?php
/**
 * Login Page - Construction Defect Tracker
 *
 * @version 1.1
 * @author irlam (Original), Gemini (Modifications & Comments)
 * @last-modified 2025-04-12 11:25:00 UTC
 *
 * --- File Description ---
 * This script handles the user login process for the Construction Defect Tracker application.
 *
 * Key Functionality:
 * 1.  **Session Check:** If a user is already logged in (session variables `user_id` or `username` are set),
 *     it redirects them immediately to the main application dashboard (`dashboard.php`).
 * 2.  **Login Form Display:** Renders an HTML login form asking for username and password.
 *     - Includes visual elements like the application logo and styling using Bootstrap.
 *     - Provides demo login banners (currently for Manager and Contractor roles) with credentials and
 *       an auto-login button for demonstration purposes.
 * 3.  **Form Submission Handling (POST Request):**
 *     - Retrieves and trims the submitted username and password.
 *     - Performs basic validation to ensure both fields are provided.
 *     - Establishes a database connection using the `Database` class.
 *     - Queries the `users` table to find a user matching the provided username.
 *     - Uses `password_verify()` to securely check if the submitted password matches the hashed password
 *       stored in the database.
 *     - **On Successful Login:**
 *         - Sets essential user information (`user_id`, `username`, `full_name`, `role_id`) into the PHP session.
 *         - Updates the user's `last_login` timestamp in the database to the current UTC time using `UTC_TIMESTAMP()`.
 *         - Redirects the user to the main application dashboard (`dashboard.php`).
 *     - **On Failed Login:**
 *         - Stores an appropriate error message (e.g., "Invalid username or password.").
 *         - Logs the login error details to the configured error log file.
 *         - Re-displays the login form along with the error message.
 * 4.  **Error Handling:** Uses a try-catch block to handle exceptions during database operations or validation,
 *     displaying user-friendly error messages and logging technical details.
 * 5.  **Security:** Uses prepared statements with bound parameters (`bindParam`) to prevent SQL injection vulnerabilities.
 *     Relies on `password_verify()` for secure password comparison against hashed passwords (assumes passwords
 *     were hashed using `password_hash()` during registration/creation).
 *
 * --- Current User Context ---
 * Current Date and Time (UTC): 2025-04-12 11:17:54
 * Current User's Login: irlam (Note: This is context for the request, not the user logging in)
 */

// --- PHP Setup ---

// Error Reporting: Display all errors and log them during development.
// IMPORTANT: Set display_errors to 0 in production.
error_reporting(E_ALL);
ini_set('display_errors', 1); // Show errors on screen (for development)
ini_set('log_errors', 1); // Log errors to a file
ini_set('error_log', __DIR__ . '/logs/error.log'); // Path to error log file

// Session Management: Start session if not already active. Needed for login state.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Redirect if Already Logged In ---
// If the user's session already contains login information, redirect them to the dashboard.
if (isset($_SESSION['username'])) { // Checking 'username' is usually sufficient.
    header("Location: dashboard.php"); // Redirect to the main application page.
    exit(); // Stop further script execution.
}

// --- Initialization ---
$error = ''; // Variable to store login error messages for display.
$username = ''; // Variable to pre-fill the username field if login fails.

// Include the database configuration file.
require_once 'config/database.php'; // Contains the Database class definition.

// --- Login Form Processing (Handles POST requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // --- Input Retrieval and Sanitization ---
        // Get username from POST data, trim whitespace. Use null coalescing operator (??) as fallback.
        // Using isset() ternary for maximum compatibility as requested previously, though ?? is fine in PHP 8.1.
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        // Get password from POST data. No trimming needed usually.
        $password = isset($_POST['password']) ? $_POST['password'] : '';

        // --- Basic Input Validation ---
        if (empty($username) || empty($password)) {
            // Throw an exception if either field is empty.
            throw new Exception('Please enter both username and password.');
        }

        // --- Database Interaction ---
        // Initialize database connection.
        $database = new Database();
        $db = $database->getConnection(); // Get PDO connection object.

        // Prepare SQL query to fetch user details based on username.
        // Select necessary fields for session and password verification.
        // IMPORTANT: Ensure your 'users' table actually has 'full_name' and 'role_id' columns if you select them.
        // If not, adjust the query and session setting logic.
        $stmt = $db->prepare("
            SELECT id, username, password, full_name, role_id, user_type, status
            FROM users
            WHERE username = :username
        ");
        // Bind the username parameter to prevent SQL injection.
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        // Execute the prepared statement.
        $stmt->execute();

        // Fetch the user record as an associative array.
        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // --- User Found - Verify Password and Status ---

            // Check if the user account is active.
            if ($user['status'] !== 'active') {
                 throw new Exception('Your account is inactive. Please contact an administrator.');
            }

            // Verify the provided password against the hashed password from the database.
            if (password_verify($password, $user['password'])) {
                // --- Password Correct - Login Successful ---

                // Regenerate session ID to prevent session fixation attacks.
                session_regenerate_id(true);

                // Store essential user information in the session.
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                // Store full name if available, otherwise use username.
                $_SESSION['full_name'] = isset($user['full_name']) && !empty($user['full_name']) ? $user['full_name'] : $user['username'];
                // Store role_id if available.
                $_SESSION['role_id'] = isset($user['role_id']) ? $user['role_id'] : null; // Or a default role ID if applicable
                // Store user_type (important for authorization checks later).
                $_SESSION['user_type'] = isset($user['user_type']) ? $user['user_type'] : 'viewer'; // Default if missing

                // Update the user's last login timestamp in the database to current UTC time.
                $updateStmt = $db->prepare("UPDATE users SET last_login = UTC_TIMESTAMP() WHERE id = :id");
                $updateStmt->execute([':id' => $user['id']]);

                // --- Session Logging (Optional but Recommended) ---
                // You might want to log successful logins and session starts here,
                // potentially in a separate 'user_sessions' table for better tracking.
                // Example:
                // $sessionLogStmt = $db->prepare("INSERT INTO user_sessions (user_id, session_id, ip_address, user_agent, logged_in_at) VALUES (:user_id, :session_id, :ip, :ua, UTC_TIMESTAMP())");
                // $sessionLogStmt->execute([
                //     ':user_id' => $user['id'],
                //     ':session_id' => session_id(),
                //     ':ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown',
                //     ':ua' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'
                // ]);


                // Redirect the user to the main application dashboard.
                header("Location: dashboard.php");
                exit(); // Stop script execution after redirection.

            } else {
                // --- Password Incorrect ---
                // Throw a generic error message to avoid revealing which part (username or password) was wrong.
                throw new Exception('Invalid username or password.');
            }
        } else {
            // --- User Not Found ---
            // Throw the same generic error message.
            throw new Exception('Invalid username or password.');
        }
    } catch (Exception $e) {
        // --- Handle All Exceptions (Validation, DB errors, Login failures) ---
        $error = $e->getMessage(); // Store the error message for display on the form.
        // Log the detailed error message (including stack trace if needed) for debugging.
        // Avoid logging the raw password.
        $logMessage = "Login Error for username '{$username}': " . $e->getMessage();
        if ($e instanceof PDOException) { // Add more detail for DB errors
            $logMessage .= " | SQLSTATE: " . $e->getCode();
        }
        error_log($logMessage);
        // The script continues to render the HTML form below, displaying the $error message.
    }
} // End of POST request processing block.

// --- HTML Output Starts Here ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php // --- HTML Head --- ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Construction Defect Tracker</title>

    <?php // --- CSS Includes --- ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/app.css?v=20251102" rel="stylesheet">
</head>
<body data-bs-theme="dark" class="login-page">
    <div class="login-page__glow login-page__glow--one"></div>
    <div class="login-page__glow login-page__glow--two"></div>
    <?php // --- Main Login Container --- ?>
    <div class="login-container">

        <?php // Removed the hardcoded time and user display divs that were here. ?>

        <div class="card login-card">
            <?php // --- Card Header with Logo and Title --- ?>
            <div class="card-header text-center login-card__header">
                <span class="login-badge">Welcome back</span>
                <img src="https://mcgoff.defecttracker.uk/mcgoff.png" alt="Logo" class="login-logo">
                <h1 class="login-title">Construction Defect Tracker</h1>
                <p class="login-subtitle">Spot, track, and close defects with confidence.</p>
                <ul class="login-quick-stats">
                    <li>
                        <span class="label">Active projects</span>
                        <span class="value">24</span>
                    </li>
                    <li>
                        <span class="label">Issues resolved</span>
                        <span class="value">8.1k</span>
                    </li>
                    <li>
                        <span class="label">Avg. fix time</span>
                        <span class="value">3.2d</span>
                    </li>
                </ul>
            </div>

            <?php // --- Card Body with Demo Banners and Login Form --- ?>
            <div class="card-body p-4">

                <?php // --- Manager Demo Login Banner --- ?>
                <div class="demo-banner is-manager">
                    <div class="d-flex align-items-center">
                        <i class='bx bx-user-check fs-4 me-2'></i> <?php // Icon ?>
                        <h5 class="mb-0">Manager Demo Access</h5>
                    </div>
                    <p class="mb-2 mt-2 small">Click credentials to copy or use auto-login.</p>
                    <div class="demo-credentials">
                        <?php // Clickable boxes for username/password ?>
                        <div class="credential-box username-box" onclick="copyToClipboard('manager')" title="Click to copy username">
                            manager
                        </div>
                        <div class="credential-box password-box" onclick="copyToClipboard('manager1')" title="Click to copy password">
                            manager1
                        </div>
                    </div>
                    <?php // Auto-login button for Manager ?>
                    <button id="managerDemoLogin" class="btn btn-sm demo-button mt-3">
                        <i class='bx bx-log-in'></i>Auto-Login as Manager
                    </button>
                </div>

                <?php // --- Contractor Demo Login Banner --- ?>
                <div class="demo-banner is-contractor"> <?php // Different gradient for Contractor ?>
                    <div class="d-flex align-items-center">
                         <i class='bx bx-hard-hat fs-4 me-2'></i> <?php // Different Icon ?>
                        <h5 class="mb-0">Contractor Demo Access</h5>
                    </div>
                    <p class="mb-2 mt-2 small">Click credentials to copy or use auto-login.</p>
                    <div class="demo-credentials">
                        <?php // Clickable boxes for Contractor username/password ?>
                        <div class="credential-box username-box" onclick="copyToClipboard('contractor')" title="Click to copy username">
                            contractor
                        </div>
                        <div class="credential-box password-box" onclick="copyToClipboard('contractor1')" title="Click to copy password">
                            contractor1
                        </div>
                    </div>
                     <?php // Auto-login button for Contractor ?>
                    <button id="contractorDemoLogin" class="btn btn-sm demo-button mt-3">
                        <i class='bx bx-log-in'></i>Auto-Login as Contractor
                    </button>
                </div>


                <div class="login-divider"><span>Sign in</span></div>


                <?php // --- Display Login Error Message (if any) --- ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class='bx bx-error-circle me-1'></i> <?php // Icon ?>
                        <?php echo htmlspecialchars($error); // Display the error message safely ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php // --- Actual Login Form --- ?>
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); // Post to the same page ?>">
                    <?php // Username Input ?>
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bx-user'></i></span>
                            <input type="text" class="form-control" id="username" name="username"
                                   value="<?php echo htmlspecialchars($username); // Pre-fill username on failed login ?>" required autofocus>
                        </div>
                    </div>
                    <?php // Password Input ?>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bx-lock-alt'></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <?php // Optional: Add "Forgot Password?" link here ?>
                        <!-- <div class="text-end mt-1"><small><a href="/forgot-password.php">Forgot Password?</a></small></div> -->
                    </div>
                    <?php // Submit Button ?>
                    <button type="submit" class="btn btn-primary login-submit w-100">Login</button>
                </form>

                <div class="login-footer text-center text-muted mt-4">
                    <small>Need access? Contact your project administrator.</small>
                </div>

            </div> <?php // End card-body ?>
        </div> <?php // End card ?>
    </div> <?php // End login-container ?>

    <?php // --- JavaScript Includes --- ?>
    <!-- Bootstrap Bundle JS (includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

    <?php // --- Inline JavaScript for Demo Logins and Copy-to-Clipboard --- ?>
    <script>
        /**
         * Inline JavaScript for Login Page Functionality:
         * - Demo Account Auto-Login: Fills credentials and submits the form for demo accounts.
         * - Copy to Clipboard: Allows users to copy demo credentials by clicking on them.
         * - Simple Toast Notification: Provides visual feedback for the copy action.
         */

        // --- Event Listener for Manager Demo Auto-Login Button ---
        const managerDemoButton = document.getElementById('managerDemoLogin');
        if (managerDemoButton) {
            managerDemoButton.addEventListener('click', function() {
                document.getElementById('username').value = 'manager'; // Set username
                document.getElementById('password').value = 'manager1'; // Set password
                // Optional delay for visual effect before submitting.
                setTimeout(() => {
                    document.querySelector('form').submit(); // Submit the main login form.
                }, 200);
            });
        }

        // --- Event Listener for Contractor Demo Auto-Login Button ---
        const contractorDemoButton = document.getElementById('contractorDemoLogin');
        if (contractorDemoButton) {
             contractorDemoButton.addEventListener('click', function() {
                document.getElementById('username').value = 'contractor'; // Set username
                document.getElementById('password').value = 'contractor1'; // Set password
                // Optional delay.
                setTimeout(() => {
                    document.querySelector('form').submit(); // Submit the main login form.
                }, 200);
            });
        }


        /**
         * Copies the provided text to the user's clipboard.
         * Uses the modern Clipboard API with a fallback for older browsers.
         * Shows a toast notification on success or failure.
         * @param {string} text - The text to copy.
         */
        function copyToClipboard(text) {
            // Try using the modern Navigator Clipboard API first (requires HTTPS or localhost).
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast(`Copied: ${text}`); // Show success toast.
                }).catch(err => {
                    console.error('Clipboard API copy failed:', err);
                    showToast('Copy failed. Please try again.'); // Show error toast.
                });
            } else {
                // --- Fallback using document.execCommand('copy') ---
                // Create a temporary textarea element to hold the text.
                const textArea = document.createElement('textarea');
                textArea.value = text;
                // Make the textarea invisible and position it off-screen.
                textArea.style.position = 'fixed';
                textArea.style.left = '-9999px';
                textArea.style.top = '-9999px';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.focus(); // Focus the element.
                textArea.select(); // Select its content.

                try {
                    // Attempt to execute the 'copy' command.
                    const successful = document.execCommand('copy');
                    if (successful) {
                        showToast(`Copied: ${text}`); // Show success toast.
                    } else {
                        throw new Error('execCommand failed'); // Force catch block if command failed.
                    }
                } catch (err) {
                    console.error('Fallback copy failed:', err);
                    showToast('Copy failed. Please copy manually.'); // Show error toast.
                } finally {
                    // Always remove the temporary textarea element.
                    document.body.removeChild(textArea);
                }
            }
        }

        /**
         * Displays a simple, temporary toast notification at the bottom center of the screen.
         * @param {string} message - The message to display in the toast.
         */
        function showToast(message) {
            // Create the toast element.
            const toast = document.createElement('div');
            // Apply styles for positioning, appearance, and z-index.
            toast.style.position = 'fixed';
            toast.style.bottom = '20px';
            toast.style.left = '50%';
            toast.style.transform = 'translateX(-50%)';
            toast.style.backgroundColor = 'rgba(44, 62, 80, 0.9)'; // Dark background with opacity.
            toast.style.color = 'white';
            toast.style.padding = '10px 20px'; /* Increased padding */
            toast.style.borderRadius = '6px'; /* More rounded */
            toast.style.zIndex = '1060'; /* Ensure above most elements */
            toast.style.fontSize = '0.9rem';
            toast.style.opacity = '0'; /* Start invisible for fade-in */
            toast.style.transition = 'opacity 0.3s ease'; /* Smooth fade transition */
            toast.textContent = message; // Set the message text.

            // Append the toast to the body.
            document.body.appendChild(toast);

            // --- Fade In ---
            // Use a tiny timeout to allow the element to be added to the DOM before starting the transition.
            setTimeout(() => {
                 toast.style.opacity = '1';
            }, 10);


            // --- Fade Out and Remove ---
            // Set a timeout to start fading out the toast.
            setTimeout(() => {
                toast.style.opacity = '0'; // Start fade-out transition.
                // Set another timeout to remove the element from the DOM *after* the fade-out transition completes.
                setTimeout(() => {
                    // Check if the toast still exists before removing (might have been removed by other means).
                    if (toast.parentNode === document.body) {
                        document.body.removeChild(toast);
                    }
                }, 300); // Duration should match the transition duration (0.3s = 300ms).
            }, 2000); // Display duration before starting fade-out (2000ms = 2 seconds).
        }
    </script>
</body>
</html>