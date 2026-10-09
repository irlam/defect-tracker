-- STAGING ONLY: apply to a new fixture-only database, never the existing live database.
-- Binding values are inserted separately by the deployment owner.
CREATE TABLE suite_instance_binding (
    singleton TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    instance_id BIGINT UNSIGNED NOT NULL,
    organization_id BIGINT UNSIGNED NOT NULL,
    suite_project_id BIGINT UNSIGNED NOT NULL,
    local_project_id INT NOT NULL,
    module_key VARCHAR(32) NOT NULL,
    CONSTRAINT suite_binding_singleton CHECK (singleton = 1),
    CONSTRAINT suite_binding_module CHECK (module_key = 'defects'),
    CONSTRAINT suite_binding_positive CHECK (instance_id > 0 AND organization_id > 0 AND suite_project_id > 0 AND local_project_id > 0),
    CONSTRAINT suite_binding_project FOREIGN KEY (local_project_id) REFERENCES projects(id)
) ENGINE=InnoDB;
