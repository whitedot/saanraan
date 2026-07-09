<?php

return [
    'label' => sr_t('member::ui.member.e335b899'),
    'order' => 10,
    'items' => [
        [
            'label' => sr_t('member::ui.member.list.d8e6279a'),
            'path' => '/admin/members',
            'order' => 10,
        ],
        [
            'label' => sr_t('member::ui.member.7482bebf'),
            'path' => '/admin/member-groups',
            'order' => 20,
        ],
        [
            'label' => sr_t('member::ui.member.bc3daeb8'),
            'path' => '/admin/member-group-rules',
            'order' => 30,
        ],
        [
            'label' => sr_t('member::ui.member.settings.6b4c84f7'),
            'path' => '/admin/member-settings',
            'order' => 40,
        ],
        [
            'label' => '회원 알림/메일 관리',
            'path' => '/admin/member-notification-templates',
            'requires_modules' => ['notification'],
            'order' => 45,
        ],
    ],
];
