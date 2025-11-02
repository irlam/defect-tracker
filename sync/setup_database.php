<?php
/**
 * Sync Database Setup Script for k87747_defecttracker
 * Created: 2025-02-26 10:19:29
 * Updated by: irlam
 */

// Include database configuration
$config = [
    'db_host' => '10.35.233.124:3306',
    'db_name' => 'k87747_defecttracker',
    'db_user' => 'k87747_defecttracker',
    'db_pass' => 'Subaru5554346'
];

// Function to execute SQL from a file with improved error handling
function executeSQLFile($pdo, $file) {
    echo "Executing SQL from file: $file\n";
    $sql = file_get_contents($file);
    
    // Split into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)), 'strlen');
    $success = true;
    $errors = [];
    
    try {
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
                echo ".";
            } catch (PDOException $e) {
                // Specific errors we can safely ignore
                if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
                    strpos($e->getMessage(), 'Duplicate key name') !== false ||
                    strpos($e->getMessage(), 'column already exists') !== false) {
                    echo "i"; // i for ignored
                    continue;
                } else {
                    echo "x"; // x for error
                    $success = false;
                    $errors[] = $e->getMessage();
                }
            }
        }
        
        if ($success) {
            echo " Done!\n";
            return true;
        } else {
            echo " Completed with errors!\n";
            foreach ($errors as $error) {
                echo "- Error: $error\n";
            }
            return !empty($errors); // Return true if no errors
        }
    } catch (PDOException $e) {
        echo "\nError executing SQL: " . $e->getMessage() . "\n";
        return false;
    }
}

// Function to check if a column exists
function columnExists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$config['db_name'], $table, $column]);
    $result = $stmt->fetchColumn();
    return ($result !== false);
}

// Connect to database
try {
    echo "Connecting to database...\n";
    
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true, // Added buffered query option
    ];
    
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    echo "Connected successfully.\n";
    
    // Execute SQL files
    echo "\nCreating sync tables...\n";
    if (!executeSQLFile($pdo, __DIR__ . '/database/create_sync_tables.sql')) {
        throw new Exception("Failed to create sync tables.");
    }
    
    // Update existing tables manually instead of using the SQL script
    echo "\nUpdating existing tables...\n";
    
    // Add sync fields to defects table if they don't exist
    $tables = ['defects', 'defect_images', 'defect_comments'];
    $syncColumns = ['sync_status', 'client_id', 'sync_timestamp', 'device_id'];
    
    foreach ($tables as $table) {
        echo "Checking {$table}... ";
        
        // Check if sync_status already exists
        $checkStmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                                  WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        $checkStmt->execute([$config['db_name'], $table, 'sync_status']);
        $exists = $checkStmt->fetchColumn();
        
        if (!$exists) {
            // Get the after column - varies by table
            $afterColumn = ($table === 'defect_images') ? 'uploaded_at' : 'updated_at';
            
            try {
                $pdo->exec("ALTER TABLE {$table} 
                           ADD COLUMN sync_status ENUM('synced', 'pending', 'conflict') NOT NULL DEFAULT 'synced' AFTER {$afterColumn},
                           ADD COLUMN client_id VARCHAR(100) NULL AFTER sync_status,
                           ADD COLUMN sync_timestamp DATETIME NULL AFTER client_id,
                           ADD COLUMN device_id VARCHAR(100) NULL AFTER sync_timestamp");
                
                // Add indexes
                $pdo->exec("ALTER TABLE {$table} ADD INDEX(sync_status)");
                $pdo->exec("ALTER TABLE {$table} ADD INDEX(client_id)");
                
                echo "columns added.\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column') !== false) {
                    echo "some columns already exist. Skipping.\n";
                } else {
                    echo "error: " . $e->getMessage() . "\n";
                }
            }
        } else {
            echo "sync columns already exist. Skipping.\n";
        }
    }
    
    echo "\nChecking for triggers...\n";
    // Check if we need to create triggers
    $stmt = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TRIGGERS 
                          WHERE TRIGGER_SCHEMA = ? AND EVENT_OBJECT_TABLE = ? AND TRIGGER_NAME = ?");
    $stmt->execute([$config['db_name'], 'defects', 'defects_before_update']);
    $triggerExists = $stmt->fetchColumn();
    
    if (!$triggerExists) {
        echo "Creating sync triggers...\n";
        
        // Create defects trigger
        try {
            $pdo->exec("
                CREATE TRIGGER defects_before_update BEFORE UPDATE ON defects
                FOR EACH ROW
                BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END
            ");
            echo "Created defects trigger.\n";
        } catch (PDOException $e) {
            echo "Error creating defects trigger: " . $e->getMessage() . "\n";
        }
        
        // Create defect_images trigger
        try {
            $pdo->exec("
                CREATE TRIGGER defect_images_before_update BEFORE UPDATE ON defect_images
                FOR EACH ROW
                BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.uploaded_at != NEW.uploaded_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END
            ");
            echo "Created defect_images trigger.\n";
        } catch (PDOException $e) {
            echo "Error creating defect_images trigger: " . $e->getMessage() . "\n";
        }
        
        // Create defect_comments trigger
        try {
            $pdo->exec("
                CREATE TRIGGER defect_comments_before_update BEFORE UPDATE ON defect_comments
                FOR EACH ROW
                BEGIN
                    -- Only mark as pending if this is a direct update, not from the sync system
                    IF NEW.sync_status = 'synced' AND OLD.updated_at != NEW.updated_at THEN
                        SET NEW.sync_status = 'pending';
                        SET NEW.sync_timestamp = NOW();
                    END IF;
                END
            ");
            echo "Created defect_comments trigger.\n";
        } catch (PDOException $e) {
            echo "Error creating defect_comments trigger: " . $e->getMessage() . "\n";
        }
    } else {
        echo "Sync triggers already exist.\n";
    }
    
    echo "\nCreating initial sync log entry...\n";
    // Create initial sync log entry
    try {
        $stmt = $pdo->prepare("INSERT INTO sync_logs 
                              (username, start_time, end_time, items_processed, 
                               sync_direction, status, message) 
                              VALUES 
                              (?, ?, ?, 0, 'bidirectional', 'success', 'Initial database setup')");
                              
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['irlam', $now, $now]);
        echo "Created sync log entry.\n";
    } catch (PDOException $e) {
        echo "Error creating sync log: " . $e->getMessage() . "\n";
    }
    
    echo "\nAdding system log entry...\n";
    // Add a system_log entry about the sync system setup
    try {
        $userId = null;
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $userStmt->execute(['irlam']);
        $userId = $userStmt->fetchColumn();
        
        if ($userId) {
            $stmt = $pdo->prepare("INSERT INTO system_logs 
                                  (user_id, action, action_by, action_at, details) 
                                  VALUES (?, 'SYNC_SYSTEM_SETUP', ?, ?, 'Offline synchronization system initialized')");
            
            $stmt->execute([$userId, $userId, date('Y-m-d H:i:s')]);
            echo "Added system log entry.\n";
        } else {
            echo "Warning: User 'irlam' not found. Skipping system log entry.\n";
        }
    } catch (PDOException $e) {
        echo "Error adding system log: " . $e->getMessage() . "\n";
    }
    
    echo "\nSync database setup completed successfully!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}