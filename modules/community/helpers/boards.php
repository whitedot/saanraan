<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/community/helpers/post-body-settings.php';
require_once SR_ROOT . '/modules/community/helpers/board-groups.php';
require_once SR_ROOT . '/modules/community/helpers/board-cleanup.php';
require_once SR_ROOT . '/modules/community/helpers/posts-extra-fields.php';

function sr_community_board_key_is_valid(string $boardKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $boardKey) === 1;
}

function sr_community_board_group_key_is_valid(string $groupKey): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $groupKey) === 1;
}

function sr_community_board_group_path(string $groupKey): string
{
    return '/community/group?key=' . rawurlencode($groupKey);
}

function sr_community_board_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_community_board_group_statuses(): array
{
    return ['enabled', 'disabled', 'archived'];
}

function sr_community_policy_values(string $policy): array
{
    if ($policy === 'read') {
        return ['public', 'member', 'group'];
    }

    if ($policy === 'write') {
        return ['guest', 'member', 'group', 'admin'];
    }

    if ($policy === 'comment') {
        return ['guest', 'member', 'group', 'disabled'];
    }

    return [];
}

function sr_community_board_group_setting_keys(): array
{
    return [
        'status',
        'skin_key',
        'read_policy',
        'write_policy',
        'comment_policy',
        'identity_verification_enabled',
        'identity_verification_purpose',
        'identity_verification_required_actions',
        'read_group_keys',
        'write_group_keys',
        'comment_group_keys',
        'read_min_level',
        'write_min_level',
        'comment_min_level',
        'series_enabled',
        'category_enabled',
        'category_required',
        'secret_posts_enabled',
        'secret_comments_enabled',
        'post_edit_lock_comment_count',
        'post_delete_lock_comment_count',
        'post_body_min_length',
        'post_body_max_length',
        'comment_body_min_length',
        'comment_body_max_length',
        'comments_per_page',
        'list_excerpt_enabled',
        'list_excerpt_length',
        'list_per_page',
        'list_default_sort',
        'summary_feed_enabled',
        'board_sidebar_menu_type',
        'board_sidebar_site_menu_key',
        'reaction_enabled',
        'reaction_post_preset_key',
        'reaction_comment_preset_key',
        'level_post_score',
        'level_comment_score',
        'image_uploads_enabled',
        'thumbnail_enabled',
        'thumbnail_criterion',
        'thumbnail_min_width',
        'thumbnail_min_bytes',
        'attachment_max_bytes',
        'attachment_max_count',
        'file_uploads_enabled',
        'file_attachment_max_bytes',
        'file_attachment_max_count',
        'file_allowed_extensions',
        'banner_before_list_id',
        'banner_after_list_id',
        'banner_before_view_id',
        'banner_after_view_id',
        'banner_before_form_id',
        'banner_after_form_id',
        'popup_layer_list_id',
        'popup_layer_view_id',
        'popup_layer_form_id',
        'privacy_consent_enabled',
        'privacy_consent_document_key',
        'privacy_consent_post_document_key',
        'privacy_consent_comment_document_key',
        'privacy_consent_attachment_upload_document_key',
        'privacy_consent_document_inherit_policy',
        'privacy_consent_title',
        'privacy_consent_body',
        'privacy_consent_version',
        'privacy_consent_require_post',
        'privacy_consent_require_comment',
        'privacy_consent_require_attachment_upload',
        'extra_fields_json',
        'comment_extra_fields_json',
        'seo_title',
        'seo_description',
        'og_title',
        'og_description',
        'og_image_url',
    ];
}

function sr_community_asset_setting_prefixes(): array
{
    return ['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'];
}

function sr_community_module_asset_setting_prefixes(): array
{
    return ['post_reward', 'comment_reward', 'write_charge', 'comment_charge', 'paid_read', 'paid_attachment_download'];
}

function sr_community_board_group_asset_setting_keys(): array
{
    $keys = [];
    foreach (sr_community_asset_setting_prefixes() as $prefix) {
        $keys[] = $prefix . '_enabled';
        $keys[] = $prefix . '_asset_module';
        $keys[] = $prefix . '_amount';
        if (in_array($prefix, sr_community_asset_composite_prefixes(), true)) {
            $keys[] = $prefix . '_settlement_currency';
        }
        $keys[] = $prefix . '_group_policies_json';
        $keys[] = $prefix . '_policy_set_id';
        if (in_array($prefix, sr_community_asset_composite_prefixes(), true)) {
            $keys[] = $prefix . '_amounts_json';
        }
    }
    $keys[] = 'paid_read_charge_policy';
    $keys[] = 'paid_attachment_download_charge_policy';
    $keys[] = 'paid_attachment_download_publisher_reward_enabled';
    $keys[] = 'paid_attachment_download_publisher_reward_rate';

    return $keys;
}

function sr_community_board_group_all_setting_keys(): array
{
    return array_values(array_unique(array_merge(
        sr_community_board_group_setting_keys(),
        sr_community_board_group_asset_setting_keys()
    )));
}

function sr_community_board_group_default_settings(array $settings): array
{
    $fileAllowedExtensions = $settings['file_allowed_extensions'] ?? ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp'];
    if (is_array($fileAllowedExtensions)) {
        $fileAllowedExtensions = implode(',', array_map('strval', $fileAllowedExtensions));
    }

    $defaults = [
        'read_policy' => 'public',
        'write_policy' => 'member',
        'comment_policy' => 'member',
        'identity_verification_enabled' => '0',
        'identity_verification_purpose' => 'real_name',
        'identity_verification_required_actions' => '[]',
        'read_group_keys' => '[]',
        'write_group_keys' => '[]',
        'comment_group_keys' => '[]',
        'read_min_level' => '0',
        'write_min_level' => '0',
        'comment_min_level' => '0',
        'series_enabled' => !empty($settings['series_enabled']) ? '1' : '0',
        'category_enabled' => '1',
        'category_required' => !empty($settings['category_required']) ? '1' : '0',
        'secret_posts_enabled' => !empty($settings['secret_posts_enabled']) ? '1' : '0',
        'secret_comments_enabled' => !empty($settings['secret_comments_enabled']) ? '1' : '0',
        'post_edit_lock_comment_count' => '0',
        'post_delete_lock_comment_count' => '0',
        'post_body_min_length' => (string) sr_community_post_body_length_setting($settings['post_body_min_length'] ?? 0),
        'post_body_max_length' => (string) sr_community_post_body_length_setting($settings['post_body_max_length'] ?? 0),
        'comment_body_min_length' => '0',
        'comment_body_max_length' => '0',
        'comments_per_page' => '0',
        'list_excerpt_enabled' => '0',
        'list_excerpt_length' => '120',
        'list_per_page' => (string) min(100, max(1, (int) ($settings['posts_per_page'] ?? 20))),
        'list_default_sort' => 'latest',
        'summary_feed_enabled' => '1',
        'board_sidebar_menu_type' => sr_community_board_sidebar_menu_type((string) ($settings['board_sidebar_menu_type'] ?? 'all_boards')),
        'board_sidebar_site_menu_key' => sr_community_board_sidebar_site_menu_key((string) ($settings['board_sidebar_site_menu_key'] ?? '')),
        'level_post_score' => (string) min(10000, max(0, (int) ($settings['level_post_score'] ?? 10))),
        'level_comment_score' => (string) min(10000, max(0, (int) ($settings['level_comment_score'] ?? 2))),
        'image_uploads_enabled' => !empty($settings['image_uploads_enabled']) ? '1' : '0',
        'thumbnail_enabled' => !empty($settings['thumbnail_enabled']) ? '1' : '0',
        'thumbnail_criterion' => sr_community_thumbnail_criterion((string) ($settings['thumbnail_criterion'] ?? 'width')),
        'thumbnail_min_width' => (string) min(4000, max(1, (int) ($settings['thumbnail_min_width'] ?? 320))),
        'thumbnail_min_bytes' => (string) min(20971520, max(0, (int) ($settings['thumbnail_min_bytes'] ?? 102400))),
        'attachment_max_bytes' => (string) min(10485760, max(1024, (int) ($settings['attachment_max_bytes'] ?? 2097152))),
        'attachment_max_count' => (string) min(10, max(0, (int) ($settings['attachment_max_count'] ?? 1))),
        'file_uploads_enabled' => !empty($settings['file_uploads_enabled']) ? '1' : '0',
        'file_attachment_max_bytes' => (string) min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880))),
        'file_attachment_max_count' => (string) min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3))),
        'file_allowed_extensions' => (string) $fileAllowedExtensions,
        'privacy_consent_enabled' => !empty($settings['privacy_consent_enabled']) ? '1' : '0',
        'privacy_consent_document_key' => (string) ($settings['privacy_consent_document_key'] ?? 'community_privacy_default'),
        'privacy_consent_post_document_key' => (string) ($settings['privacy_consent_post_document_key'] ?? ''),
        'privacy_consent_comment_document_key' => (string) ($settings['privacy_consent_comment_document_key'] ?? ''),
        'privacy_consent_attachment_upload_document_key' => (string) ($settings['privacy_consent_attachment_upload_document_key'] ?? ''),
        'privacy_consent_document_inherit_policy' => (string) ($settings['privacy_consent_document_inherit_policy'] ?? 'override'),
        'privacy_consent_title' => (string) ($settings['privacy_consent_title'] ?? '개인정보 수집 및 이용동의'),
        'privacy_consent_body' => (string) ($settings['privacy_consent_body'] ?? ''),
        'privacy_consent_version' => (string) ($settings['privacy_consent_version'] ?? '1'),
        'privacy_consent_require_post' => !empty($settings['privacy_consent_require_post']) ? '1' : '0',
        'privacy_consent_require_comment' => !empty($settings['privacy_consent_require_comment']) ? '1' : '0',
        'privacy_consent_require_attachment_upload' => !empty($settings['privacy_consent_require_attachment_upload']) ? '1' : '0',
        'extra_fields_json' => '[]',
        'comment_extra_fields_json' => '[]',
        'reaction_enabled' => !empty($settings['reaction_enabled']) ? '1' : '0',
        'reaction_post_preset_key' => (string) ($settings['reaction_post_preset_key'] ?? ''),
        'reaction_comment_preset_key' => (string) ($settings['reaction_comment_preset_key'] ?? ''),
    ];

    foreach (sr_community_public_display_setting_labels() as $settingKey => $settingLabel) {
        $defaults[(string) $settingKey] = '0';
    }

    foreach (sr_community_board_group_asset_setting_keys() as $settingKey) {
        $value = $settings[(string) $settingKey] ?? (str_ends_with((string) $settingKey, '_asset_module') ? '' : '0');
        if (is_bool($value)) {
            $value = $value ? '1' : '0';
        } elseif (is_array($value)) {
            $value = implode(',', array_map('strval', $value));
        }

        $defaults[(string) $settingKey] = (string) $value;
    }

    return $defaults;
}

function sr_community_board_default_settings(array $settings, array $groupSettings = []): array
{
    $defaults = sr_community_board_group_default_settings($settings);
    $defaults['status'] = (string) ($settings['board_status'] ?? 'enabled');
    $defaults['skin_key'] = 'basic';
    $defaults['post_editor'] = sr_community_post_editor_key((string) ($settings['post_editor'] ?? 'textarea'));
    $defaults['extra_fields_json'] = sr_community_extra_field_definitions_json($settings['extra_fields_json'] ?? '[]');
    $defaults['comment_extra_fields_json'] = sr_comment_extra_field_definitions_json($settings['comment_extra_fields_json'] ?? '[]');

    $defaults['reaction_post_preset_key'] = '';
    $defaults['reaction_comment_preset_key'] = '';

    $arrayKeys = ['read_group_keys', 'write_group_keys', 'comment_group_keys'];
    foreach ($arrayKeys as $settingKey) {
        $value = (string) ($defaults[$settingKey] ?? '');
        $decoded = json_decode($value, true);
        $defaults[$settingKey] = sr_community_normalize_board_group_keys(is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $value));
    }

    $fileAllowedExtensions = (string) ($defaults['file_allowed_extensions'] ?? '');
    $defaults['file_allowed_extensions'] = sr_community_file_extensions_from_input($fileAllowedExtensions);

    foreach (sr_community_board_group_setting_keys() as $settingKey) {
        $defaults['source_' . (string) $settingKey] = 'board';
    }
    foreach (sr_community_asset_setting_keys() as $settingKey) {
        $defaults['source_' . (string) $settingKey] = 'board';
    }

    return $defaults;
}

function sr_community_public_banner_setting_labels(): array
{
    return [
        'banner_before_list_id' => sr_t('community::display.banner_before_list'),
        'banner_after_list_id' => sr_t('community::display.banner_after_list'),
        'banner_before_view_id' => sr_t('community::display.banner_before_view'),
        'banner_after_view_id' => sr_t('community::display.banner_after_view'),
        'banner_before_form_id' => sr_t('community::display.banner_before_form'),
        'banner_after_form_id' => sr_t('community::display.banner_after_form'),
    ];
}

function sr_community_public_popup_layer_setting_labels(): array
{
    return [
        'popup_layer_list_id' => sr_t('community::display.popup_layer_list'),
        'popup_layer_view_id' => sr_t('community::display.popup_layer_view'),
        'popup_layer_form_id' => sr_t('community::display.popup_layer_form'),
    ];
}

function sr_community_asset_setting_label(string $assetPrefix): string
{
    $labels = [
        'post_reward' => sr_t('community::asset_setting.post_reward'),
        'comment_reward' => sr_t('community::asset_setting.comment_reward'),
        'write_charge' => sr_t('community::asset_setting.write_charge'),
        'comment_charge' => sr_t('community::asset_setting.comment_charge'),
        'paid_read' => sr_t('community::asset_setting.paid_read'),
        'paid_attachment_download' => sr_t('community::asset_setting.paid_attachment_download'),
    ];

    return (string) ($labels[$assetPrefix] ?? $assetPrefix);
}

function sr_community_public_display_setting_labels(): array
{
    return sr_community_public_banner_setting_labels() + sr_community_public_popup_layer_setting_labels();
}

function sr_community_board_group_column_setting_keys(): array
{
    return ['status', 'read_policy', 'write_policy', 'comment_policy', 'image_uploads_enabled'];
}

function sr_community_board_setting_source_values(): array
{
    return ['board', 'group', 'all'];
}

function sr_community_board_sidebar_menu_type_options(bool $siteMenuAvailable = true): array
{
    $options = [
        'none' => '선택 안 함',
        'all_boards' => '전체 게시판',
        'same_group' => '같은 그룹 게시판',
    ];

    if ($siteMenuAvailable) {
        $options['site_menu'] = '사이트 메뉴의 특정값';
    }

    return $options;
}

function sr_community_board_sidebar_menu_type(string $value): string
{
    $value = strtolower(trim($value));
    return array_key_exists($value, sr_community_board_sidebar_menu_type_options()) ? $value : 'all_boards';
}

function sr_community_board_sidebar_site_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_community_board_sidebar_site_menu_available(PDO $pdo): bool
{
    return sr_module_enabled($pdo, 'site_menu')
        && is_file(SR_ROOT . '/modules/site_menu/helpers.php');
}

function sr_community_normalize_board_setting_source(string $source): string
{
    if ($source === 'here_only') {
        return 'board';
    }

    return in_array($source, sr_community_board_setting_source_values(), true) ? $source : 'board';
}

function sr_community_board_scope_target_ids(PDO $pdo, int $boardId, int $boardGroupId, string $source): array
{
    if ($boardId < 1) {
        return [];
    }

    $source = sr_community_normalize_board_setting_source($source);
    if ($source === 'all') {
        $stmt = $pdo->query('SELECT id FROM sr_community_boards ORDER BY id ASC');
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    if ($source === 'group' && $boardGroupId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM sr_community_boards WHERE board_group_id = :board_group_id ORDER BY id ASC');
        $stmt->execute(['board_group_id' => $boardGroupId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    return [$boardId];
}

function sr_community_board_setting_value_type(string $settingKey): string
{
    if (in_array($settingKey, [
        'attachment_max_bytes',
        'attachment_max_count',
        'thumbnail_min_width',
        'thumbnail_min_bytes',
        'file_attachment_max_bytes',
        'file_attachment_max_count',
        'read_min_level',
        'write_min_level',
        'comment_min_level',
        'level_post_score',
        'level_comment_score',
        'post_edit_lock_comment_count',
        'post_delete_lock_comment_count',
        'post_body_min_length',
        'post_body_max_length',
        'comment_body_min_length',
        'comment_body_max_length',
        'comments_per_page',
        'list_excerpt_length',
        'list_per_page',
        'banner_before_list_id',
        'banner_after_list_id',
        'banner_before_view_id',
        'banner_after_view_id',
        'banner_before_form_id',
        'banner_after_form_id',
        'popup_layer_list_id',
        'popup_layer_view_id',
        'popup_layer_form_id',
    ], true) || str_ends_with($settingKey, '_amount')) {
        return 'int';
    }

    if (in_array($settingKey, ['file_uploads_enabled'], true) || str_ends_with($settingKey, '_enabled') || str_starts_with($settingKey, 'privacy_consent_require_')) {
        return 'bool';
    }

    if (in_array($settingKey, ['read_group_keys', 'write_group_keys', 'comment_group_keys', 'identity_verification_required_actions'], true) || str_ends_with($settingKey, '_amounts_json')) {
        return 'json';
    }

    return 'string';
}

function sr_community_thumbnail_setting_keys(): array
{
    return [
        'thumbnail_enabled',
        'thumbnail_criterion',
        'thumbnail_min_width',
        'thumbnail_min_bytes',
    ];
}

function sr_community_thumbnail_criterion(string $value): string
{
    return in_array($value, ['width', 'bytes'], true) ? $value : 'width';
}

function sr_community_thumbnail_int_setting(string $settingKey, mixed $value): int
{
    if ($settingKey === 'thumbnail_min_bytes') {
        return min(20971520, max(0, (int) $value));
    }

    return min(4000, max(1, (int) $value));
}

function sr_community_thumbnail_default_setting(string $settingKey, array $settings = []): string
{
    $settings = $settings !== [] ? sr_community_normalize_settings($settings) : sr_community_default_settings();
    if ($settingKey === 'thumbnail_enabled') {
        return !empty($settings['thumbnail_enabled']) ? '1' : '0';
    }
    if ($settingKey === 'thumbnail_criterion') {
        return sr_community_thumbnail_criterion((string) ($settings['thumbnail_criterion'] ?? 'width'));
    }

    return (string) sr_community_thumbnail_int_setting($settingKey, $settings[$settingKey] ?? 0);
}

function sr_community_board_thumbnail_setting(PDO $pdo, int $boardId, string $settingKey, array $settings = []): string
{
    if (!in_array($settingKey, sr_community_thumbnail_setting_keys(), true)) {
        return '';
    }

    $default = sr_community_thumbnail_default_setting($settingKey, $settings);
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, $settingKey, $default)
        : sr_community_board_setting_value($pdo, $boardId, $settingKey);
    if (!is_string($value) || $value === '') {
        return $default;
    }

    return $settingKey === 'thumbnail_enabled'
        ? (in_array($value, ['1', 'true', 'yes', 'on'], true) ? '1' : '0')
        : ($settingKey === 'thumbnail_criterion' ? sr_community_thumbnail_criterion($value) : (string) sr_community_thumbnail_int_setting($settingKey, $value));
}

function sr_community_board_own_thumbnail_setting(PDO $pdo, int $boardId, string $settingKey, array $settings = []): string
{
    if (!in_array($settingKey, sr_community_thumbnail_setting_keys(), true)) {
        return '';
    }

    $value = sr_community_board_setting_value($pdo, $boardId, $settingKey);
    if (!is_string($value) || $value === '') {
        return sr_community_thumbnail_default_setting($settingKey, $settings);
    }

    return $settingKey === 'thumbnail_enabled'
        ? (in_array($value, ['1', 'true', 'yes', 'on'], true) ? '1' : '0')
        : ($settingKey === 'thumbnail_criterion' ? sr_community_thumbnail_criterion($value) : (string) sr_community_thumbnail_int_setting($settingKey, $value));
}

function sr_community_effective_thumbnail_setting(PDO $pdo, array $board, string $settingKey, array $settings = []): string
{
    if (!in_array($settingKey, sr_community_thumbnail_setting_keys(), true)) {
        return '';
    }

    $default = sr_community_thumbnail_default_setting($settingKey, $settings);
    $value = sr_community_effective_board_setting($pdo, $board, $settingKey, $default);
    if (!is_string($value) || $value === '') {
        return $default;
    }

    return $settingKey === 'thumbnail_enabled'
        ? (in_array($value, ['1', 'true', 'yes', 'on'], true) ? '1' : '0')
        : ($settingKey === 'thumbnail_criterion' ? sr_community_thumbnail_criterion($value) : (string) sr_community_thumbnail_int_setting($settingKey, $value));
}

function sr_community_post_editor_key(string $value, bool $allowInherit = false): string
{
    return sr_editor_normalize_key($value, $allowInherit);
}

function sr_community_effective_post_editor(PDO $pdo, array $board, ?array $settings = null): string
{
    $boardId = (int) ($board['id'] ?? 0);

    if ($boardId > 0) {
        $boardEditorValue = sr_community_board_setting_value($pdo, $boardId, 'post_editor');
        $boardEditor = is_string($boardEditorValue) ? sr_community_post_editor_key($boardEditorValue) : '';
        if ($boardEditor !== '') {
            return sr_editor_effective_key($pdo, $boardEditor);
        }
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    return sr_editor_effective_key($pdo, (string) ($settings['post_editor'] ?? 'textarea'));
}

function sr_community_apply_board_setting_scope(PDO $pdo, int $boardId, int $boardGroupId, string $settingKey, string $source, mixed $value): void
{
    $targets = sr_community_board_scope_target_ids($pdo, $boardId, $boardGroupId, $source);
    if ($targets === []) {
        return;
    }

    if (in_array($settingKey, sr_community_board_group_column_setting_keys(), true)) {
        $placeholders = implode(',', array_fill(0, count($targets), '?'));
        $stmt = $pdo->prepare('UPDATE sr_community_boards SET ' . $settingKey . ' = ?, updated_at = ? WHERE id IN (' . $placeholders . ')');
        $stmt->execute(array_merge([(string) $value, sr_now()], $targets));
    } else {
        $valueType = sr_community_board_setting_value_type($settingKey);
        foreach ($targets as $targetBoardId) {
            sr_community_set_board_setting($pdo, (int) $targetBoardId, $settingKey, (string) $value, $valueType);
        }
    }

    foreach ($targets as $targetBoardId) {
        sr_community_set_board_setting_source($pdo, (int) $targetBoardId, $settingKey, 'board');
    }

    if ($settingKey === 'summary_feed_enabled') {
        $summaryFeedCandidate = in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
        foreach ($targets as $targetBoardId) {
            sr_community_sync_board_summary_feed_candidates($pdo, (int) $targetBoardId, $summaryFeedCandidate);
        }
    }
}

function sr_community_board_select_columns(string $alias = 'b'): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';

    return $prefix . 'id, ' . $prefix . 'board_group_id, ' . $prefix . 'board_key, ' . $prefix . 'title, '
        . $prefix . 'description, ' . $prefix . 'status, ' . $prefix . 'read_policy, ' . $prefix . 'write_policy, '
        . $prefix . 'comment_policy, ' . $prefix . 'image_uploads_enabled, ' . $prefix . 'sort_order, '
        . $prefix . 'created_at, ' . $prefix . 'updated_at';
}

function sr_community_boards(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT ' . sr_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, b.sort_order ASC, b.id ASC'
    );

    $boards = [];
    foreach ($stmt->fetchAll() as $board) {
        $boards[] = sr_community_board_with_effective_settings($pdo, $board);
    }

    return $boards;
}

function sr_community_admin_board_query_parts(array $filters): array
{
    $where = [];
    $params = [];
    $status = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $groupId = (int) ($filters['group_id'] ?? 0);
    $field = (string) ($filters['field'] ?? 'all');
    $keyword = trim((string) ($filters['q'] ?? ''));

    if ($status !== []) {
        [$condition, $conditionParams] = sr_admin_sql_in_condition('b.status', 'status', $status);
        $where[] = $condition;
        $params = array_merge($params, $conditionParams);
    }

    if ($groupId > 0) {
        $where[] = 'b.board_group_id = :board_group_id';
        $params['board_group_id'] = $groupId;
    }

    if ($keyword !== '') {
        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $keyword) . '%';
        if ($field === 'key') {
            $where[] = 'b.board_key LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'title') {
            $where[] = 'b.title LIKE :keyword';
            $params['keyword'] = $like;
        } elseif ($field === 'group') {
            $where[] = '(g.title LIKE :group_title_keyword OR g.group_key LIKE :group_key_keyword)';
            $params['group_title_keyword'] = $like;
            $params['group_key_keyword'] = $like;
        } else {
            $where[] = '(b.board_key LIKE :board_key_keyword OR b.title LIKE :title_keyword OR b.description LIKE :description_keyword OR g.title LIKE :group_title_keyword OR g.group_key LIKE :group_key_keyword)';
            $params['board_key_keyword'] = $like;
            $params['title_keyword'] = $like;
            $params['description_keyword'] = $like;
            $params['group_title_keyword'] = $like;
            $params['group_key_keyword'] = $like;
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_community_admin_board_count(PDO $pdo, array $filters): int
{
    $queryParts = sr_community_admin_board_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_community_boards b
            LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_community_admin_board_sort_options(): array
{
    return [
        'board_key' => ['columns' => ['b.board_key', 'b.id']],
        'title' => ['columns' => ['b.title', 'b.id']],
        'board_group' => ['columns' => ['g.title', 'b.id']],
        'status' => ['columns' => ['b.status', 'b.id']],
        'sort_order' => ['columns' => ['COALESCE(g.sort_order, 1000000)', 'g.id', 'b.sort_order', 'b.id']],
    ];
}

function sr_community_admin_board_default_sort(): array
{
    return sr_admin_sort_default('sort_order', 'asc');
}

function sr_community_admin_boards(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = [], bool $withEffectiveSettings = true): array
{
    $queryParts = sr_community_admin_board_query_parts($filters);
    $sql = 'SELECT ' . sr_community_board_select_columns('b') . ',
                   g.group_key AS board_group_key,
                   g.title AS board_group_title,
                   g.status AS board_group_status,
                   g.sort_order AS board_group_sort_order
            FROM sr_community_boards b
            LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }
    $sql .= sr_admin_sort_order_sql(sr_community_admin_board_sort_options(), $sort, sr_community_admin_board_default_sort());
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($queryParts['params'] as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    $boards = [];
    foreach ($stmt->fetchAll() as $board) {
        $boards[] = $withEffectiveSettings ? sr_community_board_with_effective_settings($pdo, $board) : $board;
    }

    return $boards;
}

function sr_community_admin_board_status_counts(PDO $pdo, array $allowedStatuses): array
{
    $counts = ['total' => 0];
    foreach ($allowedStatuses as $status) {
        $counts[(string) $status] = 0;
    }

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_community_boards GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
        $counts['total'] += $count;
    }

    return $counts;
}

function sr_community_enabled_boards(PDO $pdo): array
{
    $cachedBoards = sr_community_enabled_boards_file_cache_read();
    if (is_array($cachedBoards)) {
        return $cachedBoards;
    }

    $stmt = $pdo->query(
        "SELECT " . sr_community_board_select_columns('b') . ",
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         WHERE b.status = 'enabled'
         ORDER BY COALESCE(g.sort_order, 1000000) ASC, g.id ASC, b.sort_order ASC, b.id ASC"
    );

    $rows = $stmt->fetchAll();
    sr_community_preload_board_settings_runtime_cache($pdo, array_map(static fn (array $board): int => (int) ($board['id'] ?? 0), $rows));

    $boards = [];
    foreach ($rows as $board) {
        $boards[] = sr_community_board_with_effective_settings($pdo, $board);
    }
    sr_community_enabled_boards_file_cache_write($boards);

    return $boards;
}

function sr_community_board_file_cache_root(): string
{
    return rtrim((string) SR_ROOT, '/\\') . '/storage/cache/community-board';
}

function sr_community_enabled_boards_file_cache_path(): string
{
    return sr_community_board_file_cache_root() . '/enabled-boards.json';
}

function sr_community_enabled_boards_file_cache_read(): ?array
{
    $memoryRecord = $GLOBALS['sr_community_enabled_boards_file_cache_record'] ?? null;
    if (is_array($memoryRecord)
        && (string) ($memoryRecord['schema_version'] ?? '') === 'community_enabled_boards_file_cache_v1'
        && (string) ($memoryRecord['cache_status'] ?? '') === 'fresh'
        && is_array($memoryRecord['boards'] ?? null)
    ) {
        return $memoryRecord['boards'];
    }

    $path = sr_community_enabled_boards_file_cache_path();
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $json = file_get_contents($path);
    if (!is_string($json)) {
        return null;
    }

    $record = json_decode($json, true);
    if (!is_array($record)
        || (string) ($record['schema_version'] ?? '') !== 'community_enabled_boards_file_cache_v1'
        || (string) ($record['cache_status'] ?? '') !== 'fresh'
        || !is_array($record['boards'] ?? null)
    ) {
        return null;
    }

    $GLOBALS['sr_community_enabled_boards_file_cache_record'] = $record;

    return $record['boards'];
}

function sr_community_enabled_boards_file_cache_write(array $boards): void
{
    $safeBoards = [];
    foreach ($boards as $board) {
        if (is_array($board)) {
            $safeBoards[] = $board;
        }
    }

    $record = [
        'schema_version' => 'community_enabled_boards_file_cache_v1',
        'cache_status' => 'fresh',
        'board_count' => count($safeBoards),
        'boards' => $safeBoards,
        'generated_at' => sr_now(),
    ];
    $json = json_encode($record, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        return;
    }

    $path = sr_community_enabled_boards_file_cache_path();
    if (!sr_write_file_atomically($path, $json . "\n")) {
        return;
    }

    $GLOBALS['sr_community_enabled_boards_file_cache_record'] = $record;
}

function sr_community_enabled_boards_file_cache_mark_stale(): void
{
    unset($GLOBALS['sr_community_enabled_boards_file_cache_record']);
    $path = sr_community_enabled_boards_file_cache_path();
    if (is_file($path)) {
        @unlink($path);
    }
}

function sr_community_board_by_key(PDO $pdo, string $boardKey): ?array
{
    if (!sr_community_board_key_is_valid($boardKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . sr_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         WHERE b.board_key = :board_key
         LIMIT 1'
    );
    $stmt->execute(['board_key' => $boardKey]);
    $board = $stmt->fetch();

    return is_array($board) ? sr_community_board_with_effective_settings($pdo, $board) : null;
}

function sr_community_board_by_id(PDO $pdo, int $boardId): ?array
{
    if ($boardId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT ' . sr_community_board_select_columns('b') . ',
                g.group_key AS board_group_key,
                g.title AS board_group_title,
                g.status AS board_group_status,
                g.sort_order AS board_group_sort_order
         FROM sr_community_boards b
         LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
         WHERE b.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $boardId]);
    $board = $stmt->fetch();

    return is_array($board) ? sr_community_board_with_effective_settings($pdo, $board) : null;
}

function sr_community_board_with_effective_settings(PDO $pdo, array $board): array
{
    $board['effective_read_policy'] = sr_community_effective_board_policy($pdo, $board, 'read_policy');
    $board['effective_write_policy'] = sr_community_effective_board_policy($pdo, $board, 'write_policy');
    $board['effective_comment_policy'] = sr_community_effective_board_policy($pdo, $board, 'comment_policy');
    $board['effective_image_uploads_enabled'] = sr_community_effective_board_image_uploads_enabled($pdo, $board) ? 1 : 0;
    $board['effective_summary_feed_enabled'] = sr_community_effective_board_summary_feed_enabled($pdo, $board) ? 1 : 0;
    foreach (sr_community_public_display_setting_labels() as $settingKey => $settingLabel) {
        $board[$settingKey] = (int) sr_community_effective_board_setting($pdo, $board, (string) $settingKey, '0');
    }
    foreach (sr_community_seo_setting_keys() as $settingKey) {
        $board[$settingKey] = sr_community_effective_board_setting($pdo, $board, (string) $settingKey, '');
    }
    $defaultThumbnailSettings = sr_community_default_settings();
    foreach (sr_community_thumbnail_setting_keys() as $thumbnailSettingKey) {
        $board['effective_' . $thumbnailSettingKey] = sr_community_effective_thumbnail_setting($pdo, $board, $thumbnailSettingKey, $defaultThumbnailSettings);
    }
    $board['effective_file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;

    return $board;
}

function sr_community_admin_prepare_board_row(PDO $pdo, array $board, array $settings, array $publicDisplaySettingLabels, array $categoriesByBoardId = []): array
{
    $boardId = (int) ($board['id'] ?? 0);
    $board['setting_sources'] = sr_community_board_setting_sources($pdo, (int) $board['id']);
    $board['attachment_max_bytes'] = sr_community_board_own_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['attachment_max_count'] = sr_community_board_own_attachment_max_count($pdo, (int) $board['id'], $settings);
    foreach ($publicDisplaySettingLabels as $displaySettingKey => $_displaySettingLabel) {
        $board[(string) $displaySettingKey] = (int) (sr_community_board_setting_value($pdo, (int) $board['id'], (string) $displaySettingKey) ?? 0);
    }
    $defaultAttachmentMaxBytes = min(10485760, max(1024, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
    $effectiveAttachmentMaxBytes = sr_community_effective_board_setting($pdo, $board, 'attachment_max_bytes', (string) $defaultAttachmentMaxBytes);
    $board['effective_attachment_max_bytes'] = min(10485760, max(1024, (int) $effectiveAttachmentMaxBytes));
    $effectiveAttachmentMaxCount = sr_community_effective_board_setting($pdo, $board, 'attachment_max_count', '1');
    $board['effective_attachment_max_count'] = min(10, max(0, (int) $effectiveAttachmentMaxCount));
    foreach (sr_community_thumbnail_setting_keys() as $thumbnailSettingKey) {
        $board[$thumbnailSettingKey] = sr_community_board_own_thumbnail_setting($pdo, (int) $board['id'], $thumbnailSettingKey, $settings);
        $board['effective_' . $thumbnailSettingKey] = sr_community_effective_thumbnail_setting($pdo, $board, $thumbnailSettingKey, $settings);
    }
    $board['file_uploads_enabled'] = sr_community_effective_board_setting($pdo, $board, 'file_uploads_enabled', '0');
    $board['effective_file_uploads_enabled'] = sr_community_effective_board_file_uploads_enabled($pdo, $board) ? 1 : 0;
    $board['file_attachment_max_bytes'] = sr_community_board_own_file_attachment_max_bytes($pdo, (int) $board['id'], $settings);
    $board['file_attachment_max_count'] = sr_community_board_own_file_attachment_max_count($pdo, (int) $board['id'], $settings);
    $defaultFileAttachmentMaxBytes = min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880)));
    $effectiveFileAttachmentMaxBytes = sr_community_effective_board_setting($pdo, $board, 'file_attachment_max_bytes', (string) $defaultFileAttachmentMaxBytes);
    $board['effective_file_attachment_max_bytes'] = min(20971520, max(1024, (int) $effectiveFileAttachmentMaxBytes));
    $defaultFileAttachmentMaxCount = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
    $effectiveFileAttachmentMaxCount = sr_community_effective_board_setting($pdo, $board, 'file_attachment_max_count', (string) $defaultFileAttachmentMaxCount);
    $board['effective_file_attachment_max_count'] = min(5, max(0, (int) $effectiveFileAttachmentMaxCount));
    $board['file_allowed_extensions'] = sr_community_board_own_file_allowed_extensions($pdo, (int) $board['id'], $settings);
    $defaultFileAllowedExtensions = sr_community_normalize_file_extensions(is_array($settings['file_allowed_extensions'] ?? null) ? $settings['file_allowed_extensions'] : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp']);
    $effectiveFileAllowedExtensions = sr_community_effective_board_setting($pdo, $board, 'file_allowed_extensions', implode(',', $defaultFileAllowedExtensions));
    $board['effective_file_allowed_extensions'] = trim($effectiveFileAllowedExtensions) === ''
        ? $defaultFileAllowedExtensions
        : sr_community_normalize_file_extensions(preg_split('/[\s,]+/', $effectiveFileAllowedExtensions) ?: []);
    $board['read_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'read_group_keys');
    $board['write_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'write_group_keys');
    $board['comment_group_keys'] = sr_community_board_own_group_keys($pdo, (int) $board['id'], 'comment_group_keys');
    $board['effective_read_group_keys'] = sr_community_board_group_keys_from_effective_setting($pdo, $board, 'read_group_keys');
    $board['effective_write_group_keys'] = sr_community_board_group_keys_from_effective_setting($pdo, $board, 'write_group_keys');
    $board['effective_comment_group_keys'] = sr_community_board_group_keys_from_effective_setting($pdo, $board, 'comment_group_keys');
    $board['read_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'read_min_level');
    $board['write_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'write_min_level');
    $board['comment_min_level'] = sr_community_board_own_min_level($pdo, (int) $board['id'], 'comment_min_level');
    $board['category_enabled'] = sr_community_board_category_enabled($pdo, (int) $board['id']) ? '1' : '0';
    $board['category_required'] = sr_community_board_category_required($pdo, (int) $board['id']) ? '1' : '0';
    $board['series_enabled'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'series_enabled') ?? (!empty($settings['series_enabled']) ? '1' : '0');
    $board['effective_series_enabled'] = sr_community_effective_board_series_enabled($pdo, $board, $settings) ? '1' : '0';
    $board['effective_read_min_level'] = sr_community_normalize_level_value(sr_community_effective_board_setting($pdo, $board, 'read_min_level', '0'), $settings);
    $board['effective_write_min_level'] = sr_community_normalize_level_value(sr_community_effective_board_setting($pdo, $board, 'write_min_level', '0'), $settings);
    $board['effective_comment_min_level'] = sr_community_normalize_level_value(sr_community_effective_board_setting($pdo, $board, 'comment_min_level', '0'), $settings);
    $board['categories'] = is_array($categoriesByBoardId[$boardId] ?? null) ? $categoriesByBoardId[$boardId] : sr_community_categories($pdo, $boardId, false);
    $board['secret_posts_enabled'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'secret_posts_enabled') ?? (!empty($settings['secret_posts_enabled']) ? '1' : '0');
    $board['secret_comments_enabled'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'secret_comments_enabled') ?? (!empty($settings['secret_comments_enabled']) ? '1' : '0');
    $board['post_edit_lock_comment_count'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'post_edit_lock_comment_count') ?? '0';
    $board['post_delete_lock_comment_count'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'post_delete_lock_comment_count') ?? '0';
    $board['post_body_min_length'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'post_body_min_length') ?? (string) sr_community_post_body_length_setting($settings['post_body_min_length'] ?? 0);
    $board['post_body_max_length'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'post_body_max_length') ?? (string) sr_community_post_body_length_setting($settings['post_body_max_length'] ?? 0);
    $board['comment_body_min_length'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'comment_body_min_length') ?? '0';
    $board['comment_body_max_length'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'comment_body_max_length') ?? '0';
    $board['comments_per_page'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'comments_per_page') ?? '0';
    $board['list_excerpt_enabled'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'list_excerpt_enabled') ?? '0';
    $board['list_excerpt_length'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'list_excerpt_length') ?? '120';
    $board['list_per_page'] = sr_community_board_setting_value($pdo, (int) $board['id'], 'list_per_page') ?? (string) min(100, max(1, (int) ($settings['posts_per_page'] ?? 20)));
    $listDefaultSort = sr_community_board_setting_value($pdo, (int) $board['id'], 'list_default_sort') ?? 'latest';
    $board['list_default_sort'] = in_array($listDefaultSort, ['latest', 'oldest', 'views', 'comments'], true) ? $listDefaultSort : 'latest';
    $summaryFeedSetting = sr_community_board_setting_value($pdo, (int) $board['id'], 'summary_feed_enabled');
    if (!is_string($summaryFeedSetting)) {
        $summaryFeedSetting = sr_community_board_setting_value($pdo, (int) $board['id'], 'home_feed_enabled');
    }
    $board['summary_feed_enabled'] = $summaryFeedSetting ?? '1';
    $board['effective_summary_feed_enabled'] = sr_community_effective_board_summary_feed_enabled($pdo, $board) ? '1' : '0';
    $board['board_sidebar_menu_type'] = sr_community_board_sidebar_menu_type((string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'board_sidebar_menu_type') ?? ($settings['board_sidebar_menu_type'] ?? 'all_boards')));
    $board['board_sidebar_site_menu_key'] = sr_community_board_sidebar_site_menu_key((string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'board_sidebar_site_menu_key') ?? ($settings['board_sidebar_site_menu_key'] ?? '')));
    $board['level_post_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['level_comment_score'] = sr_community_board_own_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['effective_level_post_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_post_score', $settings);
    $board['effective_level_comment_score'] = sr_community_board_level_score($pdo, (int) $board['id'], 'level_comment_score', $settings);
    $board['skin_key'] = sr_community_skin_key(['skin_key' => (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'skin_key') ?? 'basic')]);
    $board['post_editor'] = sr_community_post_editor_key((string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'post_editor') ?? ($settings['post_editor'] ?? 'textarea')));
    $board['effective_post_editor'] = sr_community_effective_post_editor($pdo, $board, $settings);
    $board['reaction_enabled'] = sr_community_effective_board_setting($pdo, $board, 'reaction_enabled', !empty($settings['reaction_enabled']) ? '1' : '0');
    $board['reaction_post_preset_key'] = (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'reaction_post_preset_key') ?? '');
    $board['reaction_comment_preset_key'] = (string) (sr_community_board_setting_value($pdo, (int) $board['id'], 'reaction_comment_preset_key') ?? '');
    foreach (sr_community_privacy_consent_setting_keys() as $privacyConsentSettingKey) {
        $defaultValue = match ($privacyConsentSettingKey) {
            'privacy_consent_title' => '개인정보 수집 및 이용동의',
            'privacy_consent_version' => '1',
            'privacy_consent_document_key' => 'community_privacy_default',
            'privacy_consent_document_inherit_policy' => 'inherit',
            default => '0',
        };
        $board[$privacyConsentSettingKey] = sr_community_effective_board_setting($pdo, $board, (string) $privacyConsentSettingKey, $defaultValue);
    }
    $storedExtraFieldDefinitions = sr_community_board_setting_source($pdo, (int) $board['id'], 'extra_fields_json') === 'board'
        ? sr_community_extra_field_definitions_from_storage($pdo, (int) $board['id'])
        : [];
    $board['extra_fields_json'] = $storedExtraFieldDefinitions !== []
        ? json_encode($storedExtraFieldDefinitions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        : sr_community_effective_board_setting($pdo, $board, 'extra_fields_json', '[]');
    foreach (sr_community_asset_setting_keys() as $assetSettingKey) {
        $board['source_' . $assetSettingKey] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], (string) $assetSettingKey);
    }
    foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
        $board[$assetPrefix . '_enabled'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_enabled', !empty($settings[$assetPrefix . '_enabled']) ? '1' : '0');
        $board[$assetPrefix . '_asset_module'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_asset_module', (string) ($settings[$assetPrefix . '_asset_module'] ?? ''));
        $board[$assetPrefix . '_amount'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_amount', (string) ($settings[$assetPrefix . '_amount'] ?? 0));
        if (in_array($assetPrefix, sr_community_asset_composite_prefixes(), true)) {
            $board[$assetPrefix . '_settlement_currency'] = sr_community_asset_settlement_currency($pdo, [
                'asset_settlement_currency' => sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_settlement_currency', (string) ($settings[$assetPrefix . '_settlement_currency'] ?? '')),
            ]);
        }
        $board[$assetPrefix . '_group_policies_json'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_group_policies_json', (string) ($settings[$assetPrefix . '_group_policies_json'] ?? ''));
        $board[$assetPrefix . '_policy_set_id'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_policy_set_id', (string) ($settings[$assetPrefix . '_policy_set_id'] ?? 0));
        if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
            $board[$assetPrefix . '_amounts_json'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_amounts_json', (string) ($settings[$assetPrefix . '_amounts_json'] ?? ''));
        }
        if (in_array($assetPrefix, ['paid_read', 'paid_attachment_download'], true)) {
            $board[$assetPrefix . '_charge_policy'] = sr_community_asset_board_setting($pdo, $board, $settings, $assetPrefix . '_charge_policy', (string) ($settings[$assetPrefix . '_charge_policy'] ?? 'once'));
        }
    }
    $board['paid_attachment_download_publisher_reward_enabled'] = sr_community_asset_board_setting($pdo, $board, $settings, 'paid_attachment_download_publisher_reward_enabled', !empty($settings['paid_attachment_download_publisher_reward_enabled']) ? '1' : '0');
    $board['paid_attachment_download_publisher_reward_rate'] = sr_community_asset_board_setting($pdo, $board, $settings, 'paid_attachment_download_publisher_reward_rate', (string) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0));
    $board['source_paid_attachment_download_publisher_reward_enabled'] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], 'paid_attachment_download_publisher_reward_enabled');
    $board['source_paid_attachment_download_publisher_reward_rate'] = sr_community_board_asset_setting_key_source($pdo, (int) $board['id'], 'paid_attachment_download_publisher_reward_rate');

    return $board;
}

function sr_community_create_board(PDO $pdo, array $data): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_boards
            (board_group_id, board_key, title, description, status, read_policy, write_policy, comment_policy, image_uploads_enabled, sort_order, created_at, updated_at)
         VALUES
            (:board_group_id, :board_key, :title, :description, :status, :read_policy, :write_policy, :comment_policy, :image_uploads_enabled, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'board_group_id' => (int) ($data['board_group_id'] ?? 0) > 0 ? (int) $data['board_group_id'] : null,
        'board_key' => (string) $data['board_key'],
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'read_policy' => (string) $data['read_policy'],
        'write_policy' => (string) $data['write_policy'],
        'comment_policy' => (string) $data['comment_policy'],
        'image_uploads_enabled' => !empty($data['image_uploads_enabled']) ? 1 : 0,
        'sort_order' => (int) $data['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    sr_community_enabled_boards_file_cache_mark_stale();

    return (int) $pdo->lastInsertId();
}

function sr_community_update_board(PDO $pdo, int $boardId, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_community_boards
         SET board_group_id = :board_group_id,
             title = :title,
             description = :description,
             status = :status,
             read_policy = :read_policy,
             write_policy = :write_policy,
             comment_policy = :comment_policy,
             image_uploads_enabled = :image_uploads_enabled,
             sort_order = :sort_order,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'board_group_id' => (int) ($data['board_group_id'] ?? 0) > 0 ? (int) $data['board_group_id'] : null,
        'title' => (string) $data['title'],
        'description' => (string) $data['description'],
        'status' => (string) $data['status'],
        'read_policy' => (string) $data['read_policy'],
        'write_policy' => (string) $data['write_policy'],
        'comment_policy' => (string) $data['comment_policy'],
        'image_uploads_enabled' => !empty($data['image_uploads_enabled']) ? 1 : 0,
        'sort_order' => (int) $data['sort_order'],
        'updated_at' => sr_now(),
        'id' => $boardId,
    ]);
    sr_community_enabled_boards_file_cache_mark_stale();
    if (function_exists('sr_community_mark_board_post_embed_targets_stale')) {
        sr_community_mark_board_post_embed_targets_stale($pdo, $boardId);
    }
    if (function_exists('sr_community_feed_cache_mark_all_stale')) {
        sr_community_feed_cache_mark_all_stale($pdo, 'board_changed');
    }
}
function sr_community_board_setting_value(PDO $pdo, int $boardId, string $settingKey): ?string
{
    if ($boardId < 1 || $settingKey === '') {
        return null;
    }

    if (sr_community_board_settings_runtime_cache_enabled()) {
        $settings = sr_community_board_settings_map($pdo, $boardId);
        if (array_key_exists($settingKey, $settings)) {
            return $settings[$settingKey];
        }

        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT setting_value
         FROM sr_community_board_settings
         WHERE board_id = :board_id
           AND setting_key = :setting_key
         LIMIT 1'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
    ]);
    $value = $stmt->fetchColumn();

    return is_string($value) ? $value : null;
}

function sr_community_summary_feed_candidate_value_for_board(PDO $pdo, int $boardId): int
{
    if ($boardId < 1) {
        return 1;
    }

    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return 1;
    }

    return sr_community_effective_board_summary_feed_enabled($pdo, $board) ? 1 : 0;
}

function sr_community_sync_board_summary_feed_candidates(PDO $pdo, int $boardId, bool $summaryFeedEnabled): void
{
    if ($boardId < 1) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_posts
         SET summary_feed_candidate = :summary_feed_candidate
         WHERE board_id = :board_id
           AND summary_feed_candidate <> :summary_feed_candidate_check'
    );
    $candidate = $summaryFeedEnabled ? 1 : 0;
    $stmt->execute([
        'summary_feed_candidate' => $candidate,
        'board_id' => $boardId,
        'summary_feed_candidate_check' => $candidate,
    ]);
}

function sr_community_board_settings_runtime_cache_enabled(): bool
{
    return !empty($GLOBALS['sr_community_board_settings_runtime_cache_enabled']);
}

function sr_community_use_board_settings_runtime_cache(bool $enabled = true): void
{
    $GLOBALS['sr_community_board_settings_runtime_cache_enabled'] = $enabled;
    if (!$enabled) {
        sr_community_clear_board_settings_runtime_cache();
    }
}

function sr_community_preload_board_settings_runtime_cache(PDO $pdo, array $boardIds): void
{
    if (!sr_community_board_settings_runtime_cache_enabled()) {
        return;
    }

    $ids = [];
    foreach ($boardIds as $boardId) {
        $boardId = (int) $boardId;
        if ($boardId > 0) {
            $ids[$boardId] = $boardId;
        }
    }
    if ($ids === []) {
        return;
    }

    $settingsCache = $GLOBALS['sr_community_board_settings_runtime_cache'] ?? [];
    if (!is_array($settingsCache)) {
        $settingsCache = [];
    }
    $sourcesCache = $GLOBALS['sr_community_board_setting_sources_runtime_cache'] ?? [];
    if (!is_array($sourcesCache)) {
        $sourcesCache = [];
    }

    $missingSettingIds = [];
    $missingSourceIds = [];
    foreach ($ids as $boardId) {
        if (!array_key_exists($boardId, $settingsCache)) {
            $settingsCache[$boardId] = [];
            $missingSettingIds[] = $boardId;
        }
        if (!array_key_exists($boardId, $sourcesCache)) {
            $sourcesCache[$boardId] = [];
            $missingSourceIds[] = $boardId;
        }
    }

    if ($missingSettingIds !== []) {
        $placeholders = [];
        $params = [];
        foreach ($missingSettingIds as $index => $boardId) {
            $paramKey = 'board_id_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $boardId;
        }
        $stmt = $pdo->prepare(
            'SELECT board_id, setting_key, setting_value
             FROM sr_community_board_settings
             WHERE board_id IN (' . implode(', ', $placeholders) . ')'
        );
        foreach ($params as $paramKey => $boardId) {
            $stmt->bindValue($paramKey, $boardId, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $boardId = (int) ($row['board_id'] ?? 0);
            $settingKey = (string) ($row['setting_key'] ?? '');
            if ($boardId > 0 && $settingKey !== '') {
                $settingsCache[$boardId][$settingKey] = (string) ($row['setting_value'] ?? '');
            }
        }
    }

    if ($missingSourceIds !== []) {
        $placeholders = [];
        $params = [];
        foreach ($missingSourceIds as $index => $boardId) {
            $paramKey = 'source_board_id_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = $boardId;
        }
        $stmt = $pdo->prepare(
            'SELECT board_id, setting_key, source
             FROM sr_community_board_setting_sources
             WHERE board_id IN (' . implode(', ', $placeholders) . ')'
        );
        foreach ($params as $paramKey => $boardId) {
            $stmt->bindValue($paramKey, $boardId, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll() as $row) {
            $boardId = (int) ($row['board_id'] ?? 0);
            $settingKey = (string) ($row['setting_key'] ?? '');
            if ($boardId > 0 && in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
                $sourcesCache[$boardId][$settingKey] = sr_community_normalize_board_setting_source((string) ($row['source'] ?? 'board'));
            }
        }
    }

    $GLOBALS['sr_community_board_settings_runtime_cache'] = $settingsCache;
    $GLOBALS['sr_community_board_setting_sources_runtime_cache'] = $sourcesCache;
}

function sr_community_board_settings_map(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return [];
    }

    $cache = $GLOBALS['sr_community_board_settings_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }

    if (array_key_exists($boardId, $cache) && is_array($cache[$boardId])) {
        return $cache[$boardId];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, setting_value
         FROM sr_community_board_settings
         WHERE board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $key = (string) ($row['setting_key'] ?? '');
        if ($key !== '') {
            $settings[$key] = (string) ($row['setting_value'] ?? '');
        }
    }

    $cache[$boardId] = $settings;
    $GLOBALS['sr_community_board_settings_runtime_cache'] = $cache;

    return $settings;
}

function sr_community_clear_board_settings_runtime_cache(int $boardId = 0): void
{
    if ($boardId > 0) {
        if (isset($GLOBALS['sr_community_board_settings_runtime_cache']) && is_array($GLOBALS['sr_community_board_settings_runtime_cache'])) {
            unset($GLOBALS['sr_community_board_settings_runtime_cache'][$boardId]);
        }
        if (isset($GLOBALS['sr_community_board_setting_sources_runtime_cache']) && is_array($GLOBALS['sr_community_board_setting_sources_runtime_cache'])) {
            unset($GLOBALS['sr_community_board_setting_sources_runtime_cache'][$boardId]);
        }
        return;
    }

    unset($GLOBALS['sr_community_board_settings_runtime_cache'], $GLOBALS['sr_community_board_setting_sources_runtime_cache']);
}

function sr_community_set_board_setting(PDO $pdo, int $boardId, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if ($boardId < 1 || $settingKey === '') {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_settings
            (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:board_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_community_clear_board_settings_runtime_cache($boardId);
    sr_community_enabled_boards_file_cache_mark_stale();
}

function sr_community_set_board_group_setting(PDO $pdo, int $groupId, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    if ($groupId < 1 || !in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_group_settings
            (group_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:group_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'group_id' => $groupId,
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_community_clear_board_settings_runtime_cache();
    sr_community_enabled_boards_file_cache_mark_stale();
}

function sr_community_board_group_settings(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, setting_value
         FROM sr_community_board_group_settings
         WHERE group_id = :group_id'
    );
    $stmt->execute(['group_id' => $groupId]);

    $settings = [];
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
    }

    return $settings;
}

function sr_community_board_setting_source(PDO $pdo, int $boardId, string $settingKey): string
{
    if ($boardId < 1 || !in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
        return 'board';
    }

    $sources = sr_community_board_setting_sources($pdo, $boardId);
    $source = (string) ($sources[$settingKey] ?? 'board');

    return sr_community_normalize_board_setting_source($source);
}

function sr_community_board_setting_sources(PDO $pdo, int $boardId): array
{
    if ($boardId < 1) {
        return [];
    }

    if (!sr_community_board_settings_runtime_cache_enabled()) {
        $stmt = $pdo->prepare(
            'SELECT setting_key, source
             FROM sr_community_board_setting_sources
             WHERE board_id = :board_id'
        );
        $stmt->execute(['board_id' => $boardId]);

        $sources = [];
        foreach ($stmt->fetchAll() as $row) {
            $settingKey = (string) ($row['setting_key'] ?? '');
            if (in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
                $sources[$settingKey] = sr_community_normalize_board_setting_source((string) ($row['source'] ?? 'board'));
            }
        }

        return $sources;
    }

    $cache = $GLOBALS['sr_community_board_setting_sources_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }

    if (array_key_exists($boardId, $cache) && is_array($cache[$boardId])) {
        return $cache[$boardId];
    }

    $stmt = $pdo->prepare(
        'SELECT setting_key, source
         FROM sr_community_board_setting_sources
         WHERE board_id = :board_id'
    );
    $stmt->execute(['board_id' => $boardId]);

    $sources = [];
    foreach ($stmt->fetchAll() as $row) {
        $settingKey = (string) ($row['setting_key'] ?? '');
        if (in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
            $sources[$settingKey] = sr_community_normalize_board_setting_source((string) ($row['source'] ?? 'board'));
        }
    }

    $cache[$boardId] = $sources;
    $GLOBALS['sr_community_board_setting_sources_runtime_cache'] = $cache;

    return $sources;
}

function sr_community_set_board_setting_source(PDO $pdo, int $boardId, string $settingKey, string $source): void
{
    if ($boardId < 1 || !in_array($settingKey, sr_community_board_group_all_setting_keys(), true)) {
        return;
    }

    $source = sr_community_normalize_board_setting_source($source);
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_board_setting_sources
            (board_id, setting_key, source, created_at, updated_at)
         VALUES
            (:board_id, :setting_key, :source, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            source = VALUES(source),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'board_id' => $boardId,
        'setting_key' => $settingKey,
        'source' => $source,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_community_clear_board_settings_runtime_cache($boardId);
    sr_community_enabled_boards_file_cache_mark_stale();
}

function sr_community_effective_board_setting(PDO $pdo, array $board, string $settingKey, mixed $default = ''): string
{
    $boardId = (int) ($board['id'] ?? 0);
    if ($boardId < 1 || !in_array($settingKey, sr_community_board_group_setting_keys(), true)) {
        return (string) $default;
    }

    if (in_array($settingKey, sr_community_board_group_column_setting_keys(), true)) {
        return (string) ($board[$settingKey] ?? $default);
    }

    $boardValue = sr_community_board_setting_value($pdo, $boardId, $settingKey);
    if (is_string($boardValue) && $boardValue !== '') {
        return $boardValue;
    }

    return (string) $default;
}

function sr_community_board_sidebar_menu_context(PDO $pdo, array $board, ?array $account, array $settings = []): array
{
    $settings = $settings !== [] ? $settings : sr_community_settings($pdo);
    $menuType = sr_community_board_sidebar_menu_type(sr_community_effective_board_setting(
        $pdo,
        $board,
        'board_sidebar_menu_type',
        (string) ($settings['board_sidebar_menu_type'] ?? 'all_boards')
    ));
    $siteMenuKey = sr_community_board_sidebar_site_menu_key(sr_community_effective_board_setting(
        $pdo,
        $board,
        'board_sidebar_site_menu_key',
        (string) ($settings['board_sidebar_site_menu_key'] ?? '')
    ));

    if ($menuType === 'none') {
        return ['type' => $menuType, 'title' => '', 'html' => ''];
    }

    if ($menuType === 'site_menu') {
        if ($siteMenuKey === '' || !sr_community_board_sidebar_site_menu_available($pdo)) {
            return ['type' => $menuType, 'title' => '', 'html' => ''];
        }
        require_once SR_ROOT . '/modules/site_menu/helpers.php';
        $siteMenuOptions = function_exists('sr_site_menu_options') ? sr_site_menu_options($pdo) : [];
        $siteMenu = is_array($siteMenuOptions[$siteMenuKey] ?? null) ? $siteMenuOptions[$siteMenuKey] : [];
        $menuTitle = trim((string) ($siteMenu['label'] ?? ''));
        $html = function_exists('sr_site_menu_render')
            ? sr_site_menu_render($pdo, $siteMenuKey, 'community_board_sidebar')
            : '';

        return ['type' => $menuType, 'title' => $menuTitle, 'html' => $html];
    }

    $currentBoardId = (int) ($board['id'] ?? 0);
    $currentBoardGroupId = (int) ($board['board_group_id'] ?? 0);
    $menuTitle = $menuType === 'same_group'
        ? (trim((string) ($board['board_group_title'] ?? '')) !== '' ? (string) $board['board_group_title'] : '그룹 없음')
        : '커뮤니티';
    $links = [];
    foreach (sr_community_enabled_boards($pdo) as $candidateBoard) {
        if ($menuType === 'same_group') {
            $candidateGroupId = (int) ($candidateBoard['board_group_id'] ?? 0);
            if ($currentBoardGroupId > 0 ? $candidateGroupId !== $currentBoardGroupId : (int) ($candidateBoard['id'] ?? 0) !== $currentBoardId) {
                continue;
            }
        }
        if (!sr_community_account_can_read_board($pdo, $candidateBoard, $account)) {
            continue;
        }

        $boardKey = (string) ($candidateBoard['board_key'] ?? '');
        if (!sr_community_board_key_is_valid($boardKey)) {
            continue;
        }
        $links[] = [
            'label' => trim((string) ($candidateBoard['title'] ?? '')) !== '' ? (string) $candidateBoard['title'] : $boardKey,
            'url' => sr_url('/community/board?key=' . rawurlencode($boardKey)),
            'current' => (int) ($candidateBoard['id'] ?? 0) === $currentBoardId,
        ];
    }

    if ($links === []) {
        return ['type' => $menuType, 'title' => $menuTitle, 'html' => ''];
    }

    $html = '<nav class="community-board-sidebar-nav" aria-label="게시판 사이드 메뉴"><ul>';
    foreach ($links as $link) {
        $html .= '<li><a href="' . sr_e((string) $link['url']) . '"' . (!empty($link['current']) ? ' aria-current="page"' : '') . '>'
            . sr_e((string) $link['label'])
            . '</a></li>';
    }
    $html .= '</ul></nav>';

    return ['type' => $menuType, 'title' => $menuTitle, 'html' => $html];
}

function sr_community_effective_board_policy(PDO $pdo, array $board, string $settingKey): string
{
    $policyType = str_replace('_policy', '', $settingKey);
    $fallback = (string) ($board[$settingKey] ?? '');
    $policy = sr_community_effective_board_setting($pdo, $board, $settingKey, $fallback);

    return in_array($policy, sr_community_policy_values($policyType), true) ? $policy : $fallback;
}

function sr_community_board_comments_per_page(PDO $pdo, array $board, array $settings = []): int
{
    $globalPerPage = min(100, max(1, (int) ($settings['comments_per_page'] ?? 20)));
    $boardPerPage = (int) sr_community_effective_board_setting($pdo, $board, 'comments_per_page', '0');

    return $boardPerPage >= 1 && $boardPerPage <= 100 ? $boardPerPage : $globalPerPage;
}

function sr_community_effective_board_image_uploads_enabled(PDO $pdo, array $board): bool
{
    return in_array(sr_community_effective_board_setting($pdo, $board, 'image_uploads_enabled', (string) (int) ($board['image_uploads_enabled'] ?? 1)), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_board_summary_feed_enabled(PDO $pdo, array $board): bool
{
    $value = sr_community_effective_board_setting($pdo, $board, 'summary_feed_enabled', '');
    if ($value === '') {
        $boardId = (int) ($board['id'] ?? 0);
        $legacyValue = $boardId > 0 ? sr_community_board_setting_value($pdo, $boardId, 'home_feed_enabled') : null;
        $value = is_string($legacyValue) && $legacyValue !== '' ? $legacyValue : '1';
    }

    return in_array($value, ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_board_file_uploads_enabled(PDO $pdo, array $board): bool
{
    return in_array(sr_community_effective_board_setting($pdo, $board, 'file_uploads_enabled', '0'), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_board_series_enabled(PDO $pdo, array $board, ?array $settings = null): bool
{
    if (!function_exists('sr_community_series_supported') || !sr_community_series_supported($pdo)) {
        return false;
    }

    $settings = is_array($settings) ? $settings : sr_community_settings($pdo);
    if (empty($settings['series_enabled'])) {
        return false;
    }

    return in_array(sr_community_effective_board_setting($pdo, $board, 'series_enabled', '1'), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_board_secret_posts_enabled(PDO $pdo, array $board, ?array $settings = null): bool
{
    $settings = is_array($settings) ? $settings : sr_community_settings($pdo);

    return in_array(sr_community_effective_board_setting($pdo, $board, 'secret_posts_enabled', !empty($settings['secret_posts_enabled']) ? '1' : '0'), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_board_secret_comments_enabled(PDO $pdo, array $board, ?array $settings = null): bool
{
    $settings = is_array($settings) ? $settings : sr_community_settings($pdo);

    return in_array(sr_community_effective_board_setting($pdo, $board, 'secret_comments_enabled', !empty($settings['secret_comments_enabled']) ? '1' : '0'), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_effective_board_reaction_enabled(PDO $pdo, ?array $board, ?array $settings = null): bool
{
    $settings = is_array($settings) ? $settings : sr_community_settings($pdo);
    if (!is_array($board)) {
        return !empty($settings['reaction_enabled']);
    }

    return in_array(sr_community_effective_board_setting($pdo, $board, 'reaction_enabled', !empty($settings['reaction_enabled']) ? '1' : '0'), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_board_min_level(PDO $pdo, int $boardId, string $settingKey): int
{
    if ($boardId < 1 || !in_array($settingKey, ['read_min_level', 'write_min_level', 'comment_min_level'], true)) {
        return 0;
    }

    $board = sr_community_board_by_id($pdo, $boardId);
    if (!is_array($board)) {
        return 0;
    }

    return sr_community_normalize_level_value(sr_community_effective_board_setting($pdo, $board, $settingKey, '0'), sr_community_settings($pdo));
}

function sr_community_board_own_min_level(PDO $pdo, int $boardId, string $settingKey): int
{
    if ($boardId < 1 || !in_array($settingKey, ['read_min_level', 'write_min_level', 'comment_min_level'], true)) {
        return 0;
    }

    $value = sr_community_board_setting_value($pdo, $boardId, $settingKey);
    return is_string($value) && $value !== '' ? sr_community_normalize_level_value($value, sr_community_settings($pdo)) : 0;
}

function sr_community_board_level_score(PDO $pdo, int $boardId, string $settingKey, array $settings = []): int
{
    if (!in_array($settingKey, ['level_post_score', 'level_comment_score'], true)) {
        return 0;
    }

    $defaultSettings = sr_community_default_settings();
    $default = min(10000, max(0, (int) ($defaultSettings[$settingKey] ?? ($settingKey === 'level_post_score' ? 10 : 2))));
    if ($boardId < 1) {
        return $default;
    }

    $value = sr_community_board_setting_value($pdo, $boardId, $settingKey);
    if (is_string($value) && $value !== '') {
        return min(10000, max(0, (int) $value));
    }

    return $default;
}

function sr_community_board_own_level_score(PDO $pdo, int $boardId, string $settingKey, array $settings = []): int
{
    if (!in_array($settingKey, ['level_post_score', 'level_comment_score'], true)) {
        return 0;
    }

    $defaultSettings = sr_community_default_settings();
    $default = min(10000, max(0, (int) ($defaultSettings[$settingKey] ?? ($settingKey === 'level_post_score' ? 10 : 2))));
    if ($boardId < 1) {
        return $default;
    }

    $value = sr_community_board_setting_value($pdo, $boardId, $settingKey);

    return is_string($value) && $value !== '' ? min(10000, max(0, (int) $value)) : $default;
}

function sr_community_board_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(10485760, max(1024, (int) ($defaultSettings['attachment_max_bytes'] ?? 2097152)));
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'attachment_max_bytes', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'attachment_max_bytes');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(10485760, max(1024, (int) $value));
}

function sr_community_board_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = 1;
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'attachment_max_count', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'attachment_max_count');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(10, max(0, (int) $value));
}

function sr_community_board_own_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(10485760, max(1024, (int) ($defaultSettings['attachment_max_bytes'] ?? 2097152)));
    $value = sr_community_board_setting_value($pdo, $boardId, 'attachment_max_bytes');
    return is_string($value) && $value !== '' ? min(10485760, max(1024, (int) $value)) : $default;
}

function sr_community_board_own_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $default = 1;
    $value = sr_community_board_setting_value($pdo, $boardId, 'attachment_max_count');
    return is_string($value) && $value !== '' ? min(10, max(0, (int) $value)) : $default;
}

function sr_community_board_file_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(20971520, max(1024, (int) ($defaultSettings['file_attachment_max_bytes'] ?? 5242880)));
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'file_attachment_max_bytes', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_bytes');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(20971520, max(1024, (int) $value));
}

function sr_community_board_file_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(5, max(0, (int) ($defaultSettings['file_attachment_max_count'] ?? 3)));
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'file_attachment_max_count', (string) $default)
        : sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_count');

    if (!is_string($value) || $value === '') {
        return $default;
    }

    return min(5, max(0, (int) $value));
}

function sr_community_board_own_file_attachment_max_bytes(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(20971520, max(1024, (int) ($defaultSettings['file_attachment_max_bytes'] ?? 5242880)));
    $value = sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_bytes');
    return is_string($value) && $value !== '' ? min(20971520, max(1024, (int) $value)) : $default;
}

function sr_community_board_own_file_attachment_max_count(PDO $pdo, int $boardId, array $settings = []): int
{
    $defaultSettings = sr_community_default_settings();
    $default = min(5, max(0, (int) ($defaultSettings['file_attachment_max_count'] ?? 3)));
    $value = sr_community_board_setting_value($pdo, $boardId, 'file_attachment_max_count');
    return is_string($value) && $value !== '' ? min(5, max(0, (int) $value)) : $default;
}

function sr_community_board_file_allowed_extensions(PDO $pdo, int $boardId, array $settings = []): array
{
    $defaultSettings = sr_community_default_settings();
    $default = sr_community_normalize_file_extensions(is_array($defaultSettings['file_allowed_extensions'] ?? null) ? $defaultSettings['file_allowed_extensions'] : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp']);
    $board = sr_community_board_by_id($pdo, $boardId);
    $value = is_array($board)
        ? sr_community_effective_board_setting($pdo, $board, 'file_allowed_extensions', implode(',', $default))
        : sr_community_board_setting_value($pdo, $boardId, 'file_allowed_extensions');

    if (!is_string($value) || trim($value) === '') {
        return $default;
    }

    return sr_community_normalize_file_extensions(preg_split('/[\s,]+/', $value) ?: []);
}

function sr_community_board_own_file_allowed_extensions(PDO $pdo, int $boardId, array $settings = []): array
{
    $defaultSettings = sr_community_default_settings();
    $default = sr_community_normalize_file_extensions(is_array($defaultSettings['file_allowed_extensions'] ?? null) ? $defaultSettings['file_allowed_extensions'] : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp']);
    $value = sr_community_board_setting_value($pdo, $boardId, 'file_allowed_extensions');
    if (!is_string($value) || trim($value) === '') {
        return $default;
    }

    return sr_community_normalize_file_extensions(preg_split('/[\s,]+/', $value) ?: []);
}

function sr_community_normalize_file_extensions(array $extensions): array
{
    $allowed = array_fill_keys(array_keys(sr_community_file_extension_mime_map()), true);
    $normalized = [];
    foreach ($extensions as $extension) {
        $extension = strtolower(ltrim(trim((string) $extension), '.'));
        if (isset($allowed[$extension])) {
            $normalized[$extension] = true;
        }
    }

    return array_keys($normalized);
}

function sr_community_file_extensions_from_input(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    $rawExtensions = preg_split('/[\s,]+/', $value);
    return sr_community_normalize_file_extensions(is_array($rawExtensions) ? $rawExtensions : []);
}

function sr_community_invalid_file_extensions_from_input(string $value): array
{
    if (trim($value) === '') {
        return [];
    }

    $allowed = array_fill_keys(array_keys(sr_community_file_extension_mime_map()), true);
    $invalid = [];
    $rawExtensions = preg_split('/[\s,]+/', $value);
    foreach (is_array($rawExtensions) ? $rawExtensions : [] as $rawExtension) {
        $extension = strtolower(ltrim(trim((string) $rawExtension), '.'));
        if ($extension !== '' && !isset($allowed[$extension])) {
            $invalid[] = $extension;
        }
    }

    return array_values(array_unique($invalid));
}
