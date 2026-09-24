-- Defect Tracker Training Platform
-- Projects & Setup module
-- Safe to run after add_training_platform.sql.

INSERT INTO training_modules (
    slug, title, description, icon, accent, sort_order, is_active
)
VALUES (
    'projects-setup',
    'Projects & Setup',
    'Create project records, maintain programme dates and status, and attach clearly labelled floor plans ready for defect use.',
    'bx-buildings',
    'cyan',
    50,
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
    'projects-setup',
    'Projects & Setup',
    'Learn to create a project, maintain programme information and prepare floor plans for defect use.',
    9,
    'Beginner',
    'admin,manager',
    '{"outcomes":["Create a project with clear programme information","Set project dates and status correctly","Review the project portfolio after setup","Upload and verify a floor plan against the correct project"],"steps":[{"title":"Open Projects Management","body":"Open the Projects directory and review the portfolio dashboard."},{"title":"Create the project","body":"Enter the project name, description, start date, end date and status."},{"title":"Check programme information","body":"Confirm dates and status reflect the real project programme because they drive progress and deadline indicators."},{"title":"Review the saved project","body":"Confirm the project appears in the portfolio with the expected status and programme information."},{"title":"Upload a floor plan","body":"Select the project, enter a meaningful floor name and level, then upload a supported drawing file."},{"title":"Verify the floor-plan library","body":"Check that the drawing is clearly named, linked to the right project and available for defect location."}],"try_url":"/projects.php"}',
    NULL,
    NULL,
    NULL,
    10,
    1
FROM training_modules m
WHERE m.slug = 'projects-setup'
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
WHERE m.slug = 'projects-setup';
