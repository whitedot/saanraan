<?php

return [
    'label' => sr_t('content::ui.content.6c84a1b3'),
    'order' => 30,
    'items' => [
        [
            'label' => '콘텐츠 관리',
            'path' => '/admin/content',
            'order' => 10,
        ],
        [
            'label' => '콘텐츠 그룹 관리',
            'path' => '/admin/content-groups',
            'order' => 20,
        ],
        [
            'label' => '환경설정',
            'path' => '/admin/content/settings',
            'order' => 30,
        ],
        [
            'label' => '회원 그룹별 설정',
            'path' => '/admin/content/asset-policy-sets',
            'order' => 40,
        ],
    ],
];
