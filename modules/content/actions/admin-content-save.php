<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
if (is_file(SR_ROOT . '/modules/banner/helpers.php')) {
    require_once SR_ROOT . '/modules/banner/helpers.php';
}
if (is_file(SR_ROOT . '/modules/popup_layer/helpers.php')) {
    require_once SR_ROOT . '/modules/popup_layer/helpers.php';
}

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/content', 'edit');
sr_require_csrf();

$pageId = (int) sr_post_string('content_id', 20);
$values = sr_content_input_values($pdo);
$values['scheduled_publish_at'] = sr_content_scheduled_publish_at_from_post();
$seriesSortOrder = sr_admin_post_int_in_range('series_sort_order', 0, 1000000);
$seriesValues = [
    'series_id' => (int) sr_post_string('series_id', 20),
    'episode_label' => trim(sr_post_string('series_episode_label', 80)),
    'sort_order' => $seriesSortOrder ?? 0,
];
$publicBanners = function_exists('sr_banner_public_banners') && sr_module_enabled($pdo, 'banner')
    ? sr_banner_public_banners($pdo)
    : [];
$publicBannerIds = [];
foreach ($publicBanners as $publicBanner) {
    $publicBannerIds[(int) $publicBanner['id']] = true;
}
$publicPopupLayers = function_exists('sr_popup_layer_public_layers') && sr_module_enabled($pdo, 'popup_layer')
    ? sr_popup_layer_public_layers($pdo)
    : [];
$publicPopupLayerIds = [];
foreach ($publicPopupLayers as $publicPopupLayer) {
    $publicPopupLayerIds[(int) $publicPopupLayer['id']] = true;
}
$errors = sr_content_validate_input($pdo, $values, $pageId, $publicBannerIds, $publicPopupLayerIds);
if ($pageId > 0 && !is_array(sr_content_by_id($pdo, $pageId))) {
    $errors[] = '수정할 콘텐츠를 찾을 수 없습니다.';
}
if ((int) $seriesValues['series_id'] > 0) {
    $selectedSeries = sr_content_series_by_id($pdo, (int) $seriesValues['series_id']);
    if (!is_array($selectedSeries) || !in_array((string) ($selectedSeries['status'] ?? ''), ['pending', 'active', 'hidden'], true)) {
        $errors[] = '연결할 콘텐츠 시리즈를 확인해 주세요.';
    }
}
if ($seriesSortOrder === null) {
    $errors[] = '콘텐츠 시리즈 정렬 순서를 확인해 주세요.';
}
$errors = array_merge($errors, sr_content_validate_file_request($pdo, $pageId, $values));

if ($errors !== []) {
    $values['content_file_link_ids'] = sr_content_file_link_ids_from_post('content_file_link_ids');
    $values['series_id'] = (int) $seriesValues['series_id'];
    $values['series_episode_label'] = (string) $seriesValues['episode_label'];
    $values['series_sort_order'] = (int) $seriesValues['sort_order'];
    $_SESSION['sr_content_admin_errors'] = $errors;
    $_SESSION['sr_content_admin_values'] = $values;
    sr_redirect($pageId > 0 ? '/admin/content/edit?id=' . (string) $pageId : '/admin/content/new');
}

$beforeAssetSettings = $pageId > 0 ? sr_content_asset_settings_from_storage_for_audit($pdo, $pageId) : [];
$beforePage = $pageId > 0 ? sr_content_by_id($pdo, $pageId) : null;
$savedPageId = sr_content_save($pdo, $values, (int) $account['id'], $pageId);
sr_content_set_content_series($pdo, $savedPageId, (int) $seriesValues['series_id'], (string) $seriesValues['episode_label'], (int) $seriesValues['sort_order'], (int) $account['id']);
try {
    sr_content_save_files_from_request($pdo, $savedPageId, (int) $account['id'], $values);
} catch (Throwable $exception) {
    if (function_exists('sr_log_exception')) {
        sr_log_exception($exception, 'content_file_save_failed');
    }

    $_SESSION['sr_content_admin_errors'] = ['콘텐츠는 저장했지만 파일 저장에 실패했습니다: ' . $exception->getMessage()];
    sr_redirect('/admin/content/edit?id=' . (string) $savedPageId);
}
sr_audit_log($pdo, [
    'actor_account_id' => (int) $account['id'],
    'actor_type' => 'admin',
    'event_type' => $pageId > 0 ? 'content.updated' : 'content.created',
    'target_type' => 'content',
    'target_id' => (string) $savedPageId,
    'result' => 'success',
    'message' => $pageId > 0 ? 'Content updated.' : 'Content created.',
    'metadata' => [
        'slug' => (string) $values['slug'],
        'status' => (string) $values['status'],
        'content_group_id' => (int) ($values['content_group_id'] ?? 0),
        'layout_key' => (string) ($values['layout_key'] ?? ''),
    ],
]);
$beforeStatus = is_array($beforePage) ? (string) ($beforePage['status'] ?? '') : '';
$beforePublishedAt = is_array($beforePage) ? (string) ($beforePage['published_at'] ?? '') : '';
$afterStatus = (string) $values['status'];
$afterPublishedAt = (string) ($values['scheduled_publish_at'] ?? '');
if ($afterStatus === 'scheduled' && ($beforeStatus !== 'scheduled' || $beforePublishedAt !== $afterPublishedAt)) {
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'content.scheduled',
        'target_type' => 'content',
        'target_id' => (string) $savedPageId,
        'result' => 'success',
        'message' => 'Content scheduled for publishing.',
        'metadata' => [
            'slug' => (string) $values['slug'],
            'scheduled_publish_at' => $afterPublishedAt,
            'previous_status' => $beforeStatus,
            'previous_published_at' => $beforePublishedAt,
        ],
    ]);
} elseif ($beforeStatus === 'scheduled' && $afterStatus !== 'scheduled') {
    sr_audit_log($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'content.schedule_cleared',
        'target_type' => 'content',
        'target_id' => (string) $savedPageId,
        'result' => 'success',
        'message' => 'Content schedule cleared.',
        'metadata' => [
            'slug' => (string) $values['slug'],
            'status' => $afterStatus,
            'previous_published_at' => $beforePublishedAt,
        ],
    ]);
}
if ($pageId > 0) {
    sr_admin_audit_asset_settings_update($pdo, [
        'actor_account_id' => (int) $account['id'],
        'actor_type' => 'admin',
        'event_type' => 'content.asset_settings.updated',
        'target_type' => 'content',
        'target_id' => (string) $savedPageId,
        'asset_settings_scope' => 'content',
        'before_asset_settings' => $beforeAssetSettings,
        'after_asset_settings' => sr_content_asset_settings_from_storage_for_audit($pdo, $savedPageId),
        'message' => 'Content asset settings updated.',
        'metadata' => [
            'slug' => (string) $values['slug'],
        ],
    ]);
}

$_SESSION['sr_content_admin_notice'] = $pageId > 0 ? '콘텐츠를 저장했습니다.' : '콘텐츠를 만들었습니다.';
sr_redirect($pageId > 0 ? '/admin/content/edit?id=' . (string) $savedPageId : '/admin/content');
