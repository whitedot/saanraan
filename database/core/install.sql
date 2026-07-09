CREATE TABLE IF NOT EXISTS sr_site_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_site_settings_key (setting_key)
);

CREATE TABLE IF NOT EXISTS sr_modules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_key VARCHAR(60) NOT NULL,
    name VARCHAR(120) NOT NULL,
    version VARCHAR(40) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'disabled',
    is_bundled TINYINT(1) NOT NULL DEFAULT 0,
    installed_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_modules_key (module_key)
);

CREATE TABLE IF NOT EXISTS sr_module_settings (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    module_id BIGINT UNSIGNED NOT NULL,
    setting_key VARCHAR(120) NOT NULL,
    setting_value TEXT NULL,
    value_type VARCHAR(20) NOT NULL DEFAULT 'string',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_module_settings_key (module_id, setting_key)
);

CREATE TABLE IF NOT EXISTS sr_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_id_hash CHAR(64) NOT NULL,
    payload MEDIUMBLOB NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_sessions_session_id_hash (session_id_hash),
    KEY idx_sr_sessions_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS sr_rate_limits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    rate_key CHAR(64) NOT NULL,
    bucket VARCHAR(120) NOT NULL,
    subject_hash CHAR(64) NOT NULL,
    attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_rate_limits_key (rate_key),
    KEY idx_sr_rate_limits_bucket_expires (bucket, expires_at),
    KEY idx_sr_rate_limits_expires (expires_at)
);

CREATE TABLE IF NOT EXISTS sr_delivery_template_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    template_key VARCHAR(190) NOT NULL,
    owner_module VARCHAR(60) NOT NULL,
    category VARCHAR(40) NOT NULL,
    subject_template VARCHAR(190) NOT NULL DEFAULT '',
    body_template TEXT NULL,
    link_template VARCHAR(255) NOT NULL DEFAULT '',
    channels_json TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    updated_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_delivery_template_overrides_key (template_key),
    KEY idx_sr_delivery_template_overrides_owner (owner_module, category, status),
    KEY idx_sr_delivery_template_overrides_updated (updated_at)
);

CREATE TABLE IF NOT EXISTS sr_schema_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope VARCHAR(20) NOT NULL,
    module_key VARCHAR(60) NOT NULL DEFAULT '',
    version VARCHAR(40) NOT NULL,
    applied_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_schema_versions (scope, module_key, version)
);

CREATE TABLE IF NOT EXISTS sr_audit_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actor_account_id BIGINT UNSIGNED NULL,
    actor_type VARCHAR(40) NOT NULL DEFAULT 'system',
    event_type VARCHAR(80) NOT NULL,
    target_type VARCHAR(60) NOT NULL DEFAULT '',
    target_id VARCHAR(120) NOT NULL DEFAULT '',
    result VARCHAR(30) NOT NULL DEFAULT 'success',
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT NULL,
    message VARCHAR(255) NOT NULL DEFAULT '',
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_audit_logs_actor (actor_account_id),
    KEY idx_sr_audit_logs_event (event_type),
    KEY idx_sr_audit_logs_target (target_type, target_id),
    KEY idx_sr_audit_logs_created (created_at)
);

CREATE TABLE IF NOT EXISTS sr_url_embed_cache (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_module VARCHAR(60) NOT NULL,
    owner_type VARCHAR(60) NOT NULL,
    owner_id BIGINT UNSIGNED NOT NULL,
    owner_field VARCHAR(60) NOT NULL DEFAULT 'body',
    source_url VARCHAR(1000) NOT NULL,
    canonical_url VARCHAR(1000) NOT NULL,
    canonical_url_hash CHAR(64) NOT NULL,
    embed_kind VARCHAR(30) NOT NULL DEFAULT 'internal_url',
    provider_key VARCHAR(60) NOT NULL DEFAULT '',
    render_variant VARCHAR(60) NOT NULL DEFAULT 'summary',
    target_module VARCHAR(60) NOT NULL DEFAULT '',
    target_type VARCHAR(60) NOT NULL DEFAULT '',
    target_id VARCHAR(80) NOT NULL DEFAULT '',
    target_cache_version VARCHAR(120) NOT NULL DEFAULT '',
    label_snapshot VARCHAR(255) NOT NULL DEFAULT '',
    summary_snapshot TEXT NULL,
    image_snapshot VARCHAR(500) NOT NULL DEFAULT '',
    image_snapshot_policy VARCHAR(30) NOT NULL DEFAULT 'none',
    target_state VARCHAR(30) NOT NULL DEFAULT '',
    resolver_state VARCHAR(30) NOT NULL DEFAULT '',
    cache_status VARCHAR(30) NOT NULL DEFAULT 'fresh',
    resolved_payload_json TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    last_resolved_at DATETIME NULL,
    last_render_checked_at DATETIME NULL,
    created_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_sr_url_embed_owner_hash (owner_module, owner_type, owner_id, owner_field, canonical_url_hash),
    KEY idx_sr_url_embed_owner (owner_module, owner_type, owner_id, owner_field, cache_status, sort_order),
    KEY idx_sr_url_embed_target (target_module, target_type, target_id, cache_status),
    KEY idx_sr_url_embed_hash (canonical_url_hash)
);
