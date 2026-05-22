<?php

return [
    [
        'point_key' => 'community.home',
        'label' => sr_t('community::contract.extension.community_home'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => sr_t('community::contract.slot.before_content'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => sr_t('community::contract.slot.after_content'),
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'community.board.list',
        'label' => sr_t('community::contract.extension.board_list'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_list',
                'label' => sr_t('community::contract.slot.before_list'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_list',
                'label' => sr_t('community::contract.slot.after_list'),
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'community.post.view',
        'label' => sr_t('community::contract.extension.post_view'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => sr_t('community::contract.slot.before_content'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_content',
                'label' => sr_t('community::contract.slot.after_content'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'before_comments',
                'label' => sr_t('community::contract.slot.before_comments'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_comments',
                'label' => sr_t('community::contract.slot.after_comments'),
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'community.post.form',
        'label' => sr_t('community::contract.extension.post_form'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_form',
                'label' => sr_t('community::contract.slot.before_form'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_form',
                'label' => sr_t('community::contract.slot.after_form'),
                'kind' => 'content',
            ],
        ],
    ],
];
