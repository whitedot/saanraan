<?php

return [
    [
        'key' => 'member',
        'title' => '회원',
        'order' => 10,
        'view' => 'views/dashboard-summary.php',
        'items' => [
            [
                'label' => '활성 회원',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_member_accounts WHERE status = 'active'",
                'detail_prefix' => '최근 7일 가입 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_member_accounts WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                'state' => 'success',
                'emphasis' => 'primary',
            ],
            [
                'label' => '회원 그룹',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_member_groups WHERE status = 'enabled'",
                'detail_prefix' => '활성 배정 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_member_group_memberships WHERE status = 'active'",
                'state' => 'info',
            ],
        ],
    ],
];
