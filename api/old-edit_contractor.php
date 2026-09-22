<?php
// api/edit_contractor.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();
require_once '../includes/db.php';
require_once '../includes/permissions.php';

if (!isset($_SESSION['username'])) {
    header('Location: login.php');
    exit();
}

// Debugging: Print session data
echo '<pre>';
print_r($_SESSION);
echo '</pre>';

// Check if the user has permission to edit contractors
echo 'Checking permission for edit_contractor...<br/>';
if (!userHasPermission('edit_contractor')) {
    echo 'User does not have permission to edit contractors.<br/>';
    header('HTTP/1.1 403 Forbidden');
    exit('Access Denied');
} else {
    echo 'User has permission to edit contractors.<br/>';
}

// Function to deactivate contractor
function deactivateContractor($contractorId) {
    global $db;
    try {
        $db->beginTransaction();
        
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$_SESSION['username']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !isset($user['id'])) {
            throw new Exception("User not found: " . $_SESSION['username']);
        }
        
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
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => "Error deactivating contractor: " . $e->getMessage()]);
        exit;
    }
}

try {
    $contractorId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$contractorId) {
        throw new Exception("Invalid contractor ID");
    }

    // Debugging: Print contractor ID
    echo "Contractor ID: $contractorId<br/>";

    $stmt = $db->prepare("
        SELECT c.*, u.username as updated_by_username 
        FROM contractors c 
        LEFT JOIN users u ON c.updated_by = u.id 
        WHERE c.id = ?
    ");
    $stmt->execute([$contractorId]);
    $contractor = $stmt->fetch();

    // Debugging: Print contractor data
    echo '<pre>';
    print_r($contractor);
    echo '</pre>';

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
    <link href="../css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/navbar.php'; ?>
    <div class="container mt-4">
        <h1>Edit Contractor</h1>
        <form id="editContractorForm" method="POST">
            <input type="hidden" name="contractor_id" value="<?php echo htmlspecialchars($contractor['id']); ?>">
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
                <a href="../contractors.php" class="btn btn-secondary">Cancel</a>
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