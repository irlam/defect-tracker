<?php
// visualize_defects.php

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/visualize_defects.log');

// Database class for establishing a connection
class Database {
    // Database configuration
    private $host = "localhost";
    private $db_name = "dvntrack_defect-manager";
    private $username = "dvntrack_defect-manager";  // Change this to your MySQL username
    private $password = "^cHMcJseC$%S";      // Change this to your MySQL password
    private $conn = null;

    // Get database connection
    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }
        return $this->conn;
    }
}

// Global configuration settings
define('BASE_URL', 'https://mcgoff.defecttracker.uk/'); // Change this to your domain
define('UPLOAD_PATH', $_SERVER['DOCUMENT_ROOT'] . '/uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('EMAIL_FROM', 'noreply@your-domain.com');
define('ITEMS_PER_PAGE', 10);

// Time zone setting
date_default_timezone_set('UTC');

// Create a new Database instance and get the connection
$database = new Database();
$conn = $database->getConnection();

// Get selected project and floor plan from request (for simplicity, using hardcoded values)
$selected_project_id = 1;
$selected_floor_plan_id = 1;

// Fetch floorplan image and defects with their pin locations
$sql = "
    SELECT f.file_path AS floorplan_image, d.id AS defect_id, d.title, d.description, d.pin_x, d.pin_y
    FROM floor_plans f
    JOIN defects d ON f.id = d.floor_plan_id
    WHERE f.project_id = :project_id AND f.id = :floor_plan_id AND d.status IN ('open', 'in_progress', 'completed', 'verified', 'rejected', 'accepted')
";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':project_id', $selected_project_id);
$stmt->bindParam(':floor_plan_id', $selected_floor_plan_id);
$stmt->execute();

$floorplan_image = '';
$defects = [];

if ($stmt->rowCount() > 0) {
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!$floorplan_image) {
            $floorplan_image = $row['floorplan_image'];
        }
        $defects[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defect Pins on Floorplan</title>
    <style>
        .floorplan {
            position: relative;
            display: inline-block;
        }
        .pin {
            position: absolute;
            width: 20px;
            height: 20px;
            background-color: red;
            border-radius: 50%;
            cursor: pointer;
        }
        .pin:hover::after {
            content: attr(data-title);
            position: absolute;
            top: -25px;
            left: 25px;
            background-color: white;
            border: 1px solid black;
            padding: 5px;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <div class="floorplan">
        <?php if (!empty($floorplan_image)): ?>
            <img id="floorplan-image" src="/uploads/floor_plan_images/<?php echo htmlspecialchars($floorplan_image); ?>" alt="Floorplan" style="max-width: 100%;">
            <?php foreach ($defects as $defect): ?>
                <div class="pin" style="left: <?php echo htmlspecialchars($defect['pin_x']); ?>px; top: <?php echo htmlspecialchars($defect['pin_y']); ?>px;" data-title="<?php echo htmlspecialchars($defect['title'] . ': ' . $defect['description']); ?>"></div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No floorplan image available.</p>
        <?php endif; ?>
    </div>
</body>
</html>