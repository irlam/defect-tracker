<?php
session_start();
require_once 'auth.php';
require_once 'config.php';

if (!isset($_GET['file']) || empty($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$backupFile = $_GET['file'];
$backupPath = BACKUP_DIR . '/' . $backupFile;

// Validate backup file exists
if (!file_exists($backupPath)) {
    header('Location: index.php');
    exit;
}

// Set headers for download
header('Content-Description: File Transfer');
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . basename($backupPath) . '"');
header('Content-Length: ' . filesize($backupPath));
header('Pragma: no-cache');
header('Expires: 0');

// Output file and exit
readfile($backupPath);
exit;
?>