<?php
require_once '../config/database.php';
require_once '../includes/session.php';
require_once '../classes/SystemHealth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $systemHealth = new SystemHealth($db);

    // Get current metrics
    $metrics = [
        'cpu_usage' => sys_getloadavg()[0] * 100,
        'memory_usage' => getServerMemoryUsage(),
        'disk_usage' => getDiskUsage(),
        'database_status' => $systemHealth->checkDatabaseConnection() ? 'good' : 'critical',
        'disk_status' => $systemHealth->checkDiskSpace() ? 'good' : 'warning',
        'system_status' => $systemHealth->checkSystemLoad() ? 'good' : 'warning',
        'timestamp' => '2025-01-14 21:47:42'
    ];

    // Log metrics to database for historical tracking
    $stmt = $db->prepare("
        INSERT INTO system_health_logs (
            metric_name,
            metric_value,
            status,
            created_at
        ) VALUES (?, ?, ?, ?)
    ");

    foreach ($metrics as $name => $value) {
        if ($name !== 'timestamp') {
            $stmt->execute([
                $name,
                is_numeric($value) ? $value : json_encode($value),
                determineStatus($value),
                '2025-01-14 21:47:42'
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'data' => $metrics
    ]);

} catch (Exception $e) {
    error_log("Error getting system metrics: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error retrieving system metrics'
    ]);
}

function getServerMemoryUsage() {
    $free = shell_exec('free');
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem);
    $mem = array_merge($mem);
    return round($mem[2]/$mem[1]*100, 2);
}

function getDiskUsage() {
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    return round(($diskTotal - $diskFree) / $diskTotal * 100, 2);
}

function determineStatus($value) {
    if (is_numeric($value)) {
        if ($value > 90) return 'critical';
        if ($value > 70) return 'warning';
        return 'healthy';
    }
    return $value;
}