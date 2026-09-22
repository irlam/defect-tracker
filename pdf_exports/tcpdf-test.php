<?php
// Display all errors
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>TCPDF Path Test</h1>";

// Define the document root and absolute path
$document_root = $_SERVER['DOCUMENT_ROOT'];
echo "<p>Document Root: " . htmlspecialchars($document_root) . "</p>";

// Try all possible TCPDF locations
$possible_paths = [
    $document_root . '/includes/tcpdf/tcpdf.php',
    $document_root . '/tcpdf/tcpdf.php',
    $document_root . '/vendor/tecnickcom/tcpdf/tcpdf.php',
    dirname($document_root) . '/includes/tcpdf/tcpdf.php',
    dirname(__DIR__) . '/includes/tcpdf/tcpdf.php',
    __DIR__ . '/../includes/tcpdf/tcpdf.php'
];

foreach ($possible_paths as $path) {
    echo "<p>Testing path: " . htmlspecialchars($path) . " - ";
    if (file_exists($path)) {
        echo "<span style='color:green'>File exists!</span>";
        echo " (Size: " . filesize($path) . " bytes)";
        
        // Test loading the file
        try {
            require_once($path);
            if (class_exists('TCPDF')) {
                echo " <strong style='color:green'>✓ TCPDF class found!</strong>";
                echo " <strong>THIS IS THE CORRECT PATH</strong>";
            } else {
                echo " <span style='color:red'>✗ File loaded but TCPDF class not found!</span>";
            }
        } catch (Exception $e) {
            echo " <span style='color:red'>Error loading file: " . htmlspecialchars($e->getMessage()) . "</span>";
        }
    } else {
        echo "<span style='color:red'>File not found</span>";
    }
    echo "</p>";
}
?>