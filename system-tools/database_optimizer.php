<?php
/**
 * system-tools/database_optimizer.php
 * Database optimization and analysis tool for the defect tracker
 */

declare(strict_types=1);

class DatabaseOptimizer
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Return the full analysis report.
     */
    public function analyze(): array
    {
        return [
            'table_analysis' => $this->analyzeTableSizes(),
            'index_analysis' => $this->analyzeIndexes(),
            'query_optimization' => $this->suggestQueryOptimizations(),
            'maintenance_suggestions' => $this->getMaintenanceSuggestions(),
            'sql_suggestions' => $this->generateOptimizationSQL(),
        ];
    }

    /**
     * Backwards compatibility shim.
     */
    public function analyzeDatabase(): array
    {
        return $this->analyze();
    }

    private function analyzeTableSizes(): array
    {
        $query = "
            SELECT 
                TABLE_NAME,
                TABLE_ROWS,
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb,
                ROUND((DATA_LENGTH / 1024 / 1024), 2) AS data_mb,
                ROUND((INDEX_LENGTH / 1024 / 1024), 2) AS index_mb
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            LIMIT 12
        ";

        $stmt = $this->db->query($query);

        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    private function analyzeIndexes(): array
    {
        $suggestions = [];

        $query = "
            SELECT t.TABLE_NAME
            FROM information_schema.TABLES t
            LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                ON t.TABLE_NAME = tc.TABLE_NAME 
                AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
                AND tc.TABLE_SCHEMA = DATABASE()
            WHERE t.TABLE_SCHEMA = DATABASE()
                AND t.TABLE_TYPE = 'BASE TABLE'
                AND tc.CONSTRAINT_NAME IS NULL
        ";

        $stmt = $this->db->query($query);
        if ($stmt) {
            $tablesWithoutPK = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($tablesWithoutPK)) {
                $suggestions['missing_primary_keys'] = $tablesWithoutPK;
            }
        }

        $query = "
            SELECT 
                s.TABLE_NAME,
                s.INDEX_NAME
            FROM information_schema.STATISTICS s
            LEFT JOIN information_schema.INDEX_STATISTICS i
                ON s.TABLE_SCHEMA = i.TABLE_SCHEMA
                AND s.TABLE_NAME = i.TABLE_NAME  
                AND s.INDEX_NAME = i.INDEX_NAME
            WHERE s.TABLE_SCHEMA = DATABASE()
                AND s.INDEX_NAME != 'PRIMARY'
                AND i.INDEX_NAME IS NULL
        ";

        try {
            $stmt = $this->db->query($query);
            if ($stmt) {
                $unusedIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (!empty($unusedIndexes)) {
                    $suggestions['potentially_unused_indexes'] = $unusedIndexes;
                }
            }
        } catch (Throwable $e) {
            // INDEX_STATISTICS might not be available on all platforms
        }

        return $suggestions;
    }

    private function suggestQueryOptimizations(): array
    {
        return [
            'general' => [
                'Replace SELECT * queries with specific column names.',
                'Add indexes on frequently queried columns (user_id, status, created_at).',
                'Use LIMIT clauses for large result sets.',
                'Consider pagination for listing pages.',
                'Use prepared statements for all queries (already implemented).',
                'Avoid N+1 query patterns by leveraging JOINs.',
            ],
            'defects_table' => [
                'Consider adding a composite index on (status, assigned_to).',
                'Add an index on created_at for date filtering.',
                'Add an index on project_id if defects are frequently filtered by project.',
            ],
            'users_table' => [
                'Ensure email has a unique index.',
                'Add an index on (user_type, is_active) for role filtering.',
            ],
        ];
    }

    private function getMaintenanceSuggestions(): array
    {
        $suggestions = [];

        $query = "
            SELECT 
                TABLE_NAME,
                ROUND((DATA_FREE / 1024 / 1024), 2) AS free_mb
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
                AND DATA_FREE > 0
            ORDER BY DATA_FREE DESC
            LIMIT 10
        ";

        $stmt = $this->db->query($query);
        if ($stmt) {
            $tablesWithFreeSpace = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($tablesWithFreeSpace)) {
                $suggestions['tables_to_optimize'] = $tablesWithFreeSpace;
            }
        }

        $suggestions['maintenance_tasks'] = [
            'Run OPTIMIZE TABLE on fragmented tables.',
            'Set up automated backups and verify restoration process.',
            'Monitor the slow query log for expensive queries.',
            'Review large tables for archival opportunities.',
            'Refresh index statistics periodically.',
        ];

        return $suggestions;
    }

    public function generateOptimizationSQL(): array
    {
        return [
            'suggested_indexes' => [
                "ALTER TABLE defects ADD INDEX idx_status_assigned (status, assigned_to);",
                "ALTER TABLE defects ADD INDEX idx_created_at (created_at);",
                "ALTER TABLE defects ADD INDEX idx_project_status (project_id, status);",
                "ALTER TABLE users ADD INDEX idx_type_active (user_type, is_active);",
                "ALTER TABLE user_activity_logs ADD INDEX idx_user_created (user_id, created_at);",
            ],
            'table_optimization' => [
                "OPTIMIZE TABLE defects;",
                "OPTIMIZE TABLE users;",
                "OPTIMIZE TABLE contractors;",
                "ANALYZE TABLE defects;",
                "ANALYZE TABLE users;",
            ],
        ];
    }
}

function databaseOptimizerPrintCli(array $report): void
{
    echo "=== Database Optimization Report ===\n\n";

    echo "Top Tables by Size\n-------------------\n";
    if (!empty($report['table_analysis'])) {
        foreach ($report['table_analysis'] as $table) {
            $name = $table['TABLE_NAME'] ?? 'n/a';
            $rows = number_format((int)($table['TABLE_ROWS'] ?? 0));
            $size = number_format((float)($table['size_mb'] ?? 0), 2);
            echo sprintf("%-30s Rows: %10s | Size: %s MB\n", $name, $rows, $size);
        }
    } else {
        echo "No table metadata available.\n";
    }

    if (!empty($report['index_analysis']['missing_primary_keys'])) {
        echo "\nTables Missing Primary Keys:\n";
        foreach ($report['index_analysis']['missing_primary_keys'] as $table) {
            echo " - {$table}\n";
        }
    }

    if (!empty($report['index_analysis']['potentially_unused_indexes'])) {
        echo "\nPotentially Unused Indexes:\n";
        foreach ($report['index_analysis']['potentially_unused_indexes'] as $index) {
            echo sprintf(" - %s.%s\n", $index['TABLE_NAME'], $index['INDEX_NAME']);
        }
    }

    echo "\nSuggested SQL Statements\n-------------------------\n";
    foreach ($report['sql_suggestions']['suggested_indexes'] as $statement) {
        echo $statement . "\n";
    }
    foreach ($report['sql_suggestions']['table_optimization'] as $statement) {
        echo $statement . "\n";
    }

    echo "\nRemember to test changes in a staging environment first.\n";
}

if (PHP_SAPI === 'cli') {
    require_once __DIR__ . '/../config/database.php';

    try {
        $database = new Database();
        $db = $database->getConnection();
        $optimizer = new DatabaseOptimizer($db);
        $report = $optimizer->analyze();
        databaseOptimizerPrintCli($report);
    } catch (Throwable $e) {
        fwrite(STDERR, 'Database optimizer error: ' . $e->getMessage() . PHP_EOL);
        exit(1);
    }

    exit(0);
}

require_once __DIR__ . '/includes/tool_bootstrap.php';

$optimizerError = null;
$optimizerReport = null;

if ($db instanceof PDO) {
    try {
        $optimizer = new DatabaseOptimizer($db);
        $optimizerReport = $optimizer->analyze();
    } catch (Throwable $e) {
        $optimizerError = $e->getMessage();
    }
} else {
    $optimizerError = $dbErrorMessage ?? 'Database connection unavailable.';
}

tool_render_header(
    'Database Optimizer',
    'Analyze and optimize key tables to maintain performance.',
    [
        ['label' => 'Admin Dashboard', 'href' => '../admin.php'],
        ['label' => 'Database Optimizer'],
    ]
);

if ($optimizerError !== null) {
    echo '<div class="alert alert-danger" role="alert">' . htmlspecialchars($optimizerError, ENT_QUOTES, 'UTF-8') . '</div>';
    tool_render_footer();
    return;
}

$tableAnalysis = $optimizerReport['table_analysis'] ?? [];
$indexAnalysis = $optimizerReport['index_analysis'] ?? [];
$queryGuidance = $optimizerReport['query_optimization'] ?? [];
$maintenance = $optimizerReport['maintenance_suggestions'] ?? [];
$sqlSuggestions = $optimizerReport['sql_suggestions'] ?? [];

$missingPrimaryKeys = $indexAnalysis['missing_primary_keys'] ?? [];
$unusedIndexes = $indexAnalysis['potentially_unused_indexes'] ?? [];
$tablesToOptimize = $maintenance['tables_to_optimize'] ?? [];
$maintenanceTasks = $maintenance['maintenance_tasks'] ?? [];

$indexSql = $sqlSuggestions['suggested_indexes'] ?? [];
$tableSql = $sqlSuggestions['table_optimization'] ?? [];

echo '<div class="row g-4">';

echo '<div class="col-12 col-xl-6">';
    echo '<div class="tool-card h-100">';
        echo '<div class="tool-card-header">';
            echo '<h2 class="h5 mb-0"><i class="bx bx-table me-2"></i>Table Size Overview</h2>';
        echo '</div>';
        echo '<div class="tool-card-body">';
            if (!empty($tableAnalysis)) {
                echo '<div class="table-responsive">';
                    echo '<table class="table table-dark table-striped align-middle mb-0">';
                        echo '<thead><tr><th>Table</th><th class="text-end">Rows</th><th class="text-end">Size (MB)</th><th class="text-end">Data</th><th class="text-end">Index</th></tr></thead>';
                        echo '<tbody>';
                            foreach ($tableAnalysis as $table) {
                                $name = htmlspecialchars((string)($table['TABLE_NAME'] ?? 'n/a'), ENT_QUOTES, 'UTF-8');
                                $rows = number_format((int)($table['TABLE_ROWS'] ?? 0));
                                $size = number_format((float)($table['size_mb'] ?? 0), 2);
                                $dataMb = number_format((float)($table['data_mb'] ?? 0), 2);
                                $indexMb = number_format((float)($table['index_mb'] ?? 0), 2);
                                echo "<tr><td>{$name}</td><td class=\"text-end\">{$rows}</td><td class=\"text-end\">{$size}</td><td class=\"text-end\">{$dataMb}</td><td class=\"text-end\">{$indexMb}</td></tr>";
                            }
                        echo '</tbody>';
                    echo '</table>';
                echo '</div>';
            } else {
                echo '<p class="text-muted mb-0">No table metadata available.</p>';
            }
        echo '</div>';
    echo '</div>';
echo '</div>';

echo '<div class="col-12 col-xl-6">';
    echo '<div class="tool-card h-100">';
        echo '<div class="tool-card-header">';
            echo '<h2 class="h5 mb-0"><i class="bx bx-key me-2"></i>Index Analysis</h2>';
        echo '</div>';
        echo '<div class="tool-card-body">';
            if (!empty($missingPrimaryKeys) || !empty($unusedIndexes)) {
                if (!empty($missingPrimaryKeys)) {
                    echo '<h3 class="h6 text-uppercase text-muted mt-0">Missing Primary Keys</h3>';
                    echo '<ul class="list-group list-group-flush mb-3">';
                        foreach ($missingPrimaryKeys as $table) {
                            $name = htmlspecialchars((string)$table, ENT_QUOTES, 'UTF-8');
                            echo "<li class=\"list-group-item bg-transparent text-light\"><i class=\"bx bx-error-circle text-warning me-2\"></i>{$name}</li>";
                        }
                    echo '</ul>';
                }
                if (!empty($unusedIndexes)) {
                    echo '<h3 class="h6 text-uppercase text-muted">Potentially Unused Indexes</h3>';
                    echo '<ul class="list-group list-group-flush mb-0">';
                        foreach ($unusedIndexes as $index) {
                            $table = htmlspecialchars((string)($index['TABLE_NAME'] ?? 'n/a'), ENT_QUOTES, 'UTF-8');
                            $name = htmlspecialchars((string)($index['INDEX_NAME'] ?? 'n/a'), ENT_QUOTES, 'UTF-8');
                            echo "<li class=\"list-group-item bg-transparent text-light\"><i class=\"bx bx-shape-square text-info me-2\"></i>{$table}.{$name}</li>";
                        }
                    echo '</ul>';
                }
            } else {
                echo '<p class="text-muted mb-0">No index issues detected.</p>';
            }
        echo '</div>';
    echo '</div>';
echo '</div>';

echo '<div class="col-12 col-xl-6">';
    echo '<div class="tool-card h-100">';
        echo '<div class="tool-card-header">';
            echo '<h2 class="h5 mb-0"><i class="bx bx-line-chart me-2"></i>Query Optimization Tips</h2>';
        echo '</div>';
        echo '<div class="tool-card-body">';
            if (!empty($queryGuidance)) {
                foreach ($queryGuidance as $group => $items) {
                    $title = htmlspecialchars(str_replace('_', ' ', (string)$group), ENT_QUOTES, 'UTF-8');
                    echo '<h3 class="h6 text-uppercase text-muted">' . $title . '</h3>';
                    echo '<ul class="list-group list-group-flush mb-3">';
                        foreach ($items as $tip) {
                            $safeTip = htmlspecialchars((string)$tip, ENT_QUOTES, 'UTF-8');
                            echo "<li class=\"list-group-item bg-transparent text-light\"><i class=\"bx bx-bulb text-primary me-2\"></i>{$safeTip}</li>";
                        }
                    echo '</ul>';
                }
            } else {
                echo '<p class="text-muted mb-0">No query guidance available.</p>';
            }
        echo '</div>';
    echo '</div>';
echo '</div>';

echo '<div class="col-12 col-xl-6">';
    echo '<div class="tool-card h-100">';
        echo '<div class="tool-card-header">';
            echo '<h2 class="h5 mb-0"><i class="bx bx-wrench me-2"></i>Maintenance & SQL</h2>';
        echo '</div>';
        echo '<div class="tool-card-body">';
            if (!empty($tablesToOptimize)) {
                echo '<h3 class="h6 text-uppercase text-muted mt-0">Tables With Free Space</h3>';
                echo '<div class="table-responsive mb-3">';
                    echo '<table class="table table-dark table-sm table-striped align-middle mb-0">';
                        echo '<thead><tr><th>Table</th><th class="text-end">Free Space (MB)</th></tr></thead>';
                        echo '<tbody>';
                            foreach ($tablesToOptimize as $table) {
                                $name = htmlspecialchars((string)($table['TABLE_NAME'] ?? 'n/a'), ENT_QUOTES, 'UTF-8');
                                $free = number_format((float)($table['free_mb'] ?? 0), 2);
                                echo "<tr><td>{$name}</td><td class=\"text-end\">{$free}</td></tr>";
                            }
                        echo '</tbody>';
                    echo '</table>';
                echo '</div>';
            }

            if (!empty($maintenanceTasks)) {
                echo '<h3 class="h6 text-uppercase text-muted">Recommended Tasks</h3>';
                echo '<ul class="list-group list-group-flush mb-3">';
                    foreach ($maintenanceTasks as $task) {
                        $safeTask = htmlspecialchars((string)$task, ENT_QUOTES, 'UTF-8');
                        echo "<li class=\"list-group-item bg-transparent text-light\"><i class=\"bx bx-task text-success me-2\"></i>{$safeTask}</li>";
                    }
                echo '</ul>';
            }

            if (!empty($indexSql) || !empty($tableSql)) {
                echo '<h3 class="h6 text-uppercase text-muted">SQL Statements</h3>';
                echo '<pre class="bg-dark-subtle text-light p-3 rounded small">';
                    foreach ($indexSql as $statement) {
                        $safeStatement = htmlspecialchars((string)$statement, ENT_QUOTES, 'UTF-8');
                        echo $safeStatement . "\n";
                    }
                    foreach ($tableSql as $statement) {
                        $safeStatement = htmlspecialchars((string)$statement, ENT_QUOTES, 'UTF-8');
                        echo $safeStatement . "\n";
                    }
                echo '</pre>';
            }
        echo '</div>';
    echo '</div>';
echo '</div>';

echo '</div>';

tool_render_footer();
