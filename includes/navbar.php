<?php
/**
 * Navbar Class (navbar.php)
 *
 * Purpose: Generates the main navigation bar for the McGoff Defect Tracker application.
 *
 * Functionality:
 * - Establishes context based on the logged-in user's ID and username (provided during instantiation).
 * - Fetches the user's type (e.g., 'admin', 'manager', 'contractor', 'client', 'viewer')
 *   from the 'user_type' column in the 'users' database table using the provided user ID.
 * - Determines the correct set of navigation links and dropdown menus based on the user's type.
 * - Fetches and displays a logo for 'contractor' user types (using 'contractor_id' from 'users' table
 *   and querying the 'contractors' table) or a default admin logo for 'admin' user types.
 * - Renders the navigation bar using Bootstrap 5 HTML structure.
 * - Includes a dynamic clock displaying the current date and time in UK format (DD-MM-YYYY HH:MM:SS),
 *   using the 'Europe/London' timezone, updated every second via client-side JavaScript.
 * - Displays the username of the logged-in user ('irlam').
 * - Handles potential database errors during user type/logo fetching gracefully.
 * - Provides debug information (user type) as an HTML comment for troubleshooting.
 *
 * Context at Last Update:
 * - UTC Timestamp (YYYY-MM-DD HH:MM:SS): 2025-04-12 09:49:22
 * - User Login: irlam
 */

// Ensure PDO class is available. If your DB connection setup is in another file, require it here.
// require_once('path/to/your/db_connection.php'); // Example: Adjust path as necessary

class Navbar {
    /**
     * @var PDO Database connection object. Must be passed in the constructor.
     */
    private $db;

    /**
     * @var int The ID of the currently logged-in user.
     */
    private $userId;

    /**
     * @var string The user type string (e.g., 'admin', 'manager') fetched from the 'user_type' column. Initialized to 'viewer'.
     *             (Using the property name 'userRole' internally for consistency with previous versions, but it holds the user *type*).
     */
    private $userRole = 'viewer'; // Default user type if DB lookup fails or returns null/empty.

    /**
     * @var string The path to the user's logo image (e.g., '/uploads/logos/logo.png'), or an empty string if no logo applies.
     */
    private $userLogo = ''; // Default to no logo.

    /**
     * @var string The username of the currently logged-in user (e.g., 'irlam'). Escaped for safe HTML output.
     */
    private $username;

    /**
     * Navbar Constructor.
     *
     * Initializes the navigation bar component. Stores the database connection,
     * user ID, and username provided. It immediately calls setUserTypeAndLogo()
     * to determine the user's type and appropriate logo based on the user ID.
     *
     * @param PDO $db An active PDO database connection instance.
     * @param int $userId The unique identifier (ID) of the logged-in user.
     * @param string $username The display username of the logged-in user (e.g., 'irlam').
     */
    public function __construct($db, $userId, $username) {
        // Assign the provided database connection, user ID, and username to the object's properties.
        $this->db = $db;
        $this->userId = $userId;
        // Escape the username immediately for safe use in HTML later. Prevents XSS.
        $this->username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
        // Fetch the user's type (from user_type column) and determine their logo path right away upon object creation.
        $this->setUserTypeAndLogo(); // Renamed method call for clarity
    }

    /**
     * Fetches User Type and Determines Logo Path from Database.
     *
     * Queries the 'users' table for the given user ID to get their 'user_type' and 'contractor_id'.
     * Based on the user_type:
     * - If 'contractor' and 'contractor_id' is set, queries the 'contractors' table for the 'logo' filename.
     * - If 'admin', assigns a predefined path for the admin icon.
     * - Otherwise, no specific logo is assigned.
     * Updates the $this->userRole (holding user type) and $this->userLogo properties. Includes error handling for database operations.
     * If the database lookup fails or the user_type is empty, $this->userRole remains 'viewer' (its initialized value).
     */
    private function setUserTypeAndLogo() { // Renamed method definition
        try {
            // Prepare the SQL statement to select user_type and contractor_id for the user.
            // Using LIMIT 1 is good practice for lookups by unique ID.
            // *** MODIFICATION: Selecting 'user_type' instead of 'role' ***
            $query = "SELECT user_type, contractor_id FROM users WHERE id = :user_id LIMIT 1";
            $stmt = $this->db->prepare($query);
            // Bind the user ID parameter as an integer to prevent SQL injection.
            $stmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            // Execute the prepared statement.
            $stmt->execute();
            // Fetch the result row as an associative array. Returns false if no matching user is found.
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if a result was returned and if the 'user_type' column is not empty.
            // *** MODIFICATION: Checking 'user_type' instead of 'role' ***
            if ($result && !empty($result['user_type'])) {
                // Update the userRole property with the value from the database's user_type column.
                // *** MODIFICATION: Assigning from 'user_type' ***
                $this->userRole = $result['user_type']; // Still using $this->userRole property name internally

                // Special handling for 'contractor' user type to find their logo.
                // This logic relies on the user_type being 'contractor' and having a contractor_id.
                if ($this->userRole === 'contractor' && !empty($result['contractor_id'])) {
                    // Prepare a query to get the logo filename from the 'contractors' table.
                    $contractorQuery = "SELECT logo FROM contractors WHERE id = :contractor_id LIMIT 1";
                    $contractorStmt = $this->db->prepare($contractorQuery);
                    // Bind the contractor ID found in the user's record.
                    $contractorStmt->bindParam(':contractor_id', $result['contractor_id'], PDO::PARAM_INT);
                    $contractorStmt->execute();
                    $contractor = $contractorStmt->fetch(PDO::FETCH_ASSOC);

                    // If the contractor exists and has a logo filename specified...
                    if ($contractor && !empty($contractor['logo'])) {
                        $this->userLogo = $this->buildLogoPath($contractor['logo']);
                    }
                }
                // Special handling for 'admin' user type to assign a default icon.
                elseif ($this->userRole === 'admin') {
                    $this->userLogo = '/uploads/logos/admin-icon.png'; // Path to the default admin icon.
                }
                // For all other user types ('manager', 'client', 'viewer', etc.), $this->userLogo remains empty (as initialized).
            }
            // If $result is false (no user found) or 'user_type' is empty, $this->userRole remains 'viewer' (from initialization).

        } catch (PDOException $e) {
            // Log database connection or query errors securely. Do not expose details to the end-user.
            error_log("Navbar PDOException in setUserTypeAndLogo for user ID {$this->userId}: " . $e->getMessage());
            // Properties $userRole and $userLogo retain their initialized default values ('viewer', '').
        } catch (Exception $e) {
            // Catch any other unexpected errors during the process.
             error_log("Navbar General Exception in setUserTypeAndLogo for user ID {$this->userId}: " . $e->getMessage());
             // Properties retain their initialized default values.
        }
    }

    private function buildLogoPath($path)
    {
        if (empty($path)) {
            return '';
        }

        $trimmed = trim($path);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $trimmed)) {
            return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
        }

        $trimmed = ltrim($trimmed, '/');

        if (stripos($trimmed, 'uploads/logos/') === 0) {
            return htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
        }

        return 'uploads/logos/' . htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Renders the Full Navbar HTML Output.
     *
     * Constructs the complete HTML for the navigation bar using Bootstrap 5 classes.
     * It dynamically generates the menu items based on the user's type (fetched from user_type column) via getNavbarItems().
     * The output includes the brand logo/name, a responsive hamburger menu, the main navigation
     * links and dropdowns, the user's logo (if applicable), a live-updating clock showing
     * current UK time (via JavaScript), and the user's login name ('irlam').
     */
    public function render() {
        echo "<!-- DEBUG: Navbar User Type = '" . htmlspecialchars($this->userRole ?? 'NULL', ENT_QUOTES, 'UTF-8') . "' -->";

        if (!defined('APP_THEME_LOADED')) {
            echo '<link rel="stylesheet" href="/css/app.css">';
            define('APP_THEME_LOADED', true);
        }

        $navbarItems = $this->getNavbarItems();
        $unreadCount = 0;
        $recentNotifications = [];
        $recentNotificationsError = false;

        try {
            $unreadQuery = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0";
            $unreadStmt = $this->db->prepare($unreadQuery);
            $unreadStmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $unreadStmt->execute();
            $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
            $unreadCount = (int)($unreadResult['unread_count'] ?? 0);
        } catch (Exception $e) {
            error_log("Error fetching notification count: " . $e->getMessage());
            $unreadCount = 0;
        }

        try {
            $recentQuery = "SELECT id, type, message, created_at, is_read FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
            $recentStmt = $this->db->prepare($recentQuery);
            $recentStmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $recentStmt->execute();
            $recentNotifications = $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
            error_log("Error fetching recent notifications: " . $e->getMessage());
            $recentNotificationsError = true;
        }

        ob_start();
        ?>
        <nav class="app-navbar navbar navbar-expand-lg fixed-top" data-bs-theme="dark">
            <div class="container-xxl">
                <div class="d-flex align-items-center w-100 gap-3">
                    <a class="navbar-brand" href="/dashboard.php">
                        <span class="app-navbar__brand-icon"><i class="fas fa-layer-group"></i></span>
                        <span>McGoff Defect Tracker</span>
                    </a>
                    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                        <span class="navbar-toggler-icon"></span>
                    </button>
                </div>
                <div class="collapse navbar-collapse app-navbar__collapse mt-3 mt-lg-0" id="navbarNav">
                    <ul class="navbar-nav me-lg-auto mb-3 mb-lg-0 gap-lg-1">
                        <?php foreach ($navbarItems as $item): ?>
                            <?php $isDropdown = isset($item['dropdown']) && is_array($item['dropdown']); ?>
                            <li class="nav-item<?php echo $isDropdown ? ' dropdown' : ''; ?>">
                                <?php if ($isDropdown): ?>
                                    <a class="nav-link dropdown-toggle" href="#" id="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-dark shadow" aria-labelledby="<?php echo htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php foreach ($item['dropdown'] as $dropdownItem): ?>
                                            <?php if (($dropdownItem['label'] ?? '') === '---divider---'): ?>
                                                <div class="dropdown-divider"></div>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <?php if (($dropdownItem['type'] ?? '') === 'header'): ?>
                                                <h6 class="dropdown-header text-uppercase text-muted small"><?php echo htmlspecialchars($dropdownItem['label'], ENT_QUOTES, 'UTF-8'); ?></h6>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <a class="dropdown-item" href="<?php echo htmlspecialchars($dropdownItem['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dropdownItem['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <a class="nav-link" href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?></a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <div class="app-navbar__meta d-flex flex-column flex-lg-row align-items-lg-center gap-3 ms-lg-4 w-100 w-lg-auto">
                        <div class="d-flex align-items-center gap-2 app-navbar__identity order-lg-1">
                            <span class="app-navbar__avatar">
                                <?php if (!empty($this->userLogo)): ?>
                                    <img src="<?php echo $this->userLogo; ?>" alt="User logo">
                                <?php else: ?>
                                    <i class="fas fa-user text-primary"></i>
                                <?php endif; ?>
                            </span>
                            <div>
                                <div class="text-uppercase small fw-semibold">Signed in</div>
                                <div><?php echo $this->username; ?></div>
                            </div>
                        </div>
                        <div class="dropdown order-lg-2">
                            <button class="btn btn-link text-decoration-none position-relative p-0" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-bell fa-lg text-primary"></i>
                                <?php if ($unreadCount > 0): ?>
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">
                                <li><h6 class="dropdown-header">Notifications</h6></li>
                                <?php if ($recentNotificationsError): ?>
                                    <li><span class="dropdown-item-text text-muted">Unable to load notifications</span></li>
                                <?php else: ?>
                                    <?php if (empty($recentNotifications)): ?>
                                        <li><span class="dropdown-item-text text-muted">No notifications yet</span></li>
                                    <?php else: ?>
                                        <?php foreach ($recentNotifications as $notification): ?>
                                            <?php
                                                $iconClass = 'fas fa-info-circle text-primary';
                                                switch ($notification['type']) {
                                                    case 'defect_assigned':
                                                        $iconClass = 'fas fa-user-plus text-success';
                                                        break;
                                                    case 'defect_created':
                                                        $iconClass = 'fas fa-plus-circle text-info';
                                                        break;
                                                    case 'defect_accepted':
                                                        $iconClass = 'fas fa-check-circle text-success';
                                                        break;
                                                    case 'defect_rejected':
                                                        $iconClass = 'fas fa-times-circle text-danger';
                                                        break;
                                                    case 'defect_reopened':
                                                        $iconClass = 'fas fa-undo text-warning';
                                                        break;
                                                    case 'comment_added':
                                                        $iconClass = 'fas fa-comment text-primary';
                                                        break;
                                                }
                                            ?>
                                            <li>
                                                <a class="dropdown-item notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" href="#" data-notification-id="<?php echo $notification['id']; ?>">
                                                    <div class="d-flex align-items-start gap-2">
                                                        <i class="<?php echo $iconClass; ?> mt-1"></i>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold small"><?php echo htmlspecialchars($notification['type']); ?></div>
                                                            <div class="text-truncate small text-muted" style="max-width: 250px;">
                                                                <?php echo htmlspecialchars($notification['message']); ?>
                                                            </div>
                                                            <div class="small text-muted"><?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?></div>
                                                        </div>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-primary ms-2">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-center" href="/notifications.php"><i class="fas fa-list me-1"></i>View All Notifications</a></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        <div class="app-navbar__clock text-lg-end order-lg-3">
                            <span id="ukTime">Loading UK time...</span>
                            <small>Europe/London</small>
                        </div>
                    </div>
                </div>
            </div>
        </nav>
        <?php
        echo ob_get_clean();

        echo '<script>
            /**
             * Updates the content of the HTML element with ID "ukTime"
             * to display the current date and time in the UK (Europe/London timezone).
             * Formats the time as DD-MM-YYYY HH:MM:SS. This function is called every second.
             */
            function updateUKTime() {
                try {
                    // Create a new Date object to get the current instant in time.
                    const now = new Date();

                    // Define formatting options for the Date.toLocaleString() method.
                    const options = {
                        timeZone: "Europe/London", // CRITICAL: Ensures the time displayed is for the UK timezone.
                        year: "numeric",    // Format year as four digits (e.g., "2025").
                        month: "2-digit",   // Format month as two digits (e.g., "04").
                        day: "2-digit",     // Format day as two digits (e.g., "12").
                        hour: "2-digit",    // Format hour as two digits (e.g., "09" or "14").
                        minute: "2-digit",  // Format minute as two digits (e.g., "49").
                        second: "2-digit",  // Format second as two digits (e.g., "22").
                        hour12: false       // Use 24-hour format (00-23) instead of 12-hour AM/PM.
                    };

                    // Format the date and time string using the UK English locale ("en-GB") and the defined options.
                    // The "en-GB" locale typically gives DD/MM/YYYY format by default.
                    const ukTimeString = now.toLocaleString("en-GB", options)
                                           .replace(",", "")        // Remove the comma sometimes inserted between date and time.
                                           .replace(/\//g, "-");    // Replace the slashes (from DD/MM/YYYY) with dashes (DD-MM-YYYY).

                    // Find the HTML element (the span) where the time should be displayed, using its ID.
                    const timeElement = document.getElementById("ukTime");

                    // Check if the element actually exists in the HTML document (it should).
                    if (timeElement) {
                        // Update the text content of the span with the newly formatted UK time string.
                        timeElement.textContent = ukTimeString;
                    } else {
                        
                        console.error("Error: HTML element with ID \'ukTime\' was not found in the DOM.");
                    }
                } catch (error) {
                    // Catch any errors that might occur during date formatting or DOM manipulation.
                    console.error("Error in updateUKTime function:", error);
                    // Optionally, try to display an error message directly in the time element if it exists.
                    const timeElement = document.getElementById("ukTime");
                    if (timeElement) {
                        timeElement.textContent = "Time unavailable";
                    }
                }
            } // End of updateUKTime function definition.

            
            
            const timeIntervalId = setInterval(updateUKTime, 1000);

            // Call updateUKTime immediately once when the script initially loads.
            // This ensures the time is displayed right away, without waiting for the first second to pass.
            updateUKTime();

            function updateNavbarOffset() {
                const nav = document.querySelector(".app-navbar");
                if (!nav) {
                    return;
                }
                document.body.classList.add("has-app-navbar");
                document.documentElement.style.setProperty("--app-navbar-height", nav.offsetHeight + "px");
            }

            document.addEventListener("DOMContentLoaded", updateNavbarOffset);
            window.addEventListener("resize", updateNavbarOffset);

        </script>';

        echo '<script>
            // Handle notification dropdown clicks
            document.addEventListener("DOMContentLoaded", function() {
                // Mark notification as read when clicked in dropdown
                document.querySelectorAll(".notification-item").forEach(item => {
                    item.addEventListener("click", function(e) {
                        e.preventDefault();
                        const notificationId = this.dataset.notificationId;
                        const notificationItem = this;

                        // Mark as read via AJAX
                        fetch("notifications.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                            },
                            body: "action=mark_read&notification_id=" + notificationId
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                notificationItem.classList.remove("unread");
                                const badge = notificationItem.querySelector(".badge");
                                if (badge) badge.remove();

                                // Update notification badge count
                                updateNotificationBadge();
                            }
                        })
                        .catch(error => console.error("Error:", error));
                    });
                });
            });

            function updateNotificationBadge() {
                const badge = document.querySelector(".notification-badge");
                if (badge) {
                    const currentCount = parseInt(badge.textContent) || 0;
                    if (currentCount > 1) {
                        badge.textContent = currentCount - 1;
                    } else {
                        badge.style.display = "none";
                    }
                }
            }
        </script>';
    } // End of render() method

    /**
     * Gets User-Type-Specific Navigation Menu Items Array.
     *
     * Determines the appropriate navigation links and dropdowns based on the user's type
     * (stored internally in $this->userRole, but fetched from 'user_type' column).
     * Returns an array defining this structure. Includes a default set of items for unrecognized types or errors.
     *
     * **ASSUMPTION:** The case statements ('admin', 'manager', 'contractor', 'viewer', 'client')
     * MUST match the exact string values stored in the 'user_type' column of your 'users' table.
     * Adjust these case values if your database uses different strings for user types.
     *
     * @return array An array where each element defines a navigation item.
     *               Example link: ['label' => 'Dashboard', 'url' => '/dashboard.php']
     *               Example dropdown: ['label' => 'Defects', 'id' => 'defectsDropdown', 'dropdown' => [link items...]]
     */
    private function getNavbarItems() {
        // Initialize an empty array to hold the navigation structure for the current user.
        $items = [];

        // Use a switch statement to define the menu items based on the value of $this->userRole (which holds the user type).
        switch ($this->userRole) {
            case 'admin':
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
                    ['label' => 'Defect Ops', 'id' => 'defectsDropdownAdmin', 'dropdown' => [
                        ['type' => 'header', 'label' => 'Defects'],
                        ['label' => 'Defect Control Room', 'url' => '/defects.php'],
                        ['label' => 'Create Defect', 'url' => '/create_defect.php'],
                        ['label' => 'Assign Defects', 'url' => '/assign_to_user.php'],
                        ['label' => 'Completion Evidence', 'url' => '/upload_completed_images.php'],
                        ['label' => 'Legacy Register', 'url' => '/all_defects.php'],
                        ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
                        ['label' => '---divider---'],
                        ['type' => 'header', 'label' => 'Quick Actions'],
                        ['label' => 'View Defect', 'url' => '/view_defect.php'],
                        ['label' => 'Upload Floor Plan', 'url' => '/upload_floor_plan.php'],
                    ]],
                    ['label' => 'Projects', 'id' => 'projectsDropdownAdmin', 'dropdown' => [
                        ['label' => 'Projects Directory', 'url' => '/projects.php'],
                        ['label' => 'Floor Plan Library', 'url' => '/floor_plans.php'],
                        ['label' => 'Floorplan Selector', 'url' => '/floorplan_selector.php'],
                        ['label' => 'Delete Floor Plan', 'url' => '/delete_floor_plan.php'],
                    ]],
                    ['label' => 'Directory', 'id' => 'directoryDropdownAdmin', 'dropdown' => [
                        ['label' => 'User Management', 'url' => '/user_management.php'],
                        ['label' => 'Add User', 'url' => '/add_user.php'],
                        ['label' => 'Role Management', 'url' => '/role_management.php'],
                        ['label' => 'Contractor Directory', 'url' => '/contractors.php'],
                        ['label' => 'Add Contractor', 'url' => '/add_contractor.php'],
                        ['label' => 'Contractor Analytics', 'url' => '/contractor_stats.php'],
                        ['label' => 'View Contractor', 'url' => '/view_contractor.php'],
                    ]],
                    ['label' => 'Assets', 'id' => 'assetsDropdownAdmin', 'dropdown' => [
                        ['label' => 'Brand Assets', 'url' => '/add_logo.php'],
                        ['label' => 'Upload Floor Plan', 'url' => '/upload_floor_plan.php'],
                        ['label' => 'Process Images', 'url' => '/processDefectImages.php'],
                    ]],
                    ['label' => 'Reports', 'id' => 'reportsDropdownAdmin', 'dropdown' => [
                        ['label' => 'Reporting Hub', 'url' => '/reports.php'],
                        ['label' => 'Data Exporter', 'url' => '/export.php'],
                        ['label' => 'PDF Exports', 'url' => '/pdf_exports/export-pdf-defects-report-filtered.php'],
                    ]],
                    ['label' => 'Communications', 'id' => 'commsDropdownAdmin', 'dropdown' => [
                        ['label' => 'Notification Centre', 'url' => '/notifications.php'],
                        ['label' => 'Broadcast Message', 'url' => '/push_notifications/index.php'],
                    ]],
                    ['label' => 'System', 'id' => 'systemDropdownAdmin', 'dropdown' => [
                        ['label' => 'Admin Console', 'url' => '/admin.php'],
                        ['label' => 'System Settings', 'url' => '/admin/system_settings.php'],
                        ['label' => 'Maintenance Planner', 'url' => '/maintenance/maintenance.php'],
                        ['label' => 'Backup Manager', 'url' => '/backup_manager.php'],
                        ['label' => 'System Health', 'url' => '/system-tools/system_health.php'],
                        ['label' => 'Database Check', 'url' => '/system-tools/check_database.php'],
                        ['label' => 'Database Optimizer', 'url' => '/system-tools/database_optimizer.php'],
                        ['label' => 'GD Library Check', 'url' => '/system-tools/check_gd.php'],
                        ['label' => 'ImageMagick Check', 'url' => '/system-tools/check_imagemagick.php'],
                        ['label' => 'File Structure Map', 'url' => '/system-tools/show_file_structure.php'],
                        ['label' => 'System Analysis Report', 'url' => '/system_analysis_report.php'],
                        ['label' => 'User Logs', 'url' => '/user_logs.php'],
                    ]],
                ];
                break;

            case 'manager':
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
                    ['label' => 'Defects', 'id' => 'defectsDropdownManager', 'dropdown' => [
                        ['label' => 'Defect Control Room', 'url' => '/defects.php'],
                        ['label' => 'Create Defect', 'url' => '/create_defect.php'],
                        ['label' => 'Assign Defects', 'url' => '/assign_to_user.php'],
                        ['label' => 'Upload Completion Evidence', 'url' => '/upload_completed_images.php'],
                        ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
                    ]],
                    ['label' => 'Projects', 'id' => 'projectsDropdownManager', 'dropdown' => [
                        ['label' => 'Projects Directory', 'url' => '/projects.php'],
                        ['label' => 'Project Explorer', 'url' => '/project_details.php'],
                        ['label' => 'Floor Plans', 'url' => '/floor_plans.php'],
                        ['label' => 'Upload Floor Plan', 'url' => '/upload_floor_plan.php'],
                    ]],
                    ['label' => 'Directory', 'id' => 'directoryDropdownManager', 'dropdown' => [
                        ['label' => 'User Management', 'url' => '/user_management.php'],
                        ['label' => 'Add User', 'url' => '/add_user.php'],
                        ['label' => 'Contractors', 'url' => '/contractors.php'],
                        ['label' => 'Add Contractor', 'url' => '/add_contractor.php'],
                    ]],
                    ['label' => 'Reports', 'url' => '/reports.php'],
                    ['label' => 'Communications', 'id' => 'commsDropdownManager', 'dropdown' => [
                        ['label' => 'Notification Centre', 'url' => '/notifications.php'],
                        ['label' => 'Broadcast Message', 'url' => '/push_notifications/index.php'],
                    ]],
                    ['label' => 'Help', 'url' => '/help_index.php'],
                    ['label' => 'Logout', 'url' => '/logout.php'],
                ];
                break;

            case 'contractor':
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
                    ['label' => 'Assigned Defects', 'url' => '/my_tasks.php'],
                    ['label' => 'Submit Evidence', 'url' => '/upload_completed_images.php'],
                    ['label' => 'Notification Centre', 'url' => '/notifications.php'],
                    ['label' => 'Help', 'url' => '/help_index.php'],
                    ['label' => 'Logout', 'url' => '/logout.php'],
                ];
                break;

            case 'inspector':
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
                    ['label' => 'Defects', 'id' => 'defectsDropdownInspector', 'dropdown' => [
                        ['label' => 'Defect Control Room', 'url' => '/defects.php'],
                        ['label' => 'Create Defect', 'url' => '/create_defect.php'],
                        ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
                    ]],
                    ['label' => 'Projects', 'id' => 'projectsDropdownInspector', 'dropdown' => [
                        ['label' => 'Projects Directory', 'url' => '/projects.php'],
                        ['label' => 'Project Explorer', 'url' => '/project_details.php'],
                        ['label' => 'Floor Plans', 'url' => '/floor_plans.php'],
                    ]],
                    ['label' => 'Reports', 'url' => '/reports.php'],
                    ['label' => 'Notification Centre', 'url' => '/notifications.php'],
                    ['label' => 'Help', 'url' => '/help_index.php'],
                    ['label' => 'Logout', 'url' => '/logout.php'],
                ];
                break;

            case 'viewer':
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
                    ['label' => 'Defect Control Room', 'url' => '/defects.php'],
                    ['label' => 'Reports', 'url' => '/reports.php'],
                    ['label' => 'Help', 'url' => '/help_index.php'],
                    ['label' => 'Logout', 'url' => '/logout.php'],
                ];
                break;

            case 'client':
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
                    ['label' => 'Defect Control Room', 'url' => '/defects.php'],
                    ['label' => 'Visualise Defects', 'url' => '/visualize_defects.php'],
                    ['label' => 'Reports', 'url' => '/reports.php'],
                    ['label' => 'Help', 'url' => '/help_index.php'],
                    ['label' => 'Logout', 'url' => '/logout.php'],
                ];
                break;

            // Default case: Provides a minimal menu for any user type not explicitly handled above,
            // or if $userRole (holding user type) ended up being null/empty despite initialization (it should default to 'viewer').
            default:
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'], // Basic dashboard access.
                    ['label' => 'Help', 'url' => '/help_index.php'],     // Access to help.
                    ['label' => 'Logout', 'url' => '/logout.php'],       // Always allow logout.
                ];
                // Log a warning if this default case is reached with an actual user type value that wasn't 'viewer' or one of the handled cases.
                // This helps identify if new user types are added to the DB but not to this switch statement.
                if (!empty($this->userRole) && $this->userRole !== 'viewer') {
                    error_log("Navbar: Default navigation items used for unexpected user type: '" . htmlspecialchars($this->userRole, ENT_QUOTES, 'UTF-8') . "' for User ID: " . $this->userId);
                }
                break; // Exit the switch statement for the default case.
        } // End of switch ($this->userRole) statement.

        // Return the final array containing the navigation structure determined for the current user type.
        return $items;
    } // End of getNavbarItems() method.

} // End of Navbar class definition.
?>