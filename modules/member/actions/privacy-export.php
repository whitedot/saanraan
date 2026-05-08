<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';

if (toy_request_method() !== 'POST') {
    toy_render_error(405, '허용되지 않는 요청입니다.');
}

toy_require_csrf();

$account = toy_member_require_login($pdo);

foreach (toy_member_privacy_export_reauth_errors($pdo, $account) as $reauthError) {
    toy_render_error(403, $reauthError);
}

$export = toy_member_privacy_export_data($pdo, (int) $account['id']);
$encodedExport = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
if (!is_string($encodedExport)) {
    toy_render_error(500, '개인정보 내보내기 파일을 생성할 수 없습니다.');
}

toy_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'member',
    'event_type' => 'privacy.export.downloaded',
    'target_type' => 'member_account',
    'target_id' => (string) $account['id'],
    'result' => 'success',
    'message' => 'Member privacy export downloaded.',
]);

toy_send_download_headers('application/json; charset=UTF-8', 'toycore-privacy-export-' . (int) $account['id'] . '.json');
echo $encodedExport;
toy_finish_response();
