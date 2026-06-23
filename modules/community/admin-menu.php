<?php

return [
    'label' => sr_t('community::ui.community.4a285775'),
    'order' => 29,
    'items' => [
        [
            'label' => '게시판 관리',
            'path' => '/admin/community/boards',
            'order' => 20,
        ],
        [
            'label' => '게시판 그룹 관리',
            'path' => '/admin/community/board-groups',
            'order' => 30,
        ],
        [
            'label' => sr_t('community::ui.nav.posts'),
            'path' => '/admin/community/posts',
            'order' => 40,
        ],
        [
            'label' => sr_t('community::ui.nav.comments'),
            'path' => '/admin/community/comments',
            'order' => 50,
        ],
        [
            'label' => sr_t('community::ui.nav.reports'),
            'path' => '/admin/community/reports',
            'order' => 60,
        ],
        [
            'label' => '시리즈 관리',
            'path' => '/admin/community/series',
            'order' => 65,
        ],
        [
            'label' => sr_t('community::ui.nav.settings'),
            'path' => '/admin/community/settings',
            'order' => 70,
        ],
        [
            'label' => sr_t('community::ui.text.b2845de5'),
            'path' => '/admin/community/levels',
            'order' => 80,
        ],
        [
            'label' => '회원 그룹별 설정',
            'path' => '/admin/community/asset-policy-sets',
            'order' => 90,
        ],
        [
            'label' => '리워드 로그',
            'path' => '/admin/community/publisher-rewards',
            'order' => 95,
        ],
        [
            'label' => '보상 미회수',
            'path' => '/admin/community/recovery-failures',
            'order' => 96,
        ],
    ],
];
