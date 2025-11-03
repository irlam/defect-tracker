<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/classes/Auth.php';
require_once __DIR__ . '/includes/navbar.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$username = $_SESSION['username'];
$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $username));
$currentTimestamp = date('d/m/Y H:i');

try {
    $navbar = new Navbar($db, $userId, $username);
} catch (Throwable $navbarException) {
    error_log('Navbar initialisation failed on notifications.php: ' . $navbarException->getMessage());
    $navbar = null;
}

// Handle mark as read action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        $notificationId = (int)$_POST['notification_id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE id = :id AND user_id = :user_id");
        $stmt->bindValue(':id', $notificationId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    } elseif ($_POST['action'] === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1, updated_at = NOW() WHERE user_id = :user_id AND is_read = 0");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
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
$totalStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id");
$totalStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$totalStmt->execute();
$totalNotifications = $totalStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $perPage);

// Get notifications
$stmt = $db->prepare("
    SELECT n.*, u.username as created_by_username
    FROM notifications n
    LEFT JOIN users u ON n.user_id = u.id
    WHERE n.user_id = :user_id
    ORDER BY n.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread count
$unreadStmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
$unreadStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$unreadStmt->execute();
$unreadCount = $unreadStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Centre - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    <style>
        .notifications-page {
            color: rgba(226, 232, 240, 0.92);
        }

        .notifications-hero {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.95), rgba(37, 99, 235, 0.85));
            border-radius: 1.4rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 2rem;
            box-shadow: 0 28px 48px -22px rgba(15, 23, 42, 0.7);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1.5rem;
        }

        .notifications-hero__meta {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            color: rgba(191, 219, 254, 0.85);
            font-size: 0.9rem;
        }

        .notifications-card {
            background: rgba(15, 23, 42, 0.88);
            border-radius: 1.2rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 40px -24px rgba(15, 23, 42, 0.75);
        }

        .notifications-card .list-group-item {
            background: transparent;
            border-color: rgba(148, 163, 184, 0.12);
            padding: 1.35rem 1.75rem;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .notifications-card .list-group-item:hover {
            background: rgba(37, 99, 235, 0.12);
            transform: translateY(-2px);
        }

        .notification-item.unread {
            border-left: 4px solid rgba(59, 130, 246, 0.85);
            background: rgba(37, 99, 235, 0.08);
        }

        .notification-empty {
            border-radius: 1.4rem;
            border: 1px dashed rgba(148, 163, 184, 0.35);
            padding: 3rem 1rem;
            background: rgba(15, 23, 42, 0.65);
        }

        .badge-soft-primary {
            background: rgba(59, 130, 246, 0.18);
            color: rgba(191, 219, 254, 0.95);
            border-radius: 999px;
            padding: 0.35rem 0.9rem;
            font-size: 0.8rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        @media (max-width: 768px) {
            .notifications-hero {
                padding: 1.6rem;
            }

            .notifications-card .list-group-item {
                padding: 1.15rem 1.1rem;
            }
        }
    </style>
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>

    <main class="notifications-page container-xl py-4">
        <section class="notifications-hero mb-4">
            <div>
                <span class="badge-soft-primary mb-2"><i class='bx bx-bell me-1'></i>Notification Centre</span>
                <h1 class="h3 mb-2">Real-time project intelligence</h1>
                <p class="text-muted mb-0">Keep track of defect movements, assignments, and shipment updates across your portfolio.</p>
            </div>
            <div class="notifications-hero__meta">
                <span><i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
                <span><i class='bx bx-bell-plus me-1'></i><?php echo number_format((int) $unreadCount); ?> unread</span>
            </div>
        </section>

        <section class="d-flex justify-content-between flex-wrap gap-3 mb-4">
            <div class="d-flex gap-2">
                <a class="btn btn-outline-light btn-sm" href="dashboard.php"><i class='bx bx-left-arrow-alt me-1'></i>Back to dashboard</a>
                <a class="btn btn-outline-light btn-sm" href="defects.php"><i class='bx bx-bug me-1'></i>Defect control room</a>
            </div>
            <?php if ($unreadCount > 0): ?>
                <button id="markAllReadBtn" class="btn btn-primary btn-sm" data-loading="true">
                    <i class="fas fa-check-double me-1"></i>Mark all read
                </button>
            <?php endif; ?>
        </section>

        <?php if ($unreadCount > 0): ?>
            <div class="alert alert-info alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-bell me-2"></i>You have <strong><?php echo number_format((int) $unreadCount); ?></strong> unread notification<?php echo $unreadCount !== 1 ? 's' : ''; ?>.
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($notifications)): ?>
            <div class="notification-empty text-center text-muted">
                <i class="fas fa-bell-slash fa-3x mb-3"></i>
                <h4 class="mb-2">No notifications yet</h4>
                <p class="mb-0">You will receive updates here as defects are assigned or lifecycle events occur.</p>
            </div>
        <?php else: ?>
            <section class="notifications-card mb-4">
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
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
                        <div class="list-group-item notification-item d-flex flex-column flex-md-row gap-3 align-items-start align-items-md-center <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" data-notification-id="<?php echo (int) $notification['id']; ?>">
                            <div class="d-flex align-items-center gap-2 flex-grow-1">
                                <span class="rounded-circle bg-dark-subtle text-primary d-inline-flex align-items-center justify-content-center" style="width:42px;height:42px;">
                                    <i class="<?php echo $iconClass; ?>"></i>
                                </span>
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="mb-0 fw-semibold text-capitalize"><?php echo htmlspecialchars(str_replace('_', ' ', $notification['type'])); ?></h6>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mb-1 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <div class="small text-muted d-flex gap-3 flex-wrap">
                                        <span><i class="fas fa-clock me-1"></i><?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                        <?php if (!empty($notification['link_url'])): ?>
                                            <a href="<?php echo htmlspecialchars($notification['link_url']); ?>" class="text-decoration-none">
                                                <i class="fas fa-external-link-alt me-1"></i>Open update
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php if (!$notification['is_read']): ?>
                                <button class="btn btn-outline-light btn-sm mark-read-btn" data-notification-id="<?php echo (int) $notification['id']; ?>">
                                    <i class="fas fa-check me-1"></i>Mark read
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($totalPages > 1): ?>
                <nav aria-label="Notification pagination" class="mt-4">
                    <ul class="pagination justify-content-center pagination-dark">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                        ?>

                        <?php if ($startPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?page=1">1</a></li>
                            <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($endPage < $totalPages): ?>
                            <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="?page=<?php echo $totalPages; ?>"><?php echo $totalPages; ?></a></li>
                        <?php endif; ?>

                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
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
</body>
</html>