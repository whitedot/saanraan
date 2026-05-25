CREATE TABLE IF NOT EXISTS sr_coupon_definitions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    coupon_type VARCHAR(40) NOT NULL DEFAULT 'access',
    target_type VARCHAR(60) NOT NULL DEFAULT 'all',
    target_id VARCHAR(80) NOT NULL DEFAULT '',
    refundable_policy VARCHAR(30) NOT NULL DEFAULT 'none',
    max_uses_per_issue INT UNSIGNED NOT NULL DEFAULT 1,
    valid_from DATETIME NULL,
    valid_until DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_coupon_definitions_key (coupon_key),
    KEY idx_sr_coupon_definitions_status_target (status, target_type, target_id)
);

CREATE TABLE IF NOT EXISTS sr_coupon_issues (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_definition_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    issued_reason VARCHAR(255) NOT NULL DEFAULT '',
    issued_by_account_id BIGINT UNSIGNED NULL,
    issued_at DATETIME NOT NULL,
    expires_at DATETIME NULL,
    used_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_coupon_issues_account_status (account_id, status, expires_at, id),
    KEY idx_sr_coupon_issues_definition (coupon_definition_id, status, id)
);

CREATE TABLE IF NOT EXISTS sr_coupon_redemptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    coupon_issue_id BIGINT UNSIGNED NOT NULL,
    coupon_definition_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    target_type VARCHAR(60) NOT NULL,
    target_id VARCHAR(80) NOT NULL DEFAULT '',
    reference_module VARCHAR(60) NOT NULL DEFAULT '',
    reference_type VARCHAR(80) NOT NULL DEFAULT '',
    reference_id VARCHAR(120) NOT NULL DEFAULT '',
    dedupe_key VARCHAR(160) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'redeemed',
    redeemed_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_coupon_redemptions_dedupe (dedupe_key),
    KEY idx_sr_coupon_redemptions_account (account_id, redeemed_at),
    KEY idx_sr_coupon_redemptions_issue (coupon_issue_id, id),
    KEY idx_sr_coupon_redemptions_reference (reference_module, reference_type, reference_id)
);
