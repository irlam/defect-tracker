---
applies_to:
  - "**/*.php"
  - config/database.php
  - classes/**/*.php
---

# Database Instructions

## Database Connection

### Establishing Connection

**Always use the Database singleton:**
```php
require_once 'config/database.php';

try {
    $db = Database::getInstance()->getConnection();
} catch (PDOException $e) {
    Logger::error('Database connection failed: ' . $e->getMessage());
    die('Database connection error');
}
```

### Connection Configuration
- Configuration is stored in `.env` file
- Database class is located in `config/database.php`
- Uses PDO with MySQL driver
- Connections are persistent for performance

## Prepared Statements

### SELECT Queries

**Single row:**
```php
$stmt = $db->prepare("SELECT * FROM defects WHERE id = ?");
$stmt->execute([$defectId]);
$defect = $stmt->fetch(PDO::FETCH_ASSOC);
```

**Multiple rows:**
```php
$stmt = $db->prepare("SELECT * FROM defects WHERE project_id = ? ORDER BY created_at DESC");
$stmt->execute([$projectId]);
$defects = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

**With named parameters:**
```php
$stmt = $db->prepare("SELECT * FROM users WHERE username = :username AND active = :active");
$stmt->execute([
    ':username' => $username,
    ':active' => 1
]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
```

### INSERT Queries

**Single insert:**
```php
$stmt = $db->prepare("
    INSERT INTO defects (title, description, project_id, created_by, created_at)
    VALUES (?, ?, ?, ?, NOW())
");
$stmt->execute([$title, $description, $projectId, $userId]);
$newDefectId = $db->lastInsertId();
```

**With named parameters:**
```php
$stmt = $db->prepare("
    INSERT INTO users (username, email, password_hash, role, created_at)
    VALUES (:username, :email, :password, :role, NOW())
");
$stmt->execute([
    ':username' => $username,
    ':email' => $email,
    ':password' => $passwordHash,
    ':role' => $role
]);
```

### UPDATE Queries

```php
$stmt = $db->prepare("
    UPDATE defects 
    SET status = ?, updated_at = NOW(), updated_by = ?
    WHERE id = ?
");
$stmt->execute([$newStatus, $userId, $defectId]);
$affectedRows = $stmt->rowCount();
```

### DELETE Queries

```php
$stmt = $db->prepare("DELETE FROM defects WHERE id = ? AND created_by = ?");
$stmt->execute([$defectId, $userId]);
$affectedRows = $stmt->rowCount();
```

## Transactions

**Use transactions for multi-step operations:**
```php
try {
    $db->beginTransaction();
    
    // First operation
    $stmt1 = $db->prepare("INSERT INTO defects (title, ...) VALUES (?, ...)");
    $stmt1->execute([$title, ...]);
    $defectId = $db->lastInsertId();
    
    // Second operation
    $stmt2 = $db->prepare("INSERT INTO defect_images (defect_id, ...) VALUES (?, ...)");
    $stmt2->execute([$defectId, ...]);
    
    // Commit if all successful
    $db->commit();
} catch (Exception $e) {
    // Rollback on error
    $db->rollBack();
    Logger::error('Transaction failed: ' . $e->getMessage());
    throw $e;
}
```

## Error Handling

**Always wrap database operations in try-catch:**
```php
try {
    $stmt = $db->prepare("SELECT * FROM defects WHERE id = ?");
    $stmt->execute([$defectId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        // Handle not found
        return null;
    }
    
    return $result;
} catch (PDOException $e) {
    Logger::error('Database query error: ' . $e->getMessage());
    // Re-throw or handle appropriately
    throw new Exception('Error retrieving defect');
}
```

## Common Patterns

### Pagination

```php
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$stmtCount = $db->prepare("SELECT COUNT(*) FROM defects WHERE project_id = ?");
$stmtCount->execute([$projectId]);
$totalDefects = $stmtCount->fetchColumn();

// Get paginated results
$stmt = $db->prepare("
    SELECT * FROM defects 
    WHERE project_id = ? 
    ORDER BY created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$projectId, $perPage, $offset]);
$defects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate pagination
$totalPages = ceil($totalDefects / $perPage);
```

### Search with LIKE

```php
$searchTerm = '%' . $search . '%';
$stmt = $db->prepare("
    SELECT * FROM defects 
    WHERE title LIKE ? OR description LIKE ?
    ORDER BY created_at DESC
");
$stmt->execute([$searchTerm, $searchTerm]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Joins

```php
$stmt = $db->prepare("
    SELECT 
        d.*, 
        u.username as created_by_name,
        p.name as project_name,
        c.company_name as contractor_name
    FROM defects d
    LEFT JOIN users u ON d.created_by = u.id
    LEFT JOIN projects p ON d.project_id = p.id
    LEFT JOIN contractors c ON d.assigned_to = c.id
    WHERE d.id = ?
");
$stmt->execute([$defectId]);
$defect = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Conditional WHERE Clauses

```php
$params = [];
$sql = "SELECT * FROM defects WHERE 1=1";

if (!empty($projectId)) {
    $sql .= " AND project_id = ?";
    $params[] = $projectId;
}

if (!empty($status)) {
    $sql .= " AND status = ?";
    $params[] = $status;
}

if (!empty($priority)) {
    $sql .= " AND priority = ?";
    $params[] = $priority;
}

$sql .= " ORDER BY created_at DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$defects = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

## Performance Best Practices

### Use Indexes
- Ensure frequently queried columns have indexes
- Use `system-tools/database_optimizer.php` to analyze performance

### Fetch Only Needed Columns
```php
// ❌ Less efficient
$stmt = $db->prepare("SELECT * FROM defects");

// ✅ More efficient
$stmt = $db->prepare("SELECT id, title, status FROM defects");
```

### Use LIMIT for Large Datasets
```php
// Always limit results when appropriate
$stmt = $db->prepare("SELECT * FROM defects ORDER BY created_at DESC LIMIT 100");
```

### Close Statements
```php
// PDO automatically closes statements, but you can explicitly close:
$stmt = null;
```

## Database Checklist

Before committing database-related code:
- [ ] All queries use prepared statements
- [ ] Parameters are properly bound (no string concatenation)
- [ ] Transactions are used for multi-step operations
- [ ] Errors are caught and logged
- [ ] Connection is obtained via Database singleton
- [ ] Queries are optimized (only fetch needed columns)
- [ ] Large result sets use pagination
- [ ] Database errors don't expose sensitive information

## Troubleshooting

- **Connection errors**: Check `.env` configuration and database server
- **Slow queries**: Use `system-tools/database_optimizer.php`
- **Lock timeouts**: Ensure transactions are kept short
- **Character encoding issues**: Database uses utf8mb4 collation
