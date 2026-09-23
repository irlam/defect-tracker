<?php
/**
 * Sync Admin Dashboard
 *
 * Provides administrators with visibility into the synchronisation queue along with
 * tooling to retry failed items, clear processed records, and resolve data conflicts.
 * This themed version aligns with the main site styling and reuses the global navbar.
 */

require_once __DIR__ . '/../init.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';
require_once dirname(__DIR__, 2) . '/includes/navbar.php';
require_once dirname(__DIR__, 2) . '/includes/FieldSyncTelemetry.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $config = include __DIR__ . '/../config.php';
    $db = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']}",
        $config['db_user'],
        $config['db_pass']
    );
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Database connection failed: ' . $e->getMessage());
}

date_default_timezone_set('Europe/London');

function checkAdminAccess(PDO $db, int $userId): bool
{
    $stmt = $db->prepare('SELECT role FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && ($user['role'] ?? '') === 'admin') {
        return true;
    }

    $stmt = $db->prepare('
        SELECT r.name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = ?
    ');
    $stmt->execute([$userId]);
    $role = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($role && ($role['name'] ?? '') === 'admin') {
        return true;
    }

    $stmt = $db->prepare('
        SELECT COUNT(*) AS has_permission
        FROM user_permissions up
        JOIN permissions p ON up.permission_id = p.id
        WHERE up.user_id = ? AND p.permission_key = ?
    ');
    $stmt->execute([$userId, 'manage_sync']);
    $permission = $stmt->fetch(PDO::FETCH_ASSOC);

    if (($permission['has_permission'] ?? 0) > 0) {
        return true;
    }

    $stmt = $db->prepare('
        SELECT COUNT(*) AS has_permission
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = ? AND p.permission_key = ?
    ');
    $stmt->execute([$userId, 'manage_sync']);
    $permissionViaRole = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($permissionViaRole['has_permission'] ?? 0) > 0;
}

$currentUrl = $_SERVER['REQUEST_URI'] ?? '/sync/admin/dashboard.php';

if (!isset($_SESSION['user_id'])) {
    $stmt = $db->prepare('INSERT INTO system_logs (action, details, action_at) VALUES (?, ?, ?)');
    $stmt->execute([
        'UNAUTHORIZED_ACCESS',
        'Attempted access to sync dashboard without login',
        date('Y-m-d H:i:s')
    ]);

    header('Location: /login.php?redirect=' . urlencode($currentUrl));
    exit;
}

$userId = (int) $_SESSION['user_id'];

if (!checkAdminAccess($db, $userId)) {
    $stmt = $db->prepare('
        INSERT INTO system_logs (user_id, action, action_by, action_at, details)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $userId,
        'ACCESS_DENIED',
        $userId,
        date('Y-m-d H:i:s'),
        'Insufficient permissions for sync dashboard'
    ]);

    header('Location: /unauthorized.php');
    exit;
}

$stmt = $db->prepare('
    INSERT INTO system_logs (user_id, action, action_by, action_at, ip_address, details)
    VALUES (?, ?, ?, ?, ?, ?)
');
$stmt->execute([
    $userId,
    'SYNC_DASHBOARD_ACCESS',
    $userId,
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'Successfully accessed sync dashboard'
]);

$stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
$username = $currentUser['username'] ?? 'unknown';

$pageTitle = 'Field Sync Console';
$navbar = null;

try {
    $navbar = new Navbar($db, $userId, $_SESSION['username'] ?? $username);
} catch (Throwable $navbarError) {
    error_log('Navbar initialisation error on sync admin dashboard: ' . $navbarError->getMessage());
}

if (!isset($_SESSION['sync_dashboard_csrf'])) {
    $_SESSION['sync_dashboard_csrf'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['sync_dashboard_csrf'];

$stats = [
    'pending_items' => 0,
    'failed_items' => 0,
    'completed_items' => 0,
    'total_syncs' => 0,
    'last_sync' => 'Never',
    'conflicts' => 0,
    'device_pending' => 0,
    'device_failed' => 0,
    'uploads_today' => 0,
    'field_outcomes' => 0,
    'active_devices' => 0,
    'last_field_sync' => null,
];

$fieldDevices = [];
$recentFieldEvents = [];
$fieldTelemetryError = '';

try {
    FieldSyncTelemetry::ensureSchema($db);

    $stmt = $db->query('
        SELECT
            COALESCE(SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN pending_count ELSE 0 END), 0) AS pending,
            COALESCE(SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN failed_count ELSE 0 END), 0) AS failed,
            SUM(CASE WHEN last_seen >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS active_devices,
            MAX(last_sync_at) AS last_sync
        FROM field_sync_devices
    ');
    $deviceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['device_pending'] = (int) ($deviceStats['pending'] ?? 0);
    $stats['device_failed'] = (int) ($deviceStats['failed'] ?? 0);
    $stats['active_devices'] = (int) ($deviceStats['active_devices'] ?? 0);
    $stats['last_field_sync'] = $deviceStats['last_sync'] ?? null;

    $stmt = $db->query('
        SELECT
            SUM(CASE WHEN status = "success" AND created_at >= CURDATE() THEN 1 ELSE 0 END) AS uploads_today,
            COUNT(*) AS outcomes
        FROM field_sync_events
        WHERE event_type IN ("upload_completed", "duplicate_confirmed", "upload_failed")
    ');
    $eventStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['uploads_today'] = (int) ($eventStats['uploads_today'] ?? 0);
    $stats['field_outcomes'] = (int) ($eventStats['outcomes'] ?? 0);

    $stmt = $db->query('
        SELECT device_id, username, pending_count, failed_count, syncing_count,
               oldest_pending_at, last_seen, last_sync_at, last_status, last_error
        FROM field_sync_devices
        ORDER BY last_seen DESC
        LIMIT 20
    ');
    $fieldDevices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $db->query('
        SELECT device_id, username, event_type, status, defect_id, message, created_at
        FROM field_sync_events
        ORDER BY created_at DESC
        LIMIT 20
    ');
    $recentFieldEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $fieldError) {
    $fieldTelemetryError = 'Field telemetry could not be loaded. Check that the database account can create and read the telemetry tables.';
    error_log('Field sync telemetry dashboard error: ' . $fieldError->getMessage());
}

try {
    $stmt = $db->query('
        SELECT
            SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed,
            SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) AS completed,
            COUNT(*) AS total
        FROM sync_queue
    ');
    $queueStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stats['pending_items'] = (int) ($queueStats['pending'] ?? 0);
    $stats['failed_items'] = (int) ($queueStats['failed'] ?? 0);
    $stats['completed_items'] = (int) ($queueStats['completed'] ?? 0);

    $stmt = $db->query('SELECT COUNT(*) AS total, MAX(end_time) AS last_sync FROM sync_logs');
    $logStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $stats['total_syncs'] = (int) ($logStats['total'] ?? 0);
    $stats['last_sync'] = $logStats['last_sync'] ?? 'Never';

    $stmt = $db->query('SELECT COUNT(*) AS count FROM sync_conflicts WHERE resolved = 0');
    $conflicts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $stats['conflicts'] = (int) ($conflicts['count'] ?? 0);
} catch (PDOException $e) {
    error_log('Error retrieving sync statistics: ' . $e->getMessage());
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!is_string($submittedToken) || !hash_equals($csrfToken, $submittedToken)) {
        $message = 'Security check failed. Please refresh and try again.';
        $messageType = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'clear_failed':
                try {
                    $db->exec("DELETE FROM sync_queue WHERE status = 'failed'");
                    $message = 'Failed items cleared successfully.';
                    $messageType = 'success';

                    $stmt = $db->prepare('
                        INSERT INTO system_logs (user_id, action, action_by, action_at, details)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $userId,
                        'CLEAR_FAILED_SYNC',
                        $userId,
                        date('Y-m-d H:i:s'),
                        'Cleared failed sync items'
                    ]);
                } catch (PDOException $e) {
                    $message = 'Error clearing failed items: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'retry_failed':
                try {
                    $stmt = $db->prepare("UPDATE sync_queue SET status = 'pending', attempts = attempts + 1 WHERE status = 'failed'");
                    $stmt->execute();
                    $affected = $stmt->rowCount();

                    $message = $affected . ' failed items queued for retry.';
                    $messageType = 'success';

                    $stmt = $db->prepare('
                        INSERT INTO system_logs (user_id, action, action_by, action_at, details)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $userId,
                        'RETRY_FAILED_SYNC',
                        $userId,
                        date('Y-m-d H:i:s'),
                        'Retried ' . $affected . ' failed sync items'
                    ]);
                } catch (PDOException $e) {
                    $message = 'Error retrying failed items: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'retry_single':
                if (isset($_POST['item_id'])) {
                    try {
                        $itemId = (int) $_POST['item_id'];
                        $stmt = $db->prepare('UPDATE sync_queue SET status = "pending", attempts = attempts + 1 WHERE id = ?');
                        $stmt->execute([$itemId]);

                        $message = 'Item queued for retry.';
                        $messageType = 'success';

                        $stmt = $db->prepare('
                            INSERT INTO system_logs (user_id, action, action_by, action_at, details)
                            VALUES (?, ?, ?, ?, ?)
                        ');
                        $stmt->execute([
                            $userId,
                            'RETRY_SINGLE_SYNC',
                            $userId,
                            date('Y-m-d H:i:s'),
                            'Retried sync item ID: ' . $itemId
                        ]);
                    } catch (PDOException $e) {
                        $message = 'Error retrying item: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;

            case 'resolve_conflicts':
                $resolution = $_POST['resolution'] ?? 'server_wins';

                try {
                    $stmt = $db->prepare('
                        UPDATE sync_conflicts
                        SET resolved = 1,
                            resolution_type = ?,
                            resolved_by = ?,
                            resolved_at = ?
                        WHERE resolved = 0
                    ');
                    $stmt->execute([$resolution, $username, date('Y-m-d H:i:s')]);
                    $resolvedCount = $stmt->rowCount();

                    $stmt = $db->prepare('
                        UPDATE sync_queue sq
                        JOIN sync_conflicts sc ON sq.id = sc.sync_queue_id
                        SET sq.status = "pending", sq.force_sync = 1
                        WHERE sc.resolved = 1 AND sc.resolution_type = ?
                    ');
                    $stmt->execute([$resolution]);
                    $itemsRequeued = $stmt->rowCount();

                    $message = $resolvedCount . ' conflicts resolved using ' . $resolution . '. ' . $itemsRequeued . ' items requeued for sync.';
                    $messageType = 'success';

                    $stmt = $db->prepare('
                        INSERT INTO system_logs (user_id, action, action_by, action_at, details)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $userId,
                        'RESOLVE_CONFLICTS',
                        $userId,
                        date('Y-m-d H:i:s'),
                        'Resolved ' . $resolvedCount . ' conflicts using ' . $resolution . ' strategy'
                    ]);
                } catch (PDOException $e) {
                    $message = 'Error resolving conflicts: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'clear_completed':
                try {
                    $stmt = $db->prepare('
                        DELETE FROM sync_queue
                        WHERE status = "completed" AND processed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
                    ');
                    $stmt->execute();
                    $cleared = $stmt->rowCount();

                    $message = 'Cleared ' . $cleared . ' completed sync items older than 7 days.';
                    $messageType = 'success';

                    $stmt = $db->prepare('
                        INSERT INTO system_logs (user_id, action, action_by, action_at, details)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $userId,
                        'CLEAR_COMPLETED_SYNC',
                        $userId,
                        date('Y-m-d H:i:s'),
                        'Cleared ' . $cleared . ' completed sync items'
                    ]);
                } catch (PDOException $e) {
                    $message = 'Error clearing completed items: ' . $e->getMessage();
                    $messageType = 'error';
                }
                break;

            case 'resolve_single_conflict':
                if (isset($_POST['conflict_id'], $_POST['resolution'])) {
                    $conflictId = (int) $_POST['conflict_id'];
                    $resolution = $_POST['resolution'];

                    try {
                        $stmt = $db->prepare('
                            UPDATE sync_conflicts
                            SET resolved = 1,
                                resolution_type = ?,
                                resolved_by = ?,
                                resolved_at = ?
                            WHERE id = ?
                        ');
                        $stmt->execute([$resolution, $username, date('Y-m-d H:i:s'), $conflictId]);

                        if ($stmt->rowCount() > 0) {
                            $stmt = $db->prepare('
                                UPDATE sync_queue sq
                                JOIN sync_conflicts sc ON sq.id = sc.sync_queue_id
                                SET sq.status = "pending", sq.force_sync = 1
                                WHERE sc.id = ?
                            ');
                            $stmt->execute([$conflictId]);
                            $itemsRequeued = $stmt->rowCount();

                            $message = 'Conflict #' . $conflictId . ' resolved using ' . $resolution . '. ' . $itemsRequeued . " item(s) requeued.";
                            $messageType = 'success';

                            $stmt = $db->prepare('
                                INSERT INTO system_logs (user_id, action, action_by, action_at, details)
                                VALUES (?, ?, ?, ?, ?)
                            ');
                            $stmt->execute([
                                $userId,
                                'RESOLVE_SINGLE_CONFLICT',
                                $userId,
                                date('Y-m-d H:i:s'),
                                'Resolved conflict ' . $conflictId . ' using ' . $resolution . ' strategy'
                            ]);
                        } else {
                            $message = 'Conflict not found or already resolved.';
                            $messageType = 'error';
                        }
                    } catch (PDOException $e) {
                        $message = 'Error resolving conflict: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

$recentLogs = [];
try {
    $stmt = $db->query('SELECT * FROM sync_logs ORDER BY end_time DESC LIMIT 10');
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error retrieving sync logs: ' . $e->getMessage());
}

$recentErrors = [];
try {
    $stmt = $db->query('
        SELECT id, username, action, entity_type, entity_id, created_at, updated_at, attempts, result, status
        FROM sync_queue
        WHERE status = "failed"
        ORDER BY updated_at DESC
        LIMIT 10
    ');
    $recentErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($recentErrors as &$error) {
        if (!empty($error['result'])) {
            $decoded = json_decode($error['result'], true);
            $error['result_message'] = is_array($decoded)
                ? ($decoded['message'] ?? json_encode($decoded))
                : $error['result'];
        } else {
            $error['result_message'] = 'No result data available';
        }
    }
    unset($error);
} catch (PDOException $e) {
    error_log('Error retrieving failed sync items: ' . $e->getMessage());
}

$conflicts = [];
try {
    $stmt = $db->query('SELECT * FROM sync_conflicts WHERE resolved = 0 ORDER BY created_at DESC LIMIT 10');
    $conflicts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Error retrieving conflicts: ' . $e->getMessage());
}

$refreshInterval = 60;
$currentTimeDisplay = date('d/m/Y H:i');

$lastFieldSyncLabel = !empty($stats['last_field_sync'])
    ? date('d/m/Y H:i', strtotime((string) $stats['last_field_sync'])) . ' UK'
    : 'No field upload recorded';

$heroMetrics = [
    [
        'title' => 'Reports on Devices',
        'value' => number_format($stats['device_pending']),
        'description' => 'Waiting to upload (devices seen in 7 days)',
        'icon' => 'bx-time-five',
        'tone' => $stats['device_pending'] > 0 ? 'amber' : 'neutral',
        'element_id' => 'pending-count',
    ],
    [
        'title' => 'Need Attention',
        'value' => number_format($stats['device_failed']),
        'description' => 'Field uploads currently failing',
        'icon' => 'bx-error-circle',
        'tone' => $stats['device_failed'] > 0 ? 'crimson' : 'neutral',
        'element_id' => 'failed-count',
    ],
    [
        'title' => 'Uploaded Today',
        'value' => number_format($stats['uploads_today']),
        'description' => 'Successful uploads and confirmed retries',
        'icon' => 'bx-check-shield',
        'tone' => 'teal',
        'element_id' => 'completed-count',
    ],
    [
        'title' => 'Field Outcomes',
        'value' => number_format($stats['field_outcomes']),
        'description' => 'Recorded upload successes and failures',
        'icon' => 'bx-pulse',
        'tone' => 'indigo',
        'element_id' => 'total-syncs',
    ],
    [
        'title' => 'Last Field Upload',
        'value' => $lastFieldSyncLabel,
        'description' => 'Most recent successful device upload',
        'icon' => 'bx-calendar-check',
        'tone' => 'neutral',
        'element_id' => 'last-sync',
    ],
    [
        'title' => 'Active Devices',
        'value' => number_format($stats['active_devices']),
        'description' => 'Reported in during the last 24 hours',
        'icon' => 'bx-mobile-alt',
        'tone' => 'neutral',
        'element_id' => 'conflicts-count',
    ],
];

$syncAdminLinks = [
    ['href' => 'dashboard.php', 'icon' => 'bx-layout', 'label' => 'Dashboard'],
    ['href' => 'admin_checkTriggers.php', 'icon' => 'bx-slider', 'label' => 'Legacy Triggers'],
    ['href' => 'resolve_conflict.php', 'icon' => 'bx-error', 'label' => 'Legacy Conflicts'],
    ['href' => 'sync_logs.php', 'icon' => 'bx-history', 'label' => 'Legacy Logs'],
    ['href' => 'cleanup_settings.php', 'icon' => 'bx-broom', 'label' => 'Legacy Cleanup'],
    ['href' => 'performance_metrics.php', 'icon' => 'bx-trending-up', 'label' => 'Legacy Performance'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Field sync administration console showing live offline device and upload activity.">
    <meta name="author" content="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8'); ?>">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg">
    <link rel="shortcut icon" href="/favicons/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
    <link rel="manifest" href="/favicons/site.webmanifest">
    <link href="/css/app.css" rel="stylesheet">
    <style>
        .sync-dashboard {
            min-height: 100vh;
        }

        .sync-dashboard__header {
            background: linear-gradient(135deg, rgba(25, 34, 54, 0.95), rgba(17, 24, 39, 0.92));
            border-radius: var(--bs-border-radius-xl);
            padding: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
        }

        .sync-quick-links {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 0.75rem;
        }

        .sync-quick-link {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.9rem 1rem;
            border-radius: var(--bs-border-radius-lg);
            border: 1px solid rgba(148, 163, 184, 0.15);
            background: rgba(15, 23, 42, 0.88);
            color: rgba(226, 232, 240, 0.92);
            text-decoration: none;
            transition: transform 0.2s ease, border 0.2s ease;
            font-weight: 500;
        }

        .sync-quick-link:hover {
            transform: translateY(-2px);
            border-color: rgba(59, 130, 246, 0.6);
            color: rgba(226, 232, 240, 0.98);
        }

        .sync-hero-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
        }

        .sync-hero-card {
            position: relative;
            border-radius: var(--bs-border-radius-lg);
            padding: 1.4rem;
            background: rgba(15, 23, 42, 0.92);
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 22px 40px rgba(15, 23, 42, 0.38);
            backdrop-filter: blur(12px);
            transition: transform 0.2s ease, border 0.2s ease;
        }

        .sync-hero-card:hover {
            transform: translateY(-3px);
            border-color: rgba(96, 165, 250, 0.45);
        }

        .sync-hero-card__icon {
            width: 2.75rem;
            height: 2.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.85rem;
            margin-bottom: 1rem;
            font-size: 1.45rem;
        }

        .sync-tone-amber {
            background: rgba(253, 224, 71, 0.25);
            color: rgba(251, 191, 36, 0.95);
        }

        .sync-tone-crimson {
            background: rgba(248, 113, 113, 0.25);
            color: rgba(239, 68, 68, 0.95);
        }

        .sync-tone-teal {
            background: rgba(45, 212, 191, 0.25);
            color: rgba(16, 185, 129, 0.95);
        }

        .sync-tone-indigo {
            background: rgba(129, 140, 248, 0.28);
            color: rgba(99, 102, 241, 0.95);
        }

        .sync-tone-neutral {
            background: rgba(100, 116, 139, 0.25);
            color: rgba(148, 163, 184, 0.95);
        }

        .sync-hero-card__title {
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(148, 163, 184, 0.75);
            margin-bottom: 0.25rem;
        }

        .sync-hero-card__stat {
            font-size: clamp(1.75rem, 2.5vw, 2.4rem);
            font-weight: 600;
            color: rgba(241, 245, 249, 0.96);
            margin-bottom: 0.35rem;
        }

        .sync-hero-card__description {
            color: rgba(148, 163, 184, 0.7);
            margin-bottom: 0;
        }

        .sync-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        .sync-panel {
            background: rgba(15, 23, 42, 0.92);
            border-radius: var(--bs-border-radius-xl);
            border: 1px solid rgba(148, 163, 184, 0.16);
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.35);
            padding: 1.75rem;
        }

        .sync-panel__header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            padding-bottom: 1rem;
        }

        .sync-panel__title {
            font-size: 1.15rem;
            margin-bottom: 0.35rem;
        }

        .sync-panel__description {
            margin-bottom: 0;
            color: rgba(148, 163, 184, 0.75);
        }

        .sync-status-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.8rem;
            border-radius: 999px;
            background: rgba(34, 197, 94, 0.18);
            color: rgba(34, 197, 94, 0.95);
        }

        .sync-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .sync-actions form {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .sync-actions select {
            background: rgba(30, 41, 59, 0.9);
            color: rgba(226, 232, 240, 0.9);
            border: 1px solid rgba(148, 163, 184, 0.25);
        }

        .sync-table table {
            width: 100%;
            color: rgba(226, 232, 240, 0.88);
        }

        .sync-table thead {
            color: rgba(148, 163, 184, 0.75);
        }

        .sync-table tbody tr {
            border-color: rgba(148, 163, 184, 0.12);
        }

        .sync-table tbody tr:hover {
            background: rgba(30, 41, 59, 0.85);
        }

        .sync-empty {
            text-align: center;
            padding: 1rem;
            color: rgba(148, 163, 184, 0.7);
            border: 1px dashed rgba(148, 163, 184, 0.25);
            border-radius: var(--bs-border-radius-lg);
        }

        .badge-soft-success {
            background: rgba(16, 185, 129, 0.18);
            color: rgba(16, 185, 129, 0.95);
        }

        .badge-soft-warning {
            background: rgba(253, 224, 71, 0.18);
            color: rgba(217, 119, 6, 0.95);
        }

        .badge-soft-danger {
            background: rgba(248, 113, 113, 0.2);
            color: rgba(220, 38, 38, 0.95);
        }

        .refresh-bar {
            height: 0.3rem;
            background: linear-gradient(90deg, #38bdf8, #6366f1, #14b8a6);
            width: 0%;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1055;
            transition: width 1s linear;
        }

        .modal-content.sync-modal {
            background: rgba(15, 23, 42, 0.96);
            border: 1px solid rgba(148, 163, 184, 0.22);
        }

        .modal-content.sync-modal .modal-header {
            border-bottom: 1px solid rgba(148, 163, 184, 0.15);
        }

        .modal-content.sync-modal .modal-body {
            color: rgba(226, 232, 240, 0.9);
        }

        pre.sync-json {
            background: rgba(30, 41, 59, 0.9);
            border-radius: var(--bs-border-radius-lg);
            padding: 1rem;
            color: rgba(226, 232, 240, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        @media (max-width: 768px) {
            .sync-dashboard__header {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>
    <div class="app-content-offset"></div>

    <div class="refresh-bar" id="refresh-bar"></div>

    <main class="sync-dashboard container-xl py-4">
        <header class="sync-dashboard__header mb-4 d-flex flex-column gap-4">
            <div class="d-flex flex-wrap align-items-start justify-content-between gap-3">
                <div>
                    <h1 class="h3 mb-2"><i class='bx bx-cloud-sync me-2'></i>Field Sync Console</h1>
                    <p class="text-muted mb-0">Monitor offline field devices, queued reports, upload failures, and successful defect delivery.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimeDisplay, ENT_QUOTES, 'UTF-8'); ?> UK</span>
                    <span class="sync-status-chip"><i class='bx bx-pulse'></i>Live Monitoring</span>
                </div>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $messageType === 'error' ? 'alert-danger' : 'alert-success'; ?> d-flex align-items-center gap-2 mb-0" role="alert">
                    <i class='bx <?php echo $messageType === 'error' ? 'bx-error-circle' : 'bx-check-circle'; ?> fs-4'></i>
                    <span><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($fieldTelemetryError !== ''): ?>
                <div class="alert alert-warning d-flex align-items-center gap-2 mb-0" role="alert">
                    <i class='bx bx-error-circle fs-4'></i>
                    <span><?php echo htmlspecialchars($fieldTelemetryError, ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php elseif (empty($fieldDevices)): ?>
                <div class="alert alert-info d-flex align-items-center gap-2 mb-0" role="status">
                    <i class='bx bx-info-circle fs-4'></i>
                    <span>No field device has reported yet. Devices will appear here automatically after they load this release while online.</span>
                </div>
            <?php endif; ?>

            <div class="sync-quick-links">
                <?php foreach ($syncAdminLinks as $link): ?>
                    <a class="sync-quick-link" href="<?php echo htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8'); ?>">
                        <span><i class='bx <?php echo htmlspecialchars($link['icon'], ENT_QUOTES, 'UTF-8'); ?> me-2'></i><?php echo htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8'); ?></span>
                        <i class='bx bx-chevron-right'></i>
                    </a>
                <?php endforeach; ?>
            </div>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="text-muted small">
                    Next auto-refresh in <span id="next-refresh">--</span>
                </div>
                <button id="refresh-button" class="btn btn-outline-light btn-sm d-inline-flex align-items-center gap-2" type="button">
                    <i class='bx bx-refresh'></i>Refresh now
                </button>
            </div>
        </header>

        <section class="sync-hero-grid mb-4" id="syncHeroMetrics">
            <?php foreach ($heroMetrics as $metric): ?>
                <article class="sync-hero-card">
                    <div class="sync-hero-card__icon sync-tone-<?php echo htmlspecialchars($metric['tone'], ENT_QUOTES, 'UTF-8'); ?>">
                        <i class='bx <?php echo htmlspecialchars($metric['icon'], ENT_QUOTES, 'UTF-8'); ?>'></i>
                    </div>
                    <div class="sync-hero-card__title"><?php echo htmlspecialchars($metric['title'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="sync-hero-card__stat" id="<?php echo htmlspecialchars($metric['element_id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <?php echo htmlspecialchars($metric['value'], ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                    <p class="sync-hero-card__description mb-0"><?php echo htmlspecialchars($metric['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="sync-grid">
            <article class="sync-panel">
                <div class="sync-panel__header">
                    <div>
                        <h2 class="sync-panel__title"><i class='bx bx-mobile-alt me-2'></i>Field Devices</h2>
                        <p class="sync-panel__description">Last-known outbox health reported by mobile devices. Counts update whenever a device is online.</p>
                    </div>
                    <div class="text-muted small">
                        Last updated <span id="last-updated"><?php echo htmlspecialchars(date('H:i:s'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                </div>
                <?php if (empty($fieldDevices)): ?>
                    <p class="sync-empty mb-0">No device telemetry has been received yet.</p>
                <?php else: ?>
                    <div class="table-responsive sync-table">
                        <table class="table table-borderless align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">User / Device</th>
                                    <th scope="col">Pending</th>
                                    <th scope="col">Failed</th>
                                    <th scope="col">Syncing</th>
                                    <th scope="col">Last Seen</th>
                                    <th scope="col">Last Upload</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fieldDevices as $device): ?>
                                    <?php
                                        $deviceStatus = (string) ($device['last_status'] ?? 'online');
                                        $deviceBadge = $deviceStatus === 'error' ? 'badge-soft-danger' : ($deviceStatus === 'syncing' ? 'badge-soft-warning' : 'badge-soft-success');
                                        $shortDeviceId = substr((string) ($device['device_id'] ?? 'unknown'), 0, 12);
                                    ?>
                                    <tr>
                                        <td>
                                            <div><?php echo htmlspecialchars($device['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="text-muted small" title="<?php echo htmlspecialchars($device['device_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($shortDeviceId, ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
                                        <td><?php echo number_format((int) ($device['pending_count'] ?? 0)); ?></td>
                                        <td><?php echo number_format((int) ($device['failed_count'] ?? 0)); ?></td>
                                        <td><?php echo number_format((int) ($device['syncing_count'] ?? 0)); ?></td>
                                        <td><?php echo htmlspecialchars($device['last_seen'] ?? 'Never', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($device['last_sync_at'] ?? 'Never', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $deviceBadge; ?> text-uppercase small"><?php echo htmlspecialchars($deviceStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php if (!empty($device['last_error'])): ?>
                                                <div class="text-danger small mt-1"><?php echo htmlspecialchars($device['last_error'], ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="sync-panel">
                <div class="sync-panel__header">
                    <div>
                        <h2 class="sync-panel__title"><i class='bx bx-transfer-alt me-2'></i>Recent Field Activity</h2>
                        <p class="sync-panel__description">Device outbox actions and server-confirmed defect uploads.</p>
                    </div>
                </div>
                <?php if (empty($recentFieldEvents)): ?>
                    <p class="sync-empty mb-0">No field sync activity has been recorded yet.</p>
                <?php else: ?>
                    <div class="table-responsive sync-table">
                        <table class="table table-borderless align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Time</th>
                                    <th scope="col">User / Device</th>
                                    <th scope="col">Event</th>
                                    <th scope="col">Defect</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentFieldEvents as $event): ?>
                                    <?php
                                        $eventStatus = (string) ($event['status'] ?? 'info');
                                        $eventBadge = $eventStatus === 'failed' ? 'badge-soft-danger' : ($eventStatus === 'success' ? 'badge-soft-success' : 'badge-soft-warning');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($event['created_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <div><?php echo htmlspecialchars($event['username'] ?? 'Unknown', ENT_QUOTES, 'UTF-8'); ?></div>
                                            <div class="text-muted small"><?php echo htmlspecialchars(substr((string) ($event['device_id'] ?? 'unknown'), 0, 12), ENT_QUOTES, 'UTF-8'); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars(str_replace('_', ' ', (string) ($event['event_type'] ?? 'event')), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <?php if (!empty($event['defect_id'])): ?>
                                                <a href="/view_defect.php?id=<?php echo (int) $event['defect_id']; ?>">#<?php echo (int) $event['defect_id']; ?></a>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?php echo $eventBadge; ?> text-uppercase small"><?php echo htmlspecialchars($eventStatus, ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td><?php echo htmlspecialchars($event['message'] ?? '—', ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="sync-panel">
                <div class="sync-panel__header">
                    <div>
                        <h2 class="sync-panel__title"><i class='bx bx-archive me-2'></i>Legacy Server Queue Operations</h2>
                        <p class="sync-panel__description">These controls apply only to the older server-side sync_queue workflow, not mobile device outboxes.</p>
                    </div>
                </div>
                <div class="sync-actions">
                    <form method="post" class="d-inline-flex">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="retry_failed">
                        <button type="submit" class="btn btn-warning d-inline-flex align-items-center gap-2">
                            <i class='bx bx-redo'></i>Retry Failed Items
                        </button>
                    </form>
                    <form method="post" class="d-inline-flex" onsubmit="return confirm('Clear all failed sync items? This cannot be undone.');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="clear_failed">
                        <button type="submit" class="btn btn-danger d-inline-flex align-items-center gap-2">
                            <i class='bx bx-trash'></i>Clear Failed Items
                        </button>
                    </form>
                    <form method="post" class="d-inline-flex" onsubmit="return confirm('Clear completed items older than 7 days?');">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="clear_completed">
                        <button type="submit" class="btn btn-success d-inline-flex align-items-center gap-2">
                            <i class='bx bx-broom'></i>Clear Old Completed
                        </button>
                    </form>
                    <form method="post" class="d-inline-flex align-items-center gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="resolve_conflicts">
                        <select class="form-select form-select-sm" name="resolution" required>
                            <option value="server_wins">Server Wins</option>
                            <option value="client_wins">Client Wins</option>
                            <option value="merge">Auto-merge</option>
                        </select>
                        <button type="submit" class="btn btn-primary d-inline-flex align-items-center gap-2">
                            <i class='bx bx-merge'></i>Resolve All Conflicts
                        </button>
                    </form>
                </div>
            </article>

            <article class="sync-panel">
                <div class="sync-panel__header">
                    <div>
                        <h2 class="sync-panel__title"><i class='bx bx-history me-2'></i>Legacy Sync Logs</h2>
                        <p class="sync-panel__description">Last ten runs from the older server-side sync framework.</p>
                    </div>
                </div>
                <?php if (empty($recentLogs)): ?>
                    <p class="sync-empty mb-0">No sync logs found.</p>
                <?php else: ?>
                    <div class="table-responsive sync-table">
                        <table class="table table-borderless align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">User</th>
                                    <th scope="col">Start</th>
                                    <th scope="col">End</th>
                                    <th scope="col">Processed</th>
                                    <th scope="col">Success/Failed</th>
                                    <th scope="col">Status</th>
                                    <th scope="col" class="text-end">Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLogs as $log): ?>
                                    <?php
                                        $statusBadge = 'badge-soft-warning';
                                        if (($log['status'] ?? '') === 'success') {
                                            $statusBadge = 'badge-soft-success';
                                        } elseif (($log['status'] ?? '') === 'failed') {
                                            $statusBadge = 'badge-soft-danger';
                                        }
                                        $logPayload = htmlspecialchars(json_encode($log, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($log['username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['start_time'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($log['end_time'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($log['items_processed'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars(($log['items_succeeded'] ?? 0) . '/' . ($log['items_failed'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><span class="badge <?php echo $statusBadge; ?> text-uppercase small"><?php echo htmlspecialchars($log['status'] ?? 'unknown', ENT_QUOTES, 'UTF-8'); ?></span></td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-light" data-log="<?php echo $logPayload; ?>" onclick="showDetails(this.dataset.log)">
                                                <i class='bx bx-info-circle'></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="sync-panel">
                <div class="sync-panel__header">
                    <div>
                        <h2 class="sync-panel__title"><i class='bx bx-error me-2'></i>Legacy Server Failures</h2>
                        <p class="sync-panel__description">Failed records from the older server queue; mobile failures appear above.</p>
                    </div>
                </div>
                <?php if (empty($recentErrors)): ?>
                    <p class="sync-empty mb-0">No failed items in the queue.</p>
                <?php else: ?>
                    <div class="table-responsive sync-table">
                        <table class="table table-borderless align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">User</th>
                                    <th scope="col">Action</th>
                                    <th scope="col">Entity</th>
                                    <th scope="col">Created</th>
                                    <th scope="col">Attempts</th>
                                    <th scope="col">Result</th>
                                    <th scope="col" class="text-end">Retry</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentErrors as $error): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars((string) $error['id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($error['username'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($error['action'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($error['entity_type'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($error['created_at'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars((string) ($error['attempts'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($error['result_message'] ?? 'No details', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="retry_single">
                                                <input type="hidden" name="item_id" value="<?php echo htmlspecialchars((string) $error['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-sm btn-warning d-inline-flex align-items-center gap-1">
                                                    <i class='bx bx-redo'></i>Retry
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>

            <article class="sync-panel">
                <div class="sync-panel__header">
                    <div>
                        <h2 class="sync-panel__title"><i class='bx bx-git-branch me-2'></i>Legacy Conflicts</h2>
                        <p class="sync-panel__description">Conflicts recorded by the older server-side sync framework.</p>
                    </div>
                </div>
                <?php if (empty($conflicts)): ?>
                    <p class="sync-empty mb-0">No unresolved conflicts detected.</p>
                <?php else: ?>
                    <div class="table-responsive sync-table">
                        <table class="table table-borderless align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Entity</th>
                                    <th scope="col">Server Data</th>
                                    <th scope="col">Client Data</th>
                                    <th scope="col">Client Time</th>
                                    <th scope="col">Server Time</th>
                                    <th scope="col">Device</th>
                                    <th scope="col" class="text-end">Resolve</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conflicts as $conflict): ?>
                                    <?php
                                        $serverPayload = htmlspecialchars(json_encode(json_decode($conflict['server_data'], true), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                        $clientPayload = htmlspecialchars(json_encode(json_decode($conflict['client_data'], true), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(($conflict['entity_type'] ?? 'entity') . ' #' . ($conflict['entity_id'] ?? 'N/A'), ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-light" data-json="<?php echo $serverPayload; ?>" onclick="showJSON('Server', this.dataset.json)">
                                                <i class='bx bx-code-curly'></i> View
                                            </button>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-light" data-json="<?php echo $clientPayload; ?>" onclick="showJSON('Client', this.dataset.json)">
                                                <i class='bx bx-code-curly'></i> View
                                            </button>
                                        </td>
                                        <td><?php echo htmlspecialchars($conflict['client_timestamp'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($conflict['server_timestamp'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td><?php echo htmlspecialchars($conflict['device_id'] ?? 'N/A', ENT_QUOTES, 'UTF-8'); ?></td>
                                        <td class="text-end">
                                            <form method="post" class="d-inline-flex align-items-center gap-2">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                                                <input type="hidden" name="action" value="resolve_single_conflict">
                                                <input type="hidden" name="conflict_id" value="<?php echo htmlspecialchars((string) $conflict['id'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <select class="form-select form-select-sm" name="resolution" required>
                                                    <option value="server_wins">Server Wins</option>
                                                    <option value="client_wins">Client Wins</option>
                                                    <option value="merge">Merge</option>
                                                </select>
                                                <button type="submit" class="btn btn-sm btn-primary d-inline-flex align-items-center gap-1">
                                                    <i class='bx bx-check'></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        </section>
    </main>

    <div class="modal fade" id="detailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content sync-modal">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="modalContent"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const refreshIntervalSeconds = <?php echo (int) $refreshInterval; ?>;
        const refreshBar = document.getElementById('refresh-bar');
        const nextRefreshSpan = document.getElementById('next-refresh');
        const refreshButton = document.getElementById('refresh-button');
        const lastUpdatedLabel = document.getElementById('last-updated');
        let refreshTimeout = null;
        let countdownInterval = null;

        function startRefreshTimer() {
            clearTimeout(refreshTimeout);
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }

            if (refreshBar) {
                refreshBar.style.width = '0%';
                setTimeout(() => {
                    refreshBar.style.width = '100%';
                }, 50);
            }

            let remaining = refreshIntervalSeconds;
            if (nextRefreshSpan) {
                nextRefreshSpan.textContent = remaining + 's';
            }

            countdownInterval = setInterval(() => {
                remaining -= 1;
                if (remaining <= 0) {
                    clearInterval(countdownInterval);
                }
                if (nextRefreshSpan) {
                    nextRefreshSpan.textContent = Math.max(remaining, 0) + 's';
                }
            }, 1000);

            refreshTimeout = setTimeout(() => {
                window.location.reload();
            }, refreshIntervalSeconds * 1000);
        }

        if (refreshButton) {
            refreshButton.addEventListener('click', () => {
                clearTimeout(refreshTimeout);
                window.location.reload();
            });
        }

        if (lastUpdatedLabel) {
            lastUpdatedLabel.textContent = '<?php echo htmlspecialchars(date('H:i:s'), ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>)';
        }

        startRefreshTimer();

        const detailsModalElement = document.getElementById('detailsModal');
        const modalTitle = document.getElementById('modalTitle');
        const modalContent = document.getElementById('modalContent');
        const detailsModal = detailsModalElement ? new bootstrap.Modal(detailsModalElement) : null;

        function escapeHtml(value) {
            return value
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        window.showDetails = function (dataJson) {
            if (!detailsModal || !modalTitle || !modalContent) {
                return;
            }

            try {
                const data = JSON.parse(dataJson);
                const rows = Object.keys(data).map(key => {
                    const value = typeof data[key] === 'object' && data[key] !== null
                        ? JSON.stringify(data[key], null, 2)
                        : data[key];
                    return `<tr><th class="pe-3 text-nowrap">${escapeHtml(key)}</th><td>${escapeHtml(String(value ?? ''))}</td></tr>`;
                }).join('');

                modalTitle.textContent = 'Sync Details';
                modalContent.innerHTML = `<div class="table-responsive"><table class="table table-sm table-borderless align-middle mb-0 text-white"><tbody>${rows}</tbody></table></div>`;
                detailsModal.show();
            } catch (error) {
                console.error('Failed to parse log payload', error);
            }
        };

        window.showJSON = function (label, dataJson) {
            if (!detailsModal || !modalTitle || !modalContent) {
                return;
            }

            try {
                const data = JSON.parse(dataJson);
                modalTitle.textContent = `${label} Payload`;
                modalContent.innerHTML = `<pre class="sync-json">${escapeHtml(JSON.stringify(data, null, 2))}</pre>`;
                detailsModal.show();
            } catch (error) {
                console.error('Failed to parse JSON payload', error);
            }
        };
    </script>
</body>
</html>
