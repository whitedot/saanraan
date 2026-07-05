CREATE TABLE IF NOT EXISTS sr_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    sender_account_id BIGINT UNSIGNED NOT NULL,
    recipient_account_id BIGINT UNSIGNED NOT NULL,
    body_text TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'sent',
    read_at DATETIME NULL,
    sender_deleted_at DATETIME NULL,
    recipient_deleted_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_sr_messages_recipient_deleted_id (recipient_account_id, recipient_deleted_at, id),
    KEY idx_sr_messages_sender_deleted_id (sender_account_id, sender_deleted_at, id),
    KEY idx_sr_messages_status_created (status, created_at)
);

CREATE TABLE IF NOT EXISTS sr_message_member_settings (
    account_id BIGINT UNSIGNED NOT NULL,
    receive_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (account_id),
    KEY idx_sr_message_member_settings_receive (receive_enabled, updated_at)
);
