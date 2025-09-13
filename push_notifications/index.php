<?php
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    // Redirect non-authorized users to login page
    header('Location: ../login.php');
    exit;
}

// Use absolute path for includes to prevent "Class not found" errors
$root_path = $_SERVER['DOCUMENT_ROOT'];
require_once $root_path . '/config/database.php';
require_once $root_path . '/includes/navbar.php';
require_once 'notification_sender.php';

// Process form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $body = $_POST['message'] ?? '';
    $targetType = $_POST['target_type'] ?? 'all';
    $defectId = !empty($_POST['defect_id']) ? $_POST['defect_id'] : null;
    $userId = ($targetType === 'user') ? $_POST['user_id'] : null;
    
    if (empty($title) || empty($body)) {
        $message = '<div class="alert alert-danger">Title and message are required!</div>';
    } else {
        $result = sendNotification($title, $body, $targetType, $userId, $defectId);
        if ($result['success']) {
            $message = '<div class="alert alert-success">Notification sent successfully to ' . $result['recipients'] . ' recipients!</div>';
        } else {
            $message = '<div class="alert alert-danger">Error: ' . $result['error'] . '</div>';
        }
    }
}

// Database connection
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get users for dropdown - showing all users regardless of FCM token
    $users = [];
    $stmt = $db->prepare("SELECT id, username, first_name, last_name FROM users ORDER BY username");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get defects for dropdown
    $defects = [];
    $stmt = $db->prepare("SELECT id, title FROM defects ORDER BY id DESC LIMIT 100");
    $stmt->execute();
    $defects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $message = '<div class="alert alert-danger">Database error: ' . $e->getMessage() . '</div>';
}

// Current date for display - use correct timezone
date_default_timezone_set('Europe/London'); // Or your preferred timezone
$currentDate = date('d-m-Y H:i:s');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Push Notifications - DefectTracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <!-- Essential CSS Dependencies (using CDN only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Include any custom CSS with absolute path -->
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
<?php
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
$navbar->render();
?>
    <br><br><br><br>
    <div class="container">
        <header>
            <h1>Push Notifications</h1>
            <p class="current-time">Current time: <?php echo $currentDate; ?></p>
            <p class="user-info">Logged in as: <?php echo htmlspecialchars($_SESSION['username']); ?></p>
        </header>
        
        <div class="alert alert-info">
            <h4><i class="fa fa-info-circle"></i> About Push Notifications</h4>
            <p>This tool allows you to send instant notifications to users of the Defect Tracker system. These notifications 
            will appear on users' devices in real-time, helping to keep everyone informed about important updates.</p>
            <p><strong>Features:</strong></p>
            <ul>
                <li>Send notifications to all users or target a specific individual</li>
                <li>Link notifications to specific defects for easy reference</li>
                <li>Instantly notify team members about critical updates or required actions</li>
            </ul>
            <p>All sent notifications are logged in the system for future reference.</p>
        </div>
        
        <?php echo $message; ?>
        
        <form method="post" action="">
            <div class="form-group mb-3">
                <label for="title">Notification Title:</label>
                <input type="text" id="title" name="title" class="form-control" required>
            </div>
            
            <div class="form-group mb-3">
                <label for="message">Notification Message:</label>
                <textarea id="message" name="message" class="form-control" rows="4" required></textarea>
            </div>
            
            <div class="form-group mb-3">
                <label>Send to:</label>
                <div class="radio-group">
                    <label class="me-3">
                        <input type="radio" name="target_type" value="all" checked onchange="toggleUserSelect()"> 
                        All Users
                    </label>
                    <label>
                        <input type="radio" name="target_type" value="user" onchange="toggleUserSelect()"> 
                        Specific User
                    </label>
                </div>
            </div>
            
            <div class="form-group mb-3" id="userSelectContainer" style="display: none;">
                <label for="user_id">Select User:</label>
                <select id="user_id" name="user_id" class="form-control">
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo htmlspecialchars($user['id']); ?>">
                        <?php echo htmlspecialchars(($user['first_name'] . ' ' . $user['last_name']) . ' (' . $user['username'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group mb-4">
                <label for="defect_id">Link to Defect (Optional):</label>
                <select id="defect_id" name="defect_id" class="form-control">
                    <option value="">-- None --</option>
                    <?php foreach ($defects as $defect): ?>
                    <option value="<?php echo htmlspecialchars($defect['id']); ?>">
                        #<?php echo htmlspecialchars($defect['id'] . ' - ' . $defect['title']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions mb-4">
                <button type="submit" class="btn btn-primary">Send Notification</button>
                <a href="/dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>
        </form>
        
        <footer class="mt-5 text-center">
            <p>DefectTracker Push Notifications System &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>
    
    <script>
        function toggleUserSelect() {
            const targetType = document.querySelector('input[name="target_type"]:checked').value;
            const userSelectContainer = document.getElementById('userSelectContainer');
            
            if (targetType === 'user') {
                userSelectContainer.style.display = 'block';
            } else {
                userSelectContainer.style.display = 'none';
            }
        }
    </script>
    
    <!-- Include Bootstrap JS to ensure the dropdown menu works -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>