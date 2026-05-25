<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$account = sr_member_require_login($pdo);
$communitySettings = sr_community_settings($pdo);
$canViewMemberIdentifiers = sr_community_admin_can_view_member_identifiers($pdo, $account);
$box = sr_get_string('box', 20);
$box = $box === 'sent' ? 'sent' : 'inbox';
$messages = sr_community_message_box($pdo, (int) $account['id'], $box, 50);
$notice = '';
if (isset($_SESSION['sr_community_message_notice']) && is_string($_SESSION['sr_community_message_notice'])) {
    $notice = $_SESSION['sr_community_message_notice'];
}
unset($_SESSION['sr_community_message_notice']);

include SR_ROOT . '/modules/community/views/messages.php';
