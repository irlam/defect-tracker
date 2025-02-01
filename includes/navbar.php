<?php

class Navbar {
    private $db;
    private $userId;
    private $userDetails;
    private $menuItems;
    private $dropdownItems;

    // Constructor to initialize the Navbar with the database connection and user ID
    public function __construct(PDO $db, int $userId) {
        $this->db = $db;
        $this->userId = $userId;
        $this->loadUserDetails(); // Load details of the user
        $this->initializeMenu(); // Initialize the menu items based on user details
    }

    // Load user details from the database
    private function loadUserDetails() {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.username,
                u.email,
                u.user_type,
                u.status,
                u.is_active,
                ur.role_id,
                r.name as role_name
            FROM users u
            LEFT JOIN user_roles ur ON u.id = ur.user_id
            LEFT JOIN roles r ON ur.role_id = r.id
            WHERE u.id = ?
            AND u.is_active = 1
        ");
        
        $stmt->execute([$this->userId]);
        $this->userDetails = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$this->userDetails) {
            throw new Exception('User details not found');
        }
    }

    // Initialize the menu items and dropdown items
    private function initializeMenu() {
        // Define the menu items
        $this->menuItems = [
            [
                'title' => 'Dashboard',
                'url' => '/dashboard.php',
                'icon' => 'bx bx-home-circle',
                'active' => $this->isCurrentPage('/dashboard.php')
            ],
            [
                'title' => 'Create Defect',
                'url' => '/create_defect.php',
                'icon' => 'bx bx-plus-circle',
                'active' => $this->isCurrentPage('/create_defect.php'),
                'visible' => $this->userDetails['role_id'] != 4
            ],
            [
                'title' => 'My Tasks',
                'url' => '/my_tasks.php',
                'icon' => 'bx bx-task',
                'active' => $this->isCurrentPage('/my_tasks.php'),
                'visible' => in_array($this->userDetails['role_id'], [1, 3])
            ],
            [
                'title' => 'Reports',
                'url' => '/reports.php',
                'icon' => 'bx bx-bar-chart-alt-2',
                'active' => $this->isCurrentPage('/reports.php')
            ],
            [
                'title' => 'Defects',
                'url' => '/defects.php',
                'icon' => 'bx bx-bug',
                'active' => $this->isCurrentPage('/defects.php'),
                'visible' => in_array($this->userDetails['role_id'], [1, 2, 3, 4, 5])
            ],
            [
                'title' => 'Contractors',
                'url' => '/contractors.php',
                'icon' => 'bx bx-hard-hat',
                'active' => $this->isCurrentPage('/contractors.php'),
                'visible' => in_array($this->userDetails['role_id'], [1, 2, 3])
            ]
        ];

        // Define the dropdown items for Admin Tools
        $this->dropdownItems = [
            [
                'title' => 'Projects',
                'url' => '/projects.php',
                'icon' => 'bx bx-buildings',
                'active' => $this->isCurrentPage('/projects.php'),
                'visible' => in_array($this->userDetails['role_id'], [1])
            ],
            [
                'title' => 'Manage Users',
                'url' => '/user_management.php',
                'icon' => 'bx bx-user',
                'active' => $this->isCurrentPage('/user_management.php'),
                'visible' => in_array($this->userDetails['role_id'], [1, 2])
            ],
            [
                'title' => 'User Logs',
                'url' => '/user_logs.php',
                'icon' => 'bx bx-file',
                'active' => $this->isCurrentPage('/user_logs.php'),
                'visible' => in_array($this->userDetails['role_id'], [1, 2])
            ],
            [
                'title' => 'Comments',
                'url' => '/comments.php',
                'icon' => 'bx bx-comment',
                'active' => $this->isCurrentPage('/comments.php'),
                'visible' => in_array($this->userDetails['role_id'], [5])
            ],
            [
                'title' => 'Floor Plans', // New 'Floor Plans' section
                'url' => '/floor_plans.php',
                'icon' => 'bx bx-map',
                'active' => $this->isCurrentPage('/floor_plans.php'),
                'visible' => in_array($this->userDetails['role_id'], [1]) // Visible only to admin
            ]
        ];
    }

    // Check if the current page matches the given path
    private function isCurrentPage($path) {
        return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $path;
    }

    // Render the navbar HTML
    public function render() {
        $username = htmlspecialchars($this->userDetails['username']);
        $userType = htmlspecialchars(ucfirst($this->userDetails['role_name']));

        $html = <<<HTML
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <style>
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                bottom: 0;
                width: 250px;
                background: #2C3E50;
                color: #fff;
                transition: all 0.3s;
                z-index: 1000;
                box-shadow: 4px 0 10px rgba(0,0,0,0.1);
                display: flex;
                flex-direction: column;
                justify-content: space-between;
            }

            .sidebar.collapsed {
                width: 70px;
            }

            .sidebar-header {
                padding: 20px;
                background: #1a2634;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }

            .sidebar-brand {
                color: #fff;
                text-decoration: none;
                font-size: 1.2rem;
                font-weight: 600;
                display: flex;
                align-items: center;
            }

            .sidebar-brand i {
                font-size: 1.5rem;
                margin-right: 10px;
            }

            .sidebar-menu {
                padding: 1rem 0;
                flex-grow: 1;
            }

            .sidebar-item {
                padding: 0.8rem 1.5rem;
                color: #fff;
                text-decoration: none;
                display: flex;
                align-items: center;
                transition: all 0.3s;
            }

            .sidebar-item:hover {
                background: rgba(255,255,255,0.1);
                color: #fff;
            }

            .sidebar-item.active {
                background: #3498db;
                color: #fff;
            }

            .sidebar-item i {
                font-size: 1.2rem;
                margin-right: 10px;
                width: 20px;
                text-align: center;
            }

            .sidebar-footer {
                padding: 1rem;
                background: #1a2634;
                border-top: 1px solid rgba(255,255,255,0.1);
            }

            .user-menu {
                padding: 0.5rem 1rem;
                display: flex;
                align-items: center;
                cursor: pointer;
            }

            .user-menu:hover {
                background: rgba(255,255,255,0.1);
                border-radius: 5px;
            }

            .user-menu img {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                margin-right: 10px;
            }

            .user-info {
                flex-grow: 1;
            }

            .user-name {
                font-weight: 600;
                font-size: 0.9rem;
                margin: 0;
            }

            .user-role {
                font-size: 0.8rem;
                opacity: 0.8;
                margin: 0;
            }

            .toggle-sidebar {
                position: absolute;
                right: -15px;
                top: 50%;
                transform: translateY(-50%);
                width: 30px;
                height: 30px;
                background: #3498db;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }

            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                }
                
                .sidebar.active {
                    transform: translateX(0);
                }
            }

            .dropdown {
                position: relative;
            }

            .dropdown-content {
                display: none;
                position: absolute;
                background-color: #2C3E50;
                min-width: 160px;
                box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
                z-index: 1;
            }

            .dropdown-content a {
                color: white;
                padding: 12px 16px;
                text-decoration: none;
                display: block;
            }

            .dropdown-content a:hover {
                background-color: #ddd;
                color: black;
            }

            .dropdown:hover .dropdown-content {
                display: block;
            }

            .dropdown-btn {
                padding: 0.8rem 1.5rem;
                color: white;
                text-decoration: none;
                display: flex;
                align-items: center;
                transition: all 0.3s;
                cursor: pointer;
                background: none;
                border: none;
                width: 100%;
                text-align: left;
            }

            .dropdown-btn:hover {
                background: rgba(255,255,255,0.1);
            }

            .dropdown-btn i {
                font-size: 1.2rem;
                margin-right: 10px;
                width: 20px;
                text-align: center;
            }
        </style>

        <div class="sidebar" id="sidebar">
            <div>
                <div class="sidebar-header">
                    <a href="/dashboard.php" class="sidebar-brand">
                        <i class='bx bx-building-house'></i>
                        <span class="brand-text">Defect Tracker</span>
                    </a>
                </div>

                <div class="sidebar-menu">
HTML;

        // Render menu items
        foreach ($this->menuItems as $item) {
            if (isset($item['visible']) && !$item['visible']) continue;
            $activeClass = $item['active'] ? ' active' : '';
            $html .= <<<HTML
                    <a href="{$item['url']}" class="sidebar-item{$activeClass}">
                        <i class="{$item['icon']}"></i>
                        <span class="menu-text">{$item['title']}</span>
                    </a>
HTML;
        }

        // Render dropdown for admin tools
        $html .= <<<HTML
                    <div class="dropdown">
                        <button class="dropdown-btn">
                            <i class='bx bx-cog'></i>
                            <span class="menu-text">Admin Tools</span>
                        </button>
                        <div class="dropdown-content">
HTML;

        // Render dropdown items
        foreach ($this->dropdownItems as $item) {
            if (isset($item['visible']) && !$item['visible']) continue;
            $activeClass = $item['active'] ? ' active' : '';
            $html .= <<<HTML
                            <a href="{$item['url']}" class="{$activeClass}">
                                <i class="{$item['icon']}"></i>
                                <span class="menu-text">{$item['title']}</span>
                            </a>
HTML;
        }

        $html .= <<<HTML
                        </div>
                    </div>
                </div>
            </div>

            <div class="sidebar-footer">
                <div class="user-menu" data-bs-toggle="dropdown">
                    <i class='bx bx-user-circle' style="font-size: 32px;"></i>
                    <div class="user-info">
                        <p class="user-name">{$username}</p>
                        <p class="user-role">{$userType}</p>
                    </div>
                </div>
                <div class="dropdown-menu dropdown-menu-end">
                    <a class="dropdown-item" href="/profile.php">
                        <i class='bx bx-user me-2'></i>Profile
                    </a>
                    <a class="dropdown-item" href="/preferences.php">
                        <i class='bx bx-cog me-2'></i>Preferences
                    </a>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="/logout.php">
                        <i class='bx bx-log-out me-2'></i>Logout
                    </a>
                </div>
            </div>

            <div class="toggle-sidebar" onclick="toggleSidebar()">
                <i class='bx bx-chevron-left'></i>
            </div>
        </div>

        <script>
            function toggleSidebar() {
                const sidebar = document.getElementById('sidebar');
                sidebar.classList.toggle('collapsed');
                
                // Save state to localStorage
                localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
            }

            // Restore sidebar state on page load
            document.addEventListener('DOMContentLoaded', function() {
                const sidebar = document.getElementById('sidebar');
                const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                if (isCollapsed) {
                    sidebar.classList.add('collapsed');
                }

                // Handle mobile toggle
                const mobileToggle = document.querySelector('.navbar-toggler');
                if (mobileToggle) {
                    mobileToggle.addEventListener('click', function() {
                        sidebar.classList.toggle('active');
                    });
                }
            });
        </script>
HTML;

        return $html;
    }
}
?>