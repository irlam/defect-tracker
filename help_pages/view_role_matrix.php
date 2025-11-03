<?php
/**
 * Role Capability Matrix Viewer
 * 
 * Renders the role_capability_matrix.md file with proper formatting
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SessionManager.php';

SessionManager::start();
if (!SessionManager::isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

$pageTitle = 'Role Capability Matrix';
$mdFile = __DIR__ . '/role_capability_matrix.md';
$content = file_exists($mdFile) ? file_get_contents($mdFile) : 'Documentation not found.';

// Simple markdown to HTML conversion for basic formatting
function basicMarkdownToHtml($markdown) {
    // Headers
    $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $markdown);
    $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
    
    // Bold
    $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
    
    // Horizontal rules
    $html = preg_replace('/^---$/m', '<hr>', $html);
    
    // Lists
    $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
    $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);
    
    // Checkboxes
    $html = str_replace('✅', '<span class="text-success">✅</span>', $html);
    $html = str_replace('❌', '<span class="text-danger">❌</span>', $html);
    $html = str_replace('⚠️', '<span class="text-warning">⚠️</span>', $html);
    
    // Code blocks
    $html = preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    
    // Paragraphs
    $html = preg_replace('/^(?!<[h|u|l|p|d|t|c]).+$/m', '<p>$0</p>', $html);
    
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
        .doc-content table {
            width: 100%;
            margin: 1rem 0;
            border-collapse: collapse;
        }
        .doc-content th,
        .doc-content td {
            padding: 0.75rem;
            border: 1px solid var(--border-color);
        }
        .doc-content th {
            background: var(--surface-color);
            font-weight: 600;
        }
        .doc-content code {
            background: var(--surface-muted);
            padding: 0.125rem 0.375rem;
            border-radius: 4px;
            font-size: 0.875em;
        }
        .doc-content hr {
            border-color: var(--border-color);
            margin: 2rem 0;
        }
    </style>
</head>
<body>
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
