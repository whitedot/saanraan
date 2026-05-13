CREATE TABLE IF NOT EXISTS sr_admin_menu_overrides (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    scope VARCHAR(20) NOT NULL,
    target_key VARCHAR(190) NOT NULL,
    sort_order INT NOT NULL DEFAULT 1000,
    is_hidden TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_admin_menu_overrides_target (scope, target_key),
    KEY idx_sr_admin_menu_overrides_scope_order (scope, sort_order)
);

UPDATE sr_modules
SET version = '2026.05.002',
    updated_at = NOW()
WHERE module_key = 'admin';
