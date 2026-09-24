<?php
declare(strict_types=1);

/**
 * Lightweight read-only system health helper used by legacy admin screens.
 *
 * This class intentionally does not provision schema or seed configuration.
 * The full diagnostics page lives in /system-tools/system_health.php.
 */
final class SystemHealth
{
    private PDO $db;
    private array $metrics = [];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function checkDatabaseConnection(): bool
    {
        try {
            $row = $this->db
                ->query('SELECT DATABASE() AS database_name, VERSION() AS server_version, NOW() AS server_time')
                ->fetch(PDO::FETCH_ASSOC) ?: [];

            $this->metrics['database'] = [
                'healthy' => true,
                'database_name' => $row['database_name'] ?? null,
                'server_version' => $row['server_version'] ?? null,
                'server_time' => $row['server_time'] ?? null,
            ];

            return true;
        } catch (Throwable $e) {
            $this->metrics['database'] = [
                'healthy' => false,
                'error' => $e->getMessage(),
            ];
            return false;
        }
    }

    public function checkDiskSpace(): bool
    {
        $root = dirname(__DIR__);
        $total = @disk_total_space($root);
        $free = @disk_free_space($root);

        if ($total === false || $free === false || $total <= 0) {
            $this->metrics['disk'] = [
                'healthy' => false,
                'error' => 'Disk usage unavailable.',
            ];
            return false;
        }

        $used = $total - $free;
        $percent = ($used / $total) * 100;
        $healthy = $percent < 90;

        $this->metrics['disk'] = [
            'healthy' => $healthy,
            'total_bytes' => (int)$total,
            'used_bytes' => (int)$used,
            'free_bytes' => (int)$free,
            'usage_percentage' => round($percent, 2),
        ];

        return $healthy;
    }

    public function checkSystemLoad(): bool
    {
        if (!function_exists('sys_getloadavg')) {
            $this->metrics['system_load'] = [
                'healthy' => true,
                'available' => false,
            ];
            return true;
        }

        $load = sys_getloadavg();
        if (!is_array($load) || count($load) < 3) {
            $this->metrics['system_load'] = [
                'healthy' => true,
                'available' => false,
            ];
            return true;
        }

        $oneMinute = (float)$load[0];
        $healthy = $oneMinute < 3.0;

        $this->metrics['system_load'] = [
            'healthy' => $healthy,
            'available' => true,
            '1min' => (float)$load[0],
            '5min' => (float)$load[1],
            '15min' => (float)$load[2],
        ];

        return $healthy;
    }

    public function getMetrics(): array
    {
        // Populate a consistent snapshot when called before individual checks.
        $this->checkDatabaseConnection();
        $this->checkDiskSpace();
        $this->checkSystemLoad();

        return $this->metrics;
    }
}
