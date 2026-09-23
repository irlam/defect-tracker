<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/tool_bootstrap.php';

if (!($toolCurrentUser['is_admin'] ?? false)) {
    header('Location: ../dashboard.php');
    exit();
}

$tools = [
    [
        'group' => 'Health & Database',
        'items' => [
            ['label' => 'System Health', 'description' => 'Run environment and application health checks.', 'url' => 'system_health.php', 'icon' => 'bx-pulse'],
            ['label' => 'Database Check', 'description' => 'Validate database connectivity and schema integrity.', 'url' => 'check_database.php', 'icon' => 'bx-data'],
            ['label' => 'Database Optimizer', 'description' => 'Analyze and optimize key database tables.', 'url' => 'database_optimizer.php', 'icon' => 'bx-trending-up'],
        ],
    ],
    [
        'group' => 'Media & Server',
        'items' => [
            ['label' => 'GD Library Check', 'description' => 'Verify GD image-processing support.', 'url' => 'check_gd.php', 'icon' => 'bx-image'],
            ['label' => 'ImageMagick Check', 'description' => 'Verify ImageMagick availability and configuration.', 'url' => 'check_imagemagick.php', 'icon' => 'bx-images'],
            ['label' => 'File Structure Map', 'description' => 'Inspect the deployed application directory structure.', 'url' => 'show_file_structure.php', 'icon' => 'bx-network-chart'],
        ],
    ],
    [
        'group' => 'Diagnostics & Audit',
        'items' => [
            ['label' => 'System Analysis Report', 'description' => 'Generate a broader environment and configuration report.', 'url' => 'system_analysis_report.php', 'icon' => 'bx-file-find'],
            ['label' => 'Navbar Verification', 'description' => 'Check shared navigation coverage and role visibility.', 'url' => 'navbar_verification.php', 'icon' => 'bx-check-shield'],
            ['label' => 'Navbar Functions List', 'description' => 'Review the navigation functions and linked destinations.', 'url' => 'navbar_functions_list.php', 'icon' => 'bx-list-check'],
            ['label' => 'User Logs', 'description' => 'Review application user activity logs.', 'url' => '../user_logs.php', 'icon' => 'bx-history'],
        ],
    ],
];

tool_render_header(
    'System Tools & Diagnostics',
    'All administrative diagnostic utilities in one place.',
    [
        ['label' => 'Admin', 'href' => '../admin.php'],
        ['label' => 'System Tools'],
    ]
);
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div class="text-muted small">
        These tools were previously listed individually in the main System menu.
        They remain fully accessible here without making the global navigation excessively long.
    </div>
    <a class="btn btn-outline-primary" href="../admin.php">
        <i class="bx bx-arrow-back me-1"></i>Back to Admin Console
    </a>
</div>

<?php foreach ($tools as $group): ?>
    <section class="mb-4" aria-labelledby="<?php echo htmlspecialchars(strtolower(str_replace([' ', '&'], ['-', 'and'], $group['group'])), ENT_QUOTES, 'UTF-8'); ?>">
        <h2 class="h5 mb-3" id="<?php echo htmlspecialchars(strtolower(str_replace([' ', '&'], ['-', 'and'], $group['group'])), ENT_QUOTES, 'UTF-8'); ?>">
            <?php echo htmlspecialchars($group['group'], ENT_QUOTES, 'UTF-8'); ?>
        </h2>
        <div class="row g-3">
            <?php foreach ($group['items'] as $item): ?>
                <div class="col-12 col-md-6 col-xl-4">
                    <a
                        class="card h-100 text-decoration-none border-secondary-subtle"
                        href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8'); ?>"
                    >
                        <div class="card-body d-flex gap-3 align-items-start">
                            <span class="fs-3 text-primary" aria-hidden="true">
                                <i class="bx <?php echo htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8'); ?>"></i>
                            </span>
                            <span class="min-w-0">
                                <span class="d-block fw-semibold text-body mb-1">
                                    <?php echo htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                                <span class="d-block text-muted small">
                                    <?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </span>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php tool_render_footer(); ?>
