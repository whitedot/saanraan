<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/member/helpers.php';
require_once TOY_ROOT . '/modules/community/helpers.php';

$account = toy_member_require_login($pdo);
$box = toy_get_string('box', 20);
$box = $box === 'sent' ? 'sent' : 'inbox';
$messages = toy_community_message_box($pdo, (int) $account['id'], $box, 50);
$notice = '';
if (isset($_SESSION['toy_community_message_notice']) && is_string($_SESSION['toy_community_message_notice'])) {
    $notice = $_SESSION['toy_community_message_notice'];
}
unset($_SESSION['toy_community_message_notice']);

include TOY_ROOT . '/modules/community/views/messages.php';
