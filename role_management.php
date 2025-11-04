<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

header('Location: dashboard.php');
exit;
