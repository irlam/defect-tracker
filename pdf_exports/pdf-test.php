<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define paths to try
$paths = [
    __DIR__ . '/../tcpdf/tcpdf.php',
    $_SERVER['DOCUMENT_ROOT'] . '/tcpdf/tcpdf.php',
    'tcpdf/tcpdf.php'
];

$tcpdfPath = null;
foreach ($paths as $path) {
    if (file_exists($path)) {
        $tcpdfPath = $path;
        echo "Found TCPDF at: " . $path . "<br>";
        break;
    }
}

if (!$tcpdfPath) {
    die("TCPDF not found in any of the expected locations");
}

try {
    require_once($tcpdfPath);
    
    // Create basic PDF
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->SetCreator('Test');
    $pdf->SetAuthor('System');
    $pdf->SetTitle('TCPDF Test');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->AddPage();
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Write(10, 'If you can see this PDF, TCPDF is working correctly.');
    
    // Output as inline (view in browser)
    $pdf->Output('test.pdf', 'I');
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>