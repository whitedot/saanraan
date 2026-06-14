<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'survey',
            'target_type' => 'survey_form',
            'label' => '설문',
            'allowed_variants' => ['card', 'button', 'compact'],
            'default_variant' => 'card',
            'search' => static function (PDO $pdo, array $context): array {
                $keyword = trim(preg_replace('/\s+/', ' ', (string) ($context['keyword'] ?? '')) ?? '');
                $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
                $limit = max(1, min(20, (int) ($context['limit'] ?? 10)));
                $mode = (string) ($context['context'] ?? 'public');
                $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_like ESCAPE '\\\\' OR survey_key LIKE :keyword_like ESCAPE '\\\\')";
                $params = [];
                if ($keyword !== '') {
                    $params = [
                        'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
                        'keyword_like' => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%',
                    ];
                }
                $visibilitySql = $mode === 'admin'
                    ? 'deleted_at IS NULL'
                    : 'status = \'active\' AND deleted_at IS NULL AND public_listed = 1 AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) AND (member_group_keys_json IS NULL OR member_group_keys_json = \'\' OR member_group_keys_json = \'[]\')';
                $stmt = $pdo->prepare(
                    'SELECT id, survey_key, title, description, cover_image_url, status, starts_at, ends_at, public_listed, reward_enabled, member_group_keys_json, deleted_at
                     FROM sr_survey_forms
                     WHERE ' . $visibilitySql . '
                       AND ' . $where . '
                     ORDER BY updated_at DESC, id DESC
                     LIMIT ' . $limit
                );
                $stmt->execute($params);

                return array_map(static function (array $row): array {
                    $surveyId = (string) (int) ($row['id'] ?? 0);
                    $status = 'private';
                    if (!empty($row['deleted_at'])) {
                        $status = 'deleted';
                    } elseif ((string) ($row['status'] ?? '') === 'active' && sr_survey_public_window_is_open($row)) {
                        $status = 'active';
                    }
                    return [
                        'target_id' => $surveyId,
                        'label_snapshot' => (string) ($row['title'] ?? ''),
                        'summary' => (string) ($row['description'] ?? ''),
                        'image_snapshot' => sr_survey_clean_cover_image_url((string) ($row['cover_image_url'] ?? '')),
                        'public_url' => '/survey/' . (string) ($row['survey_key'] ?? ''),
                        'admin_url' => '/admin/surveys?mode=edit&id=' . rawurlencode($surveyId),
                        'status' => $status,
                        'meta' => '설문 #' . $surveyId . ' / key: ' . (string) ($row['survey_key'] ?? '') . ((int) ($row['reward_enabled'] ?? 0) === 1 ? ' / 보상 있음' : ''),
                    ];
                }, $stmt->fetchAll());
            },
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $surveyId = (int) ($context['target_id'] ?? 0);
                if ($surveyId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $surveyId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return ['label_snapshot' => '설문 #' . (string) $surveyId, 'status' => 'broken'];
                }

                $status = 'private';
                if (!empty($row['deleted_at'])) {
                    $status = 'deleted';
                } elseif ((string) ($row['status'] ?? '') === 'active' && sr_survey_public_window_is_open($row)) {
                    $status = 'active';
                }
                if ($status === 'active' && (string) ($context['mode'] ?? '') === 'public') {
                    $requiredGroupKeys = sr_survey_member_group_keys_from_json($row['member_group_keys_json'] ?? '[]');
                    $viewerAccountId = (int) ($context['viewer_account_id'] ?? 0);
                    if ($requiredGroupKeys !== [] && ($viewerAccountId < 1 || !sr_member_account_in_any_group($pdo, $viewerAccountId, $requiredGroupKeys))) {
                        $status = 'private';
                    }
                }

                return [
                    'label_snapshot' => (string) ($row['title'] ?? ''),
                    'summary' => (string) ($row['description'] ?? ''),
                    'image_snapshot' => sr_survey_clean_cover_image_url((string) ($row['cover_image_url'] ?? '')),
                    'public_url' => '/survey/' . (string) ($row['survey_key'] ?? ''),
                    'admin_url' => '/admin/surveys?mode=edit&id=' . rawurlencode((string) $surveyId),
                    'status' => $status,
                ];
            },
        ],
    ],
];
