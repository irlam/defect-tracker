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
                        // ...construct the full path to the logo, escaping the filename for HTML safety.
                        $this->userLogo = '/uploads/logos/' . htmlspecialchars($contractor['logo'], ENT_QUOTES, 'UTF-8');
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
        // Output the determined user type as an HTML comment - useful for debugging via browser's "View Source".
        // *** MODIFICATION: Comment reflects it's showing user_type ***
        echo "<!-- DEBUG: Navbar User Type = '" . htmlspecialchars($this->userRole ?? 'NULL', ENT_QUOTES, 'UTF-8') . "' -->";

        // Get the array defining the navigation menu structure for the current user type.
        $navbarItems = $this->getNavbarItems();

        // --- Begin Navbar HTML structure ---
        // Main <nav> element with Bootstrap styling for layout, color, and fixed positioning.
        echo '<nav class="navbar navbar-expand-lg navbar-dark shadow-sm fixed-top" style="background-color:#0b1220;">';
        // container-fluid allows the navbar content to span the full viewport width.
        echo '<div class="container-fluid">';

        // Hamburger button: Appears on smaller screens to toggle the collapsible menu.
        // Uses Bootstrap's collapse plugin via data-bs-toggle and data-bs-target attributes.
        echo '<button class="navbar-toggler me-2" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">';
        echo '<span class="navbar-toggler-icon"></span>'; // Standard Bootstrap icon for the toggler.
        echo '</button>';

        // Brand/Title: Display the application name, typically linking to the main dashboard or home page.
        echo '<a class="navbar-brand px-2" href="/dashboard.php">McGoff Defect Tracker</a>';

        // Collapsible container for navigation links. This div collapses on smaller screens. Its ID must match the toggler's data-bs-target.
        echo '<div class="collapse navbar-collapse" id="navbarNav">';
        // Unordered list for the main navigation items. 'navbar-nav' provides Bootstrap styling for horizontal layout.
        echo '<ul class="navbar-nav">';

        // Loop through the $navbarItems array (generated by getNavbarItems()) to build the menu structure.
        foreach ($navbarItems as $item) {
            // Check if the current item defines a dropdown menu structure.
            if (isset($item['dropdown']) && is_array($item['dropdown'])) {
                // Create the list item element for the dropdown.
                echo '<li class="nav-item dropdown">';
                // Create the link that acts as the dropdown toggle button.
                // 'href="#"' prevents page jump, 'role="button"' for accessibility, 'data-bs-toggle="dropdown"' enables Bootstrap JS.
                echo '<a class="nav-link dropdown-toggle" href="#" id="' . htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') . '" role="button" data-bs-toggle="dropdown" aria-expanded="false">';
                echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); // The visible text of the dropdown link (e.g., "Defects").
                echo '</a>';
                // Create the unordered list for the actual dropdown menu items.
                // 'aria-labelledby' links it back to the toggle button for accessibility.
                echo '<ul class="dropdown-menu" aria-labelledby="' . htmlspecialchars($item['id'], ENT_QUOTES, 'UTF-8') . '">';
                // Loop through the individual links defined within this dropdown's array.
                foreach ($item['dropdown'] as $dropdownItem) {
                    // Create list items for each link within the dropdown.
                    echo '<li><a class="dropdown-item" href="' . htmlspecialchars($dropdownItem['url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($dropdownItem['label'], ENT_QUOTES, 'UTF-8') . '</a></li>';
                }
                echo '</ul>'; // Close the dropdown menu list (ul.dropdown-menu).
                echo '</li>'; // Close the main dropdown list item (li.nav-item.dropdown).
            } else {
                // This item is a simple, direct navigation link (not a dropdown).
                echo '<li class="nav-item">';
                // Create the standard anchor tag for the navigation link.
                echo '<a class="nav-link" href="' . htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') . '</a>';
                echo '</li>'; // Close the list item (li.nav-item).
            }
        }
        echo '</ul>'; // Close the main navigation list (ul.navbar-nav).
        echo '</div>'; // Close the collapsible container div.

        // Right-aligned section: Contains user logo, notification bell, time display, and username.
        // 'd-flex' enables flexbox layout. 'align-items-center' vertically centers items in the flex container.
        // 'ms-auto' applies auto margin to the start (left), pushing this container to the far right.
        echo '<div class="d-flex align-items-center ms-auto">';

        // Check if a user logo path ($this->userLogo) has been set (i.e., is not empty).
        if (!empty($this->userLogo)) {
            // Display the user's logo image. 'me-3' adds margin-end (right margin) for spacing.
            echo '<div class="me-3">';
            // 'img-fluid' makes the image responsive. Inline style sets a max-height for consistent size.
            // Alt text is important for accessibility. Logo path was escaped in setUserTypeAndLogo.
            echo '<img src="' . $this->userLogo . '" alt="User Logo" class="img-fluid" style="max-height: 40px;">';
            echo '</div>';
        }

        // Add notification bell with unread count
        echo '<div class="dropdown me-3">';
        echo '<button class="btn btn-link text-decoration-none position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">';
        echo '<i class="fas fa-bell fa-lg text-primary"></i>';
        // Get unread notification count
        try {
            $unreadQuery = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = :user_id AND is_read = 0";
            $unreadStmt = $this->db->prepare($unreadQuery);
            $unreadStmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $unreadStmt->execute();
            $unreadResult = $unreadStmt->fetch(PDO::FETCH_ASSOC);
            $unreadCount = $unreadResult['unread_count'] ?? 0;

            if ($unreadCount > 0) {
                echo '<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">' . $unreadCount . '</span>';
            }
        } catch (Exception $e) {
            // Silently handle database errors for notifications
            error_log("Error fetching notification count: " . $e->getMessage());
        }
        echo '</button>';
        echo '<ul class="dropdown-menu dropdown-menu-end notification-dropdown" aria-labelledby="notificationDropdown">';
        echo '<li><h6 class="dropdown-header">Notifications</h6></li>';

        // Get recent notifications for dropdown
        try {
            $recentQuery = "SELECT id, type, message, created_at, is_read FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5";
            $recentStmt = $this->db->prepare($recentQuery);
            $recentStmt->bindParam(':user_id', $this->userId, PDO::PARAM_INT);
            $recentStmt->execute();
            $recentNotifications = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($recentNotifications)) {
                echo '<li><span class="dropdown-item-text text-muted">No notifications yet</span></li>';
            } else {
                foreach ($recentNotifications as $notification) {
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

                    echo '<li>';
                    echo '<a class="dropdown-item notification-item ' . (!$notification['is_read'] ? 'unread' : '') . '" href="#" data-notification-id="' . $notification['id'] . '">';
                    echo '<div class="d-flex align-items-start">';
                    echo '<i class="' . $iconClass . ' me-2 mt-1"></i>';
                    echo '<div class="flex-grow-1">';
                    echo '<div class="fw-bold small">' . htmlspecialchars($notification['type']) . '</div>';
                    echo '<div class="text-truncate small text-muted" style="max-width: 250px;">' . htmlspecialchars($notification['message']) . '</div>';
                    echo '<div class="small text-muted">' . date('M j, g:i A', strtotime($notification['created_at'])) . '</div>';
                    echo '</div>';
                    if (!$notification['is_read']) {
                        echo '<span class="badge bg-primary ms-2">New</span>';
                    }
                    echo '</div>';
                    echo '</a>';
                    echo '</li>';
                }
            }

            echo '<li><hr class="dropdown-divider"></li>';
            echo '<li><a class="dropdown-item text-center" href="/notifications.php"><i class="fas fa-list me-1"></i>View All Notifications</a></li>';
        } catch (Exception $e) {
            error_log("Error fetching recent notifications: " . $e->getMessage());
            echo '<li><span class="dropdown-item-text text-muted">Unable to load notifications</span></li>';
        }

        echo '</ul>';
        echo '</div>';

        // Display the UK time and user login information.
        // 'navbar-text' provides vertical alignment and color. 'text-end' aligns text to the right. 'pe-3' adds padding-end (right padding).
        echo '<div class="navbar-text text-end pe-3">';
        // This span has the ID "ukTime" and will be dynamically updated by the JavaScript clock.
        // Shows a placeholder text while loading.
        echo '<span id="ukTime">Loading UK time...</span> | Logged in as: ' . $this->username; // Username was escaped in the constructor.
        echo '</div>';

        echo '</div>'; // Close the right-aligned d-flex container.
        echo '</div>'; // Close the main container-fluid div.
        echo '</nav>'; // Close the main <nav> element.
        // --- End Navbar HTML structure ---


        // --- Begin JavaScript for Live UK Time Clock ---
        // This script is echoed directly into the HTML output sent to the browser.
        // It runs client-side to continuously update the time display.
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

        </script>'; // End of the script tag and the PHP echo statement.

        // Add notification dropdown styles and JavaScript
        echo '<style>
            .notification-dropdown {
                min-width: 350px;
                max-width: 400px;
            }
            .notification-item.unread {
                background-color: #f8f9ff;
            }
            .notification-item:hover {
                background-color: #f8f9fa;
            }
            @media (max-width: 768px) {
                .notification-dropdown {
                    min-width: 300px;
                    max-width: 350px;
                }
            }
        </style>';

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
                // Define the full navigation menu for Admin user type.
                $items = [
                    ['label' => 'Admin Panel', 'url' => '/admin.php'], // Link to the main administration section.
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'], // Link to the primary user dashboard.
                    ['label' => 'Defects', 'id' => 'defectsDropdownAdmin', 'dropdown' => [ // Dropdown menu for defect-related actions.
                        ['label' => 'Add Defects', 'url' => '/create_defect.php'],       // Link to create new defects.
                        ['label' => 'Assign Defects', 'url' => '/assign_to_user.php'],    // Link to assign defects to users/contractors.
                        ['label' => 'View Defects', 'url' => '/view_defect.php'],         // Link to view defects (possibly filtered).
                        ['label' => 'View All Defects', 'url' => '/all_defects.php'],     // Link to view all defects without filters.
                    ]], // End of 'Defects' dropdown definition for admin.
                    ['label' => 'Messages', 'url' => '/push_notifications/index.php'], // Link for sending messages or notifications.
                    ['label' => 'User Management', 'url' => '/user_management.php'],    // Link to manage user accounts.
                    ['label' => 'Maintenance', 'url' => '/maintenance/maintenance.php'],// Link to system maintenance tasks.
                    ['label' => 'Reports', 'url' => '/reports.php'],                    // Link to the reporting section.
                    ['label' => 'Help', 'url' => '/help_index.php'],                     // Link to help documentation.
                    ['label' => 'Logout', 'url' => '/logout.php'],                       // Link to log the user out.
                ]; // End of 'admin' items array definition.
                break; // Exit the switch statement for 'admin' case.

            case 'manager': // Assumes 'manager' is a value in the 'user_type' column. Adjust if needed.
                // Define the navigation menu for Manager user type.
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],
                    // Defects dropdown for the manager user type.
                    ['label' => 'Defects',
                        'id' => 'defectsDropdownManager', // Unique ID for this dropdown instance.
                        'dropdown' => [
                            ['label' => 'Add Defects', 'url' => '/create_defect.php'],        // Can add defects.
                            ['label' => 'Assign Defects', 'url' => '/assign_to_user.php'],     // Can assign defects.
                            ['label' => 'View Defects', 'url' => '/view_defect.php'],          // Can view defects (standard view).
                            ['label' => 'View All Defects', 'url' => '/all_defects.php'],      // Can view all defects.
                        ]
                    ], // End of 'Defects' dropdown definition for manager.
                    ['label' => 'Messages', 'url' => '/push_notifications/index.php'], // Can send messages.
                    ['label' => 'Reports', 'url' => '/reports.php'],                    // Can view reports.
                    ['label' => 'Add Users', 'url' => '/add_user.php'],                 // Can add new users.
                    ['label' => 'Help', 'url' => '/help_index.php'],                     // Access to help.
                    ['label' => 'Logout', 'url' => '/logout.php'],                       // Logout link.
                ]; // End of 'manager' items array definition.
                break; // Exit the switch statement for 'manager' case.

            case 'contractor': // Assumes 'contractor' is a value in the 'user_type' column. Adjust if needed.
                // Define the navigation menu for Contractor user type.
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],           // Access to their dashboard.
                    ['label' => 'Update Defects', 'url' => '/update_defects.php'], // Link to update defects assigned to them.
                    ['label' => 'My Tasks', 'url' => '/my_tasks.php'],             // Link to view a list of their assigned tasks/defects.
                    ['label' => 'Help', 'url' => '/help_index.php'],               // Access to help.
                    ['label' => 'Logout', 'url' => '/logout.php'],                 // Logout link.
                ]; // End of 'contractor' items array definition.
                break; // Exit the switch statement for 'contractor' case.

            case 'viewer': // Assumes 'viewer' is a value in the 'user_type' column. Adjust if needed.
                // Define the navigation menu for Viewer user type (typically read-only access).
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],    // Access to dashboard.
                    ['label' => 'View Defects', 'url' => '/view_defects.php'], // Simple link to view defects.
                    ['label' => 'Reports', 'url' => '/reports.php'],        // Access to view reports.
                    ['label' => 'Help', 'url' => '/help_index.php'],         // Access to help.
                    ['label' => 'Logout', 'url' => '/logout.php'],           // Logout link.
                ]; // End of 'viewer' items array definition.
                break; // Exit the switch statement for 'viewer' case.

            case 'client': // Assumes 'client' is a value in the 'user_type' column. Adjust if needed.
                // Define the navigation menu for Client user type.
                $items = [
                    ['label' => 'Dashboard', 'url' => '/dashboard.php'],              // Access to their dashboard view.
                    ['label' => 'View Defects', 'url' => '/view_defects.php'],         // Link to view defects relevant to them.
                    ['label' => 'Comment on Defects', 'url' => '/comment_defects.php'],// Link to add comments to defects.
                    ['label' => 'Help', 'url' => '/help_index.php'],                   // Access to help.
                    ['label' => 'Logout', 'url' => '/logout.php'],                     // Logout link.
                ]; // End of 'client' items array definition.
                break; // Exit the switch statement for 'client' case.

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