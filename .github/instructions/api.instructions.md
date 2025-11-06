---
applies_to:
  - api/**/*.php
---

# API Development Instructions

## API Endpoint Guidelines

When working with API endpoints in the `api/` directory:

### Structure
- Extend from `api/BaseAPI.php` when creating new API endpoints
- Follow the naming convention: `{action}_{resource}.php` (e.g., `create_defect.php`, `update_user.php`)

### Authentication & Authorization
1. **Always verify authentication** at the start of each endpoint
2. **Use RBAC** to check user permissions before processing requests
3. **Return 401 Unauthorized** for unauthenticated requests
4. **Return 403 Forbidden** for unauthorized access

### Input Validation
- Validate all input parameters
- Use `filter_var()` for type-safe validation
- Check for required parameters
- Sanitize inputs before database operations
- Return 400 Bad Request for invalid inputs

### Response Format
```php
// Success response
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'data' => $result,
    'message' => 'Operation completed successfully'
]);

// Error response
http_response_code(400);
echo json_encode([
    'success' => false,
    'error' => 'Error message',
    'details' => [] // Optional additional details
]);
```

### Database Operations
- **Always use PDO prepared statements**
- Wrap database operations in try-catch blocks
- Log errors to `logs/` directory using `Logger` class
- Use transactions for multi-step operations
- Close database connections properly

### Example API Endpoint Structure
```php
<?php
require_once '../config/database.php';
require_once '../classes/Auth.php';
require_once '../classes/RBAC.php';
require_once '../classes/Logger.php';

// Set JSON response header
header('Content-Type: application/json');

// Authenticate user
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Check permissions
$rbac = new RBAC();
if (!$rbac->hasPermission($auth->getUserId(), 'required_permission')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Forbidden']);
    exit;
}

// Validate input
$requiredParam = filter_input(INPUT_POST, 'param_name', FILTER_SANITIZE_STRING);
if (empty($requiredParam)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required parameter']);
    exit;
}

try {
    // Database operation
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT * FROM table WHERE column = ?");
    $stmt->execute([$requiredParam]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'data' => $result]);
} catch (Exception $e) {
    Logger::error('API Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}
```

### HTTP Status Codes
- **200 OK**: Successful GET, PUT, PATCH requests
- **201 Created**: Successful POST that creates a resource
- **400 Bad Request**: Invalid input or missing parameters
- **401 Unauthorized**: Missing or invalid authentication
- **403 Forbidden**: Insufficient permissions
- **404 Not Found**: Resource not found
- **500 Internal Server Error**: Server-side error

### Security Checklist
- [ ] Authentication verified
- [ ] RBAC permissions checked
- [ ] Input validated and sanitized
- [ ] SQL injection prevented (prepared statements)
- [ ] XSS prevented (output escaping if applicable)
- [ ] CSRF token validated for state-changing operations
- [ ] Errors logged without exposing sensitive data
