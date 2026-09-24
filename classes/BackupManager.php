<?php
declare(strict_types=1);

/**
 * Compatibility loader for the maintained backup manager.
 *
 * Legacy admin/API pages still require classes/BackupManager.php, while the
 * implementation lives under /backups.
 */
require_once __DIR__ . '/../backups/config.php';
require_once __DIR__ . '/../backups/functions.php';
require_once __DIR__ . '/../backups/backup-manager.php';
