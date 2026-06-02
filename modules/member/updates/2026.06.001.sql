CREATE TABLE IF NOT EXISTS sr_member_nicknames (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    nickname VARCHAR(80) NOT NULL,
    nickname_lookup VARCHAR(80) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_nicknames_account (account_id),
    UNIQUE KEY uq_sr_member_nicknames_lookup (nickname_lookup)
);
