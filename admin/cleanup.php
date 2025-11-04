#!/usr/bin/env php
<?php
/**
 * Command Line Cleanup Script
 * 
 * This script provides a command-line interface for cleaning the website.
 * 
 * Usage:
 *   php cleanup.php [--yes]
 * 
 * Options:
 *   --yes    Skip confirmation prompt (use with caution!)
 * 
 * Created: 2025-11-04
 */

// Change to the script's directory
chdir(__DIR__);

// Check if running from command line
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Parse command line arguments
$skipConfirmation = false;
if (isset($argv[1]) && $argv[1] === '--yes') {
    $skipConfirmation = true;
}

// Include the cleanup script
require_once 'cleanup_website.php';
