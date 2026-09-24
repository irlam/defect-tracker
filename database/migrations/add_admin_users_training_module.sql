-- Defect Tracker Training Platform
-- Admin & Users module
-- Safe to run after add_training_platform.sql.

INSERT INTO training_modules (
    slug, title, description, icon, accent, sort_order, is_active
)
VALUES (
    'admin-users',
    'Admin & Users',
    'Create and maintain user accounts, choose appropriate access levels, manage contractor links and preserve an auditable access history.',
    'bx-user-check',
    'violet',
    70,
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
    'admin-users',
    'Admin & Users',
    'Learn to create and maintain user accounts, assign appropriate access and manage contractor associations safely.',
    10,
    'Intermediate',
    'admin,manager',
    '{"outcomes":["Review existing users and account status","Create a new user with valid identity information","Choose an appropriate user type and contractor association","Edit or deactivate existing accounts while preserving audit history"],"steps":[{"title":"Open User Management","body":"Review registered users, their type, status, contractor association and recent login information."},{"title":"Create a new user","body":"Enter the person’s name, username, email, password and required user type."},{"title":"Choose access carefully","body":"Use the least privileged user type that supports the person’s real responsibilities."},{"title":"Link contractor users","body":"Contractor users must be associated with the correct active contractor."},{"title":"Review and save","body":"Check identity, access level and contractor association before creating the account."},{"title":"Maintain existing users","body":"Edit the existing account, change its type or deactivate it rather than creating duplicate or shared accounts."}],"try_url":"/user_management.php"}',
    NULL,
    NULL,
    NULL,
    10,
    1
FROM training_modules m
WHERE m.slug = 'admin-users'
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
WHERE m.slug = 'admin-users';
