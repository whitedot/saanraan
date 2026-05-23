CREATE TABLE IF NOT EXISTS sr_admin_account_permissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    account_id BIGINT UNSIGNED NOT NULL,
    menu_path VARCHAR(190) NOT NULL,
    action_key VARCHAR(20) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_admin_account_permissions (account_id, menu_path, action_key),
    KEY idx_sr_admin_account_permissions_menu (menu_path, action_key)
);

INSERT IGNORE INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at)
SELECT account_id, menu_path, action_key, NOW()
FROM (
    SELECT r.account_id, p.menu_path, a.action_key
    FROM sr_admin_account_roles r
    JOIN (
        SELECT '/admin' AS menu_path
        UNION ALL SELECT '/admin/settings'
        UNION ALL SELECT '/admin/modules'
        UNION ALL SELECT '/admin/menu'
        UNION ALL SELECT '/admin/audit-logs'
        UNION ALL SELECT '/admin/members'
        UNION ALL SELECT '/admin/member-settings'
        UNION ALL SELECT '/admin/member-groups'
        UNION ALL SELECT '/admin/member-group-rules'
        UNION ALL SELECT '/admin/member-group-evaluations'
        UNION ALL SELECT '/admin/member-group-assignments'
        UNION ALL SELECT '/admin/logo-manager'
        UNION ALL SELECT '/admin/site-menus'
        UNION ALL SELECT '/admin/seo'
        UNION ALL SELECT '/admin/banners'
        UNION ALL SELECT '/admin/banners/settings'
        UNION ALL SELECT '/admin/popup-layers'
        UNION ALL SELECT '/admin/popup-layers/settings'
        UNION ALL SELECT '/admin/content'
        UNION ALL SELECT '/admin/content-groups'
        UNION ALL SELECT '/admin/community/settings'
        UNION ALL SELECT '/admin/community/boards'
        UNION ALL SELECT '/admin/community/levels'
        UNION ALL SELECT '/admin/community/board-groups'
        UNION ALL SELECT '/admin/community/posts'
        UNION ALL SELECT '/admin/community/comments'
        UNION ALL SELECT '/admin/community/reports'
        UNION ALL SELECT '/admin/points/balances'
        UNION ALL SELECT '/admin/points/transactions'
        UNION ALL SELECT '/admin/deposits/balances'
        UNION ALL SELECT '/admin/deposits/transactions'
        UNION ALL SELECT '/admin/rewards/balances'
        UNION ALL SELECT '/admin/rewards/transactions'
        UNION ALL SELECT '/admin/notifications'
        UNION ALL SELECT '/admin/notification-deliveries'
        UNION ALL SELECT '/admin/privacy-requests'
    ) p
    JOIN (
        SELECT 'view' AS action_key
        UNION ALL SELECT 'edit'
        UNION ALL SELECT 'delete'
    ) a
    WHERE r.role_key = 'admin'
    UNION ALL
    SELECT r.account_id, p.menu_path, a.action_key
    FROM sr_admin_account_roles r
    JOIN (
        SELECT '/admin' AS menu_path
        UNION ALL SELECT '/admin/members'
        UNION ALL SELECT '/admin/member-groups'
        UNION ALL SELECT '/admin/community/settings'
        UNION ALL SELECT '/admin/community/boards'
        UNION ALL SELECT '/admin/community/board-groups'
        UNION ALL SELECT '/admin/community/posts'
        UNION ALL SELECT '/admin/community/comments'
        UNION ALL SELECT '/admin/community/reports'
    ) p
    JOIN (
        SELECT 'view' AS action_key
        UNION ALL SELECT 'edit'
    ) a
    WHERE r.role_key = 'manager'
) migrated_permissions;

DELETE FROM sr_admin_account_roles WHERE role_key <> 'owner';

UPDATE sr_modules
SET version = '2026.05.003'
WHERE module_key = 'admin';
