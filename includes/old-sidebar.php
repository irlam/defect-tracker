<?php
// includes/sidebar.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-17 12:48:27
// Current User's Login: irlam

// Initialize stats array if not set
if (!isset($stats)) {
    $stats = [
        'contractors' => [
            'total' => 0,
            'active' => 0,
            'pending' => 0
        ]
    ];

    // Get database connection if not already established
    if (!isset($db) && class_exists('Database')) {
        try {
            $database = new Database();
            $db = $database->getConnection();

            // Get contractor statistics
            $statsQuery = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
            FROM contractors";
            
            $statsStmt = $db->query($statsQuery);
            $contractorStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($contractorStats) {
                $stats['contractors'] = [
                    'total' => (int)$contractorStats['total'],
                    'active' => (int)$contractorStats['active'],
                    'pending' => (int)$contractorStats['pending']
                ];
            }
        } catch (Exception $e) {
            error_log("Sidebar Stats Error: " . $e->getMessage());
        }
    }
}

?>

<div class="sidebar">
    <a href="dashboard.php" class="sidebar-brand">
        <i class='bx bx-building-house'></i>
        <span>Defect Tracker</span>
    </a>
    
    <ul class="sidebar-nav">
        <!-- Dashboard -->
        <li class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo isActivePage('dashboard.php'); ?>">
                <i class='bx bxs-dashboard'></i>
                <span>Dashboard</span>
            </a>
        </li>
        
        <!-- My Tasks -->
        <li class="nav-item">
            <a href="my_tasks.php" class="nav-link <?php echo isActivePage('my_tasks.php'); ?>">
                <i class='bx bx-task'></i>
                <span>My Tasks</span>
            </a>
        </li>
        
        <!-- Projects -->
        <li class="nav-item">
            <a href="projects.php" class="nav-link <?php echo isActivePage('projects.php'); ?>">
                <i class='bx bx-folder'></i>
                <span>Projects</span>
            </a>
        </li>

        <!-- Defects -->
        <li class="nav-item">
            <a href="defects.php" class="nav-link <?php echo isActivePage('defects.php'); ?>">
                <i class='bx bx-bug'></i>
                <span>Defects</span>
            </a>
        </li>

        <!-- Contractors -->
        <li class="nav-item">
            <a href="contractors.php" class="nav-link <?php echo isActivePage('contractors.php'); ?>">
                <i class='bx bx-buildings'></i>
                <span>Contractors</span>
            </a>
        </li>

        <!-- Pending Contractors (shown only if there are pending approvals) -->
        <?php if ($stats['contractors']['pending'] > 0): ?>
        <li class="nav-item">
            <a href="pending_contractors.php" class="nav-link <?php echo isActivePage('pending_contractors.php'); ?>">
                <i class='bx bxs-time'></i>
                <span>Pending Approvals</span>
                <span class="badge bg-warning text-dark"><?php echo $stats['contractors']['pending']; ?></span>
            </a>
        </li>
        <?php endif; ?>

        <!-- Reports -->
        <li class="nav-item">
            <a href="reports.php" class="nav-link <?php echo isActivePage('reports.php'); ?>">
                <i class='bx bx-chart'></i>
                <span>Reports</span>
            </a>
        </li>

        <!-- Settings (visible only to admin users) -->
        <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
        <li class="nav-item">
            <a href="settings.php" class="nav-link <?php echo isActivePage('settings.php'); ?>">
                <i class='bx bx-cog'></i>
                <span>Settings</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <!-- User Profile -->
        <a href="profile.php" class="nav-link">
            <i class='bx bx-user-circle'></i>
            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
        </a>
        
        <!-- Logout -->
        <a href="logout.php" class="nav-link text-danger">
            <i class='bx bx-log-out'></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<style>
/* Sidebar Styles */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    height: 100vh;
    width: var(--sidebar-width);
    background-color: var(--primary-color);
    padding: 1rem;
    transition: all 0.3s ease;
    z-index: 1000;
}

/* Sidebar Brand/Logo */
.sidebar-brand {
    color: #fff;
    text-decoration: none;
    font-size: 1.25rem;
    font-weight: 600;
    padding: 1rem;
    margin: -1rem -1rem 1rem -1rem;
    background-color: rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.sidebar-brand:hover {
    color: #fff;
    background-color: rgba(0, 0, 0, 0.2);
}

/* Sidebar Navigation */
.sidebar-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}

.nav-item {
    margin-bottom: 0.25rem;
}

.nav-link {
    color: rgba(255, 255, 255, 0.8);
    text-decoration: none;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    border-radius: 0.375rem;
    gap: 0.5rem;
    transition: all 0.2s ease;
}

.nav-link:hover {
    color: #fff;
    background-color: rgba(255, 255, 255, 0.1);
}

.nav-link.active {
    color: #fff;
    background-color: rgba(255, 255, 255, 0.2);
}

.nav-link i {
    font-size: 1.25rem;
    width: 1.5rem;
    text-align: center;
}

/* Sidebar Footer */
.sidebar-footer {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 1rem;
    background-color: rgba(0, 0, 0, 0.1);
}

.sidebar-footer .nav-link {
    padding: 0.5rem 1rem;
}

/* Badge in Sidebar */
.sidebar .badge {
    margin-left: auto;
}

/* Responsive Sidebar */
@media (max-width: 768px) {
    .sidebar {
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
}
</style>