<?php
require_once __DIR__ . '/../classes/SiteMail.php';
foreach ([['victim@example.com' . "\r\nBcc: other@example.com", 'Test'], ['recipient@example.com', "Test\r\nBcc: other@example.com"]] as [$to, $subject]) {
    try { SiteMail::send([], $to, $subject, 'Test'); throw new LogicException('Header injection was allowed'); }
    catch (RuntimeException $e) { if ($e->getMessage() !== 'Invalid email.') throw $e; }
}
try { SiteMail::send([], 'recipient@example.com', 'Test', 'Test'); throw new LogicException('Missing credentials accepted'); }
catch (RuntimeException $e) { if ($e->getMessage() !== 'Save the mailbox password first.') throw $e; }
if (dirname(SiteMail::path()) === dirname(__DIR__)) throw new LogicException('Settings inside application');
echo "PASS: header injection rejected, missing credentials rejected, private settings path\n";
