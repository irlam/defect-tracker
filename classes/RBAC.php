<?php
// classes/RBAC.php
class RBAC {
    private $db;
    private $currentUser;
    private $currentDateTime;

    public function __construct($db, $currentUser = 'irlam', $currentDateTime = '2025-01-14 21:22:10') {
        $this->db = $db;
        $this->currentUser = $currentUser;
        $this->currentDateTime = $currentDateTime;
    }

    public function getRoles() {
        $stmt = $this->db->query("
            SELECT id, name, description, created_at, updated_at 
            FROM roles 
            ORDER BY name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPermissions() {
        $stmt = $this->db->query("
            SELECT id, name, description, category, created_at 
            FROM permissions 
            ORDER BY category, name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function assignUserRole($userId, $roleId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO user_roles (user_id, role_id, created_by, created_at)
                VALUES (:user_id, :role_id, :created_by, :created_at)
            ");
            return $stmt->execute([
                ':user_id' => $userId,
                ':role_id' => $roleId,
                ':created_by' => $this->currentUser,
                ':created_at' => $this->currentDateTime
            ]);
        } catch (Exception $e) {
            error_log("Error assigning role: " . $e->getMessage());
            return false;
        }
    }

    public function hasPermission($userId, $permissionName) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM user_roles ur
            JOIN role_permissions rp ON ur.role_id = rp.role_id
            JOIN permissions p ON rp.permission_id = p.id
            WHERE ur.user_id = ? AND p.name = ?
        ");
        $stmt->execute([$userId, $permissionName]);
        return $stmt->fetchColumn() > 0;
    }
}