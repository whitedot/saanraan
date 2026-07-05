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
$messages = sr_message_box($pdo, (int) $account['id'], $box, 50);
$notice = '';
if (isset($_SESSION['sr_message_notice']) && is_string($_SESSION['sr_message_notice'])) {
    $notice = $_SESSION['sr_message_notice'];
}
unset($_SESSION['sr_message_notice']);

include SR_ROOT . '/modules/message/views/messages.php';
