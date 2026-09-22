<?php
/**
 * system-tools/check_imagemagick.php
 * ImageMagick diagnostic tool
 */

declare(strict_types=1);

function checkImageMagickCli(): void
{
    exec("convert -version", $output, $return_var);
    if ($return_var === 0) {
        echo "ImageMagick is installed:\n";
        echo implode("\n", $output);
    } else {
        echo "ImageMagick is not installed.\n";
    }
}

if (PHP_SAPI === 'cli') {
    checkImageMagickCli();
    exit(0);
}

require_once __DIR__ . '/includes/tool_bootstrap.php';

exec("convert -version", $output, $return_var);
$imageMagickInstalled = ($return_var === 0);
$versionInfo = [];

if ($imageMagickInstalled && !empty($output)) {
    $versionInfo = $output;
}

tool_render_header(
    'ImageMagick Diagnostics',
    'Verify whether ImageMagick is available and review its version information.',
    [
        ['label' => 'Admin Dashboard', 'href' => '../admin.php'],
        ['label' => 'ImageMagick Diagnostics'],
    ]
);

if (!$imageMagickInstalled) {
    echo '<div class="alert alert-danger" role="alert">ImageMagick is not installed on this server.</div>';
    tool_render_footer();
    return;
}
?>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <div class="tool-card h-100">
            <div class="tool-card-header">
                <h2 class="h5 mb-0"><i class="bx bx-info-circle me-2"></i>Environment Status</h2>
            </div>
            <div class="tool-card-body">
                <p class="text-muted mb-3">ImageMagick is available and ready for image processing tasks.</p>
                <span class="badge bg-success-subtle text-success-emphasis">Installed</span>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-8">
        <div class="tool-card h-100">
            <div class="tool-card-header">
                <h2 class="h5 mb-0"><i class="bx bx-list-check me-2"></i>Version Information</h2>
            </div>
            <div class="tool-card-body">
                <div class="table-responsive">
                    <table class="table table-dark table-striped align-middle mb-0">
                        <tbody>
                        <?php foreach ($versionInfo as $line): ?>
                            <tr>
                                <td><?= htmlspecialchars($line, ENT_QUOTES, 'UTF-8') ?></td>
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