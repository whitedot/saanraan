CREATE TABLE IF NOT EXISTS sr_page_group_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_page_group_settings_key (group_id, setting_key),
    KEY idx_sr_page_group_settings_group (group_id)
);

CREATE TABLE IF NOT EXISTS sr_page_setting_sources (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    source VARCHAR(20) NOT NULL DEFAULT 'page',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_page_setting_sources_key (page_id, setting_key),
    KEY idx_sr_page_setting_sources_page (page_id)
);
