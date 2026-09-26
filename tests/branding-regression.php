<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/branding.php';

function brandCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function brandSource(string $path): string
{
    global $root;
    $source = file_get_contents($root . DIRECTORY_SEPARATOR . $path);
    if ($source === false) {
        throw new RuntimeException("Unable to read {$path}");
    }
    return $source;
}

$default = defectTrackerDefaultBranding();
brandCheck($default['name'] === 'Defect Guardian', 'Default product name is incorrect.');
brandCheck($default['logo'] === '/assets/brand/defect-guardian-logo.svg', 'Default logo is not the Defect Guardian lockup.');
brandCheck($default['is_custom'] === false, 'Default branding is incorrectly marked as tenant branding.');
brandCheck(defectTrackerBrandLogoAvailable($default['logo']), 'Bundled Guardian logo is missing.');
brandCheck(!defectTrackerBrandLogoAvailable('/uploads/logos/missing-company-logo.png'), 'Missing tenant logo is incorrectly considered available.');

$navbar = brandSource('includes/navbar.php');
brandCheck(str_contains($navbar, 'defectTrackerResolveBranding'), 'Navbar does not resolve tenant-aware branding.');
brandCheck(!str_contains($navbar, '<span>McGoff Defect Tracker</span>'), 'Navbar still has a McGoff fallback.');

$login = brandSource('login.php');
brandCheck(str_contains($login, "\$loginBrand['logo']"), 'Login does not use resolved tenant branding.');
brandCheck(!str_contains($login, 'https://mcgoff.defecttracker.uk/mcgoff.png'), 'Login still hard-codes the McGoff logo.');

foreach (['manifest.json', 'favicons/site.webmanifest'] as $manifestPath) {
    $manifest = json_decode(brandSource($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    brandCheck(($manifest['name'] ?? '') === 'Defect Guardian', "{$manifestPath} has the wrong app name.");
}

foreach ([
    'favicons/favicon-96x96.png' => [96, 96],
    'favicons/apple-touch-icon.png' => [180, 180],
    'favicons/web-app-manifest-192x192.png' => [192, 192],
    'favicons/web-app-manifest-512x512.png' => [512, 512],
] as $path => $expectedSize) {
    $size = getimagesize($root . DIRECTORY_SEPARATOR . $path);
    brandCheck($size !== false && [$size[0], $size[1]] === $expectedSize, "{$path} has the wrong dimensions.");
}

foreach (glob($root . '/presentations/*.html') ?: [] as $presentation) {
    $source = file_get_contents($presentation) ?: '';
    brandCheck(str_contains($source, 'presentation-brand-mark'), basename($presentation) . ' does not use the presentation logo.');
    brandCheck(str_contains($source, '../favicons/favicon.svg'), basename($presentation) . ' does not use the Guardian favicon.');
    brandCheck(!str_contains($source, 'McGoff Defect Tracker'), basename($presentation) . ' still has legacy showcase branding.');
}

echo "PASS: tenant-aware Defect Guardian fallback branding and icon assets are wired.\n";
