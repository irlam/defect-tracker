<?php
// classes/Auth.php
// Authentication class for user management and security

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

class Auth {
    private $conn;
    private $table_name = "users";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Login user
    public function login($username, $password) {
        try {
            $query = "SELECT id, username, password, role, email, full_name 
                     FROM " . $this->table_name . " 
                     WHERE username = :username AND is_active = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":username", $username);
            $stmt->execute();

            if($stmt->rowCount() == 1) {
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if(password_verify($password, $row['password'])) {
                    // Start session and store user data
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    $_SESSION['user_id'] = $row['id'];
                    $_SESSION['username'] = $row['username'];
                    $_SESSION['role'] = $row['role'];
                    $_SESSION['email'] = $row['email'];
                    $_SESSION['full_name'] = $row['full_name'];
                    $_SESSION['last_activity'] = time();
                    
                    return true;
                }
            }
            return false;
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    // Check if user is logged in
    public function isLoggedIn() {
        if(session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if(isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
            // Check for session timeout (30 minutes)
            if(time() - $_SESSION['last_activity'] > 1800) {
                $this->logout();
                return false;
            }
            $_SESSION['last_activity'] = time();
            return true;
        }
        return false;
    }

    // Check if user has specific role
    public function hasRole($role) {
        if($this->isLoggedIn()) {
            return $_SESSION['role'] === $role;
        }
        return false;
    }

    // Logout user
    public function logout() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_unset();
        session_destroy();
        return true;
    }

    // Get current user data
    public function getCurrentUser() {
        if($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'role' => $_SESSION['role'],
                'email' => $_SESSION['email'],
                'full_name' => $_SESSION['full_name']
            ];
        }
        return null;
    }
}
?>