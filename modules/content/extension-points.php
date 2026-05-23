<?php

return [
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
