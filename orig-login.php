<?php
// login.php

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['username'])) {
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$error = '';
$username = '';

// Include database configuration
require_once 'config/database.php';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get and sanitize input
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validate input
        if (empty($username) || empty($password)) {
            throw new Exception('Please enter both username and password.');
        }

        // Initialize database connection
        $database = new Database();
        $db = $database->getConnection();

        // Prepare and execute query
        $stmt = $db->prepare("SELECT id, username, password, full_name, role_id FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];

                // Set last login time
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                $updateStmt->execute(['id' => $user['id']]);

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit();
            } else {
                throw new Exception('Invalid username or password.');
            }
        } else {
            throw new Exception('Invalid username or password.');
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Login Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Construction Defect Tracker</title>
    
    <!-- Link to external CSS files -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Inline CSS for page styling -->
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            max-width: 400px;
            width: 90%;
            padding: 2rem;
            position: relative;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }

        .card-header {
            background-color: #2c3e50;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }

        .form-control:focus {
            box-shadow: none;
            border-color: #2c3e50;
        }

        .btn-primary {
            background-color: #2c3e50;
            border-color: #2c3e50;
        }

        .btn-primary:hover {
            background-color: #34495e;
            border-color: #34495e;
        }

        .logo {
            display: block;
            margin: 0 auto 1.5rem;
            max-width: 100px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="card">
            <div class="card-header text-center">
                <img src="https://mcgoff.defecttracker.uk/mcgoff.png" alt="Logo" class="logo">
                <h4 class="mb-0">Construction Defect Tracker</h4>
                <small class="text-light">Login to your account</small>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class='bx bx-error-circle me-1'></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bx-user'></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class='bx bx-lock-alt'></i></span>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Login</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Include Bootstrap JS for functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>