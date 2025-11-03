<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/logo_functions.php';
require_once __DIR__ . '/includes/navbar.php';

if (!isset($_SESSION['user_id'], $_SESSION['username'])) {
    header('Location: login.php');
    exit;
}

date_default_timezone_set('Europe/London');

$message = '';
$error = '';
$logoManager = null;

try {
    $logoManager = new LogoManager();
    if (!$logoManager->checkPermissions()) {
        header('Location: dashboard.php');
        exit;
    }
} catch (Throwable $initialisationError) {
    $error = Environment::isDevelopment()
        ? 'Unable to initialise logo tools: ' . $initialisationError->getMessage()
        : 'Logo tools are currently unavailable. Please try again later.';
    error_log('LogoManager bootstrap failed: ' . $initialisationError->getMessage());
}

$displayName = ucwords(str_replace(['.', '_'], [' ', ' '], $_SESSION['username'] ?? 'User'));
$currentUserRoleSummary = ucwords(str_replace(['_', '-'], [' ', ' '], $_SESSION['user_type'] ?? 'User'));
$currentTimestamp = date('d/m/Y H:i');

$navbar = null;
try {
    $navbar = new Navbar($db, (int)($_SESSION['user_id'] ?? 0), $_SESSION['username'] ?? '');
} catch (Throwable $navbarException) {
    error_log('Navbar initialisation failed on add_logo.php: ' . $navbarException->getMessage());
    $navbar = null;
}

$contractors = [];
if (isset($db) && $db instanceof PDO) {
    try {
        $contractorStmt = $db->prepare("
            SELECT id, company_name
            FROM contractors
            WHERE deleted_at IS NULL
            ORDER BY company_name ASC
        ");
        $contractorStmt->execute();
        $contractors = $contractorStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $contractorLoadError) {
        $error = $error ?: 'Unable to load contractor directory.';
        error_log('Contractor load failure (add_logo.php): ' . $contractorLoadError->getMessage());
    }
} else {
    $error = $error ?: 'Database connection unavailable.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($logoManager instanceof LogoManager) {
        try {
            if (isset($_FILES['logo']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
                $typeInput = filter_input(INPUT_POST, 'logo_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: 'company';
                $logoType = in_array($typeInput, ['company', 'contractor'], true) ? $typeInput : 'company';
                $selectedContractorId = $logoType === 'contractor'
                    ? filter_input(INPUT_POST, 'contractor_id', FILTER_VALIDATE_INT)
                    : null;

                $logoManager->uploadLogo($_FILES['logo'], $logoType, $selectedContractorId ?: null);
                $message = 'Logo uploaded successfully.';
            }

            if (isset($_POST['delete_type'])) {
                $deleteTypeInput = filter_input(INPUT_POST, 'delete_type', FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?: '';
                $deleteType = in_array($deleteTypeInput, ['company', 'contractor'], true) ? $deleteTypeInput : '';
                $deleteContractor = filter_input(INPUT_POST, 'contractor_id', FILTER_VALIDATE_INT) ?: null;

                if ($deleteType !== '') {
                    if ($logoManager->deleteLogo($deleteType, $deleteContractor)) {
                        $message = 'Logo deleted successfully.';
                    } else {
                        $error = 'Unable to delete the selected logo.';
                    }
                }
            }
        } catch (Throwable $logoActionError) {
            $error = $logoActionError->getMessage();
            error_log('Logo action failure on add_logo.php: ' . $logoActionError->getMessage());
        }
    } else {
        $error = $error ?: 'Logo tools are not currently available.';
    }
}

$companyLogo = $logoManager instanceof LogoManager ? $logoManager->getCompanyLogo() : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logo Management - Defect Tracker</title>
    <meta name="description" content="Manage company and contractor brand assets for McGoff Defect Tracker.">
    <meta name="author" content="<?php echo htmlspecialchars($_SESSION['username'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
    <meta name="last-modified" content="<?php echo htmlspecialchars(date('c'), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" type="image/png" href="/favicons/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/favicons/favicon.svg" />
    <link rel="shortcut icon" href="/favicons/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png" />
    <link rel="manifest" href="/favicons/site.webmanifest" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link href="/css/app.css?v=20251103" rel="stylesheet">
    <style>
        .logo-management-wrapper {
            padding: clamp(1.5rem, 3vw, 3rem);
        }

        .logo-surface {
            background: var(--surface-color);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-lg);
        }

        .logo-surface__header {
            border-bottom: 1px solid rgba(148, 163, 184, 0.2);
        }

        .logo-upload-dropzone {
            border: 1px dashed rgba(148, 163, 184, 0.4);
            border-radius: var(--border-radius-lg);
            padding: 1.5rem;
            background: rgba(22, 33, 61, 0.65);
        }

        .logo-preview-frame {
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.65);
            border-radius: var(--border-radius-lg);
            min-height: 140px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            padding: 1rem;
            overflow: hidden;
        }

        .logo-preview-frame img {
            max-height: 110px;
            max-width: 100%;
            width: auto;
            object-fit: contain;
            display: block;
        }

        .logo-card-title {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-muted-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .logo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1.5rem;
        }

        .logo-card {
            background: rgba(17, 28, 47, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            transition: transform var(--transition-base), box-shadow var(--transition-base);
        }

        .logo-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .logo-card__body {
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        @media (max-width: 576px) {
            .logo-management-wrapper {
                padding: 1.25rem;
            }
        }
    </style>
</head>
<body class="tool-body" data-bs-theme="dark">
    <?php
    if ($navbar instanceof Navbar) {
        $navbar->render();
    }
    ?>
    <div class="app-content-offset"></div>

    <main class="logo-management-wrapper container-xl">
        <header class="tool-header mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <h1 class="h3 mb-2">Brand Asset Studio</h1>
                    <p class="text-muted mb-0">Upload, review, and curate company and contractor logos for the platform.</p>
                </div>
                <div class="d-flex flex-column align-items-start text-muted small gap-1">
                    <span><i class='bx bx-user-voice me-1'></i><?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-label me-1'></i><?php echo htmlspecialchars($currentUserRoleSummary, ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class='bx bx-time-five me-1'></i><?php echo htmlspecialchars($currentTimestamp, ENT_QUOTES, 'UTF-8'); ?> UK</span>
                </div>
            </div>
        </header>

        <?php if ($message !== ''): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class='bx bx-check-circle me-1'></i><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class='bx bx-error-circle me-1'></i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <section class="logo-surface mb-5">
            <div class="logo-surface__header px-4 py-3 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                <div>
                    <h2 class="h5 mb-1">Upload New Logo</h2>
                    <p class="text-muted mb-0 small">Accepted formats: JPG, PNG, GIF — up to 5MB.</p>
                </div>
                <a class="btn btn-outline-light btn-sm" href="contractors.php">
                    <i class='bx bx-hard-hat me-1'></i>Review Contractor Directory
                </a>
            </div>
            <div class="px-4 py-4">
                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="post" enctype="multipart/form-data" id="logoForm">
                    <div class="row g-4">
                        <div class="col-12 col-lg-5">
                            <label for="logo_type" class="form-label text-uppercase text-muted small">Logo Type</label>
                            <select class="form-select form-select-lg bg-dark text-light border-secondary" id="logo_type" name="logo_type">
                                <option value="company">Company Logo</option>
                                <option value="contractor">Contractor Logo</option>
                            </select>
                        </div>
                        <div class="col-12 col-lg-7" id="contractor_select" style="display: none;">
                            <label for="contractor_id" class="form-label text-uppercase text-muted small">Assign Contractor</label>
                            <select class="form-select form-select-lg bg-dark text-light border-secondary" name="contractor_id" id="contractor_id">
                                <option value="">Select contractor...</option>
                                <?php foreach ($contractors as $contractor): ?>
                                    <option value="<?php echo (int) $contractor['id']; ?>">
                                        <?php echo htmlspecialchars($contractor['company_name'] ?? 'Unnamed contractor', ENT_QUOTES, 'UTF-8'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <div class="logo-upload-dropzone">
                                <label class="form-label text-uppercase text-muted small d-block mb-2" for="logo">Upload Logo Asset</label>
                                <input type="file" class="form-control form-control-lg bg-dark text-light border-secondary" id="logo" name="logo" accept="image/*" required>
                                <p class="text-muted small mt-2 mb-0"><i class='bx bx-info-circle me-1'></i>Use transparent PNGs for best results across the UI.</p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class='bx bx-cloud-upload me-1'></i>Upload Logo
                        </button>
                        <button type="reset" class="btn btn-outline-light" id="logoFormReset">
                            <i class='bx bx-reset me-1'></i>Reset Form
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <section class="logo-surface mb-5">
            <div class="logo-surface__header px-4 py-3">
                <h2 class="h5 mb-1">Company Brandmark</h2>
                <p class="text-muted small mb-0">Primary logo used across the application header and PDFs.</p>
            </div>
            <div class="px-4 py-4">
                <?php if ($companyLogo): ?>
                    <div class="logo-preview-frame mb-3">
                        <img src="<?php echo htmlspecialchars($companyLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="Company logo preview">
                    </div>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="post" class="d-inline">
                        <input type="hidden" name="delete_type" value="company">
                        <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Delete the current company logo?');">
                            <i class='bx bx-trash me-1'></i>Delete Company Logo
                        </button>
                    </form>
                <?php else: ?>
                    <div class="logo-preview-frame mb-3">
                        <span class="text-muted">No company logo uploaded yet.</span>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <section class="logo-surface">
            <div class="logo-surface__header px-4 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                <div>
                    <h2 class="h5 mb-1">Contractor Logos</h2>
                    <p class="text-muted small mb-0">Assets displayed across contractor cards and dashboards.</p>
                </div>
                <span class="badge bg-secondary-subtle text-secondary-emphasis">
                    <?php echo number_format(count($contractors)); ?> contractors
                </span>
            </div>
            <div class="px-4 py-4">
                <?php if (empty($contractors)): ?>
                    <div class="alert alert-info mb-0">
                        <i class='bx bx-info-circle me-1'></i>No contractors found. Add contractors to upload their branding.
                    </div>
                <?php else: ?>
                    <div class="logo-grid">
                        <?php foreach ($contractors as $contractor): ?>
                            <?php
                                $contractorLogo = null;
                                if ($logoManager instanceof LogoManager) {
                                    $contractorLogo = $logoManager->getContractorLogo($contractor['id']);
                                }
                            ?>
                            <article class="logo-card">
                                <div class="logo-card__body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="logo-card-title"><?php echo htmlspecialchars($contractor['company_name'] ?? 'Contractor', ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div class="logo-preview-frame">
                                        <?php if (!empty($contractorLogo)): ?>
                                            <img src="<?php echo htmlspecialchars($contractorLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($contractor['company_name'] ?? 'Contractor', ENT_QUOTES, 'UTF-8'); ?> logo">
                                        <?php else: ?>
                                            <span class="text-muted small">No logo uploaded</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($contractorLogo)): ?>
                                        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'], ENT_QUOTES, 'UTF-8'); ?>" method="post">
                                            <input type="hidden" name="delete_type" value="contractor">
                                            <input type="hidden" name="contractor_id" value="<?php echo (int) $contractor['id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm w-100" onclick="return confirm('Delete this contractor logo?');">
                                                <i class='bx bx-trash me-1'></i>Delete Logo
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleContractorSelect() {
            const logoTypeSelect = document.getElementById('logo_type');
            const contractorSelectWrapper = document.getElementById('contractor_select');
            const contractorIdInput = document.getElementById('contractor_id');

            if (!logoTypeSelect || !contractorSelectWrapper || !contractorIdInput) {
                return;
            }

            const shouldShowContractor = logoTypeSelect.value === 'contractor';
            contractorSelectWrapper.style.display = shouldShowContractor ? 'block' : 'none';
            contractorIdInput.required = shouldShowContractor;
        }

        document.addEventListener('DOMContentLoaded', () => {
            const logoType = document.getElementById('logo_type');
            const logoForm = document.getElementById('logoForm');
            const resetButton = document.getElementById('logoFormReset');

            if (logoType) {
                toggleContractorSelect();
                logoType.addEventListener('change', toggleContractorSelect);
            }

            if (logoForm) {
                logoForm.addEventListener('submit', (event) => {
                    const typeValue = logoType ? logoType.value : 'company';
                    const contractorId = document.getElementById('contractor_id');

                    if (typeValue === 'contractor' && contractorId && !contractorId.value) {
                        event.preventDefault();
                        alert('Please select a contractor before uploading their logo.');
                    }
                });
            }

            if (resetButton) {
                resetButton.addEventListener('click', () => {
                    const contractorId = document.getElementById('contractor_id');
                    if (contractorId) {
                        contractorId.value = '';
                    }
                    if (logoType) {
                        logoType.value = 'company';
                    }
                    toggleContractorSelect();
                });
            }
        });
    </script>
</body>
</html>