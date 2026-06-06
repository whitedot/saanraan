<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';

$account = sr_member_require_login($pdo);
$errors = [];
$notice = '';
$settings = sr_content_settings($pdo);
$authorPermission = sr_content_author_permission($pdo, (int) $account['id']);
$authorApplication = sr_content_author_application_by_account($pdo, (int) $account['id']);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    try {
        if (empty($settings['member_submission_enabled'])) {
            throw new InvalidArgumentException('현재 콘텐츠 등록자 신청을 받지 않습니다.');
        }
        if (is_array($authorPermission) && (string) ($authorPermission['status'] ?? '') === 'allowed') {
            throw new InvalidArgumentException('이미 콘텐츠 등록자로 승인되어 있습니다.');
        }
        if (is_array($authorPermission) && (string) ($authorPermission['status'] ?? '') === 'blocked') {
            throw new InvalidArgumentException('콘텐츠 등록자 신청이 제한되어 있습니다.');
        }

        $shouldNotifyAdmins = !is_array($authorApplication) || (string) ($authorApplication['status'] ?? '') !== 'pending';
        $applicationId = sr_content_save_author_application($pdo, (int) $account['id'], sr_post_string('application_note', 2000));
        if ($shouldNotifyAdmins) {
            sr_content_create_admin_author_application_notifications($pdo, $applicationId, (int) $account['id']);
        }
        $notice = '콘텐츠 등록자 신청을 접수했습니다.';
        $authorApplication = sr_content_author_application_by_account($pdo, (int) $account['id']);
    } catch (Throwable $exception) {
        $errors[] = $exception->getMessage();
    }
}

include SR_ROOT . '/modules/content/views/account-content-author-application.php';
