CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}quiz_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(64) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_quiz_groups_key (group_key),
    KEY idx_sr_quiz_groups_status_sort (status, sort_order, id)
);

ALTER TABLE {{SR_TABLE_PREFIX}}quiz_sets
    ADD COLUMN quiz_group_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_sr_quiz_sets_group (quiz_group_id, id);

CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}quiz_setting_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    quiz_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(80) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'item',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_quiz_setting_sources_key (quiz_id, setting_key),
    KEY idx_sr_quiz_setting_sources_quiz (quiz_id)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.005',
    updated_at = NOW()
WHERE module_key = 'quiz';
