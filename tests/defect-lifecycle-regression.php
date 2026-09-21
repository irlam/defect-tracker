<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/includes/defect_workflow.php';

function lifecycleSource(string $relativePath): string
{
    global $root;
    $contents = file_get_contents($root . DIRECTORY_SEPARATOR . $relativePath);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$relativePath}");
    }
    return $contents;
}

function lifecycleCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$expectedTransitions = [
    ['start', 'open', 'in_progress'],
    ['start', 'rejected', 'in_progress'],
    ['submit', 'in_progress', 'pending'],
    ['accept', 'pending', 'accepted'],
    ['reject', 'pending', 'rejected'],
    ['reopen', 'accepted', 'open'],
];

foreach ($expectedTransitions as [$action, $from, $to]) {
    lifecycleCheck(defectWorkflowAssertTransition($action, $from) === $to, "{$action} transition is incorrect.");
}

$invalidTransitionRejected = false;
try {
    defectWorkflowAssertTransition('accept', 'open');
} catch (RuntimeException) {
    $invalidTransitionRejected = true;
}
lifecycleCheck($invalidTransitionRejected, 'Open defects can still bypass pending review.');

$create = lifecycleSource('create_defect.php');
lifecycleCheck(str_contains($create, 'defectWorkflowAssignContractorUsers'), 'New defects are not routed to contractor task queues.');
lifecycleCheck(str_contains($create, '$db->beginTransaction();'), 'Defect creation and task routing are not transactional.');
lifecycleCheck(!str_contains($create, "createDefectForm.addEventListener('submit', function(event) {\n            event.preventDefault();"), 'Create-defect submission is still unconditionally cancelled.');
lifecycleCheck(str_contains($create, 'min-height: 48px'), 'Create-defect mobile touch targets are not protected.');

$task = lifecycleSource('view_defect_mytasks.php');
lifecycleCheck(str_contains($task, 'defectWorkflowCanAccessTask'), 'Task detail access is not restricted to assignees.');
lifecycleCheck(str_contains($task, 'action="start_defect.php"'), 'Start Work action is missing.');
lifecycleCheck(substr_count($task, 'name="csrf_token"') >= 2, 'Task lifecycle forms are missing CSRF tokens.');
lifecycleCheck(str_contains($task, 'capture="environment"'), 'Mobile camera capture is not enabled.');

$upload = lifecycleSource('upload_completed_images.php');
lifecycleCheck(str_contains($upload, 'new finfo(FILEINFO_MIME_TYPE)'), 'Completion photos are not validated by content type.');
lifecycleCheck(str_contains($upload, "defectWorkflowAssertTransition('submit'"), 'Completion submission does not enforce lifecycle state.');
lifecycleCheck(str_contains($upload, '$db->rollBack();'), 'Completion submission cannot roll back partial failures.');

$review = lifecycleSource('includes/handle_defect_review.php');
lifecycleCheck(str_contains($review, 'defectWorkflowIsReviewer'), 'Review actions do not enforce manager access.');
lifecycleCheck(str_contains($review, 'defectWorkflowHasValidCsrf'), 'Review actions do not validate CSRF tokens.');
lifecycleCheck(str_contains($review, 'defectWorkflowAssertTransition'), 'Review actions do not enforce lifecycle state.');

$tasks = lifecycleSource('my_tasks.php');
lifecycleCheck(str_contains($tasks, 'd.contractor_id'), 'Task list still uses the legacy contractor relationship.');
lifecycleCheck(str_contains($tasks, 'mobile-task-card'), 'Mobile task cards are missing.');
lifecycleCheck(str_contains($tasks, "d.status IN ('accepted', 'completed', 'verified')"), 'Completed task statistics use invalid lifecycle states.');

$defects = lifecycleSource('defects.php');
lifecycleCheck(substr_count($defects, 'name="csrf_token"') >= 3, 'Manager lifecycle forms are missing CSRF tokens.');
lifecycleCheck(str_contains($defects, "d.status IN ('accepted', 'completed', 'verified')"), 'Defect metrics use invalid lifecycle states.');
lifecycleCheck(str_contains($defects, 'u.user_type'), 'Manager controls do not honor the authenticated user type.');
lifecycleCheck(str_contains($defects, "'in_progress', 'pending', 'completed', 'verified'"), 'Lifecycle status filters are incomplete.');

echo "PASS: defect lifecycle transitions, authorization hooks, uploads, and mobile flows are covered.\n";
