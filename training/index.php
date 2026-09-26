<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$modules = $training->getModulesForUser($userId, $userRole);
$lessons = $training->getLessonsForUser($userId, $userRole);
$overall = $training->getOverallProgress($userId, $userRole);
$continueLesson = $training->getContinueLesson($userId, $userRole);

$lessonsByModule = [];
foreach ($lessons as $catalogueLesson) {
    $lessonsByModule[(string)$catalogueLesson['module_slug']][] = $catalogueLesson;
}

function trainingAudienceLabel(string $scope): string
{
    if ($scope === '' || $scope === 'all') {
        return 'All users';
    }

    $labels = [
        'admin' => 'Admin',
        'manager' => 'Manager',
        'inspector' => 'Site team',
        'contractor' => 'Contractor',
        'viewer' => 'Viewer',
    ];
    $roles = array_filter(array_map('trim', explode(',', strtolower($scope))));
    return implode(' · ', array_map(static fn(string $role): string => $labels[$role] ?? ucfirst($role), $roles));
}

function trainingRenderModulePreview(string $slug): void
{
    $previews = [
        'defect-creation' => ['bx-error-circle', 'New defect', 'Project · Priority · Evidence'],
        'floor-plans' => ['bx-map-pin', 'Level 02 plan', 'Pan · Zoom · Place pin'],
        'defect-lifecycle' => ['bx-transfer-alt', 'Pending review', 'Assigned → Complete → Accepted'],
        'reports-exports' => ['bx-bar-chart-alt-2', 'Performance', 'Open 18 · Closed 42 · Overdue 3'],
        'projects-setup' => ['bx-buildings', 'Project setup', 'Programme · Drawings · Status'],
        'mobile-pwa' => ['bx-mobile-alt', 'Field Mode', 'Offline ready · 2 saved reports'],
        'admin-users' => ['bx-user-check', 'User access', 'Role · Company · Account status'],
    ];
    [$icon, $title, $meta] = $previews[$slug] ?? ['bx-book-open', 'Guided lesson', 'Watch · Practise · Check'];
    echo '<div class="training-module-preview" aria-hidden="true">';
    echo '<div class="training-module-preview__bar"><span></span><span></span><span></span><b>Defect Tracker</b></div>';
    echo '<div class="training-module-preview__screen"><i class="bx ' . trainingEsc($icon) . '"></i><div><strong>' . trainingEsc($title) . '</strong><small>' . trainingEsc($meta) . '</small></div><span class="training-module-preview__pulse"></span></div>';
    echo '</div>';
}

trainingRenderHeader(
    'Defect Tracker Academy',
    'Complete role-aware training with animated walkthroughs, knowledge checks and progress tracking.'
);
?>

<section id="training-content" class="training-hero mb-4" aria-labelledby="academy-heading">
    <div class="row g-4 align-items-center">
        <div class="col-lg-8">
            <span class="training-eyebrow"><i class="bx bx-graduation"></i> In-app learning</span>
            <h2 id="academy-heading" class="display-6 fw-bold mt-3 mb-3">Learn the workflows you actually use on site.</h2>
            <p class="lead text-secondary mb-4">
                A complete end-user pathway for site teams, contractors, managers and administrators. Every lesson combines a video-style walkthrough, written steps, a knowledge check and live practice.
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

<section aria-labelledby="modules-heading" data-training-catalogue>
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
        <div>
            <span class="training-eyebrow"><i class="bx bx-grid-alt"></i> Guided training library</span>
            <h2 id="modules-heading" class="h3 mt-2 mb-1">Choose a workflow and learn it visually</h2>
            <p class="text-muted mb-0">The animated previews use safe example data while matching the real controls and sequence used in Defect Tracker.</p>
        </div>
        <div class="training-catalogue-search">
            <label for="training-search" class="visually-hidden">Search training</label>
            <i class="bx bx-search" aria-hidden="true"></i>
            <input id="training-search" type="search" class="form-control" placeholder="Search training" data-training-search>
        </div>
    </div>

    <div class="training-role-filters mb-4" role="group" aria-label="Filter training by audience">
        <button type="button" class="training-filter is-active" data-training-filter="all" aria-pressed="true">All available</button>
        <button type="button" class="training-filter" data-training-filter="site" aria-pressed="false">Site team</button>
        <button type="button" class="training-filter" data-training-filter="contractor" aria-pressed="false">Contractor</button>
        <button type="button" class="training-filter" data-training-filter="manager" aria-pressed="false">Manager</button>
        <button type="button" class="training-filter" data-training-filter="admin" aria-pressed="false">Admin</button>
    </div>

    <div class="row g-4" data-training-module-grid>
        <?php foreach ($modules as $module): ?>
            <?php
            $moduleLessons = $lessonsByModule[(string)$module['slug']] ?? [];
            $firstLesson = $moduleLessons[0] ?? null;
            $moduleSearchText = strtolower(implode(' ', array_merge(
                [(string)$module['title'], (string)$module['description']],
                array_map(static fn(array $item): string => (string)$item['title'] . ' ' . (string)$item['description'], $moduleLessons)
            )));
            $moduleRoles = array_unique(array_map(static fn(array $item): string => (string)($item['role_scope'] ?? 'all'), $moduleLessons));
            ?>
            <div class="col-12 col-md-6 col-xl-4" data-training-item data-training-search-text="<?php echo trainingEsc($moduleSearchText); ?>" data-training-roles="<?php echo trainingEsc(implode(',', $moduleRoles)); ?>">
                <article class="training-module-card training-card p-4 h-100" data-accent="<?php echo trainingEsc($module['accent'] ?? 'blue'); ?>">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div class="training-module-icon"><i class="bx <?php echo trainingEsc($module['icon'] ?? 'bx-book-open'); ?>"></i></div>
                        <span class="training-status-chip">
                            <?php echo (int)$module['completed_lessons']; ?>/<?php echo (int)$module['lesson_count']; ?> complete
                        </span>
                    </div>

                    <?php trainingRenderModulePreview((string)$module['slug']); ?>

                    <h3 class="h5 mt-3"><?php echo trainingEsc($module['title']); ?></h3>
                    <p class="text-muted small"><?php echo trainingEsc($module['description']); ?></p>

                    <div class="progress training-progress my-3" role="progressbar" aria-valuenow="<?php echo (int)$module['progress_percent']; ?>" aria-valuemin="0" aria-valuemax="100">
                        <div class="progress-bar" style="width: <?php echo (int)$module['progress_percent']; ?>%"></div>
                    </div>

                    <div class="training-meta mb-4">
                        <span><i class="bx bx-book me-1"></i><?php echo (int)$module['lesson_count']; ?> lesson<?php echo (int)$module['lesson_count'] === 1 ? '' : 's'; ?></span>
                        <span><i class="bx bx-line-chart me-1"></i><?php echo (int)$module['progress_percent']; ?>%</span>
                    </div>

                    <?php if ($firstLesson): ?>
                        <a class="btn btn-outline-primary stretched-link" href="/training/lesson.php?lesson=<?php echo rawurlencode((string)$firstLesson['slug']); ?>">
                            Open module
                        </a>
                    <?php else: ?>
                        <span class="btn btn-outline-secondary disabled">Coming soon</span>
                    <?php endif; ?>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
    <p class="training-no-results text-muted" data-training-no-results hidden>No training modules match that search and audience filter.</p>
</section>

<section class="training-card p-4 mt-5" aria-labelledby="matrix-heading">
    <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
        <div>
            <span class="training-eyebrow"><i class="bx bx-spreadsheet"></i> End-user training matrix</span>
            <h2 id="matrix-heading" class="h3 mt-2 mb-1">What each user needs to know</h2>
            <p class="text-muted mb-0">Use this matrix as an induction checklist, refresher plan or competency record.</p>
        </div>
        <span class="training-status-chip"><i class="bx bx-check-shield"></i> <?php echo count($lessons); ?> role-approved lessons</span>
    </div>

    <div class="training-matrix-wrap">
        <table class="training-matrix">
            <thead><tr><th>Workflow</th><th>Audience</th><th>Learning format</th><th>Time</th><th>Competency</th></tr></thead>
            <tbody>
            <?php foreach ($lessons as $matrixLesson): ?>
                <?php $matrixStatus = (string)($matrixLesson['progress_status'] ?? 'not_started'); ?>
                <tr>
                    <td data-label="Workflow">
                        <a href="/training/lesson.php?lesson=<?php echo rawurlencode((string)$matrixLesson['slug']); ?>"><?php echo trainingEsc($matrixLesson['title']); ?></a>
                        <small><?php echo trainingEsc($matrixLesson['module_title']); ?></small>
                    </td>
                    <td data-label="Audience"><?php echo trainingEsc(trainingAudienceLabel((string)($matrixLesson['role_scope'] ?? 'all'))); ?></td>
                    <td data-label="Learning format"><span class="training-format"><i class="bx bx-play-circle"></i> Animated demo</span><span class="training-format"><i class="bx bx-brain"></i> Knowledge check</span></td>
                    <td data-label="Time"><?php echo (int)$matrixLesson['estimated_minutes']; ?> min</td>
                    <td data-label="Competency"><span class="training-competency is-<?php echo trainingEsc(str_replace('_', '-', $matrixStatus)); ?>"><?php echo trainingEsc(trainingStatusLabel($matrixStatus)); ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="row g-4 mt-2">
    <div class="col-lg-8">
        <div class="training-card p-4 h-100">
            <span class="training-eyebrow"><i class="bx bx-route"></i> Recommended pathway</span>
            <h2 class="h4 mt-3">Watch it, follow it, practise it, prove it</h2>
            <div class="training-learning-path mt-4">
                <div><i class="bx bx-play-circle"></i><strong>1. Watch</strong><span>Run the animated walkthrough or use the step controls.</span></div>
                <div><i class="bx bx-list-check"></i><strong>2. Follow</strong><span>Read the exact workflow and site-ready guidance.</span></div>
                <div><i class="bx bx-mouse"></i><strong>3. Practise</strong><span>Open the real feature in a separate tab.</span></div>
                <div><i class="bx bx-check-shield"></i><strong>4. Prove</strong><span>Pass the knowledge check and mark the lesson complete.</span></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="training-card p-4 h-100">
            <span class="training-eyebrow"><i class="bx bx-link-alt"></i> Quick reference</span>
            <h2 class="h5 mt-3">Need a written guide?</h2>
            <p class="small text-muted">Training builds competency; the help centre is the quickest reference while you are doing the job.</p>
            <div class="d-grid gap-2 mt-3">
                <a class="btn btn-outline-light" href="/help_pages/navigation_guide.php">Navigation guide</a>
                <a class="btn btn-outline-light" href="/help_pages/view_role_matrix.php">Role capability matrix</a>
                <a class="btn btn-outline-light" href="/help_pages/view_pwa_health.php">PWA &amp; Field Mode guide</a>
            </div>
        </div>
    </div>
</section>

<?php trainingRenderFooter(); ?>
