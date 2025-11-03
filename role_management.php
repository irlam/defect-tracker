<?php
// role_management.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-17 14:26:38
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication and admin check
if (!isset($_SESSION['username']) || !isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

require_once 'includes/functions.php';
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Get all roles
    $rolesQuery = "SELECT * FROM roles ORDER BY name";
    $rolesStmt = $db->query($rolesQuery);
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get all permissions
    $permissionsQuery = "SELECT * FROM permissions ORDER BY name";
    $permissionsStmt = $db->query($permissionsQuery);
    $permissions = $permissionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_role':
                    $stmt = $db->prepare("INSERT INTO roles (name, description) VALUES (:name, :description)");
                    $stmt->execute([
                        ':name' => $_POST['role_name'],
                        ':description' => $_POST['role_description']
                    ]);
                    $success_message = "Role created successfully";
                    break;

                case 'update_role_permissions':
                    $roleId = (int)$_POST['role_id'];
                    
                    // First, remove all existing permissions for this role
                    $stmt = $db->prepare("DELETE FROM role_permissions WHERE role_id = :role_id");
                    $stmt->execute([':role_id' => $roleId]);
                    
                    // Then add the new permissions
                    if (isset($_POST['permissions']) && is_array($_POST['permissions'])) {
                        $insertStmt = $db->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)");
                        foreach ($_POST['permissions'] as $permissionId) {
                            $insertStmt->execute([
                                ':role_id' => $roleId,
                                ':permission_id' => (int)$permissionId
                            ]);
                        }
                    }
                    $success_message = "Role permissions updated successfully";
                    break;

                case 'delete_role':
                    $roleId = (int)$_POST['role_id'];
                    // Check if role is being used
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE role_id = :role_id");
                    $checkStmt->execute([':role_id' => $roleId]);
                    if ($checkStmt->fetchColumn() > 0) {
                        $error_message = "Cannot delete role: It is assigned to users";
                    } else {
                        $stmt = $db->prepare("DELETE FROM roles WHERE id = :id");
                        $stmt->execute([':id' => $roleId]);
                        $success_message = "Role deleted successfully";
                    }
                    break;
            }
        }
    }

    // Get role permissions for display
    $rolePermissions = [];
    foreach ($roles as $role) {
        $stmt = $db->prepare("SELECT permission_id FROM role_permissions WHERE role_id = :role_id");
        $stmt->execute([':role_id' => $role['id']]);
        $rolePermissions[$role['id']] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

} catch (Exception $e) {
    $error_message = "An error occurred: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <!-- [Previous CSS styles remain the same] -->
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php require_once 'includes/sidebar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <h1 class="h3 mb-4">Role Management</h1>

            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <!-- Create New Role -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Create New Role</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_role">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" name="role_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="role_description" class="form-control" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Create Role</button>
                    </form>
                </div>
            </div>

            <!-- Existing Roles -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Existing Roles</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($roles as $role): ?>
                        <div class="border-bottom pb-4 mb-4">
                            <h6><?php echo htmlspecialchars($role['name']); ?></h6>
                            <p class="text-muted small"><?php echo htmlspecialchars($role['description']); ?></p>
                            
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="update_role_permissions">
                                <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                
                                <div class="row g-3 mb-3">
                                    <?php foreach ($permissions as $permission): ?>
                                        <div class="col-md-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permissions[]" 
                                                       value="<?php echo $permission['id']; ?>"
                                                       <?php echo in_array($permission['id'], $rolePermissions[$role['id']]) ? 'checked' : ''; ?>>
                                                <label class="form-check-label">
                                                    <?php echo htmlspecialchars($permission['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="btn-group">
                                    <button type="submit" class="btn btn-primary btn-sm">Update Permissions</button>
                                    <button type="submit" class="btn btn-danger btn-sm" 
                                            formaction="?action=delete_role"
                                            onclick="return confirm('Are you sure you want to delete this role?');">
                                        Delete Role
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>