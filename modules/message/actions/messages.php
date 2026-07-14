<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/message/helpers.php';

$account = sr_member_require_login($pdo);
$settings = sr_message_settings($pdo);
if (!sr_message_enabled($pdo, $settings)) {
    sr_render_error(403, '쪽지 기능을 사용할 수 없습니다.');
}
$canViewMemberIdentifiers = function_exists('sr_admin_has_permission')
    && sr_admin_has_permission($pdo, (int) $account['id'], '/admin/members', 'view');
$box = sr_get_string('box', 20);
$box = $box === 'sent' ? 'sent' : 'inbox';
$messagePerPage = 20;
$messagePageInput = sr_get_string('page', 20);
$messagePage = preg_match('/\A[1-9][0-9]*\z/', $messagePageInput) === 1 ? (int) $messagePageInput : 1;
$messageCount = sr_message_box_count($pdo, (int) $account['id'], $box);
$messageTotalPages = max(1, (int) ceil($messageCount / $messagePerPage));
$messagePage = min(max(1, $messagePage), $messageTotalPages);
$messagePagination = ['page' => $messagePage, 'total_pages' => $messageTotalPages];
$messagePaginationBasePath = $box === 'sent' ? '/messages?box=sent' : '/messages';
$messages = sr_message_box($pdo, (int) $account['id'], $box, $messagePerPage, ($messagePage - 1) * $messagePerPage);
$notice = '';
if (isset($_SESSION['sr_message_notice']) && is_string($_SESSION['sr_message_notice'])) {
    $notice = $_SESSION['sr_message_notice'];
}
unset($_SESSION['sr_message_notice']);

include SR_ROOT . '/modules/message/views/messages.php';
