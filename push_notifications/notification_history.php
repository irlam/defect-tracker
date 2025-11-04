<?php
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Use absolute path for includes
$root_path = $_SERVER['DOCUMENT_ROOT'];
require_once $root_path . '/config/database.php';
require_once $root_path . '/includes/navbar.php';

// Get filter parameters
$filterStatus = $_GET['status'] ?? '';
$filterDateFrom = $_GET['date_from'] ?? '';
$filterDateTo = $_GET['date_to'] ?? '';
$filterTarget = $_GET['target'] ?? '';

// Database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Build query with filters
    $sql = "SELECT 
                nl.id,
                nl.title,
                nl.message,
                nl.target_type,
                nl.user_id,
                nl.contractor_id,
                nl.defect_id,
                nl.success_count,
                nl.failed_count,
                nl.delivery_status,
                nl.sent_at,
                nl.delivery_confirmed_at,
                nl.error_message,
                u.username as target_user,
                c.company_name as target_contractor,
                d.title as defect_title
            FROM notification_log nl
            LEFT JOIN users u ON nl.user_id = u.id
            LEFT JOIN contractors c ON nl.contractor_id = c.id
            LEFT JOIN defects d ON nl.defect_id = d.id
            WHERE 1=1";
    
    $params = [];
    
    if (!empty($filterStatus)) {
        $sql .= " AND nl.delivery_status = ?";
        $params[] = $filterStatus;
    }
    
    if (!empty($filterDateFrom)) {
        $sql .= " AND nl.sent_at >= ?";
        $params[] = $filterDateFrom . ' 00:00:00';
    }
    
    if (!empty($filterDateTo)) {
        $sql .= " AND nl.sent_at <= ?";
        $params[] = $filterDateTo . ' 23:59:59';
    }
    
    if (!empty($filterTarget)) {
        $sql .= " AND nl.target_type = ?";
        $params[] = $filterTarget;
    }
    
    $sql .= " ORDER BY nl.sent_at DESC LIMIT 100";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = 'Database error: ' . $e->getMessage();
}

date_default_timezone_set('Europe/London');
$currentDate = date('d-m-Y H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification History - DefectTracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/styles.css">
    <style>
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
            font-weight: 500;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-sent { background-color: #dbeafe; color: #1e40af; }
        .status-delivered { background-color: #d1fae5; color: #065f46; }
        .status-failed { background-color: #fee2e2; color: #991b1b; }
        
        .notification-card {
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: white;
        }
        
        .notification-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .filter-section {
            background-color: #f9fafb;
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
<?php
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
$navbar->render();
?>
    <br><br><br><br>
    <div class="container">
        <header class="mb-4">
            <h1><i class="fa fa-history"></i> Notification History</h1>
            <p class="current-time">Current time: <?php echo $currentDate; ?></p>
            <p class="user-info">Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <!-- Filters -->
        <div class="filter-section">
            <h5><i class="fa fa-filter"></i> Filters</h5>
            <form method="get" action="" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Delivery Status</label>
                    <select name="status" id="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="sent" <?php echo $filterStatus === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="delivered" <?php echo $filterStatus === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="target" class="form-label">Target Type</label>
                    <select name="target" id="target" class="form-control">
                        <option value="">All Targets</option>
                        <option value="all" <?php echo $filterTarget === 'all' ? 'selected' : ''; ?>>All Users & Contractors</option>
                        <option value="all_users" <?php echo $filterTarget === 'all_users' ? 'selected' : ''; ?>>All Users</option>
                        <option value="all_contractors" <?php echo $filterTarget === 'all_contractors' ? 'selected' : ''; ?>>All Contractors</option>
                        <option value="user" <?php echo $filterTarget === 'user' ? 'selected' : ''; ?>>Specific User</option>
                        <option value="contractor" <?php echo $filterTarget === 'contractor' ? 'selected' : ''; ?>>Specific Contractor</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Date From</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Date To</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" value="<?php echo htmlspecialchars($filterDateTo); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                    <a href="notification_history.php" class="btn btn-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Action Buttons -->
        <div class="mb-4">
            <a href="/push_notifications/" class="btn btn-success"><i class="fa fa-paper-plane"></i> Send New Notification</a>
            <a href="/dashboard.php" class="btn btn-secondary"><i class="fa fa-home"></i> Back to Dashboard</a>
        </div>
        
        <!-- Notification List -->
        <div class="notification-list">
            <h4 class="mb-3">Recent Notifications (<?php echo count($notifications); ?>)</h4>
            
            <?php if (empty($notifications)): ?>
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> No notifications found matching your criteria.
                </div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <div class="notification-card">
                        <div class="row">
                            <div class="col-md-8">
                                <h5><?php echo htmlspecialchars($notification['title']); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                
                                <div class="small text-muted">
                                    <i class="fa fa-clock"></i> Sent: <?php echo date('d/m/Y H:i', strtotime($notification['sent_at'])); ?>
                                    
                                    <?php if ($notification['target_user']): ?>
                                        | <i class="fa fa-user"></i> User: <?php echo htmlspecialchars($notification['target_user']); ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['target_contractor']): ?>
                                        | <i class="fa fa-building"></i> Contractor: <?php echo htmlspecialchars($notification['target_contractor']); ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['defect_title']): ?>
                                        | <i class="fa fa-bug"></i> Defect: #<?php echo htmlspecialchars($notification['defect_id']); ?> - <?php echo htmlspecialchars($notification['defect_title']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <span class="status-badge status-<?php echo htmlspecialchars($notification['delivery_status']); ?>">
                                    <?php echo ucfirst($notification['delivery_status']); ?>
                                </span>
                                
                                <div class="mt-2 small">
                                    <div class="text-success">
                                        <i class="fa fa-check-circle"></i> Delivered: <?php echo $notification['success_count']; ?>
                                    </div>
                                    <?php if ($notification['failed_count'] > 0): ?>
                                        <div class="text-danger">
                                            <i class="fa fa-times-circle"></i> Failed: <?php echo $notification['failed_count']; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($notification['delivery_confirmed_at']): ?>
                                        <div class="text-muted">
                                            <i class="fa fa-check-double"></i> Confirmed: <?php echo date('H:i', strtotime($notification['delivery_confirmed_at'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($notification['error_message']): ?>
                                    <div class="mt-2 small text-danger">
                                        <i class="fa fa-exclamation-triangle"></i> <?php echo htmlspecialchars(substr($notification['error_message'], 0, 50)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <footer class="mt-5 text-center">
            <p>DefectTracker Notification System &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
