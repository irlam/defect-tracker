<?php
require_once __DIR__ . '/../classes/TestNotificationRouter.php';
class TestRouteDb {
    public $project = 42;
    public function prepare($sql) { return $this; }
    public function execute($params) { return true; }
    public function fetchColumn() { return $this->project; }
}
function check($value, $message) {
    if (!$value) throw new RuntimeException($message);
}
$db = new TestRouteDb();
$calls = [];
$transport = function (...$args) use (&$calls) { $calls[] = $args; return true; };
$config = ['project_id' => 42, 'recipient' => 'recipient@example.com', 'sender' => 'sender@example.com', 'send_enabled' => true];
$router = new TestNotificationRouter($db, $config, $transport);
foreach (['created', 'assigned', 'status_changed', 'comment_added', 'push'] as $event) {
    check($router->route(123, $event)['success'], 'Expected test delivery');
}
check(count($calls) === 5 && $calls[0][0] === $config['recipient'], 'Wrong recipient/count');
$db->project = 43;
check($router->route(123, 'created') !== null, 'Unrelated project must not fall back');
check(count($calls) === 5, 'Unrelated project sent mail');
$db->project = 42;
foreach ([[], array_merge($config, ['send_enabled' => false]), array_merge($config, ['recipient' => "a@example.com\r\nBcc: b@example.com"]), array_merge($config, ['sender' => ''])] as $invalid) {
    check(!(new TestNotificationRouter($db, $invalid, $transport))->route(123, 'created')['success'], 'Unsafe configuration sent mail');
}
check(count($calls) === 5, 'Invalid configuration called transport');
foreach ([function () { return false; }, function () { throw new RuntimeException('Transport failed'); }] as $failure) {
    check((new TestNotificationRouter($db, $config, $failure))->route(123, 'created') !== null, 'Failure allowed fallback');
}
check($router->route(null, 'push') !== null, 'Unlinked broadcast allowed fallback');
check(count($calls) === 5, 'Unlinked broadcast sent mail');
echo "PASS: single recipient, all events, project isolation, disabled/invalid settings, transport failures, unlinked broadcasts\n";
