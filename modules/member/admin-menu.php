<?php

return [
    'label' => sr_t('member::ui.member.e335b899'),
    'order' => 10,
    'items' => [
        [
            'label' => sr_t('member::ui.member.list.d8e6279a'),
            'path' => '/admin/members',
            'order' => 5,
        ],
        [
            'label' => sr_t('member::ui.settings.9d52d064'),
            'path' => '/admin/member-settings',
            'order' => 10,
        ],
        [
            'label' => sr_t('member::ui.text.5d908ddd'),
            'path' => '/admin/member-groups',
            'order' => 20,
        ],
        [
            'label' => sr_t('member::ui.text.c40d1648'),
            'path' => '/admin/member-group-rules',
            'order' => 30,
        ],
        [
            'label' => sr_t('member::ui.text.3d1d323a'),
            'path' => '/admin/member-group-evaluations',
            'order' => 40,
        ],
        [
            'label' => sr_t('member::ui.text.94e3ebac'),
            'path' => '/admin/member-group-assignments',
            'order' => 50,
        ],
    ],
];
