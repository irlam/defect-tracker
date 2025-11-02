<?php
/**
 * system-tools/check_gd.php
 * GD library diagnostic tool
 */

declare(strict_types=1);

function checkGdCli(): void
{
    if (!extension_loaded('gd')) {
        echo "GD library is NOT installed.\n";
        return;
    }

    echo "GD library is installed.\n";

    $info = gd_info();
    $version = $info['GD Version'] ?? 'Unknown';
    echo "GD Version: {$version}\n\n";

    foreach ($info as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'Yes' : 'No';
        }
        echo sprintf("%-40s %s\n", $key . ':', (string)$value);
    }
}

if (PHP_SAPI === 'cli') {
    checkGdCli();
    exit(0);
}

require_once __DIR__ . '/includes/tool_bootstrap.php';

$gdLoaded = extension_loaded('gd');
$gdInfo = $gdLoaded ? gd_info() : [];

tool_render_header(
    'GD Library Diagnostics',
    'Verify whether the GD extension is available and review its supported features.',
    [
        ['label' => 'Admin Dashboard', 'href' => '../admin.php'],
        ['label' => 'GD Library Diagnostics'],
    ]
);

if (!$gdLoaded) {
    echo '<div class="alert alert-danger" role="alert">GD library is not installed on this server.</div>';
    tool_render_footer();
    return;
}

$versionDisplay = htmlspecialchars((string)($gdInfo['GD Version'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8');

$features = [];
foreach ($gdInfo as $key => $value) {
    $features[] = [
        'label' => htmlspecialchars((string)$key, ENT_QUOTES, 'UTF-8'),
        'status' => htmlspecialchars(is_bool($value) ? ($value ? 'Supported' : 'Not Supported') : (string)$value, ENT_QUOTES, 'UTF-8'),
        'is_supported' => is_bool($value) ? (bool)$value : null,
    ];
}
?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="tool-card h-100">
            <div class="tool-card-header">
                <h2 class="h5 mb-0"><i class="bx bx-info-circle me-2"></i>Environment Status</h2>
            </div>
            <div class="tool-card-body">
                <p class="text-muted mb-3">GD extension is available and ready for image processing tasks.</p>
                <span class="badge bg-success-subtle text-success-emphasis">Version <?= $versionDisplay ?></span>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="tool-card h-100">
            <div class="tool-card-header">
                <h2 class="h5 mb-0"><i class="bx bx-list-check me-2"></i>Supported Features</h2>
            </div>
            <div class="tool-card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Capability</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($features as $feature): ?>
                            <?php
                                $badgeClass = 'bg-secondary-subtle text-secondary-emphasis';
                                if ($feature['is_supported'] === true) {
                                    $badgeClass = 'bg-success-subtle text-success-emphasis';
                                } elseif ($feature['is_supported'] === false) {
                                    $badgeClass = 'bg-danger-subtle text-danger-emphasis';
                                }
                            ?>
                            <tr>
                                <td><?= $feature['label'] ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= $feature['status'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
tool_render_footer();
?>
