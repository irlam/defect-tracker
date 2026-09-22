<?php
// Turn off error display for production - prevents headers already sent errors
ini_set('display_errors', 0);
error_reporting(0);

// Include TCPDF library using the correct path (adjust as needed)
require_once($_SERVER['DOCUMENT_ROOT'] . '/tcpdf/tcpdf.php'); // Absolute path

// Get query parameters - keep original format for query but display in UK format
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Format dates for display in UK format (DD/MM/YYYY)
$start_date_display = date('d/m/Y', strtotime($start_date));
$end_date_display = date('d/m/Y', strtotime($end_date));

// Get current UTC date and time dynamically
$utc_datetime = gmdate('Y-m-d H:i:s'); // Gets current UTC time
$current_datetime = date('d/m/Y H:i', strtotime($utc_datetime)); // Convert to UK format

// Get current logged-in user from session
session_start();
$current_user = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'System User';

// Create new PDF document - set to portrait to fit more rows vertically
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('Defect Tracker');
$pdf->SetAuthor('System');
$pdf->SetTitle('Contractor Defects Report');
$pdf->SetSubject('Defect Management Report');

// Set default header data - use UK date format
$pdf->setHeaderData('', 0, 'Defect Management Report', 'Period: ' . $start_date_display . ' to ' . $end_date_display);

// Set header and footer fonts
$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

// Set default monospaced font
$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

// Set margins - reduce margins to fit more content
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// Set auto page breaks
$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', 'B', 16);

// Title
$pdf->Cell(0, 10, 'Contractor Performance Report', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 10, 'Period: ' . $start_date_display . ' to ' . $end_date_display, 0, 1, 'C');

// Center-aligned generation information
$pdf->Cell(0, 5, 'Report Generated: ' . $current_datetime . ' by ' . $current_user, 0, 1, 'C');

// Connect to database
require_once('../config/database.php');
$database = new Database();
$db = $database->getConnection();

// Get contractor performance data
$contractorPerformanceQuery = "
    SELECT 
        c.id,
        c.company_name,
        c.logo,
        c.trade,
        COUNT(DISTINCT CASE WHEN d.deleted_at IS NULL THEN d.id ELSE NULL END) as total_defects,
        SUM(CASE WHEN d.status = 'open' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as open_defects,
        SUM(CASE WHEN d.status = 'pending' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as pending_defects,
        SUM(CASE WHEN (d.status = 'closed' OR d.status = 'accepted') AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as closed_defects,
        SUM(CASE WHEN d.status = 'rejected' AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as rejected_defects,
        SUM(CASE WHEN d.due_date < UTC_TIMESTAMP() AND d.status IN ('open', 'pending') AND d.deleted_at IS NULL THEN 1 ELSE 0 END) as overdue_defects,
        MAX(CASE WHEN d.deleted_at IS NULL THEN d.updated_at ELSE NULL END) as last_update
    FROM 
        contractors c
        LEFT JOIN defects d ON c.id = d.assigned_to
            AND d.created_at BETWEEN :start_date AND :end_date
    WHERE 
        c.status = 'active'
    GROUP BY 
        c.id, c.company_name, c.logo, c.trade
    HAVING 
        total_defects > 0
    ORDER BY 
        total_defects DESC
";

$stmt = $db->prepare($contractorPerformanceQuery);
$stmt->execute([
    ':start_date' => $start_date . ' 00:00:00',
    ':end_date' => $end_date . ' 23:59:59'
]);
$contractorStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate available space and adjust row heights to fit on one page
$headerHeight = 70; // Approximate space used by headers, titles, etc.
$footerHeight = 25; // Approximate footer height
$totalRowHeight = 10; // Height of the totals row
$tableHeaderHeight = 7; // Height of table header row

// Get page dimensions
$pageHeight = $pdf->getPageHeight();
$availableHeight = $pageHeight - $headerHeight - $footerHeight - $totalRowHeight - $tableHeaderHeight;

// Count contractors
$contractorCount = count($contractorStats);

// Ensure we have at least one contractor
if ($contractorCount == 0) {
    $contractorCount = 1; // Avoid division by zero
}

// Calculate row height based on available space and number of contractors
// Set a minimum row height (12) and maximum (20) - REDUCED from original values
$rowHeight = min(20, max(12, floor($availableHeight / $contractorCount)));

// If there are many contractors, reduce font sizes
$companyNameFontSize = 8; // reduced from 9
$tradeFontSize = 7;      // reduced from 8
$dataFontSize = 8;       // reduced from 9

// For more than 10 contractors, reduce font sizes further
if ($contractorCount > 10) {
    $companyNameFontSize = 7; // reduced from 8
    $tradeFontSize = 6;       // reduced from 7
    $dataFontSize = 7;        // reduced from 8
}

// For more than 15 contractors, reduce even more
if ($contractorCount > 15) {
    $companyNameFontSize = 6; // reduced from 7
    $tradeFontSize = 5;       // reduced from 6
    $dataFontSize = 6;        // reduced from 7
}

// Add space before table
$pdf->Ln(10);

// Define consistent column widths - REDUCED contractor column width
$colWidth1 = 65;  // Contractor column (reduced from 75)
$colWidth2 = 15;  // Total (slightly increased)
$colWidth3 = 15;  // Open (slightly increased)
$colWidth4 = 17;  // Pending (slightly increased)
$colWidth5 = 17;  // Overdue (slightly increased)
$colWidth6 = 17;  // Rejected (slightly increased)
$colWidth7 = 16;  // Closed (slightly increased)
$colWidth8 = 24;  // Last Update (unchanged)

// Initialize totals counters
$totalDefects = 0;
$totalOpen = 0;
$totalPending = 0;
$totalOverdue = 0;
$totalRejected = 0;
$totalClosed = 0;

// Updated Table header with consistent column widths
$pdf->SetFillColor(230, 230, 230);
$pdf->SetFont('helvetica', 'B', 9); // Reduced from 10
$pdf->Cell($colWidth1, 7, 'Contractor', 1, 0, 'C', 1);
$pdf->Cell($colWidth2, 7, 'Total', 1, 0, 'C', 1);
$pdf->Cell($colWidth3, 7, 'Open', 1, 0, 'C', 1);
$pdf->Cell($colWidth4, 7, 'Pending', 1, 0, 'C', 1);
$pdf->Cell($colWidth5, 7, 'Overdue', 1, 0, 'C', 1);
$pdf->Cell($colWidth6, 7, 'Rejected', 1, 0, 'C', 1);
$pdf->Cell($colWidth7, 7, 'Closed', 1, 0, 'C', 1);
$pdf->Cell($colWidth8, 7, 'Last Update', 1, 1, 'C', 1);

// Table data
$pdf->SetFont('helvetica', '', $dataFontSize);
$pdf->SetFillColor(245, 245, 245);
$fill = false;

foreach ($contractorStats as $stat) {
    $startY = $pdf->GetY();
    $currX = $pdf->GetX();
    
    // First draw all the cells with their borders to ensure alignment
    $pdf->Cell($colWidth1, $rowHeight, '', 1, 0, 'L', $fill);
    
    // Draw numeric cells with vertically centered text
    $pdf->Cell($colWidth2, $rowHeight, $stat['total_defects'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidth3, $rowHeight, $stat['open_defects'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidth4, $rowHeight, $stat['pending_defects'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidth5, $rowHeight, $stat['overdue_defects'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidth6, $rowHeight, $stat['rejected_defects'], 1, 0, 'C', $fill);
    $pdf->Cell($colWidth7, $rowHeight, $stat['closed_defects'], 1, 0, 'C', $fill);
    
    // Format date for last update - use UK format (DD/MM/YYYY)
    $last_update = !empty($stat['last_update']) ? 
        date('d/m/Y', strtotime($stat['last_update'])) : 'N/A';
    $pdf->Cell($colWidth8, $rowHeight, $last_update, 1, 1, 'C', $fill);
    
    // Now go back and add the logo and text in the first cell
    $pdf->SetXY($currX, $startY);
    
    // If logo exists, add it with reduced size
    if (!empty($stat['logo'])) {
        $logoFilename = $stat['logo'];
        if (stripos($logoFilename, 'uploads/logos/') === 0) {
            $logoFilename = substr($logoFilename, strlen('uploads/logos/'));
        }
        $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/logos/' . $logoFilename;
        if (file_exists($logoPath) && is_readable($logoPath)) {
            try {
                // Get image dimensions to maintain aspect ratio
                list($origWidth, $origHeight) = getimagesize($logoPath);
                
                // Set maximum logo size (smaller than before)
                $maxLogoHeight = min(12, $rowHeight - 3); // Reduced from 15
                $maxLogoWidth = 12; // Maximum width
                
                // Calculate dimensions maintaining aspect ratio
                if ($origWidth > $origHeight) {
                    $logoWidth = $maxLogoWidth;
                    $logoHeight = ($origHeight / $origWidth) * $logoWidth;
                } else {
                    $logoHeight = $maxLogoHeight;
                    $logoWidth = ($origWidth / $origHeight) * $logoHeight;
                }
                
                // Center the logo vertically in the cell
                $logoY = $startY + ($rowHeight - $logoHeight) / 2;
                
                // Position logo at the left side of the cell with padding
                $pdf->Image($logoPath, $currX + 3, $logoY, $logoWidth, $logoHeight);
            } catch (Exception $e) {
                // Silently handle image errors - don't output anything that would break PDF
                error_log("PDF Image Error: " . $e->getMessage() . " - Logo path: " . $logoPath);
            }
        }
    }
    
    // Calculate text position for vertically centered text
    // Reduced space for logo to accommodate smaller contractor column
    $textX = $currX + 18; // Reduced from 20
    $textWidth = $colWidth1 - 20; // Reduced from 22
    
    // Company name in bold with adjusted vertical spacing
    $pdf->SetXY($textX, $startY + ($rowHeight/2) - 4); // Reduced space from 5
    $pdf->SetFont('helvetica', 'B', $companyNameFontSize);
    $pdf->Cell($textWidth, 4, $stat['company_name'], 0, 0, 'L', 0);
    
    // Trade name in regular font
    $pdf->SetXY($textX, $startY + ($rowHeight/2) + 1);
    $pdf->SetFont('helvetica', '', $tradeFontSize);
    $pdf->Cell($textWidth, 3, $stat['trade'], 0, 0, 'L', 0); // Reduced height
    
    // Reset font
    $pdf->SetFont('helvetica', '', $dataFontSize);
    
    // Move cursor to after this row
    $pdf->SetXY($currX, $startY + $rowHeight);
    
    // Add to totals
    $totalDefects += $stat['total_defects'];
    $totalOpen += $stat['open_defects'];
    $totalPending += $stat['pending_defects'];
    $totalOverdue += $stat['overdue_defects'];
    $totalRejected += $stat['rejected_defects'];
    $totalClosed += $stat['closed_defects'];
    
    // Toggle fill for alternating row colors
    $fill = !$fill;
}

// Add totals row
$totalsRowHeight = 10; // Height for the totals row
$pdf->SetFont('helvetica', 'B', 9); // Reduced from 10
$pdf->SetFillColor(200, 200, 200); // Darker gray for totals row

// Draw the TOTALS row with centered text
$pdf->Cell($colWidth1, $totalsRowHeight, 'TOTALS', 1, 0, 'C', 1);
$pdf->Cell($colWidth2, $totalsRowHeight, $totalDefects, 1, 0, 'C', 1);
$pdf->Cell($colWidth3, $totalsRowHeight, $totalOpen, 1, 0, 'C', 1);
$pdf->Cell($colWidth4, $totalsRowHeight, $totalPending, 1, 0, 'C', 1);
$pdf->Cell($colWidth5, $totalsRowHeight, $totalOverdue, 1, 0, 'C', 1);
$pdf->Cell($colWidth6, $totalsRowHeight, $totalRejected, 1, 0, 'C', 1);
$pdf->Cell($colWidth7, $totalsRowHeight, $totalClosed, 1, 0, 'C', 1);
$pdf->Cell($colWidth8, $totalsRowHeight, '', 1, 1, 'C', 1); // Empty cell for Last Update column

// Output the PDF to browser
$pdf->Output('contractor_defects_report.pdf', 'I');
?>