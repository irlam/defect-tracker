<?php
// includes/navbar-side.php
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <!-- Main Navigation -->
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class='bx bxs-dashboard'></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>" href="projects.php">
                    <i class='bx bx-folder'></i>
                    <span>Projects</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'defects.php' ? 'active' : ''; ?>" href="defects.php">
                    <i class='bx bx-bug'></i>
                    <span>Defects</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                    <i class='bx bx-bar-chart'></i>
                    <span>Reports</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'contractor_stats.php' ? 'active' : ''; ?>" href="contractor_stats.php">
                    <i class='bx bx-bar-chart'></i>
                    <span>Contractor Stats</span>
                </a>
            </li>
        </ul>

        <!-- Administration Section -->
        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Administration</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'user_management.php' ? 'active' : ''; ?>" href="user_management.php">
                    <i class='bx bxs-user-account'></i>
                    <span>User Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'role_management.php' ? 'active' : ''; ?>" href="role_management.php">
                    <i class='bx bxs-key'></i>
                    <span>Role Management</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['settings.php', 'system_settings.php']) ? 'active' : ''; ?>" href="admin/system_settings.php">
                    <i class='bx bxs-cog'></i>
                    <span>System Settings</span>
                </a>
            </li>
        </ul>

        <!-- System Information -->
        <div class="px-3 mt-4 mb-2 text-muted">
            <small>
                <div>Last Updated: <?php echo date('Y-m-d H:i:s'); ?></div>
                <div>User: <?php echo htmlspecialchars($currentUser ?? 'irlam'); ?></div>
            </small>
        </div>
    </div>
</nav>