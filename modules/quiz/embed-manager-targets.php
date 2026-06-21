<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'quiz',
            'target_type' => 'quiz_set',
            'label' => '퀴즈·테스트',
            'allowed_variants' => ['card', 'button', 'compact'],
            'default_variant' => 'card',
            'search' => static function (PDO $pdo, array $context): array {
                $keyword = trim(preg_replace('/\s+/', ' ', (string) ($context['keyword'] ?? '')) ?? '');
                $keyword = function_exists('mb_substr') ? mb_substr($keyword, 0, 120) : substr($keyword, 0, 120);
                $limit = max(1, min(20, (int) ($context['limit'] ?? 10)));
                $mode = (string) ($context['context'] ?? 'public');
                $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_like ESCAPE '\\\\' OR quiz_key LIKE :keyword_like ESCAPE '\\\\')";
                $params = [];
                if ($keyword !== '') {
                    $params = [
                        'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
                        'keyword_like' => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%',
                    ];
                }
                $visibilitySql = $mode === 'admin'
                    ? 'deleted_at IS NULL'
                    : 'status = \'active\' AND deleted_at IS NULL AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW()) AND (member_group_keys_json IS NULL OR member_group_keys_json = \'\' OR member_group_keys_json = \'[]\')';
                $stmt = $pdo->prepare(
                    'SELECT id, quiz_key, title, description, cover_image_url, status, starts_at, ends_at, reward_enabled, member_group_keys_json, deleted_at
                     FROM sr_quiz_sets
                     WHERE ' . $visibilitySql . '
                       AND ' . $where . '
                     ORDER BY updated_at DESC, id DESC
                     LIMIT ' . $limit
                );
                $stmt->execute($params);

                return array_map(static function (array $row): array {
                    $quizId = (string) (int) ($row['id'] ?? 0);
                    $status = 'private';
                    if (!empty($row['deleted_at'])) {
                        $status = 'deleted';
                    } elseif ((string) ($row['status'] ?? '') === 'active' && sr_quiz_public_window_is_open($row)) {
                        $status = 'active';
                    }
                    return [
                        'target_id' => $quizId,
                        'label_snapshot' => (string) ($row['title'] ?? ''),
                        'summary' => (string) ($row['description'] ?? ''),
                        'image_snapshot' => sr_quiz_clean_cover_image_url((string) ($row['cover_image_url'] ?? '')),
                        'public_url' => '/quiz/' . (string) ($row['quiz_key'] ?? ''),
                        'admin_url' => '/admin/quiz?mode=edit&id=' . rawurlencode($quizId),
                        'status' => $status,
                        'meta' => '퀴즈 #' . $quizId . ' / key: ' . (string) ($row['quiz_key'] ?? '') . ((int) ($row['reward_enabled'] ?? 0) === 1 ? ' / 보상 있음' : ''),
                    ];
                }, $stmt->fetchAll());
            },
            'resolve' => static function (PDO $pdo, array $context): ?array {
                $quizId = (int) ($context['target_id'] ?? 0);
                if ($quizId < 1) {
                    return null;
                }

                $stmt = $pdo->prepare('SELECT * FROM sr_quiz_sets WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $quizId]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return ['label_snapshot' => '퀴즈 #' . (string) $quizId, 'status' => 'broken'];
                }

                $status = 'private';
                if (!empty($row['deleted_at'])) {
                    $status = 'deleted';
                } elseif ((string) ($row['status'] ?? '') === 'active' && sr_quiz_public_window_is_open($row)) {
                    $status = 'active';
                }
                if ($status === 'active' && (string) ($context['mode'] ?? '') === 'public') {
                    $requiredGroupKeys = sr_quiz_member_group_keys_from_value($row['member_group_keys_json'] ?? '');
                    $viewerAccountId = (int) ($context['viewer_account_id'] ?? 0);
                    if ($requiredGroupKeys !== [] && ($viewerAccountId < 1 || !sr_member_account_in_any_group($pdo, $viewerAccountId, $requiredGroupKeys))) {
                        $status = 'private';
                    }
                }

                return [
                    'label_snapshot' => (string) ($row['title'] ?? ''),
                    'summary' => (string) ($row['description'] ?? ''),
                    'image_snapshot' => sr_quiz_clean_cover_image_url((string) ($row['cover_image_url'] ?? '')),
                    'public_url' => '/quiz/' . (string) ($row['quiz_key'] ?? ''),
                    'admin_url' => '/admin/quiz?mode=edit&id=' . rawurlencode((string) $quizId),
                    'status' => $status,
                ];
            },
        ],
    ],
];
