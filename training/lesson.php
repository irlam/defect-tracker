<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/TrainingContent.php';

$slug = trim((string)($_GET['lesson'] ?? ''));
if ($slug === '') {
    header('Location: /training/');
    exit;
}

$lesson = $training->getLessonBySlug($slug, $userId, $userRole);
if (!$lesson) {
    http_response_code(404);
    trainingRenderHeader('Lesson not found', 'The requested lesson is unavailable for your role or does not exist.');
    echo '<div id="training-content" class="training-card p-4">';
    echo '<a class="btn btn-primary" href="/training/"><i class="bx bx-left-arrow-alt me-1"></i>Back to Training Hub</a>';
    echo '</div>';
    trainingRenderFooter();
    exit;
}

$content = $lesson['content'] ?? [];
$outcomes = is_array($content['outcomes'] ?? null) ? $content['outcomes'] : [];
$steps = is_array($content['steps'] ?? null) ? $content['steps'] : [];
$progress = $lesson['progress'] ?? ['status'=>'not_started','progress_percent'=>0,'last_position'=>null];
$tryUrl = (string)($content['try_url'] ?? '/dashboard.php');
$enhanced = trainingEnhancedContent($slug);
$demoScenes = is_array($enhanced['demo'] ?? null) ? $enhanced['demo'] : [];
$tips = is_array($enhanced['tips'] ?? null) ? $enhanced['tips'] : [];
$knowledge = is_array($enhanced['knowledge'] ?? null) ? $enhanced['knowledge'] : [];
$lessonIntro = (string)($enhanced['intro'] ?? $lesson['description'] ?? '');

trainingRenderHeader(
    (string)$lesson['title'],
    (string)$lesson['description']
);
?>

<div id="training-content"
     data-training-lesson
     data-lesson-id="<?php echo (int)$lesson['id']; ?>"
     data-schema-ready="<?php echo $training->isReady() ? '1' : '0'; ?>"
     data-csrf="<?php echo trainingEsc($trainingCsrfToken); ?>">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/training/">Training</a></li>
            <li class="breadcrumb-item"><?php echo trainingEsc($lesson['module_title'] ?? 'Module'); ?></li>
            <li class="breadcrumb-item active" aria-current="page"><?php echo trainingEsc($lesson['title']); ?></li>
        </ol>
    </nav>

    <section class="training-lesson-header mb-4" aria-labelledby="lesson-title">
        <div class="row g-4 align-items-start">
            <div class="col-lg">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span class="training-role-chip"><i class="bx bx-layer"></i><?php echo trainingEsc($lesson['module_title'] ?? 'Training'); ?></span>
                    <span class="training-role-chip"><i class="bx bx-time-five"></i><?php echo (int)($lesson['estimated_minutes'] ?? 0); ?> min</span>
                    <span class="training-role-chip"><i class="bx bx-signal-3"></i><?php echo trainingEsc($lesson['difficulty'] ?? 'Beginner'); ?></span>
                    <span class="training-role-chip"><i class="bx bx-user"></i><?php echo trainingEsc(trainingStatusLabel((string)($progress['status'] ?? 'not_started'))); ?></span>
                </div>
                <h1 id="lesson-title" class="display-6 fw-bold mb-3"><?php echo trainingEsc($lesson['title']); ?></h1>
                <p class="lead text-secondary mb-0"><?php echo trainingEsc($lesson['description']); ?></p>
            </div>
            <div class="col-lg-3">
                <div class="training-card p-3">
                    <div class="d-flex justify-content-between small mb-2">
                        <span>Lesson progress</span>
                        <strong><?php echo (int)($progress['progress_percent'] ?? 0); ?>%</strong>
                    </div>
                    <div class="progress training-progress" role="progressbar" aria-valuenow="<?php echo (int)($progress['progress_percent'] ?? 0); ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?php echo (int)($progress['progress_percent'] ?? 0); ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-8">
            <section class="training-lesson-card p-4 mb-4" aria-labelledby="demo-heading">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
                    <div>
                        <span class="training-eyebrow"><i class="bx bx-play-circle"></i> Guided walkthrough</span>
                        <h2 id="demo-heading" class="h4 mt-2 mb-1">Interactive demonstration</h2>
                        <p class="text-muted small mb-0"><?php echo trainingEsc($lessonIntro); ?></p>
                    </div>
                    <span class="training-status-chip"><i class="bx bx-mouse-alt"></i> Interactive</span>
                </div>

                <?php if ($demoScenes): ?>
                    <div class="training-demo" data-training-demo data-scene-count="<?php echo count($demoScenes); ?>">
                        <div class="training-demo__topbar">
                            <div>
                                <span class="training-demo__counter" data-demo-counter>Step 1 of <?php echo count($demoScenes); ?></span>
                                <h3 class="h5 mb-0" data-demo-title><?php echo trainingEsc($demoScenes[0]['title'] ?? 'Demonstration'); ?></h3>
                            </div>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-light" data-demo-narrate aria-pressed="false">
                                    <i class="bx bx-volume-full me-1"></i>Play narration
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-light" data-demo-play aria-pressed="false">
                                    <i class="bx bx-play me-1"></i>Auto play
                                </button>
                            </div>
                        </div>

                        <div class="training-demo__viewport" aria-live="polite">
                            <?php foreach ($demoScenes as $index => $scene): ?>
                                <?php
                                $sceneAudioUrl = sprintf('/assets/training/audio/%s/scene-%02d.mp3', $slug, $index + 1);
                                $sceneAudioFile = dirname(__DIR__) . str_replace('/', DIRECTORY_SEPARATOR, $sceneAudioUrl);
                                $sceneHasAudio = is_file($sceneAudioFile);
                                ?>
                                <article
                                    class="training-demo__scene<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                    data-demo-scene="<?php echo $index; ?>"
                                    data-demo-title="<?php echo trainingEsc($scene['title'] ?? 'Demonstration'); ?>"
                                    data-demo-caption="<?php echo trainingEsc($scene['caption'] ?? ''); ?>"
                                    data-demo-voice="<?php echo trainingEsc($scene['voice'] ?? $scene['caption'] ?? ''); ?>"
                                    <?php echo $sceneHasAudio ? 'data-demo-audio="' . trainingEsc($sceneAudioUrl) . '"' : ''; ?>
                                    <?php echo $index === 0 ? '' : 'hidden'; ?>
                                >
                                    <?php trainingRenderDemoScreen((string)($scene['screen'] ?? ''), (string)($scene['focus'] ?? '')); ?>
                                </article>
                            <?php endforeach; ?>
                            <div class="training-demo__cursor" data-demo-cursor aria-hidden="true"><i class="bx bx-pointer"></i></div>
                        </div>

                        <div class="training-demo__caption">
                            <div class="training-demo__caption-icon"><i class="bx bx-info-circle"></i></div>
                            <p class="mb-0" data-demo-caption><?php echo trainingEsc($demoScenes[0]['caption'] ?? ''); ?></p>
                        </div>

                        <div class="training-demo__controls">
                            <button type="button" class="btn btn-outline-light" data-demo-prev disabled>
                                <i class="bx bx-left-arrow-alt me-1"></i>Previous
                            </button>
                            <div class="training-demo__dots" aria-label="Demonstration steps">
                                <?php foreach ($demoScenes as $index => $_scene): ?>
                                    <button
                                        type="button"
                                        class="training-demo__dot<?php echo $index === 0 ? ' is-active' : ''; ?>"
                                        data-demo-go="<?php echo $index; ?>"
                                        aria-label="Go to demonstration step <?php echo $index + 1; ?>"
                                        aria-current="<?php echo $index === 0 ? 'step' : 'false'; ?>"
                                    ></button>
                                <?php endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-primary" data-demo-next>
                                Next <i class="bx bx-right-arrow-alt ms-1"></i>
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="training-demo-stage"><p class="text-muted mb-0">Interactive content is being prepared for this lesson.</p></div>
                <?php endif; ?>
            </section>

            <section class="training-lesson-card p-4 mb-4" aria-labelledby="steps-heading">
                <span class="training-eyebrow"><i class="bx bx-list-ol"></i> Step by step</span>
                <h2 id="steps-heading" class="h4 mt-2 mb-3">Walkthrough</h2>
                <div class="training-step-list">
                    <?php foreach ($steps as $step): ?>
                        <article class="training-step">
                            <h3 class="h6 mb-2"><?php echo trainingEsc($step['title'] ?? 'Step'); ?></h3>
                            <p class="text-muted mb-0"><?php echo trainingEsc($step['body'] ?? ''); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <?php if ($tips): ?>
            <section class="training-lesson-card p-4 mb-4" aria-labelledby="tips-heading">
                <span class="training-eyebrow"><i class="bx bx-bulb"></i> Site-ready guidance</span>
                <h2 id="tips-heading" class="h4 mt-2 mb-3">Good practice</h2>
                <div class="row g-3">
                    <?php foreach ($tips as $tip): ?>
                        <div class="col-md-4">
                            <article class="training-tip h-100">
                                <i class="bx bx-check-shield"></i>
                                <h3 class="h6 mt-2"><?php echo trainingEsc($tip['title'] ?? 'Tip'); ?></h3>
                                <p class="small text-muted mb-0"><?php echo trainingEsc($tip['body'] ?? ''); ?></p>
                            </article>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if ($knowledge): ?>
            <section class="training-lesson-card p-4 mb-4" aria-labelledby="knowledge-heading" data-training-quiz>
                <span class="training-eyebrow"><i class="bx bx-brain"></i> Knowledge check</span>
                <h2 id="knowledge-heading" class="h4 mt-2">Check your understanding</h2>
                <p class="text-muted small">Answer all questions correctly. You can retry any question immediately.</p>

                <div class="training-quiz-list">
                    <?php foreach ($knowledge as $qIndex => $question): ?>
                        <fieldset class="training-quiz-question" data-quiz-question data-answer="<?php echo (int)($question['answer'] ?? 0); ?>">
                            <legend class="h6"><?php echo ($qIndex + 1) . '. ' . trainingEsc($question['question'] ?? 'Question'); ?></legend>
                            <?php foreach (($question['options'] ?? []) as $oIndex => $option): ?>
                                <label class="training-quiz-option">
                                    <input type="radio" name="quiz_<?php echo $qIndex; ?>" value="<?php echo $oIndex; ?>">
                                    <span><?php echo trainingEsc($option); ?></span>
                                </label>
                            <?php endforeach; ?>
                            <div class="training-quiz-feedback small mt-2" data-quiz-feedback aria-live="polite"></div>
                        </fieldset>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 mt-3">
                    <button type="button" class="btn btn-primary" data-quiz-check>Check answers</button>
                    <span class="small text-muted" data-quiz-score aria-live="polite">Not checked yet</span>
                </div>
            </section>
            <?php endif; ?>

            <section class="training-lesson-card p-4" aria-labelledby="try-heading">
                <span class="training-eyebrow"><i class="bx bx-mouse"></i> Try it yourself</span>
                <h2 id="try-heading" class="h4 mt-2">Open the real feature</h2>
                <p class="text-muted">
                    Put the lesson into practice in Defect Tracker. The training page stays open in this tab while the live workflow opens separately.
                </p>
                <a class="btn btn-primary" href="<?php echo trainingEsc($tryUrl); ?>" target="_blank" rel="noopener">
                    Open live feature <i class="bx bx-link-external ms-1"></i>
                </a>
            </section>
        </div>

        <aside class="col-xl-4" aria-label="Lesson supporting information">
            <section class="training-card p-4 mb-4">
                <span class="training-eyebrow"><i class="bx bx-target-lock"></i> Outcomes</span>
                <h2 class="h5 mt-2">By the end of this lesson</h2>
                <ul class="training-outcome-list mt-3">
                    <?php foreach ($outcomes as $outcome): ?>
                        <li><i class="bx bx-check-circle"></i><span><?php echo trainingEsc($outcome); ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </section>

            <section class="training-card p-4 mb-4">
                <span class="training-eyebrow"><i class="bx bx-headphone"></i> Narration</span>
                <h2 class="h5 mt-2">Narration & transcript</h2>

                <?php if (!empty($lesson['audio_path'])): ?>
                    <div class="training-audio-shell mt-3">
                        <audio controls preload="metadata" class="w-100">
                            <source src="<?php echo trainingEsc($lesson['audio_path']); ?>" type="audio/mpeg">
                            Your browser does not support audio playback.
                        </audio>
                    </div>
                <?php else: ?>
                    <div class="training-audio-shell mt-3 text-muted small">
                        Use <strong>Play narration</strong> in the interactive demonstration for the recorded Defect Guardian Academy voice. Browser narration remains available as a fallback.
                    </div>
                <?php endif; ?>

                <button
                    type="button"
                    class="btn btn-sm btn-outline-light mt-3"
                    data-training-transcript-toggle
                    aria-controls="lesson-transcript"
                    aria-expanded="false"
                >Show transcript</button>

                <div id="lesson-transcript" class="small text-muted mt-3" hidden>
                    <?php if (!empty($lesson['transcript'])): ?>
                        <?php echo nl2br(trainingEsc($lesson['transcript'])); ?>
                    <?php else: ?>
                        The interactive demonstration captions and the written walkthrough provide the current lesson transcript. A recorded narration transcript can be added here later.
                    <?php endif; ?>
                </div>
            </section>

            <section class="training-card p-4">
                <span class="training-eyebrow"><i class="bx bx-universal-access"></i> Accessibility</span>
                <h2 class="h5 mt-2">Designed for everyone</h2>
                <p class="small text-muted mb-0">
                    Keyboard navigation, transcripts, readable written steps and reduced-motion support are built into the shared lesson template.
                </p>
            </section>
        </aside>
    </div>

    <div class="d-flex flex-wrap justify-content-between gap-2 mt-4">
        <a class="btn btn-outline-light" href="/training/"><i class="bx bx-left-arrow-alt me-1"></i>Training Hub</a>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <span class="text-muted small" id="training-save-status" aria-live="polite"><?php echo $training->isReady() ? 'Progress saves automatically' : 'Preview mode'; ?></span>
            <?php if ($training->isReady()): ?>
                <button type="button" class="btn btn-success" data-training-complete>
                    <i class="bx bx-check-circle me-1"></i>Mark lesson complete
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php trainingRenderFooter(); ?>
