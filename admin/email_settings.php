<?php
ini_set('display_errors', '0');
if (session_status() === PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/SiteMail.php';
if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$db = (new Database())->getConnection();
$stmt = $db->prepare('SELECT user_type FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn() !== 'admin') { http_response_code(403); exit('Administrator access required.'); }
$_SESSION['email_csrf'] ??= bin2hex(random_bytes(32));
function esc($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$message = '';
$config = [];
try {
    $config = SiteMail::load();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!hash_equals($_SESSION['email_csrf'], $_POST['csrf'] ?? '')) { http_response_code(403); exit('Invalid request. Reload the page.'); }
        if (($_POST['action'] ?? '') === 'save') {
            $projectId = filter_var($_POST['project_id'] ?? '', FILTER_VALIDATE_INT);
            $stmt = $db->prepare('SELECT id FROM projects WHERE id = ?');
            $stmt->execute([$projectId]);
            if (!$stmt->fetchColumn()) throw new RuntimeException('Choose a valid test project.');
            $password = $_POST['password'] ?? '';
            if ($password === '') $password = $config['password'] ?? '';
            if ($password === '') throw new RuntimeException('Enter the mailbox password.');
            $config = ['password' => $password, 'project_id' => $projectId, 'test_mode' => isset($_POST['test_mode']), 'recipient' => 'cirlam@gmail.com', 'sender' => 'admin@defecttracker.uk', 'send_enabled' => true];
            SiteMail::save($config);
            $message = 'Settings saved.';
        } elseif (($_POST['action'] ?? '') === 'test') {
            if (time() - ($_SESSION['last_email_test'] ?? 0) < 60) throw new RuntimeException('Wait one minute before sending another test.');
            $_SESSION['last_email_test'] = time();
            SiteMail::send($config, 'cirlam@gmail.com', '[TEST] Defect Tracker website email check', 'This email was sent from the website admin panel. No site action is required.');
            $message = 'Mail server accepted the test. Check the inbox and spam folder; receipt is not yet confirmed.';
        }
    }
} catch (Throwable $e) { $message = $e instanceof RuntimeException ? $e->getMessage() : 'Email settings are unavailable. Check the server configuration.'; }
$projects = $db->query('SELECT id, name FROM projects ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Email settings</title><style>body{font:17px system-ui;background:#f3f5f8;color:#182330;max-width:760px;margin:40px auto;padding:20px}main{background:white;padding:28px;border-radius:12px}label{display:block;margin:20px 0}input[type=password],select{display:block;width:100%;box-sizing:border-box;padding:12px;margin-top:8px}button{padding:12px 20px;cursor:pointer}p{line-height:1.6}.notice{padding:15px;background:#fff1cc}</style></head><body><main><a href="../dashboard.php">Back to dashboard</a><h1>Email settings</h1><p>Sender: <strong>admin@defecttracker.uk</strong><br>Server: mxe97d.netcup.net · Port 465 · SSL/TLS<br>Test recipient: <strong>cirlam@gmail.com</strong></p>
<?php if ($message): ?><p class="notice" role="status"><?= esc($message) ?></p><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= esc($_SESSION['email_csrf']) ?>"><label>Mailbox password <?= empty($config['password']) ? '' : '(saved; leave blank to keep)' ?><input type="password" name="password" autocomplete="new-password"></label><label>Test project<select name="project_id" required><option value="">Choose the disposable test project</option><?php foreach ($projects as $project): ?><option value="<?= (int)$project['id'] ?>" <?= (int)($config['project_id'] ?? 0) === (int)$project['id'] ? 'selected' : '' ?>><?= esc($project['name']) ?></option><?php endforeach; ?></select></label><label><input type="checkbox" name="test_mode" <?= !empty($config['test_mode']) ? 'checked' : '' ?>> Enable test-project notifications</label><p class="notice">While enabled, other in-app and push notifications handled by this notification service are suppressed and will not be replayed. Use only during a quiet testing window. Disabling restores normal in-app/push delivery; production email notifications are not enabled.</p><button name="action" value="save">Save settings</button></form><hr><form method="post"><input type="hidden" name="csrf" value="<?= esc($_SESSION['email_csrf']) ?>"><p>Send one email using the saved settings.</p><button name="action" value="test">Send test to cirlam@gmail.com</button></form></main></body></html>
