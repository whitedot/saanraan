<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/helpers/member-groups.php';

return [
    [
        'rule_key' => 'page.paid_view_count_at_least',
        'label' => sr_t('page::member_group.rule.paid_view_count.label'),
        'description' => sr_t('page::member_group.rule.paid_view_count.description'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_paid_view_count_at_least',
    ],
    [
        'rule_key' => 'page.page.paid_view_count_at_least',
        'label' => sr_t('page::member_group.rule.page_paid_view_count.label'),
        'description' => sr_t('page::member_group.rule.page_paid_view_count.description'),
        'params' => [
            [
                'key' => 'page_id',
                'label' => sr_t('page::member_group.param.page_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_page_member_group_rule_page_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_page_paid_view_count_at_least',
    ],
    [
        'rule_key' => 'page.page_group.paid_view_count_at_least',
        'label' => sr_t('page::member_group.rule.page_group_paid_view_count.label'),
        'description' => sr_t('page::member_group.rule.page_group_paid_view_count.description'),
        'params' => [
            [
                'key' => 'page_group_id',
                'label' => sr_t('page::member_group.param.page_group_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_page_member_group_rule_page_group_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_page_group_paid_view_count_at_least',
    ],
    [
        'rule_key' => 'page.file_download_count_at_least',
        'label' => sr_t('page::member_group.rule.file_download_count.label'),
        'description' => sr_t('page::member_group.rule.file_download_count.description'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_file_download_count_at_least',
    ],
    [
        'rule_key' => 'page.page.file_download_count_at_least',
        'label' => sr_t('page::member_group.rule.page_file_download_count.label'),
        'description' => sr_t('page::member_group.rule.page_file_download_count.description'),
        'params' => [
            [
                'key' => 'page_id',
                'label' => sr_t('page::member_group.param.page_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_page_member_group_rule_page_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_page_file_download_count_at_least',
    ],
    [
        'rule_key' => 'page.page_group.file_download_count_at_least',
        'label' => sr_t('page::member_group.rule.page_group_file_download_count.label'),
        'description' => sr_t('page::member_group.rule.page_group_file_download_count.description'),
        'params' => [
            [
                'key' => 'page_group_id',
                'label' => sr_t('page::member_group.param.page_group_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_page_member_group_rule_page_group_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_page_group_file_download_count_at_least',
    ],
    [
        'rule_key' => 'page.action_count_at_least',
        'label' => sr_t('page::member_group.rule.action_count.label'),
        'description' => sr_t('page::member_group.rule.action_count.description'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_action_count_at_least',
    ],
    [
        'rule_key' => 'page.page.action_count_at_least',
        'label' => sr_t('page::member_group.rule.page_action_count.label'),
        'description' => sr_t('page::member_group.rule.page_action_count.description'),
        'params' => [
            [
                'key' => 'page_id',
                'label' => sr_t('page::member_group.param.page_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_page_member_group_rule_page_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_page_action_count_at_least',
    ],
    [
        'rule_key' => 'page.page_group.action_count_at_least',
        'label' => sr_t('page::member_group.rule.page_group_action_count.label'),
        'description' => sr_t('page::member_group.rule.page_group_action_count.description'),
        'params' => [
            [
                'key' => 'page_group_id',
                'label' => sr_t('page::member_group.param.page_group_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_page_member_group_rule_page_group_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('page::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_page_member_group_rule_page_group_action_count_at_least',
    ],
];
