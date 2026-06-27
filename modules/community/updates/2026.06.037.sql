CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_feed_cache (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    context_hash CHAR(64) NOT NULL,
    feed_key VARCHAR(120) NOT NULL,
    sort_key VARCHAR(30) NOT NULL DEFAULT 'latest',
    locale VARCHAR(20) NOT NULL DEFAULT 'ko',
    policy_version VARCHAR(80) NOT NULL DEFAULT 'v1',
    baseline VARCHAR(80) NOT NULL DEFAULT '',
    board_ids_json TEXT NOT NULL,
    display_count INT NOT NULL DEFAULT 0,
    fetch_count INT NOT NULL DEFAULT 0,
    snapshot_json MEDIUMTEXT NOT NULL,
    snapshot_count INT NOT NULL DEFAULT 0,
    cache_status VARCHAR(20) NOT NULL DEFAULT 'fresh',
    generated_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    stale_reason VARCHAR(120) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_community_feed_cache_context (context_hash),
    KEY idx_sr_community_feed_cache_status_expires (cache_status, expires_at),
    KEY idx_sr_community_feed_cache_feed_status (feed_key, cache_status, updated_at)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.037',
    updated_at = NOW()
WHERE module_key = 'community';
