<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/privacy/helpers.php';

if (sr_request_method() !== 'POST') {
    sr_render_error(405, sr_t('privacy::action.error.method_not_allowed'));
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/privacy-requests', 'view');
sr_require_csrf();

$requestId = sr_admin_post_positive_int('id');
if ($requestId <= 0) {
    sr_render_error(400, sr_t('privacy::action.error.request_required'));
}

$privacyRequest = sr_admin_privacy_request($pdo, $requestId);
if ($privacyRequest === null) {
    sr_render_error(404, sr_t('privacy::action.error.request_not_found'));
}

foreach (sr_admin_privacy_request_export_reauth_errors($pdo, $account, $requestId) as $reauthError) {
    sr_render_error(403, $reauthError);
}

$export = sr_admin_privacy_request_export_data($pdo, $privacyRequest);
$encodedExport = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($encodedExport)) {
    sr_render_error(500, sr_t('privacy::action.error.request_export_failed'));
}
sr_admin_log_privacy_request_export($pdo, $account, $requestId);

sr_send_download_headers('application/json; charset=UTF-8', 'saanraan-privacy-request-' . $requestId . '.json');
echo $encodedExport;
sr_finish_response();
