<?php
// categories.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-18 12:31:20
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

// Required includes
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Initialize database connection
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

$pageTitle = 'Categories - Defect Tracker';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    
    <!-- CSS includes -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Include Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Main Content Area -->
    <div class="main-content">
        <div class="table-container">
            <!-- Table Header with Title and Add Button -->
            <div class="table-header d-flex justify-content-between align-items-center">
                <h1 class="h2 mb-0">Categories</h1>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                    <i class="bx bx-plus"></i> Add Category
                </button>
            </div>

            <!-- Categories Table -->
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Created By</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $db->query("
                                SELECT 
                                    c.*, 
                                    u.username as creator_name 
                                FROM categories c 
                                LEFT JOIN users u ON c.created_by = u.id 
                                WHERE c.deleted_at IS NULL 
                                ORDER BY c.created_at DESC
                            ");
                            
                            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['creator_name']) . "</td>";
                                echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                                echo "<td class='text-nowrap'>";
                                echo "<button type='button' class='btn btn-sm btn-primary edit-category me-1' data-id='" . $row['id'] . "' data-name='" . htmlspecialchars($row['name'], ENT_QUOTES) . "' data-description='" . htmlspecialchars($row['description'], ENT_QUOTES) . "'>";
                                echo "<i class='bx bx-edit'></i>";
                                echo "</button>";
                                echo "<button type='button' class='btn btn-sm btn-danger delete-category' data-id='" . $row['id'] . "'>";
                                echo "<i class='bx bx-trash'></i>";
                                echo "</button>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (Exception $e) {
                            echo "<tr><td colspan='6' class='text-center text-danger'>Error loading categories</td></tr>";
                            error_log("Error loading categories: " . $e->getMessage());
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>


<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="categoryName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm">
                <div class="modal-body">
                    <input type="hidden" id="editCategoryId" name="id">
                    <div class="mb-3">
                        <label for="editCategoryName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="editCategoryName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editCategoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="editCategoryDescription" name="description" rows="3"></textarea>
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

<!-- JavaScript includes -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/main.js"></script>
<script>
$(document).ready(function() {
    // Edit Category Button Click Handler
    $('.edit-category').on('click', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const name = $(this).data('name');
        const description = $(this).data('description');
        
        $('#editCategoryId').val(id);
        $('#editCategoryName').val(name);
        $('#editCategoryDescription').val(description);
        
        $('#editCategoryModal').modal('show');
    });

    // Edit Category Form Submit Handler
    $('#editCategoryForm').on('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: 'api/edit_category.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    const row = $(`button.edit-category[data-id="${response.data.id}"]`).closest('tr');
                    row.find('td:eq(1)').text(response.data.name);
                    row.find('td:eq(2)').text(response.data.description);
                    
                    $('#editCategoryModal').modal('hide');
                    alert('Category updated successfully');
                    location.reload(); // Refresh to show updated data
                } else {
                    alert('Error: ' + (response.message || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error updating category: ' + error);
                console.error('Error details:', xhr.responseText);
            },
            complete: function() {
                submitBtn.prop('disabled', false);
            }
        });
    });

    // Add Category Form Submit Handler
    $('#addCategoryForm').on('submit', function(e) {
        e.preventDefault();
        const submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true);
        
        $.ajax({
            url: 'api/add_category.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if(response.success) {
                    $('#addCategoryModal').modal('hide');
                    alert('Category added successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (response.message || 'Unknown error occurred'));
                }
            },
            error: function(xhr, status, error) {
                alert('Error adding category: ' + error);
                console.error('Error details:', xhr.responseText);
            },
            complete: function() {
                submitBtn.prop('disabled', false);
            }
        });
    });

    // Delete Category Handler
    $('.delete-category').on('click', function(e) {
        e.preventDefault();
        if(confirm('Are you sure you want to delete this category?')) {
            const id = $(this).data('id');
            const deleteBtn = $(this);
            deleteBtn.prop('disabled', true);
            
            $.ajax({
                url: 'api/delete_category.php',
                type: 'POST',
                data: { id: id },
                dataType: 'json',
                success: function(response) {
                    if(response.success) {
                        deleteBtn.closest('tr').fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Error: ' + (response.message || 'Unknown error occurred'));
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error deleting category: ' + error);
                    console.error('Error details:', xhr.responseText);
                },
                complete: function() {
                    deleteBtn.prop('disabled', false);
                }
            });
        }
    });

    // Clear forms when modals are closed
    $('#addCategoryModal, #editCategoryModal').on('hidden.bs.modal', function() {
        $(this).find('form')[0].reset();
    });
});
// Sidebar Toggle
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mobileToggle = document.getElementById('mobileSidebarToggle');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    function toggleSidebar() {
        sidebar.classList.toggle('show');
    }
    
    mobileToggle.addEventListener('click', toggleSidebar);
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    // Close sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 992) {
            if (!sidebar.contains(e.target) && 
                !mobileToggle.contains(e.target) && 
                sidebar.classList.contains('show')) {
                toggleSidebar();
            }
        }
    });
});
</script>

</body>
</html>