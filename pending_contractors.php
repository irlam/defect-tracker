<?php
// pending_contractors.php
// Current Date and Time (UTC - YYYY-MM-DD HH:MM:SS formatted): 2025-01-17 11:15:49
// Current User's Login: irlam
// Error reporting and logging setup
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication check
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit();
}

require_once 'includes/functions.php';
require_once 'config/database.php';

$pageTitle = 'Pending Contractor Approvals';
$currentUser = $_SESSION['username'];
$currentDateTime = date('Y-m-d H:i:s');

try {
    $database = new Database();
    $db = $database->getConnection();

    // Modified query to remove registration_date and use created_at instead
    $query = "SELECT 
                c.id,
                c.company_name,
                c.contact_name,
                c.email,
                c.phone,
                c.created_at,
                c.status,
                COUNT(d.id) as active_defects
              FROM contractors c
              LEFT JOIN defects d ON c.id = d.contractor_id AND d.status != 'closed'
              WHERE c.status = 'pending'
              GROUP BY c.id
              ORDER BY c.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();
    $pendingContractors = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Pending Contractors Error: " . $e->getMessage());
    $error_message = "An error occurred while loading contractors: " . $e->getMessage();
}

// Handle approve/reject actions if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['contractor_id'])) {
    try {
        $action = $_POST['action'];
        $contractorId = (int)$_POST['contractor_id'];
        
        if ($action === 'approve') {
            $updateQuery = "UPDATE contractors SET status = 'active', updated_at = NOW() WHERE id = :id";
        } elseif ($action === 'reject') {
            $updateQuery = "UPDATE contractors SET status = 'rejected', updated_at = NOW() WHERE id = :id";
        }

        if (isset($updateQuery)) {
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':id', $contractorId);
            $updateStmt->execute();

            // Redirect to refresh the page
            header("Location: pending_contractors.php");
            exit();
        }
    } catch (Exception $e) {
        error_log("Contractor Update Error: " . $e->getMessage());
        $error_message = "An error occurred while updating contractor status: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<!-- [Previous HTML head section remains the same] -->

<body>
    <?php require_once 'includes/navbar.php'; ?>

    <main class="main-content">
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0"><?php echo $pageTitle; ?></h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Pending Approvals</li>
                    </ol>
                </nav>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pendingContractors)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class='bx bx-check-circle bx-lg text-muted mb-3'></i>
                    <p class="text-muted">No pending contractor approvals.</p>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($pendingContractors as $contractor): ?>
                    <div class="col-12">
                        <div class="card contractor-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-1"><?php echo htmlspecialchars($contractor['company_name']); ?></h5>
                                        <p class="text-muted mb-0">
                                            Contact: <?php echo htmlspecialchars($contractor['contact_name']); ?>
                                        </p>
                                    </div>
                                    <div>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="contractor_id" value="<?php echo $contractor['id']; ?>">
                                            <button type="submit" name="action" value="approve" 
                                                    class="btn btn-success btn-sm">
                                                <i class='bx bx-check'></i> Approve
                                            </button>
                                            <button type="submit" name="action" value="reject" 
                                                    class="btn btn-danger btn-sm">
                                                <i class='bx bx-x'></i> Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <hr>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Email</small>
                                        <a href="mailto:<?php echo htmlspecialchars($contractor['email']); ?>">
                                            <?php echo htmlspecialchars($contractor['email']); ?>
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Phone</small>
                                        <a href="tel:<?php echo htmlspecialchars($contractor['phone']); ?>">
                                            <?php echo htmlspecialchars($contractor['phone']); ?>
                                        </a>
                                    </div>
                                    <div class="col-md-4">
                                        <small class="text-muted d-block">Submission Date</small>
                                        <?php echo date('M d, Y', strtotime($contractor['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>