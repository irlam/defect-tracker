<?php
ini_set('display_errors','0');
if (session_status()===PHP_SESSION_NONE) session_start();
header('Cache-Control: no-store');
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/../classes/SiteMail.php';

if (empty($_SESSION['user_id'])) { header('Location: ../login.php'); exit; }
$db=(new Database())->getConnection();
if (!$db) { http_response_code(503); exit('Database unavailable.'); }
$stmt=$db->prepare('SELECT user_type FROM users WHERE id=?');
$stmt->execute([$_SESSION['user_id']]);
if ($stmt->fetchColumn()!=='admin') { http_response_code(403); exit('Administrator access required.'); }

$_SESSION['email_csrf'] ??= bin2hex(random_bytes(32));
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$message='';
$config=SiteMail::load();
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        if (!hash_equals((string)$_SESSION['email_csrf'],(string)($_POST['csrf'] ?? ''))) {
            http_response_code(403); exit('Invalid request. Reload the page.');
        }
        if (($_POST['action'] ?? '')==='save') {
            $projectId=filter_var($_POST['project_id'] ?? '',FILTER_VALIDATE_INT);
            if ($projectId) {
                $stmt=$db->prepare('SELECT id FROM projects WHERE id=?');
                $stmt->execute([$projectId]);
                if (!$stmt->fetchColumn()) throw new RuntimeException('Choose a valid test project.');
            }
            $password=(string)($_POST['password'] ?? '');
            $config=[
                'host'=>trim((string)($_POST['host'] ?? '')),
                'port'=>(int)($_POST['port'] ?? 465),
                'encryption'=>in_array($_POST['encryption'] ?? '',['ssl','tls','none'],true)?$_POST['encryption']:'ssl',
                'username'=>trim((string)($_POST['username'] ?? '')),
                'password'=>$password!==''?$password:(string)($config['password'] ?? ''),
                'sender'=>trim((string)($_POST['sender'] ?? '')),
                'sender_name'=>trim((string)($_POST['sender_name'] ?? 'Defect Tracker')),
                'recipient'=>trim((string)($_POST['recipient'] ?? '')),
                'project_id'=>$projectId ?: null,
                'test_mode'=>isset($_POST['test_mode']),
                'send_enabled'=>true,
            ];
            if ($config['host']==='' || $config['username']==='' || $config['password']==='' || !filter_var($config['sender'],FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Complete the SMTP host, username, password and valid sender address.');
            }
            if ($config['recipient']!=='' && !filter_var($config['recipient'],FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Enter a valid test recipient.');
            }
            SiteMail::save($config);
            $message='Settings saved.';
        } elseif (($_POST['action'] ?? '')==='test') {
            if (time()-(int)($_SESSION['last_email_test'] ?? 0)<60) throw new RuntimeException('Wait one minute before sending another test.');
            if (empty($config['recipient'])) throw new RuntimeException('Save a test recipient first.');
            $_SESSION['last_email_test']=time();
            SiteMail::send($config,$config['recipient'],'[TEST] Defect Tracker email check','This is a Defect Tracker test email. No action is required.');
            $message='Mail server accepted the test message.';
        }
    }
} catch (Throwable $e) {
    $message=$e instanceof RuntimeException?$e->getMessage():'Email settings are unavailable. Check the server configuration.';
}
$projects=$db->query('SELECT id,name FROM projects ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Email settings</title>
<style>body{font:17px system-ui;background:#f3f5f8;color:#182330;max-width:820px;margin:40px auto;padding:20px}main{background:white;padding:28px;border-radius:12px}label{display:block;margin:16px 0}input,select{display:block;width:100%;box-sizing:border-box;padding:12px;margin-top:7px}input[type=checkbox]{display:inline;width:auto}button{padding:12px 20px;cursor:pointer}.notice{padding:15px;background:#fff1cc}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}@media(max-width:700px){.grid{grid-template-columns:1fr}}</style>
</head><body><main><a href="../dashboard.php">Back to dashboard</a><h1>Email settings</h1>
<?php if($message):?><p class="notice" role="status"><?=esc($message)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['email_csrf'])?>">
<div class="grid"><label>SMTP host<input name="host" required value="<?=esc($config['host'] ?? '')?>"></label><label>Port<input type="number" min="1" max="65535" name="port" required value="<?=(int)($config['port'] ?? 465)?>"></label></div>
<div class="grid"><label>Encryption<select name="encryption"><option value="ssl" <?=($config['encryption'] ?? '')==='ssl'?'selected':''?>>SSL/TLS</option><option value="tls" <?=($config['encryption'] ?? '')==='tls'?'selected':''?>>STARTTLS</option><option value="none" <?=($config['encryption'] ?? '')==='none'?'selected':''?>>None</option></select></label><label>SMTP username<input name="username" required value="<?=esc($config['username'] ?? '')?>"></label></div>
<label>SMTP password <?=empty($config['password'])?'':'(saved; leave blank to keep)'?><input type="password" name="password" autocomplete="new-password"></label>
<div class="grid"><label>Sender email<input type="email" name="sender" required value="<?=esc($config['sender'] ?? '')?>"></label><label>Sender name<input name="sender_name" value="<?=esc($config['sender_name'] ?? 'Defect Tracker')?>"></label></div>
<label>Test recipient<input type="email" name="recipient" value="<?=esc($config['recipient'] ?? '')?>"></label>
<label>Test project<select name="project_id"><option value="">None</option><?php foreach($projects as $project):?><option value="<?=(int)$project['id']?>" <?=(int)($config['project_id'] ?? 0)===(int)$project['id']?'selected':''?>><?=esc($project['name'])?></option><?php endforeach;?></select></label>
<label><input type="checkbox" name="test_mode" <?=!empty($config['test_mode'])?'checked':''?>> Enable test-project notification mode</label>
<button name="action" value="save">Save settings</button></form><hr>
<form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['email_csrf'])?>"><button name="action" value="test">Send test email</button></form>
</main></body></html>
