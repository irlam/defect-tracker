<?php
/**
 * Live, read-only deployment analysis for Defect Tracker.
 * Values are calculated from the current runtime, filesystem, environment,
 * database and latest backup archive. No historical audit findings are embedded.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tool_bootstrap.php';
require_once __DIR__ . '/../config/constants.php';

$root = dirname(__DIR__);
$generatedAt = new DateTimeImmutable('now', new DateTimeZone('Europe/London'));

function reportEsc(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function reportFormatBytes(?int $bytes): string {
    if ($bytes === null || $bytes < 0) return 'Unavailable';
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $power = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 2) . ' ' . $units[$power];
}

function reportTableExists(PDO $db, string $table): bool {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND TABLE_TYPE = "BASE TABLE"'
    );
    $stmt->execute(['table' => $table]);
    return (int)$stmt->fetchColumn() > 0;
}

function reportSafeCount(PDO $db, string $table, ?string $where = null): ?int {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !reportTableExists($db, $table)) return null;
    $sql = 'SELECT COUNT(*) FROM ' . $table;
    if ($where !== null && $where !== '') $sql .= ' WHERE ' . $where;
    try {
        return (int)$db->query($sql)->fetchColumn();
    } catch (Throwable $e) {
        return null;
    }
}

function reportDirectorySummary(string $path, int $maxFiles = 20000): array {
    if (!is_dir($path)) {
        return ['exists'=>false,'writable'=>false,'files'=>0,'bytes'=>0,'truncated'=>false];
    }
    $files = 0; $bytes = 0; $truncated = false;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $item) {
            if (!$item->isFile()) continue;
            $files++;
            $bytes += (int)$item->getSize();
            if ($files >= $maxFiles) { $truncated = true; break; }
        }
    } catch (Throwable $e) {
        return ['exists'=>true,'writable'=>is_writable($path),'files'=>$files,'bytes'=>$bytes,'truncated'=>$truncated,'error'=>true];
    }
    return ['exists'=>true,'writable'=>is_writable($path),'files'=>$files,'bytes'=>$bytes,'truncated'=>$truncated];
}

function reportLatestBackup(string $backupDir): array {
    $result = ['exists'=>false,'filename'=>null,'bytes'=>null,'modified'=>null,'zip_readable'=>null,'database_dump'=>null];
    if (!is_dir($backupDir)) return $result;
    $matches = glob(rtrim($backupDir, '/') . '/*.zip') ?: [];
    if (!$matches) return $result;
    usort($matches, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    $file = $matches[0];
    $result['exists'] = true;
    $result['filename'] = basename($file);
    $result['bytes'] = is_file($file) ? (int)filesize($file) : null;
    $result['modified'] = is_file($file) ? (int)filemtime($file) : null;
    if (!class_exists('ZipArchive')) return $result;
    $zip = new ZipArchive();
    $opened = $zip->open($file);
    $result['zip_readable'] = $opened === true;
    if ($opened === true) {
        $hasDatabase = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string)$zip->getNameIndex($i);
            if (str_starts_with($name, 'database/') && str_ends_with(strtolower($name), '.sql')) {
                $hasDatabase = true; break;
            }
        }
        $result['database_dump'] = $hasDatabase;
        $zip->close();
    }
    return $result;
}

function reportGitRevision(string $root): ?string {
    $gitDir = $root . '/.git';
    $headFile = $gitDir . '/HEAD';
    if (!is_file($headFile)) return null;
    $head = trim((string)@file_get_contents($headFile));
    if ($head === '') return null;
    if (str_starts_with($head, 'ref: ')) {
        $ref = trim(substr($head, 5));
        if (!preg_match('#^[A-Za-z0-9._/-]+$#', $ref)) return null;
        $refFile = $gitDir . '/' . $ref;
        if (is_file($refFile)) {
            $hash = trim((string)@file_get_contents($refFile));
            return preg_match('/^[a-f0-9]{40}$/i', $hash) ? substr($hash, 0, 7) : null;
        }
        return null;
    }
    return preg_match('/^[a-f0-9]{40}$/i', $head) ? substr($head, 0, 7) : null;
}

function reportStatusCard(string $title, string $value, string $status='healthy', string $detail=''): void {
    $variant = tool_status_variant($status);
    $icon = tool_status_icon($status);
    $class = 'tool-card h-100';
    if ($variant === 'success') $class .= ' tool-card--success';
    elseif ($variant === 'warning') $class .= ' tool-card--warning';
    elseif ($variant === 'danger') $class .= ' tool-card--danger';

    echo '<div class="col"><div class="' . reportEsc($class) . '">';
    echo '<div class="d-flex justify-content-between gap-3 align-items-start"><div class="min-w-0">';
    echo '<div class="text-muted small mb-1">' . reportEsc($title) . '</div>';
    echo '<div class="fs-4 fw-semibold text-break">' . reportEsc($value) . '</div>';
    if ($detail !== '') echo '<div class="small text-muted mt-2">' . reportEsc($detail) . '</div>';
    echo '</div><span class="tool-status-pill tool-status-pill-' . reportEsc($variant) . '">';
    echo '<i class="bx ' . reportEsc($icon) . '"></i> ' . reportEsc(tool_status_label($status));
    echo '</span></div></div></div>';
}

$dbConnected = $db instanceof PDO;
$dbServerVersion = $dbName = null;
$tableCount = $viewCount = $dbSizeBytes = $foreignKeyCount = null;
$dbError = $dbErrorMessage ?? null;

if ($dbConnected) {
    try {
        $db->query('SELECT 1')->fetchColumn();
        $dbServerVersion = (string)($db->query('SELECT VERSION()')->fetchColumn() ?: 'Unknown');
        $dbName = (string)($db->query('SELECT DATABASE()')->fetchColumn() ?: 'Unknown');
        $stmt = $db->query(
            'SELECT
                SUM(CASE WHEN TABLE_TYPE = "BASE TABLE" THEN 1 ELSE 0 END) AS tables_count,
                SUM(CASE WHEN TABLE_TYPE = "VIEW" THEN 1 ELSE 0 END) AS views_count,
                COALESCE(SUM(DATA_LENGTH + INDEX_LENGTH), 0) AS total_bytes
             FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()'
        );
        $schemaStats = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        $tableCount = isset($schemaStats['tables_count']) ? (int)$schemaStats['tables_count'] : null;
        $viewCount = isset($schemaStats['views_count']) ? (int)$schemaStats['views_count'] : null;
        $dbSizeBytes = isset($schemaStats['total_bytes']) ? (int)$schemaStats['total_bytes'] : null;
        $foreignKeyCount = (int)$db->query(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = "FOREIGN KEY"'
        )->fetchColumn();
    } catch (Throwable $e) {
        $dbConnected = false;
        $dbError = 'Database live checks failed.';
    }
}

$dataCounts = ['Projects'=>null,'Defects'=>null,'Contractors'=>null,'Floor plans'=>null,'Users'=>null,'Notifications'=>null];
$adminUsers = $nonAdminUsers = null;
if ($dbConnected && $db instanceof PDO) {
    foreach (['Projects'=>'projects','Defects'=>'defects','Contractors'=>'contractors','Floor plans'=>'floor_plans','Users'=>'users','Notifications'=>'notifications'] as $label=>$table) {
        $dataCounts[$label] = reportSafeCount($db, $table);
    }
    if (reportTableExists($db, 'users')) {
        $adminUsers = reportSafeCount($db, 'users', "user_type = 'admin'");
        $nonAdminUsers = reportSafeCount($db, 'users', "user_type <> 'admin'");
    }
}

$requiredTables = ['users','projects','defects','floor_plans','contractors'];
$missingRequiredTables = [];
if ($dbConnected && $db instanceof PDO) {
    foreach ($requiredTables as $table) if (!reportTableExists($db, $table)) $missingRequiredTables[] = $table;
}

$requiredExtensions = ['pdo_mysql','mysqli','zip','gd'];
$extensionStatus = [];
foreach ($requiredExtensions as $extension) $extensionStatus[$extension] = extension_loaded($extension);
$extensionStatus['imagick'] = extension_loaded('imagick');

$uploads = reportDirectorySummary($root . '/uploads');
$backupDir = $root . '/backups/backups';
$backups = reportDirectorySummary($backupDir, 5000);
$latestBackup = reportLatestBackup($backupDir);

$diskTotal = @disk_total_space($root);
$diskFree = @disk_free_space($root);
$diskUsedPercent = null;
if (is_numeric($diskTotal) && is_numeric($diskFree) && (float)$diskTotal > 0) {
    $diskUsedPercent = (((float)$diskTotal - (float)$diskFree) / (float)$diskTotal) * 100;
}

$https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
    || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$cookie = session_get_cookie_params();

$sessionChecks = [
    'HTTPS request' => $https,
    'Secure session cookie' => (bool)($cookie['secure'] ?? false),
    'HttpOnly session cookie' => (bool)($cookie['httponly'] ?? false),
    'SameSite configured' => !empty($cookie['samesite'] ?? ''),
];
$configChecks = [
    'Environment-backed configuration' => class_exists('Environment'),
    'Public .env absent' => !is_file($root . '/.env'),
    'Rate limiter component present' => is_file($root . '/middleware/RateLimiter.php'),
    'Mail host configured' => trim((string)Environment::get('MAIL_HOST', '')) !== '',
];

$revision = reportGitRevision($root);
$appVersion = defined('APP_VERSION') ? APP_VERSION : 'Unknown';
$appTimezone = (string)Environment::get('APP_TIMEZONE', date_default_timezone_get());

$criticalFindings = [];
$warnings = [];
if (!$dbConnected) $criticalFindings[] = 'Database connection is unavailable.';
if ($missingRequiredTables) $criticalFindings[] = 'Required database tables are missing: ' . implode(', ', $missingRequiredTables) . '.';
foreach ($requiredExtensions as $extension) if (!$extensionStatus[$extension]) $criticalFindings[] = 'Required PHP extension is missing: ' . $extension . '.';
if (!$uploads['exists'] || !$uploads['writable']) $criticalFindings[] = 'Uploads directory is missing or not writable.';
if (!$backups['exists'] || !$backups['writable']) $warnings[] = 'Backup directory is missing or not writable.';
if (!$latestBackup['exists']) $warnings[] = 'No backup ZIP is currently available.';
elseif ($latestBackup['zip_readable'] === false) $criticalFindings[] = 'The latest backup ZIP cannot be opened.';
elseif ($latestBackup['database_dump'] === false) $warnings[] = 'The latest backup ZIP does not contain a database SQL dump.';
if (!$https) $criticalFindings[] = 'This diagnostic request is not using HTTPS.';
if (!($cookie['httponly'] ?? false)) $warnings[] = 'The current session cookie is not marked HttpOnly.';
if (!($cookie['secure'] ?? false) && $https) $warnings[] = 'The current HTTPS session cookie is not marked Secure.';
if (is_file($root . '/.env')) $warnings[] = 'A .env file exists inside the public application root.';
if ($diskUsedPercent !== null && $diskUsedPercent >= 90) $criticalFindings[] = 'Filesystem usage is at or above 90%.';
elseif ($diskUsedPercent !== null && $diskUsedPercent >= 80) $warnings[] = 'Filesystem usage is at or above 80%.';

$overallStatus = $criticalFindings ? 'critical' : ($warnings ? 'warning' : 'healthy');
$overallText = $overallStatus === 'healthy' ? 'Healthy' : ($overallStatus === 'warning' ? 'Attention recommended' : 'Action required');

tool_render_header(
    'System Analysis Report',
    'Live, read-only facts from this deployment. Generated ' . $generatedAt->format('d-m-Y H:i:s') . ' UK time.',
    [
        ['label'=>'Admin Dashboard','href'=>'../admin.php'],
        ['label'=>'System Tools','href'=>'index.php'],
        ['label'=>'System Analysis Report'],
    ]
);

echo '<div class="alert alert-info mb-4" role="status"><i class="bx bx-refresh me-2"></i>';
echo 'This report contains no historical audit results. Refresh the page to recalculate the current runtime, database, filesystem, configuration and backup checks.</div>';

echo '<div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3 mb-4">';
reportStatusCard('Overall status', $overallText, $overallStatus, count($criticalFindings) . ' critical · ' . count($warnings) . ' warning');
reportStatusCard('Application version', $appVersion, $appVersion === '3.0.0' ? 'healthy' : 'warning', $revision ? 'Deployment revision ' . $revision : 'Git revision unavailable on server');
reportStatusCard('PHP runtime', PHP_VERSION, version_compare(PHP_VERSION, '8.2.0', '>=') ? 'healthy' : 'critical', PHP_SAPI);
reportStatusCard('Database', $dbConnected ? 'Connected' : 'Unavailable', $dbConnected ? 'healthy' : 'critical', $dbConnected ? ($dbServerVersion ?: '') : ($dbError ?: 'Connection failed'));
echo '</div>';

echo '<div class="row g-4 mb-4"><div class="col-12 col-xl-7"><div class="tool-card h-100">';
echo '<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">Current application data</h2><p class="text-muted small mb-0">Live row counts from the current database.</p></div><i class="bx bx-bar-chart-alt-2 fs-3 text-info"></i></div>';
echo '<div class="row row-cols-2 row-cols-md-3 g-3">';
foreach ($dataCounts as $label=>$count) {
    echo '<div class="col"><div class="border border-secondary rounded p-3 h-100"><div class="text-muted small">' . reportEsc($label) . '</div><div class="fs-3 fw-semibold">' . ($count === null ? 'N/A' : number_format($count)) . '</div></div></div>';
}
echo '</div>';
if ($adminUsers !== null || $nonAdminUsers !== null) echo '<div class="small text-muted mt-3">Administrator users: ' . reportEsc($adminUsers ?? 'N/A') . ' · Non-admin users: ' . reportEsc($nonAdminUsers ?? 'N/A') . '</div>';
echo '</div></div>';

echo '<div class="col-12 col-xl-5"><div class="tool-card h-100">';
echo '<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">Database facts</h2><p class="text-muted small mb-0">Read directly from MySQL/MariaDB information_schema.</p></div><i class="bx bx-data fs-3 text-info"></i></div><dl class="row mb-0">';
foreach ([
    'Database name'=>$dbName ?? 'Unavailable',
    'Server version'=>$dbServerVersion ?? 'Unavailable',
    'Base tables'=>$tableCount ?? 'Unavailable',
    'Views'=>$viewCount ?? 'Unavailable',
    'Foreign keys'=>$foreignKeyCount ?? 'Unavailable',
    'Approx. DB size'=>reportFormatBytes($dbSizeBytes),
] as $label=>$value) {
    echo '<dt class="col-6 text-muted">' . reportEsc($label) . '</dt><dd class="col-6 text-end text-break">' . reportEsc($value) . '</dd>';
}
echo '</dl></div></div></div>';

echo '<div class="row g-4 mb-4"><div class="col-12 col-lg-6"><div class="tool-card h-100">';
echo '<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">PHP capabilities</h2><p class="text-muted small mb-0">Loaded extensions in the current PHP runtime.</p></div><i class="bx bx-code-alt fs-3 text-info"></i></div>';
foreach ($extensionStatus as $extension=>$loaded) {
    $required = in_array($extension, $requiredExtensions, true);
    $status = $loaded ? 'healthy' : ($required ? 'critical' : 'warning');
    $variant = tool_status_variant($status);
    echo '<div class="d-flex justify-content-between align-items-center py-2 border-bottom border-secondary-subtle"><span>' . reportEsc($extension) . ($required ? ' <span class="text-muted small">(required)</span>' : ' <span class="text-muted small">(optional)</span>') . '</span><span class="tool-status-pill tool-status-pill-' . reportEsc($variant) . '">' . ($loaded ? 'Loaded' : 'Not loaded') . '</span></div>';
}
echo '</div></div>';

echo '<div class="col-12 col-lg-6"><div class="tool-card h-100">';
echo '<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">Runtime & storage</h2><p class="text-muted small mb-0">Current server/application filesystem state.</p></div><i class="bx bx-server fs-3 text-info"></i></div><dl class="row mb-0">';
foreach ([
    'Application timezone'=>$appTimezone,
    'Uploads'=>($uploads['truncated'] ? '20,000+ files' : number_format((int)$uploads['files']) . ' files') . ' · ' . reportFormatBytes((int)$uploads['bytes']),
    'Uploads writable'=>$uploads['writable'] ? 'Yes' : 'No',
    'Backup files'=>number_format((int)$backups['files']),
    'Disk free'=>is_numeric($diskFree) ? reportFormatBytes((int)$diskFree) : 'Unavailable',
    'Disk used'=>$diskUsedPercent !== null ? number_format($diskUsedPercent, 1) . '%' : 'Unavailable',
] as $label=>$value) {
    echo '<dt class="col-5 text-muted">' . reportEsc($label) . '</dt><dd class="col-7 text-end">' . reportEsc($value) . '</dd>';
}
echo '</dl></div></div></div>';

echo '<div class="row g-4 mb-4"><div class="col-12 col-xl-7"><div class="tool-card h-100">';
echo '<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">Latest backup</h2><p class="text-muted small mb-0">Checks the newest ZIP in the live backup directory without restoring it.</p></div><i class="bx bx-archive fs-3 text-info"></i></div>';
if (!$latestBackup['exists']) {
    echo '<div class="alert alert-warning mb-0">No backup ZIP found.</div>';
} else {
    $backupDate = $latestBackup['modified'] ? (new DateTimeImmutable('@' . $latestBackup['modified']))->setTimezone(new DateTimeZone('Europe/London'))->format('d-m-Y H:i:s') : 'Unavailable';
    $zipState = $latestBackup['zip_readable'] === true ? 'Readable' : ($latestBackup['zip_readable'] === false ? 'Unreadable' : 'Not checked');
    $dbDumpState = $latestBackup['database_dump'] === true ? 'Present' : ($latestBackup['database_dump'] === false ? 'Missing' : 'Not checked');
    echo '<dl class="row mb-0">';
    foreach (['File'=>$latestBackup['filename'] ?? 'Unknown','Created/modified'=>$backupDate . ' UK','Size'=>reportFormatBytes($latestBackup['bytes']),'ZIP integrity'=>$zipState,'Database SQL dump'=>$dbDumpState] as $label=>$value) {
        echo '<dt class="col-5 text-muted">' . reportEsc($label) . '</dt><dd class="col-7 text-end text-break">' . reportEsc($value) . '</dd>';
    }
    echo '</dl>';
}
echo '</div></div>';

echo '<div class="col-12 col-xl-5"><div class="tool-card h-100">';
echo '<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">Security & configuration signals</h2><p class="text-muted small mb-0">Observable checks only; secret values are never displayed.</p></div><i class="bx bx-shield-quarter fs-3 text-info"></i></div>';
foreach (array_merge($sessionChecks, $configChecks) as $label=>$passed) {
    $variant = tool_status_variant($passed ? 'healthy' : 'warning');
    echo '<div class="d-flex justify-content-between align-items-center gap-3 py-2 border-bottom border-secondary-subtle"><span class="small">' . reportEsc($label) . '</span><span class="tool-status-pill tool-status-pill-' . reportEsc($variant) . '">' . ($passed ? 'Yes' : 'No') . '</span></div>';
}
echo '</div></div></div>';

echo '<div class="row g-4 mb-4"><div class="col-12"><div class="tool-card">';
echo '<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">Current findings</h2><p class="text-muted small mb-0">Generated from the checks above on this page load.</p></div><i class="bx bx-list-check fs-3 text-info"></i></div>';
if (!$criticalFindings && !$warnings) {
    echo '<div class="alert alert-success mb-0"><i class="bx bx-check-circle me-2"></i>No critical or warning conditions were detected by these live checks.</div>';
} else {
    if ($criticalFindings) {
        echo '<div class="mb-3"><h3 class="h6 text-danger">Critical</h3><ul class="mb-0">';
        foreach ($criticalFindings as $finding) echo '<li>' . reportEsc($finding) . '</li>';
        echo '</ul></div>';
    }
    if ($warnings) {
        echo '<div><h3 class="h6 text-warning">Warnings</h3><ul class="mb-0">';
        foreach ($warnings as $finding) echo '<li>' . reportEsc($finding) . '</li>';
        echo '</ul></div>';
    }
}
echo '</div></div></div>';

echo '<div class="d-flex flex-wrap gap-2">';
echo '<a class="btn btn-outline-primary" href="system_health.php"><i class="bx bx-pulse me-1"></i>System Health</a>';
echo '<a class="btn btn-outline-primary" href="check_database.php"><i class="bx bx-data me-1"></i>Database Check</a>';
echo '<a class="btn btn-outline-primary" href="../backups/index.php"><i class="bx bx-archive me-1"></i>Backup Manager</a>';
echo '<a class="btn btn-outline-secondary" href="index.php"><i class="bx bx-grid-alt me-1"></i>All System Tools</a>';
echo '</div>';

tool_render_footer();
