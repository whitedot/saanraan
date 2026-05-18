<?php

return [
    [
        'point_key' => 'page.view',
        'label' => '페이지 보기',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => '본문 위',
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => '본문 아래',
                'kind' => 'content',
            ],
        ],
    ],
];
