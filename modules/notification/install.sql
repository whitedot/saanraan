CREATE TABLE IF NOT EXISTS toy_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NULL,
    audience VARCHAR(30) NOT NULL DEFAULT 'account',
    title VARCHAR(160) NOT NULL,
    body_text TEXT NULL,
    link_url VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    read_at DATETIME NULL,
    created_by_account_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_notifications_account (account_id, status, read_at, id),
    KEY idx_toy_notifications_audience (audience, status, id)
);

CREATE TABLE IF NOT EXISTS toy_notification_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    channel VARCHAR(30) NOT NULL,
    recipient VARCHAR(255) NOT NULL DEFAULT '',
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    provider_message_id VARCHAR(120) NOT NULL DEFAULT '',
    error_message VARCHAR(255) NOT NULL DEFAULT '',
    attempted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_toy_notification_deliveries_notification (notification_id),
    KEY idx_toy_notification_deliveries_channel_status (channel, status, id)
);

CREATE TABLE IF NOT EXISTS toy_notification_reads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NOT NULL,
    read_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_toy_notification_reads (notification_id, account_id),
    KEY idx_toy_notification_reads_account (account_id, read_at)
);
