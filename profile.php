<?php
// profile.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-22 17:00:00
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';

$pageTitle = 'My Profile';
$currentUser = $_SESSION['username'];
$success_message = '';
$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch user details from the users table
    $stmt = $db->prepare("
        SELECT 
            u.id,
            u.username,
            u.email,
            u.first_name,
            u.last_name,
            u.role,
            u.created_at,
            u.last_login,
            u.is_active,
            COALESCE(u.updated_at, u.created_at) as last_modified,
            COUNT(d.id) as total_defects_assigned,
            u.contractor_id,
            u.contractor_name,
            u.contractor_trade
        FROM users u
        LEFT JOIN defects d ON u.id = d.assigned_to AND d.status != 'closed'
        WHERE u.id = ? AND u.is_active = 1
        GROUP BY u.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Fetch contractor details using the contractor_id from the users table.
    // This query retrieves the contractor logo and company details.
    $contractor = null;
    if (!empty($user['contractor_id'])) {
        $stmt = $db->prepare("SELECT logo, company_name FROM contractors WHERE id = ?");
        $stmt->execute([$user['contractor_id']]);
        $contractor = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Fetch recent activity
    $stmt = $db->prepare("
        SELECT 
            d.id,
            d.title,
            d.status,
            d.updated_at,
            p.name as project_name
        FROM defects d
        LEFT JOIN projects p ON d.project_id = p.id
        WHERE d.assigned_to = ?
        ORDER BY d.updated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle password change request from the user
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            // Validate input fields
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception("All password fields are required");
            }
            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }
            if (strlen($new_password) < 8) {
                throw new Exception("Password must be at least 8 characters long");
            }

            // Verify the current password
            $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $current_hash = $stmt->fetchColumn();
            if (!password_verify($current_password, $current_hash)) {
                throw new Exception("Current password is incorrect");
            }

            // Update the user's password
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("
                UPDATE users 
                SET password = ?,
                    updated_at = UTC_TIMESTAMP(),
                    updated_by = ?
                WHERE id = ?
            ");
            if ($stmt->execute([$new_hash, $_SESSION['user_id'], $_SESSION['user_id']])) {
                $success_message = "Password updated successfully";
            } else {
                throw new Exception("Failed to update password");
            }
        }
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Profile Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        /* Global override: force all visible text on the page to black */
        body, h1, h2, h3, h4, h5, h6, p, a, label, span, li, input, button, .card, .alert,
        .breadcrumb, .list-group-item, .card-title, .small, .text-muted {
            color: #000 !important;
        }
        /* Ensure the profile avatar retains its white text */
        .profile-avatar {
            color: #fff !important;
        }
        /* General page styling */
        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            min-height: 100vh;
            background-color: #f8f9fa;
        }
        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }
        .profile-header {
            background: #fff;
            border-radius: 0.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        .profile-stats {
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .activity-item {
            padding: 0.75rem;
            border-left: 3px solid #dee2e6;
            margin-bottom: 0.5rem;
            background: #fff;
            transition: all 0.3s ease;
        }
        .activity-item:hover {
            border-left-color: #0d6efd;
            background: #f8f9fa;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25em 0.5em;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">My Profile</li>
                    </ol>
                </nav>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bx bx-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bx bx-error-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Information -->
        <div class="row">
            <!-- Left Box: Profile Card -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-4">
                            <div class="profile-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                            </div>
                            <div class="flex-grow-1" style="margin-left: 15px; display: flex; align-items: center;">
                                <div>
                                    <h5 class="card-title mb-0"><?php echo htmlspecialchars($user['username']); ?></h5>
                                    <p class="text-muted mb-0"><?php echo htmlspecialchars($user['role']); ?></p>
                                </div>
                                <?php if ($contractor && !empty($contractor['logo'])): ?>
                                    <?php
                                        $logoFilename = $contractor['logo'];
                                        $logoPrefix = 'uploads/logos/';
                                        if (stripos($logoFilename, $logoPrefix) === 0) {
                                            $logoFilename = substr($logoFilename, strlen($logoPrefix));
                                        }
                                    ?>
                                    <div style="margin-left: auto;">
                                        <img src="https://mcgoff.defecttracker.uk/uploads/logos/<?php echo htmlspecialchars($logoFilename); ?>" 
                                             alt="<?php echo htmlspecialchars($contractor['company_name']); ?> Logo"
                                             style="max-height: 50px;">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-stats">
                            <div class="row text-center">
                                <div class="col">
                                    <h5 class="mb-0"><?php echo $user['total_defects_assigned']; ?></h5>
                                    <small class="text-muted">Open Defects</small>
                                </div>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bx bx-envelope me-2"></i>Email</span>
                                <span class="text-muted"><?php echo htmlspecialchars($user['email']); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bx bx-user me-2"></i>Full Name</span>
                                <span class="text-muted"><?php echo htmlspecialchars(trim($user['first_name'] . ' ' . $user['last_name'])); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bx bx-calendar me-2"></i>Joined</span>
                                <span class="text-muted"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bx bx-time me-2"></i>Last Login</span>
                                <span class="text-muted"><?php echo $user['last_login'] ? date('M d, Y H:i', strtotime($user['last_login'])) : 'Never'; ?></span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            <!-- Right Box: Password Change and Recent Activity -->
            <div class="col-md-8">
                <!-- Password Change Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Change Password</h5>
                    </div>
                    <div class="card-body">
                        <form method="post" id="passwordForm">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="8" id="newPassword">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="8">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_activity)): ?>
                            <p class="text-muted mb-0">No recent activity</p>
                        <?php else: ?>
                            <?php foreach ($recent_activity as $activity): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1">
                                                <a href="view_defect.php?id=<?php echo $activity['id']; ?>">
                                                    <?php echo htmlspecialchars($activity['title']); ?>
                                                </a>
                                            </h6>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($activity['project_name']); ?> •
                                                <?php echo date('M d, Y H:i', strtotime($activity['updated_at'])); ?>
                                            </small>
                                        </div>
                                        <?php
                                        $statusClasses = [
                                            'open' => 'warning',
                                            'in_progress' => 'info',
                                            'closed' => 'success'
                                        ];
                                        $statusClass = $statusClasses[$activity['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?> status-badge">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Password form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementsByName('confirm_password')[0].value;
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match!');
                return false;
            }
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }
        });

        // Auto-dismiss alerts
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>
</body>
</html>