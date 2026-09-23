<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SessionManager.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/TrainingRepository.php';

$sessionManager = new SessionManager();
if (!$sessionManager->isAuthenticated()) {
    header('Location: /login.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$userId = (int)($sessionManager->getUserId() ?? 0);
$username = (string)($sessionManager->getCurrentUser() ?? 'User');
$userRole = strtolower((string)($sessionManager->getUserType() ?? 'viewer'));

$navbar = null;
if ($db instanceof PDO) {
    $navbar = new Navbar($db, $userId, $username);
}

$training = new TrainingRepository($db instanceof PDO ? $db : null);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$trainingCsrfToken = (string)$_SESSION['csrf_token'];

function trainingEsc(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function trainingStatusLabel(string $status): string
{
    return match ($status) {
        'completed' => 'Complete',
        'in_progress' => 'In progress',
        default => 'Not started',
    };
}

function trainingRenderHeader(string $title, string $subtitle = ''): void
{
    global $navbar;

    echo '<!DOCTYPE html><html lang="en"><head>';
    echo '<meta charset="UTF-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta name="color-scheme" content="dark">';
    echo '<title>' . trainingEsc($title) . ' - Defect Tracker Training</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">';
    echo '<link href="/css/app.css" rel="stylesheet">';
    echo '<link href="/training/training.css" rel="stylesheet">';
    echo '</head><body class="tool-body has-app-navbar" data-bs-theme="dark">';

    if ($navbar) {
        $navbar->render();
    }

    echo '<main class="training-shell container-xl py-4">';
    echo '<a class="visually-hidden-focusable training-skip-link" href="#training-content">Skip to lesson content</a>';

    if ($subtitle !== '') {
        echo '<header class="training-page-heading mb-4">';
        echo '<h1 class="h3 mb-2">' . trainingEsc($title) . '</h1>';
        echo '<p class="text-muted mb-0">' . trainingEsc($subtitle) . '</p>';
        echo '</header>';
    }
}

function trainingRenderFooter(): void
{
    echo '</main>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<script src="/training/training.js"></script>';
    echo '</body></html>';
}
