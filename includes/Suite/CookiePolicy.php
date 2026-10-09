<?php
declare(strict_types=1);
namespace DefectTracker\Suite;

use RuntimeException;

final class CookiePolicy
{
    public const SESSION = '__Host-defects-suite';
    public const PENDING = '__Host-defects-suite-pending';

    public static function requireOrigin(array $server, array $binding): void
    {
        new Gateway($binding);
        $host = parse_url($binding['origin'], PHP_URL_HOST);
        // Do not trust forwarded host/scheme headers without a deployment-specific proxy policy.
        if (($server['HTTP_HOST'] ?? '') !== $host || !in_array($server['HTTPS'] ?? '', ['on','1'], true)) throw new RuntimeException('Invalid staging request origin.');
    }

    public static function options(int $expires): array
    {
        return ['expires'=>$expires, 'path'=>'/', 'secure'=>true, 'httponly'=>true, 'samesite'=>'Lax'];
    }

    public static function pendingOptions(int $expires): array
    {
        return array_replace(self::options($expires), ['samesite'=>'None']);
    }

    public static function headers(): array
    {
        return ['Cache-Control'=>'no-store', 'Pragma'=>'no-cache', 'Referrer-Policy'=>'no-referrer', 'X-Content-Type-Options'=>'nosniff'];
    }
}
