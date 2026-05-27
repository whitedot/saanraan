ALTER TABLE sr_content_items
    ADD COLUMN asset_access_group_policies_json TEXT NULL AFTER asset_access_amounts_json,
    ADD COLUMN asset_access_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_access_group_policies_json,
    ADD COLUMN asset_action_group_policies_json TEXT NULL AFTER asset_action_amounts_json,
    ADD COLUMN asset_action_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_action_group_policies_json;

ALTER TABLE sr_content_revisions
    ADD COLUMN asset_access_group_policies_json TEXT NULL AFTER asset_access_amounts_json,
    ADD COLUMN asset_access_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_access_group_policies_json,
    ADD COLUMN asset_action_group_policies_json TEXT NULL AFTER asset_action_amounts_json,
    ADD COLUMN asset_action_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_action_group_policies_json;

ALTER TABLE sr_content_files
    ADD COLUMN asset_download_group_policies_json TEXT NULL AFTER asset_download_amounts_json,
    ADD COLUMN asset_download_policy_set_id BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER asset_download_group_policies_json;

ALTER TABLE sr_content_asset_access_logs
    ADD COLUMN group_policy_snapshot_json TEXT NULL AFTER amount;

ALTER TABLE sr_content_asset_action_logs
    ADD COLUMN group_policy_snapshot_json TEXT NULL AFTER amount;

CREATE TABLE IF NOT EXISTS sr_content_asset_policy_sets (
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
    UNIQUE KEY uq_sr_content_asset_policy_sets_key (set_key),
    KEY idx_sr_content_asset_policy_sets_status (status, title)
);
