<?php
declare(strict_types=1);

const DEFECT_GUARDIAN_NAME = 'Defect Guardian';
const DEFECT_GUARDIAN_TAGLINE = 'Site quality, protected.';
const DEFECT_GUARDIAN_LOGO = '/assets/brand/defect-guardian-logo.svg';
const DEFECT_GUARDIAN_MARK = '/assets/brand/defect-guardian-mark.svg';

function defectTrackerDefaultBranding(): array
{
    return [
        'name' => defined('APP_NAME') ? (string) APP_NAME : DEFECT_GUARDIAN_NAME,
        'tagline' => class_exists('Environment') ? Environment::get('APP_TAGLINE', DEFECT_GUARDIAN_TAGLINE) : DEFECT_GUARDIAN_TAGLINE,
        'logo' => DEFECT_GUARDIAN_LOGO,
        'mark' => DEFECT_GUARDIAN_MARK,
        'is_custom' => false,
    ];
}

function defectTrackerNormaliseBrandLogo(?string $path): ?string
{
    $path = trim((string) $path);
    if ($path === '') {
        return null;
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }

    $path = ltrim($path, '/');
    if (stripos($path, 'uploads/logos/') === 0) {
        return '/' . $path;
    }
    return '/uploads/logos/' . $path;
}

/**
 * Resolve branding for this installation. Each subdomain normally has its own
 * database/configuration, so an uploaded company logo remains tenant-specific.
 */
function defectTrackerResolveBranding(?PDO $db): array
{
    $brand = defectTrackerDefaultBranding();
    if (!$db) {
        return $brand;
    }

    try {
        $statement = $db->prepare(
            "SELECT config_key, config_value FROM system_configurations "
            . "WHERE config_key IN ('company_logo_path', 'company_display_name')"
        );
        $statement->execute();
        $configuration = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $configuration[(string) $row['config_key']] = (string) $row['config_value'];
        }

        $configuredLogo = defectTrackerNormaliseBrandLogo($configuration['company_logo_path'] ?? null);
        if ($configuredLogo !== null) {
            $brand['logo'] = $configuredLogo;
            $brand['is_custom'] = true;
        }
        $displayName = trim((string) ($configuration['company_display_name'] ?? ''));
        if ($displayName !== '') {
            $brand['name'] = substr($displayName, 0, 120);
        }
    } catch (Throwable $configurationError) {
        error_log('Brand configuration lookup failed: ' . $configurationError->getMessage());
    }

    if ($brand['is_custom']) {
        return $brand;
    }

    try {
        $companyId = defined('COMPANY_CONTRACTOR_ID') ? COMPANY_CONTRACTOR_ID : 1;
        $statement = $db->prepare('SELECT logo FROM contractors WHERE id = :company_id LIMIT 1');
        $statement->execute([':company_id' => $companyId]);
        $legacyLogo = defectTrackerNormaliseBrandLogo($statement->fetchColumn() ?: null);
        if ($legacyLogo !== null) {
            $brand['logo'] = $legacyLogo;
            $brand['is_custom'] = true;
        }
    } catch (Throwable $legacyError) {
        error_log('Legacy brand lookup failed: ' . $legacyError->getMessage());
    }

    return $brand;
}
