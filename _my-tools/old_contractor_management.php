<?php
// contractor_management.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-18 14:02:35
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = 'Contractor Management';
$success_message = '';
$error_message = '';

// [Keep your existing PHP code for database operations]

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
            transition: margin-left 0.3s ease;
        }

        .card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem;
        }

        .card-title {
            color: #2c3e50;
            margin: 0;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .card-body {
            padding: 1rem;
        }

        .card-footer {
            background-color: transparent;
            border-top: 1px solid rgba(0,0,0,.125);
            padding: 1rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .contractor-info {
            margin-bottom: 0.5rem;
        }

        .contractor-info label {
            font-weight: 500;
            color: #6c757d;
            font-size: 0.875rem;
        }

        .modal-content {
            border: none;
            border-radius: 0.5rem;
        }

        .modal-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-footer {
            background-color: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }

        .alert {
            border: none;
            border-radius: 0.5rem;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        /* Status badges */
        .badge {
            padding: 0.5em 0.75em;
            font-weight: 500;
        }

        /* Action buttons */
        .btn-group {
            box-shadow: none;
        }

        .btn-group .btn {
            border-radius: 0.25rem;
            margin: 0 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
            <div>
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Contractor Management</li>
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
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class='bx bx-error-circle me-2'></i>
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Contractors List -->
        <div class="row g-3">
            <?php foreach ($contractors as $contractor): ?>
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title text-truncate" title="<?php echo htmlspecialchars($contractor['company_name']); ?>">
                                <?php echo htmlspecialchars($contractor['company_name']); ?>
                            </h5>
                            <div class="dropdown">
                                <button class="btn btn-link text-muted p-0" data-bs-toggle="dropdown">
                                    <i class='bx bx-dots-vertical-rounded'></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" data-bs-toggle="modal" 
                                                data-bs-target="#editContractorModal<?php echo $contractor['id']; ?>">
                                            <i class='bx bx-edit'></i> Edit
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item text-danger" 
                                                onclick="confirmDeleteContractor(<?php echo $contractor['id']; ?>)">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="contractor-info">
                                <label><i class='bx bx-user me-2'></i>Contact Person</label>
                                <div><?php echo htmlspecialchars($contractor['contact_name']); ?></div>
                            </div>
                            <div class="contractor-info">
                                <label><i class='bx bx-envelope me-2'></i>Email</label>
                                <div>
                                    <a href="mailto:<?php echo htmlspecialchars($contractor['email']); ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($contractor['email']); ?>
                                    </a>
                                </div>
                            </div>
                            <div class="contractor-info">
                                <label><i class='bx bx-phone me-2'></i>Phone</label>
                                <div>
                                    <a href="tel:<?php echo htmlspecialchars($contractor['phone']); ?>" class="text-decoration-none">
                                        <?php echo formatPhoneNumber($contractor['phone']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent">
                            <small class="text-muted">
                                <i class='bx bx-calendar me-1'></i>
                                Added: <?php echo date('M d, Y', strtotime($contractor['created_at'])); ?>
                            </small>
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
                                        <label class="form-label">Company Name</label>
                                        <input type="text" name="company_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($contractor['company_name']); ?>" required>
                                        <div class="invalid-feedback">Please provide a company name.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Contact Person</label>
                                        <input type="text" name="contact_name" class="form-control" 
                                               value="<?php echo htmlspecialchars($contractor['contact_name']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?php echo htmlspecialchars($contractor['email']); ?>" required>
                                        <div class="invalid-feedback">Please provide a valid email address.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?php echo htmlspecialchars($contractor['phone']); ?>">
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
            <?php endforeach; ?>
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
                                <label class="form-label">Company Name</label>
                                <input type="text" name="company_name" class="form-control" required>
                                <div class="invalid-feedback">Please provide a company name.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_name" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" required>
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="tel" name="phone" class="form-control">
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

    <!-- Delete Contractor Form (Hidden) -->
    <form id="deleteContractorForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_contractor">
        <input type="hidden" name="contractor_id" id="deleteContractorId">
    </form>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Form validation
        (function () {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        // Delete contractor confirmation
        function confirmDeleteContractor(contractorId) {
            if (confirm('Are you sure you want to delete this contractor? This action cannot be undone.')) {
                document.getElementById('deleteContractorId').value = contractorId;
                document.getElementById('deleteContractorForm').submit();
            }
        }

        // Enable tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    </script>
</body>
</html>