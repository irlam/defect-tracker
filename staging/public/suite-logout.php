<?php
declare(strict_types=1);
require dirname(__DIR__).'/bootstrap.php';
use DefectTracker\Suite\CookiePolicy;
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')stageFail(405);
$id=$_COOKIE[CookiePolicy::SESSION]??'';$csrf=$_POST['csrf']??'';
if(!is_string($id)||!is_string($csrf))stageFail(403);
try{$revoked=$flow->logout($id,'POST',$csrf);setcookie(CookiePolicy::SESSION,'',CookiePolicy::options(1));}
catch(Throwable $e){stageFail(403);}
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Signed out</title><h1>Signed out</h1><p>Your access on this device has ended.</p><?php if(!$revoked):?><p>Construction Suite could not confirm the remote logout. Sign out there too.</p><?php endif;?><a href="https://suite.defecttracker.uk/">Return to Construction Suite</a></html>
