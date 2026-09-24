<?php
declare(strict_types=1);

// admin/system_settings.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../classes/SystemHealth.php';
require_once __DIR__ . '/../includes/navbar.php';

if (($_SESSION['user_type'] ?? '') !== 'admin') {
    header('Location: ../dashboard.php?error=unauthorized');
    exit();
}

function systemSettingsEsc(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function systemSettingsJsonValue(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    json_decode($value, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return null;
    }

    $decoded = json_decode($value, true);
    return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

$database = new Database();
$db = $database->getConnection();

if (!$db instanceof PDO) {
    http_response_code(500);
    exit('Database connection unavailable.');
}

$navbar = new Navbar(
    $db,
    (int)($_SESSION['user_id'] ?? 0),
    (string)($_SESSION['username'] ?? 'Admin')
);

$systemHealth = new SystemHealth($db);
$databaseHealthy = $systemHealth->checkDatabaseConnection();
$diskHealthy = $systemHealth->checkDiskSpace();
$loadHealthy = $systemHealth->checkSystemLoad();
$healthMetrics = $systemHealth->getMetrics();

$configurations = [];
$emailTemplates = [];
$pageWarnings = [];

try {
    $stmt = $db->query('SELECT config_key, config_value FROM system_configurations ORDER BY config_key');
    $configurations = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $pageWarnings[] = 'System configuration data is not currently available.';
    error_log('System Settings config read failed: ' . $e->getMessage());
}

try {
    $stmt = $db->query('SELECT id, name, subject FROM email_templates ORDER BY name');
    $emailTemplates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $pageWarnings[] = 'Email template data is not currently available.';
    error_log('System Settings email template read failed: ' . $e->getMessage());
}

$backupFiles = glob(__DIR__ . '/../backups/*.{zip,sql}', GLOB_BRACE) ?: [];
usort($backupFiles, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
$recentBackups = array_slice($backupFiles, 0, 5);

function systemSettingsBytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power > 1 ? 1 : 0) . ' ' . $units[$power];
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>System Settings · Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
    <style>
        body { background: #f5f7fb; }
        .settings-shell { max-width: 1450px; margin: 0 auto; }
        .settings-card { border: 0; border-radius: 1rem; box-shadow: 0 8px 30px rgba(15,23,42,.06); }
        .health-dot { width: .7rem; height: .7rem; display: inline-block; border-radius: 50%; margin-right: .45rem; }
        .health-ok { background: #16a34a; }
        .health-warn { background: #f59e0b; }
        .setting-key { font-size: .78rem; color: #64748b; text-transform: uppercase; letter-spacing: .04em; }
        .setting-value { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; white-space: pre-wrap; word-break: break-word; }
        .backup-name { word-break: break-all; }
    </style>
</head>
<body>
<?php $navbar->render(); ?>

<main class="settings-shell container-fluid px-3 px-lg-4 py-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">System Settings</h1>
            <p class="text-muted mb-0">Read-only system overview and links to the maintained administration tools.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-primary" href="/system-tools/index.php">
                <i class="bx bx-wrench me-1"></i> System Tools
            </a>
            <a class="btn btn-success" href="/backup_manager.php">
                <i class="bx bx-data me-1"></i> Backup Manager
            </a>
        </div>
    </div>

    <?php foreach ($pageWarnings as $warning): ?>
        <div class="alert alert-warning"><?php echo systemSettingsEsc($warning); ?></div>
    <?php endforeach; ?>

    <section class="card settings-card mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h2 class="h5 mb-0">System Health</h2>
        </div>
        <div class="card-body px-4 pb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100 bg-white">
                        <div class="fw-semibold"><span class="health-dot <?php echo $databaseHealthy ? 'health-ok' : 'health-warn'; ?>"></span>Database Connection</div>
                        <div class="small text-muted mt-2">
                            <?php echo $databaseHealthy ? 'Connected successfully' : 'Requires attention'; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100 bg-white">
                        <div class="fw-semibold"><span class="health-dot <?php echo $diskHealthy ? 'health-ok' : 'health-warn'; ?>"></span>Disk Space</div>
                        <div class="small text-muted mt-2">
                            <?php
                            $disk = $healthMetrics['disk'] ?? [];
                            echo isset($disk['usage_percentage'])
                                ? systemSettingsEsc($disk['usage_percentage']) . '% used'
                                : 'Usage check completed';
                            ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 h-100 bg-white">
                        <div class="fw-semibold"><span class="health-dot <?php echo $loadHealthy ? 'health-ok' : 'health-warn'; ?>"></span>System Load</div>
                        <div class="small text-muted mt-2">
                            <?php
                            $load = $healthMetrics['system_load'] ?? [];
                            echo isset($load['1min'])
                                ? '1 minute load: ' . systemSettingsEsc(number_format((float)$load['1min'], 2))
                                : 'Load check completed';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="card settings-card h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-1">System Configuration</h2>
                        <p class="small text-muted mb-0">Current stored values. Changes should be made through the relevant maintained admin tool.</p>
                    </div>
                    <span class="badge text-bg-light"><?php echo count($configurations); ?> values</span>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if (!$configurations): ?>
                        <p class="text-muted mb-0">No system configuration values found.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($configurations as $config): ?>
                                <?php
                                $rawValue = (string)($config['config_value'] ?? '');
                                $jsonValue = systemSettingsJsonValue($rawValue);
                                ?>
                                <div class="col-12">
                                    <div class="border rounded-3 p-3 bg-white">
                                        <div class="setting-key mb-2"><?php echo systemSettingsEsc(str_replace('_', ' ', (string)$config['config_key'])); ?></div>
                                        <div class="setting-value small"><?php echo systemSettingsEsc($jsonValue ?? $rawValue); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="card settings-card mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-1">Email Templates</h2>
                        <p class="small text-muted mb-0">Templates currently stored in the database.</p>
                    </div>
                    <a class="btn btn-sm btn-outline-primary" href="/admin/email_settings.php">Email Settings</a>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if (!$emailTemplates): ?>
                        <p class="text-muted mb-0">No email templates found.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($emailTemplates as $template): ?>
                                <div class="list-group-item px-0">
                                    <div class="fw-semibold"><?php echo systemSettingsEsc($template['name'] ?? 'Template'); ?></div>
                                    <div class="small text-muted"><?php echo systemSettingsEsc($template['subject'] ?? ''); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="card settings-card">
                <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-1">Recent Backups</h2>
                        <p class="small text-muted mb-0">Latest backup archives detected on the server.</p>
                    </div>
                    <a class="btn btn-sm btn-success" href="/backup_manager.php">Manage</a>
                </div>
                <div class="card-body px-4 pb-4">
                    <?php if (!$recentBackups): ?>
                        <p class="text-muted mb-0">No backup archives found in the backup directory.</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recentBackups as $backup): ?>
                                <div class="list-group-item px-0">
                                    <div class="backup-name fw-semibold small"><?php echo systemSettingsEsc(basename($backup)); ?></div>
                                    <div class="text-muted small">
                                        <?php echo systemSettingsEsc(systemSettingsBytes((int)filesize($backup))); ?>
                                        ·
                                        <?php echo systemSettingsEsc(date('d/m/Y H:i', (int)filemtime($backup))); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
