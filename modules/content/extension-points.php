<?php

return [
    [
        'point_key' => 'content.home',
        'label' => '콘텐츠 초기화면',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'screen',
                'label' => '화면',
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'content.sidebar.summary',
        'label' => '콘텐츠 사이드 요약',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'after_summary',
                'label' => '요약 뒤',
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'content.view',
        'label' => sr_t('content::contract.extension.content_view'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => sr_t('content::contract.slot.before_content'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => sr_t('content::contract.slot.after_content'),
                'kind' => 'content',
            ],
        ],
    ],
];
