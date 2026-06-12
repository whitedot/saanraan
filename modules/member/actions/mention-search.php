<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$account = sr_member_require_login($pdo);

$query = sr_get_string('q', 120);
$limitValue = sr_get_string('limit', 10);
$limit = preg_match('/\A[1-9][0-9]*\z/', $limitValue) === 1 ? (int) $limitValue : 10;
$limit = max(1, min(20, $limit));
$query = trim($query);

if ($query === '' || (function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query)) < 1) {
    sr_json_response(['items' => []]);
}

$accountId = (int) ($account['id'] ?? 0);
$accountSubject = $accountId > 0 ? 'account:' . (string) $accountId : '';
$ipSubject = sr_client_ip();
$windowSeconds = 60;
$useRateLimits = sr_member_rate_limits_table_exists($pdo);
if (
    $useRateLimits
    && (
        ($accountSubject !== '' && sr_rate_limit_count($pdo, 'member.mention_search.account', $accountSubject, $windowSeconds) >= 120)
        || ($ipSubject !== '' && sr_rate_limit_count($pdo, 'member.mention_search.ip', $ipSubject, $windowSeconds) >= 240)
    )
) {
    sr_json_response([
        'items' => [],
        'message' => '검색 요청이 많습니다. 잠시 후 다시 시도하세요.',
    ], 429);
}

if ($useRateLimits && $accountSubject !== '') {
    sr_rate_limit_increment($pdo, 'member.mention_search.account', $accountSubject, $windowSeconds);
}
if ($useRateLimits && $ipSubject !== '') {
    sr_rate_limit_increment($pdo, 'member.mention_search.ip', $ipSubject, $windowSeconds);
}

$runtimeConfig = isset($config) && is_array($config) ? $config : sr_runtime_config();
sr_json_response([
    'items' => sr_member_mention_search_rows($pdo, $runtimeConfig, $query, $limit, [$accountId]),
]);
