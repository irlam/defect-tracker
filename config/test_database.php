<?php
// config/test_database.php
// Test database configuration using SQLite for local testing

class TestDatabase {
    private $db_path;
    private $conn = null;

    public function __construct() {
        // Create SQLite database in a writable location
        $this->db_path = __DIR__ . '/../test_database.sqlite';
    }

    // Get database connection
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "sqlite:" . $this->db_path
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create tables if they don't exist
            $this->createTables();
            
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }
        return $this->conn;
    }
    
    private function createTables() {
        // Create basic tables for testing
        $tables = [
            "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                email VARCHAR(100) UNIQUE NOT NULL,
                role TEXT CHECK (role IN ('admin', 'manager', 'contractor', 'user')) DEFAULT 'user',
                first_name VARCHAR(50),
                last_name VARCHAR(50),
                full_name VARCHAR(100), -- Added full_name field that Auth class expects
                phone VARCHAR(20),
                active INTEGER DEFAULT 1,
                is_active INTEGER DEFAULT 1, -- Added is_active field that Auth class expects
                created_by VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_by VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS projects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                description TEXT,
                status TEXT CHECK (status IN ('active', 'completed', 'on_hold')) DEFAULT 'active',
                start_date DATE,
                end_date DATE,
                project_manager_id INT,
                created_by VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_by VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_manager_id) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS floor_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_size INT,
                mime_type VARCHAR(100),
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_by VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS defects (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                project_id INT NOT NULL,
                floor_plan_id INT,
                title VARCHAR(255) NOT NULL,
                description TEXT,
                category VARCHAR(100),
                severity TEXT CHECK (severity IN ('low', 'medium', 'high', 'critical')) DEFAULT 'medium',
                status TEXT CHECK (status IN ('new', 'assigned', 'in_progress', 'completed', 'rejected', 'reopened')) DEFAULT 'new',
                assigned_to INT,
                created_by_user INT NOT NULL,
                x_coordinate DECIMAL(10,2),
                y_coordinate DECIMAL(10,2),
                due_date DATE,
                completed_date DATETIME,
                created_by VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_by VARCHAR(255),
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id),
                FOREIGN KEY (floor_plan_id) REFERENCES floor_plans(id),
                FOREIGN KEY (assigned_to) REFERENCES users(id),
                FOREIGN KEY (created_by_user) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS defect_images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                defect_id INT NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_size INT,
                mime_type VARCHAR(100),
                upload_type TEXT CHECK (upload_type IN ('before', 'after', 'general')) DEFAULT 'general',
                created_by VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (defect_id) REFERENCES defects(id) ON DELETE CASCADE
            )",
            
            "CREATE TABLE IF NOT EXISTS comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                defect_id INT NOT NULL,
                user_id INT NOT NULL,
                comment TEXT NOT NULL,
                created_by VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (defect_id) REFERENCES defects(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )",
            
            "CREATE TABLE IF NOT EXISTS roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS user_roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INT NOT NULL,
                role_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                UNIQUE(user_id, role_id)
            )",
            
            "CREATE TABLE IF NOT EXISTS permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(50) NOT NULL UNIQUE,
                description TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )",
            
            "CREATE TABLE IF NOT EXISTS role_permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INT NOT NULL,
                permission_id INT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
                UNIQUE(role_id, permission_id)
            )"
        ];
        
        foreach ($tables as $sql) {
            $this->conn->exec($sql);
        }
        
        // Insert test data
        $this->insertTestData();
    }
    
    private function insertTestData() {
        // Insert test user
        try {
            $stmt = $this->conn->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                $this->conn->exec("INSERT INTO users (username, password_hash, email, role, first_name, last_name, full_name, active, is_active, created_by) 
                                 VALUES ('admin', '" . password_hash('admin123', PASSWORD_DEFAULT) . "', 'admin@test.com', 'admin', 'Admin', 'User', 'Admin User', 1, 1, 'system')");
                
                $this->conn->exec("INSERT INTO users (username, password_hash, email, role, first_name, last_name, full_name, active, is_active, created_by) 
                                 VALUES ('testuser', '" . password_hash('test123', PASSWORD_DEFAULT) . "', 'test@test.com', 'user', 'Test', 'User', 'Test User', 1, 1, 'system')");
                                 
                // Insert test project
                $this->conn->exec("INSERT INTO projects (name, description, status, project_manager_id, created_by) 
                                 VALUES ('Test Project', 'A test project for validation', 'active', 1, 'system')");
                                 
                // Insert basic roles
                $this->conn->exec("INSERT INTO roles (name, description) VALUES 
                                 ('admin', 'Administrator with full access'),
                                 ('manager', 'Project manager'),
                                 ('contractor', 'Contractor user'),
                                 ('user', 'Basic user')");
                                 
                // Assign admin role to admin user
                $this->conn->exec("INSERT INTO user_roles (user_id, role_id) VALUES (1, 1)");
            }
        } catch (Exception $e) {
            // Ignore if already exists
        }
    }
}

// Override the original Database class for testing
if (!class_exists('Database')) {
    class Database extends TestDatabase {}
}
?>