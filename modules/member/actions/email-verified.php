<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$memberSettings = sr_member_settings($pdo);
$memberSkinView = sr_member_skin_view(sr_member_skin_key($memberSettings), 'email-verified');
include $memberSkinView;
