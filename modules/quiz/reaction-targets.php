<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!function_exists('sr_quiz_reaction_can_view')) {
    function sr_quiz_reaction_can_view(PDO $pdo, array $quiz, int $viewerAccountId): bool
    {
        if ((string) ($quiz['status'] ?? '') !== 'active' || !sr_quiz_public_window_is_open($quiz)) {
            return false;
        }

        $groupKeys = sr_quiz_member_group_keys_from_value($quiz['member_group_keys_json'] ?? '');
        if ($groupKeys === []) {
            return true;
        }
        if ($viewerAccountId < 1 || !function_exists('sr_member_account_in_any_group')) {
            return false;
        }

        return sr_member_account_in_any_group($pdo, $viewerAccountId, $groupKeys);
    }
}

if (!function_exists('sr_quiz_reaction_quiz_result')) {
    function sr_quiz_reaction_quiz_result(PDO $pdo, array $quiz, int $viewerAccountId): array
    {
        $quizId = (int) ($quiz['id'] ?? 0);
        if ((string) ($quiz['status'] ?? '') === 'deleted' || !empty($quiz['deleted_at'])) {
            $status = 'deleted';
        } elseif ((string) ($quiz['status'] ?? '') === 'active' && sr_quiz_public_window_is_open($quiz)) {
            $status = 'active';
        } else {
            $status = 'private';
        }
        $canView = $status === 'active' && sr_quiz_reaction_can_view($pdo, $quiz, $viewerAccountId);

        return [
            'target_id' => (string) $quizId,
            'label' => (string) ($quiz['title'] ?? ''),
            'public_url' => '/quiz/' . rawurlencode((string) ($quiz['quiz_key'] ?? '')),
            'admin_url' => '/admin/quiz?mode=edit&id=' . rawurlencode((string) $quizId),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => (int) ($quiz['created_by_account_id'] ?? 0),
            'recipient_account_id' => (int) ($quiz['created_by_account_id'] ?? 0),
            'preset_key' => 'emotions',
        ];
    }
}

if (!function_exists('sr_quiz_reaction_comment_result')) {
    function sr_quiz_reaction_comment_result(PDO $pdo, array $row, int $viewerAccountId): array
    {
        $quiz = [
            'id' => (int) ($row['quiz_id'] ?? 0),
            'quiz_key' => (string) ($row['quiz_key'] ?? ''),
            'title' => (string) ($row['quiz_title'] ?? ''),
            'status' => (string) ($row['quiz_status'] ?? ''),
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
            'member_group_keys_json' => $row['member_group_keys_json'] ?? '',
            'created_by_account_id' => (int) ($row['quiz_owner_account_id'] ?? 0),
            'deleted_at' => $row['quiz_deleted_at'] ?? null,
        ];
        $quizResult = sr_quiz_reaction_quiz_result($pdo, $quiz, $viewerAccountId);
        $commentStatus = (string) ($row['comment_status'] ?? '');
        if ($commentStatus === 'deleted' || (string) ($quizResult['status'] ?? '') === 'deleted') {
            $status = 'deleted';
        } elseif ($commentStatus === 'published' && (string) ($quizResult['status'] ?? '') === 'active') {
            $status = 'active';
        } elseif ($commentStatus === '') {
            $status = 'broken';
        } else {
            $status = 'private';
        }
        $isSecret = (int) ($row['is_secret'] ?? 0) === 1;
        $ownerAccountId = (int) ($row['author_account_id'] ?? 0);
        $canView = $status === 'active'
            && !empty($quizResult['can_view'])
            && (!$isSecret || ($viewerAccountId > 0 && in_array($viewerAccountId, [$ownerAccountId, (int) ($quizResult['owner_account_id'] ?? 0)], true)));

        return [
            'target_id' => (string) (int) ($row['id'] ?? 0),
            'label' => '퀴즈 댓글 #' . (string) (int) ($row['id'] ?? 0),
            'public_url' => '/quiz/' . rawurlencode((string) ($row['quiz_key'] ?? '')) . '#quiz-comments',
            'admin_url' => '/admin/quiz/comments?q=' . rawurlencode((string) (int) ($row['id'] ?? 0)),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => $ownerAccountId,
            'recipient_account_id' => $ownerAccountId,
            'preset_key' => 'emotions',
        ];
    }
}

if (!function_exists('sr_quiz_reaction_batch_ids')) {
    function sr_quiz_reaction_batch_ids(array $context): array
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
            'target_module' => 'quiz',
            'target_type' => 'quiz_set',
            'label' => '퀴즈',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $quizId = (int) ($context['target_id'] ?? 0);
                if ($quizId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare('SELECT * FROM sr_quiz_sets WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $quizId]);
                $quiz = $stmt->fetch();
                if (!is_array($quiz)) {
                    return [
                        'label' => '퀴즈 #' . (string) $quizId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_quiz_reaction_quiz_result($pdo, $quiz, (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $quizIds = sr_quiz_reaction_batch_ids($context);
                if ($quizIds === []) {
                    return [];
                }

                $placeholders = implode(', ', array_fill(0, count($quizIds), '?'));
                $stmt = $pdo->prepare('SELECT * FROM sr_quiz_sets WHERE id IN (' . $placeholders . ')');
                $stmt->execute($quizIds);
                $rows = [];
                foreach ($stmt->fetchAll() as $quiz) {
                    $rows[(int) ($quiz['id'] ?? 0)] = $quiz;
                }

                $targets = [];
                foreach ($quizIds as $quizId) {
                    $targets[(string) $quizId] = isset($rows[$quizId])
                        ? sr_quiz_reaction_quiz_result($pdo, $rows[$quizId], (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $quizId,
                            'label' => '퀴즈 #' . (string) $quizId,
                            'status' => 'broken',
                            'can_view' => false,
                            'can_write' => false,
                        ];
                }

                return $targets;
            },
        ],
        [
            'target_module' => 'quiz',
            'target_type' => 'comment',
            'label' => '퀴즈 댓글',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $commentId = (int) ($context['target_id'] ?? 0);
                if ($commentId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare(
                    'SELECT c.id, c.quiz_id, c.author_account_id, c.is_secret, c.status AS comment_status,
                            q.quiz_key, q.title AS quiz_title, q.status AS quiz_status, q.starts_at, q.ends_at,
                            q.member_group_keys_json, q.created_by_account_id AS quiz_owner_account_id, q.deleted_at AS quiz_deleted_at
                     FROM sr_quiz_comments c
                     LEFT JOIN sr_quiz_sets q ON q.id = c.quiz_id
                     WHERE c.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $commentId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'label' => '퀴즈 댓글 #' . (string) $commentId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_quiz_reaction_comment_result($pdo, $row, (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $commentIds = sr_quiz_reaction_batch_ids($context);
                if ($commentIds === []) {
                    return [];
                }

                $placeholders = implode(', ', array_fill(0, count($commentIds), '?'));
                $stmt = $pdo->prepare(
                    'SELECT c.id, c.quiz_id, c.author_account_id, c.is_secret, c.status AS comment_status,
                            q.quiz_key, q.title AS quiz_title, q.status AS quiz_status, q.starts_at, q.ends_at,
                            q.member_group_keys_json, q.created_by_account_id AS quiz_owner_account_id, q.deleted_at AS quiz_deleted_at
                     FROM sr_quiz_comments c
                     LEFT JOIN sr_quiz_sets q ON q.id = c.quiz_id
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
                        ? sr_quiz_reaction_comment_result($pdo, $rows[$commentId], (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $commentId,
                            'label' => '퀴즈 댓글 #' . (string) $commentId,
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
