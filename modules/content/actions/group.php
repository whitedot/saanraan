<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/content/helpers.php';

$groupKey = sr_content_clean_slug(sr_get_string('key', 60));
$pageGroup = sr_content_enabled_group_by_key($pdo, $groupKey);
if (!is_array($pageGroup)) {
    sr_render_error(404, sr_t('content::action.error.content_group_not_found'));
}

$groupContents = sr_content_published_contents_for_group($pdo, (int) $pageGroup['id']);
$pageTitle = (string) ($pageGroup['title'] ?? '콘텐츠 그룹');
$pageDescription = (string) ($pageGroup['description'] ?? '');

include SR_ROOT . '/modules/content/views/group.php';
