<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/reaction/helpers.php';

sr_require_csrf();

$account = sr_member_current_account($pdo);
if (!is_array($account)) {
    sr_json_response([
        'ok' => false,
        'error' => 'login_required',
    ], 401);
}

$accountId = (int) ($account['id'] ?? 0);
if (sr_reaction_write_rate_limited($pdo, $accountId)) {
    sr_json_response([
        'ok' => false,
        'error' => 'rate_limited',
    ], 429);
}
sr_reaction_record_write_rate_limit($pdo, $accountId);

$result = sr_reaction_write(
    $pdo,
    $accountId,
    sr_post_string('target_module', 60),
    sr_post_string('target_type', 60),
    sr_post_string('target_id', 80),
    sr_post_string('reaction_key', 80),
    sr_post_string('intent', 20)
);

$statusCode = 200;
if (empty($result['ok'])) {
    $error = (string) ($result['error'] ?? '');
    if ($error === 'login_required') {
        $statusCode = 401;
    } elseif (in_array($error, ['self_reaction_not_allowed', 'target_not_writable'], true)) {
        $statusCode = 403;
    } elseif (in_array($error, ['not_available', 'target_contract_missing', 'write_failed'], true)) {
        $statusCode = 503;
    } else {
        $statusCode = 400;
    }
}

sr_json_response($result, $statusCode);
