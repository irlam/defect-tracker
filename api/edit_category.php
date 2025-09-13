<?php
// api/edit_category.php
// Current Date and Time (UTC): 2025-01-18 09:16:10
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");

require_once 'config/database.php';
require_once 'includes/session.php';
require_once 'includes/auth.php';

// Check authentication and admin privileges
if (!isAuthenticated() || !hasRole('admin')) {
    $_SESSION['error_message'] = "Unauthorized access";
    header("Location: index.php");
    exit();
}

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$category = null;
$categoryId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate CSRF token
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            throw new Exception('Invalid CSRF token');
        }

        // Validate required fields
        if (empty($_POST['name']) || empty($_POST['description'])) {
            throw new Exception('Name and description are required');
        }

        // Check if category name already exists (excluding current category)
        $stmt = $db->prepare("
            SELECT id 
            FROM categories 
            WHERE name = ? 
            AND id != ? 
            AND deleted_at IS NULL
        ");
        $stmt->execute([trim($_POST['name']), $categoryId]);
        if ($stmt->fetch()) {
            throw new Exception('Category name already exists');
        }

        // Update category
        $stmt = $db->prepare("
            UPDATE categories 
            SET name = ?,
                description = ?,
                updated_by = ?,
                updated_at = UTC_TIMESTAMP()
            WHERE id = ? 
            AND deleted_at IS NULL
        ");
        
        $stmt->execute([
            trim($_POST['name']),
            trim($_POST['description']),
            $_SESSION['user_id'],
            $categoryId
        ]);

        if ($stmt->rowCount() > 0) {
            $_SESSION['success_message'] = "Category updated successfully";
            header("Location: categories.php");
            exit();
        } else {
            throw new Exception('Category not found or no changes made');
        }

    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Fetch category details
try {
    $stmt = $db->prepare("
        SELECT 
            c.*,
            u_created.username as created_by_username,
            u_updated.username as updated_by_username
        FROM categories c
        LEFT JOIN users u_created ON c.created_by = u_created.id
        LEFT JOIN users u_updated ON c.updated_by = u_updated.id
        WHERE c.id = ? 
        AND c.deleted_at IS NULL
    ");
    
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$category) {
        $_SESSION['error_message'] = "Category not found";
        header("Location: categories.php");
        exit();
    }

} catch (Exception $e) {
    $_SESSION['error_message'] = "Error fetching category: " . $e->getMessage();
    header("Location: categories.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <style>
        .form-label.required::after {
            content: " *";
            color: red;
        }
        .metadata {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-8 col-lg-6 mx-auto">
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php 
                            echo htmlspecialchars($_SESSION['error_message']);
                            unset($_SESSION['error_message']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">Edit Category</h4>
                    </div>
                    <div class="card-body">
                        <form action="edit_category.php?id=<?php echo $categoryId; ?>" method="POST" id="editCategoryForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="mb-3">
                                <label for="name" class="form-label required">Name</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($category['name']); ?>" 
                                       required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label required">Description</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="3" 
                                          required><?php echo htmlspecialchars($category['description']); ?></textarea>
                            </div>

                            <div class="metadata mb-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="mb-1">
                                            <strong>Created by:</strong> 
                                            <?php echo htmlspecialchars($category['created_by_username']); ?>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Created at:</strong> 
                                            <?php echo date('Y-m-d H:i:s', strtotime($category['created_at'])); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <?php if ($category['updated_by']): ?>
                                            <p class="mb-1">
                                                <strong>Last updated by:</strong> 
                                                <?php echo htmlspecialchars($category['updated_by_username']); ?>
                                            </p>
                                            <p class="mb-1">
                                                <strong>Last updated at:</strong> 
                                                <?php echo date('Y-m-d H:i:s', strtotime($category['updated_at'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save"></i> Save Changes
                                </button>
                                <a href="categories.php" class="btn btn-secondary">
                                    <i class="bx bx-arrow-back"></i> Back to Categories
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
            const nameField = document.getElementById('name');
            const descriptionField = document.getElementById('description');

            if (!nameField.value.trim()) {
                e.preventDefault();
                alert('Category name is required');
                nameField.focus();
                return;
            }

            if (!descriptionField.value.trim()) {
                e.preventDefault();
                alert('Category description is required');
                descriptionField.focus();
                return;
            }
        });
    </script>
</body>
</html>