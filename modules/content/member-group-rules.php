<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/helpers/member-groups.php';

return [
    [
        'rule_key' => 'content.paid_view_count_at_least',
        'label' => sr_t('content::member_group.rule.paid_view_count.label'),
        'description' => sr_t('content::member_group.rule.paid_view_count.description'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_paid_view_count_at_least',
    ],
    [
        'rule_key' => 'content.item.paid_view_count_at_least',
        'label' => sr_t('content::member_group.rule.content_paid_view_count.label'),
        'description' => sr_t('content::member_group.rule.content_paid_view_count.description'),
        'params' => [
            [
                'key' => 'content_id',
                'label' => sr_t('content::member_group.param.content_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_content_member_group_rule_content_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_content_paid_view_count_at_least',
    ],
    [
        'rule_key' => 'content.group.paid_view_count_at_least',
        'label' => sr_t('content::member_group.rule.content_group_paid_view_count.label'),
        'description' => sr_t('content::member_group.rule.content_group_paid_view_count.description'),
        'params' => [
            [
                'key' => 'content_group_id',
                'label' => sr_t('content::member_group.param.content_group_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_content_member_group_rule_content_group_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_content_group_paid_view_count_at_least',
    ],
    [
        'rule_key' => 'content.file_download_count_at_least',
        'label' => sr_t('content::member_group.rule.file_download_count.label'),
        'description' => sr_t('content::member_group.rule.file_download_count.description'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_file_download_count_at_least',
    ],
    [
        'rule_key' => 'content.item.file_download_count_at_least',
        'label' => sr_t('content::member_group.rule.content_file_download_count.label'),
        'description' => sr_t('content::member_group.rule.content_file_download_count.description'),
        'params' => [
            [
                'key' => 'content_id',
                'label' => sr_t('content::member_group.param.content_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_content_member_group_rule_content_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_content_file_download_count_at_least',
    ],
    [
        'rule_key' => 'content.group.file_download_count_at_least',
        'label' => sr_t('content::member_group.rule.content_group_file_download_count.label'),
        'description' => sr_t('content::member_group.rule.content_group_file_download_count.description'),
        'params' => [
            [
                'key' => 'content_group_id',
                'label' => sr_t('content::member_group.param.content_group_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_content_member_group_rule_content_group_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_content_group_file_download_count_at_least',
    ],
    [
        'rule_key' => 'content.action_count_at_least',
        'label' => sr_t('content::member_group.rule.action_count.label'),
        'description' => sr_t('content::member_group.rule.action_count.description'),
        'params' => [
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_action_count_at_least',
    ],
    [
        'rule_key' => 'content.item.action_count_at_least',
        'label' => sr_t('content::member_group.rule.content_action_count.label'),
        'description' => sr_t('content::member_group.rule.content_action_count.description'),
        'params' => [
            [
                'key' => 'content_id',
                'label' => sr_t('content::member_group.param.content_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_content_member_group_rule_content_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_content_action_count_at_least',
    ],
    [
        'rule_key' => 'content.group.action_count_at_least',
        'label' => sr_t('content::member_group.rule.content_group_action_count.label'),
        'description' => sr_t('content::member_group.rule.content_group_action_count.description'),
        'params' => [
            [
                'key' => 'content_group_id',
                'label' => sr_t('content::member_group.param.content_group_id'),
                'type' => 'subject',
                'default' => 0,
                'options_callback' => 'sr_content_member_group_rule_content_group_options',
            ],
            [
                'key' => 'min_count',
                'label' => sr_t('content::member_group.param.min_count'),
                'type' => 'int',
                'min' => 1,
                'max' => 10000,
                'default' => 1,
            ],
        ],
        'evaluator' => 'sr_content_member_group_rule_content_group_action_count_at_least',
    ],
];
