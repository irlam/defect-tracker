<?php
declare(strict_types=1);

$root = dirname(__DIR__);

function offlineSource(string $path): string
{
    global $root;
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . $path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$path}");
    }
    return $contents;
}

function offlineCheck(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$queue = offlineSource('js/offline-defect-queue.js');
offlineCheck(str_contains($queue, "indexedDB.open(DB_NAME"), 'Offline outbox does not use IndexedDB.');
offlineCheck(str_contains($queue, "formData.set('client_submission_id'"), 'Submission idempotency key is missing.');
offlineCheck(str_contains($queue, "window.addEventListener('online'"), 'Automatic reconnect sync is missing.');
offlineCheck(str_contains($queue, "registration.sync.register('sync-field-reports')"), 'Background sync registration is missing.');

$create = offlineSource('create_defect.php');
offlineCheck(str_contains($create, "HTTP_X_OFFLINE_SUBMISSION"), 'JSON field submission mode is missing.');
offlineCheck(str_contains($create, 'WHERE client_id = :client_id AND reported_by = :reported_by'), 'Duplicate submission lookup is missing.');
offlineCheck(str_contains($create, "bindValue(':client_id'"), 'Client submission id is not persisted.');
offlineCheck(str_contains($create, 'SELECT GET_LOCK(:lock_name, 10)'), 'Concurrent duplicate protection is missing.');

$context = offlineSource('api/offline_field_context.php');
offlineCheck(str_contains($context, "http_response_code(401)"), 'Offline reference data is not authentication protected.');
offlineCheck(str_contains($context, "'csrfToken'"), 'Fresh sync CSRF token is missing.');

$worker = offlineSource('service-worker.js');
offlineCheck(str_contains($worker, "'/offline-field.html'"), 'Offline field shell is not precached.');
offlineCheck(str_contains($worker, 'syncFieldReportsInBackground'), 'Closed-app background upload is missing.');
offlineCheck(!str_contains($worker, "'/uploads/defects/"), 'Private defect images must not be precached.');

$fieldPage = offlineSource('offline-field.html');
offlineCheck(str_contains($fieldPage, 'capture="environment"'), 'Mobile camera capture is missing.');
offlineCheck(str_contains($fieldPage, 'id="floorPin"'), 'Offline floor-plan pin control is missing.');

echo "PASS: offline field capture, durable queue, reconnect sync and duplicate protection are wired.\n";
