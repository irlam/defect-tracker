<?php
/**
 * Destructive project reset utility.
 * Removes project/user-generated data while preserving administrator accounts,
 * roles/permissions, system configuration and the database structure.
 */
if (php_sapi_name() !== 'cli') {
    if (!isset($_SESSION['executing_cleanup'])) {
        require_once __DIR__ . '/../config/database.php';
        require_once __DIR__ . '/../includes/session.php';
        if (($_SESSION['user_type'] ?? '') !== 'admin') {
            http_response_code(403);
            exit('Unauthorized access.');
        }
    }
} else {
    require_once __DIR__ . '/../config/database.php';
}

function validateTableName(string $table): bool {
    return (bool) preg_match('/^[a-zA-Z0-9_]+$/', $table);
}

function cleanDatabase(PDO $db): array {
    $results = [];
    $deleteAll = [
        'defect_images','defect_comments','defect_history','defect_assignments','acceptance_history',
        'comments','notifications','notification_log','sync_conflicts','sync_devices','sync_logs','sync_queue',
        'activity_logs','system_logs','action_log','audit_logs','export_logs','maintenance_log',
        'training_progress',
        'floor_plans','defects','projects'
    ];

    try {
        $db->beginTransaction();
        $db->exec('SET FOREIGN_KEY_CHECKS=0');

        $objectTypeStmt = $db->prepare("
            SELECT TABLE_TYPE
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
            LIMIT 1
        ");

        foreach ($deleteAll as $table) {
            if (!validateTableName($table)) throw new RuntimeException("Invalid table name: {$table}");

            $objectTypeStmt->execute([$table]);
            $objectType = $objectTypeStmt->fetchColumn();

            if ($objectType === false) {
                $results[] = "• {$table}: table not present";
                continue;
            }

            if (strtoupper((string) $objectType) !== 'BASE TABLE') {
                $results[] = "• {$table}: " . strtolower((string) $objectType) . " skipped";
                continue;
            }

            $count = $db->exec("DELETE FROM `{$table}`");
            $results[] = "✓ {$table}: " . (int)$count . ' rows removed';
        }

        $adminIds = $db->query("SELECT id FROM users WHERE user_type = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
        if (!$adminIds) throw new RuntimeException('Cleanup stopped: no administrator account exists to preserve.');
        $placeholders = implode(',', array_fill(0, count($adminIds), '?'));

        foreach (['user_logs','user_sessions','user_permissions','user_roles','user_recent_descriptions'] as $table) {
            try {
                $stmt = $db->prepare("DELETE FROM `{$table}` WHERE user_id NOT IN ({$placeholders})");
                $stmt->execute($adminIds);
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S02') throw $e;
            }
        }

        try {
            $stmt = $db->prepare("DELETE FROM contractors WHERE id NOT IN (SELECT contractor_id FROM users WHERE id IN ({$placeholders}) AND contractor_id IS NOT NULL)");
            $stmt->execute($adminIds);
        } catch (PDOException $e) {
            if ($e->getCode() !== '42S02') throw $e;
        }

        $stmt = $db->prepare("DELETE FROM users WHERE id NOT IN ({$placeholders})");
        $stmt->execute($adminIds);

        $db->exec('SET FOREIGN_KEY_CHECKS=1');
        $db->commit();
        $results[] = '✓ Database cleanup committed successfully.';
        return ['success'=>true,'results'=>$results];
    } catch (Throwable $e) {
        try { $db->exec('SET FOREIGN_KEY_CHECKS=1'); } catch (Throwable $ignored) {}
        if ($db->inTransaction()) $db->rollBack();
        $results[] = '✗ Cleanup failed: ' . $e->getMessage();
        return ['success'=>false,'results'=>$results];
    }
}

function removeRuntimeFiles(string $directory, array &$results): void {
    if (!is_dir($directory)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    $deleted = 0;
    foreach ($iterator as $item) {
        $name = $item->getFilename();
        if (in_array($name, ['.gitkeep','.htaccess'], true)) continue;
        if ($item->isFile() || $item->isLink()) {
            if (@unlink($item->getPathname())) $deleted++;
        } elseif ($item->isDir()) {
            @rmdir($item->getPathname());
        }
    }
    $results[] = '✓ ' . basename($directory) . ": {$deleted} files removed";
}

function cleanUploadedFiles(): array {
    $results = [];
    $base = dirname(__DIR__);
    foreach ([$base.'/uploads', $base.'/assets/floor_plans'] as $directory) {
        removeRuntimeFiles($directory, $results);
    }
    foreach (glob($base.'/pdf_exports/*.{pdf,tmp}', GLOB_BRACE) ?: [] as $file) {
        if (is_file($file)) @unlink($file);
    }
    $results[] = '✓ Generated PDF output removed.';
    return ['success'=>true,'results'=>$results];
}

function executeCleanup(): bool {
    echo "\n" . str_repeat('=',70) . "\nPROJECT DATA CLEANUP\n" . str_repeat('=',70) . "\n";
    echo "WARNING: all project/user-generated data will be permanently removed.\n";
    echo "Administrator accounts and system configuration are preserved.\n\n";

    if (php_sapi_name() === 'cli' && !defined('CLEANUP_SKIP_CONFIRMATION')) {
        echo "Type RESET to continue: ";
        $answer = trim((string) fgets(STDIN));
        if ($answer !== 'RESET') { echo "Cleanup cancelled.\n"; return false; }
    }

    $db = (new Database())->getConnection();
    if (!$db) { echo "✗ Database connection failed.\n"; return false; }

    $dbResult = cleanDatabase($db);
    foreach ($dbResult['results'] as $line) echo $line . "\n";
    if (!$dbResult['success']) return false;

    $fileResult = cleanUploadedFiles();
    foreach ($fileResult['results'] as $line) echo $line . "\n";
    echo "\nCleanup complete. Create and verify a fresh backup before cloning this installation.\n";
    return true;
}

$cleanupSucceeded = null;
if (php_sapi_name() === 'cli' || (($_SESSION['user_type'] ?? '') === 'admin')) {
    $cleanupSucceeded = executeCleanup();
}
