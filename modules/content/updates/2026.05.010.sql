ALTER TABLE sr_content_items
    ADD COLUMN asset_access_amounts_json TEXT NULL AFTER asset_access_amount,
    ADD COLUMN asset_action_amounts_json TEXT NULL AFTER asset_action_amount;

ALTER TABLE sr_content_revisions
    ADD COLUMN asset_access_amounts_json TEXT NULL AFTER asset_access_amount,
    ADD COLUMN asset_action_amounts_json TEXT NULL AFTER asset_action_amount;

ALTER TABLE sr_content_files
    ADD COLUMN asset_download_amounts_json TEXT NULL AFTER asset_download_amount;
