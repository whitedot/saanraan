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

INSERT INTO sr_member_nicknames (account_id, nickname, nickname_lookup, created_at, updated_at)
SELECT cn.account_id, cn.nickname, LOWER(cn.nickname), cn.created_at, cn.updated_at
FROM sr_community_member_nicknames cn
LEFT JOIN sr_member_nicknames mn ON mn.account_id = cn.account_id
WHERE mn.account_id IS NULL
  AND cn.nickname <> '';

INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
SELECT member_module.id, community_setting.setting_key, community_setting.setting_value, community_setting.value_type, NOW(), NOW()
FROM sr_modules member_module
INNER JOIN sr_modules community_module ON community_module.module_key = 'community'
INNER JOIN sr_module_settings community_setting ON community_setting.module_id = community_module.id
LEFT JOIN sr_module_settings member_setting
    ON member_setting.module_id = member_module.id
   AND member_setting.setting_key = community_setting.setting_key
WHERE member_module.module_key = 'member'
  AND community_setting.setting_key IN ('nickname_enabled', 'nickname_required')
  AND member_setting.id IS NULL;
