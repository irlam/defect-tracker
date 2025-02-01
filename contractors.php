<?php
// contractors.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-25 11:50:59
// Current User's Login: irlam

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$pageTitle = 'Contractors';
$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

// Get messages from session (only once)
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';

// Clear messages after retrieving them
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php'; // Include the navbar file

// Set timezone to UK
date_default_timezone_set('Europe/London');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $currentUserId);

    // Get current user's ID first
    $userStmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $userStmt->execute([$currentUser]);
    $userId = $userStmt->fetchColumn();

    if (!$userId) {
        // Insert user if not exists
        $insertUserStmt = $db->prepare("INSERT INTO users (username, created_at, updated_at) VALUES (?, NOW(), NOW())");
        $insertUserStmt->execute([$currentUser]);
        $userId = $db->lastInsertId();
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            $ip_address = $_SERVER['REMOTE_ADDR'];

            switch ($_POST['action']) {
                case 'create_contractor':
                    try {
                        // Validate status
                        $allowed_statuses = ['active', 'inactive', 'suspended'];
                        if (!in_array($_POST['status'], $allowed_statuses)) {
                            throw new Exception("Invalid status value");
                        }

                        $db->beginTransaction();

                        $stmt = $db->prepare("
                            INSERT INTO contractors (
                                company_name, 
                                contact_name,
                                email,
                                phone,
                                trade,
                                status,
                                created_by,
                                created_at,
                                updated_by,
                                updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        if ($stmt->execute([
                            trim($_POST['company_name']),
                            trim($_POST['contact_name']),
                            trim($_POST['email']),
                            trim($_POST['phone']),
                            trim($_POST['trade']),
                            $_POST['status'],
                            $userId,
                            $currentDateTime,
                            $userId,
                            $currentDateTime
                        ])) {
                            $contractorId = $db->lastInsertId();
                            
                            // Log to user_logs
                            $log_stmt = $db->prepare("
                                INSERT INTO user_logs 
                                (user_id, action, action_by, action_at, ip_address, details) 
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            
                            $log_details = "Created new contractor: " . trim($_POST['company_name']);
                            $log_stmt->execute([
                                $userId,
                                'create_contractor',
                                $userId,
                                $currentDateTime,
                                $ip_address,
                                $log_details
                            ]);

                            $db->commit();
                            $_SESSION['success_message'] = "Contractor created successfully";
                        } else {
                            throw new Exception("Error creating contractor");
                        }
                    } catch (Exception $e) {
                        $db->rollBack();
                        error_log("Contractor Creation Error: " . $e->getMessage());
                        $_SESSION['error_message'] = "An error occurred while creating the contractor: " . $e->getMessage();
                    }
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                    break;
                case 'update_contractor':
    try {
        // Validate status
        $allowed_statuses = ['active', 'inactive', 'suspended'];
        if (!in_array($_POST['status'], $allowed_statuses)) {
            throw new Exception("Invalid status value");
        }

        $db->beginTransaction();

        // Get current contractor data for comparison
        $getOldData = $db->prepare("
            SELECT company_name, contact_name, email, phone, trade, status 
            FROM contractors 
            WHERE id = ?
        ");
        $getOldData->execute([(int)$_POST['contractor_id']]);
        $oldData = $getOldData->fetch(PDO::FETCH_ASSOC);

        if (!$oldData) {
            throw new Exception("Contractor not found");
        }

        // Update contractor
        $updateStmt = $db->prepare("
            UPDATE contractors 
            SET 
                company_name = ?,
                contact_name = ?,
                email = ?,
                phone = ?,
                trade = ?,
                status = ?,
                updated_by = ?,
                updated_at = UTC_TIMESTAMP()
            WHERE id = ?
        ");

        $updateResult = $updateStmt->execute([
            trim($_POST['company_name']),
            trim($_POST['contact_name']),
            trim($_POST['email']),
            trim($_POST['phone']),
            trim($_POST['trade']),
            $_POST['status'],
            $userId,
            (int)$_POST['contractor_id']
        ]);

        if (!$updateResult) {
            throw new Exception("Failed to update contractor");
        }

        // Prepare changes log
        $changes = [];
        $fields = ['company_name', 'contact_name', 'email', 'phone', 'trade', 'status'];
        
        foreach ($fields as $field) {
            if ($oldData[$field] !== $_POST[$field]) {
                $changes[] = sprintf(
                    "%s: %s → %s",
                    $field,
                    $oldData[$field],
                    $_POST[$field]
                );
            }
        }

        // Log the changes
        $logStmt = $db->prepare("
            INSERT INTO user_logs 
            (user_id, action, action_by, action_at, ip_address, details) 
            VALUES (?, ?, ?, UTC_TIMESTAMP(), ?, ?)
        ");

        $logDetails = json_encode([
            'contractor_id' => (int)$_POST['contractor_id'],
            'company_name' => trim($_POST['company_name']),
            'changes' => $changes,
            'updated_by' => $_SESSION['username'],
            'timestamp' => '2025-01-27 21:51:07'
        ]);

        $logResult = $logStmt->execute([
            $userId,
            'update_contractor',
            $userId,
            $_SERVER['REMOTE_ADDR'],
            $logDetails
        ]);

        if (!$logResult) {
            throw new Exception("Failed to log the update");
        }

        $db->commit();
        $_SESSION['success_message'] = "Contractor updated successfully";

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Contractor Update Error: " . $e->getMessage());
        error_log("POST Data: " . print_r($_POST, true));
        error_log("Stack trace: " . $e->getTraceAsString());
        $_SESSION['error_message'] = "Failed to update contractor: " . $e->getMessage();
    }

    header("Location: " . $_SERVER['HTTP_REFERER']);
    exit;
    break;

                case 'delete_contractor':
                    $contractor_id = (int)$_POST['contractor_id'];
                    
                    try {
                        $db->beginTransaction();

                        // Check for associated defects
                        $checkDefects = $db->prepare("SELECT COUNT(*) FROM defects WHERE contractor_id = ?");
                        $checkDefects->execute([$contractor_id]);
                        $defectCount = $checkDefects->fetchColumn();

                        if ($defectCount > 0) {
                            throw new Exception("Cannot delete contractor. There are defects associated with this contractor.");
                        }

                        // Get contractor details before deletion for logging
                        $getContractor = $db->prepare("SELECT company_name FROM contractors WHERE id = ?");
                        $getContractor->execute([$contractor_id]);
                        $contractorName = $getContractor->fetchColumn();

                        if (!$contractorName) {
                            throw new Exception("Contractor not found");
                        }

                        $stmt = $db->prepare("DELETE FROM contractors WHERE id = ?");
                        if ($stmt->execute([$contractor_id])) {
                            // Log to user_logs
                            $log_stmt = $db->prepare("
                                INSERT INTO user_logs 
                                (user_id, action, action_by, action_at, ip_address, details) 
                                VALUES (?, ?, ?, ?, ?, ?)
                            ");
                            
                            $log_details = "Deleted contractor: $contractorName (ID: $contractor_id)";
                            $log_stmt->execute([
                                $userId,
                                'delete_contractor',
                                $userId,
                                $currentDateTime,
                                $ip_address,
                                $log_details
                            ]);

                            $db->commit();
                            $_SESSION['success_message'] = "Contractor deleted successfully";
                        } else {
                            throw new Exception("Error deleting contractor");
                        }
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        error_log("Contractor Deletion Error: " . $e->getMessage());
                        $_SESSION['error_message'] = $e->getMessage();
                    }
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit;
                    break;
            }
        }
    }

    // Get contractors with their statistics
    $query = "SELECT 
                c.*,
                u_created.username as created_by_username,
                u_updated.username as updated_by_username,
                COUNT(DISTINCT d.id) as total_defects,
                SUM(CASE WHEN d.status != 'closed' THEN 1 ELSE 0 END) as active_defects,
                DATE_FORMAT(c.created_at, '%d/%m/%Y %H:%i') as uk_created_date,
                DATE_FORMAT(c.updated_at, '%d/%m/%Y %H:%i') as uk_updated_date
              FROM contractors c
              LEFT JOIN users u_created ON c.created_by = u_created.id
              LEFT JOIN users u_updated ON c.updated_by = u_updated.id
              LEFT JOIN defects d ON c.id = d.contractor_id
              GROUP BY c.id, c.company_name, c.contact_name, c.email, c.phone, 
                       c.trade, c.status, c.created_at, c.updated_at,
                       u_created.username, u_updated.username
              ORDER BY c.company_name ASC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Contractors Error: " . $e->getMessage());
    $error_message = "An error occurred while loading contractors: " . $e->getMessage();
}
// Helper functions
function formatUKDate($date) {
    return date('d/m/Y H:i', strtotime($date));
}

function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'bg-success';
        case 'inactive':
            return 'bg-warning text-dark';
        case 'suspended':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contractors Management - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); ?>">
    <meta name="last-modified" content="2025-01-25 11:53:20">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
        }

        .contractor-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }

        .contractor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .card {
            border: none;
            background: linear-gradient(to right, #ffffff, #f8f9fa);
        }

        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1.5rem;
            border: none;
        }

        .date-info {
            background: linear-gradient(135deg, #f6f9fc 0%, #f1f4f8 100%);
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .contact-info {
            padding: 15px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .contact-info i {
            color: #3498db;
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }

        .contact-info a {
            color: #2c3e50;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .contact-info a:hover {
            color: #3498db;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php echo $navbar->render(); ?>

    <div class="main-content">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Contractors Management</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                            <li class="breadcrumb-item active">Contractors</li>
                        </ol>
                    </nav>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContractorModal">
                    <i class='bx bx-plus-circle'></i> Add Contractor
                </button>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class='bx bx-check-circle me-2'></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class='bx bx-error-circle me-2'></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Contractors Grid -->
            <div class="row">
                <?php if (empty($contractors)): ?>
                    <div class="col-12">
                        <div class="alert alert-info">
                            <i class='bx bx-info-circle me-2'></i>
                            No contractors found. Add your first contractor using the 'Add Contractor' button.
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($contractors as $contractor): ?>
                        <div class="col-md-6 col-xl-4">
                            <div class="contractor-card">
                                <div class="card h-100">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">
                                            <?php echo htmlspecialchars($contractor['company_name']); ?>
                                        </h5>
                                        <span class="badge <?php echo getStatusBadgeClass($contractor['status']); ?>">
                                            <?php echo ucfirst($contractor['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="date-info">
                                            <div class="date-label">
                                                <i class='bx bx-briefcase'></i> Trade
                                            </div>
                                            <div class="date-value">
                                                <?php echo htmlspecialchars($contractor['trade']); ?>
                                            </div>
                                        </div>

                                        <div class="contact-info">
                                            <div class="mb-2">
                                                <i class='bx bx-user'></i> 
                                                <?php echo htmlspecialchars($contractor['contact_name']); ?>
                                            </div>
                                            <div class="mb-2">
                                                <i class='bx bx-envelope'></i>
                                                <a href="mailto:<?php echo htmlspecialchars($contractor['email']); ?>">
                                                    <?php echo htmlspecialchars($contractor['email']); ?>
                                                </a>
                                            </div>
                                            <div class="mb-2">
                                                <i class='bx bx-phone'></i>
                                                <a href="tel:<?php echo htmlspecialchars($contractor['phone']); ?>">
                                                    <?php echo htmlspecialchars($contractor['phone']); ?>
                                                </a>
                                            </div>
                                        </div>

                                        <div class="contractor-stats">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class='bx bx-bug'></i> 
                                                    <span class="ms-1"><?php echo $contractor['total_defects']; ?> total defects</span>
                                                </div>
                                                <div>
                                                    <i class='bx bx-time'></i> 
                                                    <span class="ms-1"><?php echo $contractor['active_defects']; ?> active</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="date-info">
                                            <div class="date-label">
                                                <i class='bx bx-calendar'></i> Created
                                            </div>
                                            <div class="date-value">
                                                <?php echo htmlspecialchars($contractor['created_by_username']); ?> on 
                                                <?php echo $contractor['uk_created_date']; ?>
                                            </div>
                                        </div>

                                        <div class="date-info">
                                            <div class="date-label">
                                                <i class='bx bx-calendar-edit'></i> Last Updated
                                            </div>
                                            <div class="date-value">
                                                <?php echo htmlspecialchars($contractor['updated_by_username']); ?> on 
                                                <?php echo $contractor['uk_updated_date']; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer">
                                        <div class="btn-group d-flex">
                                            <button type="button" class="btn btn-outline-primary w-100"
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editContractorModal<?php echo $contractor['id']; ?>">
                                                <i class='bx bx-edit'></i> Edit
                                            </button>
                                            <button type="button" class="btn btn-outline-danger w-100"
                                                    onclick="confirmDeleteContractor(<?php echo $contractor['id']; ?>)">
                                                <i class='bx bx-trash'></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Edit Contractor Modal -->
                            <div class="modal fade" id="editContractorModal<?php echo $contractor['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="POST" class="needs-validation" novalidate>
                                            <input type="hidden" name="action" value="update_contractor">
                                            <input type="hidden" name="contractor_id" value="<?php echo $contractor['id']; ?>">
                                            
                                            <div class="modal-header">
                                                <h5 class="modal-title">Edit Contractor</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label class="form-label required">Company Name</label>
                                                    <input type="text" name="company_name" class="form-control" 
                                                           value="<?php echo htmlspecialchars($contractor['company_name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label required">Contact Name</label>
                                                    <input type="text" name="contact_name" class="form-control" 
                                                           value="<?php echo htmlspecialchars($contractor['contact_name']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label required">Email</label>
                                                    <input type="email" name="email" class="form-control" 
                                                           value="<?php echo htmlspecialchars($contractor['email']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label required">Phone</label>
                                                    <input type="tel" name="phone" class="form-control" 
                                                           value="<?php echo htmlspecialchars($contractor['phone']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label required">Trade</label>
                                                    <input type="text" name="trade" class="form-control" 
                                                           value="<?php echo htmlspecialchars($contractor['trade']); ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="active" <?php echo $contractor['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $contractor['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="suspended" <?php echo $contractor['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Create Contractor Modal -->
            <div class="modal fade" id="createContractorModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <form method="POST" class="needs-validation" novalidate>
                            <input type="hidden" name="action" value="create_contractor">
                            
                            <div class="modal-header">
                                <h5 class="modal-title">Add New Contractor</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label required">Company Name</label>
                                    <input type="text" name="company_name" class="form-control" required>
                                    <div class="invalid-feedback">Company name is required</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Contact Name</label>
                                    <input type="text" name="contact_name" class="form-control" required>
                                    <div class="invalid-feedback">Contact name is required</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Email</label>
                                    <input type="email" name="email" class="form-control" required>
                                    <div class="invalid-feedback">Valid email is required</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Phone</label>
                                    <input type="tel" name="phone" class="form-control" required>
                                    <div class="invalid-feedback">Phone number is required</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label required">Trade</label>
                                    <input type="text" name="trade" class="form-control" required>
                                    <div class="invalid-feedback">Trade is required</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select" required>
                                        <option value="active">Active</option>
                                        <option value="inactive">Inactive</option>
                                        <option value="suspended">Suspended</option>
                                    </select>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-primary">Create Contractor</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Contractor Form -->
            <form id="deleteContractorForm" method="POST" style="display: none;">
                <input type="hidden" name="action" value="delete_contractor">
                <input type="hidden" name="contractor_id" id="deleteContractorId">
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });

            // Delete contractor confirmation with additional check
            window.confirmDeleteContractor = function(contractorId) {
                const message = 'Are you sure you want to delete this contractor?\n\n' +
                              'This action cannot be undone.\n' +
                              'All associated records will be permanently removed.';
                              
                if (confirm(message)) {
                    const form = document.getElementById('deleteContractorForm');
                    document.getElementById('deleteContractorId').value = contractorId;
                    form.submit();
                }
            };

            // Auto hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });

            // Phone number validation
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                    e.target.value = value;
                });
            });

            // Email validation on input
            const emailInputs = document.querySelectorAll('input[type="email"]');
            emailInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    const email = e.target.value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (email && !emailRegex.test(email)) {
                        input.setCustomValidity('Please enter a valid email address');
                    } else {
                        input.setCustomValidity('');
                    }
                });
            });

            // Required field validation feedback
            const requiredInputs = document.querySelectorAll('[required]');
            requiredInputs.forEach(input => {
                input.addEventListener('invalid', function(e) {
                    if (!e.target.value) {
                        e.target.setCustomValidity('This field is required');
                    }
                });
                input.addEventListener('input', function(e) {
                    e.target.setCustomValidity('');
                });
            });
        });
    </script>
</body>
</html>