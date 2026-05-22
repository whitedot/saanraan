<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/privacy/helpers.php';

if (sr_request_method() !== 'POST') {
    sr_render_error(405, sr_t('privacy::action.error.method_not_allowed'));
}

sr_require_csrf();

$account = sr_member_require_login($pdo);

foreach (sr_member_privacy_export_reauth_errors($pdo, $account) as $reauthError) {
    sr_render_error(403, $reauthError);
}

$export = sr_privacy_export_data($pdo, (int) $account['id']);
$encodedExport = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($encodedExport)) {
    sr_render_error(500, sr_t('privacy::action.error.account_export_failed'));
}

sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'privacy.export.downloaded',
    'target_type' => 'member_account',
    'target_id' => (string) $account['id'],
    'result' => 'success',
    'message' => 'Member privacy export downloaded.',
]);

sr_send_download_headers('application/json; charset=UTF-8', 'saanraan-privacy-export-' . (int) $account['id'] . '.json');
echo $encodedExport;
sr_finish_response();
