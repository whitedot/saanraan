<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'targets' => [
        [
            'target_module' => 'coupon',
            'target_type' => 'claim_campaign',
            'label' => '쿠폰 발급 캠페인',
            'allowed_variants' => ['summary'],
            'default_variant' => 'summary',
            'embed_stylesheet' => '/modules/coupon/assets/embed.css',
            'resolve_url' => static function (PDO $pdo, array $context): ?array {
                $url = (string) ($context['url'] ?? '');
                $path = (string) parse_url($url, PHP_URL_PATH);
                if ($path !== '/coupons') {
                    return null;
                }
                parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
                $campaignKey = sr_coupon_clean_key((string) ($query['campaign'] ?? ''));
                if ($campaignKey === '') {
                    return null;
                }

                $campaign = sr_coupon_public_claim_campaign($pdo, $campaignKey, 0, ['content_embed']);
                if (!is_array($campaign)) {
                    return null;
                }

                $campaignUrl = '/coupons?campaign=' . rawurlencode((string) ($campaign['campaign_key'] ?? ''));
                return [
                    'target_id' => (string) (int) ($campaign['id'] ?? 0),
                    'canonical_url' => $campaignUrl,
                    'label_snapshot' => (string) ($campaign['title'] ?? ''),
                    'summary_snapshot' => (string) ($campaign['description'] ?? ''),
                    'target_state' => 'public',
                    'cache_status' => 'fresh',
                    'target_cache_version' => (string) ($campaign['updated_at'] ?? ''),
                ];
            },
            'render_embed' => static function (PDO $pdo, array $embed, array $context): array {
                $campaign = sr_coupon_claim_campaign_by_id($pdo, (int) ($embed['target_id'] ?? 0));
                if (!is_array($campaign)) {
                    return ['html' => '', 'cache_status' => 'deleted'];
                }
                $accountId = (int) ($context['viewer_account_id'] ?? 0);
                $campaign = sr_coupon_public_claim_campaign($pdo, (string) ($campaign['campaign_key'] ?? ''), $accountId, ['content_embed']);
                if (!is_array($campaign)) {
                    return ['html' => '', 'cache_status' => 'broken', 'target_cache_version' => sr_now()];
                }

                $state = is_array($campaign['claim_state'] ?? null) ? $campaign['claim_state'] : [];
                $remaining = $state['remaining'] ?? null;
                $campaignUrl = '/coupons?campaign=' . rawurlencode((string) ($campaign['campaign_key'] ?? ''));
                $ctaUrl = $accountId > 0
                    ? $campaignUrl
                    : '/login?next=' . rawurlencode($campaignUrl);
                $ctaLabel = $accountId > 0 ? '쿠폰 받기' : '로그인하고 받기';
                if ($accountId > 0 && empty($state['claimable'])) {
                    $ctaLabel = (string) ($state['message'] ?? '받을 수 없음');
                    $ctaUrl = $campaignUrl;
                }

                $html = '<sr-coupon-embed class="coupon-embed-claim card" data-coupon-embed="claim">';
                $html .= '<div class="card-body">';
                $html .= '<strong><a href="' . sr_e($campaignUrl) . '">' . sr_e((string) ($campaign['title'] ?? '')) . '</a></strong>';
                if ((string) ($campaign['description'] ?? '') !== '') {
                    $html .= '<p>' . sr_e((string) ($campaign['description'] ?? '')) . '</p>';
                }
                $html .= '<p><span>쿠폰: ' . sr_e((string) ($campaign['coupon_title'] ?? '')) . '</span>';
                $html .= '<span> 남은 수량: ' . ($remaining === null ? sr_e('제한 없음') : sr_e(number_format((int) $remaining) . '장')) . '</span></p>';
                $html .= '<a class="btn btn-solid-primary" href="' . sr_e($ctaUrl) . '">' . sr_e($ctaLabel) . '</a>';
                $html .= '</div></sr-coupon-embed>';

                return [
                    'html' => $html,
                    'cache_status' => 'fresh',
                    'target_cache_version' => (string) ($campaign['updated_at'] ?? ''),
                ];
            },
        ],
    ],
];
