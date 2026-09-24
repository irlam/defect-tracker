-- Defect Tracker Training Platform
-- Reports & Exports module
-- Safe to run after add_training_platform.sql.

INSERT INTO training_modules (
    slug, title, description, icon, accent, sort_order, is_active
)
VALUES (
    'reports-exports',
    'Reports & Exports',
    'Filter the reporting dashboard, interpret performance metrics and export the selected view to CSV or PDF.',
    'bx-bar-chart-alt-2',
    'amber',
    40,
    1
)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    description = VALUES(description),
    icon = VALUES(icon),
    accent = VALUES(accent),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

INSERT INTO training_lessons (
    module_id,
    slug,
    title,
    description,
    estimated_minutes,
    difficulty,
    role_scope,
    content_json,
    audio_path,
    transcript,
    animation_path,
    sort_order,
    is_active
)
SELECT
    m.id,
    'reports-exports',
    'Reports & Exports',
    'Learn to filter the Reports Dashboard, interpret key metrics and export the selected reporting view.',
    8,
    'Beginner',
    'admin,manager',
    '{"outcomes":["Set the reporting date range","Interpret defect and contractor metrics","Use trend charts for context","Export the filtered report to CSV or PDF"],"steps":[{"title":"Open Reports Hub","body":"Open Performance & Reporting from the Reports menu."},{"title":"Set the reporting period","body":"Choose start and end dates before interpreting dashboard figures."},{"title":"Read headline metrics","body":"Review total, open, pending, overdue, rejected and closed defects together."},{"title":"Review contractor performance","body":"Compare defect workload, overdue items, rejected work, closed work and resolution measures."},{"title":"Use trends for context","body":"Use charts to understand changes across the selected reporting period."},{"title":"Export the filtered view","body":"Export CSV for further analysis or PDF for a shareable report after confirming the reporting period."}],"try_url":"/reports.php"}',
    NULL,
    NULL,
    NULL,
    10,
    1
FROM training_modules m
WHERE m.slug = 'reports-exports'
ON DUPLICATE KEY UPDATE
    module_id = VALUES(module_id),
    title = VALUES(title),
    description = VALUES(description),
    estimated_minutes = VALUES(estimated_minutes),
    difficulty = VALUES(difficulty),
    role_scope = VALUES(role_scope),
    content_json = VALUES(content_json),
    sort_order = VALUES(sort_order),
    is_active = VALUES(is_active);

SELECT
    m.slug AS module_slug,
    m.title AS module_title,
    l.slug AS lesson_slug,
    l.title AS lesson_title,
    l.role_scope,
    l.is_active
FROM training_modules m
JOIN training_lessons l ON l.module_id = m.id
WHERE m.slug = 'reports-exports';
