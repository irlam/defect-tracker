<?php
declare(strict_types=1);
namespace DefectTracker\Suite;

use PDO;
use RuntimeException;
use Throwable;

/** Deployment-owned configuration only; no legacy .env or example fallback. */
final class Configuration
{
    public static function guard(): string
    {
        return "if (PHP_SAPI !== 'cli' && realpath((string) (\$_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) { http_response_code(404); exit; }";
    }

    public static function load(string $applicationRoot, ?string $configurationFile = null): array
    {
        $level = ob_get_level();
        ob_start();
        set_error_handler(static function (): never { throw new RuntimeException(); });
        try {
            $root = realpath($applicationRoot);
            if (!$root || !is_dir($root)) throw new RuntimeException();
            $default = $root . '/config/runtime.suite.private.php';
            $candidate = $configurationFile ?? (getenv('DEFECTS_SUITE_CONFIG_FILE') ?: $default);
            $file = realpath($candidate);
            if (!$file || !is_file($file) || !self::unlinked($candidate) || (fileperms($file) & 0077) !== 0) throw new RuntimeException();
            $source = file_get_contents($file);
            if (!is_string($source) || strlen($source) > 65536) throw new RuntimeException();
            if (self::within($file, $root)) {
                $prefix = "<?php\ndeclare(strict_types=1);\n" . self::guard() . "\nreturn ";
                if ($file !== $default || !str_starts_with($source, $prefix)) throw new RuntimeException();
            }
            // Contain accidental output, warnings and implementation details.
            $config = (static function (string $path): mixed { return require $path; })($file);
            $output = ob_get_contents();
            if ($output !== '' || !is_array($config)) throw new RuntimeException();
            return self::validate($config, $root);
        } catch (Throwable $e) {
            throw new RuntimeException('Private staging configuration unavailable.');
        } finally {
            restore_error_handler();
            while (ob_get_level() > $level) ob_end_clean();
        }
    }

    public static function validate(array $config, string $applicationRoot): array
    {
        try {
            new Gateway($config); // Validates the immutable Suite/instance binding.
            $db = $config['database'] ?? null;
            if (!is_array($db)) throw new RuntimeException();
            foreach (['host', 'name', 'username', 'password'] as $field) {
                if (!is_string($db[$field] ?? null) || $db[$field] === '' || str_contains($db[$field], "\0")) throw new RuntimeException();
            }
            if (!preg_match('/^[A-Za-z0-9.-]+$/D', $db['host']) || !preg_match('/^[A-Za-z0-9_]+_stage$/D', $db['name']) || !preg_match('/^[A-Za-z0-9_]+_stage$/D', $db['username']) || !is_int($db['port'] ?? null) || $db['port'] < 1 || $db['port'] > 65535) throw new RuntimeException();
            $root = realpath($applicationRoot);
            if (!$root) throw new RuntimeException();
            foreach (['upload_root', 'session_root'] as $field) {
                $candidate = $config[$field] ?? null;
                if (!is_string($candidate) || !str_starts_with($candidate, DIRECTORY_SEPARATOR)) throw new RuntimeException();
                $path = realpath($candidate);
                if (!$path || !is_dir($path) || !self::unlinked($candidate) || self::within($path, $root) || self::within($root, $path) || (fileperms($path) & 0077) !== 0 || !is_writable($path)) throw new RuntimeException();
                $config[$field] = $path;
            }
            if (self::within($config['upload_root'], $config['session_root']) || self::within($config['session_root'], $config['upload_root'])) throw new RuntimeException();
            return $config;
        } catch (Throwable $e) {
            throw new RuntimeException('Invalid staging configuration.');
        }
    }

    public static function connect(array $config): PDO
    {
        // Only callers holding load()/validate() output should call this method.
        try {
            $db = $config['database'];
            return new PDO('mysql:host=' . $db['host'] . ';port=' . $db['port'] . ';dbname=' . $db['name'] . ';charset=utf8mb4', $db['username'], $db['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        } catch (Throwable $e) {
            throw new RuntimeException('Staging database unavailable.');
        }
    }

    private static function within(string $path, string $parent): bool
    {
        return $path === $parent || str_starts_with($path, rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR);
    }

    private static function unlinked(string $path): bool
    {
        // Canonical equality rejects symlinks anywhere in the path without
        // probing ancestors above the hosting account's open_basedir boundary.
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            && realpath($path) === $path
            && !is_link($path);
    }
}
