<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['user_id']) || empty($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once dirname(__DIR__) . '/config/database.php';

try {
    $db = (new Database())->getConnection();
    if (!$db) {
        throw new RuntimeException('Database connection unavailable.');
    }

    $projects = $db->query(
        "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name"
    )->fetchAll(PDO::FETCH_ASSOC);
    $contractors = $db->query(
        "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name"
    )->fetchAll(PDO::FETCH_ASSOC);
    $floorPlans = $db->query(
        "SELECT id, project_id, floor_name, image_path, file_path
         FROM floor_plans WHERE status = 'active' ORDER BY floor_name"
    )->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'userId' => (int) $_SESSION['user_id'],
        'username' => (string) $_SESSION['username'],
        'csrfToken' => (string) $_SESSION['csrf_token'],
        'refreshedAt' => gmdate('c'),
        'projects' => array_map(static fn(array $project): array => [
            'id' => (int) $project['id'],
            'name' => (string) $project['name'],
        ], $projects),
        'contractors' => array_map(static fn(array $contractor): array => [
            'id' => (int) $contractor['id'],
            'name' => (string) $contractor['company_name'],
        ], $contractors),
        'floorPlans' => array_map(static fn(array $plan): array => [
            'id' => (int) $plan['id'],
            'projectId' => (int) $plan['project_id'],
            'name' => (string) $plan['floor_name'],
            'imagePath' => empty($plan['image_path']) ? null : '/' . ltrim((string) $plan['image_path'], '/'),
            'filePath' => empty($plan['file_path']) ? null : '/' . ltrim((string) $plan['file_path'], '/'),
        ], $floorPlans),
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $error) {
    error_log('Offline field context failed: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['success' => false, 'message' => 'Field data is temporarily unavailable.']);
}
