<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/banner/helpers.php';

$bannerId = (int) sr_get_string('id', 20);
$target = sr_banner_click_target($pdo, $bannerId);
if ($target === null) {
    sr_render_error(404, '배너 링크를 찾을 수 없습니다.');
}

sr_banner_record_click($pdo, $config, (int) $target['id']);
sr_banner_redirect_to_link((string) $target['link_url']);
