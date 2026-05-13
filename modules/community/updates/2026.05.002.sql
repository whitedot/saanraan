CREATE TABLE IF NOT EXISTS sr_community_board_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_groups_key (group_key),
    KEY idx_sr_community_board_groups_status_sort (status, sort_order, id)
);

ALTER TABLE sr_community_boards
    ADD COLUMN board_group_id BIGINT UNSIGNED NULL AFTER id,
    ADD KEY idx_sr_community_boards_group_sort (board_group_id, sort_order, id);

CREATE TABLE IF NOT EXISTS sr_community_board_group_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_group_settings_key (group_id, setting_key),
    KEY idx_sr_community_board_group_settings_group (group_id)
);

CREATE TABLE IF NOT EXISTS sr_community_board_setting_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'board',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_board_setting_sources_key (board_id, setting_key),
    KEY idx_sr_community_board_setting_sources_board (board_id)
);

UPDATE sr_modules
SET version = '2026.05.002',
    updated_at = NOW()
WHERE module_key = 'community';
