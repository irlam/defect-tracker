---
applies_to:
  - "**/*.php"
  - classes/Security.php
  - classes/Auth.php
  - classes/RBAC.php
---

# Security Instructions

## Critical Security Rules

When working on ANY PHP file in this project, follow these security best practices:

### 1. Input Validation & Sanitization

**ALWAYS validate and sanitize user input:**
```php
// For strings
$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING);
$username = trim($username);

// For integers
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// For email
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

// Custom validation
if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    // Invalid username format
}
```

### 2. SQL Injection Prevention

**NEVER concatenate SQL queries with user input:**
```php
// ❌ WRONG - Vulnerable to SQL injection
$sql = "SELECT * FROM users WHERE username = '$username'";

// ✅ CORRECT - Use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
```

### 3. XSS Prevention

**ALWAYS escape output when displaying user content:**
```php
// ❌ WRONG - Vulnerable to XSS
echo "<div>" . $userContent . "</div>";

// ✅ CORRECT - Escape output
echo "<div>" . htmlspecialchars($userContent, ENT_QUOTES, 'UTF-8') . "</div>";
```

### 4. CSRF Protection

**Include CSRF tokens in all forms:**
```php
// Generate token (use Security class)
require_once 'classes/Security.php';
$security = new Security();
$csrfToken = $security->generateCSRFToken();

// In HTML form
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

// Validate token on form submission
if (!$security->validateCSRFToken($_POST['csrf_token'])) {
    die('CSRF token validation failed');
}
```

### 5. Password Security

**Use PHP's password hashing functions:**
```php
// Hash password
$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Verify password
if (password_verify($plainPassword, $hashedPassword)) {
    // Password is correct
}
```

### 6. File Upload Security

**Validate file uploads thoroughly:**
```php
// Check file extension
$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
$fileExtension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

if (!in_array($fileExtension, $allowedExtensions)) {
    die('Invalid file type');
}

// Check MIME type
$allowedMimeTypes = ['image/jpeg', 'image/png', 'application/pdf'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $_FILES['file']['tmp_name']);
finfo_close($finfo);

if (!in_array($mimeType, $allowedMimeTypes)) {
    die('Invalid file type');
}

// Check file size
$maxSize = 5 * 1024 * 1024; // 5MB
if ($_FILES['file']['size'] > $maxSize) {
    die('File too large');
}

// Generate unique filename to prevent overwrites
$newFilename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $fileExtension;
```

### 7. Session Security

**Use secure session settings:**
```php
// Sessions are managed by the Auth class
// Session includes IP and user-agent validation
// See classes/Auth.php for implementation details

// Best practices:
// - Never expose session IDs in URLs
// - Session IDs are regenerated on login (handled by Auth class)
// - If implementing custom session handling, use:
//   session_regenerate_id(true);
```

### 8. Path Traversal Prevention

**Validate file paths:**
```php
// ❌ WRONG - Vulnerable to path traversal
$file = $_GET['file'];
include("uploads/" . $file);

// ✅ CORRECT - Validate and sanitize
$file = basename($_GET['file']); // Remove directory components
$allowedFiles = ['file1.php', 'file2.php'];
if (in_array($file, $allowedFiles)) {
    include("uploads/" . $file);
}
```

### 9. Error Handling

**Don't expose sensitive information in errors:**
```php
// ❌ WRONG - Exposes database details
catch (PDOException $e) {
    die($e->getMessage());
}

// ✅ CORRECT - Log error, show generic message
catch (PDOException $e) {
    Logger::error('Database error: ' . $e->getMessage());
    die('An error occurred. Please try again later.');
}
```

### 10. Environment Variables

**Never commit sensitive data to repository:**
```php
// ❌ WRONG - Hardcoded credentials
$dbPassword = 'MyPassword123';

// ✅ CORRECT - Use environment variables
$dbPassword = getenv('DB_PASSWORD');
// Or from .env file loaded by config/env.php
```

## Security Checklist for Code Changes

Before submitting code, verify:
- [ ] All user inputs are validated and sanitized
- [ ] SQL queries use prepared statements
- [ ] Output is escaped when displaying user content
- [ ] CSRF tokens are used for state-changing operations
- [ ] Passwords are hashed with password_hash()
- [ ] File uploads are validated (type, size, extension)
- [ ] Session handling is secure
- [ ] File paths are validated against path traversal
- [ ] Errors don't expose sensitive information
- [ ] No secrets are hardcoded in the code

## Security Classes

- **`classes/Security.php`**: CSRF token generation and validation
- **`classes/Auth.php`**: Authentication and session management
- **`classes/RBAC.php`**: Role-based access control

Always use these classes instead of implementing custom security logic.
