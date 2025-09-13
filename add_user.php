<?php
// add_user.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-27 18:32:20
// Current User's Login: irlam

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

date_default_timezone_set('Europe/London');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

$pageTitle = 'Add New User';
$currentUser = $_SESSION['username'];
$success_message = '';
$error_message = '';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch active contractors
    $stmt = $db->prepare("
        SELECT 
            id,
            company_name,
            contact_name,
            trade,
            city,
            county
        FROM contractors 
        WHERE status = 'active' 
            AND deleted_at IS NULL
        ORDER BY company_name ASC
    ");
    $stmt->execute();
    $contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Validation functions
    function isValidUsername($username) {
        return preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username);
    }

    function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= 100;
    }

    function isValidPassword($password) {
        return strlen($password) >= 8;
    }

    function isValidUserType($type) {
        $validTypes = ['admin', 'manager', 'contractor', 'inspector', 'viewer'];
        return in_array($type, $validTypes);
    }

    function mapUserTypeToRole($type) {
    // Based on the roles table in your database
    $roleMap = [
        'admin' => 'admin',           // id = 1
        'manager' => 'manager',       // id = 2
        'contractor' => 'contractor', // id = 3
        'viewer' => 'viewer',         // id = 4
        'inspector' => 'client'       // id = 5 (maps inspector to client role)
    ];
    return $roleMap[$type] ?? 'viewer';
}

    function getRoleIdByName($db, $roleName) {
    $stmt = $db->prepare("
        SELECT id 
        FROM roles 
        WHERE name = ?
        LIMIT 1
    ");
    $stmt->execute([$roleName]);
    $result = $stmt->fetchColumn();
    
    if (!$result) {
        // If role not found, log the error
        error_log("Role not found: " . $roleName);
        throw new Exception("Invalid role configuration. Role '" . $roleName . "' not found.");
    }
    
    return $result;
}

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate input
        $username = trim(filter_var($_POST['username'], FILTER_SANITIZE_STRING));
        $email = trim(filter_var($_POST['email'], FILTER_SANITIZE_EMAIL));
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        $userType = trim(filter_var($_POST['user_type'], FILTER_SANITIZE_STRING));
        $firstName = trim(filter_var($_POST['first_name'], FILTER_SANITIZE_STRING));
        $lastName = trim(filter_var($_POST['last_name'], FILTER_SANITIZE_STRING));
        $contractorId = isset($_POST['department']) ? trim(filter_var($_POST['department'], FILTER_SANITIZE_STRING)) : '';
        $fullName = trim($firstName . ' ' . $lastName);

        // Validation checks
        $errors = [];

        if (!isValidUsername($username)) {
            $errors[] = "Username must be 3-50 characters and contain only letters, numbers, and underscores";
        }

        if (!isValidEmail($email)) {
            $errors[] = "Please enter a valid email address";
        }

        if (!isValidPassword($password)) {
            $errors[] = "Password must be at least 8 characters";
        }

        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }

        if (!isValidUserType($userType)) {
            $errors[] = "Invalid user type selected";
        }

        // Validate contractor selection for contractor users
        if ($userType === 'contractor') {
            if (empty($contractorId)) {
                $errors[] = "Contractor selection is required for contractor users";
            } else {
                // Verify the contractor exists and is active
                $stmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM contractors 
                    WHERE id = ? 
                    AND status = 'active'
                    AND deleted_at IS NULL
                ");
                $stmt->execute([$contractorId]);
                if ($stmt->fetchColumn() == 0) {
                    $errors[] = "Invalid contractor selected";
                }
            }
        }

        // Check if username already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already exists";
        }

        // Check if email already exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already exists";
        }
        if (empty($errors)) {
            // Begin transaction
            $db->beginTransaction();

            try {
                // Hash password
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                // Get role information
                $roleName = mapUserTypeToRole($userType);
                $roleId = getRoleIdByName($db, $roleName);

                if (!$roleId) {
                    throw new Exception("Invalid role configuration");
                }

                // Get contractor details if user type is contractor
                $contractorDetails = null;
                if ($userType === 'contractor' && !empty($contractorId)) {
                    $stmt = $db->prepare("
                        SELECT company_name, trade 
                        FROM contractors 
                        WHERE id = ? 
                        AND status = 'active'
                        AND deleted_at IS NULL
                    ");
                    $stmt->execute([$contractorId]);
                    $contractorDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                // Insert new user based on user type
                if ($userType === 'contractor') {
                    $stmt = $db->prepare("
                        INSERT INTO users (
                            username, 
                            password,
                            first_name, 
                            last_name,
                            email,
                            user_type,
                            status,
                            created_by,
                            full_name,
                            role,
                            role_id,
                            contractor_id,
                            contractor_name,
                            contractor_trade,
                            theme_preference,
                            is_active
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, 
                            'active',
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            ?,
                            'light',
                            1
                        )
                    ");

                    $stmt->execute([
                        $username,
                        $passwordHash,
                        $firstName,
                        $lastName,
                        $email,
                        $userType,
                        $_SESSION['username'],
                        $fullName,
                        $roleName,
                        $roleId,
                        $contractorId,
                        $contractorDetails ? $contractorDetails['company_name'] : null,
                        $contractorDetails ? $contractorDetails['trade'] : null
                    ]);
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO users (
                            username, 
                            password,
                            first_name, 
                            last_name,
                            email,
                            user_type,
                            status,
                            created_by,
                            full_name,
                            role,
                            role_id,
                            theme_preference,
                            is_active
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, 
                            'active',
                            ?,
                            ?,
                            ?,
                            ?,
                            'light',
                            1
                        )
                    ");

                    $stmt->execute([
                        $username,
                        $passwordHash,
                        $firstName,
                        $lastName,
                        $email,
                        $userType,
                        $_SESSION['username'],
                        $fullName,
                        $roleName,
                        $roleId
                    ]);
                }

                $newUserId = $db->lastInsertId();

                // Insert into user_roles
                $stmt = $db->prepare("
                    INSERT INTO user_roles (user_id, role_id, created_at, created_by)
                    VALUES (?, ?, UTC_TIMESTAMP(), ?)
                ");
                $stmt->execute([$newUserId, $roleId, $_SESSION['user_id']]);

                // Log the user creation
                $stmt = $db->prepare("
                    INSERT INTO user_logs (
                        user_id,
                        action,
                        action_by,
                        action_at,
                        ip_address,
                        details
                    ) VALUES (
                        ?,
                        'create_user',
                        ?,
                        UTC_TIMESTAMP(),
                        ?,
                        ?
                    )
                ");

                $logDetails = json_encode([
                    'username' => $username,
                    'email' => $email,
                    'user_type' => $userType,
                    'role' => $roleName,
                    'role_id' => $roleId,
                    'contractor_id' => $userType === 'contractor' ? $contractorId : null,
                    'contractor_name' => $contractorDetails ? $contractorDetails['company_name'] : null,
                    'created_by' => $_SESSION['username']
                ]);

                $stmt->execute([
                    $newUserId,
                    $_SESSION['user_id'],
                    $_SERVER['REMOTE_ADDR'],
                    $logDetails
                ]);

                $db->commit();
                $success_message = "User successfully created";

                // Clear sensitive data
                unset($password, $confirmPassword, $passwordHash);

                // Redirect after successful creation
                header("Location: user_management.php?success=user_created");
                exit();

            } catch (Exception $e) {
                $db->rollBack();
                throw new Exception("Failed to create user: " . $e->getMessage());
            }
        } else {
            $error_message = implode("<br>", $errors);
        }
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Error in add_user.php: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Add New User - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); ?>">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding-top: 1.5rem;
            padding-bottom: 1.5rem;
        }
        
        .container {
            max-width: 900px;
            margin-top: 56px; /* Ensure content is not hidden behind the navbar */
        }
        
        .form-card {
            border-radius: 10px;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            background-color: #fff;
            border: none;
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            padding: 1.25rem 1.5rem;
        }
        
        .card-body {
            padding: 1.75rem;
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .form-control, .form-select {
            padding: 0.65rem 0.85rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        
        .password-wrapper {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
            z-index: 10;
        }
        
        .validation-check {
            font-size: 0.875rem;
            color: #6c757d;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
        }
        
        .validation-check i {
            margin-right: 5px;
        }
        
        .validation-check.valid {
            color: #198754;
        }
        
        .validation-check.invalid {
            color: #dc3545;
        }
        
        .btn {
            padding: 0.6rem 1.2rem;
            font-weight: 500;
        }
        
        .btn-primary {
            background-color: #0d6efd;
        }
        
        .btn-light {
            background-color: #f8f9fa;
            border-color: #dee2e6;
        }
        
        .form-text {
            color: #6c757d;
            margin-top: 0.25rem;
        }
        
        .breadcrumb {
            margin-bottom: 1.5rem;
        }
        
        #contractorDetails {
            padding: 0.5rem 0.75rem;
            border-left: 3px solid #6c757d;
            background-color: rgba(108, 117, 125, 0.1);
            border-radius: 0 4px 4px 0;
            margin-top: 0.5rem;
        }
        
        .alert {
            border-radius: 8px;
        }
        
        .page-header {
            margin-bottom: 2rem;
        }
        
        .page-title {
            font-weight: 600;
            color: #212529;
        }
    </style>
</head>
<body>
<?php
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
$navbar->render();
?>
    <div class="container">
        <div class="page-header">
            <h1 class="h3 page-title"><?php echo $pageTitle; ?></h1>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bx bx-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bx bx-error-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add User Form -->
        <div class="card form-card">
            <div class="card-header">
                <h5 class="card-title mb-0">User Information</h5>
            </div>
            <div class="card-body">
                <form id="addUserForm" method="post" class="needs-validation" novalidate>
                    <div class="row">
                        <!-- Username -->
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   pattern="^[a-zA-Z0-9_]{3,50}$" required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                            <div class="form-text">* 3-50 characters, letters, numbers, and underscores only!</div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
							<div class="form-text">* Must be a real email address!</div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- First Name -->
                        <div class="col-md-6 mb-3">
                            <label for="first_name" class="form-label">First Name</label>
                            <input type="text" class="form-control" id="first_name" name="first_name"
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
							<div class="form-text">Optional but its better to enter this for when generating reports and viewing completed assigned defects..</div>
                        </div>

                        <!-- Last Name -->
                        <div class="col-md-6 mb-3">
                            <label for="last_name" class="form-label">Last Name</label>
                            <input type="text" class="form-control" id="last_name" name="last_name"
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
							<div class="form-text">Optional but its better to enter this for when generating reports and viewing completed assigned defects..</div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Password -->
<div class="col-md-6 mb-3">
    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
    <div class="password-wrapper">
        <input type="password" class="form-control" id="password" name="password" required>
        <i class="bx bx-hide password-toggle"></i>
    </div>
    <div class="validation-checks mt-2">
        <div class="validation-check" data-requirement="length">
            <i class="bx bx-x"></i>At least 8 characters
        </div>
    </div>
</div>

<!-- Confirm Password -->
<div class="col-md-6 mb-3">
    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
    <div class="password-wrapper">
        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
        <i class="bx bx-hide password-toggle"></i>
    </div>
</div>
</div>

<div class="row">
    <!-- User Type -->
    <div class="col-md-6 mb-3">
        <label for="user_type" class="form-label">User Type <span class="text-danger">*</span></label>
        <select class="form-select" id="user_type" name="user_type" required>
            <option value="" selected disabled>Select user type</option>
            <option value="admin">Admin - Full system access</option>
            <option value="manager">Manager - Project and defects management access</option>
            <option value="contractor">Contractor - Limited to assigned defects</option>
            <option value="inspector">Inspector - Inspection and reporting access only</option>
            <option value="viewer">Viewer - View-only access</option>
        </select>
        <div class="form-text">Admin - Full system access.</div>
        <div class="form-text">Manager - Project and defects management access.</div>
        <div class="form-text">Contractor - Limited to viewing and completing assigned defects.</div>
        <div class="form-text">Inspector - Inspection and reporting access only.</div>
        <div class="form-text">Viewer - View-only access.</div>
    </div>

    <!-- Contractor Selection -->
    <div class="col-md-6 mb-3">
        <label for="department" class="form-label">Select Contractor <span class="text-danger contractor-required d-none">*</span></label>
        <div class="form-text">Only selectable if the user is a CONTRACTOR!</div>
        <option value="">Select Contractor</option>
        <select class="form-select" id="department" name="department">
            <?php
            $tradeGroups = [];
            foreach ($contractors as $contractor) {
                if (!isset($tradeGroups[$contractor['trade']])) {
                    $tradeGroups[$contractor['trade']] = [];
                }
                $tradeGroups[$contractor['trade']][] = $contractor;
            }
            ksort($tradeGroups);
            
            foreach ($tradeGroups as $trade => $tradeContractors):
            ?>
                <optgroup label="<?php echo htmlspecialchars($trade); ?>">
                    <?php foreach ($tradeContractors as $contractor): ?>
                        <option value="<?php echo htmlspecialchars($contractor['id']); ?>" 
                                data-type="contractor"
                                data-trade="<?php echo htmlspecialchars($contractor['trade']); ?>"
                                data-contact="<?php echo htmlspecialchars($contractor['contact_name']); ?>"
                                data-location="<?php echo htmlspecialchars(trim($contractor['city'] . (($contractor['city'] && $contractor['county']) ? ', ' : '') . $contractor['county'])); ?>">
                            <?php 
                            echo htmlspecialchars($contractor['company_name']);
                            if ($contractor['city'] || $contractor['county']) {
                                echo ' (' . htmlspecialchars(trim($contractor['city'] . (($contractor['city'] && $contractor['county']) ? ', ' : '') . $contractor['county'])) . ')';
                            }
                            ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
        <div id="contractorDetails" class="d-none mt-2">
            <small class="text-muted">
                <span id="contactName"></span>
                <span id="location"></span>
            </small>
        </div>
    </div>
</div>

<hr class="my-4">

<!-- Submit Buttons -->
<div class="d-flex justify-content-end gap-2">
    <a href="user_management.php" class="btn btn-light">Cancel</a>
    <button type="submit" class="btn btn-primary">
        <i class="bx bx-user-plus me-1"></i>Create User
    </button>
</div>
</form>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('addUserForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const username = document.getElementById('username');
        const userType = document.getElementById('user_type');
        const department = document.getElementById('department');
        const contractorDetails = document.getElementById('contractorDetails');
        const contactNameSpan = document.getElementById('contactName');
        const locationSpan = document.getElementById('location');
        const contractorRequired = document.querySelector('.contractor-required');

        // Password visibility toggle
        document.querySelectorAll('.password-toggle').forEach(function(toggle) {
            toggle.addEventListener('click', function() {
                const input = this.parentElement.querySelector('input');
                if (input.type === 'password') {
                    input.type = 'text';
                    this.classList.remove('bx-hide');
                    this.classList.add('bx-show');
                } else {
                    input.type = 'password';
                    this.classList.remove('bx-show');
                    this.classList.add('bx-hide');
                }
            });
        });

        // Password validation
        password.addEventListener('input', function() {
            const lengthCheck = document.querySelector('[data-requirement="length"]');
            const isValid = this.value.length >= 8;
            
            lengthCheck.classList.toggle('valid', isValid);
            lengthCheck.classList.toggle('invalid', !isValid);
            lengthCheck.querySelector('i').className = `bx ${isValid ? 'bx-check' : 'bx-x'}`;
        });

        // User Type and Contractor coordination
        userType.addEventListener('change', function() {
            const selectedType = this.value;
            
            contractorRequired.classList.toggle('d-none', selectedType !== 'contractor');
            department.required = (selectedType === 'contractor');
            department.disabled = (selectedType !== 'contractor');
            
            if (selectedType !== 'contractor') {
                department.value = '';
                contractorDetails.classList.add('d-none');
            }

            // Update form validation state
            if (selectedType === 'contractor') {
                department.setAttribute('required', '');
                if (!department.value) {
                    department.classList.add('is-invalid');
                }
            } else {
                department.removeAttribute('required');
                department.classList.remove('is-invalid');
            }
        });

        // Contractor Selection Details
        department.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (selectedOption && selectedOption.value && selectedOption.dataset.type === 'contractor') {
                contactNameSpan.textContent = `Contact: ${selectedOption.dataset.contact}`;
                locationSpan.textContent = selectedOption.dataset.location ? 
                    ` | Location: ${selectedOption.dataset.location}` : '';
                contractorDetails.classList.remove('d-none');
                this.classList.remove('is-invalid');
            } else {
                contractorDetails.classList.add('d-none');
                if (userType.value === 'contractor') {
                    this.classList.add('is-invalid');
                }
            }
        });

        // Form validation
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }

            if (password.value !== confirmPassword.value) {
                event.preventDefault();
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }

            if (userType.value === 'contractor' && !department.value) {
                event.preventDefault();
                department.setCustomValidity('Please select a contractor');
            } else {
                department.setCustomValidity('');
            }

            form.classList.add('was-validated');
        });

        // Real-time password match validation
        confirmPassword.addEventListener('input', function() {
            if (password.value === this.value) {
                this.setCustomValidity('');
                this.classList.remove('is-invalid');
            } else {
                this.setCustomValidity('Passwords do not match');
                this.classList.add('is-invalid');
            }
        });

        // Username availability check with debounce
        let usernameTimeout;
        username.addEventListener('input', function() {
            clearTimeout(usernameTimeout);
            const inputValue = this.value;
            
            if (inputValue.length >= 3) {
                usernameTimeout = setTimeout(() => {
                    fetch(`check_username.php?username=${encodeURIComponent(inputValue)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.exists) {
                                this.setCustomValidity('Username already taken');
                                this.classList.add('is-invalid');
                            } else {
                                this.setCustomValidity('');
                                this.classList.remove('is-invalid');
                            }
                        })
                        .catch(error => console.error('Error checking username:', error));
                }, 500);
            }
        });

        // Auto-dismiss alerts
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // Initialize form state
        userType.dispatchEvent(new Event('change'));
    });
</script>
</body>
</html>