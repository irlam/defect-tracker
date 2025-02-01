<?php
// add_logo.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-25 15:28:42
// Current User's Login: irlam

// Include required files
require_once 'includes/init.php';
require_once 'includes/logo_functions.php';

// Initialize LogoManager
$logoManager = new LogoManager();

// Check permissions before any output
if (!$logoManager->checkPermissions()) {
    header('Location: index.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_FILES['logo'])) {
            $type = $_POST['logo_type'] ?? 'company';
            $contractorId = ($type === 'contractor') ? ($_POST['contractor_id'] ?? null) : null;
            
            $logoManager->uploadLogo($_FILES['logo'], $type, $contractorId);
            $message = 'Logo uploaded successfully!';
        }

        // Handle logo deletion
        if (isset($_POST['delete_type'])) {
            $type = $_POST['delete_type'];
            $contractorId = ($type === 'contractor') ? ($_POST['contractor_id'] ?? null) : null;
            if ($logoManager->deleteLogo($type, $contractorId)) {
                $message = 'Logo deleted successfully!';
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get contractors list for dropdown
$contractors = [];
$sql = "SELECT id, company_name FROM contractors ORDER BY company_name";
$result = $db->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $contractors[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logo Management - Defect Tracker</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/boxicons.min.css" rel="stylesheet">
    <style>
        .logo-preview {
            max-height: 100px;
            width: auto;
            object-fit: contain;
        }
        .logo-card {
            height: 100%;
        }
        .logo-container {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Logo Management</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <!-- Upload Form -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upload New Logo</h5>
                    </div>
                    <div class="card-body">
                        <form action="" method="post" enctype="multipart/form-data" id="logoForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="logo_type" class="form-label">Logo Type</label>
                                        <select class="form-select" id="logo_type" name="logo_type" onchange="toggleContractorSelect()">
                                            <option value="company">Company Logo</option>
                                            <option value="contractor">Contractor Logo</option>
                                        </select>
                                    </div>

                                    <div class="mb-3" id="contractor_select" style="display: none;">
                                        <label for="contractor_id" class="form-label">Select Contractor</label>
                                        <select class="form-select" name="contractor_id" id="contractor_id">
                                            <option value="">Select a contractor...</option>
                                            <?php foreach ($contractors as $contractor): ?>
                                                <option value="<?php echo $contractor['id']; ?>">
                                                    <?php echo htmlspecialchars($contractor['company_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="logo" class="form-label">Upload Logo</label>
                                        <input type="file" class="form-control" id="logo" name="logo" accept="image/*" required>
                                        <div class="form-text">Maximum file size: 5MB. Accepted formats: JPG, PNG, GIF</div>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Upload Logo</button>
                        </form>
                    </div>
                </div>

                <!-- Current Logos Display -->
                <h3 class="mb-3">Current Logos</h3>
                
                <!-- Company Logo Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Company Logo</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $companyLogo = $logoManager->getCompanyLogo();
                        if ($companyLogo && file_exists($companyLogo)):
                        ?>
                            <div class="logo-container">
                                <img src="<?php echo $companyLogo; ?>" alt="Company Logo" class="logo-preview">
                            </div>
                            <form action="" method="post">
                                <input type="hidden" name="delete_type" value="company">
                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this logo?')">
                                    <i class='bx bx-trash'></i> Delete Logo
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="text-muted">No company logo uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Contractor Logos -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Contractor Logos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($contractors as $contractor):
                                $contractorLogo = $logoManager->getContractorLogo($contractor['id']);
                            ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card logo-card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($contractor['company_name']); ?></h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="logo-container">
                                                <?php if ($contractorLogo && file_exists($contractorLogo)): ?>
                                                    <img src="<?php echo $contractorLogo; ?>" alt="<?php echo htmlspecialchars($contractor['company_name']); ?> Logo" class="logo-preview">
                                                <?php else: ?>
                                                    <span class="text-muted">No logo uploaded</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($contractorLogo && file_exists($contractorLogo)): ?>
                                                <form action="" method="post">
                                                    <input type="hidden" name="delete_type" value="contractor">
                                                    <input type="hidden" name="contractor_id" value="<?php echo $contractor['id']; ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm w-100" onclick="return confirm('Are you sure you want to delete this logo?')">
                                                        <i class='bx bx-trash'></i> Delete Logo
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function toggleContractorSelect() {
            const logoType = document.getElementById('logo_type').value;
            const contractorSelect = document.getElementById('contractor_select');
            const contractorId = document.getElementById('contractor_id');
            
            contractorSelect.style.display = logoType === 'contractor' ? 'block' : 'none';
            contractorId.required = logoType === 'contractor';
        }

        // Form validation
        document.getElementById('logoForm').onsubmit = function(e) {
            const logoType = document.getElementById('logo_type').value;
            const contractorId = document.getElementById('contractor_id');
            
            if (logoType === 'contractor' && !contractorId.value) {
                alert('Please select a contractor');
                e.preventDefault();
                return false;
            }
            return true;
        };
    </script>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>