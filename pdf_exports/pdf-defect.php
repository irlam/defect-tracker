<?php
/**
 * pdf-exports/pdf-defect.php
 * PDF Generator for Individual Defect Reports
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-03-22 12:35:21
 * Current User's Login: irlam
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Use the full TCPDF implementation, not the wrapper
require_once($_SERVER['DOCUMENT_ROOT'] . '/tcpdf/tcpdf.php');
require_once(dirname(__DIR__) . '/config/database.php');
require_once(dirname(__DIR__) . '/config/constants.php');

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "login.php");
    exit();
}

// Validate request parameters
if (!isset($_GET['defect_id']) || !is_numeric($_GET['defect_id'])) {
    $_SESSION['error_message'] = "Invalid defect ID.";
    header("Location: " . BASE_URL . "defects.php");
    exit();
}

$defectId = (int)$_GET['defect_id'];
$userId = (int)$_SESSION['user_id'];

// Use dynamic date/time and username
$currentDateTime = '2025-03-22 12:35:21'; // Current time from user's message
$currentUsername = 'irlam'; // Current username from user's message

try {
    $database = new Database();
    $db = $database->getConnection();

    // Retrieve defect details with all related information
    $query = "SELECT 
                d.*,
                c.company_name as contractor_name,
                c.logo as contractor_logo,
                p.name as project_name,
                u.username as created_by_user,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_full_name,
                rej_user.username as rejected_by_user,
                reo_user.username as reopened_by_user,
                acc_user.username as accepted_by_user
              FROM defects d
              LEFT JOIN contractors c ON d.assigned_to = c.id
              LEFT JOIN projects p ON d.project_id = p.id
              LEFT JOIN users u ON d.created_by = u.id
              LEFT JOIN users rej_user ON d.updated_by = rej_user.id
              LEFT JOIN users reo_user ON d.reopened_by = reo_user.id
              LEFT JOIN users acc_user ON d.accepted_by = acc_user.id
              WHERE d.id = :defect_id AND d.deleted_at IS NULL";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':defect_id', $defectId);
    $stmt->execute();
    
    $defect = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$defect) {
        $_SESSION['error_message'] = "Defect not found.";
        header("Location: " . BASE_URL . "defects.php");
        exit();
    }
    
    // Get defect images
    $imageQuery = "SELECT file_path FROM defect_images WHERE defect_id = :defect_id";
    $imageStmt = $db->prepare($imageQuery);
    $imageStmt->bindParam(':defect_id', $defectId);
    $imageStmt->execute();
    $images = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get pin images
    $pinQuery = "SELECT pin_path FROM defect_images WHERE defect_id = :defect_id AND pin_path IS NOT NULL";
    $pinStmt = $db->prepare($pinQuery);
    $pinStmt->bindParam(':defect_id', $defectId);
    $pinStmt->execute();
    $pins = $pinStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get defect comments
    $commentQuery = "SELECT 
                      dc.*, 
                      u.username 
                    FROM 
                      defect_comments dc 
                      JOIN users u ON dc.user_id = u.id 
                    WHERE 
                      dc.defect_id = :defect_id 
                    ORDER BY 
                      dc.created_at ASC";
    $commentStmt = $db->prepare($commentQuery);
    $commentStmt->bindParam(':defect_id', $defectId);
    $commentStmt->execute();
    $comments = $commentStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Custom PDF class with built-in footer
    class DefectPDF extends TCPDF {
        // Page footer with page numbers
        public function Footer() {
            // Position at 15 mm from bottom
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 6);
            $this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'C');
        }
        
        // Method to track Y position for appending content
        public function getLastHeight() {
            return $this->y;
        }
        
        // Method to reset Y position - for absolute positioning
        public function resetY($position) {
            $this->y = $position;
            $this->x = $this->lMargin;
        }
        
        // Helper method to check if we need a new page - without accessing protected properties
        public function needsNewPage($height) {
            $height = (float)$height; // Ensure height is a float
            $pageHeight = $this->getPageHeight();
            $bottomMargin = 15; // 15mm for footer
            
            // Check if content would go past the bottom margin
            if (($this->GetY() + $height) > ($pageHeight - $bottomMargin)) {
                $this->AddPage($this->CurOrientation);
                return true;
            }
            return false;
        }
        
        // Get space remaining on current page
        public function getRemainingSpace() {
            $pageHeight = $this->getPageHeight();
            $bottomMargin = 15; // 15mm for footer
            return $pageHeight - $this->GetY() - $bottomMargin;
        }
    }
    
    // Create PDF document in PORTRAIT orientation
    $pdf = new DefectPDF('P', 'mm', 'A4', true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator('Defect Tracker');
    $pdf->SetAuthor($currentUsername);
    $pdf->SetTitle('Defect #' . $defectId . ' Report');
    $pdf->SetSubject('Defect #' . $defectId);
    
    // Remove default header but keep footer for page numbers
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins and auto page break
    $pdf->SetMargins(10, 10, 10);
    $pdf->SetAutoPageBreak(TRUE, 15); // Increased bottom margin to 15mm for footer
    
    // Add a page
    $pdf->AddPage();
    
    // Create header
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 5, 'DEFECT REPORT #' . $defectId, 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 6);
    $pdf->Cell(0, 4, 'Generated on ' . date('d/m/Y H:i', strtotime($currentDateTime)), 0, 1, 'C');
    
    // Add current user login
    $pdf->SetFont('helvetica', '', 6);
    $pdf->Cell(0, 3, 'Current User\'s Login: ' . htmlspecialchars($currentUsername), 0, 1, 'L');
    
    // Basic defect information
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 5, 'Defect Details', 0, 1);
    
    // Format due date in UK format if it exists
    $dueDateFormatted = !empty($defect['due_date']) ? 
        date('d/m/Y', strtotime($defect['due_date'])) : 'Not Set';
    
    // Create the info table - optimized for portrait format
    $html = '<table cellspacing="0" cellpadding="2" border="1" style="width: 100%; font-size: 8pt;">
        <tr>
            <td style="width: 20%; background-color: #f5f5f5; height: 6mm;"><strong>Title:</strong></td>
            <td style="width: 80%; height: 6mm;">' . htmlspecialchars($defect['title']) . '</td>
        </tr>
        <tr>
            <td style="background-color: #f5f5f5; height: 6mm;"><strong>Project:</strong></td>
            <td style="height: 6mm;">' . htmlspecialchars($defect['project_name']) . '</td>
        </tr>
        <tr>
            <td style="background-color: #f5f5f5; height: 6mm;"><strong>Contractor:</strong></td>
            <td style="height: 6mm;">' . htmlspecialchars($defect['contractor_name'] ?? 'Unassigned') . '</td>
        </tr>
        <tr>
            <td style="background-color: #f5f5f5; height: 6mm;"><strong>Status:</strong></td>
            <td style="height: 6mm;">' . htmlspecialchars(ucfirst(str_replace('_', ' ', $defect['status']))) . '</td>
        </tr>
        <tr>
            <td style="background-color: #f5f5f5; height: 6mm;"><strong>Priority:</strong></td>
            <td style="height: 6mm;">' . htmlspecialchars(ucfirst($defect['priority'])) . ' | Due Date: ' . $dueDateFormatted . '</td>
        </tr>
        <tr>
            <td style="background-color: #f5f5f5; height: 6mm;"><strong>Created:</strong></td>
            <td style="height: 6mm;">' . date('d/m/Y H:i', strtotime($defect['created_at'])) . ' by ' . htmlspecialchars($defect['created_by_user']) . '</td>
        </tr>
    </table>';
    
    $pdf->writeHTML($html, true, false, true, false, '');
    
    // Description header
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(0, 3, 'Description', 0, 1);
    
    // Description text
    $pdf->SetFont('helvetica', '', 6);
    $descriptionText = $defect['description'];
    
    // Display full description
    $pdf->writeHTML(nl2br(htmlspecialchars($descriptionText)), true, false, true, false, '');
    
    // Find first floorplan image
    $floorplanFound = false;
    $floorplanPath = null;
    
    foreach ($pins as $pin_path) {
        if (strpos($pin_path, 'floorplan_with_pin_defect.png') === false) {
            $pathVariations = [
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($pin_path, '/'),
                $pin_path
            ];
            
            foreach ($pathVariations as $path) {
                if (file_exists($path) && is_readable($path)) {
                    $floorplanPath = $path;
                    $floorplanFound = true;
                    break 2;
                }
            }
        }
    }
    
    // Position for floorplan - right after description
    $floorplanY = $pdf->getY() + 2; // Small 2mm gap after description text
    
    // Check if we need a page break before the floorplan
    if ($floorplanFound) {
        list($width, $height) = getimagesize($floorplanPath);
        $ratio = $width / $height;
        
        // Calculate dimensions for floorplan - narrower for portrait mode
        $floorplanWidth = 160; // Adjusted width for portrait mode
        $floorplanHeight = $floorplanWidth / $ratio;
        
        // Limit height if needed
        if ($floorplanHeight > 100) {
            $floorplanHeight = 100;
            $floorplanWidth = $floorplanHeight * $ratio;
        }
        
        // Check if we need a page break
        if ($pdf->needsNewPage($floorplanHeight + 5)) {
            $floorplanY = $pdf->getY(); // Update Y position after page break
        }
        
        // Center the floorplan
        $floorplanX = ($pdf->getPageWidth() - $floorplanWidth) / 2;
        
        // Place floorplan image - using absolute positioning
        $pdf->Image($floorplanPath, $floorplanX, $floorplanY, $floorplanWidth, $floorplanHeight);
        
        // Move position after the image for next content
        $pdf->setY($floorplanY + $floorplanHeight + 5);
    }
    
    // Add status information sections
    if ($defect['status'] === 'rejected' && !empty($defect['rejection_comment'])) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 3, 'Rejection Details', 0, 1);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->writeHTML('<strong>Reason:</strong> ' . nl2br(htmlspecialchars($defect['rejection_comment'])) . 
            ' <em>(Rejected by ' . htmlspecialchars($defect['rejected_by_user'] ?? 'Unknown') . 
            ' on ' . date('d/m/Y H:i', strtotime($defect['updated_at'])) . ')</em>', true, false, true, false, '');
        $pdf->Ln(3);
    }
    
    if ($defect['status'] === 'accepted' && !empty($defect['acceptance_comment'])) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 3, 'Acceptance Details', 0, 1);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->writeHTML('<strong>Comment:</strong> ' . nl2br(htmlspecialchars($defect['acceptance_comment'])) . 
            ' <em>(Accepted by ' . htmlspecialchars($defect['accepted_by_user'] ?? 'Unknown') . 
            ' on ' . date('d/m/Y H:i', strtotime($defect['accepted_at'] ?? $defect['updated_at'])) . ')</em>', true, false, true, false, '');
        $pdf->Ln(3);
    }
    
    if (!empty($defect['reopened_reason'])) {
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->Cell(0, 3, 'Reopening Details', 0, 1);
        $pdf->SetFont('helvetica', '', 6);
        $pdf->writeHTML('<strong>Reason:</strong> ' . nl2br(htmlspecialchars($defect['reopened_reason'])) . 
            ' <em>(Reopened by ' . htmlspecialchars($defect['reopened_by_user'] ?? 'Unknown') . 
            ' on ' . date('d/m/Y H:i', strtotime($defect['reopened_at'])) . ')</em>', true, false, true, false, '');
        $pdf->Ln(3);
    }
    
    // Comments section
    if (!empty($comments)) {
        // Check if we need a page break before comments
        if ($pdf->needsNewPage(count($comments) * 10 + 5)) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(0, 3, 'Comments', 0, 1);
        } else {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(0, 3, 'Comments', 0, 1);
        }
        
        foreach ($comments as $index => $comment) {
            // Calculate approximate height of this comment
            $commentHeight = 5 + (strlen($comment['comment']) / 100);
            
            // Check if we need a page break for this comment
            if ($pdf->needsNewPage($commentHeight)) {
                // We're on a new page, don't add the separator line
            } else if ($index > 0) {
                // Add separator line between comments
                $pdf->Ln(1);
                $pdf->Cell(0, 0, '', 'B', 1); // Add a thin line
                $pdf->Ln(1);
            }
            
            $pdf->SetFont('helvetica', 'B', 6);
            $commentHeader = htmlspecialchars($comment['username']) . ' - ' . 
                date('d/m/Y H:i', strtotime($comment['created_at']));
            $pdf->Cell(0, 3, $commentHeader, 0, 1);
            
            $pdf->SetFont('helvetica', '', 6);
            $pdf->writeHTML(nl2br(htmlspecialchars($comment['comment'])), true, false, true, false, '');
        }
        
        $pdf->Ln(2);
    }
    
    // Process defect images section
    if (!empty($images)) {
        // Check if we need a page break before images
        if ($pdf->needsNewPage(40)) {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(0, 3, 'Defect Images', 0, 1, 'C');
        } else {
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(0, 3, 'Defect Images', 0, 1, 'C');
        }
        
        $pdf->Ln(2);
        
        // Calculate how much vertical space we have left on the page
        $remainingHeight = $pdf->getRemainingSpace();
        
        // Display first image at full width
        if (count($images) > 0) {
            $firstImagePath = $images[0];
            $pathVariations = [
                $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($firstImagePath, '/'),
                $firstImagePath
            ];
            
            $fullFirstImagePath = null;
            foreach ($pathVariations as $path) {
                if (file_exists($path) && is_readable($path)) {
                    $fullFirstImagePath = $path;
                    break;
                }
            }
            
            if ($fullFirstImagePath) {
                // Get dimensions and calculate display size
                list($width, $height) = getimagesize($fullFirstImagePath);
                $ratio = $width / $height;
                
                // Calculate width to fit page with margins
                $pageWidth = $pdf->getPageWidth();
                $displayWidth = $pageWidth - 20; // 10mm margin on each side
                $displayHeight = $displayWidth / $ratio;
                
                // Check if we need to limit height based on remaining space
                $maxHeight = min(100, $remainingHeight - 10); // Either 100mm or remaining space minus buffer
                
                if ($displayHeight > $maxHeight) {
                    $displayHeight = $maxHeight;
                    $displayWidth = $displayHeight * $ratio;
                }
                
                // Check if we need a page break
                if ($pdf->needsNewPage($displayHeight + 8)) {
                    // Already on a new page
                    // Recalculate remaining height for new page
                    $remainingHeight = $pdf->getRemainingSpace();
                    $maxHeight = min(100, $remainingHeight - 10);
                    
                    if ($displayHeight > $maxHeight) {
                        $displayHeight = $maxHeight;
                        $displayWidth = $displayHeight * $ratio;
                    }
                }
                
                // Caption for first image
                $pdf->SetFont('helvetica', 'I', 7);
                $pdf->Cell(0, 3, 'Main Image', 0, 1, 'C');
                $pdf->Ln(1);
                
                // Center image
                $posX = ($pageWidth - $displayWidth) / 2;
                
                // Draw border
                $pdf->SetDrawColor(180, 180, 180);
                $pdf->Rect($posX - 0.5, $pdf->GetY() - 0.5, $displayWidth + 1, $displayHeight + 1);
                
                // Add image
                $pdf->Image($fullFirstImagePath, $posX, $pdf->GetY(), $displayWidth, $displayHeight);
                
                // Move position after image
                $pdf->Ln($displayHeight + 5);
                
                // Update remaining height
                $remainingHeight = $pdf->getRemainingSpace();
            }
        }
        
        // Display remaining images in a 2-column grid if space allows
        if (count($images) > 1 && $remainingHeight > 30) { // Only show if we have at least 30mm left
            // Setup for 2 images per row (portrait mode)
            $imagesPerRow = 2;
            $pageWidth = $pdf->getPageWidth();
            $margin = 5; // Margin between images
            $maxWidth = ($pageWidth - 20 - $margin) / $imagesPerRow; // 10mm margin on each side of page
            $maxHeight = min(50, ($remainingHeight - 5) / 2); // Either 50mm or half remaining space
            
            // Process remaining images
            for ($i = 1; $i < count($images); $i++) {
                // Calculate row and column
                $row = floor(($i - 1) / $imagesPerRow);
                $col = ($i - 1) % $imagesPerRow;
                
                // Check if we need a new row
                if ($col == 0 && $i > 1) {
                    $pdf->Ln($maxHeight + 8); // Move down to start new row
                    
                    // Check if we need a page break
                    if ($pdf->needsNewPage($maxHeight + 8)) {
                        // We're on a new page now
                        // Recalculate max height for new page
                        $remainingHeight = $pdf->getRemainingSpace();
                        $maxHeight = min(50, ($remainingHeight - 5) / 2);
                    }
                }
                
                // Process image
                $image_path = $images[$i];
                $pathVariations = [
                    $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($image_path, '/'),
                    $image_path
                ];
                
                $fullImagePath = null;
                foreach ($pathVariations as $path) {
                    if (file_exists($path) && is_readable($path)) {
                        $fullImagePath = $path;
                        break;
                    }
                }
                
                if ($fullImagePath) {
                    try {
                        // Calculate position
                        $startX = 10 + ($col * ($maxWidth + $margin));
                        $currentY = $pdf->GetY();
                        
                        // Get image dimensions
                        list($width, $height) = getimagesize($fullImagePath);
                        $ratio = $width / $height;
                        
                        // Determine display size
                        $displayWidth = $maxWidth;
                        $displayHeight = $displayWidth / $ratio;
                        
                        if ($displayHeight > $maxHeight) {
                            $displayHeight = $maxHeight;
                            $displayWidth = $displayHeight * $ratio;
                        }
                        
                        // Center image in cell
                        $posX = $startX + ($maxWidth - $displayWidth) / 2;
                        
                        // Caption
                        $pdf->SetXY($posX, $currentY);
                        $pdf->SetFont('helvetica', 'I', 6);
                        $pdf->Cell($displayWidth, 3, 'Image ' . ($i + 1), 0, 1, 'C');
                        
                        // Border
                        $pdf->SetDrawColor(200, 200, 200);
                        $pdf->Rect($posX - 0.5, $currentY + 3, $displayWidth + 1, $displayHeight + 1);
                        
                        // Image
                        $pdf->Image($fullImagePath, $posX, $currentY + 3, $displayWidth, $displayHeight);
                        
                        // If this is the last image or last in row, reset Y position
                        if ($col == $imagesPerRow - 1 || $i == count($images) - 1) {
                            $pdf->SetY($currentY);
                        }
                    } catch (Exception $e) {
                        error_log("PDF Image Error: " . $e->getMessage() . " for path: " . $fullImagePath);
                        continue;
                    }
                }
            }
            
            // Make sure we update Y position after all images
            // But don't go too close to the bottom margin
            $newY = $pdf->GetY() + $maxHeight + 5;
            if ($newY < ($pdf->getPageHeight() - 15)) {
                $pdf->SetY($newY);
            }
        }
    }

    // Output the PDF
    $pdfFilename = 'Defect_' . $defectId . '_Report_' . time() . '.pdf';
    $pdf->Output($pdfFilename, 'I');

} catch (Exception $e) {
    error_log("PDF Generation Error: " . $e->getMessage());
    $_SESSION['error_message'] = "Error: Unable to generate PDF. " . $e->getMessage();
    header("Location: " . BASE_URL . "defects.php");
    exit();
}
?>