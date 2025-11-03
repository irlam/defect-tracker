<?php
require_once 'config/database.php';
require_once 'classes/Auth.php';
require_once 'classes/Logger.php';
require_once 'config/config.php';

try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    error_log('Notifications DB connection error: ' . $e->getMessage());
    die('Unable to connect to the database.');
}

$auth = new Auth($db);
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notificationId = (int)$_POST['notification_id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } elseif ($_POST['action'] === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }
}

// Get notifications with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$totalStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$totalStmt->execute([$userId]);
$totalNotifications = $totalStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications
$stmt = $db->prepare("
    SELECT n.*, u.username as created_by_username
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$userId, $perPage, $offset]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unreadStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadStmt->execute([$userId]);
$unreadCount = $unreadStmt->fetchColumn();

include 'includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Notifications</h1>
                    <p class="text-muted">Stay updated with your defect tracking activities</p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($unreadCount > 0): ?>
                        <button id="markAllReadBtn" class="btn btn-outline-primary">
                            <i class="fas fa-check-double"></i> Mark All Read
                        </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i> Back
                    </button>
                </div>
            </div>

            <?php if ($unreadCount > 0): ?>
                <div class="alert alert-info d-flex align-items-center">
                    <i class="fas fa-bell me-2"></i>
                    <span>You have <strong><?php echo $unreadCount; ?></strong> unread notification<?php echo $unreadCount !== 1 ? 's' : ''; ?></span>
                </div>
            <?php endif; ?>

            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bell-slash fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No notifications yet</h4>
                    <p class="text-muted">You'll see notifications here when defects are assigned to you or other activities occur.</p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="list-group-item notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>"
                                     data-notification-id="<?php echo $notification['id']; ?>">
                                    <div class="d-flex w-100 justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
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
                                                <i class="<?php echo $iconClass; ?> me-2"></i>
                                                <h6 class="mb-0 fw-bold">
                                                    <?php echo htmlspecialchars($notification['type']); ?>
                                                </h6>
                                                <?php if (!$notification['is_read']): ?>
                                                    <span class="badge bg-primary ms-2">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="mb-1 text-dark"><?php echo htmlspecialchars($notification['message']); ?></p>
                                            <div class="d-flex align-items-center text-muted small">
                                                <i class="fas fa-clock me-1"></i>
                                                <span><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                                <?php if ($notification['link_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($notification['link_url']); ?>"
                                                       class="ms-auto btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-external-link-alt"></i> View
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if (!$notification['is_read']): ?>
                                            <button class="btn btn-sm btn-outline-secondary ms-2 mark-read-btn"
                                                    data-notification-id="<?php echo $notification['id']; ?>">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Notification pagination" class="mt-4">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=1">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a>
                                </li>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.notification-item {
    transition: background-color 0.2s ease;
    border-left: 4px solid transparent;
}

.notification-item.unread {
    background-color: #f8f9ff;
    border-left-color: #0d6efd;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

@media (max-width: 768px) {
    .container-fluid {
        padding-left: 15px;
        padding-right: 15px;
    }

    .notification-item {
        padding: 1rem 0.75rem;
    }

    .btn {
        padding: 0.375rem 0.75rem;
        font-size: 0.875rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mark individual notification as read
    document.querySelectorAll('.mark-read-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const notificationId = this.dataset.notificationId;
            const notificationItem = this.closest('.notification-item');

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_read&notification_id=' + notificationId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    notificationItem.classList.remove('unread');
                    this.remove();
                    updateUnreadBadge();
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });

    // Mark all notifications as read
    const markAllReadBtn = document.getElementById('markAllReadBtn');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function() {
            if (!confirm('Mark all notifications as read?')) return;

            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=mark_all_read'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        const markBtn = item.querySelector('.mark-read-btn');
                        if (markBtn) markBtn.remove();
                    });
                    this.style.display = 'none';
                    updateUnreadBadge();
                }
            })
            .catch(error => console.error('Error:', error));
        });
    }

    function updateUnreadBadge() {
        // Update the notification badge in the header if it exists
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            const currentCount = parseInt(badge.textContent) || 0;
            if (currentCount > 0) {
                badge.textContent = Math.max(0, currentCount - 1);
                if (currentCount - 1 <= 0) {
                    badge.style.display = 'none';
                }
            }
        }
    }

    // Set up Server-Sent Events for real-time notifications
    if (typeof(EventSource) !== "undefined") {
        const eventSource = new EventSource('api/notification_stream.php');

        eventSource.onmessage = function(event) {
            console.log('SSE message received:', event.data);
        };

        eventSource.addEventListener('connected', function(event) {
            console.log('Connected to notification stream');
        });

        eventSource.addEventListener('notification', function(event) {
            const notification = JSON.parse(event.data);
            console.log('New notification:', notification);

            // Show a toast notification
            showToastNotification(notification);

            // Update unread count
            updateUnreadCount();
        });

        eventSource.addEventListener('unread_count', function(event) {
            const data = JSON.parse(event.data);
            updateNotificationBadge(data.count);
        });

        eventSource.addEventListener('error', function(event) {
            console.error('SSE Error:', event);
            // Reconnect after a delay
            setTimeout(() => {
                location.reload();
            }, 5000);
        });
    } else {
        console.warn('EventSource not supported by this browser');
    }

    function showToastNotification(notification) {
        // Create toast notification element
        const toastHtml = `
            <div class="toast align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        <strong>${notification.type}</strong><br>
                        ${notification.message}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;

        // Add to toast container
        let toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
            toastContainer.style.zIndex = '9999';
            document.body.appendChild(toastContainer);
        }

        toastContainer.insertAdjacentHTML('beforeend', toastHtml);

        // Initialize and show the toast
        const toastElement = toastContainer.lastElementChild;
        const toast = new bootstrap.Toast(toastElement, {
            autohide: true,
            delay: 5000
        });
        toast.show();

        // Remove from DOM after hiding
        toastElement.addEventListener('hidden.bs.toast', function() {
            this.remove();
        });
    }

    function updateUnreadCount() {
        // Refresh the page to show new notifications
        // In a production app, you'd update the list dynamically
        setTimeout(() => {
            location.reload();
        }, 1000);
    }

    function updateNotificationBadge(count) {
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>