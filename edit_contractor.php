<?php
// edit_contractor.php
// Current Date and Time (UTC): 2025-01-16 19:44:58
// Current User: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();
require_once 'includes/db.php';
require_once 'includes/permissions.php';

// Check if user is logged in
if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Check if user has permission to edit contractors
if (!userHasPermission('edit_contractor')) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
}

// Function to deactivate contractor
// edit_contractor.php
// Current Date and Time (UTC): 2025-01-16 19:48:09
// Current User: irlam

function deactivateContractor($contractorId) {
    global $db;
    
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Get current user's ID
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !isset($user['id'])) {
            throw new Exception("User not found: " . $_SESSION['username']);
        }
        
        // Update contractor status
        $stmt = $db->prepare("
            UPDATE contractors 
            SET status = 'inactive',
                updated_by = :user_id
            WHERE id = :contractor_id
        ");
        
        $result = $stmt->execute([
            'user_id' => $user['id'],
            'contractor_id' => $contractorId
        ]);
        
        if (!$result) {
            throw new Exception("Failed to update contractor");
        }
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Deactivation error: " . $e->getMessage());
        throw $e;
    }
}
        
        // Log the parameters
        error_log("Update parameters: " . print_r($params, true));
        
        $result = $stmt->execute($params);
        
        if ($stmt->rowCount() === 0) {
            throw new Exception("Update failed - either contractor not found or invalid user ID");
        }
        
        // Log the action
        logSystem(
            "Contractor ID {$contractorId} deactivated by {$_SESSION['username']} (User ID: {$user['id']})",
            $_SESSION['username']
        );
        
        // Commit transaction
        $db->commit();
        
        return true;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollBack();
        error_log("Error in deactivateContractor: " . $e->getMessage());
        throw $e;
    }
}

// Update the trigger to be more specific about the error
try {
    $db->exec("
        DROP TRIGGER IF EXISTS before_contractor_update;
        
        CREATE TRIGGER before_contractor_update 
        BEFORE UPDATE ON contractors
        FOR EACH ROW
        BEGIN
            IF NEW.updated_by IS NOT NULL AND NOT EXISTS (
                SELECT 1 FROM users WHERE id = NEW.updated_by
            ) THEN
                SET @error_message = CONCAT('Invalid user ID for updated_by: ', IFNULL(NEW.updated_by, 'NULL'));
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = @error_message;
            END IF;
        END;
    ");
} catch (Exception $e) {
    error_log("Error creating trigger: " . $e->getMessage());
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'deactivate') {
            $contractorId = filter_input(INPUT_POST, 'contractor_id', FILTER_VALIDATE_INT);
            
            if (!$contractorId) {
                throw new Exception("Invalid contractor ID");
            }
            
            if (deactivateContractor($contractorId)) {
                echo json_encode(['success' => true]);
                exit;
            }
        }
        
        // Handle other edit actions here
        // ...
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => "Error deactivating contractor: " . $e->getMessage()
        ]);
        exit;
    }
}

// Handle GET requests for loading the edit form
try {
    $contractorId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    
    if (!$contractorId) {
        throw new Exception("Invalid contractor ID");
    }
    
    $stmt = $db->prepare("
        SELECT c.*, u.username as updated_by_username 
        FROM contractors c 
        LEFT JOIN users u ON c.updated_by = u.id 
        WHERE c.id = ?
    ");
    
    $stmt->execute([$contractorId]);
    $contractor = $stmt->fetch();
    
    if (!$contractor) {
        throw new Exception("Contractor not found");
    }
    
} catch (Exception $e) {
    error_log("Error loading contractor: " . $e->getMessage());
    http_response_code(404);
    exit("Contractor not found");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Contractor - Construction Defect Tracker</title>
    <link href="css/styles.css" rel="stylesheet">
    <!-- Add your CSS and JavaScript includes here -->
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <h1>Edit Contractor</h1>
        
        <form id="editContractorForm" method="POST">
            <input type="hidden" name="contractor_id" value="<?php echo htmlspecialchars($contractor['id']); ?>">
            
            <!-- Add your form fields here -->
            
            <div class="form-group">
                <label for="status">Status</label>
                <select class="form-control" id="status" name="status" <?php echo $contractor['status'] === 'inactive' ? 'disabled' : ''; ?>>
                    <option value="active" <?php echo $contractor['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $contractor['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Save Changes</button>
                <?php if ($contractor['status'] === 'active'): ?>
                    <button type="button" class="btn btn-danger" onclick="deactivateContractor(<?php echo $contractor['id']; ?>)">Deactivate Contractor</button>
                <?php endif; ?>
                <a href="contractors.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
    function deactivateContractor(contractorId) {
        if (confirm('Are you sure you want to deactivate this contractor?')) {
            fetch('edit_contractor.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=deactivate&contractor_id=${contractorId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Error deactivating contractor');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error deactivating contractor');
            });
        }
    }
    </script>
</body>
</html>