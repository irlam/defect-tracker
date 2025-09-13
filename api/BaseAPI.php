<?php
/**
 * api/BaseAPI.php
 * Base API class with improved security and error handling
 * Current Date and Time (UTC): <?php echo gmdate('Y-m-d H:i:s'); ?>
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Security.php';

class BaseAPI {
    protected $db;
    protected $currentUser;
    protected $currentUserId;
    protected $currentDateTime;

    public function __construct($db = null) {
        // Set security headers
        $this->setSecurityHeaders();
        
        // Initialize database connection
        if ($db === null) {
            $database = new Database();
            $this->db = $database->getConnection();
        } else {
            $this->db = $db;
        }
        
        // Set content type
        header('Content-Type: application/json');
        
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check authentication
        $this->checkAuthentication();
        
        // Initialize user context
        $this->currentUserId = $_SESSION['user_id'] ?? null;
        $this->currentUser = $_SESSION['username'] ?? null;
        $this->currentDateTime = gmdate('Y-m-d H:i:s');
    }

    /**
     * Set security headers
     */
    protected function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        if (Environment::isProduction()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Check if user is authenticated
     */
    protected function checkAuthentication() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
            $this->sendError('Unauthorized access', 401);
            exit();
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            session_destroy();
            $this->sendError('Session expired', 401);
            exit();
        }
        
        $_SESSION['last_activity'] = time();
    }

    /**
     * Validate CSRF token for state-changing operations
     */
    protected function validateCSRF() {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        
        if (!$token || !Security::validateCSRFToken($token)) {
            $this->sendError('Invalid security token', 403);
            exit();
        }
    }

    /**
     * Send successful response
     */
    protected function sendSuccess($message, $data = null, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => $this->currentDateTime
        ]);
        exit();
    }

    /**
     * Send error response
     */
    protected function sendError($message, $statusCode = 400, $details = null) {
        http_response_code($statusCode);
        
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => $this->currentDateTime
        ];
        
        // Only include details in development mode
        if (Environment::isDevelopment() && $details) {
            $response['details'] = $details;
        }
        
        echo json_encode($response);
        exit();
    }

    /**
     * Legacy method for backward compatibility
     */
    protected function sendResponse($success, $message, $statusCode = 200, $data = null) {
        if ($success) {
            $this->sendSuccess($message, $data, $statusCode);
        } else {
            $this->sendError($message, $statusCode);
        }
    }

    /**
     * Validate and sanitize input data
     */
    protected function validateInput($data, $rules = []) {
        $validated = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Check required fields
            if (isset($rule['required']) && $rule['required'] && empty($value)) {
                $errors[] = "Field '{$field}' is required";
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value) && (!isset($rule['required']) || !$rule['required'])) {
                $validated[$field] = null;
                continue;
            }
            
            // Type validation
            if (isset($rule['type'])) {
                switch ($rule['type']) {
                    case 'email':
                        if (!Security::validateEmail($value)) {
                            $errors[] = "Field '{$field}' must be a valid email";
                        }
                        break;
                    case 'integer':
                        if (!filter_var($value, FILTER_VALIDATE_INT)) {
                            $errors[] = "Field '{$field}' must be an integer";
                        }
                        $value = (int)$value;
                        break;
                    case 'float':
                        if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                            $errors[] = "Field '{$field}' must be a number";
                        }
                        $value = (float)$value;
                        break;
                }
            }
            
            // Length validation
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[] = "Field '{$field}' must not exceed {$rule['max_length']} characters";
            }
            
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[] = "Field '{$field}' must be at least {$rule['min_length']} characters";
            }
            
            $validated[$field] = Security::sanitizeInput($value);
        }
        
        if (!empty($errors)) {
            $this->sendError('Validation failed', 400, $errors);
        }
        
        return $validated;
    }
    
    /**
     * Log API activity
     */
    protected function logActivity($action, $details = null) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO api_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $this->currentUserId,
                $action,
                $details ? json_encode($details) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null,
                $this->currentDateTime
            ]);
        } catch (PDOException $e) {
            // Log to file if database logging fails
            error_log("API logging failed: " . $e->getMessage());
        }
    }
}