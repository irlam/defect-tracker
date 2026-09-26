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

$narrationCount = 0;
foreach ($adminLessons as $lesson) {
    $slug = (string)($lesson['slug'] ?? '');
    $enhanced = trainingEnhancedContent($slug);
    trainingAssert($slug !== '', 'Every matrix lesson must have a slug.');
    trainingAssert(count($enhanced['demo'] ?? []) >= 5, $slug . ' should provide a video-style walkthrough with at least five scenes.');
    trainingAssert(count($enhanced['knowledge'] ?? []) >= 3, $slug . ' should include a meaningful knowledge check.');
    trainingAssert((int)($lesson['estimated_minutes'] ?? 0) > 0, $slug . ' should include an estimated duration.');
    trainingAssert(isset($lesson['progress_status'], $lesson['progress_percent']), $slug . ' should expose competency progress to the matrix.');

    foreach (($enhanced['demo'] ?? []) as $sceneIndex => $_scene) {
        $audioFile = sprintf(
            '%s/../assets/training/audio/%s/scene-%02d.mp3',
            __DIR__,
            $slug,
            $sceneIndex + 1
        );
        trainingAssert(is_file($audioFile), $slug . ' scene ' . ($sceneIndex + 1) . ' should have recorded narration.');
        trainingAssert((int)@filesize($audioFile) > 10000, $slug . ' scene ' . ($sceneIndex + 1) . ' narration should not be empty.');
        $narrationCount++;
    }
}
trainingAssert($narrationCount === 45, 'The Academy should contain all 45 VoxCPM2 narration clips.');

$voiceReference = __DIR__ . '/../assets/training/voice/defect-guardian-narrator-reference.mp3';
$voiceGeneratorSource = file_get_contents(__DIR__ . '/../scripts/generate_training_voiceovers.py') ?: '';
trainingAssert(is_file($voiceReference), 'The approved Academy narrator reference must be versioned with the application.');
trainingAssert((int)@filesize($voiceReference) > 10000, 'The approved Academy narrator reference must not be empty.');
trainingAssert(
    hash_file('sha256', $voiceReference) === '8c4a7b5f5144fcc69f29b3b274a04e93537d8044028df35c4629f61bb740949f',
    'The approved Academy narrator reference must not change accidentally.'
);
trainingAssert(
    str_contains($voiceGeneratorSource, 'DEFAULT_REFERENCE_AUDIO')
        && str_contains($voiceGeneratorSource, 'reference_wav_path=str(reference_audio)'),
    'Future lesson narration must clone the approved Academy narrator reference.'
);

$indexSource = file_get_contents(__DIR__ . '/../training/index.php') ?: '';
$scriptSource = file_get_contents(__DIR__ . '/../training/training.js') ?: '';
$styleSource = file_get_contents(__DIR__ . '/../training/training.css') ?: '';
$lessonSource = file_get_contents(__DIR__ . '/../training/lesson.php') ?: '';
$bootstrapSource = file_get_contents(__DIR__ . '/../training/bootstrap.php') ?: '';

trainingAssert(str_contains($indexSource, 'End-user training matrix'), 'Training hub must render the end-user training matrix.');
trainingAssert(str_contains($indexSource, 'data-training-search'), 'Training hub must provide catalogue search.');
trainingAssert(str_contains($indexSource, 'trainingRenderModulePreview'), 'Every module must render an animated interface preview.');
trainingAssert(str_contains($scriptSource, 'data-training-filter'), 'Training JavaScript must support audience filtering.');
trainingAssert(str_contains($scriptSource, 'recordedAudio'), 'Training JavaScript must prefer recorded narration.');
trainingAssert(str_contains($scriptSource, 'playSequence'), 'Auto play must follow narration duration rather than a fixed timer.');
trainingAssert(str_contains($styleSource, '@media (max-width: 767.98px)'), 'Training styles must include the mobile layout.');
trainingAssert(!str_contains($lessonSource, 'Phase 1 lesson engine'), 'Lessons must not show obsolete Phase 1 wording.');
trainingAssert(str_contains($lessonSource, 'data-demo-audio'), 'Lesson scenes must expose recorded audio paths.');
trainingAssert(str_contains($bootstrapSource, 'filemtime'), 'Training assets must be cache-busted after deployment.');

if ($failures) {
    fwrite(STDERR, "Training regression failures:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "Training regressions passed (7 modules, 45 VoxCPM2 narrations, role matrix, search and responsive layout).\n";
