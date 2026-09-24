-- Defect Tracker Training Platform
-- Mobile & PWA module
-- Safe to run after add_training_platform.sql.

INSERT INTO training_modules (
    slug, title, description, icon, accent, sort_order, is_active
)
VALUES (
    'mobile-pwa',
    'Mobile & PWA',
    'Install Defect Tracker on a device, prepare Field Mode and safely capture and sync defects when reception is unreliable.',
    'bx-mobile-alt',
    'blue',
    60,
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
    'mobile-pwa',
    'Mobile & PWA',
    'Learn to install Defect Tracker on a device, prepare offline Field Mode and safely sync saved field reports.',
    10,
    'Beginner',
    'all',
    '{"outcomes":["Install or add Defect Tracker to a device home screen","Prepare Field Mode while connected","Capture a defect when reception is unavailable","Check saved reports and confirm they sync when connectivity returns"],"steps":[{"title":"Open Defect Tracker online","body":"Open the app securely on the phone or tablet while connected and sign in."},{"title":"Install or add to the home screen","body":"Use the browser install option or Add to Home Screen so Defect Tracker can launch like an app."},{"title":"Prepare Field Mode","body":"Open Field Mode online first so project, contractor and floor-plan reference data is available on the device."},{"title":"Capture an offline field defect","body":"Complete the defect details, select the floor plan, place the location pin and attach site photos."},{"title":"Save to the device outbox","body":"Save the report locally when offline and confirm it appears in the pending queue."},{"title":"Reconnect and sync","body":"When reception returns, remain signed in and confirm queued reports upload or retry any report needing attention."}],"try_url":"/offline-field.html"}',
    NULL,
    NULL,
    NULL,
    10,
    1
FROM training_modules m
WHERE m.slug = 'mobile-pwa'
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
WHERE m.slug = 'mobile-pwa';
