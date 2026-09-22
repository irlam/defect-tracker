<?php
/**
 * PWA Health Check Viewer
 * 
 * Renders the pwa_health_check.md file with proper formatting
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SessionManager.php';
require_once __DIR__ . '/../includes/navbar.php';

SessionManager::start();
if (!SessionManager::isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Create a database connection for navbar
$database = new Database();
$db = $database->getConnection();

$pageTitle = 'PWA Health Check';
$mdFile = __DIR__ . '/pwa_health_check.md';
$content = file_exists($mdFile) ? file_get_contents($mdFile) : 'Documentation not found.';

// Simple markdown to HTML conversion
function basicMarkdownToHtml($markdown) {
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    $html = preg_replace('/^---$/m', '<hr>', $html);
    $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);
    $html = str_replace('✅', '<span class="text-success">✅</span>', $html);
    $html = str_replace('❌', '<span class="text-danger">❌</span>', $html);
    $html = str_replace('⚠️', '<span class="text-warning">⚠️</span>', $html);
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    $html = preg_replace('/```(\w+)?\n(.*?)\n```/s', '<pre><code>$2</code></pre>', $html);
    $html = preg_replace('/^(?!<[h|u|l|p|d|t|c|pre]).+$/m', '<p>$0</p>', $html);
    return $html;
}

$htmlContent = basicMarkdownToHtml($content);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <style>
        body {
            padding-top: 76px;
        }
        .doc-content {
            max-width: 900px;
            margin: 0 auto;
        }
        .doc-content h1 {
            color: var(--primary-color);
            margin-bottom: 2rem;
        }
        .doc-content h2 {
            color: var(--text-color);
            margin-top: 2rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
            padding-bottom: 0.5rem;
        }
        .doc-content h3 {
            color: var(--text-color);
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
        .doc-content code {
            background: var(--surface-muted);
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-size: 0.875em;
        }
        .doc-content pre {
            background: var(--surface-muted);
            padding: 1rem;
            border-radius: var(--border-radius);
            overflow-x: auto;
        }
        .doc-content pre code {
            background: none;
            padding: 0;
        }
        .doc-content hr {
            border-color: var(--border-color);
            margin: 2rem 0;
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php
    // Render navbar
    $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    $navbar->render();
    ?>
    <div class="container my-5">
        <div class="doc-content">
            <?php echo $htmlContent; ?>
            
            <div class="mt-5">
                <a href="/help_index.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Help Index
                </a>
                <a href="/Site-presentation/training.php" class="btn btn-outline-primary ms-2">
                    <i class="fas fa-graduation-cap"></i> Training Materials
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
