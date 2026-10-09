<?php
declare(strict_types=1);
require dirname(__DIR__).'/staging/bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')stageFail(405);
if(empty($_COOKIE[\DefectTracker\Suite\CookiePolicy::SESSION])){header('Location: /suite-login.php',true,303);exit;}
$current=stageCurrent();$editable=in_array($current['identity']['role'],['platform_admin','admin','manager','site_manager'],true);
$project=$db->query('SELECT name FROM projects')->fetchColumn();
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="<?=stageEscape($current['csrf'])?>"><title>Defects · <?=stageEscape((string)$project)?></title><link rel="stylesheet" href="/style.css"><script defer src="/app.js"></script></head><body>
<header><a href="https://suite.defecttracker.uk/">Construction Suite</a><form method="post" action="/suite-logout.php"><input type="hidden" name="csrf" value="<?=stageEscape($current['csrf'])?>"><button>Sign out</button></form></header>
<main><div class="heading"><div><small><?=stageEscape((string)$project)?></small><h1>Defects register</h1><p><?=stageEscape($current['identity']['name'])?> · <?=stageEscape($current['identity']['role'])?></p></div><a class="button" href="/export.php">Export CSV</a></div>
<?php if(!$editable):?><p class="notice">You can view defects and download attachments and reports. Contact your company administrator if you need to make changes.</p><?php endif;?>
<p id="message" role="status"></p>
<?php if($editable):?><section><h2 id="form-title">Add a defect</h2><form id="defect-form"><input type="hidden" name="id"><label>Title<input name="title" maxlength="190" required></label><label>Description<textarea name="description" maxlength="10000" rows="3"></textarea></label><div class="columns"><label>Priority<select name="priority"><option>normal</option><option>low</option><option>high</option><option>critical</option></select></label><label>Status<select name="status" disabled><option>open</option><option>in_progress</option><option>pending</option><option>accepted</option><option>rejected</option></select></label></div><div class="actions"><button type="submit" class="button">Save defect</button><button type="button" id="cancel-edit">Clear form</button></div></form></section><?php endif;?>
<section><div class="heading"><h2>Project defects</h2><button id="refresh">Refresh</button></div><div id="defects" data-editable="<?=$editable?'1':'0'?>">Loading…</div></section></main></body></html>
