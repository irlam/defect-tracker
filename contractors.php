<?php
// contractors.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-02-06 18:26:12
// Current User's Login: irlam

// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/navbar.php';

$pageTitle = 'Contractor HQ';
$success_message = '';
$error_message = '';
$currentDateTime = date('Y-m-d H:i:s');
$currentUser = $_SESSION['user_id'];

$db = null;
$navbar = null;
$contractors = [];

try {
    $database = new Database();
    $db = $database->getConnection();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    try {
        $navbar = new Navbar($db, $_SESSION['user_id'], $_SESSION['username']);
    } catch (Exception $navbarException) {
        error_log("Navbar error in contractors.php: " . $navbarException->getMessage());
    }

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'create_contractor':
                    // Collect contractor data from the form
                    $company_name = trim($_POST['company_name']);
                    $contact_name = trim($_POST['contact_name']);
                    $email = trim($_POST['email']);
                    $phone = trim($_POST['phone']);
                    $trade = trim($_POST['trade']);
                    $address_line1 = trim($_POST['address_line1']);
                    $address_line2 = trim($_POST['address_line2']);
                    $city = trim($_POST['city']);
                    $county = trim($_POST['county']);
                    $postcode = trim($_POST['postcode']);
                    $vat_number = trim($_POST['vat_number']);
                    $company_number = trim($_POST['company_number']);
                    $insurance_info = trim($_POST['insurance_info']);
                    $utr_number = trim($_POST['utr_number']);
                    $notes = trim($_POST['notes']);
                    $status = $_POST['status'];
                    $license_number = trim($_POST['license_number']); // Retrieve license number

                    // Handle logo upload
                    $logo = '';
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/logos/'; // Directory to store logos
                        $fileName = uniqid() . '_' . basename($_FILES['logo']['name']); // Generate unique filename
                        $targetPath = $uploadDir . $fileName; // Full path to the uploaded file

                        // Move the uploaded file to the target directory
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                            $logo = $fileName; // Save filename to the database
                        } else {
                            $error_message = "Failed to upload logo.";
                        }
                    }

                    // Prepare and execute the SQL query to insert contractor data
                    $stmt = $db->prepare("
                        INSERT INTO contractors (
                            company_name, contact_name, email, phone, trade, address_line1, address_line2,
                            city, county, postcode, vat_number, company_number, insurance_info, utr_number,
                            notes, status, created_by, updated_by, logo, created_at, updated_at, license_number
                        ) VALUES (
                            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?
                        )
                    ");

                    $params = [
                        $company_name, $contact_name, $email, $phone, $trade, $address_line1, $address_line2,
                        $city, $county, $postcode, $vat_number, $company_number, $insurance_info, $utr_number,
                        $notes, $status, $currentUser, $currentUser, $logo, $license_number
                    ];

                    try {
                        $stmt->execute($params);
                        $success_message = "Contractor created successfully";
                    } catch (PDOException $e) {
                        error_log("Error creating contractor: " . $e->getMessage());
                        $error_message = "Error creating contractor. Please check the logs for details.";
                    }
                    break;

                case 'update_contractor':
                    // Collect contractor data from the form
                    $contractor_id = (int)$_POST['contractor_id'];
                    $company_name = trim($_POST['company_name']);
                    $contact_name = trim($_POST['contact_name']);
                    $email = trim($_POST['email']);
                    $phone = trim($_POST['phone']);
                    $trade = trim($_POST['trade']);
                    $address_line1 = trim($_POST['address_line1']);
                    $address_line2 = trim($_POST['address_line2']);
                    $city = trim($_POST['city']);
                    $county = trim($_POST['county']);
                    $postcode = trim($_POST['postcode']);
                    $vat_number = trim($_POST['vat_number']);
                    $company_number = trim($_POST['company_number']);
                    $insurance_info = trim($_POST['insurance_info']);
                    $utr_number = trim($_POST['utr_number']);
                    $notes = trim($_POST['notes']);
                    $status = $_POST['status'];
                    $license_number = trim($_POST['license_number']); // Retrieve license number

                    // Handle logo upload
                    $logo = $_POST['old_logo']; // Keep old logo if no new logo is uploaded
                    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                        $uploadDir = 'uploads/logos/'; // Directory to store logos
                        $fileName = uniqid() . '_' . basename($_FILES['logo']['name']); // Generate unique filename
                        $targetPath = $uploadDir . $fileName; // Full path to the uploaded file

                        // Move the uploaded file to the target directory
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $targetPath)) {
                            // Delete old logo if it exists
                            if (!empty($_POST['old_logo'])) {
                                $oldLogoPath = $uploadDir . $_POST['old_logo'];
                                if (file_exists($oldLogoPath)) {
                                    unlink($oldLogoPath);
                                }
                            }
                            $logo = $fileName; // Save filename to the database
                        } else {
                            $error_message = "Failed to upload logo.";
                        }
                    }

                    // Prepare and execute the SQL query to update contractor data
                    $stmt = $db->prepare("
                        UPDATE contractors 
                        SET 
                            company_name = ?, contact_name = ?, email = ?, phone = ?, trade = ?, 
                            address_line1 = ?, address_line2 = ?, city = ?, county = ?, postcode = ?, 
                            vat_number = ?, company_number = ?, insurance_info = ?, utr_number = ?, 
                            notes = ?, status = ?, updated_by = ?, updated_at = NOW(), logo = ?, license_number = ?
                        WHERE id = ?
                    ");

                    $params = [
                        $company_name, $contact_name, $email, $phone, $trade, $address_line1, $address_line2,
                        $city, $county, $postcode, $vat_number, $company_number, $insurance_info, $utr_number,
                        $notes, $status, $currentUser, $logo, $license_number, $contractor_id
                    ];

                    try {
                        $stmt->execute($params);
                        $success_message = "Contractor updated successfully";
                    } catch (PDOException $e) {
                        error_log("Error updating contractor: " . $e->getMessage());
                        $error_message = "Error updating contractor. Please check the logs for details.";
                    }
                    break;

                case 'delete_contractor':
                    // Delete a contractor
                    $contractor_id = (int)$_POST['contractor_id'];

                    // Get the logo filename before deleting the contractor
                    $stmt = $db->prepare("SELECT logo FROM contractors WHERE id = ?");
                    $stmt->execute([$contractor_id]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    $logoFilename = $result ? $result['logo'] : null;

                    // Prepare and execute the SQL query to delete the contractor
                    $stmt = $db->prepare("DELETE FROM contractors WHERE id = ?");
                    if ($stmt->execute([$contractor_id])) {
                        // Delete the logo file if it exists
                        if ($logoFilename) {
                            $uploadDir = 'uploads/logos/';
                            $logoPath = $uploadDir . $logoFilename;
                            if (file_exists($logoPath)) {
                                unlink($logoPath);
                            }
                        }
                        $success_message = "Contractor deleted successfully";
                    } else {
                        $error_message = "Error deleting contractor";
                    }
                    break;
            }
        }
    }

    // Get all contractors
    $query = "
        SELECT 
            c.id, c.company_name, c.contact_name, c.email, c.phone, c.trade,
            c.address_line1, c.address_line2, c.city, c.county, c.postcode,
            c.vat_number, c.company_number, c.insurance_info, c.utr_number,
            c.notes, c.status, c.created_at, c.updated_at, c.logo, c.license_number
        FROM contractors AS c 
        ORDER BY c.created_at DESC
    ";
    $contractors = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Database error in contractors.php: " . $e->getMessage());
    $error_message = "Database error: " . $e->getMessage();
}

$totalContractors = count($contractors);
$statusCounts = [
    'active' => 0,
    'inactive' => 0,
    'suspended' => 0,
    'other' => 0,
];
$uniqueTrades = [];
$insuredCount = 0;
$licensedCount = 0;
$latestUpdate = null;
$recentAdditionName = '';
$recentAdditionRelative = 'N/A';

if (!empty($contractors)) {
    foreach ($contractors as $contractor) {
        $statusKey = strtolower($contractor['status'] ?? 'other');
        if (array_key_exists($statusKey, $statusCounts)) {
            $statusCounts[$statusKey]++;
        } else {
            $statusCounts['other']++;
        }

        if (!empty($contractor['trade'])) {
            $uniqueTrades[strtolower(trim($contractor['trade']))] = true;
        }

        if (!empty(trim((string) ($contractor['insurance_info'] ?? '')))) {
            $insuredCount++;
        }

        if (!empty(trim((string) ($contractor['license_number'] ?? '')))) {
            $licensedCount++;
        }

        $timelineCandidate = $contractor['updated_at'] ?: $contractor['created_at'];
        if ($timelineCandidate && ($latestUpdate === null || strtotime($timelineCandidate) > strtotime($latestUpdate))) {
            $latestUpdate = $timelineCandidate;
        }
    }

    $recentAdditionName = $contractors[0]['company_name'] ?? '';
    $recentAdditionRelative = !empty($contractors[0]['created_at'])
        ? formatRelativeTime($contractors[0]['created_at'])
        : 'N/A';
}

$tradesRepresented = count($uniqueTrades);
$latestUpdateRelative = $latestUpdate ? formatRelativeTime($latestUpdate) : 'N/A';

// Helper function for status badge classes
function getStatusBadgeClass($status) {
    switch (strtolower($status)) {
        case 'active':
            return 'status-badge status-badge--active';
        case 'inactive':
            return 'status-badge status-badge--inactive';
        case 'suspended':
            return 'status-badge status-badge--suspended';
        default:
            return 'status-badge status-badge--default';
    }
}

// Helper function to format dates
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatRelativeTime($date)
{
    if (empty($date)) {
        return 'N/A';
    }

    try {
        $target = new DateTime($date, new DateTimeZone('UTC'));
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $diff = $now->diff($target);

        if ($diff->y > 0) {
            return $diff->y . 'y ago';
        }

        if ($diff->m > 0) {
            return $diff->m . 'mo ago';
        }

        if ($diff->d > 0) {
            return $diff->d . 'd ago';
        }

        if ($diff->h > 0) {
            return $diff->h . 'h ago';
        }

        if ($diff->i > 0) {
            return $diff->i . 'm ago';
        }

        return 'Just now';
    } catch (Exception $e) {
        error_log('Relative time formatting error: ' . $e->getMessage());
        return 'N/A';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Contractor HQ - Defect Tracker System">
    <meta name="author" content="<?php echo htmlspecialchars($_SESSION['username']); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars($currentDateTime); ?>">
    <title>Contractor HQ - Defect Tracker</title>
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css?v=20251103" rel="stylesheet">
</head>
<body class="tool-body contractor-page-body" data-bs-theme="dark">
<?php
try {
    if ($navbar instanceof Navbar) {
        $navbar->render();
    }
} catch (Exception $renderException) {
    error_log('Navbar render error on contractors.php: ' . $renderException->getMessage());
    echo '<div class="alert alert-danger m-3" role="alert">Navigation failed to load. Refresh the page or contact support.</div>';
}
?>

<div class="app-content-offset"></div>

<main class="contractor-page container-fluid px-4 pb-5">
    <section class="contractor-hero shadow-lg mb-4">
        <div class="contractor-hero__headline">
            <div>
                <span class="contractor-hero__pill"><i class='bx bx-hard-hat me-1'></i>Trade Network</span>
                <h1 class="contractor-hero__title">Contractor HQ</h1>
                <p class="contractor-hero__subtitle">
                    Managing <?php echo number_format($totalContractors); ?> partners across <?php echo number_format($tradesRepresented); ?> trades.
                    <?php if (!empty($recentAdditionName)): ?>
                        <span class="contractor-hero__highlight">Latest addition: <?php echo htmlspecialchars($recentAdditionName); ?> (<?php echo htmlspecialchars($recentAdditionRelative); ?>)</span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="contractor-hero__actions text-end">
                <button type="button" class="btn btn-primary btn-lg shadow-sm" data-bs-toggle="modal" data-bs-target="#createContractorModal">
                    <i class='bx bx-plus-circle me-2'></i>New Contractor
                </button>
                <p class="contractor-hero__timestamp mt-3">
                    <i class='bx bx-time-five me-1'></i>Updated <?php echo htmlspecialchars($latestUpdateRelative); ?>
                </p>
            </div>
        </div>
        <div class="contractor-hero__metrics">
            <article class="contractor-metric">
                <div class="contractor-metric__icon contractor-metric__icon--blue"><i class='bx bx-group'></i></div>
                <div class="contractor-metric__details">
                    <span class="contractor-metric__label">Active Workforce</span>
                    <span class="contractor-metric__value"><?php echo number_format($statusCounts['active'] ?? 0); ?></span>
                    <span class="contractor-metric__note">of <?php echo number_format($totalContractors); ?> total</span>
                </div>
            </article>
            <article class="contractor-metric">
                <div class="contractor-metric__icon contractor-metric__icon--teal"><i class='bx bx-dots-grid'></i></div>
                <div class="contractor-metric__details">
                    <span class="contractor-metric__label">Trades Represented</span>
                    <span class="contractor-metric__value"><?php echo number_format($tradesRepresented); ?></span>
                    <span class="contractor-metric__note">Diverse capabilities in play</span>
                </div>
            </article>
            <article class="contractor-metric">
                <div class="contractor-metric__icon contractor-metric__icon--violet"><i class='bx bx-id-card'></i></div>
                <div class="contractor-metric__details">
                    <span class="contractor-metric__label">Licensed Vendors</span>
                    <span class="contractor-metric__value"><?php echo number_format($licensedCount); ?></span>
                    <span class="contractor-metric__note">Compliance-ready partners</span>
                </div>
            </article>
            <article class="contractor-metric">
                <div class="contractor-metric__icon contractor-metric__icon--amber"><i class='bx bx-shield-quarter'></i></div>
                <div class="contractor-metric__details">
                    <span class="contractor-metric__label">Insured Partners</span>
                    <span class="contractor-metric__value"><?php echo number_format($insuredCount); ?></span>
                    <span class="contractor-metric__note">Insurance evidence on file</span>
                </div>
            </article>
        </div>
    </section>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm contractor-alert" role="alert">
            <i class='bx bx-check-circle me-2'></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm contractor-alert" role="alert">
            <i class='bx bx-error-circle me-2'></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <section class="row g-3 contractor-controls align-items-center mb-4">
        <div class="col-12 col-lg-6">
            <div class="input-group input-group-lg contractor-controls__search">
                <span class="input-group-text"><i class='bx bx-search'></i></span>
                <input type="search" class="form-control" id="contractorSearch" placeholder="Search by company, trade, contact, or location" aria-label="Search contractors">
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="contractor-controls__filters d-flex flex-wrap gap-2">
                <button type="button" class="contractor-filter__button is-active" data-filter="all">
                    <i class='bx bx-show me-1'></i>All
                    <span class="contractor-filter__count"><?php echo number_format($totalContractors); ?></span>
                </button>
                <button type="button" class="contractor-filter__button" data-filter="active">
                    <i class='bx bx-bolt-circle me-1'></i>Active
                    <span class="contractor-filter__count"><?php echo number_format($statusCounts['active'] ?? 0); ?></span>
                </button>
                <button type="button" class="contractor-filter__button" data-filter="inactive">
                    <i class='bx bx-moon me-1'></i>Inactive
                    <span class="contractor-filter__count"><?php echo number_format($statusCounts['inactive'] ?? 0); ?></span>
                </button>
                <button type="button" class="contractor-filter__button" data-filter="suspended">
                    <i class='bx bx-error me-1'></i>Suspended
                    <span class="contractor-filter__count"><?php echo number_format($statusCounts['suspended'] ?? 0); ?></span>
                </button>
            </div>
        </div>
    </section>

    <section class="contractor-directory">
        <div class="row g-4 contractor-grid" id="contractorGrid">
            <?php if (empty($contractors)): ?>
                <div class="col-12">
                    <div class="contractor-empty-state text-center py-5">
                        <div class="contractor-empty-state__icon mb-3"><i class='bx bx-buildings'></i></div>
                        <h2 class="fw-semibold mb-2">No contractors on file yet</h2>
                        <p class="text-muted mb-4">Start building your supply chain by adding your first contractor.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createContractorModal">
                            <i class='bx bx-plus-circle me-2'></i>Create Contractor
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($contractors as $contractor): ?>
                    <?php
                        $statusKey = strtolower($contractor['status'] ?? 'other');
                        $addressParts = array_filter([
                            $contractor['address_line1'] ?? '',
                            $contractor['address_line2'] ?? '',
                            $contractor['city'] ?? '',
                            $contractor['county'] ?? '',
                            $contractor['postcode'] ?? '',
                        ], function ($value) {
                            return !empty(trim((string) $value));
                        });
                        $addressSummary = !empty($addressParts) ? implode(', ', $addressParts) : 'Address not provided';
                        $searchableValues = [
                            $contractor['company_name'] ?? '',
                            $contractor['contact_name'] ?? '',
                            $contractor['trade'] ?? '',
                            $contractor['email'] ?? '',
                            $contractor['phone'] ?? '',
                            $addressSummary,
                            $contractor['license_number'] ?? '',
                            $contractor['vat_number'] ?? '',
                        ];
                        $searchableText = strtolower(implode(' ', array_filter($searchableValues, function ($value) {
                            return !empty(trim((string) $value));
                        })));
                        $logoPath = '';
                        if (!empty($contractor['logo'])) {
                            $logoFilename = $contractor['logo'];
                            if (stripos($logoFilename, 'uploads/logos/') === 0) {
                                $logoFilename = substr($logoFilename, strlen('uploads/logos/'));
                            }
                            $logoPath = '/uploads/logos/' . $logoFilename;
                        }
                        $initialsSource = $contractor['company_name'] ?? 'NA';
                        $initials = strtoupper(substr($initialsSource, 0, 2));
                        $updatedRelative = formatRelativeTime($contractor['updated_at'] ?: $contractor['created_at']);
                        $creationDate = formatDate($contractor['created_at']);
                        $notes = trim((string) ($contractor['notes'] ?? ''));
                    ?>
                    <div class="col-12 col-lg-6 col-xxl-4 contractor-grid__item" data-status="<?php echo htmlspecialchars($statusKey); ?>" data-search="<?php echo htmlspecialchars($searchableText); ?>">
                        <article class="contractor-card shadow-sm h-100">
                            <header class="contractor-card__header">
                                <div class="contractor-card__brand">
                                    <div class="contractor-card__avatar <?php echo $logoPath ? '' : 'contractor-card__avatar--initial'; ?>">
                                        <?php if ($logoPath): ?>
                                            <img src="<?php echo htmlspecialchars($logoPath); ?>" alt="<?php echo htmlspecialchars($contractor['company_name']); ?> logo">
                                        <?php else: ?>
                                            <span><?php echo htmlspecialchars($initials); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h2 class="contractor-card__title mb-0"><?php echo htmlspecialchars($contractor['company_name']); ?></h2>
                                        <p class="contractor-card__subtitle mb-0"><i class='bx bx-briefcase-alt-2 me-1'></i><?php echo htmlspecialchars($contractor['trade'] ?: 'Trade pending'); ?></p>
                                    </div>
                                </div>
                                <span class="<?php echo getStatusBadgeClass($contractor['status']); ?>"><?php echo ucfirst(htmlspecialchars($contractor['status'])); ?></span>
                            </header>
                            <div class="contractor-card__body">
                                <ul class="contractor-card__meta-list">
                                    <li><i class='bx bx-user-voice'></i><?php echo htmlspecialchars($contractor['contact_name'] ?: 'No contact assigned'); ?></li>
                                    <li><i class='bx bx-envelope'></i><a href="mailto:<?php echo htmlspecialchars($contractor['email']); ?>"><?php echo htmlspecialchars($contractor['email']); ?></a></li>
                                    <li><i class='bx bx-phone-call'></i><a href="tel:<?php echo htmlspecialchars($contractor['phone']); ?>"><?php echo htmlspecialchars($contractor['phone']); ?></a></li>
                                    <li><i class='bx bx-map'></i><?php echo htmlspecialchars($addressSummary); ?></li>
                                </ul>
                                <div class="contractor-card__tags">
                                    <?php if (!empty($contractor['license_number'])): ?>
                                        <span class="contractor-tag"><i class='bx bx-id-card'></i><?php echo htmlspecialchars($contractor['license_number']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($contractor['vat_number'])): ?>
                                        <span class="contractor-tag"><i class='bx bx-receipt'></i>VAT <?php echo htmlspecialchars($contractor['vat_number']); ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($contractor['insurance_info'])): ?>
                                        <span class="contractor-tag contractor-tag--success"><i class='bx bx-shield-quarter'></i>Insurance verified</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($notes !== ''): ?>
                                    <p class="contractor-card__notes"><i class='bx bx-note me-1'></i><?php echo nl2br(htmlspecialchars($notes)); ?></p>
                                <?php endif; ?>
                            </div>
                            <footer class="contractor-card__footer">
                                <div class="contractor-card__timestamps">
                                    <span><i class='bx bx-calendar-star me-1'></i>Added <?php echo htmlspecialchars($creationDate); ?></span>
                                    <span><i class='bx bx-refresh me-1'></i>Updated <?php echo htmlspecialchars($updatedRelative); ?></span>
                                </div>
                                <div class="contractor-card__actions">
                                    <a href="view_contractor.php?id=<?php echo $contractor['id']; ?>" class="btn btn-sm btn-outline-light">
                                        <i class='bx bx-show-alt me-1'></i>View
                                    </a>
                                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editContractorModal<?php echo $contractor['id']; ?>">
                                        <i class='bx bx-edit-alt me-1'></i>Edit
                                    </button>
                                    <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteContractor(<?php echo $contractor['id']; ?>)">
                                        <i class='bx bx-trash-alt me-1'></i>Delete
                                    </button>
                                </div>
                            </footer>
                        </article>

                        <div class="modal fade contractor-modal__wrapper" id="editContractorModal<?php echo $contractor['id']; ?>" tabindex="-1" aria-labelledby="editContractorLabel<?php echo $contractor['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content contractor-modal">
                                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                                        <input type="hidden" name="action" value="update_contractor">
                                        <input type="hidden" name="contractor_id" value="<?php echo $contractor['id']; ?>">
                                        <input type="hidden" name="old_logo" value="<?php echo htmlspecialchars($contractor['logo']); ?>">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editContractorLabel<?php echo $contractor['id']; ?>">Edit Contractor</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label class="form-label required">Company Name</label>
                                                    <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($contractor['company_name']); ?>" required>
                                                    <div class="invalid-feedback">Company name is required</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label required">Contact Name</label>
                                                    <input type="text" name="contact_name" class="form-control" value="<?php echo htmlspecialchars($contractor['contact_name']); ?>" required>
                                                    <div class="invalid-feedback">Contact name is required</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label required">Email</label>
                                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($contractor['email']); ?>" required>
                                                    <div class="invalid-feedback">Email is required</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label required">Phone</label>
                                                    <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($contractor['phone']); ?>" required>
                                                    <div class="invalid-feedback">Phone is required</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Trade</label>
                                                    <input type="text" name="trade" class="form-control" value="<?php echo htmlspecialchars($contractor['trade']); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="active" <?php echo $contractor['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $contractor['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="suspended" <?php echo $contractor['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Address Line 1</label>
                                                    <input type="text" name="address_line1" class="form-control" value="<?php echo htmlspecialchars($contractor['address_line1']); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Address Line 2</label>
                                                    <input type="text" name="address_line2" class="form-control" value="<?php echo htmlspecialchars($contractor['address_line2']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">City</label>
                                                    <input type="text" name="city" class="form-control" value="<?php echo htmlspecialchars($contractor['city']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">County</label>
                                                    <input type="text" name="county" class="form-control" value="<?php echo htmlspecialchars($contractor['county']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Postcode</label>
                                                    <input type="text" name="postcode" class="form-control" value="<?php echo htmlspecialchars($contractor['postcode']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">VAT Number</label>
                                                    <input type="text" name="vat_number" class="form-control" value="<?php echo htmlspecialchars($contractor['vat_number']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Company Number</label>
                                                    <input type="text" name="company_number" class="form-control" value="<?php echo htmlspecialchars($contractor['company_number']); ?>">
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">UTR Number</label>
                                                    <input type="text" name="utr_number" class="form-control" value="<?php echo htmlspecialchars($contractor['utr_number']); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">License Number</label>
                                                    <input type="text" name="license_number" class="form-control" value="<?php echo htmlspecialchars($contractor['license_number']); ?>">
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Insurance Info</label>
                                                    <textarea name="insurance_info" class="form-control" rows="3"><?php echo htmlspecialchars($contractor['insurance_info']); ?></textarea>
                                                </div>
                                                <div class="col-12">
                                                    <label class="form-label">Notes</label>
                                                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($contractor['notes']); ?></textarea>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label">Logo</label>
                                                    <input type="file" name="logo" class="form-control">
                                                </div>
                                                <?php if ($contractor['logo']): ?>
                                                    <?php
                                                        $logoFilename = $contractor['logo'];
                                                        if (stripos($logoFilename, 'uploads/logos/') === 0) {
                                                            $logoFilename = substr($logoFilename, strlen('uploads/logos/'));
                                                        }
                                                        $logoSrc = '/uploads/logos/' . $logoFilename;
                                                    ?>
                                                    <div class="col-md-6 d-flex align-items-end">
                                                        <img src="<?php echo htmlspecialchars($logoSrc); ?>" alt="<?php echo htmlspecialchars($contractor['company_name']); ?> logo" class="img-fluid rounded shadow-sm contractor-modal__preview">
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" class="btn btn-primary">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div id="contractorEmptyState" class="contractor-empty-state text-center py-5" hidden>
            <div class="contractor-empty-state__icon mb-3"><i class='bx bx-search-alt-2'></i></div>
            <h2 class="fw-semibold mb-2">No contractors match the current filters</h2>
            <p class="text-muted mb-4">Adjust your search or clear the filters to see more partners.</p>
            <button type="button" class="btn btn-outline-light" data-reset-filters>
                <i class='bx bx-reset me-2'></i>Reset filters
            </button>
        </div>
    </section>
</main>

<div class="modal fade contractor-modal__wrapper" id="createContractorModal" tabindex="-1" aria-labelledby="createContractorLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content contractor-modal">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="create_contractor">
                <div class="modal-header">
                    <h5 class="modal-title" id="createContractorLabel">Create New Contractor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Company Name</label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Name</label>
                            <input type="text" name="contact_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Trade</label>
                            <input type="text" name="trade" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address Line 1</label>
                            <input type="text" name="address_line1" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Address Line 2</label>
                            <input type="text" name="address_line2" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">County</label>
                            <input type="text" name="county" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Postcode</label>
                            <input type="text" name="postcode" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">VAT Number</label>
                            <input type="text" name="vat_number" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Company Number</label>
                            <input type="text" name="company_number" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">UTR Number</label>
                            <input type="text" name="utr_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">License Number</label>
                            <input type="text" name="license_number" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Insurance Info</label>
                            <textarea name="insurance_info" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Logo</label>
                            <input type="file" name="logo" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Contractor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form id="deleteContractorForm" method="POST" class="d-none">
    <input type="hidden" name="action" value="delete_contractor">
    <input type="hidden" name="contractor_id" id="deleteContractorId">
</form>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.needs-validation');
        Array.from(forms).forEach(form => {
            form.addEventListener('submit', event => {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            });
        });

        const searchInput = document.getElementById('contractorSearch');
        const filterButtons = document.querySelectorAll('.contractor-filter__button');
        const gridItems = document.querySelectorAll('.contractor-grid__item');
        const emptyState = document.getElementById('contractorEmptyState');
        const resetButtons = document.querySelectorAll('[data-reset-filters]');

        function applyFilters() {
            const query = searchInput ? searchInput.value.trim().toLowerCase() : '';
            const activeFilterButton = document.querySelector('.contractor-filter__button.is-active');
            const activeFilter = activeFilterButton ? activeFilterButton.getAttribute('data-filter') : 'all';
            let visibleCount = 0;

            gridItems.forEach(item => {
                const matchesFilter = activeFilter === 'all' || item.dataset.status === activeFilter;
                const searchable = (item.dataset.search || '').toLowerCase();
                const matchesSearch = !query || searchable.includes(query);
                const shouldShow = matchesFilter && matchesSearch;
                item.classList.toggle('is-hidden', !shouldShow);
                if (shouldShow) {
                    visibleCount++;
                }
            });

            if (emptyState) {
                emptyState.hidden = visibleCount > 0;
            }
        }

        if (searchInput) {
            searchInput.addEventListener('input', applyFilters);
        }

        filterButtons.forEach(button => {
            button.addEventListener('click', () => {
                filterButtons.forEach(btn => btn.classList.remove('is-active'));
                button.classList.add('is-active');
                applyFilters();
            });
        });

        resetButtons.forEach(button => {
            button.addEventListener('click', () => {
                if (searchInput) {
                    searchInput.value = '';
                }
                const allButton = document.querySelector('.contractor-filter__button[data-filter="all"]');
                if (allButton) {
                    filterButtons.forEach(btn => btn.classList.remove('is-active'));
                    allButton.classList.add('is-active');
                }
                applyFilters();
            });
        });

        applyFilters();

        window.confirmDeleteContractor = function(contractorId) {
            if (confirm('Are you sure you want to delete this contractor? This action cannot be undone.')) {
                const form = document.getElementById('deleteContractorForm');
                if (form) {
                    document.getElementById('deleteContractorId').value = contractorId;
                    form.submit();
                }
            }
        };

        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.forEach(function (tooltipTriggerEl) {
            new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
</body>
</html>