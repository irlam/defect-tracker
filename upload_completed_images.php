<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/defect_workflow.php';
require_once __DIR__ . '/classes/NotificationHelper.php';

$defectId = filter_input(INPUT_POST, 'defect_id', FILTER_VALIDATE_INT) ?: 0;
$redirect = $defectId > 0 ? 'view_defect_mytasks.php?id=' . $defectId : 'my_tasks.php';
$writtenFiles = [];

try {
    if (!isset($_SESSION['user_id'])) {
        throw new RuntimeException('Please sign in to submit completion evidence.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !defectWorkflowHasValidCsrf($_POST['csrf_token'] ?? null)) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
    if ($defectId < 1 || empty($_FILES['completed_images']['name'][0])) {
        throw new RuntimeException('Choose at least one completion photo.');
    }

    $db = (new Database())->getConnection();
    if (!$db instanceof PDO) {
        throw new RuntimeException('Database connection unavailable.');
    }
    $userId = (int) $_SESSION['user_id'];
    if (!defectWorkflowCanAccessTask($db, $defectId, $userId)) {
        throw new RuntimeException('This defect is not assigned to you.');
    }

    $uploadDir = __DIR__ . '/uploads/defects/' . $defectId . '/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Unable to prepare the photo upload folder.');
    }

    $allowedMimeTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $maxFileSize = min(MAX_FILE_SIZE, 10 * 1024 * 1024);
    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $files = $_FILES['completed_images'];
    $validUploads = [];
    $errors = [];

    foreach ($files['name'] as $index => $originalName) {
        $error = (int) ($files['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        $tmpName = (string) ($files['tmp_name'][$index] ?? '');
        $size = (int) ($files['size'][$index] ?? 0);
        if ($error !== UPLOAD_ERR_OK) {
            $errors[] = basename((string) $originalName) . ' could not be uploaded.';
            continue;
        }
        if ($size < 1 || $size > $maxFileSize) {
            $errors[] = basename((string) $originalName) . ' exceeds the photo size limit.';
            continue;
        }

        $mimeType = $fileInfo->file($tmpName) ?: '';
        if (!isset($allowedMimeTypes[$mimeType])) {
            $errors[] = basename((string) $originalName) . ' is not a supported image.';
            continue;
        }

        $fileName = sprintf('complete_%s_%s.%s', date('Ymd_His'), bin2hex(random_bytes(6)), $allowedMimeTypes[$mimeType]);
        $validUploads[] = ['tmp' => $tmpName, 'name' => $fileName];
    }

    if ($validUploads === []) {
        throw new RuntimeException($errors[0] ?? 'No valid completion photos were selected.');
    }

    $db->beginTransaction();
    $defect = defectWorkflowLoadForUpdate($db, $defectId);
    defectWorkflowAssertTransition('submit', (string) $defect['status']);

    $imageStmt = $db->prepare("
        INSERT INTO defect_images (defect_id, file_path, uploaded_by, created_at, uploaded_at)
        VALUES (:defect_id, :file_path, :uploaded_by, NOW(), NOW())
    ");
    $firstPath = null;
    foreach ($validUploads as $upload) {
        $destination = $uploadDir . $upload['name'];
        if (!move_uploaded_file($upload['tmp'], $destination)) {
            throw new RuntimeException('A completion photo could not be saved.');
        }
        $writtenFiles[] = $destination;
        $relativePath = 'uploads/defects/' . $defectId . '/' . $upload['name'];
        $firstPath ??= $relativePath;
        $imageStmt->execute([
            ':defect_id' => $defectId,
            ':file_path' => $relativePath,
            ':uploaded_by' => $userId,
        ]);
    }

    $updateStmt = $db->prepare("
        UPDATE defects
        SET status = 'pending', closure_image = :closure_image,
            updated_by = :user_id, updated_at = NOW()
        WHERE id = :defect_id
    ");
    $updateStmt->execute([
        ':closure_image' => $firstPath,
        ':user_id' => $userId,
        ':defect_id' => $defectId,
    ]);
    defectWorkflowLogHistory($db, $defectId, $userId, 'Completion evidence submitted; status changed to Pending Review.');
    $db->commit();

    (new NotificationHelper($db))->notifyDefectStatusChanged($defectId, 'pending', $userId);
    $_SESSION['success_message'] = 'Completion evidence submitted for manager review.';
    if ($errors !== []) {
        $_SESSION['warning_message'] = implode(' ', $errors);
    }
} catch (Throwable $exception) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    foreach ($writtenFiles as $writtenFile) {
        if (is_file($writtenFile)) {
            @unlink($writtenFile);
        }
    }
    error_log('Completion Evidence Error: ' . $exception->getMessage());
    $_SESSION['error_message'] = $exception->getMessage();
}

header('Location: ' . BASE_URL . $redirect);
exit;
