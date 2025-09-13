<?php
// add_contractor.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-18 13:42:13
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

// Current user and datetime
$currentUser = $_SESSION['username'];
$currentDateTime = date('Y-m-d H:i:s');

// Initialize variables for form data
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        $required_fields = ['company_name', 'contact_name', 'email', 'phone', 'trade'];
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
            }
        }

        if (!empty($missing_fields)) {
            throw new Exception("Required fields missing: " . implode(", ", $missing_fields));
        }

        // Validate email format
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Validate UK phone number format (basic validation)
        $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
        if (!preg_match('/^(?:(?:(?:44\s?|0)(?:1|2|3|7|8)){1}\d{9})$/', $phone)) {
            throw new Exception("Invalid UK phone number format");
        }

        // Prepare contractor insert
        $query = "INSERT INTO contractors (
            company_name, contact_name, email, phone, trade,
            address_line1, address_line2, city, county, postcode,
            vat_number, company_number, insurance_info, utr_number,
            notes, created_by, created_at, status
        ) VALUES (
            :company_name, :contact_name, :email, :phone, :trade,
            :address_line1, :address_line2, :city, :county, :postcode,
            :vat_number, :company_number, :insurance_info, :utr_number,
            :notes, :created_by, :created_at, :status
        )";

        $stmt = $db->prepare($query);
        
        // Execute with parameters
        $stmt->execute([
            'company_name' => $_POST['company_name'],
            'contact_name' => $_POST['contact_name'],
            'email' => $_POST['email'],
            'phone' => $_POST['phone'],
            'trade' => $_POST['trade'],
            'address_line1' => $_POST['address_line1'] ?? '',
            'address_line2' => $_POST['address_line2'] ?? '',
            'city' => $_POST['city'] ?? '',
            'county' => $_POST['county'] ?? '',
            'postcode' => $_POST['postcode'] ?? '',
            'vat_number' => $_POST['vat_number'] ?? '',
            'company_number' => $_POST['company_number'] ?? '',
            'insurance_info' => $_POST['insurance_info'] ?? '',
            'utr_number' => $_POST['utr_number'] ?? '',
            'notes' => $_POST['notes'] ?? '',
            'created_by' => $_SESSION['user_id'],
            'created_at' => $currentDateTime,
            'status' => 'active'
        ]);

        $success_message = "Contractor added successfully!";
        
    } catch (Exception $e) {
        $error_message = "Error adding contractor: " . $e->getMessage();
    }
}

// Get list of trades for dropdown
$trades = [
    'General Builder',
    'Electrician',
    'Plumber',
    'Gas Engineer',
    'Carpenter',
    'Bricklayer',
    'Painter & Decorator',
    'Roofer',
    'Flooring Specialist',
    'Landscaper',
    'Plasterer',
    'Tiler',
    'Glazier',
    'Steel Fabricator',
    'Other'
];

// Get list of UK counties for dropdown
$counties = [
    'Avon', 'Bedfordshire', 'Berkshire', 'Buckinghamshire',
    'Cambridgeshire', 'Cheshire', 'Cleveland', 'Cornwall',
    'Cumbria', 'Derbyshire', 'Devon', 'Dorset', 'Durham',
    'East Sussex', 'Essex', 'Gloucestershire', 'Greater London',
    'Greater Manchester', 'Hampshire', 'Herefordshire', 'Hertfordshire',
    'Isle of Wight', 'Kent', 'Lancashire', 'Leicestershire', 'Lincolnshire',
    'Merseyside', 'Norfolk', 'North Yorkshire', 'Northamptonshire',
    'Northumberland', 'Nottinghamshire', 'Oxfordshire', 'Rutland',
    'Shropshire', 'Somerset', 'South Yorkshire', 'Staffordshire', 'Suffolk',
    'Surrey', 'Tyne and Wear', 'Warwickshire', 'West Midlands',
    'West Sussex', 'West Yorkshire', 'Wiltshire', 'Worcestershire'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Contractor - Construction Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
            background-color: #f8f9fa;
        }

        .card {
            box-shadow: 0 2px 4px rgba(0,0,0,.05);
            border: none;
            margin-bottom: 1rem;
        }

        .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid rgba(0,0,0,.05);
            padding: 1rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .form-label {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }

        /* Form validation styles */
        .was-validated .form-control:invalid:focus,
        .form-control.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220,53,69,.25);
        }

        .was-validated .form-control:valid:focus,
        .form-control.is-valid:focus {
            border-color: #198754;
            box-shadow: 0 0 0 0.2rem rgba(25,135,84,.25);
        }
    </style>
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Add New Contractor</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="contractors.php" class="btn btn-sm btn-outline-secondary">
                    <i class='bx bx-arrow-back'></i> Back to Contractors
                </a>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success_message); ?>
                <br>
                <a href="contractors.php" class="alert-link">Return to Contractors List</a>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="POST" class="needs-validation" novalidate>
            <div class="row g-3">
                <!-- Company Information -->
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Company Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="company_name" class="form-label required-field">Company Name</label>
                                <input type="text" class="form-control" id="company_name" name="company_name" required>
                                <div class="invalid-feedback">Please provide a company name.</div>
                            </div>
                            <div class="mb-3">
                                <label for="vat_number" class="form-label">VAT Number</label>
                                <input type="text" class="form-control" id="vat_number" name="vat_number" 
                                       placeholder="GB123456789">
                            </div>
                            <div class="mb-3">
                                <label for="company_number" class="form-label">Company Number</label>
                                <input type="text" class="form-control" id="company_number" name="company_number" 
                                       placeholder="12345678">
                            </div>
                            <div class="mb-3">
                                <label for="utr_number" class="form-label">UTR Number</label>
                                <input type="text" class="form-control" id="utr_number" name="utr_number" 
                                       placeholder="1234567890">
                            </div>
                            <div class="mb-3">
                                <label for="trade" class="form-label required-field">Trade/Specialty</label>
                                <select class="form-select" id="trade" name="trade" required>
                                    <option value="">Select Trade</option>
                                    <?php foreach ($trades as $trade): ?>
                                        <option value="<?php echo htmlspecialchars($trade); ?>">
                                            <?php echo htmlspecialchars($trade); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a trade.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Contact Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="contact_name" class="form-label required-field">Contact Name</label>
                                <input type="text" class="form-control" id="contact_name" name="contact_name" required>
                                <div class="invalid-feedback">Please provide a contact name.</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label required-field">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                                <div class="invalid-feedback">Please provide a valid email address.</div>
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label required-field">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       placeholder="07123 456789" required>
                                <div class="invalid-feedback">Please provide a valid UK phone number.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Address Information -->
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Address Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="address_line1" class="form-label">Address Line 1</label>
                                    <input type="text" class="form-control" id="address_line1" name="address_line1">
                                </div>
                                <div class="col-12">
                                    <label for="address_line2" class="form-label">Address Line 2</label>
                                    <input type="text" class="form-control" id="address_line2" name="address_line2">
                                </div>
                                <div class="col-md-6">
                                    <label for="city" class="form-label">City/Town</label>
                                    <input type="text" class="form-control" id="city" name="city">
                                </div>
                                <div class="col-md-3">
                                    <label for="county" class="form-label">County</label>
                                    <select class="form-select" id="county" name="county">
                                        <option value="">Select County</option>
                                        <?php foreach ($counties as $county): ?>
                                            <option value="<?php echo htmlspecialchars($county); ?>">
                                                <?php echo htmlspecialchars($county); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="postcode" class="form-label">Postcode</label>
                                    <input type="text" class="form-control" id="postcode" name="postcode">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Additional Information -->
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Additional Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="insurance_info" class="form-label">Insurance Information</label>
                                <textarea class="form-control" id="insurance_info" name="insurance_info" rows="3"></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <button type="submit" class="btn btn-primary">
                    <i class='bx bx-plus-circle'></i> Add Contractor
                </button>
                <a href="contractors.php" class="btn btn-secondary">
                    <i class='bx bx-x'></i> Cancel
                </a>
            </div>
        </form>
    </div>

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

        // UK postcode validation
        document.getElementById('postcode').addEventListener('blur', function() {
            let postcode = this.value.toUpperCase();
            let regex = /^[A-Z]{1,2}[0-9][A-Z0-9]? ?[0-9][A-Z]{2}$/;
            if (postcode && !regex.test(postcode)) {
                this.setCustomValidity('Please enter a valid UK postcode');
            } else {
                this.setCustomValidity('');
            }
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('blur', function() {
            let phone = this.value.replace(/\s+/g, '');
            if (phone.startsWith('0') || phone.startsWith('44')) {
                // Basic UK phone number validation
                let regex = /^(?:(?:(?:44\s?|0)(?:1|2|3|7|8)){1}\d{9})$/;
                if (!regex.test(phone.replace(/\s+/g, ''))) {
                    this.setCustomValidity('Please enter a valid UK phone number');
                } else {
                    this.setCustomValidity('');
                }
            }
        });
    </script>
</body>
</html>