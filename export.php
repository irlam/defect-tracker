<?php
/**
 * export.php
 * Current Date and Time (UTC): 2025-01-26 17:21:49
 * Current User's Login: irlam
 */

session_start();
require_once 'config/database.php';
require_once 'includes/autoload.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

function exportAndDownload($data, $format) {
    // Generate unique filename
    $timestamp = date('Y-m-d_H-i-s');
    $random = substr(md5(uniqid()), 0, 8);
    $filename = "export_{$timestamp}_{$random}";
    
    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    switch ($format) {
        case 'csv':
            $filename .= '.csv';
            header('Content-Type: text/csv');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            
            // Open output stream
            $output = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Add headers
            fputcsv($output, array_keys($data[0]));
            
            // Add data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            break;

        case 'excel':
            $filename .= '.xlsx';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Add headers
            $columns = array_keys($data[0]);
            $col = 1;
            foreach ($columns as $column) {
                $sheet->setCellValueByColumnAndRow($col, 1, $column);
                $col++;
            }
            
            // Add data
            $row = 2;
            foreach ($data as $rowData) {
                $col = 1;
                foreach ($rowData as $value) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto-size columns
            foreach (range('A', $sheet->getHighestDataColumn()) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            break;

        case 'pdf':
            $filename .= '.pdf';
            header('Content-Type: application/pdf');
            header("Content-Disposition: attachment; filename=\"{$filename}\"");
            
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('DVN Track');
            $pdf->SetAuthor($_SESSION['username']);
            $pdf->SetTitle('Data Export');
            
            // Set margins
            $pdf->SetMargins(15, 15, 15);
            $pdf->SetAutoPageBreak(TRUE, 15);
            
            // Add a page
            $pdf->AddPage();
            
            // Create HTML table
            $html = '<table border="1" cellpadding="4">';
            
            // Add headers
            $html .= '<thead><tr>';
            foreach (array_keys($data[0]) as $header) {
                $html .= '<th style="background-color: #f0f0f0;"><b>' . htmlspecialchars($header) . '</b></th>';
            }
            $html .= '</tr></thead><tbody>';
            
            // Add data rows
            foreach ($data as $row) {
                $html .= '<tr>';
                foreach ($row as $value) {
                    $html .= '<td>' . htmlspecialchars($value) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
            
            // Write HTML to PDF
            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Output PDF
            $pdf->Output($filename, 'D'); // 'D' forces download
            break;
    }
    
    // Log the export
    $db = (new Database())->getConnection();
    $stmt = $db->prepare("
        INSERT INTO export_logs (
            user_id, 
            export_type, 
            file_format, 
            filename, 
            filesize, 
            created_at,
            status,
            ip_address,
            user_agent
        ) VALUES (
            ?, ?, ?, ?, ?, UTC_TIMESTAMP(), 'completed', ?, ?
        )
    ");
    
    $filesize = strlen(ob_get_contents());
    $stmt->execute([
        $_SESSION['user_id'],
        'dashboard',
        $format,
        $filename,
        $filesize,
        $_SERVER['REMOTE_ADDR'],
        $_SERVER['HTTP_USER_AGENT']
    ]);
}

try {
    // Get export format from request
    $format = $_GET['format'] ?? 'csv';
    if (!in_array($format, ['csv', 'excel', 'pdf'])) {
        throw new Exception('Invalid export format');
    }
    
    // Get data to export (example with contractors)
    $db = (new Database())->getConnection();
    $stmt = $db->query("
        SELECT 
            c.company_name,
            c.trade,
            c.contact_name,
            c.email,
            c.phone,
            c.status,
            COUNT(d.id) as total_defects,
            SUM(CASE WHEN d.status = 'open' THEN 1 ELSE 0 END) as open_defects
        FROM contractors c
        LEFT JOIN defects d ON c.id = d.contractor_id
        GROUP BY c.id
        ORDER BY c.company_name
    ");
    
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Export and download
    exportAndDownload($data, $format);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode([
        'error' => 'Export failed: ' . $e->getMessage()
    ]);
    
    // Log error
    error_log("Export error: " . $e->getMessage());
}