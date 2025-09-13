<?php
// upload_completed_images.php
// This file uploads completed images into the defect-specific folder,
// inserts a new row into the defect_images table with the relative file path,
// and then updates the defect record to mark it as pending (for manager review)
// by changing the status (make sure the defects table supports 'pending').

session_start(); // Ensure session is started

require_once 'config/database.php'; // Database connection
require_once 'config/constants.php';  // Site constants

// Debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Check that defect_id is provided and files are selected.
if (!isset($_POST['defect_id']) || empty($_FILES['completed_images']['name'][0])) {
    $_SESSION['error_message'] = "Invalid request or no files selected.";
    header("Location: view_defect_mytasks.php?id=" . ($_POST['defect_id'] ?? 0));
    exit;
}

$defectId = filter_input(INPUT_POST, 'defect_id', FILTER_VALIDATE_INT);
if (!$defectId) {
    $_SESSION['error_message'] = "Invalid defect ID.";
    header("Location: defects.php");
    exit;
}

// Ensure that the user is logged in and that the user id is valid.
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to upload images.";
    header("Location: login.php");
    exit;
}

$uploadedBy = (int) $_SESSION['user_id'];

try {
    $database = new Database();
    $db = $database->getConnection();

    // Define the base upload directory (relative path to be stored in the DB)
    $baseUploadDir = 'uploads/defects/';
    // Build the absolute path for file storage using __DIR__
    $uploadDir = __DIR__ . '/' . $baseUploadDir . $defectId . '/';

    // Ensure the defect-specific upload directory exists.
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $fileCount = count($_FILES['completed_images']['name']);
    $errors = [];
    $firstCompletedImagePath = null;

    for ($i = 0; $i < $fileCount; $i++) {
        $fileName    = $_FILES['completed_images']['name'][$i];
        $fileTmpName = $_FILES['completed_images']['tmp_name'][$i];
        $fileSize    = $_FILES['completed_images']['size'][$i];
        $fileError   = $_FILES['completed_images']['error'][$i];

        // File Validation
        if ($fileError !== UPLOAD_ERR_OK) {
            $errors[] = "Error uploading $fileName: " . getUploadErrorMessage($fileError);
            continue;
        }

        $maxFileSize = 5 * 1024 * 1024; // 5MB limit
        if ($fileSize > $maxFileSize) {
            $errors[] = "$fileName exceeds the maximum file size.";
            continue;
        }

        $allowedFileTypes = ['jpg', 'jpeg', 'png', 'gif'];
        $fileInfo = pathinfo($fileName);
        $fileExtension = strtolower($fileInfo['extension'] ?? '');

        if (!in_array($fileExtension, $allowedFileTypes)) {
            $errors[] = "$fileName has an invalid file type. Allowed types: " . implode(', ', $allowedFileTypes);
            continue;
        }

        // Create the new filename by prepending "complete_" to the original filename.
        $originalName = $fileInfo['filename'];
        $newFileName = 'complete_' . $originalName . '.' . $fileExtension;
        $destination = $uploadDir . $newFileName; // Absolute path for file move

        // Move the uploaded file to the destination folder.
        if (move_uploaded_file($fileTmpName, $destination)) {
            // Build the relative file path to store in the database.
            // For example: uploads/defects/229/complete_example.jpg
            $filePath = $baseUploadDir . $defectId . '/' . $newFileName;

            // If this is the first successfully uploaded completed image, store its path
            if (!$firstCompletedImagePath) {
                $firstCompletedImagePath = $filePath;
            }

            // Insert a new row into the defect_images table.
            $stmt = $db->prepare("
                INSERT INTO defect_images (defect_id, file_path, uploaded_by, created_at)
                VALUES (:defect_id, :file_path, :uploaded_by, NOW())
            ");
            $stmt->execute([
                ':defect_id'   => $defectId,
                ':file_path'   => $filePath,
                ':uploaded_by' => $uploadedBy
            ]);
        } else {
            $errors[] = "Failed to move uploaded file $fileName.";
        }
    }

    // If at least one completed image was uploaded successfully, update the defect record.
    if ($firstCompletedImagePath) {
        // IMPORTANT: Ensure that the 'pending' status is allowed in your defects table.
        // If not, consider updating the table or using an allowed value.
        $updateStmt = $db->prepare("
            UPDATE defects
            SET status = 'pending',
                closure_image = :closure_image,
                updated_at = NOW()
            WHERE id = :defect_id
        ");
        $updateStmt->execute([
            ':closure_image' => $firstCompletedImagePath,
            ':defect_id'     => $defectId
        ]);

        // Optionally, log this status change in a defect_history table.
        $historyStmt = $db->prepare("
            INSERT INTO defect_history (defect_id, description, updated_by, created_at)
            VALUES (:defect_id, :description, :updated_by, NOW())
        ");
        $description = 'Defect status changed to pending after completed images were uploaded.';
        $historyStmt->execute([
            ':defect_id'  => $defectId,
            ':description'=> $description,
            ':updated_by' => $uploadedBy
        ]);
    }

    if (empty($errors)) {
        $_SESSION['success_message'] = "Completed image(s) uploaded and defect marked as pending successfully!";
    } else {
        $_SESSION['error_message'] = "Some images were not uploaded due to errors:<br>" . implode("<br>", $errors);
    }
} catch (Exception $e) {
    error_log("Error in upload_completed_images.php: " . $e->getMessage());
    $_SESSION['error_message'] = "An unexpected error occurred during upload.";
}

header("Location: view_defect_mytasks.php?id=" . $defectId);
exit();

// Helper function to convert upload error codes to human-readable messages
function getUploadErrorMessage(int $errorCode): string {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
            return 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
        case UPLOAD_ERR_FORM_SIZE:
            return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
        case UPLOAD_ERR_PARTIAL:
            return 'The uploaded file was only partially uploaded.';
        case UPLOAD_ERR_NO_FILE:
            return 'No file was uploaded.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Missing a temporary folder.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Failed to write file to disk.';
        case UPLOAD_ERR_EXTENSION:
            return 'File upload stopped by extension.';
        default:
            return 'Unknown upload error.';
    }
}
?>