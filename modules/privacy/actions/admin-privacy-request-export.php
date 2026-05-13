<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/admin/helpers.php';
require_once TOY_ROOT . '/modules/privacy/helpers.php';

if (toy_request_method() !== 'POST') {
    toy_render_error(405, '허용되지 않는 요청입니다.');
}

$account = toy_member_require_login($pdo);
toy_admin_require_role($pdo, (int) $account['id'], ['owner', 'admin']);
toy_require_csrf();

$requestId = toy_admin_post_positive_int('id');
if ($requestId <= 0) {
    toy_render_error(400, '개인정보 처리 요청을 선택하세요.');
}

$privacyRequest = toy_admin_privacy_request($pdo, $requestId);
if ($privacyRequest === null) {
    toy_render_error(404, '개인정보 처리 요청을 찾을 수 없습니다.');
}

foreach (toy_admin_privacy_request_export_reauth_errors($pdo, $account, $requestId) as $reauthError) {
    toy_render_error(403, $reauthError);
}

$export = toy_admin_privacy_request_export_data($pdo, $privacyRequest);
$encodedExport = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($encodedExport)) {
    toy_render_error(500, '개인정보 처리 자료 파일을 생성할 수 없습니다.');
}
toy_admin_log_privacy_request_export($pdo, $account, $requestId);

toy_send_download_headers('application/json; charset=UTF-8', 'toycore-privacy-request-' . $requestId . '.json');
echo $encodedExport;
toy_finish_response();
