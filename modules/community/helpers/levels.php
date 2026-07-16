<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/community/helpers/post-body-settings.php';
require_once SR_ROOT . '/modules/community/helpers/drafts.php';
require_once SR_ROOT . '/modules/community/helpers/posts-extra-fields.php';

function sr_community_default_settings(): array
{
    $metadata = sr_module_metadata('community');
    $settings = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];
    return [
        'posts_per_page' => (int) ($settings['posts_per_page'] ?? 20),
        'comments_per_page' => (int) ($settings['comments_per_page'] ?? 20),
        'post_create_window_seconds' => (int) ($settings['post_create_window_seconds'] ?? 300),
        'post_create_limit' => (int) ($settings['post_create_limit'] ?? 10),
        'comment_create_window_seconds' => (int) ($settings['comment_create_window_seconds'] ?? 300),
        'comment_create_limit' => (int) ($settings['comment_create_limit'] ?? 30),
        'report_create_window_seconds' => (int) ($settings['report_create_window_seconds'] ?? 300),
        'report_create_limit' => (int) ($settings['report_create_limit'] ?? 20),
        'report_auto_action_enabled' => (bool) ($settings['report_auto_action_enabled'] ?? false),
        'report_auto_action_threshold' => (int) ($settings['report_auto_action_threshold'] ?? 5),
        'report_auto_action_window_days' => (int) ($settings['report_auto_action_window_days'] ?? 0),
        'report_auto_action_public_mode' => is_string($settings['report_auto_action_public_mode'] ?? null) ? (string) $settings['report_auto_action_public_mode'] : 'exclude',
        'account_guard_publication_hold_enabled' => (bool) ($settings['account_guard_publication_hold_enabled'] ?? false),
        'account_guard_publication_hold_threshold' => (int) ($settings['account_guard_publication_hold_threshold'] ?? 3),
        'account_guard_publication_hold_overlap_review_percent' => (int) ($settings['account_guard_publication_hold_overlap_review_percent'] ?? 80),
        'account_guard_publication_hold_duration_minutes' => (int) ($settings['account_guard_publication_hold_duration_minutes'] ?? 120),
        'account_guard_confirmed_hold_enabled' => (bool) ($settings['account_guard_confirmed_hold_enabled'] ?? false),
        'account_guard_confirmed_hold_threshold' => (int) ($settings['account_guard_confirmed_hold_threshold'] ?? 3),
        'account_guard_confirmed_hold_window_days' => (int) ($settings['account_guard_confirmed_hold_window_days'] ?? 30),
        'account_guard_confirmed_hold_duration_minutes' => (int) ($settings['account_guard_confirmed_hold_duration_minutes'] ?? 1440),
        'attachment_max_bytes' => (int) ($settings['attachment_max_bytes'] ?? $settings['image_upload_max_bytes'] ?? 2097152),
        'image_uploads_enabled' => (bool) ($settings['image_uploads_enabled'] ?? true),
        'thumbnail_enabled' => (bool) ($settings['thumbnail_enabled'] ?? true),
        'thumbnail_criterion' => (string) ($settings['thumbnail_criterion'] ?? 'width'),
        'thumbnail_min_width' => (int) ($settings['thumbnail_min_width'] ?? 320),
        'thumbnail_min_bytes' => (int) ($settings['thumbnail_min_bytes'] ?? 102400),
        'file_uploads_enabled' => (bool) ($settings['file_uploads_enabled'] ?? false),
        'file_attachment_max_bytes' => (int) ($settings['file_attachment_max_bytes'] ?? 5242880),
        'file_attachment_max_count' => (int) ($settings['file_attachment_max_count'] ?? 3),
        'file_allowed_extensions' => is_array($settings['file_allowed_extensions'] ?? null)
            ? $settings['file_allowed_extensions']
            : ['pdf', 'txt', 'csv', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'hwp'],
        'level_enabled' => (bool) ($settings['level_enabled'] ?? false),
        'level_display_name' => is_string($settings['level_display_name'] ?? null) ? (string) $settings['level_display_name'] : '레벨',
        'level_short_label' => is_string($settings['level_short_label'] ?? null) ? (string) $settings['level_short_label'] : 'Lv.',
        'level_max_value' => (int) ($settings['level_max_value'] ?? 10),
        'level_auto_recalculate' => (bool) ($settings['level_auto_recalculate'] ?? false),
        'level_post_score' => (int) ($settings['level_post_score'] ?? 10),
        'level_comment_score' => (int) ($settings['level_comment_score'] ?? 2),
        'identity_restricted_board_required' => (bool) ($settings['identity_restricted_board_required'] ?? false),
        'layout_key' => is_string($settings['layout_key'] ?? null) ? (string) $settings['layout_key'] : '',
        'theme_key' => is_string($settings['theme_key'] ?? null) ? (string) $settings['theme_key'] : 'basic',
        'layout_primary_menu_key' => is_string($settings['layout_primary_menu_key'] ?? null) ? (string) $settings['layout_primary_menu_key'] : 'header',
        'board_sidebar_menu_type' => is_string($settings['board_sidebar_menu_type'] ?? null) ? (string) $settings['board_sidebar_menu_type'] : 'all_boards',
        'board_sidebar_site_menu_key' => is_string($settings['board_sidebar_site_menu_key'] ?? null) ? (string) $settings['board_sidebar_site_menu_key'] : '',
        'business_info_visible' => (bool) ($settings['business_info_visible'] ?? true),
        'layout_extra_menu_keys_json' => is_array($settings['layout_extra_menu_keys_json'] ?? null) || is_string($settings['layout_extra_menu_keys_json'] ?? null)
            ? ($settings['layout_extra_menu_keys_json'] ?? [])
            : [],
        'series_enabled' => (bool) ($settings['series_enabled'] ?? true),
        'draft_autosave_enabled' => (bool) ($settings['draft_autosave_enabled'] ?? false),
        'draft_autosave_interval_seconds' => (int) ($settings['draft_autosave_interval_seconds'] ?? 60),
        'draft_retention_days' => (int) ($settings['draft_retention_days'] ?? 7),
        'draft_max_count_per_account' => (int) ($settings['draft_max_count_per_account'] ?? 20),
        'post_editor' => is_string($settings['post_editor'] ?? null) ? (string) $settings['post_editor'] : 'textarea',
        'post_toolbar_preset' => is_string($settings['post_toolbar_preset'] ?? null) ? (string) $settings['post_toolbar_preset'] : 'community_post_basic',
        'post_body_min_length' => sr_community_post_body_length_setting($settings['post_body_min_length'] ?? 0),
        'post_body_max_length' => sr_community_post_body_length_setting($settings['post_body_max_length'] ?? 0),
        'external_embed_enabled' => (bool) ($settings['external_embed_enabled'] ?? $settings['embed_enabled'] ?? true),
        'internal_embed_enabled' => (bool) ($settings['internal_embed_enabled'] ?? $settings['embed_enabled'] ?? true),
        'plain_text_auto_link_urls' => (bool) ($settings['plain_text_auto_link_urls'] ?? false),
        'plain_text_auto_link_new_tab' => (bool) ($settings['plain_text_auto_link_new_tab'] ?? false),
        'secret_posts_enabled' => (bool) ($settings['secret_posts_enabled'] ?? false),
        'secret_comments_enabled' => (bool) ($settings['secret_comments_enabled'] ?? false),
        'extra_fields_json' => is_array($settings['extra_fields_json'] ?? null) || is_string($settings['extra_fields_json'] ?? null) ? ($settings['extra_fields_json'] ?? '[]') : '[]',
        'comment_extra_fields_json' => is_string($settings['comment_extra_fields_json'] ?? null) ? (string) $settings['comment_extra_fields_json'] : '[]',
        'privacy_consent_enabled' => (bool) ($settings['privacy_consent_enabled'] ?? false),
        'privacy_consent_document_key' => is_string($settings['privacy_consent_document_key'] ?? null) ? (string) $settings['privacy_consent_document_key'] : 'community_privacy_default',
        'privacy_consent_post_document_key' => is_string($settings['privacy_consent_post_document_key'] ?? null) ? (string) $settings['privacy_consent_post_document_key'] : '',
        'privacy_consent_comment_document_key' => is_string($settings['privacy_consent_comment_document_key'] ?? null) ? (string) $settings['privacy_consent_comment_document_key'] : '',
        'privacy_consent_attachment_upload_document_key' => is_string($settings['privacy_consent_attachment_upload_document_key'] ?? null) ? (string) $settings['privacy_consent_attachment_upload_document_key'] : '',
        'privacy_consent_document_inherit_policy' => is_string($settings['privacy_consent_document_inherit_policy'] ?? null) ? (string) $settings['privacy_consent_document_inherit_policy'] : 'override',
        'privacy_consent_title' => is_string($settings['privacy_consent_title'] ?? null) ? (string) $settings['privacy_consent_title'] : '개인정보 수집 및 이용동의',
        'privacy_consent_body' => is_string($settings['privacy_consent_body'] ?? null) ? (string) $settings['privacy_consent_body'] : '',
        'privacy_consent_version' => is_string($settings['privacy_consent_version'] ?? null) ? (string) $settings['privacy_consent_version'] : '1',
        'privacy_consent_require_post' => (bool) ($settings['privacy_consent_require_post'] ?? false),
        'privacy_consent_require_comment' => (bool) ($settings['privacy_consent_require_comment'] ?? false),
        'privacy_consent_require_attachment_upload' => (bool) ($settings['privacy_consent_require_attachment_upload'] ?? false),
        'reaction_enabled' => (bool) ($settings['reaction_enabled'] ?? true),
        'reaction_post_preset_key' => is_string($settings['reaction_post_preset_key'] ?? null) ? (string) $settings['reaction_post_preset_key'] : '',
        'reaction_comment_preset_key' => is_string($settings['reaction_comment_preset_key'] ?? null) ? (string) $settings['reaction_comment_preset_key'] : '',
        'post_reward_enabled' => (bool) ($settings['post_reward_enabled'] ?? false),
        'post_reward_asset_module' => is_string($settings['post_reward_asset_module'] ?? null) ? (string) $settings['post_reward_asset_module'] : '',
        'post_reward_amount' => (int) ($settings['post_reward_amount'] ?? 0),
        'post_reward_group_policies_json' => is_string($settings['post_reward_group_policies_json'] ?? null) ? (string) $settings['post_reward_group_policies_json'] : '',
        'post_reward_policy_set_id' => (int) ($settings['post_reward_policy_set_id'] ?? 0),
        'post_reward_reversal_enabled' => (bool) ($settings['post_reward_reversal_enabled'] ?? false),
        'comment_reward_enabled' => (bool) ($settings['comment_reward_enabled'] ?? false),
        'comment_reward_asset_module' => is_string($settings['comment_reward_asset_module'] ?? null) ? (string) $settings['comment_reward_asset_module'] : '',
        'comment_reward_amount' => (int) ($settings['comment_reward_amount'] ?? 0),
        'comment_reward_group_policies_json' => is_string($settings['comment_reward_group_policies_json'] ?? null) ? (string) $settings['comment_reward_group_policies_json'] : '',
        'comment_reward_policy_set_id' => (int) ($settings['comment_reward_policy_set_id'] ?? 0),
        'comment_reward_reversal_enabled' => (bool) ($settings['comment_reward_reversal_enabled'] ?? false),
        'write_charge_enabled' => (bool) ($settings['write_charge_enabled'] ?? false),
        'write_charge_asset_module' => is_string($settings['write_charge_asset_module'] ?? null) ? (string) $settings['write_charge_asset_module'] : '',
        'write_charge_amount' => (int) ($settings['write_charge_amount'] ?? 0),
        'write_charge_settlement_currency' => is_string($settings['write_charge_settlement_currency'] ?? null) ? (string) $settings['write_charge_settlement_currency'] : 'KRW',
        'write_charge_amounts_json' => is_string($settings['write_charge_amounts_json'] ?? null) ? (string) $settings['write_charge_amounts_json'] : '',
        'write_charge_group_policies_json' => is_string($settings['write_charge_group_policies_json'] ?? null) ? (string) $settings['write_charge_group_policies_json'] : '',
        'write_charge_policy_set_id' => (int) ($settings['write_charge_policy_set_id'] ?? 0),
        'comment_charge_enabled' => (bool) ($settings['comment_charge_enabled'] ?? false),
        'comment_charge_asset_module' => is_string($settings['comment_charge_asset_module'] ?? null) ? (string) $settings['comment_charge_asset_module'] : '',
        'comment_charge_amount' => (int) ($settings['comment_charge_amount'] ?? 0),
        'comment_charge_settlement_currency' => is_string($settings['comment_charge_settlement_currency'] ?? null) ? (string) $settings['comment_charge_settlement_currency'] : 'KRW',
        'comment_charge_amounts_json' => is_string($settings['comment_charge_amounts_json'] ?? null) ? (string) $settings['comment_charge_amounts_json'] : '',
        'comment_charge_group_policies_json' => is_string($settings['comment_charge_group_policies_json'] ?? null) ? (string) $settings['comment_charge_group_policies_json'] : '',
        'comment_charge_policy_set_id' => (int) ($settings['comment_charge_policy_set_id'] ?? 0),
        'paid_read_enabled' => (bool) ($settings['paid_read_enabled'] ?? false),
        'paid_read_asset_module' => is_string($settings['paid_read_asset_module'] ?? null) ? (string) $settings['paid_read_asset_module'] : '',
        'paid_read_amount' => (int) ($settings['paid_read_amount'] ?? 0),
        'paid_read_settlement_currency' => is_string($settings['paid_read_settlement_currency'] ?? null) ? (string) $settings['paid_read_settlement_currency'] : 'KRW',
        'paid_read_amounts_json' => is_string($settings['paid_read_amounts_json'] ?? null) ? (string) $settings['paid_read_amounts_json'] : '',
        'paid_read_group_policies_json' => is_string($settings['paid_read_group_policies_json'] ?? null) ? (string) $settings['paid_read_group_policies_json'] : '',
        'paid_read_policy_set_id' => (int) ($settings['paid_read_policy_set_id'] ?? 0),
        'paid_read_charge_policy' => is_string($settings['paid_read_charge_policy'] ?? null) ? (string) $settings['paid_read_charge_policy'] : 'once',
        'once_history_policy' => is_string($settings['once_history_policy'] ?? null) ? (string) $settings['once_history_policy'] : 'all_access',
        'paid_attachment_download_enabled' => (bool) ($settings['paid_attachment_download_enabled'] ?? false),
        'paid_attachment_download_asset_module' => is_string($settings['paid_attachment_download_asset_module'] ?? null) ? (string) $settings['paid_attachment_download_asset_module'] : '',
        'paid_attachment_download_amount' => (int) ($settings['paid_attachment_download_amount'] ?? 0),
        'paid_attachment_download_settlement_currency' => is_string($settings['paid_attachment_download_settlement_currency'] ?? null) ? (string) $settings['paid_attachment_download_settlement_currency'] : 'KRW',
        'paid_attachment_download_amounts_json' => is_string($settings['paid_attachment_download_amounts_json'] ?? null) ? (string) $settings['paid_attachment_download_amounts_json'] : '',
        'paid_attachment_download_group_policies_json' => is_string($settings['paid_attachment_download_group_policies_json'] ?? null) ? (string) $settings['paid_attachment_download_group_policies_json'] : '',
        'paid_attachment_download_policy_set_id' => (int) ($settings['paid_attachment_download_policy_set_id'] ?? 0),
        'paid_attachment_download_charge_policy' => is_string($settings['paid_attachment_download_charge_policy'] ?? null) ? (string) $settings['paid_attachment_download_charge_policy'] : 'once',
        'paid_attachment_download_publisher_reward_enabled' => (bool) ($settings['paid_attachment_download_publisher_reward_enabled'] ?? false),
        'paid_attachment_download_publisher_reward_rate' => (int) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0),
        'multi_asset_payment_enabled' => (bool) ($settings['multi_asset_payment_enabled'] ?? true),
    ];
}

function sr_community_layout_menu_slots(): array
{
    return [
        'primary' => 'layout_primary_menu_key',
    ];
}

function sr_community_level_max_setting(mixed $value): int
{
    return min(100, max(1, (int) $value));
}

function sr_community_max_level_value(?array $settings = null): int
{
    $settings = is_array($settings) ? $settings : sr_community_default_settings();

    return sr_community_level_max_setting($settings['level_max_value'] ?? 10);
}

function sr_community_normalize_level_value(mixed $value, ?array $settings = null): int
{
    return min(sr_community_max_level_value($settings), max(0, (int) $value));
}

function sr_community_level_text_setting(mixed $value, string $default, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    if (function_exists('mb_substr')) {
        $value = mb_substr($value, 0, $maxLength);
    } else {
        $value = substr($value, 0, $maxLength);
    }

    return $value !== '' ? $value : $default;
}

function sr_community_level_label(int $levelValue, ?array $settings = null, bool $preferShort = false): string
{
    $levelValue = sr_community_normalize_level_value($levelValue, $settings);
    $settings = is_array($settings) ? $settings : sr_community_default_settings();
    $displayName = sr_community_level_text_setting($settings['level_display_name'] ?? '레벨', '레벨', 40);
    $shortLabel = trim((string) ($settings['level_short_label'] ?? ''));
    if (function_exists('mb_substr')) {
        $shortLabel = mb_substr($shortLabel, 0, 20);
    } else {
        $shortLabel = substr($shortLabel, 0, 20);
    }
    $label = $preferShort && $shortLabel !== '' ? $shortLabel : $displayName;

    return trim($label . ' ' . number_format($levelValue));
}

function sr_community_level_label_for_value(PDO $pdo, int $levelValue, ?array $settings = null, bool $preferShort = false): string
{
    return sr_community_level_label($levelValue, $settings, $preferShort);
}

function sr_community_settings(PDO $pdo): array
{
    return sr_community_normalize_settings(sr_module_settings($pdo, 'community'), null, $pdo);
}

function sr_community_normalize_settings(array $settings, ?array $site = null, ?PDO $pdo = null): array
{
    $rawSettings = $settings;
    if (array_key_exists('embed_enabled', $settings)) {
        $settings['external_embed_enabled'] = $settings['external_embed_enabled'] ?? $settings['embed_enabled'];
        $settings['internal_embed_enabled'] = $settings['internal_embed_enabled'] ?? $settings['embed_enabled'];
    }
    $settings = array_merge(sr_community_default_settings(), $settings);
    $settings['posts_per_page'] = min(100, max(1, (int) ($settings['posts_per_page'] ?? 20)));
    $settings['comments_per_page'] = min(100, max(1, (int) ($settings['comments_per_page'] ?? 20)));
    $settings['post_create_window_seconds'] = min(86400, max(60, (int) ($settings['post_create_window_seconds'] ?? 300)));
    $settings['post_create_limit'] = min(100, max(1, (int) ($settings['post_create_limit'] ?? 10)));
    $settings['comment_create_window_seconds'] = min(86400, max(60, (int) ($settings['comment_create_window_seconds'] ?? 300)));
    $settings['comment_create_limit'] = min(300, max(1, (int) ($settings['comment_create_limit'] ?? 30)));
    $settings['report_create_window_seconds'] = min(86400, max(60, (int) ($settings['report_create_window_seconds'] ?? 300)));
    $settings['report_create_limit'] = min(200, max(1, (int) ($settings['report_create_limit'] ?? 20)));
    $settings['report_auto_action_enabled'] = sr_community_bool_setting($settings['report_auto_action_enabled'] ?? false);
    $settings['report_auto_action_threshold'] = min(100, max(2, (int) ($settings['report_auto_action_threshold'] ?? 5)));
    $settings['report_auto_action_window_days'] = min(365, max(0, (int) ($settings['report_auto_action_window_days'] ?? 0)));
    $reportAutoActionPublicMode = (string) ($settings['report_auto_action_public_mode'] ?? 'exclude');
    $settings['report_auto_action_public_mode'] = in_array($reportAutoActionPublicMode, ['exclude', 'placeholder'], true)
        ? $reportAutoActionPublicMode
        : 'exclude';
    $settings['account_guard_publication_hold_enabled'] = sr_community_bool_setting($settings['account_guard_publication_hold_enabled'] ?? false);
    $settings['account_guard_publication_hold_threshold'] = min(20, max(2, (int) ($settings['account_guard_publication_hold_threshold'] ?? 3)));
    $settings['account_guard_publication_hold_overlap_review_percent'] = min(100, max(0, (int) ($settings['account_guard_publication_hold_overlap_review_percent'] ?? 80)));
    $settings['account_guard_publication_hold_duration_minutes'] = min(10080, max(10, (int) ($settings['account_guard_publication_hold_duration_minutes'] ?? 120)));
    $settings['account_guard_confirmed_hold_enabled'] = sr_community_bool_setting($settings['account_guard_confirmed_hold_enabled'] ?? false);
    $settings['account_guard_confirmed_hold_threshold'] = min(20, max(2, (int) ($settings['account_guard_confirmed_hold_threshold'] ?? 3)));
    $settings['account_guard_confirmed_hold_window_days'] = min(365, max(1, (int) ($settings['account_guard_confirmed_hold_window_days'] ?? 30)));
    $settings['account_guard_confirmed_hold_duration_minutes'] = min(10080, max(10, (int) ($settings['account_guard_confirmed_hold_duration_minutes'] ?? 1440)));
    $settings['attachment_max_bytes'] = min(10485760, max(1024, (int) ($settings['attachment_max_bytes'] ?? 2097152)));
    $settings['image_uploads_enabled'] = sr_community_bool_setting($settings['image_uploads_enabled'] ?? true);
    $settings['thumbnail_enabled'] = sr_community_bool_setting($settings['thumbnail_enabled'] ?? true);
    $thumbnailCriterion = (string) ($settings['thumbnail_criterion'] ?? 'width');
    $settings['thumbnail_criterion'] = in_array($thumbnailCriterion, ['width', 'bytes'], true) ? $thumbnailCriterion : 'width';
    $settings['thumbnail_min_width'] = min(4000, max(1, (int) ($settings['thumbnail_min_width'] ?? 320)));
    $settings['thumbnail_min_bytes'] = min(20971520, max(0, (int) ($settings['thumbnail_min_bytes'] ?? 102400)));
    $settings['file_uploads_enabled'] = sr_community_bool_setting($settings['file_uploads_enabled'] ?? false);
    $settings['file_attachment_max_bytes'] = min(20971520, max(1024, (int) ($settings['file_attachment_max_bytes'] ?? 5242880)));
    $settings['file_attachment_max_count'] = min(5, max(0, (int) ($settings['file_attachment_max_count'] ?? 3)));
    $settings['file_allowed_extensions'] = sr_community_normalize_file_extensions(
        is_array($settings['file_allowed_extensions'] ?? null) ? $settings['file_allowed_extensions'] : (string) ($settings['file_allowed_extensions'] ?? '')
    );
    $settings['level_enabled'] = sr_community_bool_setting($settings['level_enabled'] ?? false);
    $settings['level_display_name'] = sr_community_level_text_setting($settings['level_display_name'] ?? '레벨', '레벨', 40);
    $settings['level_short_label'] = sr_community_level_text_setting($settings['level_short_label'] ?? 'Lv.', '', 20);
    $settings['level_max_value'] = sr_community_level_max_setting($settings['level_max_value'] ?? 10);
    $settings['level_auto_recalculate'] = sr_community_bool_setting($settings['level_auto_recalculate'] ?? false);
    $settings['level_post_score'] = min(10000, max(0, (int) ($settings['level_post_score'] ?? 10)));
    $settings['level_comment_score'] = min(10000, max(0, (int) ($settings['level_comment_score'] ?? 2)));
    $settings['identity_restricted_board_required'] = sr_community_bool_setting($settings['identity_restricted_board_required'] ?? false);
    $settings['once_history_policy'] = sr_community_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
    $settings['layout_key'] = sr_community_layout_key($settings, $site, $pdo);
    $settings['theme_key'] = sr_community_theme_key((string) ($settings['theme_key'] ?? 'basic'));
    if (!isset(sr_community_theme_options()[$settings['theme_key']])) {
        $settings['theme_key'] = sr_community_theme_key('');
    }
    foreach (sr_community_layout_menu_slots() as $settingKey) {
        $settings[$settingKey] = sr_community_clean_layout_menu_key((string) ($settings[$settingKey] ?? ''));
    }
    $settings['board_sidebar_menu_type'] = sr_community_board_sidebar_menu_type((string) ($settings['board_sidebar_menu_type'] ?? 'all_boards'));
    $settings['board_sidebar_site_menu_key'] = sr_community_board_sidebar_site_menu_key((string) ($settings['board_sidebar_site_menu_key'] ?? ''));
    $settings['layout_extra_menu_keys_json'] = sr_community_layout_extra_menu_items_from_settings($settings);
    $settings['business_info_visible'] = sr_community_bool_setting($settings['business_info_visible'] ?? true);
    $settings['series_enabled'] = sr_community_bool_setting($settings['series_enabled'] ?? true);
    $settings['draft_autosave_enabled'] = sr_community_bool_setting($settings['draft_autosave_enabled'] ?? false);
    $settings['draft_autosave_interval_seconds'] = sr_community_draft_autosave_interval_seconds($settings);
    $settings['draft_retention_days'] = sr_community_draft_retention_days($settings);
    $settings['draft_max_count_per_account'] = sr_community_draft_max_count_per_account($settings);
    $settings['post_editor'] = sr_editor_normalize_key((string) ($settings['post_editor'] ?? 'textarea'));
    $settings['post_toolbar_preset'] = sr_community_post_toolbar_preset_key((string) ($settings['post_toolbar_preset'] ?? 'community_post_basic'));
    $settings['post_body_min_length'] = sr_community_post_body_length_setting($settings['post_body_min_length'] ?? 0);
    $settings['post_body_max_length'] = sr_community_post_body_length_setting($settings['post_body_max_length'] ?? 0);
    $settings['external_embed_enabled'] = sr_community_bool_setting($settings['external_embed_enabled'] ?? true);
    $settings['internal_embed_enabled'] = sr_community_bool_setting($settings['internal_embed_enabled'] ?? true);
    unset($settings['embed_enabled']);
    $settings['plain_text_auto_link_urls'] = sr_community_bool_setting($settings['plain_text_auto_link_urls'] ?? false);
    $settings['plain_text_auto_link_new_tab'] = sr_community_bool_setting($settings['plain_text_auto_link_new_tab'] ?? false);
    $settings['secret_posts_enabled'] = sr_community_bool_setting($settings['secret_posts_enabled'] ?? false);
    $settings['secret_comments_enabled'] = sr_community_bool_setting($settings['secret_comments_enabled'] ?? false);
    $settings['extra_fields_json'] = sr_community_extra_field_definitions_json($settings['extra_fields_json'] ?? '[]');
    $settings['comment_extra_fields_json'] = sr_comment_extra_field_definitions_json($settings['comment_extra_fields_json'] ?? '[]');
    $settings['privacy_consent_enabled'] = sr_community_bool_setting($settings['privacy_consent_enabled'] ?? false);
    $settings['privacy_consent_document_key'] = preg_match('/\A[a-z][a-z0-9_]{2,79}\z/', (string) ($settings['privacy_consent_document_key'] ?? 'community_privacy_default')) === 1
        ? (string) $settings['privacy_consent_document_key']
        : 'community_privacy_default';
    foreach (sr_community_privacy_consent_target_keys() as $privacyConsentTargetKey) {
        $privacyConsentDocumentSettingKey = sr_community_privacy_consent_document_setting_key($privacyConsentTargetKey);
        $settings[$privacyConsentDocumentSettingKey] = sr_community_privacy_consent_clean_document_key((string) ($settings[$privacyConsentDocumentSettingKey] ?? ''));
    }
    $settings['privacy_consent_document_inherit_policy'] = in_array((string) ($settings['privacy_consent_document_inherit_policy'] ?? 'override'), ['inherit', 'override', 'disabled'], true)
        ? (string) $settings['privacy_consent_document_inherit_policy']
        : 'override';
    $settings['privacy_consent_title'] = trim((string) ($settings['privacy_consent_title'] ?? '개인정보 수집 및 이용동의'));
    if ($settings['privacy_consent_title'] === '') {
        $settings['privacy_consent_title'] = '개인정보 수집 및 이용동의';
    }
    $settings['privacy_consent_body'] = trim((string) ($settings['privacy_consent_body'] ?? ''));
    $settings['privacy_consent_version'] = trim((string) ($settings['privacy_consent_version'] ?? '1'));
    if ($settings['privacy_consent_version'] === '') {
        $settings['privacy_consent_version'] = '1';
    }
    $settings['privacy_consent_require_post'] = sr_community_bool_setting($settings['privacy_consent_require_post'] ?? false);
    $settings['privacy_consent_require_comment'] = sr_community_bool_setting($settings['privacy_consent_require_comment'] ?? false);
    $settings['privacy_consent_require_attachment_upload'] = sr_community_bool_setting($settings['privacy_consent_require_attachment_upload'] ?? false);
    $settings['reaction_enabled'] = sr_community_bool_setting($settings['reaction_enabled'] ?? true);
    $settings['reaction_post_preset_key'] = $pdo instanceof PDO && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $settings['reaction_post_preset_key'] ?? '') : '';
    $settings['reaction_comment_preset_key'] = $pdo instanceof PDO && sr_module_enabled($pdo, 'reaction') && function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $settings['reaction_comment_preset_key'] ?? '') : '';
    foreach (sr_community_module_asset_setting_prefixes() as $assetPrefix) {
        $settings[$assetPrefix . '_enabled'] = sr_community_bool_setting($settings[$assetPrefix . '_enabled'] ?? false);
        $settings[$assetPrefix . '_asset_module'] = sr_community_asset_prefix_uses_composite($assetPrefix)
            ? sr_community_asset_module_value_from_keys(sr_community_asset_module_keys_from_value($settings[$assetPrefix . '_asset_module'] ?? '', true), true)
            : sr_community_asset_module_key_or_empty((string) ($settings[$assetPrefix . '_asset_module'] ?? ''));
        $settings[$assetPrefix . '_amount'] = min(999999999, max(0, (int) ($settings[$assetPrefix . '_amount'] ?? 0)));
        if (in_array($assetPrefix, sr_community_asset_composite_prefixes(), true)) {
            $settlementCurrencyKey = $assetPrefix . '_settlement_currency';
            $settlementCurrency = array_key_exists($settlementCurrencyKey, $rawSettings)
                ? (string) ($rawSettings[$settlementCurrencyKey] ?? '')
                : '';
            $settings[$assetPrefix . '_settlement_currency'] = $pdo instanceof PDO
                ? sr_community_asset_settlement_currency($pdo, ['asset_settlement_currency' => $settlementCurrency])
                : (function_exists('sr_normalize_currency_code') ? sr_normalize_currency_code($settlementCurrency) : strtoupper(trim($settlementCurrency)));
            if ((string) $settings[$assetPrefix . '_settlement_currency'] === '') {
                $settings[$assetPrefix . '_settlement_currency'] = 'KRW';
            }
        }
        $settings[$assetPrefix . '_group_policies_json'] = sr_community_asset_group_policy_json_from_value($settings[$assetPrefix . '_group_policies_json'] ?? '');
        $settings[$assetPrefix . '_policy_set_id'] = max(0, (int) ($settings[$assetPrefix . '_policy_set_id'] ?? 0));
        if (sr_community_asset_prefix_uses_composite($assetPrefix)) {
            $settings[$assetPrefix . '_amounts_json'] = sr_community_asset_amounts_json_from_map(
                sr_community_asset_amounts_from_value(
                    $settings[$assetPrefix . '_amounts_json'] ?? '',
                    sr_community_asset_module_keys_from_value($settings[$assetPrefix . '_asset_module'], true),
                    (int) $settings[$assetPrefix . '_amount']
                )
            );
        }
    }
    $settings['post_reward_reversal_enabled'] = sr_community_bool_setting($settings['post_reward_reversal_enabled'] ?? false);
    $settings['comment_reward_reversal_enabled'] = sr_community_bool_setting($settings['comment_reward_reversal_enabled'] ?? false);
    $settings['paid_read_charge_policy'] = sr_community_asset_charge_policy((string) ($settings['paid_read_charge_policy'] ?? 'once'), 'once');
    $settings['paid_attachment_download_charge_policy'] = sr_community_asset_charge_policy((string) ($settings['paid_attachment_download_charge_policy'] ?? 'once'), 'once');
    $settings['paid_attachment_download_publisher_reward_enabled'] = sr_community_bool_setting($settings['paid_attachment_download_publisher_reward_enabled'] ?? false);
    $settings['paid_attachment_download_publisher_reward_rate'] = min(100, max(0, (int) ($settings['paid_attachment_download_publisher_reward_rate'] ?? 0)));
    $settings['multi_asset_payment_enabled'] = sr_community_bool_setting($settings['multi_asset_payment_enabled'] ?? true);

    return $settings;
}

function sr_community_clean_layout_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_community_clean_layout_extra_menu_area_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A(?:[a-f0-9]{12}|[a-z][a-z0-9_]{0,59})\z/', $value) === 1 ? $value : '';
}

function sr_community_layout_extra_menu_label(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, 80) : substr($value, 0, 80);
}

function sr_community_layout_extra_menu_hash_key(array $usedKeys = [], string $seed = ''): string
{
    $used = array_fill_keys(array_map('strval', $usedKeys), true);
    if ($seed !== '') {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $key = substr(hash('sha256', 'community.layout_extra_menu|' . $seed . '|' . (string) $attempt), 0, 12);
            if (!isset($used[$key])) {
                return $key;
            }
        }
    }
    do {
        $key = bin2hex(random_bytes(6));
    } while (isset($used[$key]));

    return $key;
}

function sr_community_layout_menu_builtin_options(): array
{
    return [
        'sr_community_board_groups' => '게시판 그룹',
    ];
}

function sr_community_layout_menu_key_is_builtin(string $value): bool
{
    return array_key_exists($value, sr_community_layout_menu_builtin_options());
}

function sr_community_layout_extra_menu_items_from_value(mixed $value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
    }
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    foreach (array_values($value) as $itemIndex => $item) {
        $areaKey = '';
        $menuKey = '';
        if (is_array($item)) {
            $areaKey = sr_community_clean_layout_extra_menu_area_key((string) ($item['area_key'] ?? ($item['key'] ?? ($item['slot_key'] ?? ''))));
            $label = sr_community_layout_extra_menu_label((string) ($item['label'] ?? ($item['name'] ?? '')));
            $menuKey = sr_community_clean_layout_menu_key((string) ($item['menu_key'] ?? ''));
        } else {
            $label = '';
            $menuKey = sr_community_clean_layout_menu_key((string) $item);
        }
        if ($menuKey === '') {
            continue;
        }
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_community_layout_extra_menu_hash_key(array_keys($items), (string) $itemIndex . '|' . $menuKey);
        }
        $items[$areaKey] = [
            'area_key' => $areaKey,
            'label' => $label,
            'menu_key' => $menuKey,
        ];
    }

    return array_values($items);
}

function sr_community_layout_extra_menu_items_from_pair_values(mixed $areaKeys, mixed $labels, mixed $menuKeys): array
{
    $areaKeys = is_array($areaKeys) ? array_values($areaKeys) : [];
    $labels = is_array($labels) ? array_values($labels) : [];
    $menuKeys = is_array($menuKeys) ? array_values($menuKeys) : [];
    $items = [];
    foreach ($menuKeys as $index => $menuKeyValue) {
        $menuKey = sr_community_clean_layout_menu_key((string) $menuKeyValue);
        if ($menuKey === '') {
            continue;
        }
        $areaKey = sr_community_clean_layout_extra_menu_area_key((string) ($areaKeys[$index] ?? ''));
        if ($areaKey === '' || isset($items[$areaKey])) {
            $areaKey = sr_community_layout_extra_menu_hash_key(array_keys($items));
        }
        $label = sr_community_layout_extra_menu_label((string) ($labels[$index] ?? ''));
        if (!isset($items[$areaKey])) {
            $items[$areaKey] = [
                'area_key' => $areaKey,
                'label' => $label,
                'menu_key' => $menuKey,
            ];
        }
    }

    return array_values($items);
}

function sr_community_layout_extra_menu_keys_from_value(mixed $value): array
{
    return array_values(array_map(static fn (array $item): string => (string) $item['menu_key'], sr_community_layout_extra_menu_items_from_value($value)));
}

function sr_community_layout_extra_menu_items_from_settings(array $settings): array
{
    $items = [];
    foreach (sr_community_layout_extra_menu_items_from_value($settings['layout_extra_menu_keys_json'] ?? []) as $item) {
        $items[(string) $item['area_key']] = $item;
    }
    foreach (['layout_secondary_menu_key', 'layout_tertiary_menu_key', 'layout_quaternary_menu_key', 'layout_quinary_menu_key'] as $legacySettingKey) {
        $menuKey = sr_community_clean_layout_menu_key((string) ($settings[$legacySettingKey] ?? ''));
        $areaKey = str_replace('layout_', '', str_replace('_menu_key', '', $legacySettingKey));
        if ($menuKey !== '' && !isset($items[$areaKey])) {
            $items[$areaKey] = [
                'area_key' => $areaKey,
                'label' => '',
                'menu_key' => $menuKey,
            ];
        }
    }

    return array_values($items);
}

function sr_community_layout_extra_menu_keys_from_settings(array $settings): array
{
    return array_values(array_map(static fn (array $item): string => (string) $item['menu_key'], sr_community_layout_extra_menu_items_from_settings($settings)));
}

function sr_community_layout_extra_menu_keys_json(mixed $value): string
{
    $json = json_encode(sr_community_layout_extra_menu_items_from_value($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return is_string($json) ? $json : '[]';
}

function sr_community_layout_menu_links(PDO $pdo, string $menuKey): array
{
    return $menuKey === 'sr_community_board_groups' ? sr_community_primary_menu_fallback_links($pdo) : [];
}

function sr_community_layout_menu_html(PDO $pdo, string $menuKey, string $slotKey): string
{
    $links = sr_community_layout_menu_links($pdo, $menuKey);
    if ($links === []) {
        return '';
    }

    $currentPath = '/' . trim(sr_request_path(), '/');
    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
    $currentQuery = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    parse_str(is_string($currentQuery) ? $currentQuery : '', $currentQueryParams);
    $currentBoardKey = '';
    $currentGroupKey = '';
    if (in_array($currentPath, ['/community/board', '/community/write'], true)) {
        $currentBoardKey = (string) ($currentQueryParams['key'] ?? '');
    } elseif (in_array($currentPath, ['/community/post', '/community/edit'], true)) {
        $currentPostId = (int) ($currentQueryParams['id'] ?? 0);
        if ($currentPostId > 0) {
            try {
                $stmt = $pdo->prepare(
                    'SELECT b.board_key, g.group_key AS board_group_key
                     FROM sr_community_posts p
                     INNER JOIN sr_community_boards b ON b.id = p.board_id
                     LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
                     WHERE p.id = :id
                     LIMIT 1'
                );
                $stmt->execute(['id' => $currentPostId]);
                $postBoard = $stmt->fetch();
                $currentBoardKey = is_array($postBoard) ? (string) ($postBoard['board_key'] ?? '') : '';
                $currentGroupKey = is_array($postBoard) ? (string) ($postBoard['board_group_key'] ?? '') : '';
            } catch (Throwable) {
                $currentBoardKey = '';
                $currentGroupKey = '';
            }
        }
    }
    if ($currentBoardKey !== '' && $currentGroupKey === '') {
        try {
            $stmt = $pdo->prepare(
                'SELECT g.group_key
                 FROM sr_community_boards b
                 LEFT JOIN sr_community_board_groups g ON g.id = b.board_group_id
                 WHERE b.board_key = :board_key
                   AND b.status = \'enabled\'
                   AND g.status = \'enabled\'
                 LIMIT 1'
            );
            $stmt->execute(['board_key' => $currentBoardKey]);
            $group = $stmt->fetch();
            $currentGroupKey = is_array($group) ? (string) ($group['group_key'] ?? '') : '';
        } catch (Throwable) {
            $currentGroupKey = '';
        }
    }

    $html = '<div class="sr-site-menu sr-site-menu-fallback" data-site-menu-fallback="' . sr_e($slotKey) . '"><ul class="sr-site-menu-list sr-site-menu-list-depth-1">';
    foreach ($links as $link) {
        $label = (string) ($link['label'] ?? '');
        $url = (string) ($link['url'] ?? '');
        if ($label === '' || $url === '') {
            continue;
        }

        $linkPath = parse_url($url, PHP_URL_PATH);
        $linkPath = is_string($linkPath) && $linkPath !== '' ? '/' . trim($linkPath, '/') : '/';
        $linkPath = $linkPath === '/' ? '/' : rtrim($linkPath, '/');
        $linkQuery = parse_url($url, PHP_URL_QUERY);
        parse_str(is_string($linkQuery) ? $linkQuery : '', $linkQueryParams);
        $linkBoardKey = (string) ($linkQueryParams['key'] ?? '');
        $linkGroupKey = (string) ($link['group_key'] ?? '');
        $linkFragment = parse_url($url, PHP_URL_FRAGMENT);
        $linkFragment = is_string($linkFragment) ? $linkFragment : '';
        if ($linkGroupKey === '' && str_starts_with($linkFragment, 'group-')) {
            $linkGroupKey = rawurldecode(substr($linkFragment, 6));
        }
        $queryMatches = $linkQueryParams === [];
        if (!$queryMatches && is_array($currentQueryParams)) {
            $queryMatches = true;
            foreach ($linkQueryParams as $linkQueryKey => $linkQueryValue) {
                if (!array_key_exists((string) $linkQueryKey, $currentQueryParams) || $currentQueryParams[(string) $linkQueryKey] != $linkQueryValue) {
                    $queryMatches = false;
                    break;
                }
            }
        }
        $currentAttribute = ($linkFragment === '' && $linkPath === $currentPath && $queryMatches) || (
            $linkPath === '/community/board'
            && $linkBoardKey !== ''
            && $linkBoardKey === $currentBoardKey
        ) || (
            $linkGroupKey !== ''
            && $linkGroupKey === $currentGroupKey
        ) ? ' aria-current="page"' : '';
        $html .= '<li class="sr-site-menu-item"><a class="sr-site-menu-link" href="' . sr_e(sr_url($url)) . '"' . $currentAttribute . '>' . sr_e($label) . '</a></li>';
    }
    $html .= '</ul></div>';

    return $html;
}

function sr_community_public_layout_context(array $settings, array $context = []): array
{
    $layoutKey = sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? ''));
    if ($layoutKey !== '') {
        $context['layout_key'] = $layoutKey;
    }
    $themeKey = sr_community_theme_key((string) ($settings['theme_key'] ?? ''));
    if ($themeKey !== '') {
        $context['theme_key'] = $themeKey;
    }
    $context['consumer_domain'] = 'community';
    $context['style_profile'] = 'module';
    $context['module_home_url'] = sr_url('/community');
    $context['module_label'] = '커뮤니티';
    $context['module_menu_label'] = '커뮤니티 메뉴';
    $context['business_info_visible'] = !array_key_exists('business_info_visible', $settings) || !empty($settings['business_info_visible']);
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = sr_public_layout_module_theme_asset_url('community', $themeKey, 'reset.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('community', $themeKey, 'common.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('community', $themeKey, 'module.css');
    $themeStylesheet = sr_module_view_theme_stylesheet_url('community', $themeKey);
    if ($themeStylesheet !== '') {
        $stylesheets[] = $themeStylesheet;
    }
    if ($themeKey !== sr_public_theme_default_key()) {
        $bodyClass = sr_ui_icon_class_attr((string) ($context['body_class'] ?? ''));
        $context['body_class'] = trim($bodyClass . ' community-view-theme-' . $themeKey);
    }
    $context['stylesheets'] = array_values(array_unique($stylesheets));
    $scripts = is_array($context['scripts'] ?? null) ? $context['scripts'] : [];
    $scripts[] = '/modules/community/assets/module.js';
    $context['scripts'] = array_values(array_unique($scripts));

    $siteMenus = [];
    $siteMenus['primary'] = sr_community_clean_layout_menu_key((string) ($settings['layout_primary_menu_key'] ?? 'header'));
    $contextSiteMenus = is_array($context['site_menus'] ?? null) ? $context['site_menus'] : [];
    $context['site_menus'] = array_merge($contextSiteMenus, $siteMenus);
    $context['site_extra_menus'] = sr_community_layout_extra_menu_items_from_settings($settings);

    return $context;
}

function sr_community_ui_kit_layout_context(array $settings, array $context = []): array
{
    $context = sr_community_public_layout_context($settings, $context);
    $themeKey = sr_community_theme_key((string) ($settings['theme_key'] ?? ''));
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = sr_public_layout_module_theme_asset_url('community', $themeKey, 'common.css');
    $stylesheets[] = sr_public_layout_module_theme_asset_url('community', $themeKey, 'ui-kit-layout.css');
    $context['stylesheets'] = array_values(array_unique($stylesheets));

    return $context;
}

function sr_community_bool_setting(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_community_post_toolbar_preset_key(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'community_post_basic';
    }

    if (function_exists('sr_ckeditor_toolbar_presets')) {
        $presets = sr_ckeditor_toolbar_presets();
        return isset($presets[$value]) ? $value : 'community_post_basic';
    }

    return preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $value) === 1 ? $value : 'community_post_basic';
}

function sr_community_post_toolbar_preset_options(): array
{
    if (function_exists('sr_ckeditor_toolbar_presets')) {
        $options = [];
        foreach (sr_ckeditor_toolbar_presets() as $presetKey => $preset) {
            $options[(string) $presetKey] = (string) ($preset['label'] ?? $presetKey);
        }
        if ($options !== []) {
            return $options;
        }
    }

    return [
        'community_post_basic' => '커뮤니티 게시글 기본',
    ];
}

function sr_community_post_toolbar_preset(PDO $pdo, ?array $settings = null): string
{
    $settings = is_array($settings) ? $settings : sr_community_settings($pdo);
    sr_editor_effective_key($pdo, (string) ($settings['post_editor'] ?? 'textarea'));
    return sr_community_post_toolbar_preset_key((string) ($settings['post_toolbar_preset'] ?? 'community_post_basic'));
}

function sr_community_group_keys_from_setting(mixed $value): array
{
    if (is_array($value)) {
        return sr_community_normalize_board_group_keys($value);
    }

    $value = trim((string) $value);
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);
    if (is_array($decoded)) {
        return sr_community_normalize_board_group_keys($decoded);
    }

    $rawKeys = preg_split('/[\s,]+/', $value);
    return sr_community_normalize_board_group_keys(is_array($rawKeys) ? $rawKeys : []);
}

function sr_community_level_tables_exist(PDO $pdo): bool
{
    static $existsByPdo = [];
    $cacheKey = (string) spl_object_id($pdo);
    if (array_key_exists($cacheKey, $existsByPdo)) {
        return $existsByPdo[$cacheKey];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_community_levels LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_community_account_levels LIMIT 1');
        $pdo->query('SELECT 1 FROM sr_community_level_logs LIMIT 1');
        $existsByPdo[$cacheKey] = true;
    } catch (Throwable $exception) {
        $existsByPdo[$cacheKey] = false;
    }

    return $existsByPdo[$cacheKey];
}

function sr_community_level_recalculate_job_create(PDO $pdo, int $accountId, int $total, int $batchSize): array
{
    $now = sr_now();
    $lockToken = bin2hex(random_bytes(16));
    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_level_recalculate_jobs
            (requested_by, status, stage, cursor_value, processed_total, total_count, batch_size, lock_token, created_at, updated_at, started_at)
         VALUES
            (:requested_by, \'running\', \'accounts\', 0, 0, :total_count, :batch_size, :lock_token, :created_at, :updated_at, :started_at)'
    );
    $stmt->execute([
        'requested_by' => $accountId > 0 ? $accountId : null,
        'total_count' => max(0, $total),
        'batch_size' => max(1, min(100, $batchSize)),
        'lock_token' => $lockToken,
        'created_at' => $now,
        'updated_at' => $now,
        'started_at' => $now,
    ]);

    return [
        'id' => (int) $pdo->lastInsertId(),
        'lock_token' => $lockToken,
        'cursor_value' => 0,
        'processed_total' => 0,
        'total_count' => max(0, $total),
        'batch_size' => max(1, min(100, $batchSize)),
    ];
}

function sr_community_level_recalculate_job_by_id(PDO $pdo, int $jobId): ?array
{
    if ($jobId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_community_level_recalculate_jobs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $jobId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_community_level_recalculate_job_require_running(array $job, string $lockToken): void
{
    if ((int) ($job['id'] ?? 0) < 1 || (string) ($job['status'] ?? '') !== 'running' || $lockToken === '' || !hash_equals((string) ($job['lock_token'] ?? ''), $lockToken)) {
        throw new RuntimeException('레벨 재계산 작업 상태가 만료되었습니다. 화면을 새로고침한 뒤 다시 실행하세요.');
    }
}

function sr_community_level_recalculate_job_progress(PDO $pdo, int $jobId, string $lockToken, int $cursor, int $processedTotal, int $total): void
{
    if ($jobId < 1 || $lockToken === '') {
        return;
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_community_level_recalculate_jobs
         SET cursor_value = :cursor_value,
             processed_total = :processed_total,
             total_count = :total_count,
             updated_at = :updated_at
         WHERE id = :id
           AND status = 'running'
           AND lock_token = :lock_token"
    );
    $stmt->execute([
        'cursor_value' => max(0, $cursor),
        'processed_total' => max(0, $processedTotal),
        'total_count' => max(0, $total),
        'updated_at' => sr_now(),
        'id' => $jobId,
        'lock_token' => $lockToken,
    ]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('레벨 재계산 작업 잠금이 만료되었습니다.');
    }
}

function sr_community_level_recalculate_job_complete(PDO $pdo, int $jobId, string $lockToken, int $cursor, int $processedTotal, int $total): void
{
    if ($jobId < 1 || $lockToken === '') {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_community_level_recalculate_jobs
         SET status = 'completed',
             stage = 'complete',
             cursor_value = :cursor_value,
             processed_total = :processed_total,
             total_count = :total_count,
             updated_at = :updated_at,
             completed_at = :completed_at
         WHERE id = :id
           AND status = 'running'
           AND lock_token = :lock_token"
    );
    $stmt->execute([
        'cursor_value' => max(0, $cursor),
        'processed_total' => max(0, $processedTotal),
        'total_count' => max(0, $total),
        'updated_at' => $now,
        'completed_at' => $now,
        'id' => $jobId,
        'lock_token' => $lockToken,
    ]);
    if ($stmt->rowCount() < 1) {
        throw new RuntimeException('레벨 재계산 작업 완료 상태를 저장하지 못했습니다.');
    }
}

function sr_community_level_recalculate_job_fail(PDO $pdo, int $jobId, string $lockToken, Throwable $exception): void
{
    if ($jobId < 1 || $lockToken === '') {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        "UPDATE sr_community_level_recalculate_jobs
         SET status = 'failed',
             failure_message = :failure_message,
             updated_at = :updated_at,
             failed_at = :failed_at
         WHERE id = :id
           AND status = 'running'
           AND lock_token = :lock_token"
    );
    $stmt->execute([
        'failure_message' => mb_substr($exception->getMessage(), 0, 2000),
        'updated_at' => $now,
        'failed_at' => $now,
        'id' => $jobId,
        'lock_token' => $lockToken,
    ]);
}

function sr_community_levels(PDO $pdo, ?array $settings = null): array
{
    if (!sr_community_level_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_community_levels
         WHERE level_value <= :max_level
         ORDER BY level_value ASC, id ASC"
    );
    $stmt->execute(['max_level' => sr_community_max_level_value($settings)]);

    return $stmt->fetchAll();
}

function sr_community_enabled_levels(PDO $pdo, ?array $settings = null): array
{
    if (!sr_community_level_tables_exist($pdo)) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_community_levels
         WHERE status = 'enabled'
           AND level_value <= :max_level
         ORDER BY level_value ASC, id ASC"
    );
    $stmt->execute(['max_level' => sr_community_max_level_value($settings)]);

    return $stmt->fetchAll();
}

function sr_community_account_level_snapshot(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !sr_community_level_tables_exist($pdo)) {
        return sr_community_empty_account_level_snapshot($accountId);
    }

    $cacheKey = (string) spl_object_id($pdo) . ':' . (string) $accountId;
    $cache = $GLOBALS['sr_community_account_level_snapshot_runtime_cache'] ?? [];
    if (!is_array($cache)) {
        $cache = [];
    }
    if (array_key_exists($cacheKey, $cache) && is_array($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $stmt = $pdo->prepare(
        'SELECT account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at
         FROM sr_community_account_levels
         WHERE account_id = :account_id
         LIMIT 1'
    );
    $stmt->execute(['account_id' => $accountId]);
    $snapshot = $stmt->fetch();

    $snapshot = is_array($snapshot) ? $snapshot : sr_community_empty_account_level_snapshot($accountId);
    $cache[$cacheKey] = $snapshot;
    $GLOBALS['sr_community_account_level_snapshot_runtime_cache'] = $cache;

    return $snapshot;
}

function sr_community_clear_account_level_snapshot_runtime_cache(PDO $pdo, int $accountId): void
{
    if ($accountId < 1 || !isset($GLOBALS['sr_community_account_level_snapshot_runtime_cache']) || !is_array($GLOBALS['sr_community_account_level_snapshot_runtime_cache'])) {
        return;
    }

    unset($GLOBALS['sr_community_account_level_snapshot_runtime_cache'][(string) spl_object_id($pdo) . ':' . (string) $accountId]);
}

function sr_community_delete_account_level_data(PDO $pdo, int $accountId): array
{
    if ($accountId < 1 || !sr_community_level_tables_exist($pdo)) {
        return [
            'account_level_deleted' => false,
            'level_log_deleted_count' => 0,
        ];
    }

    $stmt = $pdo->prepare('DELETE FROM sr_community_account_levels WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
    $accountLevelDeleted = $stmt->rowCount() > 0;
    sr_community_clear_account_level_snapshot_runtime_cache($pdo, $accountId);

    $stmt = $pdo->prepare('DELETE FROM sr_community_level_logs WHERE account_id = :account_id');
    $stmt->execute(['account_id' => $accountId]);
    $levelLogDeletedCount = $stmt->rowCount();

    return [
        'account_level_deleted' => $accountLevelDeleted,
        'level_log_deleted_count' => $levelLogDeletedCount,
    ];
}

function sr_community_empty_account_level_snapshot(int $accountId): array
{
    return [
        'account_id' => $accountId,
        'level_value' => 0,
        'score_value' => 0,
        'post_count' => 0,
        'comment_count' => 0,
        'evaluated_at' => null,
        'created_at' => null,
        'updated_at' => null,
    ];
}

function sr_community_account_meets_min_level(PDO $pdo, int $accountId, int $minLevel, ?array $settings = null): bool
{
    $minLevel = max(0, $minLevel);
    if ($minLevel < 1) {
        return true;
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    if (empty($settings['level_enabled'])) {
        return false;
    }

    $snapshot = sr_community_account_level_snapshot($pdo, $accountId);
    return (int) ($snapshot['level_value'] ?? 0) >= $minLevel;
}

function sr_community_account_satisfies_access(PDO $pdo, int $accountId, array $context): array
{
    $settings = sr_community_normalize_settings(is_array($context['settings'] ?? null) ? $context['settings'] : sr_community_settings($pdo));
    $groupKeys = sr_community_normalize_board_group_keys(is_array($context['group_keys'] ?? null) ? $context['group_keys'] : []);
    $minLevel = sr_community_normalize_level_value($context['min_level'] ?? 0, $settings);
    if ($accountId < 1) {
        return [
            'allowed' => false,
            'reason_key' => 'login_required',
            'matched_by' => '',
            'group_matched' => false,
            'level_matched' => false,
        ];
    }

    $hasGroupCondition = $groupKeys !== [];
    $hasLevelCondition = $minLevel > 0;
    if (!$hasGroupCondition && !$hasLevelCondition) {
        return [
            'allowed' => true,
            'reason_key' => '',
            'matched_by' => 'member',
            'group_matched' => false,
            'level_matched' => false,
        ];
    }

    $groupMatched = !$hasGroupCondition ? true : sr_member_account_in_any_group($pdo, $accountId, $groupKeys);
    $levelMatched = !$hasLevelCondition ? true : sr_community_account_meets_min_level($pdo, $accountId, $minLevel, $settings);
    $allowed = $groupMatched && $levelMatched;
    $matchedBy = $allowed ? ($hasGroupCondition && $hasLevelCondition ? 'group_level' : ($hasGroupCondition ? 'group' : 'level')) : '';

    return [
        'allowed' => $allowed,
        'reason_key' => $allowed ? '' : 'access_condition_not_met',
        'matched_by' => $matchedBy,
        'group_matched' => $hasGroupCondition && $groupMatched,
        'level_matched' => $hasLevelCondition && $levelMatched,
    ];
}

function sr_community_recalculate_account_level(PDO $pdo, int $accountId, ?array $settings = null, string $reasonKey = 'activity_changed'): array
{
    if ($accountId < 1 || !sr_community_level_tables_exist($pdo)) {
        return sr_community_empty_account_level_snapshot($accountId);
    }

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    if (empty($settings['level_enabled'])) {
        return sr_community_account_level_snapshot($pdo, $accountId);
    }

    $stmt = $pdo->prepare(
        "SELECT board_id, COUNT(*) AS post_count
         FROM sr_community_posts
         WHERE author_account_id = :account_id
           AND status = 'published'
         GROUP BY board_id"
    );
    $stmt->execute(['account_id' => $accountId]);
    $postCountsByBoard = [];
    foreach ($stmt->fetchAll() as $row) {
        $postCountsByBoard[(int) ($row['board_id'] ?? 0)] = (int) ($row['post_count'] ?? 0);
    }

    $stmt = $pdo->prepare(
        "SELECT p.board_id, COUNT(*) AS comment_count
         FROM sr_community_comments c
         INNER JOIN sr_community_posts p ON p.id = c.post_id
         WHERE c.author_account_id = :account_id
           AND c.status = 'published'
         GROUP BY p.board_id"
    );
    $stmt->execute(['account_id' => $accountId]);
    $commentCountsByBoard = [];
    foreach ($stmt->fetchAll() as $row) {
        $commentCountsByBoard[(int) ($row['board_id'] ?? 0)] = (int) ($row['comment_count'] ?? 0);
    }

    $postCount = array_sum($postCountsByBoard);
    $commentCount = array_sum($commentCountsByBoard);
    $scoreValue = 0;
    foreach ($postCountsByBoard as $boardId => $boardPostCount) {
        $scoreValue += $boardPostCount * sr_community_board_level_score($pdo, (int) $boardId, 'level_post_score', $settings);
    }
    foreach ($commentCountsByBoard as $boardId => $boardCommentCount) {
        $scoreValue += $boardCommentCount * sr_community_board_level_score($pdo, (int) $boardId, 'level_comment_score', $settings);
    }
    $levelValue = sr_community_level_value_for_score($pdo, $scoreValue, $settings);
    if (sr_community_account_is_owner($pdo, $accountId)) {
        $levelValue = sr_community_max_level_value($settings);
    }
    $before = sr_community_account_level_snapshot($pdo, $accountId);
    $now = sr_now();

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_account_levels
            (account_id, level_value, score_value, post_count, comment_count, evaluated_at, created_at, updated_at)
         VALUES
            (:account_id, :level_value, :score_value, :post_count, :comment_count, :evaluated_at, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            level_value = VALUES(level_value),
            score_value = VALUES(score_value),
            post_count = VALUES(post_count),
            comment_count = VALUES(comment_count),
            evaluated_at = VALUES(evaluated_at),
            updated_at = VALUES(updated_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'level_value' => $levelValue,
        'score_value' => $scoreValue,
        'post_count' => $postCount,
        'comment_count' => $commentCount,
        'evaluated_at' => $now,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    if ((int) ($before['level_value'] ?? 0) !== $levelValue || (int) ($before['score_value'] ?? 0) !== $scoreValue) {
        sr_community_log_level_change($pdo, $accountId, $before, [
            'level_value' => $levelValue,
            'score_value' => $scoreValue,
        ], $reasonKey);
    }

    sr_community_clear_account_level_snapshot_runtime_cache($pdo, $accountId);

    return sr_community_account_level_snapshot($pdo, $accountId);
}

function sr_community_account_is_owner(PDO $pdo, int $accountId): bool
{
    if ($accountId < 1) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("SELECT 1 FROM sr_admin_account_roles WHERE account_id = :account_id AND role_key = 'owner' LIMIT 1");
        $stmt->execute(['account_id' => $accountId]);
        return is_array($stmt->fetch());
    } catch (Throwable $exception) {
        return false;
    }
}

function sr_community_maybe_recalculate_account_level(PDO $pdo, int $accountId, ?array $settings = null, string $reasonKey = 'activity_changed'): array
{
    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    if (empty($settings['level_auto_recalculate'])) {
        return sr_community_account_level_snapshot($pdo, $accountId);
    }

    return sr_community_recalculate_account_level($pdo, $accountId, $settings, $reasonKey);
}

// Policy checks for manual edits live in the admin action; this helper only persists the requested snapshot.

function sr_community_level_value_for_score(PDO $pdo, int $scoreValue, ?array $settings = null): int
{
    $levelValue = 0;
    foreach (sr_community_enabled_levels($pdo, $settings) as $level) {
        if ((int) ($level['min_score'] ?? 0) <= $scoreValue) {
            $levelValue = max($levelValue, (int) $level['level_value']);
        }
    }

    return $levelValue;
}

function sr_community_update_level_min_scores(PDO $pdo, array $minScoresById, ?array $settings = null): int
{
    if (!sr_community_level_tables_exist($pdo)) {
        return 0;
    }

    $updates = [];
    $lastMinScore = 0;
    foreach (sr_community_levels($pdo, $settings) as $level) {
        $levelId = (int) ($level['id'] ?? 0);
        if ($levelId < 1 || !array_key_exists($levelId, $minScoresById)) {
            continue;
        }

        $minScore = (int) $minScoresById[$levelId];
        if ($minScore < $lastMinScore) {
            throw new InvalidArgumentException(sr_t('community::action.admin.level_min_score_order_invalid'));
        }

        $lastMinScore = $minScore;
        if ($minScore !== (int) ($level['min_score'] ?? 0)) {
            $updates[$levelId] = $minScore;
        }
    }

    if ($updates === []) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_community_levels
         SET min_score = :min_score, updated_at = :updated_at
         WHERE id = :id'
    );
    $now = sr_now();
    foreach ($updates as $levelId => $minScore) {
        $stmt->execute([
            'min_score' => $minScore,
            'updated_at' => $now,
            'id' => $levelId,
        ]);
    }

    return count($updates);
}

function sr_community_default_min_score_for_level(int $levelValue, array $existingLevels = []): int
{
    $defaults = [
        1 => 0,
        2 => 10,
        3 => 50,
        4 => 100,
        5 => 300,
        6 => 600,
        7 => 1000,
        8 => 1500,
        9 => 2100,
        10 => 3000,
    ];
    if (isset($defaults[$levelValue])) {
        return $defaults[$levelValue];
    }

    $lastScore = 0;
    foreach ($existingLevels as $level) {
        $currentValue = (int) ($level['level_value'] ?? 0);
        if ($currentValue < 1 || $currentValue >= $levelValue) {
            continue;
        }
        $lastScore = max($lastScore, (int) ($level['min_score'] ?? 0));
    }

    return min(1000000000, $lastScore + 1000);
}

function sr_community_ensure_level_definitions(PDO $pdo, int $maxLevel): int
{
    if (!sr_community_level_tables_exist($pdo)) {
        return 0;
    }

    $maxLevel = sr_community_level_max_setting($maxLevel);
    $stmt = $pdo->query(
        "SELECT *
         FROM sr_community_levels
         ORDER BY level_value ASC, id ASC"
    );
    $levels = $stmt->fetchAll();
    $existingByValue = [];
    foreach ($levels as $level) {
        $existingByValue[(int) ($level['level_value'] ?? 0)] = $level;
    }

    $insert = $pdo->prepare(
        'INSERT INTO sr_community_levels
            (level_value, title, description, min_score, status, sort_order, created_at, updated_at)
         VALUES
            (:level_value, :title, :description, :min_score, :status, :sort_order, :created_at, :updated_at)'
    );
    $created = 0;
    $now = sr_now();
    for ($levelValue = 1; $levelValue <= $maxLevel; $levelValue++) {
        if (isset($existingByValue[$levelValue])) {
            continue;
        }

        $minScore = sr_community_default_min_score_for_level($levelValue, $levels);
        $insert->execute([
            'level_value' => $levelValue,
            'title' => '레벨 ' . (string) $levelValue,
            'description' => $levelValue === 1
                ? '기본 커뮤니티 레벨입니다.'
                : '커뮤니티 활동 점수 ' . (string) $minScore . '점 이상입니다.',
            'min_score' => $minScore,
            'status' => 'enabled',
            'sort_order' => $levelValue * 10,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $created++;
        $levels[] = [
            'level_value' => $levelValue,
            'min_score' => $minScore,
        ];
    }

    return $created;
}

function sr_community_log_level_change(PDO $pdo, int $accountId, array $before, array $after, string $reasonKey): void
{
    if (!sr_community_level_tables_exist($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO sr_community_level_logs
            (account_id, old_level_value, new_level_value, old_score_value, new_score_value, reason_key, created_at)
         VALUES
            (:account_id, :old_level_value, :new_level_value, :old_score_value, :new_score_value, :reason_key, :created_at)'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'old_level_value' => (int) ($before['level_value'] ?? 0),
        'new_level_value' => (int) ($after['level_value'] ?? 0),
        'old_score_value' => (int) ($before['score_value'] ?? 0),
        'new_score_value' => (int) ($after['score_value'] ?? 0),
        'reason_key' => preg_match('/\A[a-z][a-z0-9_]{0,59}\z/', $reasonKey) === 1 ? $reasonKey : 'activity_changed',
        'created_at' => sr_now(),
    ]);
}

function sr_community_recalculate_recent_account_levels(PDO $pdo, int $limit = 100): array
{
    $limit = max(1, min(500, $limit));
    $summary = sr_community_recalculate_account_levels_batch($pdo, 0, $limit);

    return [
        'accounts' => (int) ($summary['accounts'] ?? 0),
        'next_cursor' => (int) ($summary['next_cursor'] ?? 0),
        'done' => !empty($summary['done']),
    ];
}

function sr_community_recalculate_target_account_count(PDO $pdo): int
{
    if (!sr_community_level_tables_exist($pdo)) {
        return 0;
    }

    $stmt = $pdo->query("SELECT COUNT(*) AS account_count FROM sr_member_accounts WHERE status IN ('active', 'pending')");
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['account_count'] ?? 0) : 0;
}

function sr_community_recalculate_account_levels_batch(PDO $pdo, int $cursor = 0, int $limit = 50, ?array $settings = null): array
{
    $cursor = max(0, $cursor);
    $limit = max(1, min(500, $limit));
    if (!sr_community_level_tables_exist($pdo)) {
        return [
            'accounts' => 0,
            'next_cursor' => $cursor,
            'done' => true,
        ];
    }

    $stmt = $pdo->prepare(
        "SELECT id
         FROM sr_member_accounts
         WHERE status IN ('active', 'pending')
           AND id > :cursor
         ORDER BY id ASC
         LIMIT :limit_value"
    );
    $stmt->bindValue('cursor', $cursor, PDO::PARAM_INT);
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();

    $settings = is_array($settings) ? sr_community_normalize_settings($settings) : sr_community_settings($pdo);
    $count = 0;
    $nextCursor = $cursor;
    foreach ($rows as $row) {
        $accountId = (int) ($row['id'] ?? 0);
        if ($accountId < 1) {
            continue;
        }

        sr_community_recalculate_account_level($pdo, $accountId, $settings, 'admin_recalculate');
        $nextCursor = $accountId;
        $count++;
    }

    return [
        'accounts' => $count,
        'next_cursor' => $nextCursor,
        'done' => count($rows) < $limit,
    ];
}
