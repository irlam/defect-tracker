<?php
// all_defects.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/all_defects.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

// Initialize variables
$pageTitle = 'All Defects';
$currentUser = $_SESSION['username'];

// Define INCLUDED constant for navbar security
define('INCLUDED', true);

require_once 'includes/functions.php';
require_once 'config/database.php';
require_once 'includes/navbar.php';
require_once 'includes/all_defects_PDF_converter.php';

try {
    $database = new Database();
    $db = $database->getConnection();

    // Fetch projects
    $projectsQuery = "SELECT id, name FROM projects ORDER BY name ASC";
    $projectsStmt = $db->prepare($projectsQuery);
    $projectsStmt->execute();
    $projects = $projectsStmt->fetchAll(PDO::FETCH_ASSOC);

    $pdfConverter = new AllDefectsPdfConverter();

    // Initialize selected project and floor plan IDs
    $selectedProjectId = isset($_GET['project_id']) ? $_GET['project_id'] : null;
    $selectedFloorPlanId = isset($_GET['floor_plan_id']) ? $_GET['floor_plan_id'] : null;
    $selectedDefectId = isset($_GET['defect_id']) ? $_GET['defect_id'] : null;

    // Fetch floor plans for the selected project
    $floorPlans = [];
    if ($selectedProjectId) {
        $floorPlansQuery = "SELECT id, level, image_path FROM floor_plans 
                           WHERE project_id = :project_id 
                           ORDER BY CASE 
                               WHEN level = 'Basement' THEN 0 
                               ELSE CAST(REGEXP_REPLACE(level, '[^0-9]', '') AS SIGNED) 
                           END";
        $floorPlansStmt = $db->prepare($floorPlansQuery);
        $floorPlansStmt->bindParam(':project_id', $selectedProjectId);
        $floorPlansStmt->execute();
        $floorPlans = $floorPlansStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch defects for the selected floor plan
    $defects = [];
    if ($selectedFloorPlanId) {
        $defectsQuery = "SELECT
                            d.id AS defect_id,
                            d.title,
                            d.description,
                            d.status,
                            d.priority,
                            d.pin_x,
                            d.pin_y,
                            d.created_at,
                            c.company_name,
                            c.logo AS contractor_logo,
                            c.id AS contractor_id,
                            u.username AS reported_by
                         FROM defects d
                         LEFT JOIN contractors c ON d.contractor_id = c.id
                         LEFT JOIN users u ON d.reported_by = u.id
                         WHERE d.floor_plan_id = :floor_plan_id 
                         AND d.deleted_at IS NULL
                         ORDER BY d.created_at DESC";
        $defectsStmt = $db->prepare($defectsQuery);
        $defectsStmt->bindParam(':floor_plan_id', $selectedFloorPlanId);
        $defectsStmt->execute();
        $defects = $defectsStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fetch selected defect details
    $selectedDefect = null;
    if ($selectedDefectId) {
        $selectedDefectQuery = "SELECT
                                    d.*,
                                    c.company_name,
                                    c.logo AS contractor_logo,
                                    c.id AS contractor_id,
                                    u.username AS reported_by,
                                    u2.username AS assigned_to_user
                                 FROM defects d
                                 LEFT JOIN contractors c ON d.contractor_id = c.id
                                 LEFT JOIN users u ON d.reported_by = u.id
                                 LEFT JOIN users u2 ON d.assigned_to = u2.id
                                 WHERE d.id = :defect_id AND d.deleted_at IS NULL";
        $selectedDefectStmt = $db->prepare($selectedDefectQuery);
        $selectedDefectStmt->bindParam(':defect_id', $selectedDefectId);
        $selectedDefectStmt->execute();
        $selectedDefect = $selectedDefectStmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log("All Defects Error: " . $e->getMessage());
    $error_message = "An error occurred while loading defects: " . $e->getMessage();
}

// Helper functions
function formatDefectTitle($title) {
    return strlen($title) > 50 ? substr($title, 0, 50) . '...' : $title;
}

function getBaseUrl() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . "://" . $host;
}

function formatDate($date) {
    return date('d M Y H:i', strtotime($date));
}

function getImageUrl($imagePath) {
    // Base URL for the images
    $baseUrl = 'https://mcgoff.defecttracker.uk';
    
    // If the path already starts with http/https, return it as is
    if (strpos($imagePath, 'http') === 0) {
        return $imagePath;
    }
    
    // Ensure the path starts with a forward slash
    if (strpos($imagePath, '/') !== 0) {
        $imagePath = '/' . $imagePath;
    }
    
    return $baseUrl . $imagePath;
}

// Status and priority colors
$statusColors = [
    'open' => 'danger',
    'in_progress' => 'warning',
    'completed' => 'info',
    'verified' => 'primary',
    'rejected' => 'secondary',
    'accepted' => 'success'
];

$priorityColors = [
    'low' => 'success',
    'medium' => 'warning',
    'high' => 'danger',
    'critical' => 'dark'
];
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <style>
        :root {
            --primary-color: #007bff;
            --hover-color: #0056b3;
            --pin-size: 24px;
            --tooltip-bg: rgba(255, 255, 255, 0.95);
        }
        .main-content {
    padding: 20px;
    margin: 0 auto; /* This centers the content horizontally */
    max-width: 1400px; /* Set a maximum width for larger screens */
    transition: all 0.3s;
}
@media (max-width: 768px) {
    .main-content {
        padding: 15px; /* Slightly less padding on mobile */
    }
}
        .floor-plan-container {
            position: relative;
            width: 100%;
            max-width: 1200px;
            margin: 20px auto;
            border: 1px solid #ddd;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .floor-plan-image {
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }
        .defect-pin {
            position: absolute;
            width: var(--pin-size);
            height: var(--pin-size);
            transform: translate(-50%, -50%);
            cursor: pointer;
            z-index: 100;
            transition: transform 0.2s ease;
        }
        .defect-pin::before {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: url('/uploads/images/location-pin.svg');
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            transition: transform 0.2s ease;
        }
        .defect-pin:hover {
            transform: translate(-50%, -50%) scale(1.1);
            z-index: 101;
        }
        .defect-tooltip {
            position: absolute;
            background: var(--tooltip-bg);
            border: 1px solid #ddd;
            padding: 12px;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
            z-index: 102;
            min-width: 250px;
            max-width: 350px;
            display: none;
            top: calc(100% + 10px);
            left: 50%;
            transform: translateX(-50%);
            font-size: 0.9rem;
            backdrop-filter: blur(5px);
        }
        .defect-tooltip::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-width: 0 8px 8px 8px;
            border-style: solid;
            border-color: transparent transparent #ddd transparent;
        }
        .defect-pin:hover .defect-tooltip {
            display: block;
        }
        .floor-plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }
        .floor-plan-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        .floor-plan-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .floor-plan-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(0,123,255,0.05);
        }
        .defect-table {
            margin-top: 20px;
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        .defect-table th {
            background-color: #f8f9fa;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .defect-table tbody tr {
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        .defect-table tbody tr:hover {
            background-color: rgba(0,123,255,0.05);
        }
        .defect-table tr.selected {
            background-color: rgba(0,123,255,0.1);
        }
        .contractor-logo {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 4px;
        }
        .status-badge {
            padding: 0.25em 0.6em;
            font-size: 0.85em;
            font-weight: 500;
            border-radius: 12px;
            text-transform: capitalize;
        }
        .priority-badge {
            padding: 0.25em 0.6em;
            font-size: 0.85em;
            font-weight: 500;
            border-radius: 12px;
            text-transform: capitalize;
        }
        .defect-details {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-top: 20px;
            padding: 20px;
        }
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            display: none;
        }
        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
	<?php
$navbar->render();
?>
	<br><br><br><br><br>
    <div class="loading-overlay">
        <div class="spinner"></div>
    </div>
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header Section -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
        <p style="color: black;">
            This page allows you to view all defects reported across projects. Select a project and floor plan to see defects visually on the floor plan image and in a detailed list below. Click on pins or table rows to highlight specific defects.
        </p>
    </div>
</div>
<?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($error_message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
            <!-- Project Selection -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <label for="project_id" class="form-label">Select Project</label>
                            <select class="form-select" id="project_id" name="project_id" onchange="this.form.submit()">
                                <option value="">Choose...</option>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php echo htmlspecialchars($project['id']); ?>" 
                                            <?php if ($selectedProjectId == $project['id']) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($project['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>
            <?php if ($selectedProjectId): ?>
                <!-- Floor Plans Grid -->
                <div class="floor-plans-grid">
                    <?php foreach ($floorPlans as $floorPlan): ?>
                        <div class="floor-plan-card <?php echo ($selectedFloorPlanId == $floorPlan['id']) ? 'selected' : ''; ?>"
                             onclick="window.location.href='?project_id=<?php echo htmlspecialchars($selectedProjectId); ?>&floor_plan_id=<?php echo htmlspecialchars($floorPlan['id']); ?>'">
                            <h5 class="mb-0">Level: <?php echo htmlspecialchars($floorPlan['level']); ?></h5>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($selectedFloorPlanId): ?>
                    <!-- Floor Plan Display -->
                    <div class="floor-plan-container">
                        <?php
                        $selectedFloorPlan = array_filter($floorPlans, function($fp) use ($selectedFloorPlanId) {
                            return $fp['id'] == $selectedFloorPlanId;
                        });
                        $selectedFloorPlan = reset($selectedFloorPlan);
                        ?>
                        <img id="floorPlanImage" 
                             src="<?php echo htmlspecialchars(getImageUrl($selectedFloorPlan['image_path'])); ?>" 
                             alt="Floor Plan" 
                             class="floor-plan-image">
                        <!-- Defect Pins (using data attributes for ratios) -->
                        <?php foreach ($defects as $defect): ?>
                            <?php if (isset($defect['pin_x']) && isset($defect['pin_y'])): ?>
                                <div class="defect-pin" 
                                     data-defect-id="<?php echo htmlspecialchars($defect['defect_id']); ?>" 
                                     data-pin-x="<?php echo htmlspecialchars($defect['pin_x']); ?>" 
                                     data-pin-y="<?php echo htmlspecialchars($defect['pin_y']); ?>">
                                    <div class="defect-tooltip">
                                        <h6><?php echo htmlspecialchars($defect['title']); ?></h6>
                                        <p class="mb-1">
                                            <span class="status-badge bg-<?php echo $statusColors[$defect['status']]; ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $defect['status'])); ?>
                                            </span>
                                            <span class="priority-badge bg-<?php echo $priorityColors[$defect['priority']]; ?>">
                                                <?php echo htmlspecialchars($defect['priority']); ?>
                                            </span>
                                        </p>
                                        <small class="text-muted">
                                            Reported by: <?php echo htmlspecialchars($defect['reported_by']); ?><br>
                                            Created: <?php echo formatDate($defect['created_at']); ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <!-- Defects Table -->
                    <div class="table-responsive mt-4">
                        <table class="table table-hover defect-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Contractor</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($defects as $defect): ?>
                                    <tr class="<?php echo ($selectedDefectId == $defect['defect_id']) ? 'selected' : ''; ?>"
                                        onclick="highlightDefect('<?php echo htmlspecialchars($defect['defect_id']); ?>')">
                                        <td><?php echo htmlspecialchars($defect['defect_id']); ?></td>
                                        <td><?php echo htmlspecialchars(formatDefectTitle($defect['title'])); ?></td>
                                        <td>
                                            <span class="status-badge bg-<?php echo $statusColors[$defect['status']]; ?>">
                                                <?php echo htmlspecialchars(str_replace('_', ' ', $defect['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="priority-badge bg-<?php echo $priorityColors[$defect['priority']]; ?>">
                                                <?php echo htmlspecialchars($defect['priority']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($defect['contractor_logo']): ?>
                                                <img src="<?php echo getBaseUrl() . '/uploads/logos/' . htmlspecialchars($defect['contractor_logo']); ?>" 
                                                     alt="Contractor Logo" 
                                                     class="contractor-logo me-2">
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($defect['company_name'] ?? 'Not Assigned'); ?>
                                        </td>
                                        <td><?php echo formatDate($defect['created_at']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to highlight a selected defect from the table or pin click.
        function highlightDefect(defectId) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('defect_id', defectId);
            window.history.pushState({}, '', `${window.location.pathname}?${urlParams.toString()}`);
        
            document.querySelectorAll('.defect-pin').forEach(pin => {
                if (pin.dataset.defectId === defectId) {
                    pin.style.zIndex = '102';
                    pin.querySelector('.defect-tooltip').style.display = 'block';
                } else {
                    pin.style.zIndex = '100';
                    pin.querySelector('.defect-tooltip').style.display = 'none';
                }
            });
        
            document.querySelectorAll('.defect-table tbody tr').forEach(row => {
                row.classList.remove('selected');
                if (row.querySelector('td').textContent === defectId) {
                    row.classList.add('selected');
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
        }
        
        // Missing piece: Adjust pin positions based on the resized floor plan image.
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const selectedDefectId = urlParams.get('defect_id');
            if (selectedDefectId) {
                highlightDefect(selectedDefectId);
            }
        
            // Initialize loading overlay on navigation.
            const loadingOverlay = document.querySelector('.loading-overlay');
            document.querySelectorAll('a, form').forEach(element => {
                element.addEventListener('click', () => {
                    loadingOverlay.style.display = 'flex';
                });
            });
            window.addEventListener('load', () => {
                loadingOverlay.style.display = 'none';
            });
        
            // Adjust defect pin positions relative to the floor plan image.
            const floorPlanImage = document.getElementById('floorPlanImage');
            const defectPins = document.querySelectorAll('.defect-pin');
        
            function adjustPinPositions() {
                const imgWidth = floorPlanImage.clientWidth;
                const imgHeight = floorPlanImage.clientHeight;
                defectPins.forEach(pin => {
                    const pinRatioX = parseFloat(pin.getAttribute('data-pin-x'));
                    const pinRatioY = parseFloat(pin.getAttribute('data-pin-y'));
                    pin.style.left = (imgWidth * pinRatioX) + 'px';
                    pin.style.top = (imgHeight * pinRatioY) + 'px';
                });
            }
        
            // Adjust positions when the image is loaded and on window resize.
            if (floorPlanImage.complete) {
                adjustPinPositions();
            } else {
                floorPlanImage.onload = adjustPinPositions;
            }
            window.addEventListener('resize', adjustPinPositions);
        });
    </script>
</body>
</html>