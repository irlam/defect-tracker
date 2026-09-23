<?php
/**
 * Production-safe functional smoke checks.
 * Read-only: no mock database, no seeded credentials, no writes.
 */
declare(strict_types=1);

require_once __DIR__ . '/includes/tool_bootstrap.php';
require_once __DIR__ . '/../config/constants.php';

$root = dirname(__DIR__);
$checks = [];

function addSmokeCheck(array &$checks, string $label, bool $passed, string $detail): void {
    $checks[] = [
        'label' => $label,
        'status' => $passed ? 'healthy' : 'critical',
        'message' => $detail,
    ];
}

addSmokeCheck(
    $checks,
    'PHP runtime',
    version_compare(PHP_VERSION, '8.2.0', '>='),
    'Running PHP ' . PHP_VERSION . '; Defect Tracker requires PHP 8.2 or newer.'
);

addSmokeCheck(
    $checks,
    'Application version',
    defined('APP_VERSION') && APP_VERSION === '3.0.0',
    'Current application version: ' . (defined('APP_VERSION') ? APP_VERSION : 'unknown')
);

$dbHealthy = $db instanceof PDO;
if ($dbHealthy) {
    try {
        $dbHealthy = (int)$db->query('SELECT 1')->fetchColumn() === 1;
    } catch (Throwable $e) {
        $dbHealthy = false;
    }
}
addSmokeCheck($checks, 'Database connectivity', $dbHealthy, $dbHealthy ? 'SELECT 1 completed successfully.' : 'Database connectivity check failed.');

$requiredTables = ['users','projects','defects','contractors','floor_plans','defect_images'];
$missingTables = [];
if ($dbHealthy && $db instanceof PDO) {
    $stmt = $db->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table'
    );
    foreach ($requiredTables as $table) {
        $stmt->execute(['table' => $table]);
        if ((int)$stmt->fetchColumn() === 0) $missingTables[] = $table;
    }
} else {
    $missingTables = $requiredTables;
}
addSmokeCheck(
    $checks,
    'Core database schema',
    empty($missingTables),
    empty($missingTables) ? 'All required application tables are present.' : 'Missing: ' . implode(', ', $missingTables)
);

$uploads = $root . '/uploads';
addSmokeCheck(
    $checks,
    'Upload storage',
    is_dir($uploads) && is_writable($uploads),
    is_dir($uploads) ? (is_writable($uploads) ? 'Uploads directory is writable.' : 'Uploads directory is not writable.') : 'Uploads directory is missing.'
);

$backupDir = $root . '/backups/backups';
addSmokeCheck(
    $checks,
    'Backup storage',
    is_dir($backupDir) && is_writable($backupDir),
    is_dir($backupDir) ? (is_writable($backupDir) ? 'Backup directory is writable.' : 'Backup directory is not writable.') : 'Backup directory is missing.'
);

foreach ([
    'Health endpoint' => $root . '/health.php',
    'Defect creation' => $root . '/create_defect.php',
    'Floor plan selector' => $root . '/floorplan_selector.php',
    'Reporting hub' => $root . '/reports.php',
    'System analysis' => $root . '/system-tools/system_analysis_report.php',
] as $label => $path) {
    addSmokeCheck($checks, $label, is_file($path) && is_readable($path), is_file($path) ? 'Application file is present and readable.' : 'Application file is missing.');
}

$passed = count(array_filter($checks, static fn(array $check): bool => $check['status'] === 'healthy'));
$total = count($checks);
$failed = $total - $passed;

tool_render_header(
    'Functional Smoke Tests',
    'Read-only checks against the current production deployment. No mock database or seeded test users are used.',
    [
        ['label' => 'Admin Dashboard', 'href' => '../admin.php'],
        ['label' => 'System Tools', 'href' => 'index.php'],
        ['label' => 'Functional Smoke Tests'],
    ]
);

echo '<div class="row g-3 mb-4">';
echo '<div class="col-md-4"><div class="tool-card tool-card--success h-100"><div class="text-muted small">Passed</div><div class="fs-2 fw-semibold">' . $passed . '</div></div></div>';
echo '<div class="col-md-4"><div class="tool-card ' . ($failed ? 'tool-card--danger' : 'tool-card--success') . ' h-100"><div class="text-muted small">Failed</div><div class="fs-2 fw-semibold">' . $failed . '</div></div></div>';
echo '<div class="col-md-4"><div class="tool-card h-100"><div class="text-muted small">Total checks</div><div class="fs-2 fw-semibold">' . $total . '</div></div></div>';
echo '</div>';

echo '<div class="row row-cols-1 row-cols-lg-2 g-3">';
foreach ($checks as $check) {
    $variant = tool_status_variant($check['status']);
    $icon = tool_status_icon($check['status']);
    $card = 'tool-card h-100 ' . ($variant === 'success' ? 'tool-card--success' : 'tool-card--danger');
    echo '<div class="col"><div class="' . htmlspecialchars($card, ENT_QUOTES, 'UTF-8') . '">';
    echo '<div class="d-flex justify-content-between gap-3 align-items-start">';
    echo '<div><h2 class="h6 mb-1">' . htmlspecialchars($check['label'], ENT_QUOTES, 'UTF-8') . '</h2>';
    echo '<p class="text-muted small mb-0">' . htmlspecialchars($check['message'], ENT_QUOTES, 'UTF-8') . '</p></div>';
    echo '<span class="tool-status-pill tool-status-pill-' . $variant . '"><i class="bx ' . $icon . '"></i> ' . tool_status_label($check['status']) . '</span>';
    echo '</div></div></div>';
}
echo '</div>';

tool_render_footer();
