<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers/common.php';

require_once SR_ROOT . '/modules/content/helpers/assets.php';
require_once SR_ROOT . '/modules/content/helpers/cover-images.php';
require_once SR_ROOT . '/modules/content/helpers/groups.php';
require_once SR_ROOT . '/modules/content/helpers/member-submissions.php';
require_once SR_ROOT . '/modules/content/helpers/references.php';
require_once SR_ROOT . '/modules/content/helpers/records.php';
require_once SR_ROOT . '/modules/content/helpers/body-files.php';
require_once SR_ROOT . '/modules/content/helpers/files.php';
require_once SR_ROOT . '/modules/content/helpers/series.php';
require_once SR_ROOT . '/modules/content/helpers/comments.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/embed_manager/helpers.php';

function sr_content_allowed_statuses(): array
{
    return ['draft', 'scheduled', 'published', 'hidden'];
}

function sr_content_datetime_local_value(?string $value): string
{
    return sr_datetime_local_value($value);
}

function sr_content_time_html(?string $value, string $emptyText = ''): string
{
    return sr_relative_time_html($value, $emptyText);
}

function sr_content_scheduled_publish_at_from_post(): string
{
    $value = trim(sr_post_string('scheduled_publish_at', 30));
    if ($value === '') {
        return '';
    }

    $timestamp = strtotime(str_replace('T', ' ', $value));
    return $timestamp === false ? '' : date('Y-m-d H:i:s', $timestamp);
}

function sr_content_publish_due_scheduled(PDO $pdo): int
{
    $now = sr_now();
    $stmt = $pdo->prepare(
        "SELECT id, slug, published_at
         FROM sr_content_items
         WHERE status = 'scheduled'
           AND published_at IS NOT NULL
           AND published_at <= :now_value
         ORDER BY published_at ASC, id ASC"
    );
    $stmt->execute(['now_value' => $now]);

    $publishedCount = 0;
    $updateStmt = $pdo->prepare(
        "UPDATE sr_content_items
         SET status = 'published',
             updated_at = :updated_at
         WHERE id = :id
           AND status = 'scheduled'"
    );
    foreach ($stmt->fetchAll() as $row) {
        $contentId = (int) ($row['id'] ?? 0);
        if ($contentId < 1) {
            continue;
        }

        $updateStmt->execute([
            'updated_at' => $now,
            'id' => $contentId,
        ]);
        if ($updateStmt->rowCount() < 1) {
            continue;
        }

        $publishedCount += 1;
        sr_audit_log($pdo, [
            'actor_type' => 'system',
            'event_type' => 'content.scheduled_published',
            'target_type' => 'content',
            'target_id' => (string) $contentId,
            'result' => 'success',
            'message' => 'Scheduled content published.',
            'metadata' => [
                'slug' => (string) ($row['slug'] ?? ''),
                'scheduled_publish_at' => (string) ($row['published_at'] ?? ''),
                'published_at' => $now,
            ],
        ]);
    }

    return $publishedCount;
}

function sr_content_default_settings(): array
{
    return [
        'editor' => 'textarea',
        'editor_toolbar_preset' => 'content_basic',
        'plain_text_auto_link_urls' => false,
        'secret_comments_enabled' => false,
        'once_history_policy' => 'all_access',
        'layout_key' => 'content.basic',
        'layout_primary_menu_key' => 'header',
        'layout_secondary_menu_key' => '',
        'layout_tertiary_menu_key' => '',
        'layout_quaternary_menu_key' => '',
        'layout_quinary_menu_key' => '',
        'series_enabled' => true,
        'member_submission_enabled' => false,
        'member_submission_default_review_required' => false,
        'member_submission_author_reward_enabled' => false,
        'member_submission_author_reward_asset_module' => '',
        'member_submission_author_reward_amount' => 0,
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
    ];
}

function sr_content_layout_menu_slots(): array
{
    return [
        'primary' => 'layout_primary_menu_key',
        'secondary' => 'layout_secondary_menu_key',
        'tertiary' => 'layout_tertiary_menu_key',
        'quaternary' => 'layout_quaternary_menu_key',
        'quinary' => 'layout_quinary_menu_key',
    ];
}

function sr_content_clean_layout_menu_key(string $value): string
{
    $value = strtolower(trim($value));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $value) === 1 ? $value : '';
}

function sr_content_layout_menu_builtin_options(): array
{
    return [
        'sr_content_groups' => '콘텐츠 그룹',
    ];
}

function sr_content_layout_menu_key_is_builtin(string $value): bool
{
    return array_key_exists($value, sr_content_layout_menu_builtin_options());
}

function sr_content_bool_setting(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }

    return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
}

function sr_content_toolbar_preset_key(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return 'content_basic';
    }

    if (function_exists('sr_ckeditor_toolbar_presets')) {
        $presets = sr_ckeditor_toolbar_presets();
        return isset($presets[$value]) ? $value : 'content_basic';
    }

    return preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $value) === 1 ? $value : 'content_basic';
}

function sr_content_toolbar_preset_options(): array
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
        'content_basic' => '콘텐츠 본문 기본',
    ];
}

function sr_content_settings(PDO $pdo): array
{
    $settings = array_merge(sr_content_default_settings(), sr_module_settings($pdo, 'content'));
    $settings['editor'] = sr_editor_normalize_key((string) ($settings['editor'] ?? 'textarea'));
    $settings['editor_toolbar_preset'] = sr_content_toolbar_preset_key((string) ($settings['editor_toolbar_preset'] ?? 'content_basic'));
    $settings['plain_text_auto_link_urls'] = sr_content_bool_setting($settings['plain_text_auto_link_urls'] ?? false);
    $settings['secret_comments_enabled'] = sr_content_bool_setting($settings['secret_comments_enabled'] ?? false);
    $settings['once_history_policy'] = sr_content_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access'));
    $settings['layout_key'] = sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? 'content.basic'));
    if (!isset(sr_public_layout_options($pdo)[$settings['layout_key']])) {
        $settings['layout_key'] = sr_public_layout_key(null, $pdo);
    }
    foreach (sr_content_layout_menu_slots() as $settingKey) {
        $settings[$settingKey] = sr_content_clean_layout_menu_key((string) ($settings[$settingKey] ?? ''));
    }
    $settings['series_enabled'] = sr_content_bool_setting($settings['series_enabled'] ?? true);
    $settings['member_submission_enabled'] = sr_content_bool_setting($settings['member_submission_enabled'] ?? false);
    $settings['member_submission_default_review_required'] = sr_content_bool_setting($settings['member_submission_default_review_required'] ?? false);
    $rewardAssetModule = sr_content_clean_slug((string) ($settings['member_submission_author_reward_asset_module'] ?? ''));
    $settings['member_submission_author_reward_asset_module'] = isset(sr_content_asset_modules($pdo)[$rewardAssetModule]) ? $rewardAssetModule : '';
    $settings['member_submission_author_reward_amount'] = min(999999999, max(0, (int) ($settings['member_submission_author_reward_amount'] ?? 0)));
    $settings['member_submission_author_reward_enabled'] = sr_content_bool_setting($settings['member_submission_author_reward_enabled'] ?? false);
    $settings['reaction_preset_key'] = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $settings['reaction_preset_key'] ?? '') : '';
    $settings['reaction_comment_preset_key'] = function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $settings['reaction_comment_preset_key'] ?? '') : '';

    return $settings;
}

function sr_content_default_layout_key(PDO $pdo, ?array $site = null): string
{
    $layoutKey = sr_public_layout_normalize_key((string) (sr_content_settings($pdo)['layout_key'] ?? ''));
    if ($layoutKey !== '' && isset(sr_public_layout_options($pdo)[$layoutKey])) {
        return $layoutKey;
    }

    return sr_public_layout_key($site, $pdo);
}

function sr_content_public_layout_context(array $settings, array $context = []): array
{
    $layoutKey = sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? ''));
    if ($layoutKey !== '') {
        $context['layout_key'] = $layoutKey;
    }
    $context['style_profile'] = 'module';
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = '/modules/content/assets/reset.css';
    $stylesheets[] = '/modules/content/assets/ui-kit.css';
    $layoutStylesheet = sr_public_layout_module_stylesheet($layoutKey);
    if ($layoutStylesheet !== '') {
        $stylesheets[] = $layoutStylesheet;
    }
    $stylesheets[] = '/modules/content/assets/module.css';
    $context['stylesheets'] = array_values(array_unique($stylesheets));
    $scripts = is_array($context['scripts'] ?? null) ? $context['scripts'] : [];
    $scripts[] = '/modules/content/assets/module.js';
    $context['scripts'] = array_values(array_unique($scripts));

    $siteMenus = [];
    foreach (sr_content_layout_menu_slots() as $slotKey => $settingKey) {
        $siteMenus[$slotKey] = sr_content_clean_layout_menu_key((string) ($settings[$settingKey] ?? ($slotKey === 'primary' ? 'header' : '')));
    }

    $context['site_menus'] = array_merge(is_array($context['site_menus'] ?? null) ? $context['site_menus'] : [], $siteMenus);

    return $context;
}

function sr_content_ui_kit_layout_context(array $settings, array $context = []): array
{
    $context = sr_content_public_layout_context($settings, $context);
    $stylesheets = is_array($context['stylesheets'] ?? null) ? $context['stylesheets'] : [];
    $stylesheets[] = '/modules/content/assets/ui-kit.css';
    $stylesheets[] = '/modules/content/assets/ui-kit-layout.css';
    $context['stylesheets'] = array_values(array_unique($stylesheets));

    return $context;
}

function sr_content_primary_menu_fallback_links(PDO $pdo): array
{
    $links = [];
    foreach (sr_content_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_content_group_key_is_valid($groupKey)) {
            continue;
        }

        $label = trim((string) ($group['title'] ?? ''));
        $links[] = [
            'label' => $label !== '' ? $label : $groupKey,
            'url' => sr_content_group_path($groupKey),
        ];
    }

    return $links;
}

function sr_content_layout_menu_links(PDO $pdo, string $menuKey): array
{
    return $menuKey === 'sr_content_groups' ? sr_content_primary_menu_fallback_links($pdo) : [];
}

function sr_content_layout_menu_html(PDO $pdo, string $menuKey, string $slotKey): string
{
    $links = sr_content_layout_menu_links($pdo, $menuKey);
    if ($links === []) {
        return '';
    }

    $currentPath = '/' . trim(sr_request_path(), '/');
    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
    $currentQuery = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY);
    parse_str(is_string($currentQuery) ? $currentQuery : '', $currentQueryParams);
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
        $currentAttribute = $linkPath === $currentPath && $queryMatches ? ' aria-current="page"' : '';
        $html .= '<li class="sr-site-menu-item"><a class="sr-site-menu-link" href="' . sr_e(sr_url($url)) . '"' . $currentAttribute . '>' . sr_e($label) . '</a></li>';
    }

    $html .= '</ul></div>';

    return $html;
}

function sr_content_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'content' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('콘텐츠 모듈이 등록되어 있지 않습니다.');
    }

    $rows = [
        ['editor', sr_editor_normalize_key((string) ($settings['editor'] ?? 'textarea')), 'string'],
        ['editor_toolbar_preset', sr_content_toolbar_preset_key((string) ($settings['editor_toolbar_preset'] ?? 'content_basic')), 'string'],
        ['plain_text_auto_link_urls', !empty($settings['plain_text_auto_link_urls']) ? '1' : '0', 'bool'],
        ['secret_comments_enabled', !empty($settings['secret_comments_enabled']) ? '1' : '0', 'bool'],
        ['once_history_policy', sr_content_once_history_policy((string) ($settings['once_history_policy'] ?? 'all_access')), 'string'],
        ['layout_key', sr_public_layout_normalize_key((string) ($settings['layout_key'] ?? 'content.basic')), 'string'],
        ['layout_primary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_primary_menu_key'] ?? 'header')), 'string'],
        ['layout_secondary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_secondary_menu_key'] ?? '')), 'string'],
        ['layout_tertiary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_tertiary_menu_key'] ?? '')), 'string'],
        ['layout_quaternary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_quaternary_menu_key'] ?? '')), 'string'],
        ['layout_quinary_menu_key', sr_content_clean_layout_menu_key((string) ($settings['layout_quinary_menu_key'] ?? '')), 'string'],
        ['series_enabled', !empty($settings['series_enabled']) ? '1' : '0', 'bool'],
        ['member_submission_enabled', !empty($settings['member_submission_enabled']) ? '1' : '0', 'bool'],
        ['member_submission_default_review_required', !empty($settings['member_submission_default_review_required']) ? '1' : '0', 'bool'],
        ['member_submission_author_reward_enabled', !empty($settings['member_submission_author_reward_enabled']) ? '1' : '0', 'bool'],
        ['member_submission_author_reward_asset_module', sr_content_clean_slug((string) ($settings['member_submission_author_reward_asset_module'] ?? '')), 'string'],
        ['member_submission_author_reward_amount', (string) min(999999999, max(0, (int) ($settings['member_submission_author_reward_amount'] ?? 0))), 'integer'],
        ['reaction_preset_key', function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $settings['reaction_preset_key'] ?? '') : '', 'string'],
        ['reaction_comment_preset_key', function_exists('sr_reaction_setting_preset_key') ? sr_reaction_setting_preset_key($pdo, $settings['reaction_comment_preset_key'] ?? '') : '', 'string'],
    ];
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    foreach ($rows as $row) {
        $stmt->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => (string) $row[0],
            'setting_value' => (string) $row[1],
            'value_type' => (string) $row[2],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
    sr_clear_module_settings_cache('content');
}

function sr_content_once_history_policy_values(): array
{
    return [
        'all_access' => '결제/쿠폰 이력이 있으면 재결제 없음',
        'asset_any' => '결제 이력만 재결제 없음 (쿠폰 제외)',
        'current_asset_once' => '현재 결제수단 이력만 재결제 없음',
    ];
}

function sr_content_once_history_policy(string $policy): string
{
    return array_key_exists($policy, sr_content_once_history_policy_values()) ? $policy : 'all_access';
}

function sr_content_editor_key(PDO $pdo): string
{
    $settings = sr_content_settings($pdo);
    return sr_editor_effective_key($pdo, (string) $settings['editor']);
}

function sr_content_editor_toolbar_preset(PDO $pdo): string
{
    $settings = sr_content_settings($pdo);
    sr_editor_effective_key($pdo, (string) $settings['editor']);
    return sr_content_toolbar_preset_key((string) ($settings['editor_toolbar_preset'] ?? 'content_basic'));
}

function sr_content_html_body_enabled(PDO $pdo): bool
{
    return sr_content_editor_key($pdo) === 'ckeditor';
}

function sr_content_body_html(array $page, ?array $settings = null, ?PDO $pdo = null): string
{
    $linkPlainUrls = sr_content_bool_setting($settings['plain_text_auto_link_urls'] ?? $page['plain_text_auto_link_urls'] ?? false);

    $html = sr_body_text_html($page, $linkPlainUrls);
    if ($pdo instanceof PDO && (string) ($page['body_format'] ?? 'plain') === 'html') {
        $html = sr_embed_manager_render_body_html($pdo, $html, 'content', 'content', (int) ($page['id'] ?? 0), 'body', ['mode' => 'public']);
    }

    return $html;
}

function sr_content_link_card_resolve_many(PDO $pdo, array $types): array
{
    $ids = [];
    foreach ($types['content'] ?? [] as $id) {
        if (preg_match('/\A[1-9][0-9]*\z/', (string) $id) === 1) {
            $ids[(int) $id] = true;
        }
    }
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        'SELECT id, slug, title, summary, status, published_at
         FROM sr_content_items
         WHERE id IN (' . $placeholders . ')'
    );
    $stmt->execute(array_keys($ids));

    $resolved = [];
    foreach ($stmt->fetchAll() as $row) {
        $contentId = (string) (int) ($row['id'] ?? 0);
        $status = (string) ($row['status'] ?? '');
        $isPublished = $status === 'published';
        $resolved[sr_content_link_card_ref_key($contentId)] = [
            'module' => 'content',
            'entity_type' => 'content',
            'entity_id' => $contentId,
            'title' => $isPublished ? (string) ($row['title'] ?? '') : '연결할 수 없는 콘텐츠',
            'summary' => $isPublished ? (string) ($row['summary'] ?? '') : '',
            'url' => $isPublished ? sr_content_path((string) ($row['slug'] ?? '')) : '',
            'status' => $status,
            'broken' => !$isPublished,
        ];
    }

    foreach (array_keys($ids) as $id) {
        $key = sr_content_link_card_ref_key((string) $id);
        if (!isset($resolved[$key])) {
            $resolved[$key] = sr_content_link_card_broken_result((string) $id);
        }
    }

    return $resolved;
}

function sr_content_link_card_broken_result(string $contentId): array
{
    return [
        'module' => 'content',
        'entity_type' => 'content',
        'entity_id' => $contentId,
        'title' => '연결할 수 없는 콘텐츠',
        'summary' => '',
        'url' => '',
        'status' => 'broken',
        'broken' => true,
    ];
}

function sr_content_link_card_ref_key(string $contentId): string
{
    return 'content:content:' . $contentId;
}

function sr_content_admin_link_refs(PDO $pdo, bool $brokenOnly = false): array
{
    return [];
}

function sr_content_default_values(?PDO $pdo = null, ?array $site = null, array $groupSettings = []): array
{
    $defaults = sr_content_group_default_settings($site, $pdo);

    $values = [
        'title' => '',
        'content_group_scope' => 'here_only',
        'content_group_id' => 0,
        'slug' => '',
        'summary' => '',
        'cover_image_url' => '',
        'body_text' => '',
        'status' => (string) ($defaults['status'] ?? 'draft'),
        'layout_key' => (string) ($defaults['layout_key'] ?? ($pdo instanceof PDO ? sr_content_default_layout_key($pdo, $site) : '')),
        'asset_access_enabled' => (int) ($defaults['asset_access_enabled'] ?? 0),
        'asset_module' => (string) ($defaults['asset_module'] ?? ''),
        'asset_access_amount' => (int) ($defaults['asset_access_amount'] ?? 0),
        'asset_access_amounts_json' => (string) ($defaults['asset_access_amounts_json'] ?? ''),
        'asset_access_group_policies_json' => (string) ($defaults['asset_access_group_policies_json'] ?? ''),
        'asset_access_policy_set_id' => (int) ($defaults['asset_access_policy_set_id'] ?? 0),
        'asset_charge_policy' => (string) ($defaults['asset_charge_policy'] ?? 'once'),
        'asset_action_enabled' => (int) ($defaults['asset_action_enabled'] ?? 0),
        'asset_action_module' => (string) ($defaults['asset_action_module'] ?? ''),
        'asset_action_amount' => (int) ($defaults['asset_action_amount'] ?? 0),
        'asset_action_amounts_json' => (string) ($defaults['asset_action_amounts_json'] ?? ''),
        'asset_action_group_policies_json' => (string) ($defaults['asset_action_group_policies_json'] ?? ''),
        'asset_action_policy_set_id' => (int) ($defaults['asset_action_policy_set_id'] ?? 0),
        'asset_action_direction' => (string) ($defaults['asset_action_direction'] ?? 'grant'),
        'asset_action_label' => (string) ($defaults['asset_action_label'] ?? sr_t('content::ui.text.727333ab')),
        'banner_before_content_id' => (int) ($defaults['banner_before_content_id'] ?? 0),
        'banner_after_content_id' => (int) ($defaults['banner_after_content_id'] ?? 0),
        'popup_layer_id' => (int) ($defaults['popup_layer_id'] ?? 0),
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
        'seo_title' => '',
        'seo_description' => '',
    ];

    foreach (sr_content_group_setting_keys() as $settingKey) {
        $values['source_' . (string) $settingKey] = 'content';
    }

    return sr_content_normalize_asset_values($values);
}

function sr_content_reserved_slugs(): array
{
    return ['account', 'action', 'admin', 'api', 'assets', 'community', 'content', 'download', 'group', 'login', 'logout', 'modules', 'pages', 'register'];
}

function sr_content_clean_single_line(string $value, int $maxLength): string
{
    return sr_clean_single_line($value, $maxLength);
}

function sr_content_clean_text(string $value, int $maxLength): string
{
    return sr_clean_text($value, $maxLength);
}

function sr_content_clean_slug(string $value): string
{
    return strtolower(trim($value));
}

function sr_content_slug_is_valid(string $slug): bool
{
    return preg_match('/\A[a-z0-9][a-z0-9-]{1,118}[a-z0-9]\z/', $slug) === 1
        && !in_array($slug, sr_content_reserved_slugs(), true);
}

function sr_content_path(string $slug): string
{
    return '/content/' . rawurlencode($slug);
}

function sr_content_slug_from_request_path(): string
{
    $path = sr_request_path();
    $prefix = '/content/';
    if (!str_starts_with($path, $prefix)) {
        return '';
    }

    $slug = substr($path, strlen($prefix));
    if (!is_string($slug) || $slug === '' || strpos($slug, '/') !== false) {
        return '';
    }

    return sr_content_clean_slug($slug);
}

function sr_content_by_id(PDO $pdo, int $pageId): ?array
{
    if ($pageId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_content_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $pageId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_content_by_slug(PDO $pdo, string $slug): ?array
{
    if (!sr_content_slug_is_valid($slug)) {
        return null;
    }

    sr_content_publish_due_scheduled($pdo);

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_items
         WHERE slug = :slug
         LIMIT 1"
    );
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();

    if (!is_array($row)) {
        return null;
    }

    return sr_content_with_effective_settings($pdo, $row);
}

function sr_content_published_by_slug(PDO $pdo, string $slug): ?array
{
    $page = sr_content_by_slug($pdo, $slug);

    return is_array($page) && (string) ($page['status'] ?? '') === 'published' ? $page : null;
}

function sr_content_should_count_view(int $contentId): bool
{
    if ($contentId < 1) {
        return false;
    }

    $sessionKey = 'sr_content_viewed_items';
    $viewed = isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : [];
    $contentKey = (string) $contentId;
    if (isset($viewed[$contentKey])) {
        return false;
    }

    $viewed[$contentKey] = time();
    if (count($viewed) > 500) {
        asort($viewed);
        $viewed = array_slice($viewed, -500, null, true);
    }
    $_SESSION[$sessionKey] = $viewed;

    return true;
}

function sr_content_increment_view_count(PDO $pdo, int $contentId): void
{
    if ($contentId < 1) {
        return;
    }

    $stmt = $pdo->prepare('UPDATE sr_content_items SET view_count = view_count + 1 WHERE id = :id');
    $stmt->execute(['id' => $contentId]);
}

function sr_content_slug_exists(PDO $pdo, string $slug, int $exceptPageId = 0): bool
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_items
         WHERE slug = :slug
           AND id <> :except_id
         LIMIT 1'
    );
    $stmt->execute([
        'slug' => $slug,
        'except_id' => $exceptPageId,
    ]);

    return is_array($stmt->fetch());
}

function sr_content_admin_multi_filter_values(string $key, array $allowedValues): array
{
    $rawValues = $_GET[$key] ?? [];
    if (!is_array($rawValues)) {
        $rawValues = [$rawValues];
    }

    $allowedMap = array_fill_keys(array_map('strval', $allowedValues), true);
    $selected = [];
    foreach ($rawValues as $rawValue) {
        $value = is_scalar($rawValue) ? trim((string) $rawValue) : '';
        if ($value === '' || !isset($allowedMap[$value])) {
            continue;
        }
        $selected[$value] = $value;
    }

    return array_values($selected);
}

function sr_content_admin_single_filter_values(string $key, array $allowedValues): array
{
    $values = sr_content_admin_multi_filter_values($key, $allowedValues);

    return $values === [] ? [] : [(string) $values[0]];
}

function sr_content_admin_filters(): array
{
    $statuses = sr_content_admin_multi_filter_values('status', sr_content_allowed_statuses());

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'title', 'slug'], true)) {
        $field = 'all';
    }

    return [
        'status' => $statuses,
        'content_group_id' => (int) sr_get_string('content_group_id', 20),
        'field' => $field,
        'q' => sr_content_clean_single_line(sr_get_string('q', 120), 120),
    ];
}

function sr_content_admin_status_counts(PDO $pdo): array
{
    sr_content_publish_due_scheduled($pdo);
    $counts = [
        'total' => 0,
        'draft' => 0,
        'scheduled' => 0,
        'published' => 0,
        'hidden' => 0,
    ];

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_content_items GROUP BY status');
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

function sr_content_admin_query_parts(array $filters): array
{
    $where = [];
    $params = [];
    $statuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    if ($statuses !== []) {
        $placeholders = [];
        foreach (array_values($statuses) as $index => $status) {
            $paramKey = 'status_' . (string) $index;
            $placeholders[] = ':' . $paramKey;
            $params[$paramKey] = (string) $status;
        }
        $where[] = 'p.status IN (' . implode(', ', $placeholders) . ')';
    }

    if ((int) ($filters['content_group_id'] ?? 0) > 0) {
        $where[] = 'p.content_group_id = :content_group_id';
        $params['content_group_id'] = (int) $filters['content_group_id'];
    }

    if ((string) ($filters['q'] ?? '') !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 'p.title LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } elseif ($field === 'slug') {
            $where[] = 'p.slug LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } else {
            $where[] = '(p.title LIKE :title_keyword OR p.slug LIKE :slug_keyword)';
            $params['title_keyword'] = '%' . (string) $filters['q'] . '%';
            $params['slug_keyword'] = '%' . (string) $filters['q'] . '%';
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_content_admin_sort_options(): array
{
    return [
        'title' => [
            'label' => '제목',
            'columns' => ['p.title', 'p.id'],
        ],
        'content_group' => [
            'label' => '콘텐츠 그룹',
            'columns' => ['g.title', 'p.id'],
        ],
        'slug' => [
            'label' => '주소 이름',
            'columns' => ['p.slug', 'p.id'],
        ],
        'status' => [
            'label' => '상태',
            'columns' => ['p.status', 'p.id'],
        ],
        'asset_access' => [
            'label' => '유료 열람',
            'columns' => ['p.asset_access_enabled', 'p.asset_module', 'p.asset_access_amount', 'p.id'],
        ],
        'view_count' => [
            'label' => '조회수',
            'columns' => ['p.view_count', 'p.id'],
        ],
        'created_by' => [
            'label' => '작성자',
            'columns' => ["COALESCE(creator.display_name, '')", 'p.id'],
        ],
        'updated_at' => [
            'label' => '수정일',
            'columns' => ['p.updated_at', 'p.id'],
        ],
        'published_at' => [
            'label' => '공개일',
            'columns' => ['p.published_at', 'p.id'],
        ],
    ];
}

function sr_content_admin_default_sort(): array
{
    return [
        'key' => 'updated_at',
        'dir' => 'desc',
    ];
}

function sr_content_admin_sort_from_request(): array
{
    $defaultSort = sr_content_admin_default_sort();
    $sortKey = sr_get_string('sort', 40);
    $sortDir = strtolower(sr_get_string('dir', 10));
    $sortOptions = sr_content_admin_sort_options();

    if (!isset($sortOptions[$sortKey])) {
        $sortKey = (string) $defaultSort['key'];
        $sortDir = (string) $defaultSort['dir'];
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = (string) $defaultSort['dir'];
    }

    return [
        'key' => $sortKey,
        'dir' => $sortDir,
        'is_default' => $sortKey === (string) $defaultSort['key'] && $sortDir === (string) $defaultSort['dir'],
    ];
}

function sr_content_admin_sort_order_sql(array $sort): string
{
    $sortOptions = sr_content_admin_sort_options();
    $defaultSort = sr_content_admin_default_sort();
    $sortKey = (string) ($sort['key'] ?? $defaultSort['key']);
    $sortDir = strtolower((string) ($sort['dir'] ?? $defaultSort['dir']));
    if (!isset($sortOptions[$sortKey])) {
        $sortKey = (string) $defaultSort['key'];
        $sortDir = (string) $defaultSort['dir'];
    }
    if (!in_array($sortDir, ['asc', 'desc'], true)) {
        $sortDir = (string) $defaultSort['dir'];
    }

    $orderParts = [];
    foreach ($sortOptions[$sortKey]['columns'] as $column) {
        $orderParts[] = $column . ' ' . strtoupper($sortDir);
    }

    return ' ORDER BY ' . implode(', ', $orderParts);
}

function sr_content_admin_sort_url(string $sortKey = '', string $sortDir = ''): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    $contextPath = function_exists('sr_request_path') ? sr_request_path() : $path;
    $queryString = (string) (parse_url($uri, PHP_URL_QUERY) ?: '');
    $params = [];
    if ($queryString !== '') {
        parse_str($queryString, $params);
        if (!is_array($params)) {
            $params = [];
        }
        if (function_exists('sr_admin_normalize_query_params')) {
            $params = sr_admin_normalize_query_params($params, $contextPath);
        }
    }

    unset($params['page'], $params['sort'], $params['dir']);

    $sortOptions = sr_content_admin_sort_options();
    $defaultSort = sr_content_admin_default_sort();
    $sortDir = strtolower($sortDir);
    if (isset($sortOptions[$sortKey]) && in_array($sortDir, ['asc', 'desc'], true)) {
        if ($sortKey !== (string) $defaultSort['key'] || $sortDir !== (string) $defaultSort['dir']) {
            $params['sort'] = $sortKey;
            $params['dir'] = $sortDir;
        }
    }

    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

    return sr_url($path . ($query !== '' ? '?' . $query : ''));
}

function sr_content_admin_sort_header_html(string $label, string $sortKey, array $currentSort): string
{
    $currentKey = (string) ($currentSort['key'] ?? '');
    $currentDir = (string) ($currentSort['dir'] ?? 'desc');
    $isCurrent = $currentKey === $sortKey;
    $ascActive = $isCurrent && $currentDir === 'asc';
    $descActive = $isCurrent && $currentDir === 'desc';

    $ascClass = 'btn btn-sm admin-sort-button btn-group-start ' . ($ascActive ? 'btn-solid-primary' : 'btn-solid-light');
    $descClass = 'btn btn-sm admin-sort-button btn-group-end ' . ($descActive ? 'btn-solid-primary' : 'btn-solid-light');
    $ascLabel = $label . ' 오름차순 정렬';
    $descLabel = $label . ' 내림차순 정렬';

    return '<div class="admin-sort-header">'
        . '<span class="admin-sort-label">' . sr_e($label) . '</span>'
        . '<span class="admin-sort-button-group" role="group" aria-label="' . sr_e($label . ' 정렬 방향') . '">'
        . '<a href="' . sr_e(sr_content_admin_sort_url($sortKey, 'asc')) . '" class="' . sr_e($ascClass) . '" aria-label="' . sr_e($ascLabel) . '" title="' . sr_e($ascLabel) . '"' . ($ascActive ? ' aria-current="true"' : '') . '>' . sr_material_icon_html('arrow_upward') . '</a>'
        . '<a href="' . sr_e(sr_content_admin_sort_url($sortKey, 'desc')) . '" class="' . sr_e($descClass) . '" aria-label="' . sr_e($descLabel) . '" title="' . sr_e($descLabel) . '"' . ($descActive ? ' aria-current="true"' : '') . '>' . sr_material_icon_html('arrow_downward') . '</a>'
        . '</span>'
        . ($isCurrent ? '<span class="sr-only">현재 ' . sr_e($currentDir === 'asc' ? '오름차순' : '내림차순') . '</span>' : '')
        . '</div>';
}

function sr_content_admin_sort_aria(string $sortKey, array $currentSort): string
{
    if ((string) ($currentSort['key'] ?? '') !== $sortKey) {
        return '';
    }

    return (string) ($currentSort['dir'] ?? 'desc') === 'asc' ? ' aria-sort="ascending"' : ' aria-sort="descending"';
}

function sr_content_admin_count(PDO $pdo, array $filters): int
{
    sr_content_publish_due_scheduled($pdo);
    $queryParts = sr_content_admin_query_parts($filters);
    $sql = 'SELECT COUNT(*) AS count_value
            FROM sr_content_items p
            LEFT JOIN sr_content_groups g ON g.id = p.content_group_id
            LEFT JOIN sr_member_accounts creator ON creator.id = p.created_by
            LEFT JOIN sr_member_accounts updater ON updater.id = p.updated_by';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_content_admin_list(PDO $pdo, array $filters, int $limit = 0, int $offset = 0, array $sort = []): array
{
    sr_content_publish_due_scheduled($pdo);
    $queryParts = sr_content_admin_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $seriesSupported = sr_content_series_supported($pdo);
    $seriesSelectSql = $seriesSupported
        ? 'cs.series_key AS content_series_key, cs.title AS content_series_title,
                   csi.episode_label AS content_series_episode_label, csi.sort_order AS content_series_sort_order,'
        : "'' AS content_series_key, '' AS content_series_title,
                   '' AS content_series_episode_label, 0 AS content_series_sort_order,";
    $seriesJoinSql = $seriesSupported
        ? 'LEFT JOIN sr_content_series_items csi ON csi.active_content_id = p.id
            LEFT JOIN sr_content_series cs ON cs.id = csi.series_id'
        : '';
    $sql = 'SELECT p.*, g.group_key AS content_group_key, g.title AS content_group_title,
                   ' . $seriesSelectSql . '
                   creator.display_name AS created_by_name, updater.display_name AS updated_by_name
            FROM sr_content_items p
            LEFT JOIN sr_content_groups g ON g.id = p.content_group_id
            ' . $seriesJoinSql . '
            LEFT JOIN sr_member_accounts creator ON creator.id = p.created_by
            LEFT JOIN sr_member_accounts updater ON updater.id = p.updated_by';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_content_admin_sort_order_sql($sort);
    if ($limit > 0) {
        $sql .= ' LIMIT :limit_value OFFSET :offset_value';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($params as $paramKey => $paramValue) {
        $stmt->bindValue($paramKey, $paramValue, is_int($paramValue) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    if ($limit > 0) {
        $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
        $stmt->bindValue('offset_value', max(0, $offset), PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_published_contents_for_group(PDO $pdo, int $groupId): array
{
    if ($groupId < 1) {
        return [];
    }

    sr_content_publish_due_scheduled($pdo);

    $stmt = $pdo->prepare(
        "SELECT id, slug, title, summary, cover_image_url, updated_at, published_at
         FROM sr_content_items
         WHERE content_group_id = :group_id
           AND status = 'published'
         ORDER BY published_at DESC, updated_at DESC, id DESC"
    );
    $stmt->execute(['group_id' => $groupId]);

    return $stmt->fetchAll();
}

function sr_content_recent_published_contents(PDO $pdo, int $limit = 20): array
{
    $limit = max(1, min(100, $limit));

    sr_content_publish_due_scheduled($pdo);

    $stmt = $pdo->query(
        "SELECT p.id, p.slug, p.title, p.summary, p.cover_image_url, p.updated_at, p.published_at,
                g.group_key AS content_group_key, g.title AS content_group_title
         FROM sr_content_items p
         LEFT JOIN sr_content_groups g ON g.id = p.content_group_id AND g.status = 'enabled'
         WHERE p.status = 'published'
         ORDER BY p.published_at DESC, p.updated_at DESC, p.id DESC
         LIMIT " . $limit
    );

    return $stmt->fetchAll();
}

function sr_content_optional_table_exists(PDO $pdo, string $tableName): bool
{
    static $cache = [];
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName)) {
        return false;
    }
    if (isset($cache[$tableName])) {
        return $cache[$tableName];
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        $cache[$tableName] = true;
    } catch (Throwable) {
        $cache[$tableName] = false;
    }

    return $cache[$tableName];
}

function sr_content_optional_column_exists(PDO $pdo, string $tableName, string $columnName): bool
{
    static $cache = [];
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $tableName) || !preg_match('/\A[a-zA-Z0-9_]+\z/', $columnName)) {
        return false;
    }

    $cacheKey = $tableName . '.' . $columnName;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
             FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name'
        );
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);
        $cache[$cacheKey] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

function sr_content_optional_count(PDO $pdo, string $tableName, string $whereSql, array $params = []): int
{
    if (!sr_content_optional_table_exists($pdo, $tableName)) {
        return 0;
    }

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM ' . $tableName . ' WHERE ' . $whereSql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

function sr_content_record_storage_cleanup_failure(PDO $pdo, string $sourceType, int $sourceId, string $driver, string $key, string $errorMessage): void
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_storage_cleanup_failures') || !sr_storage_key_is_safe($key)) {
        return;
    }

    $driver = in_array($driver, ['local', 's3'], true) ? $driver : 'local';
    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_content_storage_cleanup_failures
                (source_type, source_id, storage_driver, storage_key, status, attempt_count, last_error, created_at, updated_at)
             VALUES
                (:source_type, :source_id, :storage_driver, :storage_key, \'pending\', 1, :last_error, :created_at, :updated_at)'
        );
        $stmt->execute([
            'source_type' => sr_content_clean_key($sourceType),
            'source_id' => $sourceId,
            'storage_driver' => $driver,
            'storage_key' => $key,
            'last_error' => sr_content_clean_text($errorMessage, 1000),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    } catch (Throwable $exception) {
        sr_log_exception($exception, 'content_storage_cleanup_failure_record_failed');
    }
}

function sr_content_storage_cleanup_failures(PDO $pdo, int $limit = 50): array
{
    if (!sr_content_optional_table_exists($pdo, 'sr_content_storage_cleanup_failures')) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_content_storage_cleanup_failures
         WHERE status = 'pending'
         ORDER BY updated_at DESC, id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', max(1, min(200, $limit)), PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_retry_storage_cleanup_failure(PDO $pdo, int $failureId): array
{
    if ($failureId < 1 || !sr_content_optional_table_exists($pdo, 'sr_content_storage_cleanup_failures')) {
        return ['ok' => false, 'message' => '저장소 정리 실패 기록을 찾을 수 없습니다.'];
    }

    $stmt = $pdo->prepare("SELECT * FROM sr_content_storage_cleanup_failures WHERE id = :id AND status = 'pending' LIMIT 1");
    $stmt->execute(['id' => $failureId]);
    $failure = $stmt->fetch();
    if (!is_array($failure)) {
        return ['ok' => false, 'message' => '재시도할 저장소 정리 실패 기록을 찾을 수 없습니다.'];
    }

    $driver = (string) ($failure['storage_driver'] ?? 'local');
    $key = (string) ($failure['storage_key'] ?? '');
    $now = sr_now();
    if ($key !== '' && sr_storage_delete($driver, $key)) {
        $stmt = $pdo->prepare(
            "UPDATE sr_content_storage_cleanup_failures
             SET status = 'cleaned',
                 attempt_count = attempt_count + 1,
                 last_error = '',
                 updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute(['updated_at' => $now, 'id' => $failureId]);

        return ['ok' => true, 'message' => '저장소 파일 정리를 완료했습니다.'];
    }

    $stmt = $pdo->prepare(
        "UPDATE sr_content_storage_cleanup_failures
         SET attempt_count = attempt_count + 1,
             last_error = :last_error,
             updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'last_error' => '저장소 파일 정리 재시도에 실패했습니다.',
        'updated_at' => $now,
        'id' => $failureId,
    ]);

    return ['ok' => false, 'message' => '저장소 파일 정리 재시도에 실패했습니다. 저장소 권한 또는 S3 설정을 확인해 주세요.'];
}

function sr_content_clean_key(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_]+/', '_', $value) ?? '';
    $value = trim($value, '_');

    return $value !== '' ? substr($value, 0, 60) : 'unknown';
}

function sr_content_homepage_candidates(PDO $pdo): array
{
    $candidates = [
        [
            'module_key' => 'content',
            'label' => sr_t('content::ui.content.6c84a1b3'),
            'path' => '/content',
            'detail' => '/content',
            'available' => true,
        ],
    ];

    foreach (sr_content_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_content_group_key_is_valid($groupKey)) {
            continue;
        }

        $path = sr_content_group_path($groupKey);
        $candidates[] = [
            'module_key' => 'content',
            'label' => sr_t('content::homepage.group_candidate_label', ['title' => (string) ($group['title'] ?? $groupKey)]),
            'path' => $path,
            'detail' => $path,
            'available' => true,
        ];
    }

    $stmt = $pdo->query(
        "SELECT id, slug, title, updated_at
         FROM sr_content_items
         WHERE status = 'published'
         ORDER BY updated_at DESC, id DESC
         LIMIT 200"
    );

    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_content_slug_is_valid($slug)) {
            continue;
        }

        $path = sr_content_path($slug);
        $candidates[] = [
            'module_key' => 'content',
            'label' => sr_t('content::homepage.candidate_label', ['title' => (string) ($page['title'] ?? $slug)]),
            'path' => $path,
            'detail' => $path,
            'available' => true,
        ];
    }

    return $candidates;
}

function sr_content_homepage_path_is_available(PDO $pdo, string $homePath): ?bool
{
    if ($homePath === '/content') {
        return true;
    }

    if (str_starts_with($homePath, '/content/group?key=')) {
        $query = parse_url($homePath, PHP_URL_QUERY);
        if (!is_string($query)) {
            return false;
        }

        parse_str($query, $params);
        $groupKey = is_string($params['key'] ?? null) ? strtolower(trim((string) $params['key'])) : '';
        return $groupKey !== '' && sr_content_enabled_group_by_key($pdo, $groupKey) !== null;
    }

    $prefix = '/content/';
    if (!str_starts_with($homePath, $prefix)) {
        return null;
    }

    $slug = rawurldecode(substr($homePath, strlen($prefix)));
    if (!is_string($slug) || $slug === '' || strpos($slug, '/') !== false) {
        return false;
    }

    return sr_content_published_by_slug($pdo, sr_content_clean_slug($slug)) !== null;
}
