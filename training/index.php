<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$modules = $training->getModulesForUser($userId, $userRole);
$overall = $training->getOverallProgress($userId, $userRole);
$continueLesson = $training->getContinueLesson($userId, $userRole);

trainingRenderHeader(
    'Defect Tracker Academy',
    'Role-aware training, guided walkthroughs and progress tracking built into Defect Tracker.'
);
?>

<section id="training-content" class="training-hero mb-4" aria-labelledby="academy-heading">
    <div class="row g-4 align-items-center">
        <div class="col-lg-8">
            <span class="training-eyebrow"><i class="bx bx-graduation"></i> In-app learning</span>
            <h2 id="academy-heading" class="display-6 fw-bold mt-3 mb-3">Learn the workflows you actually use on site.</h2>
            <p class="lead text-secondary mb-4">
                Short, practical lessons for creating defects, working with floor plans and completing the contractor-to-manager lifecycle.
            </p>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($continueLesson): ?>
                    <a class="btn btn-primary btn-lg" href="/training/lesson.php?lesson=<?php echo rawurlencode((string)$continueLesson['slug']); ?>">
                        <i class="bx bx-play-circle me-2"></i>Continue learning
                    </a>
                <?php endif; ?>
                <a class="btn btn-outline-light btn-lg" href="/help_index.php">
                    <i class="bx bx-help-circle me-2"></i>Help centre
                </a>
            </div>
        </div>
        <div class="col-lg-4 d-flex justify-content-lg-end">
            <div class="training-progress-ring" data-training-ring="<?php echo (int)$overall['percent']; ?>">
                <span class="training-progress-ring__value"><?php echo (int)$overall['percent']; ?>%</span>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-4">
        <div class="col-sm-4">
            <div class="training-card p-3 h-100">
                <div class="text-muted small">Lessons complete</div>
                <div class="fs-4 fw-bold"><?php echo (int)$overall['completed_lessons']; ?> / <?php echo (int)$overall['total_lessons']; ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="training-card p-3 h-100">
                <div class="text-muted small">Your role</div>
                <div class="fs-4 fw-bold text-capitalize"><?php echo trainingEsc($userRole); ?></div>
            </div>
        </div>
        <div class="col-sm-4">
            <div class="training-card p-3 h-100">
                <div class="text-muted small">Platform status</div>
                <div class="fs-4 fw-bold"><?php echo $training->isReady() ? 'Tracking active' : 'Preview mode'; ?></div>
            </div>
        </div>
    </div>
</section>

<?php if (!$training->isReady()): ?>
    <div class="alert alert-warning training-schema-note mb-4" role="status">
        <div class="d-flex gap-3 align-items-start">
            <i class="bx bx-info-circle fs-4"></i>
            <div>
                <strong>Training preview is active.</strong>
                <div class="small mt-1">
                    The hub and lesson routes are ready. Apply <code>database/migrations/add_training_platform.sql</code> to enable persistent module and user-progress tracking.
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if ($continueLesson): ?>
<section class="training-continue training-card p-4 mb-4" aria-labelledby="continue-heading">
    <div class="row g-3 align-items-center">
        <div class="col-md">
            <span class="training-eyebrow mb-2"><i class="bx bx-book-open"></i> Continue where you left off</span>
            <h2 id="continue-heading" class="h4 mb-2"><?php echo trainingEsc($continueLesson['title']); ?></h2>
            <div class="training-meta">
                <span><i class="bx bx-layer me-1"></i><?php echo trainingEsc($continueLesson['module_title']); ?></span>
                <span><i class="bx bx-time-five me-1"></i><?php echo (int)$continueLesson['estimated_minutes']; ?> min</span>
                <span><?php echo (int)$continueLesson['progress_percent']; ?>% complete</span>
            </div>
            <div class="progress training-progress mt-3" role="progressbar" aria-valuenow="<?php echo (int)$continueLesson['progress_percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?php echo (int)$continueLesson['progress_percent']; ?>%"></div>
            </div>
        </div>
        <div class="col-md-auto">
            <a class="btn btn-primary" href="/training/lesson.php?lesson=<?php echo rawurlencode((string)$continueLesson['slug']); ?>">
                Continue <i class="bx bx-right-arrow-alt ms-1"></i>
            </a>
        </div>
    </div>
</section>
<?php endif; ?>

<section aria-labelledby="modules-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div>
            <span class="training-eyebrow"><i class="bx bx-grid-alt"></i> Learning modules</span>
            <h2 id="modules-heading" class="h3 mt-2 mb-1">Start with the core workflow</h2>
            <p class="text-muted mb-0">Core operational training plus reporting and export guidance, all using the reusable interactive lesson engine.</p>
        </div>
    </div>

    <div class="row g-4">
        <?php foreach ($modules as $module): ?>
            <div class="col-12 col-md-6 col-xl-4">
                <article class="training-module-card training-card p-4 h-100" data-accent="<?php echo trainingEsc($module['accent'] ?? 'blue'); ?>">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="training-module-icon"><i class="bx <?php echo trainingEsc($module['icon'] ?? 'bx-book-open'); ?>"></i></div>
                        <span class="training-status-chip">
                            <?php echo (int)$module['completed_lessons']; ?>/<?php echo (int)$module['lesson_count']; ?> complete
                        </span>
                    </div>

                    <h3 class="h5 mt-4"><?php echo trainingEsc($module['title']); ?></h3>
                    <p class="text-muted small"><?php echo trainingEsc($module['description']); ?></p>

                    <div class="progress training-progress my-3" role="progressbar" aria-valuenow="<?php echo (int)$module['progress_percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?php echo (int)$module['progress_percent']; ?>%"></div>
                    </div>

                    <div class="training-meta mb-4">
                        <span><i class="bx bx-book me-1"></i><?php echo (int)$module['lesson_count']; ?> lesson<?php echo (int)$module['lesson_count'] === 1 ? '' : 's'; ?></span>
                        <span><i class="bx bx-line-chart me-1"></i><?php echo (int)$module['progress_percent']; ?>%</span>
                    </div>

                    <?php
                    $lessonSlug = match ((string)$module['slug']) {
                        'defect-creation' => 'create-a-defect',
                        'floor-plans' => 'floor-plan-location',
                        'defect-lifecycle' => 'contractor-manager-lifecycle',
                        'reports-exports' => 'reports-exports',
                        'projects-setup' => 'projects-setup',
                        'mobile-pwa' => 'mobile-pwa',
                        default => null,
                    };
                    ?>
                    <?php if ($lessonSlug): ?>
                        <a class="btn btn-outline-primary stretched-link" href="/training/lesson.php?lesson=<?php echo rawurlencode($lessonSlug); ?>">
                            Open module
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline-secondary disabled">Coming soon</span>
                    <?php endif; ?>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="row g-4 mt-2">
    <div class="col-lg-8">
        <div class="training-card p-4 h-100">
            <span class="training-eyebrow"><i class="bx bx-route"></i> Phase 1 structure</span>
            <h2 class="h4 mt-3">Built to grow without rebuilding the training area</h2>
            <p class="text-muted">
                Modules, lessons and progress are database-backed. The lesson page is reusable, so future training can add audio, animation, quizzes and role-specific content without creating a new layout every time.
            </p>
            <div class="row g-3 mt-1">
                <div class="col-sm-6">
                    <div class="training-card p-3 h-100">
                        <i class="bx bx-headphone fs-3 text-info"></i>
                        <h3 class="h6 mt-2">Audio-ready</h3>
                        <p class="small text-muted mb-0">Lesson metadata includes audio and transcript locations for Phase 2 narration.</p>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="training-card p-3 h-100">
                        <i class="bx bx-movie-play fs-3 text-info"></i>
                        <h3 class="h6 mt-2">Animation-ready</h3>
                        <p class="small text-muted mb-0">Each lesson has a dedicated demo stage ready for guided animations or short screen recordings.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="training-card p-4 h-100">
            <span class="training-eyebrow"><i class="bx bx-link-alt"></i> Reference</span>
            <h2 class="h5 mt-3">Need documentation instead?</h2>
            <div class="d-grid gap-2 mt-3">
                <a class="btn btn-outline-light" href="/help_pages/navigation_guide.php">Navigation guide</a>
                <a class="btn btn-outline-light" href="/help_pages/view_role_matrix.php">Role capability matrix</a>
                <a class="btn btn-outline-light" href="/help_pages/view_pwa_health.php">PWA guide</a>
            </div>
        </div>
    </div>
</section>

<?php trainingRenderFooter(); ?>
