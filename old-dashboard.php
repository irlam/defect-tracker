<?php
/**
 * dashboard.php
 * Current Date and Time (UTC): 2025-02-23 15:12:28
 * Current User's Login: irlam
 */

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

// Initial debug logging
error_log("Session Debug for user {$_SESSION['username']}:");
error_log("Session data: " . print_r($_SESSION, true));

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Standardize session variables - ensure user_id is set
    if (!isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
        $stmt = $db->prepare("
            SELECT id, user_type, status, is_active 
            FROM users 
            WHERE username = ?
        ");
        $stmt->execute([$_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['user_status'] = $user['status'];
            $_SESSION['is_active'] = $user['is_active'];
        } else {
            error_log("Failed to find user data for username: {$_SESSION['username']}");
        }
    }

    // Verify and update admin role
    $adminRoleCheck = "
        INSERT INTO roles (id, name, description, created_at)
        VALUES (1, 'admin', 'Administrator role with full access', UTC_TIMESTAMP())
        ON DUPLICATE KEY UPDATE
            name = 'admin',
            description = 'Administrator role with full access',
            updated_at = UTC_TIMESTAMP()
    ";
    $db->exec($adminRoleCheck);

    // Update user permissions
    $userUpdate = "
        UPDATE users 
        SET 
            user_type = 'admin',
            status = 'active',
            is_active = 1,
            updated_at = UTC_TIMESTAMP()
        WHERE username = :username
        AND (user_type != 'admin' OR status != 'active' OR is_active != 1)
    ";
    $updateStmt = $db->prepare($userUpdate);
    $updateStmt->execute(['username' => $_SESSION['username']]);

    // Ensure user has admin role
    $roleUpdate = "
        INSERT INTO user_roles (user_id, role_id, created_at)
        SELECT u.id, 1, UTC_TIMESTAMP()
        FROM users u
        WHERE u.username = :username
        ON DUPLICATE KEY UPDATE
            role_id = 1,
            updated_at = UTC_TIMESTAMP()
    ";
    $roleStmt = $db->prepare($roleUpdate);
    $roleStmt->execute(['username' => $_SESSION['username']]);

    // Get and verify user permissions
    $stmt = $db->prepare("
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
        WHERE u.username = ?
        AND u.is_active = 1
    ");
    $stmt->execute([$_SESSION['username']]);
    $userDetails = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userDetails) {
        error_log("Invalid user details for username: {$_SESSION['username']}");
        session_destroy();
        header("Location: login.php?error=invalid_user");
        exit();
    }

    // Update session with verified user details
    $_SESSION['user_id'] = $userDetails['id'];
    $_SESSION['user_type'] = $userDetails['user_type'];
    $_SESSION['role_id'] = $userDetails['role_id'];
    $_SESSION['user_status'] = $userDetails['status'];
    $_SESSION['is_admin'] = ($userDetails['user_type'] === 'admin' && $userDetails['role_id'] === 1);

    // Set timezone to UK
    date_default_timezone_set('Europe/London');

    $pageTitle = 'Defects Dashboard';
    $currentUser = $_SESSION['username'];
    $currentDateTime = date('Y-m-d H:i:s');
    $error_message = '';

    // Get overall statistics
    $statsQuery = "SELECT 
        (SELECT COUNT(*) FROM contractors WHERE status = 'active') as active_contractors,
        (SELECT COUNT(*) FROM defects WHERE status = 'open') as open_defects,
        (SELECT COUNT(*) FROM defects) as total_defects,
        (SELECT COUNT(*) FROM defects WHERE status = 'pending') as pending_defects";
    $statsStmt = $db->prepare($statsQuery);
    $statsStmt->execute();
    $overallStats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    // Get defects by contractor statistics (updated to count 'pending' defects)
    // Now, defects with a status 'accepted' are considered as closed and defects with a status 'rejected' are also shown.
    $contractorsQuery = "SELECT 
        c.id,
        c.company_name,
        c.status as contractor_status,
        c.logo,
        COUNT(d.id) as total_defects,
        SUM(CASE WHEN LOWER(d.status) = 'open' THEN 1 ELSE 0 END) as open_defects,
        SUM(CASE WHEN LOWER(d.status) = 'pending' THEN 1 ELSE 0 END) as pending_defects,
        SUM(CASE WHEN LOWER(d.status) = 'accepted' THEN 1 ELSE 0 END) as closed_defects,
        SUM(CASE WHEN LOWER(d.status) = 'rejected' THEN 1 ELSE 0 END) as rejected_defects,
        IFNULL(MAX(d.updated_at), 'N/A') as last_update
    FROM contractors c
    LEFT JOIN defects d ON c.id = d.assigned_to
    WHERE c.status = 'active'
    GROUP BY c.id, c.company_name, c.status, c.logo
    ORDER BY total_defects DESC, company_name ASC";
    $contractorsStmt = $db->prepare($contractorsQuery);
    $contractorsStmt->execute();
    $contractorStats = $contractorsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get recent defects with enhanced details
    $recentDefects = $db->prepare("
        SELECT 
            d.id,
            d.status,
            d.priority,
            d.created_at,
            d.updated_at,
            c.company_name,
            c.trade,
            c.logo,
            u.username as reported_by
        FROM defects d
        LEFT JOIN contractors c ON d.assigned_to = c.id
        LEFT JOIN users u ON d.created_by = u.id
        ORDER BY d.created_at DESC
        LIMIT 10
    ");
    $recentDefects->execute();
    $recentDefectsList = $recentDefects->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the dashboard: " . $e->getMessage();
}

// Helper functions
function getPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high': return 'danger';
        case 'medium': return 'warning';
        case 'low': return 'success';
        default: return 'secondary';
    }
}
function formatUKDate($date) {
    return $date && $date !== 'N/A' ? date('d/m/Y H:i', strtotime($date)) : 'N/A';
}
function getTrendIndicator($value, $threshold = 0) {
    if ($value > $threshold) {
        return '<i class="bx bx-trending-up text-success"></i>';
    } elseif ($value < $threshold) {
        return '<i class="bx bx-trending-down text-danger"></i>';
    }
    return '<i class="bx bx-minus text-warning"></i>';
}
function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "just now";
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . " min" . ($mins > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } else {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    }
}
function getStatusBadgeClass($status) {
    $statusClasses = [
        'open' => 'danger',
        'in_progress' => 'warning',
        'resolved' => 'info',
        'accepted' => 'success',
        'pending' => 'secondary',
        'rejected' => 'danger'
    ];
    return $statusClasses[strtolower($status)] ?? 'secondary';
}
function getPriorityBadgeClass($priority) {
    $priorityClasses = [
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'success'
    ];
    return $priorityClasses[strtolower($priority)] ?? 'secondary';
}
function formatUKDateTime($date) {
    return date('d/m/Y H:i', strtotime($date));
}
function getBaseUrl() {
    return 'https://mcgoff.defecttracker.uk';
}

$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Defect Tracker Dashboard">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser ?? ''); ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? ''); ?> - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    
    <!-- Essential CSS Dependencies (using CDN only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Base Layout */
        .main-content {
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
            margin-top: 56px; /* Adjust based on the height of your navbar */
        }
        .navbar-logo {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
        }
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
        }
        /* Gradient and Card Styles */
        .stats-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 25px 0 rgba(0, 0, 0, 0.1);
            background: #fff;
            margin-bottom: 1.5rem;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 28px 0 rgba(0, 0, 0, 0.15);
        }
        .stats-card .gradient-layer {
            position: relative;
            padding: 1.5rem;
            border-radius: 20px;
            background: linear-gradient(45deg, var(--gradient-start), var(--gradient-end));
            color: white;
        }
        /* Card Variant Gradients */
        .stats-card.contractors { --gradient-start: #4158D0; --gradient-end: #C850C0; }
        .stats-card.open-defects { --gradient-start: #FF416C; --gradient-end: #FF4B2B; }
        .stats-card.total-defects { --gradient-start: #8EC5FC; --gradient-end: #E0C3FC; }
        .stats-card.pending-defects { --gradient-start: #F6D365; --gradient-end: #FDA085; }
        /* Table Styling */
        .table th {
            border-top: none;
            background-color: rgba(0,0,0,0.02);
            font-weight: 600;
        }
        .table thead tr th:last-child {
            color: black !important;
        }
        /* Additional Styles */
        .btn-create-defect {
            font-size: 1.5rem;
            padding: 15px 40px;
            border-radius: 15px;
            background-color: #28a745;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }
        .btn-create-defect:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body>
<?php
$navbar->render();
?>
	<br><br>
<div class="main-content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-3">
                <div class="stats-card contractors">
                    <div class="gradient-layer">
                        <h3 class="card-title">Active Contractors</h3>
                        <p class="card-text"><?php echo $overallStats['active_contractors']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card open-defects">
                    <div class="gradient-layer">
                        <h3 class="card-title">Open Defects</h3>
                        <p class="card-text"><?php echo $overallStats['open_defects']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card total-defects">
                    <div class="gradient-layer">
                        <h3 class="card-title">Total Defects</h3>
                        <p class="card-text"><?php echo $overallStats['total_defects']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card pending-defects">
                    <div class="gradient-layer">
                        <h3 class="card-title">Pending Defects</h3>
                        <p class="card-text"><?php echo $overallStats['pending_defects']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <h2>Defects by Contractor</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Contractor</th>
                            <th>Total Defects</th>
                            <th>Open</th>
                            <th>Pending</th>
                            <th>Closed</th>
                            <th>Rejected</th>
                            <th>Last Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contractorStats as $contractor) : ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($contractor['company_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    <?php if ($contractor['logo']) : ?>
                                        <img src="<?php echo getBaseUrl() . '/uploads/logos/' . htmlspecialchars($contractor['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height: 30px; margin-left: 5px;">
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $contractor['total_defects']; ?></td>
                                <td><?php echo $contractor['open_defects']; ?></td>
                                <td><?php echo $contractor['pending_defects']; ?></td>
                                <td><?php echo $contractor['closed_defects']; ?></td>
                                <td><?php echo $contractor['rejected_defects']; ?></td>
                                <td><?php echo formatUKDate($contractor['last_update']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <h2>Recent Defects</h2>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Contractor</th>    
                            <th>Reported By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDefectsList as $defect) : ?>
    <tr>
        <td><?php echo htmlspecialchars($defect['id'], ENT_QUOTES, 'UTF-8'); ?></td>
        <td><span class="badge bg-<?php echo getStatusBadgeClass($defect['status']); ?>"><?php echo htmlspecialchars($defect['status'], ENT_QUOTES, 'UTF-8'); ?></span></td>
        <td><span class="badge bg-<?php echo getPriorityBadgeClass($defect['priority']); ?>"><?php echo htmlspecialchars($defect['priority'], ENT_QUOTES, 'UTF-8'); ?></span></td>
        <td><?php echo formatUKDateTime($defect['created_at']); ?></td>
        <td><?php echo formatUKDateTime($defect['updated_at']); ?></td>
        <td>
            <?php echo htmlspecialchars($defect['company_name'], ENT_QUOTES, 'UTF-8'); ?>
            <?php if ($defect['logo']) : ?>
                <img src="<?php echo getBaseUrl() . '/uploads/logos/' . htmlspecialchars($defect['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="Logo" style="max-height: 30px; margin-left: 5px;">
            <?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($defect['reported_by'], ENT_QUOTES, 'UTF-8'); ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</div>
</div>
</div>
<!-- Essential JavaScript Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips and popovers
    [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        .forEach(function(tooltipEl) { new bootstrap.Tooltip(tooltipEl); });
    [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        .forEach(function(popoverEl) { new bootstrap.Popover(popoverEl); });

    // Auto hide alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible:not(.debug-info)')
        .forEach(function(alert) {
            setTimeout(function() { new bootstrap.Alert(alert).close(); }, 5000);
        });

    // Set up periodic refresh
    setupAutoRefresh();

    // Initialize the UK time display
    updateTimes();
    setInterval(updateTimes, 1000);

    // Initialize any toggle functionality
    initializeToggles();
});

function setupAutoRefresh() {
    // Refresh dashboard data every 5 minutes
    setInterval(function() {
        fetch(window.location.href)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                // Update statistics cards
                doc.querySelectorAll('.stats-card').forEach((card, index) => {
                    const currentCard = document.querySelectorAll('.stats-card')[index];
                    if (currentCard) { currentCard.innerHTML = card.innerHTML; }
                });
                // Update tables
                doc.querySelectorAll('.table').forEach((table, index) => {
                    const currentTable = document.querySelectorAll('.table')[index];
                    if (currentTable) { currentTable.innerHTML = table.innerHTML; }
                });
            })
            .catch(error => console.error('Error refreshing data:', error));
    }, 300000); // 5 minutes
}

function updateTimes() {
    const ukOptions = {
        timeZone: 'Europe/London',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    };
    const now = new Date();
    const ukTimeString = now.toLocaleString('en-GB', ukOptions)
        .replace(',', '')
        .replace(/\//g, '-');
    document.getElementById('ukTime').textContent = ukTimeString;
}

function toggleDefectForm(defectId) {
    const formRow = document.getElementById('defectFormRow_' + defectId);
    if (formRow) {
        if (formRow.style.display === 'none') {
            formRow.style.display = 'table-row';
        } else {
            formRow.style.display = 'none';
        }
    }
}

function initializeToggles() {
    // Initialize any data toggles here
    // This function can be expanded for additional functionality
}

function showImageModal(imgSrc) {
    // Create a modal to display the image in full size
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'imageModal';
    modal.setAttribute('tabindex', '-1');
    modal.setAttribute('aria-hidden', 'true');
    
    modal.innerHTML = `
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Defect Image</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="${imgSrc}" class="img-fluid" alt="Defect Image">
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    const modalInstance = new bootstrap.Modal(modal);
    modalInstance.show();
    
    // Clean up when the modal is hidden
    modal.addEventListener('hidden.bs.modal', function () {
        document.body.removeChild(modal);
    });
}

// System information
const systemInfo = {
    currentTime: '2025-03-01 13:17:52', // Current UTC time
    currentUser: 'irlam', // Current user's login
    displaySystemInfo: function() {
        console.log(`Dashboard loaded by ${this.currentUser} at ${this.currentTime}`);
    }
};

// Initialize system info
systemInfo.displaySystemInfo();
</script>
</body>
</html>