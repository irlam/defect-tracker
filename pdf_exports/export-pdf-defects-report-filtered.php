<?php
// pdf_exports/export-pdf-defects-report-filtered.php
// This is for generating a pdf from the defects.php page when applying filtetrs etc
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../config/constants.php';
require_once '../includes/functions.php';
require_once($_SERVER['DOCUMENT_ROOT'] . '/tcpdf/tcpdf.php'); // Absolute path

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Get current user ID and username
$currentUserId = (int)$_SESSION['user_id'];
$currentUsername = $_SESSION['username'];

// Set current UTC date time in the required format
$current_datetime = date('Y-m-d H:i:s');

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$contractorFilter = $_GET['contractor'] ?? 'all';
$projectFilter = $_GET['project'] ?? 'all';
$dateAddedFilter = $_GET['date_added'] ?? '';
$searchTerm = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

try {
    $database = new Database();
    $db = $database->getConnection();

    // Base query with correct column names - similar to defects.php but without pagination limits
    $query = "SELECT 
                d.*,
                c.company_name as contractor_name,
                c.logo as contractor_logo,
                p.name as project_name,
                u.username as created_by_user,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_full_name,
                GROUP_CONCAT(DISTINCT di.file_path) as image_paths,
                GROUP_CONCAT(DISTINCT di.pin_path) as pin_paths,
                (SELECT COUNT(*) FROM defect_comments dc WHERE dc.defect_id = d.id) as comment_count,
                rej_user.username as rejected_by_user,
                reo_user.username as reopened_by_user,
                d.rejection_comment,
                d.rejection_status,
                d.reopened_at,
                d.assigned_to,
                d.reported_by,
                d.acceptance_comment,
                d.accepted_at,
                acc_user.username as accepted_by_user
              FROM defects d
              LEFT JOIN contractors c ON d.assigned_to = c.id
              LEFT JOIN projects p ON d.project_id = p.id
              LEFT JOIN users u ON d.created_by = u.id
              LEFT JOIN defect_images di ON d.id = di.defect_id
              LEFT JOIN users rej_user ON d.updated_by = rej_user.id
              LEFT JOIN users reo_user ON d.reopened_by = reo_user.id
              LEFT JOIN users acc_user ON d.accepted_by = acc_user.id
              WHERE d.deleted_at IS NULL";

    $params = [];

    // Add filters to query - same as in defects.php
    if ($statusFilter !== 'all') {
        $query .= " AND d.status = :status";
        $params[':status'] = $statusFilter;
    }

    if ($priorityFilter !== 'all') {
        $query .= " AND d.priority = :priority";
        $params[':priority'] = $priorityFilter;
    }

    if ($contractorFilter !== 'all') {
        $query .= " AND d.assigned_to = :contractor_id";
        $params[':contractor_id'] = $contractorFilter;
    }

    if ($projectFilter !== 'all') {
        $query .= " AND d.project_id = :project_id";
        $params[':project_id'] = $projectFilter;
    }

    if (!empty($dateAddedFilter)) {
        $query .= " AND DATE(d.created_at) = :date_added";
        $params[':date_added'] = $dateAddedFilter;
    }

    if (!empty($searchTerm)) {
        $query .= " AND (d.title LIKE :search OR d.description LIKE :search OR c.company_name LIKE :search)";
        $params[':search'] = "%$searchTerm%";
    }

    // Add group by and order by - but no LIMIT as we want all results in the PDF
    $query .= " GROUP BY d.id ORDER BY d.updated_at DESC";

    // Prepare and execute query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $defects = $stmt->fetchAll(PDO::FETCH_ASSOC) ?? [];
    
    // Create PDF using TCPDF
    class MYPDF extends TCPDF {
        // Page header
        public function Header() {
            // Logo
            $image_file = '../assets/images/logo.png';
            if (file_exists($image_file)) {
                $this->Image($image_file, 15, 10, 30, '', 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
            
            // Set font
            $this->SetFont('helvetica', 'B', 20);
            
            // Title
            $this->Cell(0, 20, 'Defects Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
            
            // Date - Changed to UK format (DD/MM/YYYY HH:MM:SS)
            $this->SetFont('helvetica', 'I', 10);
            $this->SetY(20);
            $this->Cell(0, 10, 'Generated on: ' . date('d/m/Y H:i:s'), 0, false, 'R', 0, '', 0, false, 'T', 'M');
        }

        // Page footer
        public function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            
            // Set font
            $this->SetFont('helvetica', 'I', 8);
            
            // Page number
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    // Create new PDF document - Using Landscape orientation
    $pdf = new MYPDF('L', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($_SESSION['username']);
    $pdf->SetTitle('Defects Report');
    $pdf->SetSubject('Defects Report');
    $pdf->SetKeywords('Defects, Report, PDF');

    // Set default header data
    $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

    // Set margins - Increased left and right margins for better centering
    $pdf->SetMargins(15, PDF_MARGIN_TOP + 10, 15);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // Add a page
    $pdf->AddPage();

    // Set font
    $pdf->SetFont('helvetica', '', 12);
    
    // Add current date/time and user login
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Generated By: ' . htmlspecialchars($currentUsername), 0, 1, 'L');
    
    $pdf->Ln(5);

    // Add filter summary
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Filter Summary:', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    
    // Get filter information
    $filterInfo = array();
    
    // Project filter
    if ($projectFilter !== 'all') {
        $projectQuery = "SELECT name FROM projects WHERE id = :project_id";
        $projectStmt = $db->prepare($projectQuery);
        $projectStmt->execute([':project_id' => $projectFilter]);
        $projectName = $projectStmt->fetchColumn();
        $filterInfo[] = "Project: " . $projectName;
    } else {
        $filterInfo[] = "Project: All Projects";
    }
    
    // Contractor filter
    if ($contractorFilter !== 'all') {
        $contractorQuery = "SELECT company_name FROM contractors WHERE id = :contractor_id";
        $contractorStmt = $db->prepare($contractorQuery);
        $contractorStmt->execute([':contractor_id' => $contractorFilter]);
        $contractorName = $contractorStmt->fetchColumn();
        $filterInfo[] = "Contractor: " . $contractorName;
    } else {
        $filterInfo[] = "Contractor: All Contractors";
    }
    
    // Status filter
    if ($statusFilter !== 'all') {
        $filterInfo[] = "Status: " . ucfirst(str_replace('_', ' ', $statusFilter));
    } else {
        $filterInfo[] = "Status: All Statuses";
    }
    
    // Priority filter
    if ($priorityFilter !== 'all') {
        $filterInfo[] = "Priority: " . ucfirst($priorityFilter);
    } else {
        $filterInfo[] = "Priority: All Priorities";
    }
    
    // Date Added filter
    if (!empty($dateAddedFilter)) {
        // Convert YYYY-MM-DD to DD/MM/YYYY format
        $formattedDate = date('d/m/Y', strtotime($dateAddedFilter));
        $filterInfo[] = "Date Added: " . $formattedDate;
    }
    
    // Search Term filter
    if (!empty($searchTerm)) {
        $filterInfo[] = "Search Term: '" . $searchTerm . "'";
    }
    
    // Display filter information
    $pdf->MultiCell(0, 10, implode("\n", $filterInfo), 0, 'L');
    
    $pdf->Ln(5);

    // Add defects count
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'Found ' . count($defects) . ' defects', 0, 1);
    $pdf->Ln(5);

    // Calculate available page width (accounting for margins)
    $pageWidth = $pdf->getPageWidth() - 30; // 15mm margins on each side

    // Define column widths as percentages of available page width
    $idWidth = $pageWidth * 0.08; // 8% of available width
    $titleWidth = $pageWidth * 0.30; // 30% of available width
    $projectWidth = $pageWidth * 0.17; // 17% of available width
    $contractorWidth = $pageWidth * 0.17; // 17% of available width
    $statusWidth = $pageWidth * 0.13; // 13% of available width
    $priorityDueDateWidth = $pageWidth * 0.15; // 15% of available width

    // Add table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetTextColor(0);
    $pdf->SetFont('helvetica', 'B', 10);
    
    // Table header with calculated column widths
    $pdf->Cell($idWidth, 10, 'ID', 1, 0, 'C', true);
    $pdf->Cell($titleWidth, 10, 'Title', 1, 0, 'C', true);
    $pdf->Cell($projectWidth, 10, 'Project', 1, 0, 'C', true);
    $pdf->Cell($contractorWidth, 10, 'Contractor', 1, 0, 'C', true);
    $pdf->Cell($statusWidth, 10, 'Status', 1, 0, 'C', true);
    $pdf->Cell($priorityDueDateWidth, 10, 'Priority / Due Date', 1, 1, 'C', true);

    // Table data
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetFillColor(255, 255, 255);

    // Default minimum row height
    $defaultRowHeight = 10;
    
    // Maximum content per page (adjust based on testing)
    $maxRowsPerPage = 20; // This is an estimate, actual will depend on row heights
    $rowCounter = 0;

    foreach ($defects as $defect) {
        // Format due date in UK format (DD/MM/YYYY) if it exists
        $dueDateFormatted = !empty($defect['due_date']) ? 
            date('d/m/Y', strtotime($defect['due_date'])) : 'Not Set';
            
        // Prepare priority and due date content
        $priorityDueDateContent = ucfirst($defect['priority']) . "\n" . $dueDateFormatted;
        
        // Calculate optimal row height based on content length
        $titleLines = $pdf->getNumLines($defect['title'], $titleWidth - 2);
        $projectLines = $pdf->getNumLines($defect['project_name'] ?? 'N/A', $projectWidth - 2);
        $contractorName = $defect['contractor_name'] ?? 'Unassigned';
        $contractorLines = $pdf->getNumLines($contractorName, $contractorWidth - 14); // Account for logo space
        $statusLines = $pdf->getNumLines(ucfirst(str_replace('_', ' ', $defect['status'])), $statusWidth - 2);
        $priorityDueDateLines = $pdf->getNumLines($priorityDueDateContent, $priorityDueDateWidth - 2);
        
        // Calculate maximum number of lines needed
        $maxLines = max($titleLines, $projectLines, $contractorLines, $statusLines, $priorityDueDateLines);
        
        // Calculate row height (one line is approximately 4 units high)
        $rowHeight = max($defaultRowHeight, $maxLines * 4 + 2); // Add 2 for padding
        
        // Check if we need a new page - improved page break logic
        $rowCounter++;
        if ($pdf->GetY() + $rowHeight > $pdf->getPageHeight() - 25 || $rowCounter > $maxRowsPerPage) {
            $rowCounter = 1; // Reset counter for new page
            $pdf->AddPage();
            
            // Reprint the header on the new page
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($idWidth, 10, 'ID', 1, 0, 'C', true);
            $pdf->Cell($titleWidth, 10, 'Title', 1, 0, 'C', true);
            $pdf->Cell($projectWidth, 10, 'Project', 1, 0, 'C', true);
            $pdf->Cell($contractorWidth, 10, 'Contractor', 1, 0, 'C', true);
            $pdf->Cell($statusWidth, 10, 'Status', 1, 0, 'C', true);
            $pdf->Cell($priorityDueDateWidth, 10, 'Priority / Due Date', 1, 1, 'C', true);
            
            $pdf->SetFont('helvetica', '', 9);
            $pdf->SetFillColor(255, 255, 255);
        }
        
        // Store starting position for this row
        $startY = $pdf->GetY();
        $startX = $pdf->GetX();
        
        // Draw all cell borders first with consistent height
        $currentX = $startX;
        
        // ID cell border
        $pdf->Cell($idWidth, $rowHeight, '', 1, 0);
        $currentX += $idWidth;
        
        // Title cell border
        $pdf->Cell($titleWidth, $rowHeight, '', 1, 0);
        $currentX += $titleWidth;
        
        // Project cell border
        $pdf->Cell($projectWidth, $rowHeight, '', 1, 0);
        $currentX += $projectWidth;
        
        // Contractor cell border
        $pdf->Cell($contractorWidth, $rowHeight, '', 1, 0);
        $currentX += $contractorWidth;
        
        // Status cell border
        $pdf->Cell($statusWidth, $rowHeight, '', 1, 0);
        $currentX += $statusWidth;
        
        // Priority/Due Date cell border
        $pdf->Cell($priorityDueDateWidth, $rowHeight, '', 1, 1);
        
        // Now fill in the content by setting positions and using write/MultiCell
        
        // ID cell content
        $pdf->SetXY($startX, $startY);
        $pdf->Cell($idWidth, $rowHeight, $defect['id'], 0, 0, 'C');
        
        // Title cell content
        $pdf->SetXY($startX + $idWidth, $startY);
        $pdf->MultiCell($titleWidth, $rowHeight, $defect['title'], 0, 'L', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Project cell content
        $pdf->SetXY($startX + $idWidth + $titleWidth, $startY);
        $pdf->MultiCell($projectWidth, $rowHeight, $defect['project_name'] ?? 'N/A', 0, 'L', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Contractor cell content with logo
        $contractorX = $startX + $idWidth + $titleWidth + $projectWidth;
        $contractorY = $startY;
        
        // Check if logo exists and is valid
        $hasLogo = false;
        $textIndent = 0;
        
        if (!empty($defect['contractor_logo'])) {
            $logoPath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/logos/' . $defect['contractor_logo'];
            if (file_exists($logoPath) && is_readable($logoPath)) {
                // Get image dimensions to maintain aspect ratio
                $imgInfo = @getimagesize($logoPath);
                if ($imgInfo !== false) {
                    // Max logo dimensions
                    $maxLogoHeight = min(8, $rowHeight - 2); // Account for padding
                    $maxLogoWidth = 12; // Logo width in landscape
                    
                    // Calculate new dimensions maintaining aspect ratio
                    $originalWidth = $imgInfo[0];
                    $originalHeight = $imgInfo[1];
                    $aspectRatio = $originalWidth / $originalHeight;
                    
                    if ($aspectRatio > 1) {
                        // Wider than tall
                        $logoWidth = $maxLogoWidth;
                        $logoHeight = $logoWidth / $aspectRatio;
                        if ($logoHeight > $maxLogoHeight) {
                            $logoHeight = $maxLogoHeight;
                            $logoWidth = $logoHeight * $aspectRatio;
                        }
                    } else {
                        // Taller than wide or square
                        $logoHeight = $maxLogoHeight;
                        $logoWidth = $logoHeight * $aspectRatio;
                        if ($logoWidth > $maxLogoWidth) {
                            $logoWidth = $maxLogoWidth;
                            $logoHeight = $logoWidth / $aspectRatio;
                        }
                    }
                    
                    try {
                        // Place logo at the left side with proper dimensions
                        $pdf->Image($logoPath, $contractorX + 2, $contractorY + ($rowHeight - $logoHeight) / 2, $logoWidth, $logoHeight);
                        $hasLogo = true;
                        $textIndent = $logoWidth + 4; // Indent text based on actual logo width + margin
                    } catch (Exception $e) {
                        error_log("PDF Image Error: " . $e->getMessage() . " - Logo path: " . $logoPath);
                    }
                }
            }
        }
        
        // Add company name with proper indent if a logo is present
        $pdf->SetXY($contractorX + $textIndent, $contractorY);
        $pdf->MultiCell($contractorWidth - $textIndent, $rowHeight, $contractorName, 0, 'L', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Status cell content
        $pdf->SetXY($contractorX + $contractorWidth, $contractorY);
        $pdf->MultiCell($statusWidth, $rowHeight, ucfirst(str_replace('_', ' ', $defect['status'])), 0, 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Priority and Due Date cell content
        $pdf->SetXY($contractorX + $contractorWidth + $statusWidth, $contractorY);
        $pdf->MultiCell($priorityDueDateWidth, $rowHeight/2, $priorityDueDateContent, 0, 'C', false, 0, '', '', true, 0, false, true, $rowHeight, 'M');
        
        // Move to next row
        $pdf->SetXY($startX, $startY + $rowHeight);
    }

    // Output the PDF - Changed to UK date format (DD-MM-YYYY)
    $pdf->Output('defects_report_' . date('d-m-Y') . '.pdf', 'I');

} catch (Exception $e) {
    error_log("PDF Export Error: " . $e->getMessage());
    echo "Error generating PDF: " . $e->getMessage();
    exit();
}