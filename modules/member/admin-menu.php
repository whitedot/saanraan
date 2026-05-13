<?php

return [
    'label' => '회원',
    'order' => 10,
    'items' => [
        [
            'label' => '회원 목록',
            'path' => '/admin/members',
            'order' => 5,
        ],
        [
            'label' => '기본 설정',
            'path' => '/admin/member-settings',
            'order' => 10,
        ],
        [
            'label' => '그룹',
            'path' => '/admin/member-groups',
            'order' => 20,
        ],
    ],
];
