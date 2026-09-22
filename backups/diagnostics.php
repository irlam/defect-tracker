<?php
session_start();
require_once 'auth.php';
require_once 'config.php';

// Create basic system diagnostics
function check_php_extensions() {
    $required = array('zip', 'mysqli', 'pdo_mysql');
    $missing = array();
    
    foreach ($required as $ext) {
        if (!extension_loaded($ext)) {
            $missing[] = $ext;
        }
    }
    
    return $missing;
}

function check_mysql_connection() {
    try {
        $mysqli = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if (!$mysqli) {
            return array(
                'status' => false,
                'error' => mysqli_connect_error()
            );
        }
        
        // Check if we can query
        $result = mysqli_query($mysqli, "SHOW TABLES");
        if (!$result) {
            return array(
                'status' => false,
                'error' => mysqli_error($mysqli)
            );
        }
        
        // Count tables
        $tables = array();
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }
        
        mysqli_close($mysqli);
        
        return array(
            'status' => true,
            'tables_count' => count($tables),
            'tables' => $tables
        );
    } catch (Exception $e) {
        return array(
            'status' => false,
            'error' => $e->getMessage()
        );
    }
}

function check_mysqldump() {
    exec('which mysqldump', $output, $returnVar);
    
    if ($returnVar !== 0) {
        return array(
            'status' => false,
            'message' => 'mysqldump command not found'
        );
    }
    
    return array(
        'status' => true,
        'path' => $output[0]
    );
}

function check_directory_permissions() {
    $dirs = array(
        BACKUP_DIR => is_writable(BACKUP_DIR),
        WEBSITE_ROOT => is_readable(WEBSITE_ROOT)
    );
    
    return $dirs;
}

// Run diagnostic tests
$diagnostics = array(
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'],
    'current_time' => date('d-m-Y H:i:s'),
    'timezone' => date_default_timezone_get(),
    'missing_extensions' => check_php_extensions(),
    'mysql_connection' => check_mysql_connection(),
    'mysqldump' => check_mysqldump(),
    'directory_permissions' => check_directory_permissions()
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup System Diagnostics</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Backup System Diagnostics</h1>
            <p>Current user: <?php echo htmlspecialchars(CURRENT_USER); ?></p>
            <p>Current time: <?php echo date('d-m-Y H:i:s'); ?></p>
            <p><a href="index.php">&laquo; Back to Backup Manager</a></p>
        </header>
        
        <section>
            <h2>System Information</h2>
            <table>
                <tr>
                    <th>PHP Version</th>
                    <td><?php echo $diagnostics['php_version']; ?></td>
                </tr>
                <tr>
                    <th>Web Server</th>
                    <td><?php echo $diagnostics['server']; ?></td>
                </tr>
                <tr>
                    <th>Current Time</th>
                    <td><?php echo $diagnostics['current_time']; ?></td>
                </tr>
                <tr>
                    <th>Timezone</th>
                    <td><?php echo $diagnostics['timezone']; ?></td>
                </tr>
            </table>
        </section>
        
        <section>
            <h2>PHP Extensions</h2>
            <?php if (empty($diagnostics['missing_extensions'])): ?>
                <p class="success">All required PHP extensions are installed.</p>
            <?php else: ?>
                <p class="error">Missing required PHP extensions: <?php echo implode(', ', $diagnostics['missing_extensions']); ?></p>
            <?php endif; ?>
        </section>
        
        <section>
            <h2>MySQL Connection</h2>
            <?php if ($diagnostics['mysql_connection']['status']): ?>
                <p class="success">Database connection successful.</p>
                <p>Found <?php echo $diagnostics['mysql_connection']['tables_count']; ?> tables in the database.</p>
                <ul>
                    <?php foreach ($diagnostics['mysql_connection']['tables'] as $table): ?>
                        <li><?php echo htmlspecialchars($table); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="error">Database connection failed: <?php echo htmlspecialchars($diagnostics['mysql_connection']['error']); ?></p>
            <?php endif; ?>
        </section>
        
        <section>
            <h2>mysqldump Command</h2>
            <?php if ($diagnostics['mysqldump']['status']): ?>
                <p class="success">mysqldump command is available at: <?php echo htmlspecialchars($diagnostics['mysqldump']['path']); ?></p>
            <?php else: ?>
                <p class="warning">mysqldump command is not available. PHP will use its internal functions for database backup.</p>
            <?php endif; ?>
        </section>
        
        <section>
            <h2>Directory Permissions</h2>
            <table>
                <tr>
                    <th>Directory</th>
                    <th>Permission</th>
                </tr>
                <?php foreach ($diagnostics['directory_permissions'] as $dir => $writable): ?>
                <tr>
                    <td><?php echo htmlspecialchars($dir); ?></td>
                    <td class="<?php echo $writable ? 'success' : 'error'; ?>">
                        <?php echo $writable ? 'Writable/Readable' : 'Not Writable/Readable'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </section>
    </div>
</body>
</html>