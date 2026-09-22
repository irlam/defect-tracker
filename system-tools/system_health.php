<?php
/**
 * system-tools/system_health.php
 * Comprehensive system health and provisioning tool with shared layout
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/../config/database.php';

    try {
        $database = new Database();
        $db = $database->getConnection();
    } catch (Throwable $e) {
        fwrite(STDERR, 'Database connection failed: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    $health = new SystemHealth($db);
    $provisioning = $health->ensureBaselineSchema();
    $checks = $health->runChecks();

    echo "=== System Health Scan ===\n";
    echo 'Generated: ' . gmdate('Y-m-d H:i:s') . ' UTC' . PHP_EOL . PHP_EOL;

    echo "Schema Provisioning\n---------------------\n";
    if (!empty($provisioning['schema'])) {
        foreach ($provisioning['schema'] as $item) {
            echo sprintf(' - %-35s %s (%s)' . PHP_EOL,
                $item['name'],
                strtoupper($item['status']),
                $item['message']
            );
        }
    } else {
        echo "No schema adjustments were required." . PHP_EOL;
    }

    echo PHP_EOL . "Baseline Configuration\n----------------------\n";
    if (!empty($provisioning['seeding'])) {
        foreach ($provisioning['seeding'] as $item) {
            echo sprintf(' - %-35s %s (%s)' . PHP_EOL,
                $item['name'],
                strtoupper($item['status']),
                $item['message']
            );
        }
    } else {
        echo "No configuration updates were applied." . PHP_EOL;
    }

    echo PHP_EOL . "Health Checks\n-------------\n";
    foreach ($checks as $check) {
        echo sprintf(' - %-35s %s (%s)' . PHP_EOL,
            $check['label'] ?? 'Metric',
            strtoupper($check['status'] ?? 'n/a'),
            $check['message'] ?? 'No details'
        );
    }

    exit(0);
}

require_once __DIR__ . '/includes/tool_bootstrap.php';

class SystemHealth
{
    private PDO $db;

    /** @var array<string, mixed> */
    private array $metrics = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Ensure supporting tables and baseline configuration exist.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function ensureBaselineSchema(): array
    {
        $summary = [
            'schema' => [],
            'seeding' => [],
        ];

        $actor = $_SESSION['username'] ?? 'system';
        $timestamp = gmdate('Y-m-d H:i:s');

        $tableDefinitions = [
            'api_keys' => [
                'label' => 'API keys registry',
                'sql' => <<<SQL
CREATE TABLE IF NOT EXISTS api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    api_key_hash VARCHAR(255) NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(255),
    created_at DATETIME,
    updated_by VARCHAR(255),
    updated_at DATETIME,
    expires_at DATETIME,
    last_used_at DATETIME,
    INDEX idx_api_keys_user (user_id),
    CONSTRAINT fk_api_keys_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            'system_configurations' => [
                'label' => 'System configuration store',
                'sql' => <<<SQL
CREATE TABLE IF NOT EXISTS system_configurations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(50) NOT NULL UNIQUE,
    config_value TEXT,
    created_by VARCHAR(255),
    created_at DATETIME,
    updated_by VARCHAR(255),
    updated_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            'email_templates' => [
                'label' => 'Email templates',
                'sql' => <<<SQL
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    variables JSON,
    created_by VARCHAR(255),
    created_at DATETIME,
    updated_by VARCHAR(255),
    updated_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
            'system_health_logs' => [
                'label' => 'System health logs',
                'sql' => <<<SQL
CREATE TABLE IF NOT EXISTS system_health_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    metric_name VARCHAR(50) NOT NULL,
    metric_value TEXT,
    status ENUM('healthy', 'warning', 'critical') NOT NULL,
    created_at DATETIME
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL,
            ],
        ];

        foreach ($tableDefinitions as $definition) {
            try {
                $this->db->exec($definition['sql']);
                $summary['schema'][] = [
                    'name' => $definition['label'],
                    'status' => 'healthy',
                    'message' => 'Table available.',
                ];
            } catch (PDOException $exception) {
                $summary['schema'][] = [
                    'name' => $definition['label'],
                    'status' => 'critical',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $passwordTemplateVariables = json_encode(
            [
                'username' => "User's name",
                'reset_link' => 'Password reset URL',
            ],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO email_templates (name, subject, body, variables, created_by, created_at)
                 VALUES (:name, :subject, :body, :variables, :created_by, :created_at)
                 ON DUPLICATE KEY UPDATE 
                    subject = VALUES(subject),
                    body = VALUES(body),
                    variables = VALUES(variables),
                    updated_by = VALUES(created_by),
                    updated_at = VALUES(created_at)'
            );
            $stmt->execute([
                'name' => 'password_reset',
                'subject' => 'Password Reset Request',
                'body' => '<h2>Password Reset Request</h2><p>Dear {{username}},</p><p>Click the link below to reset your password:</p><p><a href="{{reset_link}}">Reset Password</a></p>',
                'variables' => $passwordTemplateVariables,
                'created_by' => $actor,
                'created_at' => $timestamp,
            ]);

            $summary['seeding'][] = [
                'name' => 'Password reset email template',
                'status' => 'healthy',
                'message' => 'Template verified.',
            ];
        } catch (PDOException $exception) {
            $summary['seeding'][] = [
                'name' => 'Password reset email template',
                'status' => 'warning',
                'message' => $exception->getMessage(),
            ];
        }

        $configurationDefaults = [
            'password_policy' => json_encode(
                [
                    'min_length' => 12,
                    'require_special' => true,
                    'require_numbers' => true,
                    'require_uppercase' => true,
                ],
                JSON_UNESCAPED_SLASHES
            ),
            'session_timeout' => '1800',
            'backup_retention_days' => '30',
            'max_login_attempts' => '5',
        ];

        $configStatement = $this->db->prepare(
            'INSERT INTO system_configurations (config_key, config_value, created_by, created_at)
             VALUES (:config_key, :config_value, :created_by, :created_at)
             ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value),
                updated_by = VALUES(created_by),
                updated_at = VALUES(created_at)'
        );

        foreach ($configurationDefaults as $key => $value) {
            try {
                $configStatement->execute([
                    'config_key' => $key,
                    'config_value' => $value,
                    'created_by' => $actor,
                    'created_at' => $timestamp,
                ]);

                $summary['seeding'][] = [
                    'name' => sprintf('Configuration "%s"', $key),
                    'status' => 'healthy',
                    'message' => 'Configuration verified.',
                ];
            } catch (PDOException $exception) {
                $summary['seeding'][] = [
                    'name' => sprintf('Configuration "%s"', $key),
                    'status' => 'warning',
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return $summary;
    }

    /**
     * Run health checks covering connectivity and environment.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runChecks(): array
    {
        return [
            $this->checkDatabaseConnection(),
            $this->checkDiskSpace(),
            $this->checkSystemLoad(),
            $this->checkPhpEnvironment(),
        ];
    }

    /**
     * Return raw metrics collected while running checks.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDatabaseConnection(): array
    {
        try {
            $metadata = $this->db->query('SELECT NOW() AS server_time, DATABASE() AS database_name')->fetch(PDO::FETCH_ASSOC) ?: [];
            $version = $this->db->query('SELECT VERSION() AS server_version')->fetch(PDO::FETCH_ASSOC) ?: [];

            $details = [
                'Database' => $metadata['database_name'] ?? 'N/A',
                'Server time' => $metadata['server_time'] ?? 'N/A',
                'Server version' => $version['server_version'] ?? 'N/A',
            ];

            $this->metrics['database'] = $details;

            return [
                'label' => 'Database Connection',
                'status' => 'healthy',
                'message' => 'Connection established successfully.',
                'details' => $details,
                'metrics' => $details,
            ];
        } catch (Throwable $exception) {
            return [
                'label' => 'Database Connection',
                'status' => 'critical',
                'message' => $exception->getMessage(),
                'details' => [],
                'metrics' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function checkDiskSpace(): array
    {
        $rootPath = DIRECTORY_SEPARATOR;
        $total = @disk_total_space($rootPath);
        $free = @disk_free_space($rootPath);

        if ($total === false || $free === false || $total <= 0) {
            return [
                'label' => 'Disk Usage',
                'status' => 'warning',
                'message' => 'Unable to determine disk usage for root partition.',
                'details' => [],
                'metrics' => [],
            ];
        }

        $used = $total - $free;
        $usagePercentage = ($used / $total) * 100;

        $status = 'healthy';
        if ($usagePercentage >= 90) {
            $status = 'critical';
        } elseif ($usagePercentage >= 80) {
            $status = 'warning';
        }

        $details = [
            'Total capacity' => $this->formatBytes((int)$total),
            'Used space' => sprintf('%s (%.2f%%)', $this->formatBytes((int)$used), $usagePercentage),
            'Free space' => $this->formatBytes((int)$free),
        ];

        $this->metrics['disk'] = [
            'total_bytes' => (int)$total,
            'used_bytes' => (int)$used,
            'free_bytes' => (int)$free,
            'usage_percentage' => $usagePercentage,
        ];

        return [
            'label' => 'Disk Usage',
            'status' => $status,
            'message' => $status === 'healthy' ? 'Disk usage within expected thresholds.' : 'Disk usage requires attention.',
            'details' => $details,
            'metrics' => $this->metrics['disk'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkSystemLoad(): array
    {
        $load = function_exists('sys_getloadavg') ? sys_getloadavg() : false;

        if ($load === false) {
            return [
                'label' => 'System Load',
                'status' => 'warning',
                'message' => 'System load average is not available on this platform.',
                'details' => [],
                'metrics' => [],
            ];
        }

        [$one, $five, $fifteen] = $load;

        $status = 'healthy';
        if ($one >= 3.0) {
            $status = 'critical';
        } elseif ($one >= 1.5) {
            $status = 'warning';
        }

        $details = [
            '1 minute average' => number_format($one, 2),
            '5 minute average' => number_format($five, 2),
            '15 minute average' => number_format($fifteen, 2),
        ];

        $this->metrics['system_load'] = [
            '1min' => $one,
            '5min' => $five,
            '15min' => $fifteen,
        ];

        return [
            'label' => 'System Load',
            'status' => $status,
            'message' => $status === 'healthy' ? 'Load averages are stable.' : 'Server load is elevated.',
            'details' => $details,
            'metrics' => $this->metrics['system_load'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkPhpEnvironment(): array
    {
        $requiredExtensions = ['pdo', 'pdo_mysql', 'gd', 'json', 'curl', 'mbstring'];
        $missingExtensions = [];

        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                $missingExtensions[] = $extension;
            }
        }

        $status = empty($missingExtensions) ? 'healthy' : 'warning';
        $message = empty($missingExtensions)
            ? 'All required PHP extensions are enabled.'
            : 'Missing PHP extensions: ' . implode(', ', $missingExtensions);

        $loadedExtensionsCount = count(get_loaded_extensions());

        $details = [
            'PHP version' => PHP_VERSION,
            'Memory limit' => ini_get('memory_limit'),
            'Max execution time' => ini_get('max_execution_time') . 's',
            'Upload max filesize' => ini_get('upload_max_filesize'),
            'Post max size' => ini_get('post_max_size'),
            'Loaded extensions' => (string)$loadedExtensionsCount,
        ];

        $this->metrics['php'] = [
            'missing_extensions' => $missingExtensions,
            'loaded_extensions_count' => $loadedExtensionsCount,
            'memory_limit' => ini_get('memory_limit'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];

        return [
            'label' => 'PHP Environment',
            'status' => $status,
            'message' => $message,
            'details' => $details,
            'metrics' => $this->metrics['php'],
            'missing_extensions' => $missingExtensions,
        ];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $pow = (int)floor(log($bytes, 1024));
        $pow = max(0, min($pow, count($units) - 1));

        $value = $bytes / (1024 ** $pow);

        return round($value, $precision) . ' ' . $units[$pow];
    }
}

$errors = [];
$schemaSummary = ['schema' => [], 'seeding' => []];
$checks = [];
$metrics = [];

if ($db instanceof PDO) {
    $health = new SystemHealth($db);
    $schemaSummary = $health->ensureBaselineSchema();
    $checks = $health->runChecks();
    $metrics = $health->getMetrics();
} else {
    $errors[] = 'Database connection unavailable: ' . ($dbErrorMessage ?? 'unknown error');
}

tool_render_header(
    'System Health Scan',
    'Run environment diagnostics covering PHP, database connectivity, and disk usage.',
    [
        ['label' => 'Admin Dashboard', 'href' => '../admin.php'],
        ['label' => 'System Health Scan'],
    ]
);

if (!empty($errors)) {
    echo '<div class="alert alert-danger" role="alert">';
    foreach ($errors as $error) {
        echo '<div>' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
    }
    echo '</div>';
} else {
    echo '<div class="row g-4 mb-4">';
        echo '<div class="col-xl-6">';
            echo '<div class="tool-card">';
                echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                    echo '<h2 class="h5 mb-0">Schema Provisioning</h2>';
                    echo '<i class="bx bx-data text-info fs-3"></i>';
                echo '</div>';

                if (!empty($schemaSummary['schema'])) {
                    echo '<ul class="list-unstyled mb-0">';
                    foreach ($schemaSummary['schema'] as $item) {
                        $statusVariant = tool_status_variant($item['status'] ?? 'secondary');
                        $statusLabel = tool_status_label($item['status'] ?? 'secondary');
                        $statusIcon = tool_status_icon($item['status'] ?? 'secondary');
                        $message = htmlspecialchars($item['message'] ?? '', ENT_QUOTES, 'UTF-8');
                        $name = htmlspecialchars($item['name'] ?? 'Task', ENT_QUOTES, 'UTF-8');

                        echo '<li class="mb-3">';
                            echo '<div class="d-flex justify-content-between align-items-start">';
                                echo '<div class="pe-3">';
                                    echo '<span class="fw-semibold">' . $name . '</span>';
                                    if ($message !== '') {
                                        echo '<div class="text-muted small">' . $message . '</div>';
                                    }
                                echo '</div>';
                                echo '<span class="tool-status-pill tool-status-pill-' . $statusVariant . '">';
                                    echo '<i class="bx ' . $statusIcon . '"></i> ' . $statusLabel;
                                echo '</span>';
                            echo '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="text-muted mb-0">No schema changes were required.</p>';
                }
            echo '</div>';
        echo '</div>';

        echo '<div class="col-xl-6">';
            echo '<div class="tool-card">';
                echo '<div class="d-flex justify-content-between align-items-center mb-3">';
                    echo '<h2 class="h5 mb-0">Baseline Configuration</h2>';
                    echo '<i class="bx bx-slider text-success fs-3"></i>';
                echo '</div>';

                if (!empty($schemaSummary['seeding'])) {
                    echo '<ul class="list-unstyled mb-0">';
                    foreach ($schemaSummary['seeding'] as $item) {
                        $statusVariant = tool_status_variant($item['status'] ?? 'secondary');
                        $statusLabel = tool_status_label($item['status'] ?? 'secondary');
                        $statusIcon = tool_status_icon($item['status'] ?? 'secondary');
                        $message = htmlspecialchars($item['message'] ?? '', ENT_QUOTES, 'UTF-8');
                        $name = htmlspecialchars($item['name'] ?? 'Item', ENT_QUOTES, 'UTF-8');

                        echo '<li class="mb-3">';
                            echo '<div class="d-flex justify-content-between align-items-start">';
                                echo '<div class="pe-3">';
                                    echo '<span class="fw-semibold">' . $name . '</span>';
                                    if ($message !== '') {
                                        echo '<div class="text-muted small">' . $message . '</div>';
                                    }
                                echo '</div>';
                                echo '<span class="tool-status-pill tool-status-pill-' . $statusVariant . '">';
                                    echo '<i class="bx ' . $statusIcon . '"></i> ' . $statusLabel;
                                echo '</span>';
                            echo '</div>';
                        echo '</li>';
                    }
                    echo '</ul>';
                } else {
                    echo '<p class="text-muted mb-0">No baseline configuration updates were needed.</p>';
                }
            echo '</div>';
        echo '</div>';
    echo '</div>';

    if (!empty($checks)) {
        echo '<div class="row row-cols-1 row-cols-lg-2 g-4">';
        foreach ($checks as $check) {
            $status = $check['status'] ?? 'secondary';
            $statusVariant = tool_status_variant($status);
            $statusLabel = tool_status_label($status);
            $statusIcon = tool_status_icon($status);

            $cardClass = 'tool-card h-100';
            if ($statusVariant === 'success') {
                $cardClass .= ' tool-card--success';
            } elseif ($statusVariant === 'warning') {
                $cardClass .= ' tool-card--warning';
            } elseif ($statusVariant === 'danger') {
                $cardClass .= ' tool-card--danger';
            }

            $message = htmlspecialchars($check['message'] ?? '', ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($check['label'] ?? 'Metric', ENT_QUOTES, 'UTF-8');

            echo '<div class="col">';
                echo '<div class="' . $cardClass . '">';
                    echo '<div class="d-flex justify-content-between align-items-start mb-3">';
                        echo '<div>';
                            echo '<h2 class="h5 mb-1">' . $label . '</h2>';
                            if ($message !== '') {
                                echo '<p class="text-muted mb-0">' . $message . '</p>';
                            }
                        echo '</div>';
                        echo '<span class="tool-status-pill tool-status-pill-' . $statusVariant . '">';
                            echo '<i class="bx ' . $statusIcon . '"></i> ' . $statusLabel;
                        echo '</span>';
                    echo '</div>';

                    if (!empty($check['details']) && is_iterable($check['details'])) {
                        echo '<ul class="list-unstyled mb-0">';
                        foreach ($check['details'] as $detailLabel => $detailValue) {
                            $safeLabel = htmlspecialchars((string)$detailLabel, ENT_QUOTES, 'UTF-8');
                            $safeValue = is_array($detailValue)
                                ? htmlspecialchars(implode(', ', array_map('strval', $detailValue)), ENT_QUOTES, 'UTF-8')
                                : htmlspecialchars((string)$detailValue, ENT_QUOTES, 'UTF-8');

                            echo '<li class="d-flex justify-content-between">';
                                echo '<span class="text-muted me-3">' . $safeLabel . '</span>';
                                echo '<span class="fw-medium">' . $safeValue . '</span>';
                            echo '</li>';
                        }
                        echo '</ul>';
                    }

                    if (!empty($check['missing_extensions']) && is_array($check['missing_extensions'])) {
                        echo '<div class="mt-3">';
                            echo '<span class="text-muted small d-block mb-2">Missing extensions</span>';
                            foreach ($check['missing_extensions'] as $extension) {
                                echo '<span class="badge text-bg-warning text-dark me-1 mb-1">' . htmlspecialchars((string)$extension, ENT_QUOTES, 'UTF-8') . '</span>';
                            }
                        echo '</div>';
                    }
                echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '<div class="row g-4 mt-1">';
        echo '<div class="col-lg-6">';
            echo '<div class="tool-card h-100">';
                echo '<h2 class="h5 mb-3">PHP Environment Snapshot</h2>';
                echo '<dl class="row mb-0">';
                    echo '<dt class="col-sm-6 text-muted">Version</dt><dd class="col-sm-6">' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</dd>';
                    echo '<dt class="col-sm-6 text-muted">Memory limit</dt><dd class="col-sm-6">' . htmlspecialchars($metrics['php']['memory_limit'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</dd>';
                    echo '<dt class="col-sm-6 text-muted">Max execution time</dt><dd class="col-sm-6">' . htmlspecialchars(($metrics['php']['max_execution_time'] ?? 'N/A') . 's', ENT_QUOTES, 'UTF-8') . '</dd>';
                    echo '<dt class="col-sm-6 text-muted">Upload max filesize</dt><dd class="col-sm-6">' . htmlspecialchars($metrics['php']['upload_max_filesize'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</dd>';
                    echo '<dt class="col-sm-6 text-muted">Post max size</dt><dd class="col-sm-6">' . htmlspecialchars($metrics['php']['post_max_size'] ?? 'N/A', ENT_QUOTES, 'UTF-8') . '</dd>';
                    echo '<dt class="col-sm-6 text-muted">Loaded extensions</dt><dd class="col-sm-6">' . htmlspecialchars((string)($metrics['php']['loaded_extensions_count'] ?? '0'), ENT_QUOTES, 'UTF-8') . '</dd>';
                echo '</dl>';
            echo '</div>';
        echo '</div>';

        echo '<div class="col-lg-6">';
            echo '<div class="tool-card h-100">';
                echo '<h2 class="h5 mb-3">Disk Utilization</h2>';

                $usagePercentage = (float)($metrics['disk']['usage_percentage'] ?? 0.0);
                $progressVariant = 'bg-success';
                if ($usagePercentage >= 90) {
                    $progressVariant = 'bg-danger';
                } elseif ($usagePercentage >= 80) {
                    $progressVariant = 'bg-warning text-dark';
                }

                echo '<div class="mb-3">';
                    echo '<div class="progress" role="progressbar" aria-label="Disk usage" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . (int)$usagePercentage . '">';
                        echo '<div class="progress-bar ' . $progressVariant . '" style="width: ' . number_format($usagePercentage, 2) . '%"></div>';
                    echo '</div>';
                echo '</div>';

                echo '<dl class="row mb-0">';
                    echo '<dt class="col-sm-6 text-muted">Total capacity</dt><dd class="col-sm-6">' . htmlspecialchars(tool_format_bytes($metrics['disk']['total_bytes'] ?? null), ENT_QUOTES, 'UTF-8') . '</dd>';
                    echo '<dt class="col-sm-6 text-muted">Used</dt><dd class="col-sm-6">' . htmlspecialchars(tool_format_bytes($metrics['disk']['used_bytes'] ?? null), ENT_QUOTES, 'UTF-8') . ' (' . number_format($usagePercentage, 2) . '%)</dd>';
                    echo '<dt class="col-sm-6 text-muted">Free</dt><dd class="col-sm-6">' . htmlspecialchars(tool_format_bytes($metrics['disk']['free_bytes'] ?? null), ENT_QUOTES, 'UTF-8') . '</dd>';
                echo '</dl>';
            echo '</div>';
        echo '</div>';
    echo '</div>';
}

tool_render_footer();
