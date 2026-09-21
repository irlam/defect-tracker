<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function source(string $relativePath): string
{
    global $root;
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . $relativePath);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$relativePath}");
    }
    return $contents;
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$defects = source('defects.php');
check(substr_count($defects, ':search_title') === 2, 'Defect title search placeholder is not unique and bound.');
check(substr_count($defects, ':search_description') === 2, 'Defect description search placeholder is not unique and bound.');
check(substr_count($defects, ':search_contractor') === 2, 'Contractor search placeholder is not unique and bound.');

$floorPlans = source('floor_plans.php');
check(substr_count($floorPlans, 'initializePDFPreviews();') === 1, 'PDF previews must have one initialization path.');
check(str_contains($floorPlans, "previewState === 'loading'"), 'PDF preview re-entry guard is missing.');
check(str_contains($floorPlans, '!empty($plan[\'image_path\'])'), 'Generated floor-plan images are not used for fast previews.');
check(str_contains($floorPlans, "querySelector('img.floor-plan-image')"), 'Image previews are not excluded from the PDF.js fallback.');
check(str_contains($floorPlans, 'floor-plans-card'), 'Floor-plan responsive card hook is missing.');

$appCss = source('css/app.css');
check(substr_count($appCss, '{') === substr_count($appCss, '}'), 'Shared stylesheet braces are unbalanced.');
check(str_contains($appCss, 'width: clamp(88px, 12vw, 150px);'), 'Floor-plan previews do not scale with the viewport.');
check(str_contains($appCss, 'overflow-x: auto;'), 'Responsive table scrolling is missing.');

$navbar = source('includes/navbar.php');
check(str_contains($navbar, 'filemtime($themeFile)'), 'Shared stylesheet cache busting is missing.');

$contractor = source('view_contractor.php');
check(!str_contains($contractor, "includes/sidebar.php"), 'Contractor page still loads the removed sidebar.');
check(str_contains($contractor, '$navbar->render();'), 'Contractor page navbar is missing.');
check(!str_contains($contractor, 'edit_contractor.php'), 'Contractor page links to a missing edit route.');

$stream = source('api/notification_stream.php');
check(str_contains($stream, 'new Database()'), 'Notification stream does not create a database connection.');
check(str_contains($stream, 'id: $id'), 'Notification stream does not emit resumable event IDs.');

$visualizer = source('visualize_defects.php');
check(str_contains($visualizer, 'name="floor_plan_id"'), 'Visualizer floor-plan picker is missing.');
check(!str_contains($visualizer, 'floorplan_selector.php'), 'Visualizer still links to the broken selector route.');
check(str_contains($visualizer, "NULLIF(f.image_path, '')"), 'Visualizer does not prefer the preview image.');

echo "PASS: functional regressions 1-5 are covered.\n";
