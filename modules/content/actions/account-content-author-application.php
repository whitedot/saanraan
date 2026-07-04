<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
if (sr_module_enabled($pdo, 'identity_verification') && is_file(SR_ROOT . '/modules/identity_verification/helpers.php')) {
    require_once SR_ROOT . '/modules/identity_verification/helpers.php';
}

$account = sr_member_require_login($pdo);
$flash = isset($_SESSION['sr_content_author_application_flash']) && is_array($_SESSION['sr_content_author_application_flash'])
    ? $_SESSION['sr_content_author_application_flash']
    : [];
unset($_SESSION['sr_content_author_application_flash']);
$errors = isset($flash['errors']) && is_array($flash['errors']) ? array_values(array_map('strval', $flash['errors'])) : [];
$notice = (string) ($flash['notice'] ?? '');
$settings = sr_content_settings($pdo);
$contentAuthorIdentityPurpose = 'content.author_application';
$contentAuthorIdentityPolicy = function_exists('sr_identity_verification_requirement_policy')
    ? sr_identity_verification_requirement_policy($pdo, (int) $account['id'], $contentAuthorIdentityPurpose, !empty($settings['identity_author_application_required']) ? 'required' : 'off', '/account/content/author-application')
    : ['required' => !empty($settings['identity_author_application_required']), 'satisfied' => false, 'available' => false, 'start_url' => ''];
$contentAuthorAdultIdentityPurpose = 'content.author_application.adult';
$contentAuthorAdultIdentityPolicy = [
    'required' => !empty($settings['identity_author_application_adult_required']),
    'satisfied' => empty($settings['identity_author_application_adult_required']),
    'available' => false,
    'start_url' => '',
];
if (!empty($settings['identity_author_application_adult_required']) && function_exists('sr_identity_verification_available') && function_exists('sr_identity_verification_account_satisfies_adult')) {
    $contentAuthorAdultIdentityAvailable = sr_identity_verification_available($pdo, $contentAuthorAdultIdentityPurpose);
    $contentAuthorAdultIdentityPolicy = [
        'required' => true,
        'satisfied' => sr_identity_verification_account_satisfies_adult($pdo, (int) $account['id'], $contentAuthorAdultIdentityPurpose),
        'available' => $contentAuthorAdultIdentityAvailable,
        'start_url' => $contentAuthorAdultIdentityAvailable ? sr_identity_verification_start_url($contentAuthorAdultIdentityPurpose, '/account/content/author-application') : '',
    ];
}
$authorPermission = sr_content_author_permission($pdo, (int) $account['id']);
$authorApplication = sr_content_author_application_by_account($pdo, (int) $account['id']);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    $errors = [];
    $notice = '';
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
        if (!empty($contentAuthorIdentityPolicy['required']) && empty($contentAuthorIdentityPolicy['satisfied'])) {
            throw new InvalidArgumentException(!empty($contentAuthorIdentityPolicy['available'])
                ? '콘텐츠 작성자 신청 전 본인확인을 완료해 주세요.'
                : '본인확인 기능이 준비되지 않아 콘텐츠 작성자 신청을 진행할 수 없습니다.');
        }
        if (!empty($contentAuthorAdultIdentityPolicy['required']) && empty($contentAuthorAdultIdentityPolicy['satisfied'])) {
            throw new InvalidArgumentException(!empty($contentAuthorAdultIdentityPolicy['available'])
                ? '콘텐츠 작성자 신청 전 성인 본인확인을 완료해 주세요.'
                : '본인확인 기능이 준비되지 않아 콘텐츠 작성자 신청을 진행할 수 없습니다.');
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
    $_SESSION['sr_content_author_application_flash'] = [
        'errors' => $errors,
        'notice' => $notice,
    ];
    sr_redirect('/account/content/author-application');
}

include SR_ROOT . '/modules/content/views/account-content-author-application.php';
