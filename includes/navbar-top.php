// includes/navbar-top.php
<?php
$currentUser = 'irlam';
$currentDateTime = '2025-01-14 13:57:36';
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">Construction Defect Tracker</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNavbar" aria-controls="topNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="topNavbar">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="nav-link">
                        <i class='bx bx-time'></i> UTC: <?php echo $currentDateTime; ?>
                    </span>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class='bx bx-user'></i> <?php echo htmlspecialchars($currentUser); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li><a class="dropdown-item" href="#"><i class='bx bx-user-circle'></i> Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class='bx bx-cog'></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class='bx bx-log-out'></i> Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>