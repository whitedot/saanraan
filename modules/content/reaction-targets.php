<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/helpers/assets.php';
require_once __DIR__ . '/helpers/body-files.php';
require_once __DIR__ . '/helpers/comments.php';

if (!function_exists('sr_content_reaction_account')) {
    function sr_content_reaction_account(int $accountId): ?array
    {
        return $accountId > 0 ? ['id' => $accountId] : null;
    }
}

if (!function_exists('sr_content_reaction_content_status')) {
    function sr_content_reaction_content_status(array $row): string
    {
        $status = (string) ($row['status'] ?? '');
        if ($status === 'published') {
            return 'active';
        }
        if ($status === 'deleted') {
            return 'deleted';
        }

        return $status === '' ? 'broken' : 'private';
    }
}

if (!function_exists('sr_content_reaction_content_result')) {
    function sr_content_reaction_content_result(PDO $pdo, array $row, int $viewerAccountId): array
    {
        $contentId = (int) ($row['id'] ?? 0);
        $status = sr_content_reaction_content_status($row);
        $account = sr_content_reaction_account($viewerAccountId);
        $canView = $status === 'active' && sr_content_can_access_body_file($pdo, $row, $account);
        $settings = sr_content_settings($pdo);
        $presetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $row['reaction_preset_key'] ?? '') : '';
        if ($presetKey === '') {
            $presetKey = (string) ($settings['reaction_preset_key'] ?? '');
        }

        return [
            'target_id' => (string) $contentId,
            'label' => (string) ($row['title'] ?? ''),
            'public_url' => sr_content_path((string) ($row['slug'] ?? '')),
            'admin_url' => '/admin/content?mode=edit&id=' . rawurlencode((string) $contentId),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => (int) ($row['created_by'] ?? 0),
            'recipient_account_id' => (int) ($row['created_by'] ?? 0),
            'preset_key' => $presetKey,
        ];
    }
}

if (!function_exists('sr_content_reaction_comment_result')) {
    function sr_content_reaction_comment_result(PDO $pdo, array $row, int $viewerAccountId): array
    {
        $commentStatus = (string) ($row['comment_status'] ?? '');
        $contentStatus = sr_content_reaction_content_status([
            'status' => (string) ($row['content_status'] ?? ''),
        ]);
        if ($commentStatus === 'deleted' || $contentStatus === 'deleted') {
            $status = 'deleted';
        } elseif ($commentStatus === 'published' && $contentStatus === 'active') {
            $status = 'active';
        } elseif ($commentStatus === '') {
            $status = 'broken';
        } else {
            $status = 'private';
        }

        $page = [
            'id' => (int) ($row['content_id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'title' => (string) ($row['content_title'] ?? ''),
            'status' => (string) ($row['content_status'] ?? ''),
            'created_by' => (int) ($row['content_owner_account_id'] ?? 0),
            'asset_access_enabled' => (int) ($row['asset_access_enabled'] ?? 0),
            'asset_module' => (string) ($row['asset_module'] ?? ''),
            'asset_access_amount' => (int) ($row['asset_access_amount'] ?? 0),
            'asset_access_amounts_json' => (string) ($row['asset_access_amounts_json'] ?? ''),
            'asset_access_group_policies_json' => (string) ($row['asset_access_group_policies_json'] ?? ''),
            'asset_access_policy_set_id' => (int) ($row['asset_access_policy_set_id'] ?? 0),
            'asset_charge_policy' => (string) ($row['asset_charge_policy'] ?? 'once'),
        ];
        $comment = [
            'id' => (int) ($row['id'] ?? 0),
            'author_account_id' => (int) ($row['author_account_id'] ?? 0),
            'is_secret' => (int) ($row['is_secret'] ?? 0),
            'status' => $commentStatus,
        ];
        $account = sr_content_reaction_account($viewerAccountId);
        $canView = $status === 'active'
            && sr_content_can_access_body_file($pdo, $page, $account)
            && sr_content_account_can_view_comment_body($comment, $page, $account, $pdo);
        $settings = sr_content_settings($pdo);
        $presetKey = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $row['reaction_comment_preset_key'] ?? '') : '';
        if ($presetKey === '') {
            $presetKey = (string) ($settings['reaction_comment_preset_key'] ?? '');
        }

        return [
            'target_id' => (string) (int) ($row['id'] ?? 0),
            'label' => '콘텐츠 댓글 #' . (string) (int) ($row['id'] ?? 0),
            'public_url' => sr_content_path((string) ($row['slug'] ?? '')) . '#content-comments',
            'admin_url' => '/admin/content/comments?q=' . rawurlencode((string) (int) ($row['id'] ?? 0)),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => (int) ($row['author_account_id'] ?? 0),
            'recipient_account_id' => (int) ($row['author_account_id'] ?? 0),
            'preset_key' => $presetKey,
        ];
    }
}

if (!function_exists('sr_content_reaction_batch_ids')) {
    function sr_content_reaction_batch_ids(array $context): array
    {
        $ids = [];
        foreach (($context['target_ids'] ?? []) as $targetId) {
            $id = (int) $targetId;
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}

return [
    'targets' => [
        [
            'target_module' => 'content',
            'target_type' => 'content',
            'label' => '콘텐츠',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $contentId = (int) ($context['target_id'] ?? 0);
                if ($contentId < 1) {
                    return null;
                }

                sr_content_publish_due_scheduled($pdo);
                $stmt = $pdo->prepare('SELECT * FROM sr_content_items WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $contentId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'label' => '콘텐츠 #' . (string) $contentId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_content_reaction_content_result($pdo, $row, (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $contentIds = sr_content_reaction_batch_ids($context);
                if ($contentIds === []) {
                    return [];
                }

                sr_content_publish_due_scheduled($pdo);
                $placeholders = implode(', ', array_fill(0, count($contentIds), '?'));
                $stmt = $pdo->prepare('SELECT * FROM sr_content_items WHERE id IN (' . $placeholders . ')');
                $stmt->execute($contentIds);
                $rows = [];
                foreach ($stmt->fetchAll() as $row) {
                    $rows[(int) ($row['id'] ?? 0)] = $row;
                }

                $targets = [];
                foreach ($contentIds as $contentId) {
                    $targets[(string) $contentId] = isset($rows[$contentId])
                        ? sr_content_reaction_content_result($pdo, $rows[$contentId], (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $contentId,
                            'label' => '콘텐츠 #' . (string) $contentId,
                            'status' => 'broken',
                            'can_view' => false,
                            'can_write' => false,
                        ];
                }

                return $targets;
            },
        ],
        [
            'target_module' => 'content',
            'target_type' => 'comment',
            'label' => '콘텐츠 댓글',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $commentId = (int) ($context['target_id'] ?? 0);
                if ($commentId < 1) {
                    return null;
                }

                sr_content_publish_due_scheduled($pdo);
                $stmt = $pdo->prepare(
                    'SELECT c.id, c.content_id, c.author_account_id, c.is_secret, c.status AS comment_status,
                            p.slug, p.title AS content_title, p.status AS content_status, p.created_by AS content_owner_account_id,
                            p.asset_access_enabled, p.asset_module, p.asset_access_amount, p.asset_access_amounts_json,
                            p.asset_access_group_policies_json, p.asset_access_policy_set_id, p.asset_charge_policy,
                            p.reaction_comment_preset_key
                     FROM sr_content_comments c
                     LEFT JOIN sr_content_items p ON p.id = c.content_id
                     WHERE c.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $commentId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'label' => '콘텐츠 댓글 #' . (string) $commentId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_content_reaction_comment_result($pdo, $row, (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $commentIds = sr_content_reaction_batch_ids($context);
                if ($commentIds === []) {
                    return [];
                }

                sr_content_publish_due_scheduled($pdo);
                $placeholders = implode(', ', array_fill(0, count($commentIds), '?'));
                $stmt = $pdo->prepare(
                    'SELECT c.id, c.content_id, c.author_account_id, c.is_secret, c.status AS comment_status,
                            p.slug, p.title AS content_title, p.status AS content_status, p.created_by AS content_owner_account_id,
                            p.asset_access_enabled, p.asset_module, p.asset_access_amount, p.asset_access_amounts_json,
                            p.asset_access_group_policies_json, p.asset_access_policy_set_id, p.asset_charge_policy,
                            p.reaction_comment_preset_key
                     FROM sr_content_comments c
                     LEFT JOIN sr_content_items p ON p.id = c.content_id
                     WHERE c.id IN (' . $placeholders . ')'
                );
                $stmt->execute($commentIds);
                $rows = [];
                foreach ($stmt->fetchAll() as $row) {
                    $rows[(int) ($row['id'] ?? 0)] = $row;
                }

                $targets = [];
                foreach ($commentIds as $commentId) {
                    $targets[(string) $commentId] = isset($rows[$commentId])
                        ? sr_content_reaction_comment_result($pdo, $rows[$commentId], (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $commentId,
                            'label' => '콘텐츠 댓글 #' . (string) $commentId,
                            'status' => 'broken',
                            'can_view' => false,
                            'can_write' => false,
                        ];
                }

                return $targets;
            },
        ],
    ],
];
