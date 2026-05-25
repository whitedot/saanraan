CREATE TABLE IF NOT EXISTS sr_member_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    phone VARCHAR(40) NOT NULL DEFAULT '',
    birth_date DATE NULL,
    avatar_path VARCHAR(255) NOT NULL DEFAULT '',
    profile_text TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_member_profiles_account (account_id)
);
