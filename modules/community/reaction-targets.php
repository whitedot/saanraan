<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/helpers/levels.php';
require_once __DIR__ . '/helpers/boards.php';
require_once __DIR__ . '/helpers/posts.php';
require_once __DIR__ . '/helpers/assets.php';

if (!function_exists('sr_community_reaction_account')) {
    function sr_community_reaction_account(int $accountId): ?array
    {
        return $accountId > 0 ? ['id' => $accountId] : null;
    }
}

if (!function_exists('sr_community_reaction_status')) {
    function sr_community_reaction_status(string $postStatus, ?array $board): string
    {
        if ($postStatus === 'deleted' || !is_array($board)) {
            return $postStatus === 'deleted' ? 'deleted' : 'broken';
        }
        if ((string) ($board['status'] ?? '') !== 'enabled') {
            return 'private';
        }
        if ($postStatus === 'published') {
            return 'active';
        }
        if (in_array($postStatus, ['hidden', 'blocked'], true)) {
            return 'private';
        }

        return $postStatus === '' ? 'broken' : 'private';
    }
}

if (!function_exists('sr_community_reaction_can_view_post')) {
    function sr_community_reaction_can_view_post(PDO $pdo, array $post, array $board, int $viewerAccountId): bool
    {
        $account = sr_community_reaction_account($viewerAccountId);
        if (!sr_community_account_can_read_board($pdo, $board, $account) || !sr_community_account_can_view_post_body($pdo, $post, $account)) {
            return false;
        }

        $settings = sr_community_settings($pdo);
        $paidReadConfig = sr_community_asset_event_config($pdo, $board, $settings, 'paid_read', 'once');
        $isAuthor = $viewerAccountId > 0 && $viewerAccountId === (int) ($post['author_account_id'] ?? 0);
        if ($isAuthor || !sr_community_asset_event_required($paidReadConfig)) {
            return true;
        }
        if ($viewerAccountId < 1) {
            return false;
        }

        return sr_community_has_paid_read_session($viewerAccountId, (int) ($post['id'] ?? 0))
            || sr_community_once_access_already_granted($pdo, $paidReadConfig, $viewerAccountId, 'post_read', (int) ($post['id'] ?? 0));
    }
}

if (!function_exists('sr_community_reaction_post_result')) {
    function sr_community_reaction_post_result(PDO $pdo, array $post, ?array $board, int $viewerAccountId): array
    {
        $postId = (int) ($post['id'] ?? 0);
        $status = sr_community_reaction_status((string) ($post['status'] ?? ''), $board);
        $canView = $status === 'active' && is_array($board) && sr_community_reaction_can_view_post($pdo, $post, $board, $viewerAccountId);
        $settings = sr_community_settings($pdo);
        $reactionEnabled = sr_community_effective_board_reaction_enabled($pdo, $board, $settings);
        if (!$reactionEnabled) {
            $canView = false;
        }
        $presetKey = '';
        if ($reactionEnabled) {
            $presetKey = is_array($board)
                ? sr_community_effective_board_setting($pdo, $board, 'reaction_post_preset_key', (string) ($settings['reaction_post_preset_key'] ?? ''))
                : (string) ($settings['reaction_post_preset_key'] ?? '');
        }

        return [
            'target_id' => (string) $postId,
            'label' => (string) ($post['title'] ?? ''),
            'public_url' => '/community/post?id=' . rawurlencode((string) $postId),
            'admin_url' => '/admin/community/posts?q=' . rawurlencode((string) $postId),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => (int) ($post['author_account_id'] ?? 0),
            'recipient_account_id' => (int) ($post['author_account_id'] ?? 0),
            'preset_key' => $presetKey,
        ];
    }
}

if (!function_exists('sr_community_reaction_comment_result')) {
    function sr_community_reaction_comment_result(PDO $pdo, array $row, ?array $board, int $viewerAccountId): array
    {
        $post = [
            'id' => (int) ($row['post_id'] ?? 0),
            'author_account_id' => (int) ($row['post_author_account_id'] ?? 0),
            'is_secret' => (int) ($row['post_is_secret'] ?? 0),
            'status' => (string) ($row['post_status'] ?? ''),
        ];
        $postStatus = sr_community_reaction_status((string) ($row['post_status'] ?? ''), $board);
        $commentStatus = (string) ($row['comment_status'] ?? '');
        if ($commentStatus === 'deleted' || $postStatus === 'deleted') {
            $status = 'deleted';
        } elseif ($commentStatus === 'published' && $postStatus === 'active') {
            $status = 'active';
        } elseif ($commentStatus === '') {
            $status = 'broken';
        } else {
            $status = 'private';
        }

        $comment = [
            'id' => (int) ($row['id'] ?? 0),
            'author_account_id' => (int) ($row['author_account_id'] ?? 0),
            'is_secret' => (int) ($row['comment_is_secret'] ?? 0),
            'status' => $commentStatus,
        ];
        $account = sr_community_reaction_account($viewerAccountId);
        $canView = $status === 'active'
            && is_array($board)
            && sr_community_reaction_can_view_post($pdo, $post, $board, $viewerAccountId)
            && sr_community_account_can_view_comment_body($comment, $post, $account, $pdo);
        $settings = sr_community_settings($pdo);
        $reactionEnabled = sr_community_effective_board_reaction_enabled($pdo, $board, $settings);
        if (!$reactionEnabled) {
            $canView = false;
        }
        $presetKey = '';
        if ($reactionEnabled) {
            $presetKey = is_array($board)
                ? sr_community_effective_board_setting($pdo, $board, 'reaction_comment_preset_key', (string) ($settings['reaction_comment_preset_key'] ?? ''))
                : (string) ($settings['reaction_comment_preset_key'] ?? '');
        }

        return [
            'target_id' => (string) (int) ($row['id'] ?? 0),
            'label' => '커뮤니티 댓글 #' . (string) (int) ($row['id'] ?? 0),
            'public_url' => '/community/post?id=' . rawurlencode((string) (int) ($row['post_id'] ?? 0)) . '#community-comments',
            'admin_url' => '/admin/community/comments?q=' . rawurlencode((string) (int) ($row['id'] ?? 0)),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => (int) ($row['author_account_id'] ?? 0),
            'recipient_account_id' => (int) ($row['author_account_id'] ?? 0),
            'preset_key' => $presetKey,
        ];
    }
}

if (!function_exists('sr_community_reaction_batch_ids')) {
    function sr_community_reaction_batch_ids(array $context): array
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

if (!function_exists('sr_community_reaction_boards_by_ids')) {
    function sr_community_reaction_boards_by_ids(PDO $pdo, array $boardIds): array
    {
        $boards = [];
        foreach (array_values(array_unique(array_filter(array_map('intval', $boardIds)))) as $boardId) {
            if ($boardId > 0) {
                $board = sr_community_board_by_id($pdo, $boardId);
                if (is_array($board)) {
                    $boards[$boardId] = $board;
                }
            }
        }

        return $boards;
    }
}

return [
    'targets' => [
        [
            'target_module' => 'community',
            'target_type' => 'post',
            'label' => '커뮤니티 게시글',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $postId = (int) ($context['target_id'] ?? 0);
                if ($postId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare('SELECT * FROM sr_community_posts WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $postId]);
                $post = $stmt->fetch();
                if (!is_array($post)) {
                    return [
                        'label' => '게시글 #' . (string) $postId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_community_reaction_post_result($pdo, $post, sr_community_board_by_id($pdo, (int) ($post['board_id'] ?? 0)), (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $postIds = sr_community_reaction_batch_ids($context);
                if ($postIds === []) {
                    return [];
                }

                $placeholders = implode(', ', array_fill(0, count($postIds), '?'));
                $stmt = $pdo->prepare('SELECT * FROM sr_community_posts WHERE id IN (' . $placeholders . ')');
                $stmt->execute($postIds);
                $posts = [];
                $boardIds = [];
                foreach ($stmt->fetchAll() as $post) {
                    $postId = (int) ($post['id'] ?? 0);
                    $posts[$postId] = $post;
                    $boardIds[] = (int) ($post['board_id'] ?? 0);
                }
                $boards = sr_community_reaction_boards_by_ids($pdo, $boardIds);

                $targets = [];
                foreach ($postIds as $postId) {
                    $boardId = isset($posts[$postId]) ? (int) ($posts[$postId]['board_id'] ?? 0) : 0;
                    $targets[(string) $postId] = isset($posts[$postId])
                        ? sr_community_reaction_post_result($pdo, $posts[$postId], $boards[$boardId] ?? null, (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $postId,
                            'label' => '게시글 #' . (string) $postId,
                            'status' => 'broken',
                            'can_view' => false,
                            'can_write' => false,
                        ];
                }

                return $targets;
            },
        ],
        [
            'target_module' => 'community',
            'target_type' => 'comment',
            'label' => '커뮤니티 댓글',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $commentId = (int) ($context['target_id'] ?? 0);
                if ($commentId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare(
                    'SELECT c.id, c.post_id, c.author_account_id, c.is_secret AS comment_is_secret, c.status AS comment_status,
                            p.board_id, p.author_account_id AS post_author_account_id, p.is_secret AS post_is_secret, p.status AS post_status
                     FROM sr_community_comments c
                     LEFT JOIN sr_community_posts p ON p.id = c.post_id
                     WHERE c.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $commentId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'label' => '커뮤니티 댓글 #' . (string) $commentId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_community_reaction_comment_result($pdo, $row, sr_community_board_by_id($pdo, (int) ($row['board_id'] ?? 0)), (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $commentIds = sr_community_reaction_batch_ids($context);
                if ($commentIds === []) {
                    return [];
                }

                $placeholders = implode(', ', array_fill(0, count($commentIds), '?'));
                $stmt = $pdo->prepare(
                    'SELECT c.id, c.post_id, c.author_account_id, c.is_secret AS comment_is_secret, c.status AS comment_status,
                            p.board_id, p.author_account_id AS post_author_account_id, p.is_secret AS post_is_secret, p.status AS post_status
                     FROM sr_community_comments c
                     LEFT JOIN sr_community_posts p ON p.id = c.post_id
                     WHERE c.id IN (' . $placeholders . ')'
                );
                $stmt->execute($commentIds);
                $rows = [];
                $boardIds = [];
                foreach ($stmt->fetchAll() as $row) {
                    $commentId = (int) ($row['id'] ?? 0);
                    $rows[$commentId] = $row;
                    $boardIds[] = (int) ($row['board_id'] ?? 0);
                }
                $boards = sr_community_reaction_boards_by_ids($pdo, $boardIds);

                $targets = [];
                foreach ($commentIds as $commentId) {
                    $boardId = isset($rows[$commentId]) ? (int) ($rows[$commentId]['board_id'] ?? 0) : 0;
                    $targets[(string) $commentId] = isset($rows[$commentId])
                        ? sr_community_reaction_comment_result($pdo, $rows[$commentId], $boards[$boardId] ?? null, (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $commentId,
                            'label' => '커뮤니티 댓글 #' . (string) $commentId,
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
