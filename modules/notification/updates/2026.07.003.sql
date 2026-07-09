CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}notification_channel_template_bindings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_key VARCHAR(60) NOT NULL,
    event_key VARCHAR(120) NOT NULL,
    channel VARCHAR(30) NOT NULL,
    provider_template_code VARCHAR(120) NOT NULL DEFAULT '',
    variables_json TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_notification_channel_template_binding (module_key, event_key, channel),
    KEY idx_sr_notification_channel_template_bindings_status (channel, status, module_key, event_key)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.07.003',
    updated_at = NOW()
WHERE module_key = 'notification';
