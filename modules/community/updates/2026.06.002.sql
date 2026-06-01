CREATE TABLE IF NOT EXISTS sr_community_series_scraps (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    series_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_series_scraps_account_series (account_id, series_id),
    KEY idx_sr_community_series_scraps_account_id (account_id, id),
    KEY idx_sr_community_series_scraps_series_id (series_id, id)
);

UPDATE sr_modules
SET version = '2026.06.002',
    updated_at = NOW()
WHERE module_key = 'community';
