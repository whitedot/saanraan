CREATE TABLE IF NOT EXISTS toy_banners (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(120) NOT NULL,
    body_text TEXT NULL,
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    image_url VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    starts_at DATETIME NULL,
    ends_at DATETIME NULL,
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_banners_status_dates (status, starts_at, ends_at),
    KEY idx_toy_banners_sort (sort_order, id)
);

CREATE TABLE IF NOT EXISTS toy_banner_targets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    banner_id BIGINT UNSIGNED NOT NULL,
    module_key VARCHAR(60) NOT NULL,
    point_key VARCHAR(120) NOT NULL,
    slot_key VARCHAR(80) NOT NULL,
    subject_id VARCHAR(80) NOT NULL DEFAULT '',
    match_type VARCHAR(20) NOT NULL DEFAULT 'all',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_banner_targets_banner (banner_id),
    KEY idx_toy_banner_targets_lookup (module_key, point_key, slot_key, match_type, subject_id, banner_id)
);
