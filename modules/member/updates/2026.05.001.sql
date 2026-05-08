CREATE TABLE IF NOT EXISTS toy_member_groups (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    is_system TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_member_groups_key (group_key),
    KEY idx_toy_member_groups_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS toy_member_group_memberships (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    assignment_type VARCHAR(30) NOT NULL DEFAULT 'manual',
    source_module_key VARCHAR(60) NOT NULL DEFAULT '',
    source_rule_key VARCHAR(120) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    granted_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    created_by_account_id BIGINT UNSIGNED NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_member_group_memberships_group_status_account (group_id, status, account_id),
    KEY idx_toy_member_group_memberships_account_status_group (account_id, status, group_id),
    KEY idx_toy_member_group_memberships_source_status (source_module_key, source_rule_key, status)
);

CREATE TABLE IF NOT EXISTS toy_member_group_rules (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    source_module_key VARCHAR(60) NOT NULL,
    rule_key VARCHAR(120) NOT NULL,
    rule_params_json TEXT NOT NULL,
    evaluation_policy VARCHAR(30) NOT NULL DEFAULT 'grant_only',
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    last_evaluated_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_member_group_rules_group_status (group_id, status),
    KEY idx_toy_member_group_rules_source_status (source_module_key, rule_key, status)
);

CREATE TABLE IF NOT EXISTS toy_member_group_membership_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    group_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    membership_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(60) NOT NULL,
    source_module_key VARCHAR(60) NOT NULL DEFAULT '',
    source_rule_key VARCHAR(120) NOT NULL DEFAULT '',
    actor_account_id BIGINT UNSIGNED NULL,
    message VARCHAR(255) NOT NULL DEFAULT '',
    metadata_json TEXT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_member_group_membership_logs_group_account (group_id, account_id),
    KEY idx_toy_member_group_membership_logs_account_created (account_id, created_at),
    KEY idx_toy_member_group_membership_logs_source (source_module_key, source_rule_key)
);
