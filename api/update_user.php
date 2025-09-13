<?php
// api/update_user.php
session_start();
require_once '../config/database.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $db->beginTransaction();

    // Update user
    $stmt = $db->prepare("
        UPDATE users SET 
            email = :email,
            user_type = :user_type,
            status = :status,
            updated_by = :updated_by,
            updated_at = :updated_at
        WHERE id = :id
    ");

    $stmt->execute([
        ':id' => $_POST['id'],
        ':email' => $_POST['email'],
        ':user_type' => $_POST['user_type'],
        ':status' => $_POST['status'],
        ':updated_by' => 'irlam',
        ':updated_at' => '2025-01-14 21:11:42'
    ]);

    // Update or insert contractor info
    if ($_POST['user_type'] === 'contractor') {
        $stmt = $db->prepare("
            INSERT INTO contractors (
                user_id, 
                company_name, 
                updated_at
            ) VALUES (
                :user_id,
                :company_name,
                :updated_at
            ) ON DUPLICATE KEY UPDATE 
                company_name = VALUES(company_name),
                updated_at = VALUES(updated_at)
        ");

        $stmt->execute([
            ':user_id' => $_POST['id'],
            ':company_name' => $_POST['company_name'],
            ':updated_at' => '2025-01-14 21:11:42'
        ]);
    }

    $db->commit();
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);

} catch (Exception $e) {
    $db->rollBack();
    error_log("Error updating user: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error updating user']);
}

// Add the JavaScript for edit, delete, and reset password functions
?>

<script>
// Edit User Function
function editUser(user) {
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    
    document.getElementById('edit_id').value = user.id;
    document.getElementById('edit_username').value = user.username;
    document.getElementById('edit_email').value = user.email;
    document.getElementById('edit_user_type').value = user.user_type;
    document.getElementById('edit_status').value = user.status;
    
    if (user.user_type === 'contractor') {
        document.getElementById('edit_company_name').value = user.company_name;
        document.querySelector('.edit-contractor-fields').style.display = 'block';
    } else {
        document.querySelector('.edit-contractor-fields').style.display = 'none';
    }
    
    modal.show();
}

// Delete User Function
async function deleteUser(id) {
    if (!confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
        return;
    }

    try {
        const response = await fetch('api/delete_user.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                deleted_by: 'irlam',
                deleted_at: '2025-01-14 21:11:42'
            })
        });

        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Error deleting user: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error deleting user');
    }
}

// Reset Password Function
async function resetPassword(id) {
    if (!confirm('Are you sure you want to reset this user\'s password?')) {
        return;
    }

    try {
        const response = await fetch('api/reset_password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                id: id,
                reset_by: 'irlam',
                reset_at: '2025-01-14 21:11:42'
            })
        });

        const result = await response.json();
        
        if (result.success) {
            alert('Password reset email has been sent to the user.');
        } else {
            alert('Error resetting password: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error resetting password');
    }
}

// Add Event Listeners for Edit User Form
document.getElementById('editUserForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('updated_by', 'irlam');
    formData.append('updated_at', '2025-01-14 21:11:42');

    try {
        const response = await fetch('api/update_user.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            location.reload();
        } else {
            alert('Error updating user: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error updating user');
    }
});
</script>