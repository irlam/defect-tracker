<?php
// Run each order in a fresh PHP process. No database connection is made.
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$order = $argv[1] ?? 'constants-first';
if ($order === 'constants-first') {
    require __DIR__ . '/../config/constants.php';
    $expected = MAX_FILE_SIZE;
    require __DIR__ . '/../config/database.php';
} else {
    require __DIR__ . '/../config/database.php';
    $expected = MAX_FILE_SIZE;
    require __DIR__ . '/../config/constants.php';
}
if (MAX_FILE_SIZE !== $expected || headers_sent()) {
    throw new RuntimeException('Configuration changed the upload limit or emitted output.');
}
echo "PASS: $order; upload limit preserved and headers available.\n";
