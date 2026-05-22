<?php

return [
    [
        'point_key' => 'page.view',
        'label' => sr_t('page::contract.extension.page_view'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => sr_t('page::contract.slot.before_content'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => sr_t('page::contract.slot.after_content'),
                'kind' => 'content',
            ],
        ],
    ],
];
