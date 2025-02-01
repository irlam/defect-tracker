<?php
// user_management.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-27 21:38:30
// Current User's Login: irlam

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication and Authorization check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit();
}

// Check if user has admin or manager permissions
if (!in_array($_SESSION['user_type'], ['admin', 'manager'])) {
    // Log unauthorized access attempt
    error_log("Unauthorized access attempt to user_management.php by user: " . $_SESSION['username']);
    header("Location: dashboard.php?error=unauthorized");
    exit();
}

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';

// Define user types as a constant array
define('USER_TYPES', [
    'admin' => 'Administrator',
    'manager' => 'Manager',
    'contractor' => 'Contractor',
    'viewer' => 'Viewer',
    'client' => 'Client'
]);

$pageTitle = 'User Management';
$currentUser = $_SESSION['username'];
$success_message = '';
$error_message = '';

// Define user types from schema
$userTypes = [
    'admin',
    'manager',
    'contractor',
    'viewer',
    'client'
];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Initialize the Navbar class
    $navbar = new Navbar($db, $_SESSION['user_id']);

    // Get success message from URL if exists
    if (isset($_GET['success']) && $_GET['success'] === 'user_created') {
        $success_message = "User successfully created";
    }

    // Get active contractors for the dropdown
    $stmt = $db->prepare("
        SELECT id, company_name, trade 
        FROM contractors 
        WHERE status = 'active' 
        ORDER BY company_name
    ");
    $stmt->execute();
    $contractors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get users with their roles, status, and contractor information
    $query = "
        SELECT 
            u.id,
            u.username,
            u.email,
            u.first_name,
            u.last_name,
            COALESCE(u.user_type, 'viewer') as user_type,
            COALESCE(u.status, 'inactive') as status,
            u.last_login,
            u.is_active,
            u.contractor_id,
            u.contractor_name,
            u.contractor_trade,
            COALESCE(s.active_sessions, 0) as active_sessions
        FROM users u
        LEFT JOIN (
            SELECT 
                user_id, 
                COUNT(*) as active_sessions
            FROM user_sessions 
            WHERE logged_out_at IS NULL 
            GROUP BY user_id
        ) s ON u.id = s.user_id
        WHERE u.is_active = 1
        ORDER BY u.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception("Invalid security token");
        }

        switch ($_POST['action']) {
            case 'change_status':
                if (isset($_POST['user_id'], $_POST['new_status'])) {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET 
                            status = ?,
                            updated_at = UTC_TIMESTAMP(),
                            updated_by = ?
                        WHERE id = ?
                    ");
                    
                    $stmt->execute([
                        $_POST['new_status'],
                        $_SESSION['username'],
                        $_POST['user_id']
                    ]);

                    // Log the status change
                    $stmt = $db->prepare("
                        INSERT INTO user_logs (
                            user_id,
                            action,
                            action_by,
                            action_at,
                            ip_address,
                            details
                        ) VALUES (?, 'status_changed', ?, UTC_TIMESTAMP(), ?, ?)
                    ");

                    $logDetails = json_encode([
                        'new_status' => $_POST['new_status'],
                        'changed_by' => $_SESSION['username'],
                        'timestamp' => '2025-01-27 21:39:35'
                    ]);

                    $stmt->execute([
                        $_POST['user_id'],
                        $_SESSION['user_id'],
                        $_SERVER['REMOTE_ADDR'],
                        $logDetails
                    ]);

                    $success_message = "User status updated successfully";
                }
                break;

            case 'change_type':
                if (isset($_POST['user_id'], $_POST['new_type'])) {
                    try {
                        // Validate user type
                        if (!in_array($_POST['new_type'], $userTypes)) {
                            throw new Exception("Invalid user type selected");
                        }

                        // Begin transaction
                        $db->beginTransaction();

                        // Map user type to role
                        $roleMapping = array(
                            'admin' => 'admin',
                            'manager' => 'project_manager',
                            'contractor' => 'contractor',
                            'viewer' => 'viewer',
                            'client' => 'client'
                        );

                        // Get the corresponding role
                        $role = isset($roleMapping[$_POST['new_type']]) ? $roleMapping[$_POST['new_type']] : 'viewer';

                        // Handle contractor association
                        $contractorId = null;
                        $contractorName = null;
                        $contractorTrade = null;

                        if ($_POST['new_type'] === 'contractor' && isset($_POST['contractor_id'])) {
                            // Get contractor details
                            $stmt = $db->prepare("
                                SELECT id, company_name, trade
                                FROM contractors 
                                WHERE id = ? AND status = 'active'
                            ");
                            $stmt->execute([$_POST['contractor_id']]);
                            $contractor = $stmt->fetch(PDO::FETCH_ASSOC);

                            if (!$contractor) {
                                throw new Exception("Invalid contractor selected");
                            }

                            $contractorId = $contractor['id'];
                            $contractorName = $contractor['company_name'];
                            $contractorTrade = $contractor['trade'];
                        }

                        // Update user with contractor information if applicable
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET 
                                user_type = ?,
                                role = ?,
                                contractor_id = ?,
                                contractor_name = ?,
                                contractor_trade = ?,
                                updated_at = UTC_TIMESTAMP(),
                                updated_by = ?
                            WHERE id = ?
                        ");
                        
                        if (!$stmt->execute([
                            $_POST['new_type'],
                            $role,
                            $contractorId,
                            $contractorName,
                            $contractorTrade,
                            $_SESSION['username'],
                            $_POST['user_id']
                        ])) {
                            throw new Exception("Failed to update user: " . implode(" ", $stmt->errorInfo()));
                        }

                        // Get role ID for user_roles table
                        switch ($_POST['new_type']) {
                            case 'admin':
                                $roleId = 1;
                                break;
                            case 'manager':
                                $roleId = 2;
                                break;
                            case 'contractor':
                                $roleId = 3;
                                break;
                            case 'client':
                                $roleId = 5;
                                break;
                            case 'viewer':
                            default:
                                $roleId = 4;
                                break;
                        }

                        // Update user_roles table
                        $stmt = $db->prepare("
                            INSERT INTO user_roles (user_id, role_id, created_by, created_at, updated_at)
                            VALUES (?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
                            ON DUPLICATE KEY UPDATE
                                role_id = ?,
                                updated_by = ?,
                                updated_at = UTC_TIMESTAMP()
                        ");

                        if (!$stmt->execute([
                            $_POST['user_id'],
                            $roleId,
                            $_SESSION['user_id'],
                            $roleId,
                            $_SESSION['user_id']
                        ])) {
                            throw new Exception("Failed to update user_roles: " . implode(" ", $stmt->errorInfo()));
                        }

                        // Log the change
                        $stmt = $db->prepare("
                            INSERT INTO user_logs (
                                user_id,
                                action,
                                action_by,
                                action_at,
                                ip_address,
                                details
                            ) VALUES (?, 'type_changed', ?, UTC_TIMESTAMP(), ?, ?)
                        ");

                        $logDetails = json_encode([
                            'new_type' => $_POST['new_type'],
                            'new_role' => $role,
                            'new_role_id' => $roleId,
                            'contractor_id' => $contractorId,
                            'contractor_name' => $contractorName,
                            'contractor_trade' => $contractorTrade,
                            'changed_by' => $_SESSION['username'],
                            'timestamp' => '2025-01-27 21:39:35'
                        ]);

                        $stmt->execute([
                            $_POST['user_id'],
                            $_SESSION['user_id'],
                            $_SERVER['REMOTE_ADDR'],
                            $logDetails
                        ]);

                        // Commit transaction
                        $db->commit();

                        $success_message = "User type and role updated successfully";
                    } catch (Exception $e) {
                        // Rollback transaction on error
                        $db->rollBack();
                        $error_message = "Error changing user type: " . $e->getMessage();
                        error_log("Error in change_type: " . $e->getMessage());
                    }
                }
                break;

            case 'edit_user':
                if (isset($_POST['user_id'], $_POST['username'], $_POST['email'], $_POST['first_name'], $_POST['last_name'])) {
                    try {
                        $stmt = $db->prepare("
                            UPDATE users 
                            SET 
                                username = ?,
                                email = ?,
                                first_name = ?,
                                last_name = ?,
                                updated_at = UTC_TIMESTAMP(),
                                updated_by = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $_POST['username'],
                            $_POST['email'],
                            $_POST['first_name'],
                            $_POST['last_name'],
                            $_SESSION['username'],
                            $_POST['user_id']
                        ]);

                        // Log the edit
                        $stmt = $db->prepare("
                            INSERT INTO user_logs (
                                user_id,
                                action,
                                action_by,
                                action_at,
                                ip_address,
                                details
                            ) VALUES (?, 'user_edited', ?, UTC_TIMESTAMP(), ?, ?)
                        ");

                        $logDetails = json_encode([
                            'edited_by' => $_SESSION['username'],
                            'timestamp' => '2025-01-27 21:39:35'
                        ]);

                        $stmt->execute([
                            $_POST['user_id'],
                            $_SESSION['user_id'],
                            $_SERVER['REMOTE_ADDR'],
                            $logDetails
                        ]);

                        $success_message = "User details updated successfully";
                    } catch (Exception $e) {
                        $error_message = "Error updating user: " . $e->getMessage();
                        error_log("Error in edit_user: " . $e->getMessage());
                    }
                }
                break;
        }
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
    error_log("Error in user_management.php: " . $e->getMessage());
}

// Generate new CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="User Management - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($currentUser); ?>">
    <meta name="last-modified" content="2025-01-27 21:40:43">
    <title><?php echo $pageTitle; ?> - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <style>
        .main-content {
            margin-left: 250px;
            padding: 1.5rem;
            min-height: 100vh;
            background-color: #f8f9fa;
            transition: margin-left 0.3s ease-in-out;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding-top: 60px;
            }
        }

        .user-status {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }

        .user-status.active {
            background-color: #198754;
        }

        .user-status.inactive {
            background-color: #dc3545;
        }

        .type-badge {
            text-transform: capitalize;
        }

        .type-badge[data-type="admin"] {
            background-color: #dc3545 !important;
        }

        .type-badge[data-type="manager"] {
            background-color: #198754 !important;
        }

        .type-badge[data-type="contractor"] {
            background-color: #0d6efd !important;
        }

        .type-badge[data-type="viewer"] {
            background-color: #6c757d !important;
        }

        .type-badge[data-type="client"] {
            background-color: #17a2b8 !important;
        }

        .changing {
            animation: fade 1s;
        }

        @keyframes fade {
            0% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .contractor-info {
            font-size: 0.875rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body>
    <?php echo $navbar->render(); ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">User Management</li>
                    </ol>
                </nav>
            </div>
            <a href="add_user.php" class="btn btn-primary">
                <i class="bx bx-user-plus me-1"></i>Add New User
            </a>
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

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Users</h5>
                    <div class="input-group" style="width: 300px;">
                        <span class="input-group-text"><i class="bx bx-search"></i></span>
                        <input type="text" class="form-control" id="searchInput" placeholder="Search users...">
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>User Type</th>
                                <th>Contractor</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr data-user-id="<?php echo $user['id']; ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="user-status <?php echo $user['status']; ?>"></span>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <span class="badge type-badge" data-type="<?php echo htmlspecialchars($user['user_type']); ?>">
                                        <?php echo htmlspecialchars(ucfirst($user['user_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['user_type'] === 'contractor' && $user['contractor_name']): ?>
                                        <?php echo htmlspecialchars($user['contractor_name']); ?>
                                        <div class="contractor-info"><?php echo htmlspecialchars($user['contractor_trade']); ?></div>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                        <?php echo ucfirst($user['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'Never'; ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="showEditUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars(addslashes($user['username'])); ?>', '<?php echo htmlspecialchars(addslashes($user['email'])); ?>', '<?php echo htmlspecialchars(addslashes($user['first_name'])); ?>', '<?php echo htmlspecialchars(addslashes($user['last_name'])); ?>', '<?php echo htmlspecialchars($user['user_type']); ?>')">
                                                    <i class="bx bx-edit me-2"></i>Edit
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="showChangeTypeModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['user_type']); ?>', <?php echo $user['contractor_id'] ? $user['contractor_id'] : 'null'; ?>)">
                                                    <i class="bx bx-transfer me-2"></i>Change Type
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item <?php echo $user['status'] === 'active' ? 'text-danger' : 'text-success'; ?>" 
                                                   href="#" 
                                                   onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                    <i class="bx bx-power-off me-2"></i>
                                                    <?php echo $user['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <!-- Change Type Modal -->
    <div class="modal fade" id="changeTypeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change User Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="changeTypeForm" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_type">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="user_id" id="typeUserId">
                        
                        <div class="mb-3">
                            <label class="form-label">New User Type</label>
                            <select class="form-select" name="new_type" id="newType" required>
                                <option value="">Select new type</option>
                                <?php foreach (USER_TYPES as $type => $label): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>">
                                        <?php echo htmlspecialchars($label); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3 contractor-fields" style="display: none;">
                            <label class="form-label">Select Contractor</label>
                            <select class="form-select" name="contractor_id" id="contractorId">
                                <option value="">Select a contractor</option>
                                <?php foreach ($contractors as $contractor): ?>
                                    <option value="<?php echo htmlspecialchars($contractor['id']); ?>">
                                        <?php echo htmlspecialchars($contractor['company_name']); ?> 
                                        (<?php echo htmlspecialchars($contractor['trade']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Select the contractor company to associate with this user</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="editUserForm" method="post">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="user_id" id="editUserId">
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" name="username" id="editUsername" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">First Name</label>
                            <input type="text" class="form-control" name="first_name" id="editFirstName">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Last Name</label>
                            <input type="text" class="form-control" name="last_name" id="editLastName">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide contractor fields based on user type selection
        document.getElementById('newType').addEventListener('change', function() {
            const contractorFields = document.querySelector('.contractor-fields');
            const contractorSelect = document.getElementById('contractorId');
            
            if (this.value === 'contractor') {
                contractorFields.style.display = 'block';
                contractorSelect.required = true;
            } else {
                contractorFields.style.display = 'none';
                contractorSelect.required = false;
                contractorSelect.value = '';
            }
        });

        // Function to show change type modal
        function showChangeTypeModal(userId, currentType, contractorId) {
            const modal = document.getElementById('changeTypeModal');
            const typeSelect = modal.querySelector('#newType');
            const contractorFields = modal.querySelector('.contractor-fields');
            const contractorSelect = document.getElementById('contractorId');
            const form = modal.querySelector('form');
            
            document.getElementById('typeUserId').value = userId;
            
            // Reset and update options
            Array.from(typeSelect.options).forEach(option => {
                option.disabled = option.value === currentType;
            });
            
            typeSelect.value = '';
            
            // Handle contractor fields
            if (currentType === 'contractor' && contractorId) {
                contractorFields.style.display = 'block';
                contractorSelect.value = contractorId;
            } else {
                contractorFields.style.display = 'none';
                contractorSelect.value = '';
            }

            new bootstrap.Modal(modal).show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                form.reset();
                contractorFields.style.display = 'none';
                Array.from(typeSelect.options).forEach(option => {
                    option.disabled = false;
                });
            }, { once: true });
        }

        // Function to show edit user modal
        function showEditUserModal(userId, username, email, firstName, lastName) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editFirstName').value = firstName;
            document.getElementById('editLastName').value = lastName;
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        // Function to toggle user status
        function toggleUserStatus(userId, currentStatus) {
            if (!confirm(`Are you sure you want to ${currentStatus === 'active' ? 'deactivate' : 'activate'} this user?`)) {
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="change_status">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="user_id" value="${userId}">
                <input type="hidden" name="new_status" value="${currentStatus === 'active' ? 'inactive' : 'active'}">
            `;
            document.body.append(form);
            form.submit();
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            document.querySelectorAll('tbody tr').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Form submission handling
        document.querySelectorAll('#editUserForm, #changeTypeForm').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = this.querySelector('button[type="submit"]');
                submitButton.disabled = true;
                
                // Form will submit normally, button is re-enabled on page reload
            });
        });

        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert-dismissible').forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });

        // Handle modal close reset
        ['editUserModal', 'changeTypeModal'].forEach(modalId => {
            const modal = document.getElementById(modalId);
            modal.addEventListener('hidden.bs.modal', function () {
                const form = this.querySelector('form');
                if (form) {
                    form.reset();
                }
                const contractorFields = this.querySelector('.contractor-fields');
                if (contractorFields) {
                    contractorFields.style.display = 'none';
                }
            });
        });

        // Add animation when status changes
        function animateStatusChange(element) {
            element.classList.add('changing');
            setTimeout(() => element.classList.remove('changing'), 1000);
        }
    </script>
</body>
</html>