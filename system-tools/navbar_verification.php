<?php
/**
 * Navbar Verification Tool
 * 
 * This script verifies that all navbar items point to valid, functional pages
 * and generates a comprehensive report of all available functions.
 * 
 * Created: 2025-11-04
 * Purpose: Verify navbar integrity and document all system functions
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: /login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/navbar.php';

$database = new Database();
$db = $database->getConnection();

$pageTitle = 'Navbar Verification Tool';

// Function to check if a file exists and is accessible
function checkFileExists($url) {
    $path = $_SERVER['DOCUMENT_ROOT'] . $url;
    
    // Handle directory URLs
    if (substr($url, -1) === '/') {
        $indexPath = $path . 'index.php';
        if (file_exists($indexPath)) {
            return ['exists' => true, 'path' => $indexPath, 'type' => 'directory_index'];
        }
        $htmlPath = $path . 'index.html';
        if (file_exists($htmlPath)) {
            return ['exists' => true, 'path' => $htmlPath, 'type' => 'directory_index'];
        }
        return ['exists' => false, 'path' => $path, 'type' => 'directory'];
    }
    
    if (file_exists($path)) {
        return ['exists' => true, 'path' => $path, 'type' => 'file'];
    }
    
    return ['exists' => false, 'path' => $path, 'type' => 'missing'];
}

// Function to extract navbar items for all user types
function getAllNavbarItems() {
    $userTypes = ['admin', 'manager', 'contractor', 'inspector', 'viewer', 'client'];
    $allItems = [];
    
    foreach ($userTypes as $userType) {
        $reflection = new ReflectionClass('Navbar');
        $method = $reflection->getMethod('getNavbarItems');
        $method->setAccessible(true);
        
        // Create a mock Navbar instance
        global $db;
        $navbar = new Navbar($db, 1, 'test_user');
        
        // Set the userRole property using reflection
        $property = $reflection->getProperty('userRole');
        $property->setAccessible(true);
        $property->setValue($navbar, $userType);
        
        $items = $method->invoke($navbar);
        $allItems[$userType] = $items;
    }
    
    return $allItems;
}

// Function to flatten navbar items into a list of URLs
function extractUrls($items, $userType, &$urlList) {
    foreach ($items as $item) {
        if (isset($item['url'])) {
            $urlList[] = [
                'user_type' => $userType,
                'label' => $item['label'],
                'url' => $item['url'],
                'parent' => null
            ];
        }
        
        if (isset($item['dropdown']) && is_array($item['dropdown'])) {
            foreach ($item['dropdown'] as $dropdownItem) {
                if (isset($dropdownItem['url'])) {
                    $urlList[] = [
                        'user_type' => $userType,
                        'label' => $dropdownItem['label'],
                        'url' => $dropdownItem['url'],
                        'parent' => $item['label']
                    ];
                }
            }
        }
    }
}

// Get all navbar items
$allNavbarItems = getAllNavbarItems();

// Extract all unique URLs
$allUrls = [];
foreach ($allNavbarItems as $userType => $items) {
    extractUrls($items, $userType, $allUrls);
}

// Remove duplicates based on URL
$uniqueUrls = [];
$seenUrls = [];
foreach ($allUrls as $urlData) {
    if (!in_array($urlData['url'], $seenUrls)) {
        $seenUrls[] = $urlData['url'];
        $uniqueUrls[] = $urlData;
    }
}

// Sort by URL
usort($uniqueUrls, function($a, $b) {
    return strcmp($a['url'], $b['url']);
});

// Check each URL
$verificationResults = [];
foreach ($uniqueUrls as $urlData) {
    $checkResult = checkFileExists($urlData['url']);
    $verificationResults[] = array_merge($urlData, $checkResult);
}

// Count results
$totalUrls = count($verificationResults);
$existingUrls = count(array_filter($verificationResults, function($r) { return $r['exists']; }));
$missingUrls = $totalUrls - $existingUrls;

// Initialize navbar
$navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css" rel="stylesheet">
</head>
<body class="tool-body has-app-navbar" data-bs-theme="dark">
    <?php $navbar->render(); ?>

    <main class="tool-page container-xl py-4">
        <header class="tool-header mb-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="h3 mb-2">
                        <i class='bx bx-check-shield'></i> Navbar Verification Tool
                    </h1>
                    <p class="text-muted mb-0">
                        Comprehensive verification of all navbar menu items and system functions
                    </p>
                </div>
                <div class="text-end">
                    <a href="/system-tools/system_health.php" class="btn btn-sm btn-outline-light">
                        <i class='bx bx-arrow-back'></i> Back to System Tools
                    </a>
                </div>
            </div>
        </header>

        <!-- Summary Cards -->
        <section class="mb-4">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="fs-2 text-primary">
                                    <i class='bx bx-list-ul'></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Total URLs</div>
                                    <div class="h4 mb-0"><?php echo $totalUrls; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="fs-2 text-success">
                                    <i class='bx bx-check-circle'></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Valid URLs</div>
                                    <div class="h4 mb-0"><?php echo $existingUrls; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-3">
                                <div class="fs-2 text-danger">
                                    <i class='bx bx-error-circle'></i>
                                </div>
                                <div>
                                    <div class="text-muted small">Missing URLs</div>
                                    <div class="h4 mb-0"><?php echo $missingUrls; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Verification Results -->
        <section class="mb-4">
            <div class="card border-0">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="h5 mb-0">URL Verification Results</h2>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-light" onclick="filterTable('all')">All</button>
                        <button type="button" class="btn btn-outline-light" onclick="filterTable('valid')">Valid</button>
                        <button type="button" class="btn btn-outline-light" onclick="filterTable('missing')">Missing</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0" id="verificationTable">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Status</th>
                                    <th>URL</th>
                                    <th>Label</th>
                                    <th>Parent Menu</th>
                                    <th>User Type</th>
                                    <th>File Path</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($verificationResults as $result): ?>
                                <tr class="verification-row" data-status="<?php echo $result['exists'] ? 'valid' : 'missing'; ?>">
                                    <td class="text-center">
                                        <?php if ($result['exists']): ?>
                                            <i class='bx bx-check-circle text-success fs-4'></i>
                                        <?php else: ?>
                                            <i class='bx bx-error-circle text-danger fs-4'></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <code><?php echo htmlspecialchars($result['url']); ?></code>
                                    </td>
                                    <td><?php echo htmlspecialchars($result['label']); ?></td>
                                    <td>
                                        <?php if ($result['parent']): ?>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($result['parent']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($result['user_type']); ?></span>
                                    </td>
                                    <td>
                                        <small class="text-muted font-monospace">
                                            <?php echo htmlspecialchars(str_replace($_SERVER['DOCUMENT_ROOT'], '', $result['path'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <!-- Function List by User Type -->
        <section class="mb-4">
            <div class="card border-0">
                <div class="card-header">
                    <h2 class="h5 mb-0">Functions by User Type</h2>
                </div>
                <div class="card-body">
                    <?php foreach ($allNavbarItems as $userType => $items): ?>
                    <div class="mb-4">
                        <h3 class="h6 text-uppercase mb-3">
                            <i class='bx bx-user'></i> <?php echo ucfirst($userType); ?> Role
                        </h3>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($items as $item): ?>
                            <li class="list-group-item bg-transparent border-secondary">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['label']); ?></strong>
                                        <?php if (isset($item['url'])): ?>
                                            <br><small class="text-muted"><code><?php echo htmlspecialchars($item['url']); ?></code></small>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (isset($item['url'])): ?>
                                        <a href="<?php echo htmlspecialchars($item['url']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class='bx bx-link-external'></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <?php if (isset($item['dropdown']) && is_array($item['dropdown'])): ?>
                                <ul class="list-group list-group-flush mt-2 ms-4">
                                    <?php foreach ($item['dropdown'] as $dropdownItem): ?>
                                        <?php if (isset($dropdownItem['url'])): ?>
                                        <li class="list-group-item bg-transparent border-0 py-1">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <small>→ <?php echo htmlspecialchars($dropdownItem['label']); ?></small>
                                                    <br><small class="text-muted"><code><?php echo htmlspecialchars($dropdownItem['url']); ?></code></small>
                                                </div>
                                                <a href="<?php echo htmlspecialchars($dropdownItem['url']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                    <i class='bx bx-link-external'></i>
                                                </a>
                                            </div>
                                        </li>
                                        <?php elseif (($dropdownItem['label'] ?? '') === '---divider---'): ?>
                                        <li class="list-group-item bg-transparent border-0 py-1">
                                            <hr class="my-1">
                                        </li>
                                        <?php elseif (($dropdownItem['type'] ?? '') === 'header'): ?>
                                        <li class="list-group-item bg-transparent border-0 py-1">
                                            <small class="text-uppercase text-muted fw-bold"><?php echo htmlspecialchars($dropdownItem['label']); ?></small>
                                        </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Export Options -->
        <section class="mb-4">
            <div class="card border-0">
                <div class="card-header">
                    <h2 class="h5 mb-0">Export Options</h2>
                </div>
                <div class="card-body">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" onclick="exportToCSV()">
                            <i class='bx bx-download'></i> Export to CSV
                        </button>
                        <button type="button" class="btn btn-outline-light" onclick="window.print()">
                            <i class='bx bx-printer'></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterTable(status) {
            const rows = document.querySelectorAll('.verification-row');
            rows.forEach(row => {
                if (status === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.dataset.status === status ? '' : 'none';
                }
            });
        }

        function exportToCSV() {
            const results = <?php echo json_encode($verificationResults); ?>;
            let csv = 'Status,URL,Label,Parent Menu,User Type,File Path\n';
            
            results.forEach(result => {
                const status = result.exists ? 'Valid' : 'Missing';
                const parent = result.parent || '';
                const path = result.path.replace(/"/g, '""');
                csv += `"${status}","${result.url}","${result.label}","${parent}","${result.user_type}","${path}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `navbar_verification_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
