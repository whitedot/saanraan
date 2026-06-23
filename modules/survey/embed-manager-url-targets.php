<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'survey',
            'target_type' => 'survey_form',
            'label' => '설문·여론조사',
            'allowed_variants' => ['summary'],
            'default_variant' => 'summary',
            'resolve_url' => static function (PDO $pdo, array $context): ?array {
                $path = (string) parse_url((string) ($context['url'] ?? ''), PHP_URL_PATH);
                if (!str_starts_with($path, '/survey/')) {
                    return null;
                }
                $surveyKey = rawurldecode(substr($path, strlen('/survey/')));
                if ($surveyKey === '' || str_contains($surveyKey, '/')) {
                    return null;
                }
                $stmt = $pdo->prepare('SELECT * FROM sr_survey_forms WHERE survey_key = :survey_key LIMIT 1');
                $stmt->execute(['survey_key' => $surveyKey]);
                $row = $stmt->fetch();
                if (!is_array($row)) {
                    return null;
                }
                $public = empty($row['deleted_at'])
                    && (string) ($row['status'] ?? '') === 'active'
                    && (int) ($row['public_listed'] ?? 0) === 1
                    && sr_survey_public_window_is_open($row);
                return [
                    'target_id' => (string) (int) ($row['id'] ?? 0),
                    'canonical_url' => '/survey/' . (string) ($row['survey_key'] ?? ''),
                    'label_snapshot' => (string) ($row['title'] ?? ''),
                    'summary_snapshot' => (string) ($row['description'] ?? ''),
                    'image_snapshot' => sr_survey_clean_cover_image_url((string) ($row['cover_image_url'] ?? '')),
                    'image_snapshot_policy' => (string) ($row['cover_image_url'] ?? '') !== '' ? 'public_url_ok' : 'none',
                    'target_state' => $public ? 'public' : 'private',
                    'cache_status' => $public ? 'fresh' : 'broken',
                    'target_cache_version' => (string) ($row['updated_at'] ?? ''),
                ];
            },
            'render_embed' => static function (PDO $pdo, array $embed, array $context): array {
                if ((string) ($embed['target_state'] ?? '') !== 'public') {
                    return ['html' => ''];
                }
                $image = (string) ($embed['image_snapshot'] ?? '');
                $html = '<aside class="survey-embed-summary" data-survey-embed="summary">';
                if ($image !== '') {
                    $html .= '<a class="survey-embed-summary-image" href="' . sr_e((string) ($embed['canonical_url'] ?? '')) . '"><img src="' . sr_e($image) . '" alt="" loading="lazy" decoding="async" /></a>';
                }
                $html .= '<strong><a href="' . sr_e((string) ($embed['canonical_url'] ?? '')) . '">' . sr_e((string) ($embed['label_snapshot'] ?? '')) . '</a></strong>';
                if ((string) ($embed['summary_snapshot'] ?? '') !== '') {
                    $html .= '<p>' . sr_e((string) $embed['summary_snapshot']) . '</p>';
                }
                return ['html' => $html . '</aside>'];
            },
        ],
    ],
];
