CREATE TABLE IF NOT EXISTS sr_admin_account_roles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    role_key VARCHAR(40) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_admin_account_roles (account_id, role_key)
);

CREATE TABLE IF NOT EXISTS sr_admin_account_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    menu_path VARCHAR(190) NOT NULL,
    action_key VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_admin_account_permissions (account_id, menu_path, action_key),
    KEY idx_sr_admin_account_permissions_menu (menu_path, action_key)
);

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
