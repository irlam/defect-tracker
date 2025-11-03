<?php
/**
 * Create Defect - Defect Tracker
 * create_defect.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/create_defect.log'); // Specific log file

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/DefectImageProcessor.php';
require_once 'includes/navbar.php';
require_once 'classes/NotificationHelper.php';
// require_once 'includes/PdfConverter.php';

$currentUser = $_SESSION['username'];
$currentUserId = (int)$_SESSION['user_id'];
$currentDateTime = date('Y-m-d H:i:s');

// Log entry function
function logEntry($message) {
    global $currentUser, $currentDateTime;
    $logMessage = "[" . $currentDateTime . "] User: " . $currentUser . " - " . $message . "\n";
    error_log($logMessage);
}

// Generate CSRF token if not already generated
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    logEntry("Generated new CSRF token.");
}

$database = new Database();
$db = $database->getConnection();

// Initialize the Navbar class
$navbar = new Navbar($db, $currentUserId, $_SESSION['username']);

// Get projects list
$projectQuery = "SELECT id, name FROM projects WHERE status = 'active' ORDER BY name";
$projectStmt = $db->query($projectQuery);
$projects = $projectStmt->fetchAll(PDO::FETCH_ASSOC);

// Get contractors list
$contractorQuery = "SELECT id, company_name FROM contractors WHERE status = 'active' ORDER BY company_name";
$contractorStmt = $db->query($contractorQuery);
$contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC);

// Get active floor plans
$floorPlanQuery = "SELECT id, floor_name, image_path, file_path FROM floor_plans WHERE status = 'active'";
$floorPlanStmt = $db->query($floorPlanQuery);
$floorPlans = $floorPlanStmt->fetchAll(PDO::FETCH_ASSOC);
$selectedFloorPlan = null;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    logEntry("Received POST request.");

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        logEntry("CSRF token validation failed. POST data: " . print_r($_POST, true));
        $_SESSION['error_message'] = "CSRF token validation failed";
        header("Location: defects.php");
        exit();
    }

    $selectedFloorPlanId = filter_input(INPUT_POST, 'floor_plan_id', FILTER_VALIDATE_INT);
    if (!$selectedFloorPlanId) {
        logEntry("No floor plan selected. POST data: " . print_r($_POST, true));
        $_SESSION['error_message'] = "Please select a floor plan.";
        header("Location: create_defect.php");
        exit();
    }
    foreach ($floorPlans as $floorPlan) {
        if ($floorPlan['id'] == $selectedFloorPlanId) {
            $selectedFloorPlan = $floorPlan;
            break;
        }
    }

    if (!$selectedFloorPlan) {
        logEntry("Selected floor plan not found. ID: " . $selectedFloorPlanId . " POST data: " . print_r($_POST, true));
        $_SESSION['error_message'] = "Selected floor plan not found.";
        header("Location: create_defect.php");
        exit();
    }

    $imagePath = $selectedFloorPlan['image_path'];
    $filePath = $selectedFloorPlan['file_path'];

    $pinX = filter_input(INPUT_POST, 'pin_x', FILTER_VALIDATE_FLOAT);
    $pinY = filter_input(INPUT_POST, 'pin_y', FILTER_VALIDATE_FLOAT);

    if ($pinX === false || $pinY === false) {
        logEntry("Invalid pin coordinates. pinX: " . $pinX . ", pinY: " . $pinY . " POST data: " . print_r($_POST, true));
        $_SESSION['error_message'] = "Invalid pin coordinates.";
        header("Location: create_defect.php");
        exit();
    }

    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $contractorId = filter_input(INPUT_POST, 'contractor_id', FILTER_VALIDATE_INT);
    $priority = filter_input(INPUT_POST, 'priority', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    // Replace the current due date handling line
// FROM: $dueDate = filter_input(INPUT_POST, 'due_date', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
// TO:

// Get the raw due date value
$rawDueDate = isset($_POST['due_date']) ? $_POST['due_date'] : null;

// Format properly for database
if (!empty($rawDueDate)) {
    // If already in YYYY-MM-DD format
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDueDate)) {
        $dueDate = $rawDueDate;
    } else {
        // Try to parse the date
        $timestamp = strtotime($rawDueDate);
        if ($timestamp !== false) {
            $dueDate = date('Y-m-d', $timestamp);
        } else {
            $dueDate = null;
        }
    }
} else {
    $dueDate = null;
}


	    // Insert defect into database
	logEntry("Due date value before database insertion: " . ($dueDate === null ? "NULL" : $dueDate));
    $defectQuery = "INSERT INTO defects (project_id, floor_plan_id, title, description, contractor_id, priority, due_date, pin_x, pin_y, reported_by, created_at, has_pin, assigned_to, created_by)
                    VALUES (:project_id, :floor_plan_id, :title, :description, :contractor_id, :priority, :due_date, :pin_x, :pin_y, :reported_by, :created_at, :has_pin, :assigned_to, :created_by)";
    $defectStmt = $db->prepare($defectQuery);
    $defectStmt->bindParam(':project_id', $projectId);
    $defectStmt->bindParam(':floor_plan_id', $selectedFloorPlanId);
    $defectStmt->bindParam(':title', $title);
    $defectStmt->bindParam(':description', $description);
    $defectStmt->bindParam(':contractor_id', $contractorId);
    $defectStmt->bindParam(':priority', $priority);
    //$defectStmt->bindParam(':due_date', $dueDate);  // <-- REPLACE THIS LINE
	// New code to replace the single line above
if ($dueDate === null) {
    $defectStmt->bindValue(':due_date', null, PDO::PARAM_NULL);
} else {
    $defectStmt->bindValue(':due_date', $dueDate, PDO::PARAM_STR);
}
    $defectStmt->bindParam(':pin_x', $pinX);
    $defectStmt->bindParam(':pin_y', $pinY);
    $defectStmt->bindParam(':reported_by', $currentUserId);
    $defectStmt->bindParam(':created_at', $currentDateTime);
    $hasPin = true;
    $defectStmt->bindParam(':has_pin', $hasPin, PDO::PARAM_BOOL);
    $defectStmt->bindParam(':assigned_to', $contractorId);
    $defectStmt->bindParam(':created_by', $currentUserId);

    try {
        $defectStmt->execute();
    } catch (PDOException $e) {
        logEntry("Database error inserting defect: " . $e->getMessage() . " Query: " . $defectQuery);
        $_SESSION['error_message'] = "Database error creating defect.";
        header("Location: defects.php");
        exit();
    }

    // Get the generated defect ID
    $defectId = $db->lastInsertId();
    logEntry("Defect created with ID: " . $defectId);

    // Trigger notification for defect creation
    $notificationHelper = new NotificationHelper($db);
    $notificationHelper->notifyDefectCreated($defectId, $currentUserId);

    // Store the recent description
    storeRecentDescription($currentUserId, $description, $db);

    // Create directory for defect images
    $defectDir = __DIR__ . "/uploads/defects/{$defectId}";
    if (!is_dir($defectDir)) {
        if (!mkdir($defectDir, 0777, true)) {
            logEntry("Failed to create defect directory: " . $defectDir);
        } else {
            logEntry("Created defect directory: " . $defectDir);
        }
    }

    // Process floor plan image with pin using DefectImageProcessor
    $pinImagePath = "";
    $processedImagePath = "";
    if ($pinX !== null && $pinY !== null) {
        $floorPlanPath = __DIR__ . "/" . $selectedFloorPlan['image_path'];
        logEntry("Floor Plan Path: " . $floorPlanPath);

        $imageProcessor = new DefectImageProcessor();
        $result = $imageProcessor->createDefectImage($floorPlanPath, $defectDir, $pinX, $pinY);
		        if ($result['status'] == 'success') {
            // Copy the processed image to a fixed name for consistency.
            $floorPlanWithPinName = "floorplan_with_pin_defect.png";
            $floorPlanWithPinPath = "uploads/defects/{$defectId}/" . $floorPlanWithPinName;

            if (copy($result['path'], __DIR__ . "/" . $floorPlanWithPinPath)) {
                $pinImagePath = $floorPlanWithPinPath;
                $processedImagePath = __DIR__ . "/" . $floorPlanWithPinPath;
                logEntry("Created floor plan with pin: " . $floorPlanWithPinPath);
            } else {
                logEntry("Failed to create floor plan with pin copy");
                $pinImagePath = "";
                $hasPin = false;
            }
        } else {
            logEntry("Failed to overlay pin on floor plan: " . $result['message']);
            $_SESSION['warning_message'] = "Failed to overlay pin on floor plan, using canvas drawing if it exists.";
            $pinImagePath = "";
            $hasPin = false;
        }

        // Insert the processed floor plan (with pin) record if available
        if (!empty($pinImagePath)) {
            $pinImageInsertQuery = "INSERT INTO defect_images (defect_id, file_path, pin_path, uploaded_by, created_at, uploaded_at)
                                    VALUES (:defect_id, :file_path, :pin_path, :uploaded_by, :created_at, :uploaded_at)";
            $pinImageInsertStmt = $db->prepare($pinImageInsertQuery);

            $filePathValue = $pinImagePath; 

            $pinImageInsertStmt->bindParam(':defect_id', $defectId);
            $pinImageInsertStmt->bindValue(':file_path', $filePathValue);
            $pinImageInsertStmt->bindParam(':pin_path', $pinImagePath, PDO::PARAM_STR);
            $pinImageInsertStmt->bindParam(':uploaded_by', $currentUserId);
            $pinImageInsertStmt->bindParam(':created_at', $currentDateTime);
            $pinImageInsertStmt->bindParam(':uploaded_at', $currentDateTime);

            try {
                $pinImageInsertStmt->execute();
                logEntry("Successfully inserted pin image record: " . $filePathValue);
            } catch (PDOException $e) {
                logEntry("Database error inserting pin image: " . $e->getMessage());
            }
        }
    }

    // Compute the hash of the processed floor plan image once (if available)
    $processedHash = "";
    if (!empty($processedImagePath) && file_exists($processedImagePath)) {
        $processedHash = md5_file($processedImagePath);
    }
	    // Process user-uploaded images (multiple images allowed)
if (!empty($_FILES['images']['name'][0])) {
    logEntry("Processing " . count($_FILES['images']['name']) . " uploaded images");
    
    // Create a dedicated log entry to see the full file information
    logEntry("FILES array details: " . print_r($_FILES, true));
    
    foreach ($_FILES['images']['name'] as $key => $name) {
        $tmpName = $_FILES['images']['tmp_name'][$key];
        $fileType = $_FILES['images']['type'][$key];
        $fileSize = $_FILES['images']['size'][$key];
        
        logEntry("Processing file: " . $name . ", type: " . $fileType . ", size: " . $fileSize);
        
        // Skip empty files
        if (empty($tmpName) || !file_exists($tmpName) || $fileSize == 0) {
            logEntry("Empty or missing file at key " . $key);
            continue;
        }
        
        // IMPORTANT: DISABLE the duplicate file check - this is causing problems with Take Picture images
        /* 
        if (!empty($processedHash) && file_exists($tmpName)) {
            $uploadedHash = md5_file($tmpName);
            if ($processedHash === $uploadedHash) {
                logEntry("Skipping duplicate upload for file: " . $name);
                continue; // Skip this uploaded file if it matches the processed image.
            }
        }
        */

        // Generate a unique name to avoid conflicts
        $fileName = time() . "_" . uniqid() . "_" . basename($name);
        $targetFilePath = "{$defectDir}/{$fileName}";

        // Check if the file is an edited image based on the name or content type
        $isEdited = (strpos($name, 'edited') !== false) || 
                    (strpos($name, 'edited_image') !== false);
        
        logEntry("Moving file to: " . $targetFilePath . ($isEdited ? " (edited image)" : ""));
        
        if (move_uploaded_file($tmpName, $targetFilePath)) {
            $filePath = "uploads/defects/{$defectId}/{$fileName}";

            // Insert image record for user-uploaded image
            $imageInsertQuery = "INSERT INTO defect_images (defect_id, file_path, pin_path, uploaded_by, created_at, uploaded_at, is_edited)
                                VALUES (:defect_id, :file_path, :pin_path, :uploaded_by, :created_at, :uploaded_at, :is_edited)";
            $imageInsertStmt = $db->prepare($imageInsertQuery);
            $imageInsertStmt->bindParam(':defect_id', $defectId);
            $imageInsertStmt->bindParam(':file_path', $filePath);
            $imageInsertStmt->bindValue(':pin_path', null, PDO::PARAM_NULL);
            $imageInsertStmt->bindParam(':uploaded_by', $currentUserId);
            $imageInsertStmt->bindParam(':created_at', $currentDateTime);
            $imageInsertStmt->bindParam(':uploaded_at', $currentDateTime);
            $imageInsertStmt->bindValue(':is_edited', $isEdited ? 1 : 0, PDO::PARAM_INT);
            try {
                $imageInsertStmt->execute();
                logEntry("Uploaded image: " . $filePath . ($isEdited ? " (edited)" : ""));
            } catch (PDOException $e) {
                logEntry("Database error inserting image: " . $e->getMessage());
            }
        } else {
            $phpError = error_get_last();
            logEntry("Failed to move uploaded file: " . $tmpName . " to " . $targetFilePath);
            logEntry("PHP error: " . ($phpError ? json_encode($phpError) : "No error details available"));
        }
    }
} else {
    logEntry("No images found in the form submission");
    // Debug the FILES array
    logEntry("FILES array contents: " . print_r($_FILES, true));
}

    $_SESSION['success_message'] = "Defect created successfully.";
    header("Location: defects.php");
    exit();
}	


// Get priority list
$priorityQuery = "SELECT DISTINCT priority FROM defects WHERE deleted_at IS NULL ORDER BY priority";
$priorityStmt = $db->query($priorityQuery);
$priorities = $priorityStmt->fetchAll(PDO::FETCH_COLUMN);

// Retrieve recent descriptions for the user
$recentDescriptions = getRecentDescriptions($currentUserId, $db);

// Function to store recent descriptions
function storeRecentDescription($userId, $description, $db) {
    $countQuery = "SELECT COUNT(*) FROM user_recent_descriptions WHERE user_id = :user_id";
    $countStmt = $db->prepare($countQuery);
    $countStmt->bindParam(':user_id', $userId);
    $countStmt->execute();
    $count = $countStmt->fetchColumn();

    if ($count >= 6) {
        $deleteQuery = "DELETE FROM user_recent_descriptions WHERE user_id = :user_id ORDER BY created_at ASC LIMIT 1";
        $deleteStmt = $db->prepare($deleteQuery);
        $deleteStmt->bindParam(':user_id', $userId);
        $deleteStmt->execute();
    }

    $insertQuery = "INSERT INTO user_recent_descriptions (user_id, description) VALUES (:user_id, :description)";
    $insertStmt = $db->prepare($insertQuery);
    $insertStmt->bindParam(':user_id', $userId);
    $insertStmt->bindParam(':description', $description);
    $insertStmt->execute();
}

// Function to retrieve recent descriptions
function getRecentDescriptions($userId, $db) {
    $query = "SELECT description FROM user_recent_descriptions WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 6";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$projectCount = is_array($projects ?? null) ? count($projects) : 0;
$contractorCount = is_array($contractors ?? null) ? count($contractors) : 0;
$floorPlanCount = is_array($floorPlans ?? null) ? count($floorPlans) : 0;
$recentSnippetCount = is_array($recentDescriptions ?? null) ? count($recentDescriptions) : 0;
$displayName = trim((string)($_SESSION['full_name'] ?? ''));
if ($displayName === '') {
    $displayName = $currentUser;
}
$timestampDisplay = date('d M Y, H:i');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Defect - Defect Tracker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
	<link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <style>
        .create-defect {
            color: rgba(226, 232, 240, 0.94);
        }

        .create-defect__hero {
            background: linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(37, 99, 235, 0.86));
            border-radius: 1.5rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 2.5rem 2.25rem;
            box-shadow: 0 32px 56px -28px rgba(15, 23, 42, 0.78);
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.75rem;
        }

        .create-defect__hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.08em;
            background: rgba(59, 130, 246, 0.25);
            color: rgba(226, 232, 240, 0.92);
            border-radius: 999px;
            padding: 0.4rem 0.85rem;
        }

        .create-defect__hero h1 {
            font-size: clamp(1.8rem, 1.2rem + 1.5vw, 2.2rem);
            font-weight: 600;
            color: rgba(226, 232, 240, 0.98);
            margin-bottom: 0.75rem;
        }

        .create-defect__hero p {
            color: rgba(191, 219, 254, 0.86);
            margin-bottom: 0;
        }

        .create-defect__meta {
            display: grid;
            gap: 0.7rem;
            font-size: 0.9rem;
            color: rgba(191, 219, 254, 0.85);
        }

        .create-defect__meta-item {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 0.85rem;
            border-radius: 0.85rem;
            background: rgba(15, 23, 42, 0.45);
            border: 1px solid rgba(148, 163, 184, 0.18);
        }

        .create-defect__meta-item strong {
            font-weight: 600;
            color: rgba(226, 232, 240, 0.95);
        }

        .create-defect__card {
            background: rgba(15, 23, 42, 0.88);
            border-radius: 1.45rem;
            border: 1px solid rgba(148, 163, 184, 0.18);
            box-shadow: 0 32px 58px -32px rgba(15, 23, 42, 0.9);
        }

        .create-defect__card-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .create-defect__card-heading p {
            margin: 0;
            color: rgba(148, 163, 184, 0.82);
            max-width: 32rem;
        }

        .create-defect__section {
            background: rgba(15, 23, 42, 0.7);
            border-radius: 1.1rem;
            border: 1px solid rgba(148, 163, 184, 0.12);
            padding: 1.75rem;
            margin-bottom: 1.75rem;
            box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.08);
        }

        .create-defect__section:last-of-type {
            margin-bottom: 0;
        }

        .create-defect__section-heading {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.25rem;
        }

        .create-defect__section-heading div {
            flex: 1 1 220px;
        }

        .create-defect__step {
            width: 2.6rem;
            height: 2.6rem;
            border-radius: 0.9rem;
            background: rgba(59, 130, 246, 0.22);
            color: rgba(191, 219, 254, 0.95);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            letter-spacing: 0.05em;
        }

        .create-defect__section-title {
            font-size: 1.05rem;
            font-weight: 600;
            margin-bottom: 0.3rem;
            color: rgba(226, 232, 240, 0.95);
        }

        .create-defect__section-text {
            color: rgba(148, 163, 184, 0.82);
            font-size: 0.9rem;
            margin: 0;
        }

        .form-label {
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            color: rgba(148, 163, 184, 0.85);
        }

        .form-control,
        .form-select {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(148, 163, 184, 0.2);
            color: rgba(226, 232, 240, 0.95);
            border-radius: 0.85rem;
            padding: 0.75rem 1rem;
            min-height: 3rem;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: rgba(96, 165, 250, 0.65);
            box-shadow: 0 0 0 0.2rem rgba(96, 165, 250, 0.25);
            background: rgba(15, 23, 42, 0.85);
            color: #fff;
        }

        textarea.form-control {
            min-height: 140px;
        }

        .form-select-button {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            padding: 0.9rem 1rem;
            font-weight: 600;
            border-radius: 0.9rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.6);
            color: rgba(226, 232, 240, 0.95);
            transition: all 0.2s ease;
            box-shadow: inset 0 1px 0 rgba(148, 163, 184, 0.13);
        }

        .form-select-button:hover {
            border-color: rgba(96, 165, 250, 0.55);
            color: #fff;
        }

        .form-select-button.blue {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.2), rgba(59, 130, 246, 0.12));
            color: rgba(191, 219, 254, 0.95);
        }

        .form-select-button.selected,
        .form-select-button.green {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.35), rgba(5, 150, 105, 0.4));
            color: rgba(209, 250, 229, 1);
            border-color: rgba(16, 185, 129, 0.55);
            box-shadow: 0 18px 32px -20px rgba(16, 185, 129, 0.6);
        }

        .dropdown-menu {
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(59, 130, 246, 0.35);
            border-radius: 0.9rem;
            padding: 0.5rem;
            box-shadow: 0 28px 48px -32px rgba(15, 23, 42, 0.9);
            max-height: 260px;
            overflow-y: auto;
        }

        .dropdown-item {
            border-radius: 0.65rem;
            font-weight: 500;
            padding: 0.65rem 0.85rem;
            color: rgba(226, 232, 240, 0.9) !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .dropdown-item:hover,
        .dropdown-item:focus {
            background: rgba(59, 130, 246, 0.22);
            color: #fff !important;
            transform: translateY(-1px);
        }

        .dropdown-item + .dropdown-item {
            margin-top: 0.25rem;
        }

        .dropdown-menu::-webkit-scrollbar {
            width: 6px;
        }

        .dropdown-menu::-webkit-scrollbar-thumb {
            background: rgba(59, 130, 246, 0.45);
            border-radius: 999px;
        }

        .dropdown-menu::-webkit-scrollbar-track {
            background: transparent;
        }

        .required::after {
            content: ' *';
            color: #f87171;
        }

        #imageGallery {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 0.75rem;
        }

        #imageGallery img {
            border-radius: 0.75rem;
            box-shadow: 0 10px 20px -18px rgba(59, 130, 246, 0.65);
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(15, 23, 42, 0.6);
            padding: 4px;
        }

        .img-thumbnail {
            background: transparent;
            border: none;
        }

        .selected-image {
            outline: 3px solid rgba(59, 130, 246, 0.7);
            border-radius: 0.75rem;
        }

        .pdf-container {
            position: relative;
            width: 100%;
            height: 480px;
            overflow: hidden;
            touch-action: none;
            background: rgba(15, 23, 42, 0.65);
            border-radius: 1rem;
            border: 1px solid rgba(148, 163, 184, 0.2);
        }

        #pdfCanvas,
        #pinOverlay {
            position: absolute;
            left: 50%;
            top: 50%;
            transform-origin: 50% 50%;
            image-rendering: -webkit-optimize-contrast;
            image-rendering: crisp-edges;
            image-rendering: pixelated;
            backface-visibility: hidden;
        }

        #pinOverlay {
            width: 100%;
            height: 100%;
            pointer-events: auto;
        }

        #pdfLoadingOverlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, 0.82);
            color: rgba(226, 232, 240, 0.92);
            font-weight: 600;
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10;
            border-radius: 1rem;
        }

        .location-pin {
            position: absolute;
            width: 46px;
            height: 46px;
            background-image: url('uploads/images/location-pin.svg');
            background-size: contain;
            background-repeat: no-repeat;
            pointer-events: none;
        }

        .drawing-canvas {
            position: absolute;
            top: 0;
            left: 0;
        }

        .create-defect__toolbar button {
            border-radius: 999px;
            padding-inline: 1rem;
        }

        .create-defect__toolbar .btn-secondary {
            background: rgba(15, 23, 42, 0.65);
            border: 1px solid rgba(148, 163, 184, 0.25);
            color: rgba(226, 232, 240, 0.85);
        }

        .create-defect__toolbar .btn-secondary:hover {
            border-color: rgba(96, 165, 250, 0.55);
            color: #fff;
        }

        .create-defect__quickfill .btn {
            background: rgba(15, 23, 42, 0.55);
            border: 1px solid rgba(148, 163, 184, 0.22);
            color: rgba(226, 232, 240, 0.87);
            border-radius: 0.75rem;
        }

        .create-defect__quickfill .btn:hover {
            border-color: rgba(96, 165, 250, 0.6);
            color: #fff;
        }

        .create-defect__quickfill .dropdown-menu {
            width: 100%;
        }

        .take-picture__btn {
            width: 100%;
            border-radius: 0.85rem;
            font-weight: 600;
            padding: 0.9rem;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.35), rgba(59, 130, 246, 0.55));
            color: #fff;
            border: none;
            box-shadow: 0 24px 42px -30px rgba(59, 130, 246, 0.85);
        }

        .take-picture__btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 24px 48px -28px rgba(59, 130, 246, 0.95);
        }

        .create-defect__cta {
            margin-top: 2rem;
        }

        .create-defect__cta .btn {
            padding-left: 1.75rem;
            padding-right: 1.75rem;
            font-weight: 600;
        }

        .create-defect__hint {
            font-size: 0.85rem;
            color: rgba(148, 163, 184, 0.78);
            margin-top: 0.75rem;
        }

        .create-defect__sidebar {
            background: linear-gradient(180deg, rgba(37, 99, 235, 0.28), rgba(17, 24, 39, 0.95));
            border-radius: 1.5rem;
            border: 1px solid rgba(37, 99, 235, 0.35);
            padding: 2rem 1.75rem;
            box-shadow: 0 28px 48px -32px rgba(37, 99, 235, 0.65);
            color: rgba(226, 232, 240, 0.96);
            position: sticky;
            top: 108px;
        }

        .create-defect__sidebar h3 {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(191, 219, 254, 0.75);
            margin-bottom: 1rem;
        }

        .create-defect__sidebar ul {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 0.75rem;
        }

        .create-defect__sidebar li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            background: rgba(15, 23, 42, 0.45);
            border: 1px solid rgba(59, 130, 246, 0.35);
            border-radius: 1rem;
            padding: 0.75rem 1rem;
        }

        .create-defect__sidebar li span {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.85rem;
            color: rgba(226, 232, 240, 0.9);
        }

        .create-defect__sidebar li strong {
            font-size: 1.1rem;
            color: rgba(226, 232, 240, 0.98);
        }

        .modal-content {
            background: rgba(15, 23, 42, 0.92);
            color: rgba(226, 232, 240, 0.96);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 1rem;
        }

        .modal-header {
            border-bottom-color: rgba(148, 163, 184, 0.2);
        }

        .modal-footer {
            border-top-color: rgba(148, 163, 184, 0.2);
        }

        .btn-close {
            filter: invert(1);
        }

        @media (max-width: 1199.98px) {
            .create-defect__sidebar {
                position: static;
            }
        }

        @media (max-width: 768px) {
            .create-defect__hero {
                padding: 1.75rem;
            }

            .create-defect__section {
                padding: 1.25rem;
            }

            .create-defect__card-heading {
                gap: 1.5rem;
            }

            .create-defect__cta {
                display: flex;
                flex-direction: column;
                gap: 0.75rem;
            }

            .create-defect__cta .btn {
                width: 100%;
            }

            .pdf-container {
                height: 360px;
            }
        }
    </style>
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php $navbar->render(); ?>

    <main class="create-defect container-xl py-4">
        <section class="create-defect__hero mb-4">
            <div>
                <span class="create-defect__hero-badge"><i class='bx bx-map-pin me-1'></i>New field issue</span>
                <h1>Log a site defect in moments</h1>
                <p>Pin the floor plan, attach supporting photos, and notify the right contractor without leaving the job.</p>
            </div>
            <div class="create-defect__meta">
                <div class="create-defect__meta-item"><i class='bx bx-user-circle'></i><span><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span></div>
                <div class="create-defect__meta-item"><i class='bx bx-time-five'></i><span><?php echo htmlspecialchars($timestampDisplay, ENT_QUOTES, 'UTF-8'); ?> UK</span></div>
                <div class="create-defect__meta-item"><i class='bx bx-buildings'></i><span><strong><?php echo number_format($projectCount); ?></strong> active projects</span></div>
                <div class="create-defect__meta-item"><i class='bx bx-image-alt'></i><span><strong><?php echo number_format($floorPlanCount); ?></strong> floor plans ready</span></div>
            </div>
        </section>

        <div class="row g-4 align-items-start">
            <div class="col-12 col-xxl-8">
                <div class="card create-defect__card border-0">
                    <div class="card-body p-4 p-xl-5">
                        <div class="create-defect__card-heading">
                            <div>
                                <h2 class="h4 mb-1">Raise a defect report</h2>
                                <p>Complete the sections below to produce a routed defect with imagery, assignee, and location context.</p>
                            </div>
                            <span class="badge bg-primary bg-opacity-25 text-light text-uppercase"><i class="bx bx-check-shield me-1"></i>Guided workflow</span>
                        </div>

                        <form action="create_defect.php" method="POST" id="createDefectForm" class="needs-validation" enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                            <div class="create-defect__section">
                                <div class="create-defect__section-heading">
                                    <span class="create-defect__step">01</span>
                                    <div>
                                        <h3 class="create-defect__section-title">Defect essentials</h3>
                                        <p class="create-defect__section-text">Give the issue a clear title, target project, and priority so we can route it instantly.</p>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label required" for="title">Title</label>
                                        <input type="text" class="form-control" id="title" name="title" required>
                                        <div class="invalid-feedback">Please provide a title</div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label" for="due_date">Due Date</label>
                                        <input type="date" class="form-control" name="due_date" id="due_date">
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label required">Project</label>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle w-100 form-select-button blue" type="button" id="projectDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span id="projectSelectedText">Select Project</span>
                                            </button>
                                            <ul class="dropdown-menu w-100" aria-labelledby="projectDropdown">
                                                <?php foreach ($projects as $project): ?>
                                                    <li><a class="dropdown-item" href="#" data-project-id="<?php echo htmlspecialchars($project['id']); ?>" data-project-name="<?php echo htmlspecialchars($project['name']); ?>"><?php echo htmlspecialchars($project['name']); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <input type="hidden" id="project_id" name="project_id" required>
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label">Assigned Contractor</label>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle w-100 form-select-button blue" type="button" id="contractorDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span id="contractorSelectedText">Select Contractor</span>
                                            </button>
                                            <ul class="dropdown-menu w-100" aria-labelledby="contractorDropdown">
                                                <?php foreach ($contractors as $contractor): ?>
                                                    <li><a class="dropdown-item" href="#" data-contractor-id="<?php echo htmlspecialchars($contractor['id']); ?>" data-contractor-name="<?php echo htmlspecialchars($contractor['company_name']); ?>"><?php echo htmlspecialchars($contractor['company_name']); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <input type="hidden" id="contractor_id" name="contractor_id">
                                        </div>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label required">Priority</label>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle w-100 form-select-button blue" type="button" id="priorityDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span id="prioritySelectedText">Select Priority</span>
                                            </button>
                                            <ul class="dropdown-menu w-100" aria-labelledby="priorityDropdown">
                                                <li><a class="dropdown-item" href="#" data-priority-value="low" data-priority-display="Low (5-7 business days)">Low (5-7 business days)</a></li>
                                                <li><a class="dropdown-item" href="#" data-priority-value="medium" data-priority-display="Medium (3-5 business days)">Medium (3-5 business days)</a></li>
                                                <li><a class="dropdown-item" href="#" data-priority-value="high" data-priority-display="High (1-2 business days)">High (1-2 business days)</a></li>
                                                <li><a class="dropdown-item" href="#" data-priority-value="critical" data-priority-display="Critical (Within 24 hours)">Critical (Within 24 hours)</a></li>
                                            </ul>
                                            <input type="hidden" id="priority" name="priority" required>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="create-defect__section">
                                <div class="create-defect__section-heading">
                                    <span class="create-defect__step">02</span>
                                    <div>
                                        <h3 class="create-defect__section-title">Narrative & quick snippets</h3>
                                        <p class="create-defect__section-text">Describe what you see and reuse previous notes to move faster.</p>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label required" for="description">Defect Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                                        <div class="invalid-feedback">Please provide a description</div>
                                    </div>
                                    <?php if (!empty($recentDescriptions)): ?>
                                    <div class="col-12 col-lg-6 create-defect__quickfill">
                                        <div class="dropdown mt-2">
                                            <button class="btn dropdown-toggle w-100" type="button" id="recentDescriptionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                Recent Descriptions
                                            </button>
                                            <ul class="dropdown-menu w-100" aria-labelledby="recentDescriptionsDropdown">
                                                <?php foreach ($recentDescriptions as $recentDescription): ?>
                                                    <li><a class="dropdown-item" href="#" data-description="<?php echo htmlspecialchars($recentDescription); ?>"><?php echo htmlspecialchars($recentDescription); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="create-defect__section">
                                <div class="create-defect__section-heading">
                                    <span class="create-defect__step">03</span>
                                    <div>
                                        <h3 class="create-defect__section-title">Location & floor plan</h3>
                                        <p class="create-defect__section-text">Select the plan, drop a pin, and adjust zoom so the team knows exactly where to attend.</p>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label required">Floor Plan</label>
                                        <div class="dropdown">
                                            <button class="btn btn-outline-secondary dropdown-toggle w-100 form-select-button blue" type="button" id="floorPlanDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                                <span id="floorPlanSelectedText">Select Floor Plan</span>
                                            </button>
                                            <ul class="dropdown-menu w-100" aria-labelledby="floorPlanDropdown">
                                                <?php foreach ($floorPlans as $floorPlan): ?>
                                                    <li><a class="dropdown-item" href="#" data-floor-plan-id="<?php echo htmlspecialchars($floorPlan['id']); ?>" data-floor-plan-name="<?php echo htmlspecialchars($floorPlan['floor_name']); ?>" data-image-path="<?php echo htmlspecialchars($floorPlan['image_path']); ?>" data-file-path="<?php echo htmlspecialchars($floorPlan['file_path']); ?>"><?php echo htmlspecialchars($floorPlan['floor_name']); ?></a></li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <input type="hidden" id="floor_plan_id" name="floor_plan_id" required>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <div id="floorPlanContainer" style="display:none;">
                                            <label class="form-label">Select Pin Location</label>
                                            <div class="pdf-container">
                                                <canvas id="pdfCanvas"></canvas>
                                                <div id="pinOverlay"></div>
                                                <div id="pdfLoadingOverlay">Loading floor plan...</div>
                                            </div>
                                            <input type="hidden" name="pin_x" id="pin_x" value="">
                                            <input type="hidden" name="pin_y" id="pin_y" value="">
                                            <input type="hidden" name="floor_plan_path" id="floor_plan_path" value="">
                                            <div class="create-defect__toolbar mt-3 d-flex flex-wrap gap-2">
                                                <button type="button" id="clearPinButton" class="btn btn-secondary"><i class="bx bx-target-lock me-1"></i>Clear Pin</button>
                                                <button type="button" id="zoomInButton" class="btn btn-secondary"><i class="bx bx-zoom-in"></i></button>
                                                <button type="button" id="zoomOutButton" class="btn btn-secondary"><i class="bx bx-zoom-out"></i></button>
                                                <button type="button" id="resetZoomButton" class="btn btn-secondary"><i class="bx bx-refresh"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="create-defect__section">
                                <div class="create-defect__section-heading">
                                    <span class="create-defect__step">04</span>
                                    <div>
                                        <h3 class="create-defect__section-title">Site evidence</h3>
                                        <p class="create-defect__section-text">Upload supporting images or take a live picture to document the issue clearly.</p>
                                    </div>
                                </div>
                                <div class="row g-3 align-items-start">
                                    <div class="col-12 col-lg-6">
                                        <label class="form-label required" for="imageUpload">Upload Images</label>
                                        <input type="file" class="form-control" id="imageUpload" name="images[]" accept="image/*" multiple required>
                                        <div class="invalid-feedback">Please upload at least one image</div>
                                        <p class="create-defect__hint"><i class="bx bx-info-circle me-1"></i>Attach annotated shots or captures from the field camera.</p>
                                    </div>
                                    <div class="col-12 col-lg-6">
                                        <button type="button" class="take-picture__btn" id="takePictureButton"><i class="bx bx-camera me-1"></i>Take Picture</button>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Image Gallery</label>
                                        <div id="imageGallery"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="create-defect__cta d-flex flex-column flex-md-row justify-content-end gap-3">
                                <a href="defects.php" class="btn btn-outline-light"><i class="bx bx-arrow-back me-1"></i>Cancel</a>
                                <button type="submit" class="btn btn-primary"><i class="bx bx-send me-1"></i>Create Defect</button>
                            </div>
                        </form>

                        <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">
                        <script src="https://cdn.jsdelivr.net/npm/sweetalert2/dist/sweetalert2.min.js"></script>
                        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
                        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
                        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
                        <script src="js/create_defect.js"></script>
                    </div>
                </div>
            </div>
            <div class="col-12 col-xxl-4">
                <aside class="create-defect__sidebar">
                    <h3>Quick stats</h3>
                    <ul>
                        <li><span><i class='bx bx-hard-hat'></i>Contractors</span><strong><?php echo number_format($contractorCount); ?></strong></li>
                        <li><span><i class='bx bx-detail'></i>Saved snippets</span><strong><?php echo number_format($recentSnippetCount); ?></strong></li>
                        <li><span><i class='bx bx-photo-album'></i>Uploads</span><strong>1+</strong></li>
                        <li><span><i class='bx bx-current-location'></i>Pin accuracy</span><strong>Live</strong></li>
                    </ul>
                    <p class="create-defect__hint mt-3"><i class='bx bx-bulb me-1'></i>Drop the pin before you shoot photos so contractors can stage the repair exactly where needed.</p>
                </aside>
            </div>
        </div>
    </main>

    <!-- Modals -->
    <div class="modal fade" id="loadingModal" tabindex="-1" role="dialog" aria-labelledby="loadingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Creating defect...</p>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pinWarningModal" tabindex="-1" role="dialog" aria-labelledby="pinWarningModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pinWarningModalLabel">Pin Location Required</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    Please select a location on the floor plan to place the pin.
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="drawingModal" tabindex="-1" role="dialog" aria-labelledby="drawingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="drawingModalLabel">Draw Markup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="image-preview" id="drawingContainer">
                        <canvas id="drawingCanvas" class="drawing-canvas"></canvas>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" id="clearDrawingButton">Clear Drawing</button>
                    <button type="button" class="btn btn-primary" id="saveDrawingButton">Save Drawing</button>
                </div>
            </div>
        </div>
    </div>

	<!-- Add this right before the closing </body> tag -->
<script>
/**
 * Dropdown selection fix
 * Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-03-10 21:25:38
 * Current User's Login: irlam
 */
document.addEventListener('DOMContentLoaded', function() {
    // Fix dropdown functionality
    function setupDropdownFix() {
        const dropdowns = ['projectDropdown', 'contractorDropdown', 'priorityDropdown', 'floorPlanDropdown'];
        
        dropdowns.forEach(dropdownId => {
            const dropdownItems = document.querySelectorAll(`#${dropdownId} + .dropdown-menu .dropdown-item`);
            dropdownItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    let selectedValue, selectedText;
                    const dropdownButton = document.getElementById(dropdownId);
                    let selectedTextElement;
                    
                    // Get the correct IDs based on dropdown
                    if (dropdownId === 'projectDropdown') {
                        selectedValue = this.dataset.projectId;
                        selectedText = this.dataset.projectName;
                        selectedTextElement = document.getElementById('projectSelectedText');
                        document.getElementById('project_id').value = selectedValue;
                    } else if (dropdownId === 'contractorDropdown') {
                        selectedValue = this.dataset.contractorId;
                        selectedText = this.dataset.contractorName;
                        selectedTextElement = document.getElementById('contractorSelectedText');
                        document.getElementById('contractor_id').value = selectedValue;
                    } else if (dropdownId === 'priorityDropdown') {
                        selectedValue = this.dataset.priorityValue;
                        selectedText = this.textContent;
                        selectedTextElement = document.getElementById('prioritySelectedText');
                        document.getElementById('priority').value = selectedValue;
                    } else if (dropdownId === 'floorPlanDropdown') {
                        selectedValue = this.dataset.floorPlanId;
                        selectedText = this.dataset.floorPlanName;
                        selectedTextElement = document.getElementById('floorPlanSelectedText');
                        document.getElementById('floor_plan_id').value = selectedValue;
                        
                        // Load floor plan
                        const imagePath = this.dataset.imagePath;
                        const filePath = this.dataset.filePath;
                        document.getElementById('floor_plan_path').value = imagePath;
                        document.getElementById('floorPlanContainer').style.display = 'block';
                    }
                    
                    // Update the text and styling
                    if (selectedTextElement) {
                        selectedTextElement.textContent = selectedText;
                    }
                    
                    dropdownButton.classList.add('selected', 'green');
                    dropdownButton.classList.remove('blue', 'btn-outline-secondary');
                    
                    // Close the dropdown
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownButton);
                    if (dropdown) {
                        dropdown.hide();
                    }
                });
            });
        });
    }
    
    // Fix form submission if needed
    const createDefectForm = document.getElementById('createDefectForm');
    if (createDefectForm && !createDefectForm.hasAttribute('data-event-bound')) {
        createDefectForm.setAttribute('data-event-bound', 'true');
        createDefectForm.addEventListener('submit', function(event) {
            event.preventDefault();
            
            // Show loading modal
            if (window.bootstrap && bootstrap.Modal) {
                const loadingModal = document.getElementById('loadingModal');
                if (loadingModal) {
                    const bsModal = new bootstrap.Modal(loadingModal);
                    bsModal.show();
                }
            }
            
            // Submit the form directly instead of using fetch
           // this.submit();
        });
    }
    
    // Fix the recent descriptions dropdown
const descriptionLinks = document.querySelectorAll('.dropdown-menu a[data-description]');
descriptionLinks.forEach(link => {
    // Remove any existing event listeners by cloning and replacing
    const newLink = link.cloneNode(true);
    if (link.parentNode) {
        link.parentNode.replaceChild(newLink, link);
    }
    
    // Add the event listener to the new element
    newLink.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const description = this.getAttribute('data-description');
        if (description) {
            const descTextarea = document.getElementById('description');
            const currentDescription = descTextarea.value;
            descTextarea.value = currentDescription ? currentDescription + "\n" + description : description;
        }
    });
});
    
    // Add fix for floor plan pin placement with drag functionality
    function fixPinPlacement() {
        // Wait for floor plan to load before fixing pin placement
        const fixPinInterval = setInterval(() => {
            const overlay = document.getElementById('pinOverlay');
            if (!overlay || !document.getElementById('pdfCanvas')) return;
            
            clearInterval(fixPinInterval);
            
            // Clear existing event listeners by cloning and replacing
            const newOverlay = overlay.cloneNode(true);
            if (overlay.parentNode) {
                overlay.parentNode.replaceChild(newOverlay, overlay);
            }
            
            // Variables for drag functionality
            let isDragging = false;
            let activePin = null;
            let offsetX = 0;
            let offsetY = 0;
            
            // Add click handler for pin placement
            newOverlay.addEventListener('click', function(e) {
                // Don't place a new pin if we're already dragging one
                if (isDragging) return;
                
                e.preventDefault();
                e.stopPropagation();
                
                const rect = newOverlay.getBoundingClientRect();
                const x = (e.clientX - rect.left) / rect.width;
                const y = (e.clientY - rect.top) / rect.height;
                
                // Place pin on click/tap
                const pin = placePinImmediate(x, y);
                
                // Set up the pin for potential dragging
                initializeDraggablePin(pin);
            }, {passive: false});
            
            // Add touch handler for pin placement
            newOverlay.addEventListener('touchstart', function(e) {
                // Don't place a new pin if we're already dragging one
                if (isDragging) return;
                
                e.preventDefault();
                
                if (e.touches && e.touches.length > 0) {
                    const rect = newOverlay.getBoundingClientRect();
                    const x = (e.touches[0].clientX - rect.left) / rect.width;
                    const y = (e.touches[0].clientY - rect.top) / rect.height;
                    
                    // Place pin on tap
                    const pin = placePinImmediate(x, y);
                    
                    // Set up the pin for potential dragging
                    initializeDraggablePin(pin);
                }
            }, {passive: false});
            
            // Function to initialize dragging for the pin
            function initializeDraggablePin(pin) {
                if (!pin) return;
                
                // Add drag indicator styles
                pin.style.cursor = 'move';
                
                // Add mousedown event to start dragging
                pin.addEventListener('mousedown', startDrag);
                
                // Add touchstart event for mobile
                pin.addEventListener('touchstart', startDragTouch, {passive: false});
                
                // Function to start dragging with mouse
                function startDrag(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Calculate offset for smooth dragging from current position
                    const rect = newOverlay.getBoundingClientRect();
                    const pinRect = pin.getBoundingClientRect();
                    
                    // Calculate the offset from mouse position to pin's center
                    offsetX = e.clientX - pinRect.left - (pinRect.width / 2);
                    offsetY = e.clientY - pinRect.top - (pinRect.height / 2);
                    
                    activePin = pin;
                    isDragging = true;
                    
                    // Add the document event listeners
                    document.addEventListener('mousemove', drag);
                    document.addEventListener('mouseup', stopDrag);
                }
                
                // Function to start dragging with touch
                function startDragTouch(e) {
                    e.preventDefault();
                    
                    if (!e.touches || e.touches.length === 0) return;
                    
                    // Calculate offset for smooth dragging from current position
                    const rect = newOverlay.getBoundingClientRect();
                    const pinRect = pin.getBoundingClientRect();
                    
                    // Calculate the offset from touch position to pin's center
                    offsetX = e.touches[0].clientX - pinRect.left - (pinRect.width / 2);
                    offsetY = e.touches[0].clientY - pinRect.top - (pinRect.height / 2);
                    
                    activePin = pin;
                    isDragging = true;
                    
                    // Add the document event listeners
                    document.addEventListener('touchmove', dragTouch, {passive: false});
                    document.addEventListener('touchend', stopDragTouch);
                }
                
                // Function to handle dragging with mouse
                function drag(e) {
                    if (!isDragging || !activePin) return;
                    
                    e.preventDefault();
                    
                    const rect = newOverlay.getBoundingClientRect();
                    
                    // Calculate the new position (accounting for offset)
                    const newX = (e.clientX - offsetX - rect.left) / rect.width;
                    const newY = (e.clientY - offsetY - rect.top) / rect.height;
                    
                    // Update pin position (constrained to 0-1 for both x and y)
                    updatePinPosition(
                        Math.max(0, Math.min(1, newX)), 
                        Math.max(0, Math.min(1, newY))
                    );
                }
                
                // Function to handle dragging with touch
                function dragTouch(e) {
                    if (!isDragging || !activePin || !e.touches || e.touches.length === 0) return;
                    
                    e.preventDefault();
                    
                    const rect = newOverlay.getBoundingClientRect();
                    
                    // Calculate the new position (accounting for offset)
                    const newX = (e.touches[0].clientX - offsetX - rect.left) / rect.width;
                    const newY = (e.touches[0].clientY - offsetY - rect.top) / rect.height;
                    
                    // Update pin position (constrained to 0-1 for both x and y)
                    updatePinPosition(
                        Math.max(0, Math.min(1, newX)), 
                        Math.max(0, Math.min(1, newY))
                    );
                }
                
                // Function to stop dragging with mouse
                function stopDrag(e) {
                    if (!isDragging) return;
                    
                    e.preventDefault();
                    isDragging = false;
                    activePin = null;
                    
                    // Remove the document event listeners
                    document.removeEventListener('mousemove', drag);
                    document.removeEventListener('mouseup', stopDrag);
                    
                    // Show success message for repositioned pin
                    showPinSuccess("Pin position updated");
                }
                
                // Function to stop dragging with touch
                function stopDragTouch(e) {
                    if (!isDragging) return;
                    
                    isDragging = false;
                    activePin = null;
                    
                    // Remove the document event listeners
                    document.removeEventListener('touchmove', dragTouch);
                    document.removeEventListener('touchend', stopDragTouch);
                    
                    // Show success message for repositioned pin
                    showPinSuccess("Pin position updated");
                }
            }
            
            // Function to update pin position 
            function updatePinPosition(x, y) {
                if (!activePin) return;
                
                // Update the pin's position
                activePin.style.left = (x * 100) + '%';
                activePin.style.top = (y * 100) + '%';
                
                // Update form values
                document.getElementById('pin_x').value = x;
                document.getElementById('pin_y').value = y;
            }
            
        }, 500); // Check every half second
    }

    // Function to place pin immediately without holding 
    function placePinImmediate(x, y) {
        // Clear existing pin
        const overlay = document.getElementById('pinOverlay');
        while (overlay.firstChild) {
            overlay.removeChild(overlay.firstChild);
        }
        
        // Create new pin
        const pin = document.createElement('div');
        pin.className = 'location-pin';
        pin.style.left = (x * 100) + '%';
        pin.style.top = (y * 100) + '%';
        pin.style.transform = 'translate(-50%, -100%)';
        pin.style.cursor = 'move'; // Add cursor style
        overlay.appendChild(pin);
        
        // Update form values
        document.getElementById('pin_x').value = x;
        document.getElementById('pin_y').value = y;
        
        // Show success message
        showPinSuccess("Pin placed successfully");
        
        return pin;
    }
    
    // Function to show success message
    function showPinSuccess(message) {
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                text: message,
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
        } else {
            console.log(message);
        }
    }

    // Add floor plan dropdown handler to trigger pin placement fix
    const floorPlanDropdownItems = document.querySelectorAll('#floorPlanDropdown + .dropdown-menu .dropdown-item');
    floorPlanDropdownItems.forEach(item => {
        item.addEventListener('click', function() {
            // Give time for floor plan to load
            setTimeout(fixPinPlacement, 1000);
        });
    });
    
    // Run the dropdown fix
    setupDropdownFix();
});
</script>
<script src="js/floor_plan_integration.js"></script>	
</body>
</html>	