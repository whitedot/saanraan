<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!function_exists('sr_survey_reaction_can_view')) {
    function sr_survey_reaction_can_view(PDO $pdo, array $survey, int $viewerAccountId): bool
    {
        if ((string) ($survey['status'] ?? '') !== 'active' || !sr_survey_public_window_is_open($survey)) {
            return false;
        }
        if ((int) ($survey['login_required'] ?? 1) === 1 && $viewerAccountId < 1) {
            return false;
        }

        $groupKeys = sr_survey_member_group_keys_from_json($survey['member_group_keys_json'] ?? '[]');
        if ($groupKeys === []) {
            return true;
        }
        if ($viewerAccountId < 1 || !function_exists('sr_member_account_in_any_group')) {
            return false;
        }

        return sr_member_account_in_any_group($pdo, $viewerAccountId, $groupKeys);
    }
}

if (!function_exists('sr_survey_reaction_survey_result')) {
    function sr_survey_reaction_survey_result(PDO $pdo, array $survey, int $viewerAccountId): array
    {
        $surveyId = (int) ($survey['id'] ?? 0);
        if ((string) ($survey['status'] ?? '') === 'deleted' || !empty($survey['deleted_at'])) {
            $status = 'deleted';
        } elseif ((string) ($survey['status'] ?? '') === 'active' && sr_survey_public_window_is_open($survey)) {
            $status = 'active';
        } else {
            $status = 'private';
        }
        $canView = $status === 'active' && sr_survey_reaction_can_view($pdo, $survey, $viewerAccountId);
        $settings = sr_survey_settings($pdo);
        $presetKey = (string) ($survey['reaction_preset_key'] ?? '');
        if ($presetKey === '') {
            $presetKey = (string) ($settings['reaction_preset_key'] ?? '');
        }

        return [
            'target_id' => (string) $surveyId,
            'label' => (string) ($survey['title'] ?? ''),
            'public_url' => '/survey/' . rawurlencode((string) ($survey['survey_key'] ?? '')),
            'admin_url' => '/admin/surveys?mode=edit&id=' . rawurlencode((string) $surveyId),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => (int) ($survey['created_by_account_id'] ?? 0),
            'recipient_account_id' => (int) ($survey['created_by_account_id'] ?? 0),
            'preset_key' => $presetKey,
        ];
    }
}

if (!function_exists('sr_survey_reaction_comment_result')) {
    function sr_survey_reaction_comment_result(PDO $pdo, array $row, int $viewerAccountId): array
    {
        $survey = [
            'id' => (int) ($row['survey_id'] ?? 0),
            'survey_key' => (string) ($row['survey_key'] ?? ''),
            'title' => (string) ($row['survey_title'] ?? ''),
            'status' => (string) ($row['survey_status'] ?? ''),
            'starts_at' => $row['starts_at'] ?? null,
            'ends_at' => $row['ends_at'] ?? null,
            'login_required' => (int) ($row['login_required'] ?? 1),
            'member_group_keys_json' => $row['member_group_keys_json'] ?? '',
            'created_by_account_id' => (int) ($row['survey_owner_account_id'] ?? 0),
            'deleted_at' => $row['survey_deleted_at'] ?? null,
            'reaction_preset_key' => $row['reaction_preset_key'] ?? '',
        ];
        $surveyResult = sr_survey_reaction_survey_result($pdo, $survey, $viewerAccountId);
        $commentStatus = (string) ($row['comment_status'] ?? '');
        if ($commentStatus === 'deleted' || (string) ($surveyResult['status'] ?? '') === 'deleted') {
            $status = 'deleted';
        } elseif ($commentStatus === 'published' && (string) ($surveyResult['status'] ?? '') === 'active') {
            $status = 'active';
        } elseif ($commentStatus === '') {
            $status = 'broken';
        } else {
            $status = 'private';
        }
        $isSecret = (int) ($row['is_secret'] ?? 0) === 1;
        $ownerAccountId = (int) ($row['author_account_id'] ?? 0);
        $canView = $status === 'active'
            && !empty($surveyResult['can_view'])
            && (!$isSecret || ($viewerAccountId > 0 && in_array($viewerAccountId, [$ownerAccountId, (int) ($surveyResult['owner_account_id'] ?? 0)], true)));
        $settings = sr_survey_settings($pdo);
        $presetKey = (string) ($row['reaction_comment_preset_key'] ?? '');
        if ($presetKey === '') {
            $presetKey = (string) ($settings['reaction_comment_preset_key'] ?? '');
        }

        return [
            'target_id' => (string) (int) ($row['id'] ?? 0),
            'label' => '설문 댓글 #' . (string) (int) ($row['id'] ?? 0),
            'public_url' => '/survey/' . rawurlencode((string) ($row['survey_key'] ?? '')) . '#survey-comments',
            'admin_url' => '/admin/surveys/comments?q=' . rawurlencode((string) (int) ($row['id'] ?? 0)),
            'status' => $status,
            'can_view' => $canView,
            'can_write' => $canView,
            'owner_account_id' => $ownerAccountId,
            'recipient_account_id' => $ownerAccountId,
            'preset_key' => $presetKey,
        ];
    }
}

if (!function_exists('sr_survey_reaction_batch_ids')) {
    function sr_survey_reaction_batch_ids(array $context): array
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
            'target_module' => 'survey',
            'target_type' => 'survey_form',
            'label' => '설문',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $surveyId = (int) ($context['target_id'] ?? 0);
                if ($surveyId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $surveyId]);
                $survey = $stmt->fetch();
                if (!is_array($survey)) {
                    return [
                        'label' => '설문 #' . (string) $surveyId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_survey_reaction_survey_result($pdo, $survey, (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $surveyIds = sr_survey_reaction_batch_ids($context);
                if ($surveyIds === []) {
                    return [];
                }

                $placeholders = implode(', ', array_fill(0, count($surveyIds), '?'));
                $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id IN (' . $placeholders . ')');
                $stmt->execute($surveyIds);
                $rows = [];
                foreach ($stmt->fetchAll() as $survey) {
                    $rows[(int) ($survey['id'] ?? 0)] = $survey;
                }

                $targets = [];
                foreach ($surveyIds as $surveyId) {
                    $targets[(string) $surveyId] = isset($rows[$surveyId])
                        ? sr_survey_reaction_survey_result($pdo, $rows[$surveyId], (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $surveyId,
                            'label' => '설문 #' . (string) $surveyId,
                            'status' => 'broken',
                            'can_view' => false,
                            'can_write' => false,
                        ];
                }

                return $targets;
            },
        ],
        [
            'target_module' => 'survey',
            'target_type' => 'comment',
            'label' => '설문 댓글',
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $commentId = (int) ($context['target_id'] ?? 0);
                if ($commentId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare(
                    'SELECT c.id, c.survey_id, c.author_account_id, c.is_secret, c.status AS comment_status,
                            s.survey_key, s.title AS survey_title, s.status AS survey_status, s.starts_at, s.ends_at,
                            s.login_required, s.member_group_keys_json, s.reaction_preset_key, s.reaction_comment_preset_key,
                            s.created_by_account_id AS survey_owner_account_id,
                            s.deleted_at AS survey_deleted_at
                     FROM sr_survey_comments c
                     LEFT JOIN sr_survey_forms s ON s.id = c.survey_id
                     WHERE c.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $commentId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return [
                        'label' => '설문 댓글 #' . (string) $commentId,
                        'status' => 'broken',
                        'can_view' => false,
                        'can_write' => false,
                    ];
                }

                return sr_survey_reaction_comment_result($pdo, $row, (int) ($context['viewer_account_id'] ?? 0));
            },
            'batch_resolve' => static function (PDO $pdo, array $context): array {
                $commentIds = sr_survey_reaction_batch_ids($context);
                if ($commentIds === []) {
                    return [];
                }

                $placeholders = implode(', ', array_fill(0, count($commentIds), '?'));
                $stmt = $pdo->prepare(
                    'SELECT c.id, c.survey_id, c.author_account_id, c.is_secret, c.status AS comment_status,
                            s.survey_key, s.title AS survey_title, s.status AS survey_status, s.starts_at, s.ends_at,
                            s.login_required, s.member_group_keys_json, s.reaction_preset_key, s.reaction_comment_preset_key,
                            s.created_by_account_id AS survey_owner_account_id,
                            s.deleted_at AS survey_deleted_at
                     FROM sr_survey_comments c
                     LEFT JOIN sr_survey_forms s ON s.id = c.survey_id
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
                        ? sr_survey_reaction_comment_result($pdo, $rows[$commentId], (int) ($context['viewer_account_id'] ?? 0))
                        : [
                            'target_id' => (string) $commentId,
                            'label' => '설문 댓글 #' . (string) $commentId,
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
