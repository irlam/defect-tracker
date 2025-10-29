<?php
// contractor_autosave.php
// Current Date and Time (UTC): 2025-01-16 12:58:01
// Current User: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    die(json_encode([
        'success' => false,
        'message' => 'Authentication required'
    ]));
}

// Check if it's an AJAX request
if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) != 'xmlhttprequest') {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]));
}

// Verify this is an auto-save request
if (!isset($_POST['auto_save']) || $_POST['auto_save'] !== '1') {
    die(json_encode([
        'success' => false,
        'message' => 'Invalid request type'
    ]));
}

require_once 'config/database.php';
require_once 'includes/audit_logger.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Validate contractor ID
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        throw new Exception('Invalid contractor ID');
    }
    
    $contractor_id = (int)$_POST['id'];
    
    // Verify contractor exists
    $check_query = "SELECT id FROM contractors WHERE id = :id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute(['id' => $contractor_id]);
    
    if (!$check_stmt->fetch()) {
        throw new Exception('Contractor not found');
    }

    // Start transaction
    $db->beginTransaction();

    // Save to draft table
    $draft_query = "INSERT INTO contractor_drafts (
        contractor_id,
        company_name,
        contact_name,
        email,
        phone,
        trade,
        address_line1,
        address_line2,
        city,
        county,
        postcode,
        vat_number,
        company_number,
        insurance_info,
        utr_number,
        notes,
        status,
        created_by,
        created_at
    ) VALUES (
        :contractor_id,
        :company_name,
        :contact_name,
        :email,
        :phone,
        :trade,
        :address_line1,
        :address_line2,
        :city,
        :county,
        :postcode,
        :vat_number,
        :company_number,
        :insurance_info,
        :utr_number,
        :notes,
        :status,
        :created_by,
        :created_at
    ) ON DUPLICATE KEY UPDATE
        company_name = VALUES(company_name),
        contact_name = VALUES(contact_name),
        email = VALUES(email),
        phone = VALUES(phone),
        trade = VALUES(trade),
        address_line1 = VALUES(address_line1),
        address_line2 = VALUES(address_line2),
        city = VALUES(city),
        county = VALUES(county),
        postcode = VALUES(postcode),
        vat_number = VALUES(vat_number),
        company_number = VALUES(company_number),
        insurance_info = VALUES(insurance_info),
        utr_number = VALUES(utr_number),
        notes = VALUES(notes),
        status = VALUES(status),
        updated_by = VALUES(created_by),
        updated_at = VALUES(created_at)";

    $draft_stmt = $db->prepare($draft_query);
    $current_time = date('Y-m-d H:i:s');

    $draft_stmt->execute([
        'contractor_id' => $contractor_id,
        'company_name' => $_POST['company_name'] ?? '',
        'contact_name' => $_POST['contact_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'trade' => $_POST['trade'] ?? '',
        'address_line1' => $_POST['address_line1'] ?? '',
        'address_line2' => $_POST['address_line2'] ?? '',
        'city' => $_POST['city'] ?? '',
        'county' => $_POST['county'] ?? '',
        'postcode' => strtoupper($_POST['postcode'] ?? ''),
        'vat_number' => strtoupper($_POST['vat_number'] ?? ''),
        'company_number' => $_POST['company_number'] ?? '',
        'insurance_info' => $_POST['insurance_info'] ?? '',
        'utr_number' => $_POST['utr_number'] ?? '',
        'notes' => $_POST['notes'] ?? '',
        'status' => $_POST['status'] ?? 'active',
        'created_by' => $_SESSION['user_id'],
        'created_at' => $current_time
    ]);

    // Log the auto-save action
    $log_query = "INSERT INTO system_log (
        action_type,
        action_description,
        performed_by,
        performed_at,
        ip_address
    ) VALUES (
        'CONTRACTOR_AUTOSAVE',
        :description,
        :user_id,
        :timestamp,
        :ip_address
    )";

    $log_stmt = $db->prepare($log_query);
    $log_stmt->execute([
        'description' => "Auto-saved draft for contractor ID: {$contractor_id}",
        'user_id' => $_SESSION['user_id'],
        'timestamp' => $current_time,
        'ip_address' => $_SERVER['REMOTE_ADDR']
    ]);

    // Commit transaction
    $db->commit();

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Draft saved successfully',
        'timestamp' => $current_time
    ]);

} catch (Exception $e) {
    // Rollback transaction if there was an error
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    // Log the error
    error_log("Auto-save error: " . $e->getMessage());

    // Return error response
    echo json_encode([
        'success' => false,
        'message' => 'Error saving draft: ' . $e->getMessage()
    ]);
}
?>