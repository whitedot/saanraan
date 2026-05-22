<?php

return [
    [
        'point_key' => 'member.login',
        'label' => sr_t('member::contract.extension.member_login'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_form',
                'label' => sr_t('member::contract.slot.before_form'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_form',
                'label' => sr_t('member::contract.slot.after_form'),
                'kind' => 'content',
            ],
        ],
    ],
    [
        'point_key' => 'member.register',
        'label' => sr_t('member::contract.extension.member_register'),
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_form',
                'label' => sr_t('member::contract.slot.before_form'),
                'kind' => 'content',
            ],
            [
                'slot_key' => 'after_form',
                'label' => sr_t('member::contract.slot.after_form'),
                'kind' => 'content',
            ],
        ],
    ],
];
