
<?php
// CLI-only helper: php hashed-password.php 'a-strong-new-password'
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$password = $argv[1] ?? '';
if ($password === '') {
    fwrite(STDERR, "Usage: php hashed-password.php 'a-strong-new-password'\n");
    exit(1);
}

echo password_hash($password, PASSWORD_DEFAULT) . PHP_EOL;
