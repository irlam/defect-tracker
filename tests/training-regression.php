<?php
declare(strict_types=1);

require_once __DIR__ . '/../training/TrainingRepository.php';
require_once __DIR__ . '/../training/TrainingContent.php';

$failures = [];

function trainingAssert(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$repository = new TrainingRepository(null);
$adminModules = $repository->getModulesForUser(1, 'admin');
$adminLessons = $repository->getLessonsForUser(1, 'admin');
$contractorModules = $repository->getModulesForUser(2, 'contractor');
$contractorLessons = $repository->getLessonsForUser(2, 'contractor');

trainingAssert(count($adminModules) === 7, 'Fallback academy should expose all seven modules to an administrator.');
trainingAssert(count($adminLessons) === 7, 'Fallback training matrix should expose seven administrator lessons.');
trainingAssert(count($contractorModules) === 2, 'Fallback academy should hide modules with no contractor lessons.');
trainingAssert(count($contractorLessons) === 2, 'Fallback training matrix should only expose all-role lessons to a contractor.');

foreach ($adminLessons as $lesson) {
    $slug = (string)($lesson['slug'] ?? '');
    $enhanced = trainingEnhancedContent($slug);
    trainingAssert($slug !== '', 'Every matrix lesson must have a slug.');
    trainingAssert(count($enhanced['demo'] ?? []) >= 5, $slug . ' should provide a video-style walkthrough with at least five scenes.');
    trainingAssert(count($enhanced['knowledge'] ?? []) >= 3, $slug . ' should include a meaningful knowledge check.');
    trainingAssert((int)($lesson['estimated_minutes'] ?? 0) > 0, $slug . ' should include an estimated duration.');
    trainingAssert(isset($lesson['progress_status'], $lesson['progress_percent']), $slug . ' should expose competency progress to the matrix.');
}

$indexSource = file_get_contents(__DIR__ . '/../training/index.php') ?: '';
$scriptSource = file_get_contents(__DIR__ . '/../training/training.js') ?: '';
$styleSource = file_get_contents(__DIR__ . '/../training/training.css') ?: '';
$lessonSource = file_get_contents(__DIR__ . '/../training/lesson.php') ?: '';

trainingAssert(str_contains($indexSource, 'End-user training matrix'), 'Training hub must render the end-user training matrix.');
trainingAssert(str_contains($indexSource, 'data-training-search'), 'Training hub must provide catalogue search.');
trainingAssert(str_contains($indexSource, 'trainingRenderModulePreview'), 'Every module must render an animated interface preview.');
trainingAssert(str_contains($scriptSource, 'data-training-filter'), 'Training JavaScript must support audience filtering.');
trainingAssert(str_contains($styleSource, '@media (max-width: 767.98px)'), 'Training styles must include the mobile layout.');
trainingAssert(!str_contains($lessonSource, 'Phase 1 lesson engine'), 'Lessons must not show obsolete Phase 1 wording.');

if ($failures) {
    fwrite(STDERR, "Training regression failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Training regressions passed (7 modules, animated lesson coverage, role matrix, search and responsive layout).\n";
