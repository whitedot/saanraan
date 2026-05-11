<?php

declare(strict_types=1);

require_once TOY_ROOT . '/modules/banner/helpers.php';

$bannerId = (int) toy_get_string('id', 20);
$target = toy_banner_click_target($pdo, $bannerId);
if ($target === null) {
    toy_render_error(404, '배너 링크를 찾을 수 없습니다.');
}

toy_banner_record_click($pdo, $config, (int) $target['id']);
toy_banner_redirect_to_link((string) $target['link_url']);
