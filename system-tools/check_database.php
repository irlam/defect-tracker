<?php
/**
 * system-tools/check_database.php
 * Database integrity and connectivity diagnostics for the admin tools suite
 */

declare(strict_types=1);

$requiredTables = [
    'users',
    'projects',
    'floor_plans',
    'defects',
    'defect_images',
    'comments',
];

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/../config/database.php';

    try {
        $database = new Database();
        $db = $database->getConnection();
    } catch (Throwable $e) {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    $report = runDatabaseChecks($db, $requiredTables);

    echo "=== Database Integrity Report ===\n";
    echo 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC' . PHP_EOL . PHP_EOL;

    foreach ($report['checks'] as $check) {
        echo sprintf('[%s] %s: %s' . PHP_EOL,
            strtoupper($check['status']),
            $check['label'],
            $check['message']
        );

        if (!empty($check['details'])) {
            foreach ($check['details'] as $label => $value) {
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                echo sprintf("  - %s: %s\n", $label, $value);
            }
        }

        echo PHP_EOL;
    }

    exit(0);
}

require_once __DIR__ . '/includes/tool_bootstrap.php';

/**
 * Execute connectivity and schema checks.
 *
 * @param PDO $db
 * @param array<int, string> $requiredTables
 * @return array<string, mixed>
 */
function runDatabaseChecks(PDO $db, array $requiredTables): array
{
    $checks = [];

    // Connection test
    try {
        $db->query('SELECT 1');
        $checks[] = [
            'label' => 'Connection',
            'status' => 'healthy',
            'message' => 'Database connection established successfully.',
            'details' => [
                'Database' => $db->query('SELECT DATABASE()')->fetchColumn() ?: 'N/A',
                'Server version' => $db->query('SELECT VERSION()')->fetchColumn() ?: 'N/A',
            ],
        ];
    } catch (PDOException $exception) {
        $checks[] = [
            'label' => 'Connection',
            'status' => 'critical',
            'message' => $exception->getMessage(),
            'details' => [],
        ];
        return ['checks' => $checks];
    }

    // Schema presence check
    $missingTables = [];
    foreach ($requiredTables as $table) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table');
        $stmt->execute(['table' => $table]);
        if ((int)$stmt->fetchColumn() === 0) {
            $missingTables[] = $table;
        }
    }

    $checks[] = [
        'label' => 'Schema Integrity',
        'status' => empty($missingTables) ? 'healthy' : 'critical',
        'message' => empty($missingTables)
            ? 'All required tables are present.'
            : 'Missing tables detected in the schema.',
        'details' => empty($missingTables) ? [] : ['Missing tables' => $missingTables],
    ];

    // Table size summary
    $sizeQuery = $db->query(
        "SELECT TABLE_NAME, TABLE_ROWS, ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb
         FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()
         ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC LIMIT 6"
    );
    $tableSizes = $sizeQuery ? $sizeQuery->fetchAll(PDO::FETCH_ASSOC) : [];

    $checks[] = [
        'label' => 'Table Overview',
        'status' => 'healthy',
        'message' => 'Top tables by size.',
        'details' => ['tables' => $tableSizes],
    ];

    // Foreign key checks (optional)
    $foreignKeyQuery = $db->query(
        "SELECT TABLE_NAME, CONSTRAINT_NAME
         FROM information_schema.TABLE_CONSTRAINTS
         WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
    );

    $hasForeignKeys = $foreignKeyQuery ? $foreignKeyQuery->rowCount() > 0 : false;

    $checks[] = [
        'label' => 'Relational Integrity',
        'status' => $hasForeignKeys ? 'healthy' : 'warning',
        'message' => $hasForeignKeys
            ? 'Foreign key constraints detected.'
            : 'No foreign key constraints detected. Consider adding constraints for data integrity.',
        'details' => [],
    ];

    return ['checks' => $checks];
}

$report = [];

if ($db instanceof PDO) {
    $report = runDatabaseChecks($db, $requiredTables);
} else {
    $report['error'] = $dbErrorMessage ?? 'Database connection unavailable.';
}

tool_render_header(
    'Database Check',
    'Validate schema connectivity and run quick integrity checks.',
    [
        ['label' => 'Admin Dashboard', 'href' => '../admin.php'],
        ['label' => 'Database Check'],
    ]
);

if (!empty($report['error'])) {
    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($report['error'], ENT_QUOTES, 'UTF-8') . '</div>';
} else {
    echo '<div class="row row-cols-1 row-cols-lg-2 g-4">';
    foreach ($report['checks'] as $check) {
        $statusVariant = tool_status_variant($check['status'] ?? 'secondary');
        $statusLabel = tool_status_label($check['status'] ?? 'secondary');
        $statusIcon = tool_status_icon($check['status'] ?? 'secondary');

        $cardClass = 'tool-card h-100';
        if ($statusVariant === 'success') {
            $cardClass .= ' tool-card--success';
        } elseif ($statusVariant === 'warning') {
            $cardClass .= ' tool-card--warning';
        } elseif ($statusVariant === 'danger') {
            $cardClass .= ' tool-card--danger';
        }

        echo '<div class="col">';
            echo '<div class="' . $cardClass . '">';
                echo '<div class="d-flex justify-content-between align-items-start mb-3">';
                    echo '<div>';
                        echo '<h2 class="h5 mb-1">' . htmlspecialchars($check['label'] ?? 'Check', ENT_QUOTES, 'UTF-8') . '</h2>';
                        echo '<p class="text-muted mb-0">' . htmlspecialchars($check['message'] ?? '', ENT_QUOTES, 'UTF-8') . '</p>';
                    echo '</div>';
                    echo '<span class="tool-status-pill tool-status-pill-' . $statusVariant . '">';
                        echo '<i class="bx ' . $statusIcon . '"></i> ' . $statusLabel;
                    echo '</span>';
                echo '</div>';

                if (!empty($check['details'])) {
                    if (isset($check['details']['tables']) && is_array($check['details']['tables'])) {
                        echo '<div class="table-responsive">';
                        echo '<table class="table table-sm table-dark align-middle mb-0">';
                        echo '<thead><tr><th>Table</th><th class="text-end">Rows</th><th class="text-end">Size (MB)</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($check['details']['tables'] as $table) {
                            $name = htmlspecialchars($table['TABLE_NAME'] ?? 'n/a', ENT_QUOTES, 'UTF-8');
                            $rows = number_format((int)($table['TABLE_ROWS'] ?? 0));
                            $size = number_format((float)($table['size_mb'] ?? 0), 2);
                            echo '<tr><td>' . $name . '</td><td class="text-end">' . $rows . '</td><td class="text-end">' . $size . '</td></tr>';
                        }
                        echo '</tbody></table>';
                        echo '</div>';
                    } else {
                        echo '<ul class="list-unstyled mb-0">';
                        foreach ($check['details'] as $label => $value) {
                            $safeLabel = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
                            if (is_array($value)) {
                                $safeValue = htmlspecialchars(implode(', ', array_map('strval', $value)), ENT_QUOTES, 'UTF-8');
                            } else {
                                $safeValue = htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
                            }
                            echo '<li class="d-flex justify-content-between">';
                                echo '<span class="text-muted">' . $safeLabel . '</span>';
                                echo '<span class="fw-medium">' . $safeValue . '</span>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }
                }
            echo '</div>';
        echo '</div>';
    }
    echo '</div>';
}

tool_render_footer();