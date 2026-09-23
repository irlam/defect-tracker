-- Defect Tracker Training Platform - Phase 1
-- Adds module, lesson and per-user progress storage.
-- Safe to run once on MySQL/MariaDB.

CREATE TABLE IF NOT EXISTS training_modules (
    id INT NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    icon VARCHAR(80) NOT NULL DEFAULT 'bx-book-open',
    accent VARCHAR(32) NOT NULL DEFAULT 'blue',
    sort_order INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_modules_slug (slug),
    KEY idx_training_modules_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_lessons (
    id INT NOT NULL AUTO_INCREMENT,
    module_id INT NOT NULL,
    slug VARCHAR(120) NOT NULL,
    title VARCHAR(180) NOT NULL,
    description TEXT NULL,
    estimated_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    difficulty ENUM('Beginner','Intermediate','Advanced') NOT NULL DEFAULT 'Beginner',
    role_scope VARCHAR(255) NOT NULL DEFAULT 'all',
    content_json LONGTEXT NULL,
    audio_path VARCHAR(255) NULL,
    transcript MEDIUMTEXT NULL,
    animation_path VARCHAR(255) NULL,
    sort_order INT NOT NULL DEFAULT 100,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_lessons_slug (slug),
    KEY idx_training_lessons_module_sort (module_id, is_active, sort_order),
    CONSTRAINT fk_training_lessons_module
        FOREIGN KEY (module_id) REFERENCES training_modules(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS training_progress (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT NOT NULL,
    lesson_id INT NOT NULL,
    status ENUM('not_started','in_progress','completed') NOT NULL DEFAULT 'not_started',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    last_position VARCHAR(120) NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_training_progress_user_lesson (user_id, lesson_id),
    KEY idx_training_progress_user_status (user_id, status),
    KEY idx_training_progress_lesson (lesson_id),
    CONSTRAINT fk_training_progress_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_training_progress_lesson
        FOREIGN KEY (lesson_id) REFERENCES training_lessons(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_training_progress_percent CHECK (progress_percent BETWEEN 0 AND 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO training_modules (slug, title, description, icon, accent, sort_order, is_active)
VALUES
('defect-creation', 'Defect Creation', 'Raise clear, complete defects with the right project, contractor, evidence and priority.', 'bx-error-circle', 'blue', 10, 1),
('floor-plans', 'Floor Plans', 'Navigate drawings, zoom precisely and place a defect pin at the correct location.', 'bx-map-alt', 'cyan', 20, 1),
('defect-lifecycle', 'Contractor & Manager Workflow', 'Follow a defect from assignment through evidence, review, rejection, acceptance and closeout.', 'bx-transfer-alt', 'violet', 30, 1)
ON DUPLICATE KEY UPDATE
title = VALUES(title),
description = VALUES(description),
icon = VALUES(icon),
accent = VALUES(accent),
sort_order = VALUES(sort_order),
is_active = VALUES(is_active);

INSERT INTO training_lessons (
    module_id, slug, title, description, estimated_minutes, difficulty,
    role_scope, content_json, sort_order, is_active
)
SELECT m.id, 'create-a-defect', 'Create a Defect',
       'Learn the complete workflow for raising a clear, actionable defect.',
       8, 'Beginner', 'admin,manager,inspector',
       '{"outcomes":["Choose the correct project and contractor","Set priority and due date","Add photographic evidence","Select a floor plan and confirm the location"],"steps":[{"title":"Open Defect Ops","body":"Open Defect Ops and choose the option to raise a new defect."},{"title":"Describe the defect","body":"Add a concise title, clear description, project, contractor, priority and due date."},{"title":"Add evidence","body":"Attach a clear photo that shows the issue and surrounding context."},{"title":"Set the location","body":"Choose the project floor plan, navigate to the correct area and place the pin precisely."},{"title":"Review and submit","body":"Check the details before submitting the defect to the workflow."}],"try_url":"/create_defect.php"}',
       10, 1
FROM training_modules m WHERE m.slug = 'defect-creation'
ON DUPLICATE KEY UPDATE
title = VALUES(title), description = VALUES(description), estimated_minutes = VALUES(estimated_minutes),
difficulty = VALUES(difficulty), role_scope = VALUES(role_scope), content_json = VALUES(content_json),
sort_order = VALUES(sort_order), is_active = VALUES(is_active);

INSERT INTO training_lessons (
    module_id, slug, title, description, estimated_minutes, difficulty,
    role_scope, content_json, sort_order, is_active
)
SELECT m.id, 'floor-plan-location', 'Locate a Defect on a Floor Plan',
       'Use pan, zoom and pin placement accurately on desktop and mobile.',
       6, 'Beginner', 'admin,manager,inspector',
       '{"outcomes":["Pan around large drawings","Zoom with mouse or touch gestures","Place and fine-tune a defect pin","Reset the plan to fit the viewport"],"steps":[{"title":"Select the floor plan","body":"Choose the drawing associated with the project."},{"title":"Navigate in Pan mode","body":"Drag the plan to move around it and zoom until the target area is clear."},{"title":"Place the pin","body":"Switch to Place Pin mode and choose the exact defect location."},{"title":"Fine-tune and confirm","body":"Drag the pin if necessary, then confirm the location and return to the defect form."}],"try_url":"/create_defect.php"}',
       10, 1
FROM training_modules m WHERE m.slug = 'floor-plans'
ON DUPLICATE KEY UPDATE
title = VALUES(title), description = VALUES(description), estimated_minutes = VALUES(estimated_minutes),
difficulty = VALUES(difficulty), role_scope = VALUES(role_scope), content_json = VALUES(content_json),
sort_order = VALUES(sort_order), is_active = VALUES(is_active);

INSERT INTO training_lessons (
    module_id, slug, title, description, estimated_minutes, difficulty,
    role_scope, content_json, sort_order, is_active
)
SELECT m.id, 'contractor-manager-lifecycle', 'Contractor → Manager Defect Lifecycle',
       'Understand the complete operational handoff from assignment to closeout.',
       12, 'Intermediate', 'all',
       '{"outcomes":["Understand contractor actions","Submit completion evidence correctly","Review, reject or accept work","Reopen defects when further work is required"],"steps":[{"title":"Assignment","body":"The defect is assigned to a contractor with the required information and evidence."},{"title":"Contractor action","body":"The contractor reviews the defect, progresses the work and adds completion evidence."},{"title":"Submit for review","body":"Completed work is sent back to the manager for review."},{"title":"Manager decision","body":"The manager can accept the work or reject it with a clear reason for further action."},{"title":"Close or reopen","body":"Accepted defects can be closed, while defects requiring additional action can be reopened."}],"try_url":"/defects.php"}',
       10, 1
FROM training_modules m WHERE m.slug = 'defect-lifecycle'
ON DUPLICATE KEY UPDATE
title = VALUES(title), description = VALUES(description), estimated_minutes = VALUES(estimated_minutes),
difficulty = VALUES(difficulty), role_scope = VALUES(role_scope), content_json = VALUES(content_json),
sort_order = VALUES(sort_order), is_active = VALUES(is_active);
