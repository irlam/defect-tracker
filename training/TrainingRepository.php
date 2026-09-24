<?php
declare(strict_types=1);

final class TrainingRepository
{
    private ?PDO $db;
    private bool $schemaReady = false;

    public function __construct(?PDO $db)
    {
        $this->db = $db;
        $this->schemaReady = $this->detectSchema();
    }

    public function isReady(): bool
    {
        return $this->schemaReady;
    }

    public function getModulesForUser(int $userId, string $role): array
    {
        if (!$this->schemaReady || !$this->db) {
            return $this->fallbackModules();
        }

        $stmt = $this->db->prepare(
            "SELECT
                m.id,
                m.slug,
                m.title,
                m.description,
                m.icon,
                m.accent,
                m.sort_order,
                COUNT(l.id) AS lesson_count,
                COALESCE(SUM(CASE WHEN p.status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_lessons,
                COALESCE(MAX(p.updated_at), MAX(l.updated_at), m.updated_at) AS last_activity
             FROM training_modules m
             LEFT JOIN training_lessons l
                ON l.module_id = m.id
               AND l.is_active = 1
               AND (
                    l.role_scope = 'all'
                    OR FIND_IN_SET(:role, l.role_scope) > 0
               )
             LEFT JOIN training_progress p
                ON p.lesson_id = l.id
               AND p.user_id = :user_id
             WHERE m.is_active = 1
             GROUP BY m.id, m.slug, m.title, m.description, m.icon, m.accent, m.sort_order, m.updated_at
             ORDER BY m.sort_order, m.title"
        );
        $stmt->execute(['role' => $role, 'user_id' => $userId]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($modules as &$module) {
            $lessonCount = (int)$module['lesson_count'];
            $completed = (int)$module['completed_lessons'];
            $module['progress_percent'] = $lessonCount > 0 ? (int)round(($completed / $lessonCount) * 100) : 0;
        }
        unset($module);

        return $modules;
    }

    public function getOverallProgress(int $userId, string $role): array
    {
        $modules = $this->getModulesForUser($userId, $role);
        $lessons = array_sum(array_map(static fn(array $m): int => (int)$m['lesson_count'], $modules));
        $completed = array_sum(array_map(static fn(array $m): int => (int)$m['completed_lessons'], $modules));

        return [
            'total_lessons' => $lessons,
            'completed_lessons' => $completed,
            'percent' => $lessons > 0 ? (int)round(($completed / $lessons) * 100) : 0,
        ];
    }

    public function getContinueLesson(int $userId, string $role): ?array
    {
        if (!$this->schemaReady || !$this->db) {
            return [
                'slug' => 'create-a-defect',
                'title' => 'Create a Defect',
                'module_title' => 'Defect Creation',
                'estimated_minutes' => 8,
                'progress_percent' => 0,
            ];
        }

        $stmt = $this->db->prepare(
            "SELECT
                l.slug,
                l.title,
                l.estimated_minutes,
                m.title AS module_title,
                COALESCE(p.progress_percent, 0) AS progress_percent,
                COALESCE(p.updated_at, l.updated_at) AS sort_date
             FROM training_lessons l
             INNER JOIN training_modules m ON m.id = l.module_id
             LEFT JOIN training_progress p
                ON p.lesson_id = l.id
               AND p.user_id = :user_id
             WHERE l.is_active = 1
               AND m.is_active = 1
               AND (
                    l.role_scope = 'all'
                    OR FIND_IN_SET(:role, l.role_scope) > 0
               )
               AND COALESCE(p.status, 'not_started') <> 'completed'
             ORDER BY
                CASE WHEN p.status = 'in_progress' THEN 0 ELSE 1 END,
                COALESCE(p.updated_at, l.created_at) DESC,
                m.sort_order,
                l.sort_order
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'role' => $role]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

        return $lesson ?: null;
    }

    public function getLessonBySlug(string $slug, int $userId, string $role): ?array
    {
        if (!$this->schemaReady || !$this->db) {
            foreach ($this->fallbackLessons() as $lesson) {
                if ($lesson['slug'] === $slug) {
                    $lesson['progress'] = ['status' => 'not_started', 'progress_percent' => 0, 'last_position' => null];
                    return $lesson;
                }
            }
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT
                l.*,
                m.title AS module_title,
                m.slug AS module_slug,
                m.icon AS module_icon,
                m.accent AS module_accent,
                p.status AS progress_status,
                p.progress_percent,
                p.last_position,
                p.started_at,
                p.completed_at
             FROM training_lessons l
             INNER JOIN training_modules m ON m.id = l.module_id
             LEFT JOIN training_progress p
                ON p.lesson_id = l.id
               AND p.user_id = :user_id
             WHERE l.slug = :slug
               AND l.is_active = 1
               AND m.is_active = 1
               AND (
                    l.role_scope = 'all'
                    OR FIND_IN_SET(:role, l.role_scope) > 0
               )
             LIMIT 1"
        );
        $stmt->execute(['user_id' => $userId, 'slug' => $slug, 'role' => $role]);
        $lesson = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lesson) {
            return null;
        }

        $lesson['progress'] = [
            'status' => $lesson['progress_status'] ?? 'not_started',
            'progress_percent' => (int)($lesson['progress_percent'] ?? 0),
            'last_position' => $lesson['last_position'] ?? null,
        ];
        $lesson['content'] = $this->decodeContent((string)($lesson['content_json'] ?? ''));

        return $lesson;
    }

    public function updateProgress(int $userId, int $lessonId, string $status, int $percent, ?string $lastPosition = null): bool
    {
        if (!$this->schemaReady || !$this->db || $userId < 1 || $lessonId < 1) {
            return false;
        }

        $allowed = ['not_started', 'in_progress', 'completed'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        $percent = max(0, min(100, $percent));
        if ($status === 'completed') {
            $percent = 100;
        }

        $stmt = $this->db->prepare(
            "INSERT INTO training_progress
                (user_id, lesson_id, status, progress_percent, last_position, started_at, completed_at)
             VALUES
                (:user_id, :lesson_id, :status, :progress_percent, :last_position,
                 CASE WHEN :status_started IN ('in_progress','completed') THEN NOW() ELSE NULL END,
                 CASE WHEN :status_completed = 'completed' THEN NOW() ELSE NULL END)
             ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                progress_percent = VALUES(progress_percent),
                last_position = VALUES(last_position),
                started_at = COALESCE(training_progress.started_at, VALUES(started_at)),
                completed_at = CASE
                    WHEN VALUES(status) = 'completed' THEN COALESCE(training_progress.completed_at, NOW())
                    ELSE training_progress.completed_at
                END"
        );

        return $stmt->execute([
            'user_id' => $userId,
            'lesson_id' => $lessonId,
            'status' => $status,
            'progress_percent' => $percent,
            'last_position' => $lastPosition,
            'status_started' => $status,
            'status_completed' => $status,
        ]);
    }

    private function detectSchema(): bool
    {
        if (!$this->db) {
            return false;
        }

        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME IN ('training_modules','training_lessons','training_progress')"
            );
            if ((int)$stmt->fetchColumn() !== 3) {
                return false;
            }

            $lessonCount = (int)$this->db->query("SELECT COUNT(*) FROM training_lessons WHERE is_active = 1")->fetchColumn();
            return $lessonCount > 0;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function decodeContent(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $content = json_decode($json, true);
        return is_array($content) ? $content : [];
    }

    private function fallbackModules(): array
    {
        return [
            ['id'=>1,'slug'=>'defect-creation','title'=>'Defect Creation','description'=>'Raise clear, complete defects with the right project, contractor, evidence and priority.','icon'=>'bx-error-circle','accent'=>'blue','sort_order'=>10,'lesson_count'=>1,'completed_lessons'=>0,'progress_percent'=>0],
            ['id'=>2,'slug'=>'floor-plans','title'=>'Floor Plans','description'=>'Navigate drawings, zoom precisely and place a defect pin at the correct location.','icon'=>'bx-map-alt','accent'=>'cyan','sort_order'=>20,'lesson_count'=>1,'completed_lessons'=>0,'progress_percent'=>0],
            ['id'=>3,'slug'=>'defect-lifecycle','title'=>'Contractor & Manager Workflow','description'=>'Follow a defect from assignment through evidence, review, rejection, acceptance and closeout.','icon'=>'bx-transfer-alt','accent'=>'violet','sort_order'=>30,'lesson_count'=>1,'completed_lessons'=>0,'progress_percent'=>0],
            ['id'=>4,'slug'=>'reports-exports','title'=>'Reports & Exports','description'=>'Filter the reporting dashboard, interpret performance metrics and export the selected view to CSV or PDF.','icon'=>'bx-bar-chart-alt-2','accent'=>'amber','sort_order'=>40,'lesson_count'=>1,'completed_lessons'=>0,'progress_percent'=>0],
            ['id'=>5,'slug'=>'projects-setup','title'=>'Projects & Setup','description'=>'Create project records, maintain programme dates and status, and attach clearly labelled floor plans ready for defect use.','icon'=>'bx-buildings','accent'=>'cyan','sort_order'=>50,'lesson_count'=>1,'completed_lessons'=>0,'progress_percent'=>0],
            ['id'=>6,'slug'=>'mobile-pwa','title'=>'Mobile & PWA','description'=>'Install Defect Tracker on a device, prepare Field Mode and safely capture and sync defects when reception is unreliable.','icon'=>'bx-mobile-alt','accent'=>'blue','sort_order'=>60,'lesson_count'=>1,'completed_lessons'=>0,'progress_percent'=>0],
        ];
    }

    private function fallbackLessons(): array
    {
        return [
            [
                'id'=>1,'slug'=>'create-a-defect','title'=>'Create a Defect','description'=>'Learn the complete workflow for raising a clear, actionable defect.','estimated_minutes'=>8,'difficulty'=>'Beginner','role_scope'=>'admin,manager,inspector','module_title'=>'Defect Creation','module_slug'=>'defect-creation','module_icon'=>'bx-error-circle','module_accent'=>'blue',
                'content'=>[
                    'outcomes'=>['Choose the correct project and contractor','Set priority and due date','Add photographic evidence','Select a floor plan and confirm the location'],
                    'steps'=>[
                        ['title'=>'Open Defect Ops','body'=>'Open Defect Ops and choose the option to raise a new defect.'],
                        ['title'=>'Describe the defect','body'=>'Add a concise title, clear description, project, contractor, priority and due date.'],
                        ['title'=>'Add evidence','body'=>'Attach a clear photo that shows the issue and surrounding context.'],
                        ['title'=>'Set the location','body'=>'Choose the project floor plan, navigate to the correct area and place the pin precisely.'],
                        ['title'=>'Review and submit','body'=>'Check the details before submitting the defect to the workflow.'],
                    ],
                    'try_url'=>'/create_defect.php'
                ]
            ],
            [
                'id'=>2,'slug'=>'floor-plan-location','title'=>'Locate a Defect on a Floor Plan','description'=>'Use pan, zoom and pin placement accurately on desktop and mobile.','estimated_minutes'=>6,'difficulty'=>'Beginner','role_scope'=>'admin,manager,inspector','module_title'=>'Floor Plans','module_slug'=>'floor-plans','module_icon'=>'bx-map-alt','module_accent'=>'cyan',
                'content'=>[
                    'outcomes'=>['Pan around large drawings','Zoom with mouse or touch gestures','Place and fine-tune a defect pin','Reset the plan to fit the viewport'],
                    'steps'=>[
                        ['title'=>'Select the floor plan','body'=>'Choose the drawing associated with the project.'],
                        ['title'=>'Navigate in Pan mode','body'=>'Drag the plan to move around it and zoom until the target area is clear.'],
                        ['title'=>'Place the pin','body'=>'Switch to Place Pin mode and choose the exact defect location.'],
                        ['title'=>'Fine-tune and confirm','body'=>'Drag the pin if necessary, then confirm the location and return to the defect form.'],
                    ],
                    'try_url'=>'/create_defect.php'
                ]
            ],
            [
                'id'=>3,'slug'=>'contractor-manager-lifecycle','title'=>'Contractor → Manager Defect Lifecycle','description'=>'Understand the complete operational handoff from assignment to closeout.','estimated_minutes'=>12,'difficulty'=>'Intermediate','role_scope'=>'all','module_title'=>'Contractor & Manager Workflow','module_slug'=>'defect-lifecycle','module_icon'=>'bx-transfer-alt','module_accent'=>'violet',
                'content'=>[
                    'outcomes'=>['Understand contractor actions','Submit completion evidence correctly','Review, reject or accept work','Reopen defects when further work is required'],
                    'steps'=>[
                        ['title'=>'Assignment','body'=>'The defect is assigned to a contractor with the required information and evidence.'],
                        ['title'=>'Contractor action','body'=>'The contractor reviews the defect, progresses the work and adds completion evidence.'],
                        ['title'=>'Submit for review','body'=>'Completed work is sent back to the manager for review.'],
                        ['title'=>'Manager decision','body'=>'The manager can accept the work or reject it with a clear reason for further action.'],
                        ['title'=>'Close or reopen','body'=>'Accepted defects can be closed, while defects requiring additional action can be reopened.'],
                    ],
                    'try_url'=>'/defects.php'
                ]
            ],
            [
                'id'=>4,'slug'=>'reports-exports','title'=>'Reports & Exports','description'=>'Learn to filter the Reports Dashboard, interpret key metrics and export the selected reporting view.','estimated_minutes'=>8,'difficulty'=>'Beginner','role_scope'=>'admin,manager','module_title'=>'Reports & Exports','module_slug'=>'reports-exports','module_icon'=>'bx-bar-chart-alt-2','module_accent'=>'amber',
                'content'=>[
                    'outcomes'=>['Set the reporting date range','Interpret defect and contractor metrics','Use trend charts for context','Export the filtered report to CSV or PDF'],
                    'steps'=>[
                        ['title'=>'Open Reports Hub','body'=>'Open Performance & Reporting from the Reports menu.'],
                        ['title'=>'Set the reporting period','body'=>'Choose start and end dates before interpreting dashboard figures.'],
                        ['title'=>'Read headline metrics','body'=>'Review total, open, pending, overdue, rejected and closed defects together.'],
                        ['title'=>'Review contractor performance','body'=>'Compare defect workload, overdue items, rejected work, closed work and resolution measures.'],
                        ['title'=>'Use trends for context','body'=>'Use charts to understand changes across the selected reporting period.'],
                        ['title'=>'Export the filtered view','body'=>'Export CSV for further analysis or PDF for a shareable report after confirming the reporting period.'],
                    ],
                    'try_url'=>'/reports.php'
                ]
            ],
            [
                'id'=>5,'slug'=>'projects-setup','title'=>'Projects & Setup','description'=>'Learn to create a project, maintain programme information and prepare floor plans for defect use.','estimated_minutes'=>9,'difficulty'=>'Beginner','role_scope'=>'admin,manager','module_title'=>'Projects & Setup','module_slug'=>'projects-setup','module_icon'=>'bx-buildings','module_accent'=>'cyan',
                'content'=>[
                    'outcomes'=>['Create a project with clear programme information','Set project dates and status correctly','Review the project portfolio after setup','Upload and verify a floor plan against the correct project'],
                    'steps'=>[
                        ['title'=>'Open Projects Management','body'=>'Open the Projects directory and review the portfolio dashboard.'],
                        ['title'=>'Create the project','body'=>'Enter the project name, description, start date, end date and status.'],
                        ['title'=>'Check programme information','body'=>'Confirm dates and status reflect the real project programme because they drive progress and deadline indicators.'],
                        ['title'=>'Review the saved project','body'=>'Confirm the project appears in the portfolio with the expected status and programme information.'],
                        ['title'=>'Upload a floor plan','body'=>'Select the project, enter a meaningful floor name and level, then upload a supported drawing file.'],
                        ['title'=>'Verify the floor-plan library','body'=>'Check that the drawing is clearly named, linked to the right project and available for defect location.'],
                    ],
                    'try_url'=>'/projects.php'
                ]
            ],
            [
                'id'=>6,'slug'=>'mobile-pwa','title'=>'Mobile & PWA','description'=>'Learn to install Defect Tracker on a device, prepare offline Field Mode and safely sync saved field reports.','estimated_minutes'=>10,'difficulty'=>'Beginner','role_scope'=>'all','module_title'=>'Mobile & PWA','module_slug'=>'mobile-pwa','module_icon'=>'bx-mobile-alt','module_accent'=>'blue',
                'content'=>[
                    'outcomes'=>['Install or add Defect Tracker to a device home screen','Prepare Field Mode while connected','Capture a defect when reception is unavailable','Check saved reports and confirm they sync when connectivity returns'],
                    'steps'=>[
                        ['title'=>'Open Defect Tracker online','body'=>'Open the app securely on the phone or tablet while connected and sign in.'],
                        ['title'=>'Install or add to the home screen','body'=>'Use the browser install option or Add to Home Screen so Defect Tracker can launch like an app.'],
                        ['title'=>'Prepare Field Mode','body'=>'Open Field Mode online first so project, contractor and floor-plan reference data is available on the device.'],
                        ['title'=>'Capture an offline field defect','body'=>'Complete the defect details, select the floor plan, place the location pin and attach site photos.'],
                        ['title'=>'Save to the device outbox','body'=>'Save the report locally when offline and confirm it appears in the pending queue.'],
                        ['title'=>'Reconnect and sync','body'=>'When reception returns, remain signed in and confirm queued reports upload or retry any report needing attention.'],
                    ],
                    'try_url'=>'/offline-field.html'
                ]
            ],
        ];
    }
}
