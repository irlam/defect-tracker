<?php
// contractors.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-06 18:26:12
// Current User's Login: irlam

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = 'Contractors Management';
$success_message = '';
$error_message = '';
$currentDateTime = date('Y-m-d H:i:s'); // Get current date and time
$currentUser = $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_contractor':
                    // Collect contractor data from the form
                    $company_name = trim($_POST['company_name']);
                    $contact_name = trim($_POST['contact_name']);
                    $email = trim($_POST['email']);
                    $phone = trim($_POST['phone']);
                    $trade = trim($_POST['trade']);
                    $address_line1 = trim($_POST['address_line1']);
                    $address_line2 = trim($_POST['address_line2']);
                    $city = trim($_POST['city']);
                    $county = trim($_POST['county']);
                    $postcode = trim($_POST['postcode']);
                    $vat_number = trim($_POST['vat_number']);
                    $company_number = trim($_POST['company_number']);
                    $insurance_info = trim($_POST['insurance_info']);
                    $utr_number = trim($_POST['utr_number']);
                    $notes = trim($_POST['notes']);
                    $status = $_POST['status'];
                    $license_number = trim($_POST['license_number']); // Retrieve license number

                    // Handle logo upload
                    $logo = '';
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/logos/'; // Directory to store logos
                        $fileName = uniqid() . '_' . basename($_FILES['logo']['name']); // Generate unique filename
                        $targetPath = $uploadDir . $fileName; // Full path to the uploaded file

                        // Move the uploaded file to the target directory
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                            $logo = $fileName; // Save filename to the database
                        } else {
                            $error_message = "Failed to upload logo.";
                        }
                    }

                    // Prepare and execute the SQL query to insert contractor data
                    $stmt = $db->prepare("
                        INSERT INTO contractors (
                            company_name, contact_name, email, phone, trade, address_line1, address_line2,
                            city, county, postcode, vat_number, company_number, insurance_info, utr_number,
                            notes, status, created_by, updated_by, logo, created_at, updated_at, license_number
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?
                        )
                    ");

                    $params = [
                        $company_name, $contact_name, $email, $phone, $trade, $address_line1, $address_line2,
                        $city, $county, $postcode, $vat_number, $company_number, $insurance_info, $utr_number,
                        $notes, $status, $currentUser, $currentUser, $logo, $license_number
                    ];

                    try {
                        $stmt->execute($params);
                        $success_message = "Contractor created successfully";
                    } catch (PDOException $e) {
                        error_log("Error creating contractor: " . $e->getMessage());
                        $error_message = "Error creating contractor. Please check the logs for details.";
                    }
                    break;

                case 'update_contractor':
                    // Collect contractor data from the form
                    $contractor_id = (int)$_POST['contractor_id'];
                    $company_name = trim($_POST['company_name']);
                    $contact_name = trim($_POST['contact_name']);
                    $email = trim($_POST['email']);
                    $phone = trim($_POST['phone']);
                    $trade = trim($_POST['trade']);
                    $address_line1 = trim($_POST['address_line1']);
                    $address_line2 = trim($_POST['address_line2']);
                    $city = trim($_POST['city']);
                    $county = trim($_POST['county']);
                    $postcode = trim($_POST['postcode']);
                    $vat_number = trim($_POST['vat_number']);
                    $company_number = trim($_POST['company_number']);
                    $insurance_info = trim($_POST['insurance_info']);
                    $utr_number = trim($_POST['utr_number']);
                    $notes = trim($_POST['notes']);
                    $status = $_POST['status'];
                    $license_number = trim($_POST['license_number']); // Retrieve license number

                    // Handle logo upload
                    $logo = $_POST['old_logo']; // Keep old logo if no new logo is uploaded
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/logos/'; // Directory to store logos
                        $fileName = uniqid() . '_' . basename($_FILES['logo']['name']); // Generate unique filename
                        $targetPath = $uploadDir . $fileName; // Full path to the uploaded file

                        // Move the uploaded file to the target directory
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                            // Delete old logo if it exists
                            if (!empty($_POST['old_logo'])) {
                                $oldLogoPath = $uploadDir . $_POST['old_logo'];
                                if (file_exists($oldLogoPath)) {
                                    unlink($oldLogoPath);
                                }
                            }
                            $logo = $fileName; // Save filename to the database
                        } else {
                            $error_message = "Failed to upload logo.";
                        }
                    }

                    // Prepare and execute the SQL query to update contractor data
                    $stmt = $db->prepare("
                        UPDATE contractors 
                        SET 
                            company_name = ?, contact_name = ?, email = ?, phone = ?, trade = ?, 
                            address_line1 = ?, address_line2 = ?, city = ?, county = ?, postcode = ?, 
                            vat_number = ?, company_number = ?, insurance_info = ?, utr_number = ?, 
                            notes = ?, status = ?, updated_by = ?, updated_at = NOW(), logo = ?, license_number = ?
                        WHERE id = ?
                    ");

                    $params = [
                        $company_name, $contact_name, $email, $phone, $trade, $address_line1, $address_line2,
                        $city, $county, $postcode, $vat_number, $company_number, $insurance_info, $utr_number,
                        $notes, $status, $currentUser, $logo, $license_number, $contractor_id
                    ];

                    try {
                        $stmt->execute($params);
                        $success_message = "Contractor updated successfully";
                    } catch (PDOException $e) {
                        error_log("Error updating contractor: " . $e->getMessage());
                        $error_message = "Error updating contractor. Please check the logs for details.";
                    }
                    break;

                case 'delete_contractor':
                    // Delete a contractor
                    $contractor_id = (int)$_POST['contractor_id'];

                    // Get the logo filename before deleting the contractor
                    $stmt = $db->prepare("SELECT logo FROM contractors WHERE id = ?");
                    $stmt->execute([$contractor_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $logoFilename = $result ? $result['logo'] : null;

                    // Prepare and execute the SQL query to delete the contractor
                    $stmt = $db->prepare("DELETE FROM contractors WHERE id = ?");
                    if ($stmt->execute([$contractor_id])) {
                        // Delete the logo file if it exists
                        if ($logoFilename) {
                            $uploadDir = 'uploads/logos/';
                            $logoPath = $uploadDir . $logoFilename;
                            if (file_exists($logoPath)) {
                                unlink($logoPath);
                            }
                        }
                        $success_message = "Contractor deleted successfully";
                    } else {
                        $error_message = "Error deleting contractor";
                    }
                    break;
            }
        }
    }

    // Get all contractors
    $query = "
        SELECT 
            c.id, c.company_name, c.contact_name, c.email, c.phone, c.trade,
            c.address_line1, c.address_line2, c.city, c.county, c.postcode,
            c.vat_number, c.company_number, c.insurance_info, c.utr_number,
            c.notes, c.status, c.created_at, c.updated_at, c.logo, c.license_number
        FROM contractors AS c 
        ORDER BY c.created_at DESC
    ";
    $contractors = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Database error in contractors.php: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
}

// Helper function for status badge classes
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'bg-success';
        case 'inactive':
            return 'bg-warning text-dark';
        case 'suspended':
            return 'bg-danger';
        default:
            return 'bg-primary';
    }
}

// Helper function to format dates
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contractors Management - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($_SESSION['username']); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars($currentDateTime); ?>">
    <title>Contractors Management - Defect Tracker</title>
	<link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
<link rel="shortcut icon" href="/favicons/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
<link rel="manifest" href="/favicons/site.webmanifest" />
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Boxicons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">	
    <style><link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
<link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
<link rel="shortcut icon" href="/favicons/favicon.ico" />
<link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
<link rel="manifest" href="/favicons/site.webmanifest" />
		
		
	<style>	
		/* Styles for the main content area */
.main-content {
    padding: 20px;
    min-height: 100vh;
    background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;  /* Centers the content horizontally */
}

        /* Styles for contractor cards */
        .contractor-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border: none;
        }

        /* Hover effect for contractor cards */
        .contractor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }

        /* Style for cards */
        .card {
            border: none;
            background: linear-gradient(to right, #ffffff, #f8f9fa);
        }

        /* Style for card headers */
        .card-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1rem;
        }

        /* Style for modal content */
        .modal-content {
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }

        /* Style for modal headers */
        .modal-header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            border-bottom: none;
        }

        /* Style for close button in modal headers */
        .modal-header .btn-close {
            color: white;
            opacity: 0.8;
        }

        /* Style for modal footers */
        .modal-footer {
            background: linear-gradient(to right, #f8f9fa, #ffffff);
            border-top: 1px solid rgba(0,0,0,0.05);
        }

        /* Style for primary buttons */
        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Hover effect for primary buttons */
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9 0%, #2c3e50 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }

        /* Style for danger buttons */
        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
        }

        /* Hover effect for danger buttons */
        .btn-danger:hover {
            background: linear-gradient(135deg, #c0392b 0%, #a93226 100%);
        }

        /* Style for status badges */
        .status-badge {
            font-weight: 500;
            padding: 0.5em 1em;
            border-radius: 20px;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">

    <div class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
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
                <i class='bx bx-plus-circle'></i> Create Contractor
            </button>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <?php if (empty($contractors)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        No contractors found. Create your first contractor using the 'Create Contractor' button.
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
                                    <span class="badge <?php echo getStatusBadgeClass($contractor['status']); ?> status-badge">
                                        <?php echo ucfirst(htmlspecialchars($contractor['status'])); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <p class="card-text">
                                        Contact: <?php echo htmlspecialchars($contractor['contact_name']); ?><br>
                                        Email: <?php echo htmlspecialchars($contractor['email']); ?><br>
                                        Phone: <?php echo htmlspecialchars($contractor['phone']); ?><br>
                                        Trade: <?php echo htmlspecialchars($contractor['trade']); ?><br>
                                        Address: <?php echo htmlspecialchars($contractor['address_line1']); ?>, <?php echo htmlspecialchars($contractor['address_line2']); ?><br>
                                        City: <?php echo htmlspecialchars($contractor['city']); ?><br>
                                        County: <?php echo htmlspecialchars($contractor['county']); ?><br>
                                        Postcode: <?php echo htmlspecialchars($contractor['postcode']); ?><br>
                                    </p>
                                    <?php if ($contractor['logo']): ?>
                                        <img src="uploads/logos/<?php echo htmlspecialchars($contractor['logo']); ?>" alt="Contractor Logo" style="max-width: 100px; max-height: 100px;">
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer">
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editContractorModal<?php echo $contractor['id']; ?>">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger"
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
                                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                        <input type="hidden" name="action" value="update_contractor">
                                        <input type="hidden" name="contractor_id" value="<?php echo $contractor['id']; ?>">
                                        <input type="hidden" name="old_logo" value="<?php echo htmlspecialchars($contractor['logo']); ?>">

                                        <div class="modal-header">
                                            <h5 class="modal-title">Edit Contractor</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-3">
                                                <label class="form-label required">Company Name</label>
                                                <input type="text" name="company_name" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['company_name']); ?>" required>
                                                <div class="invalid-feedback">Company name is required</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Contact Name</label>
                                                <input type="text" name="contact_name" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['contact_name']); ?>" required>
                                                <div class="invalid-feedback">Contact name is required</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Email</label>
                                                <input type="email" name="email" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['email']); ?>" required>
                                                <div class="invalid-feedback">Email is required</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label required">Phone</label>
                                                <input type="text" name="phone" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['phone']); ?>" required>
                                                <div class="invalid-feedback">Phone is required</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Trade</label>
                                                <input type="text" name="trade" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['trade']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Address Line 1</label>
                                                <input type="text" name="address_line1" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['address_line1']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Address Line 2</label>
                                                <input type="text" name="address_line2" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['address_line2']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">City</label>
                                                <input type="text" name="city" class="form-control" 
                                                       value="<?php echo htmlspecialchars($contractor['city']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">County</label>
                                                <input type="text" name="county" class="form-control"
                                                       value="<?php echo htmlspecialchars($contractor['county']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Postcode</label>
                                                <input type="text" name="postcode" class="form-control"
                                                       value="<?php echo htmlspecialchars($contractor['postcode']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">VAT Number</label>
                                                <input type="text" name="vat_number" class="form-control"
                                                       value="<?php echo htmlspecialchars($contractor['vat_number']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Company Number</label>
                                                <input type="text" name="company_number" class="form-control"
                                                       value="<?php echo htmlspecialchars($contractor['company_number']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Insurance Info</label>
                                                <textarea name="insurance_info" class="form-control" rows="3"><?php echo htmlspecialchars($contractor['insurance_info']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">UTR Number</label>
                                                <input type="text" name="utr_number" class="form-control"
                                                       value="<?php echo htmlspecialchars($contractor['utr_number']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Notes</label>
                                                <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($contractor['notes']); ?></textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Status</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="active" <?php echo $contractor['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                    <option value="inactive" <?php echo $contractor['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                    <option value="suspended" <?php echo $contractor['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">License Number</label>
                                                <input type="text" name="license_number" class="form-control"
                                                       value="<?php echo htmlspecialchars($contractor['license_number']); ?>">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Logo</label>
                                                <input type="file" name="logo" class="form-control">
                                                <?php if ($contractor['logo']): ?>
                                                    <img src="uploads/logos/<?php echo htmlspecialchars($contractor['logo']); ?>" alt="Contractor Logo" style="max-width: 100px; max-height: 100px;">
                                                <?php endif; ?>
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
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="create_contractor">

                        <div class="modal-header">
                            <h5 class="modal-title">Create New Contractor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Name</label>
                                <input type="text" name="contact_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Trade</label>
                                <input type="text" name="trade" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address Line 1</label>
                                <input type="text" name="address_line1" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" name="address_line2" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" name="city" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">County</label>
                                <input type="text" name="county" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Postcode</label>
                                <input type="text" name="postcode" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">VAT Number</label>
                                <input type="text" name="vat_number" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Company Number</label>
                                <input type="text" name="company_number" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Insurance Info</label>
                                <textarea name="insurance_info" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">UTR Number</label>
                                <input type="text" name="utr_number" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select" required>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="suspended">Suspended</option>
                                </select>
                            </div>
                             <div class="mb-3">
                                <label class="form-label">License Number</label>
                                <input type="text" name="license_number" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Logo</label>
                                <input type="file" name="logo" class="form-control">
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
    </div>

    <!-- Delete Contractor Form -->
    <form id="deleteContractorForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_contractor">
        <input type="hidden" name="contractor_id" id="deleteContractorId">
    </form>

    <!-- Bootstrap JavaScript -->
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

            // Delete contractor confirmation
            window.confirmDeleteContractor = function(contractorId) {
                if (confirm('Are you sure you want to delete this contractor? This action cannot be undone.')) {
                    const form = document.getElementById('deleteContractorForm');
                    document.getElementById('deleteContractorId').value = contractorId;
                    form.submit();
                }
            };

            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>