<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/navbar.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['admin', 'manager'])) {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $_SESSION['username'] ?? 'User'));
$currentTimestamp = date('d/m/Y H:i');

try {
    $navbar = new Navbar($db, (int) $_SESSION['user_id'], $_SESSION['username']);
} catch (Throwable $navbarException) {
    error_log('Navbar initialisation failed on role_management.php: ' . $navbarException->getMessage());
    $navbar = null;
}

$roles = [];
$permissions = [];
$rolePermissions = [];

try {
    $rolesStmt = $db->query('SELECT id, name, description, created_at FROM roles ORDER BY name ASC');
    $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $permissionsStmt = $db->query('SELECT id, name, description FROM permissions ORDER BY name ASC');
    $permissions = $permissionsStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? '';

        switch ($action) {
            case 'create_role':
                $roleName = trim((string) filter_input(INPUT_POST, 'role_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
                $roleDescription = trim((string) filter_input(INPUT_POST, 'role_description', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

                if ($roleName === '') {
                    $error_message = 'Role name is required.';
                } else {
                    $createStmt = $db->prepare('INSERT INTO roles (name, description) VALUES (:name, :description)');
                    $createStmt->bindValue(':name', $roleName);
                    $createStmt->bindValue(':description', $roleDescription);
                    $createStmt->execute();
                    $success_message = 'Role created successfully.';
                }
                break;

            case 'update_role_permissions':
                $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
                if (!$roleId) {
                    $error_message = 'Invalid role selected.';
                    break;
                }

                $db->beginTransaction();
                try {
                    $deleteStmt = $db->prepare('DELETE FROM role_permissions WHERE role_id = :role_id');
                    $deleteStmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                    $deleteStmt->execute();

                    $permissionsInput = filter_input(INPUT_POST, 'permissions', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
                    if (!empty($permissionsInput)) {
                        $insertStmt = $db->prepare('INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)');
                        foreach ($permissionsInput as $permissionId) {
                            $permissionId = filter_var($permissionId, FILTER_VALIDATE_INT);
                            if ($permissionId) {
                                $insertStmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                                $insertStmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
                                $insertStmt->execute();
                            }
                        }
                    }
                    $db->commit();
                    $success_message = 'Role permissions updated successfully.';
                } catch (Exception $roleUpdateException) {
                    $db->rollBack();
                    $error_message = 'Failed to update role permissions: ' . $roleUpdateException->getMessage();
                }
                break;

            case 'delete_role':
                $roleId = filter_input(INPUT_POST, 'role_id', FILTER_VALIDATE_INT);
                if (!$roleId) {
                    $error_message = 'Invalid role selected.';
                    break;
                }

                $usageCheck = $db->prepare('SELECT COUNT(*) FROM users WHERE role_id = :role_id');
                $usageCheck->bindValue(':role_id', $roleId, PDO::PARAM_INT);
                $usageCheck->execute();

                if ((int) $usageCheck->fetchColumn() > 0) {
                    $error_message = 'Cannot delete this role because it is assigned to active users.';
                } else {
                    $deleteRoleStmt = $db->prepare('DELETE FROM roles WHERE id = :id');
                    $deleteRoleStmt->bindValue(':id', $roleId, PDO::PARAM_INT);
                    $deleteRoleStmt->execute();
                    $success_message = 'Role deleted successfully.';
                }
                break;
        }

        if ($success_message !== '' && empty($error_message)) {
            header('Location: role_management.php?success=1');
            exit;
        }
    }

    $rolePermissionsStmt = $db->prepare('SELECT role_id, permission_id FROM role_permissions');
    $rolePermissionsStmt->execute();
    foreach ($rolePermissionsStmt->fetchAll(PDO::FETCH_ASSOC) as $mapping) {
        $rolePermissions[(int) $mapping['role_id']][] = (int) $mapping['permission_id'];
    }

    if (isset($_GET['success'])) {
        $success_message = 'Changes saved successfully.';
    }
} catch (Exception $e) {
    $error_message = 'An error occurred: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="/css/app.css" rel="stylesheet">
    <style>
        .role-page {
            color: rgba(226, 232, 240, 0.92);
        }

        .role-hero {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(37, 99, 235, 0.85));
            border-radius: 1.4rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 2.25rem;
            box-shadow: 0 28px 48px -24px rgba(15, 23, 42, 0.7);
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 1.75rem;
        }

        .role-hero__meta {
            display: flex;
            flex-direction: column;
            gap: 0.55rem;
            color: rgba(191, 219, 254, 0.85);
            font-size: 0.9rem;
        }

        .role-card {
            background: rgba(15, 23, 42, 0.88);
            border-radius: 1.3rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 24px 40px -24px rgba(15, 23, 42, 0.75);
            padding: 1.75rem;
        }

        .role-permissions {
            background: rgba(15, 23, 42, 0.82);
            border-radius: 1.1rem;
            border: 1px solid rgba(148, 163, 184, 0.14);
            padding: 1.5rem;
        }

        .role-permissions .form-check {
            padding: 0.65rem 0.75rem;
            background: rgba(30, 41, 59, 0.4);
            border-radius: 0.75rem;
            border: 1px solid rgba(148, 163, 184, 0.12);
        }

        .badge-soft {
            background: rgba(59, 130, 246, 0.18);
            color: rgba(191, 219, 254, 0.95);
            border-radius: 999px;
            padding: 0.3rem 0.85rem;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .role-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        @media (max-width: 768px) {
            .role-hero {
                padding: 1.75rem;
            }

            .role-card {
                padding: 1.35rem;
            }
        }
    </style>
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php if ($navbar instanceof Navbar) { $navbar->render(); } ?>

    <main class="role-page container-xl py-4">
        <section class="role-hero mb-4">
            <div>
                <span class="badge-soft mb-2"><i class='bx bx-shield-quarter me-1'></i>Role governance</span>
                <h1 class="h3 mb-2">Govern access across the defect platform</h1>
                <p class="text-muted mb-0">Create roles, fine-tune permissions, and keep privilege lines tight for every project stakeholder.</p>
            </div>
            <div class="role-hero__meta">
                <span><i class='bx bx-user-circle me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                <span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
                <span><i class='bx bx-layer me-1'></i><?php echo number_format(count($roles)); ?> roles active</span>
            </div>
        </section>

        <?php if ($success_message !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bx bx-check-circle me-1"></i><?php echo htmlspecialchars($success_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bx bx-error-circle me-1"></i><?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <section class="row g-4 mb-4">
            <div class="col-12 col-lg-5">
                <article class="role-card h-100">
                    <h2 class="h5 mb-3"><i class='bx bx-plus-circle me-2'></i>Create new role</h2>
                    <p class="text-muted small mb-4">Model new responsibilities with precise permissions mapped to operational playbooks.</p>
                    <form method="POST" class="d-grid gap-3">
                        <input type="hidden" name="action" value="create_role">
                        <div>
                            <label class="form-label text-uppercase text-muted small">Role name</label>
                            <input type="text" name="role_name" class="form-control form-control-lg bg-dark text-light border-secondary" required>
                        </div>
                        <div>
                            <label class="form-label text-uppercase text-muted small">Description</label>
                            <textarea name="role_description" rows="3" class="form-control bg-dark text-light border-secondary" placeholder="Outline responsibilities and scope"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class='bx bx-save me-1'></i>Create role</button>
                    </form>
                </article>
            </div>
            <div class="col-12 col-lg-7">
                <article class="role-card h-100">
                    <h2 class="h5 mb-3"><i class='bx bx-lock-alt me-2'></i>Permission catalogue</h2>
                    <p class="text-muted small mb-4">Reference the capabilities available for each role. Assignments are performed below per role.</p>
                    <div class="role-permissions">
                        <div class="row g-3">
                            <?php foreach ($permissions as $permission): ?>
                                <div class="col-12 col-md-6">
                                    <div class="form-check">
                                        <label class="form-check-label w-100">
                                            <span class="fw-semibold d-block"><?php echo htmlspecialchars($permission['name']); ?></span>
                                            <span class="text-muted small"><?php echo htmlspecialchars($permission['description'] ?? ''); ?></span>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (empty($permissions)): ?>
                                <div class="text-muted small">No permissions defined yet. Add entries in the permissions table.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        </section>

        <section class="role-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
                <h2 class="h5 mb-0"><i class='bx bx-layer-plus me-2'></i>Active roles</h2>
                <span class="badge-soft"><?php echo number_format(count($roles)); ?> configured</span>
            </div>

            <?php if (empty($roles)): ?>
                <div class="text-muted">No roles configured yet. Create a role to begin assigning permissions.</div>
            <?php else: ?>
                <div class="d-grid gap-4">
                    <?php foreach ($roles as $role): ?>
                        <article class="p-4 rounded-4 border border-secondary-subtle bg-dark bg-opacity-25">
                            <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                                <div>
                                    <h3 class="h6 mb-1 text-uppercase letter-spacing-1"><?php echo htmlspecialchars($role['name']); ?></h3>
                                    <p class="text-muted small mb-0"><?php echo htmlspecialchars($role['description'] ?? 'No description provided.'); ?></p>
                                </div>
                                <form method="POST" class="role-actions" onsubmit="return confirm('Are you sure you want to delete this role?');">
                                    <input type="hidden" name="role_id" value="<?php echo (int) $role['id']; ?>">
                                    <button type="submit" name="action" value="delete_role" class="btn btn-outline-danger btn-sm">
                                        <i class='bx bx-trash me-1'></i>Delete
                                    </button>
                                </form>
                            </div>
                            <form method="POST" class="d-grid gap-3">
                                <input type="hidden" name="action" value="update_role_permissions">
                                <input type="hidden" name="role_id" value="<?php echo (int) $role['id']; ?>">
                                <div class="row g-3">
                                    <?php foreach ($permissions as $permission): ?>
                                        <div class="col-md-4 col-lg-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox"
                                                       name="permissions[]"
                                                       value="<?php echo (int) $permission['id']; ?>"
                                                       id="role_<?php echo (int) $role['id']; ?>_perm_<?php echo (int) $permission['id']; ?>"
                                                       <?php echo in_array((int) $permission['id'], $rolePermissions[$role['id']] ?? [], true) ? 'checked' : ''; ?>>
                                                <label class="form-check-label small" for="role_<?php echo (int) $role['id']; ?>_perm_<?php echo (int) $permission['id']; ?>">
                                                    <?php echo htmlspecialchars($permission['name']); ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (empty($permissions)): ?>
                                        <div class="text-muted small">No permissions available for assignment.</div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between flex-wrap gap-2">
                                    <span class="text-muted small">Updated via admin console</span>
                                    <button type="submit" class="btn btn-outline-light btn-sm"><i class='bx bx-save me-1'></i>Save permissions</button>
                                </div>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

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