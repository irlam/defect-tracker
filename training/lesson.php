<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

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

trainingRenderHeader(
    (string)$lesson['title'],
    (string)$lesson['description']
);
?>

<div id="training-content">
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
                        <h2 id="demo-heading" class="h4 mt-2 mb-1">Interactive demonstration area</h2>
                        <p class="text-muted small mb-0">Phase 1 provides the reusable stage. Narrated animation and real screen walkthroughs arrive in Phase 2.</p>
                    </div>
                    <span class="training-placeholder-badge training-status-chip"><i class="bx bx-wrench"></i> Demo shell ready</span>
                </div>

                <div class="training-demo-stage" role="region" aria-label="Training demonstration placeholder">
                    <div>
                        <div class="training-demo-stage__icon"><i class="bx bx-movie-play"></i></div>
                        <h3 class="h5 mt-3">Animation stage</h3>
                        <p class="text-muted mb-0">
                            This area is designed for cursor animation, highlights, captions, screen recordings and step controls without changing the lesson layout.
                        </p>
                    </div>
                </div>
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

            <section class="training-lesson-card p-4" aria-labelledby="try-heading">
                <span class="training-eyebrow"><i class="bx bx-mouse"></i> Try it yourself</span>
                <h2 id="try-heading" class="h4 mt-2">Open the real feature</h2>
                <p class="text-muted">
                    Training should lead directly into the live workflow. Open the relevant Defect Tracker feature in a new tab and follow the steps above.
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
                <h2 class="h5 mt-2">Audio-ready lesson</h2>

                <?php if (!empty($lesson['audio_path'])): ?>
                    <div class="training-audio-shell mt-3">
                        <audio controls preload="metadata" class="w-100">
                            <source src="<?php echo trainingEsc($lesson['audio_path']); ?>" type="audio/mpeg">
                            Your browser does not support audio playback.
                        </audio>
                    </div>
                <?php else: ?>
                    <div class="training-audio-shell mt-3 text-muted small">
                        Narration has not been recorded yet. The lesson template is ready to accept an MP3/WebM audio file.
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
                        The transcript field is ready for Phase 2 narration. Written steps remain available at all times for accessibility.
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
        <span class="text-muted small align-self-center">Phase 1 lesson engine</span>
    </div>
</div>

<?php trainingRenderFooter(); ?>
