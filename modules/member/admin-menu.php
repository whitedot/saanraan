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
        [
            'label' => '자동 규칙',
            'path' => '/admin/member-group-rules',
            'order' => 30,
        ],
        [
            'label' => '재평가',
            'path' => '/admin/member-group-evaluations',
            'order' => 40,
        ],
        [
            'label' => '수동 배정',
            'path' => '/admin/member-group-assignments',
            'order' => 50,
        ],
    ],
];
