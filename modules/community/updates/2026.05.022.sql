ALTER TABLE sr_community_asset_logs
    ADD COLUMN group_policy_snapshot_json TEXT NULL AFTER amount;

CREATE TABLE IF NOT EXISTS sr_community_asset_policy_sets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    set_key VARCHAR(60) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    policies_json TEXT NULL,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_asset_policy_sets_key (set_key),
    KEY idx_sr_community_asset_policy_sets_status (status, title)
);
