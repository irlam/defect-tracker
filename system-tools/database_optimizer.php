<?php
/**
 * _my-tools/database_optimizer.php
 * Database optimization and analysis tool for the defect tracker
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/error_handler.php';

class DatabaseOptimizer {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Analyze table structures and suggest optimizations
     */
    public function analyzeDatabase() {
        $results = [
            'table_analysis' => $this->analyzeTableSizes(),
            'index_analysis' => $this->analyzeIndexes(),
            'query_optimization' => $this->suggestQueryOptimizations(),
            'maintenance_suggestions' => $this->getMaintenanceSuggestions()
        ];
        
        return $results;
    }
    
    /**
     * Analyze table sizes and row counts
     */
    private function analyzeTableSizes() {
        $query = "
            SELECT 
                TABLE_NAME,
                TABLE_ROWS,
                ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size_MB',
                ROUND((DATA_LENGTH / 1024 / 1024), 2) AS 'Data_MB',
                ROUND((INDEX_LENGTH / 1024 / 1024), 2) AS 'Index_MB'
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
            ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Analyze indexes and suggest improvements
     */
    private function analyzeIndexes() {
        $suggestions = [];
        
        // Find tables without primary keys
        $query = "
            SELECT TABLE_NAME
            FROM information_schema.TABLES t
            LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                ON t.TABLE_NAME = tc.TABLE_NAME 
                AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
                AND tc.TABLE_SCHEMA = DATABASE()
            WHERE t.TABLE_SCHEMA = DATABASE()
                AND tc.CONSTRAINT_NAME IS NULL
        ";
        
        $stmt = $this->db->query($query);
        $tablesWithoutPK = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($tablesWithoutPK)) {
            $suggestions['missing_primary_keys'] = $tablesWithoutPK;
        }
        
        // Find unused indexes
        $query = "
            SELECT 
                s.TABLE_SCHEMA,
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
            $unusedIndexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($unusedIndexes)) {
                $suggestions['potentially_unused_indexes'] = $unusedIndexes;
            }
        } catch (Exception $e) {
            // INDEX_STATISTICS might not be available in all MySQL versions
        }
        
        return $suggestions;
    }
    
    /**
     * Suggest query optimizations
     */
    private function suggestQueryOptimizations() {
        $suggestions = [];
        
        // Common optimization suggestions
        $suggestions['general'] = [
            'Replace SELECT * queries with specific column names',
            'Add indexes on frequently queried columns (user_id, status, created_at)',
            'Use LIMIT clauses for large result sets',
            'Consider pagination for listing pages',
            'Use prepared statements for all queries (already implemented)',
            'Avoid N+1 query problems by using JOINs'
        ];
        
        // Table-specific suggestions based on common patterns
        $suggestions['defects_table'] = [
            'Consider adding composite index on (status, assigned_to)',
            'Consider adding index on created_at for date filtering',
            'Consider adding index on project_id if frequently filtered'
        ];
        
        $suggestions['users_table'] = [
            'Ensure email has unique index',
            'Consider adding index on (user_type, is_active) for role filtering'
        ];
        
        return $suggestions;
    }
    
    /**
     * Get maintenance suggestions
     */
    private function getMaintenanceSuggestions() {
        $suggestions = [];
        
        // Check for tables that might need optimization
        $query = "
            SELECT 
                TABLE_NAME,
                ROUND((DATA_FREE / 1024 / 1024), 2) AS 'Free_MB'
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = DATABASE()
                AND DATA_FREE > 0
            ORDER BY DATA_FREE DESC
        ";
        
        $stmt = $this->db->query($query);
        $tablesWithFreeSpace = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($tablesWithFreeSpace)) {
            $suggestions['tables_to_optimize'] = $tablesWithFreeSpace;
        }
        
        $suggestions['maintenance_tasks'] = [
            'Run OPTIMIZE TABLE on tables with fragmentation',
            'Consider archiving old defects/logs if tables are large',
            'Set up automated backup schedule',
            'Monitor slow query log',
            'Regular index statistics updates'
        ];
        
        return $suggestions;
    }
    
    /**
     * Generate SQL statements for common optimizations
     */
    public function generateOptimizationSQL() {
        $sql = [];
        
        // Common indexes that might be useful
        $sql['suggested_indexes'] = [
            "ALTER TABLE defects ADD INDEX idx_status_assigned (status, assigned_to);",
            "ALTER TABLE defects ADD INDEX idx_created_at (created_at);", 
            "ALTER TABLE defects ADD INDEX idx_project_status (project_id, status);",
            "ALTER TABLE users ADD INDEX idx_type_active (user_type, is_active);",
            "ALTER TABLE user_activity_logs ADD INDEX idx_user_created (user_id, created_at);"
        ];
        
        // Table optimization
        $sql['table_optimization'] = [
            "OPTIMIZE TABLE defects;",
            "OPTIMIZE TABLE users;",
            "OPTIMIZE TABLE contractors;",
            "ANALYZE TABLE defects;",
            "ANALYZE TABLE users;"
        ];
        
        return $sql;
    }
    
    /**
     * Display analysis results as HTML
     */
    public function displayAnalysis() {
        $analysis = $this->analyzeDatabase();
        
        echo "<h2>Database Analysis Report</h2>";
        
        // Table sizes
        echo "<h3>Table Sizes</h3>";
        echo "<table class='table table-striped'>";
        echo "<tr><th>Table</th><th>Rows</th><th>Size (MB)</th><th>Data (MB)</th><th>Index (MB)</th></tr>";
        foreach ($analysis['table_analysis'] as $table) {
            echo "<tr>";
            echo "<td>{$table['TABLE_NAME']}</td>";
            echo "<td>" . number_format($table['TABLE_ROWS']) . "</td>";
            echo "<td>{$table['Size_MB']}</td>";
            echo "<td>{$table['Data_MB']}</td>";
            echo "<td>{$table['Index_MB']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // Index analysis
        if (!empty($analysis['index_analysis'])) {
            echo "<h3>Index Analysis</h3>";
            
            if (isset($analysis['index_analysis']['missing_primary_keys'])) {
                echo "<div class='alert alert-warning'>";
                echo "<strong>Tables missing primary keys:</strong> " . 
                     implode(', ', $analysis['index_analysis']['missing_primary_keys']);
                echo "</div>";
            }
        }
        
        // Optimization suggestions
        echo "<h3>Query Optimization Suggestions</h3>";
        foreach ($analysis['query_optimization'] as $category => $suggestions) {
            echo "<h4>" . ucwords(str_replace('_', ' ', $category)) . "</h4>";
            echo "<ul>";
            foreach ($suggestions as $suggestion) {
                echo "<li>{$suggestion}</li>";
            }
            echo "</ul>";
        }
        
        // Generated SQL
        $sql = $this->generateOptimizationSQL();
        echo "<h3>Suggested SQL Optimizations</h3>";
        echo "<div class='alert alert-info'>";
        echo "<strong>Note:</strong> Test these on a development environment first!";
        echo "</div>";
        
        echo "<h4>Suggested Indexes</h4>";
        echo "<pre>";
        foreach ($sql['suggested_indexes'] as $statement) {
            echo htmlspecialchars($statement) . "\n";
        }
        echo "</pre>";
        
        echo "<h4>Table Optimization</h4>";
        echo "<pre>";
        foreach ($sql['table_optimization'] as $statement) {
            echo htmlspecialchars($statement) . "\n";
        }
        echo "</pre>";
    }
}

// If accessed directly, display the analysis
if (basename($_SERVER['SCRIPT_NAME']) === 'database_optimizer.php') {
    session_start();
    
    // Basic authentication check
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
        die('Access denied. Please login first.');
    }
    
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Database Optimizer</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container mt-4">
            <h1>Database Optimization Tool</h1>
            <?php
            try {
                $optimizer = new DatabaseOptimizer();
                $optimizer->displayAnalysis();
            } catch (Exception $e) {
                echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
            }
            ?>
        </div>
    </body>
    </html>
    <?php
}