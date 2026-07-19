<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/public-data-cache.php';

function sr_content_sidebar_group_menu_rows(PDO $pdo): array
{
    $cached = sr_public_data_cache_read('public-side-menu', 'content.groups', 'content_sidebar_groups_v1');
    if (is_array($cached)) {
        return $cached;
    }

    $groups = [];
    foreach (sr_content_enabled_groups($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_content_group_key_is_valid($groupKey)) {
            continue;
        }
        $groups[] = [
            'group_key' => $groupKey,
            'title' => (string) ($group['title'] ?? $groupKey),
        ];
    }
    sr_public_data_cache_write('public-side-menu', 'content.groups', 'content_sidebar_groups_v1', $groups);

    return $groups;
}

function sr_content_sidebar_excerpt(string $value, int $length = 72): string
{
    $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $length ? mb_substr($value, 0, $length, 'UTF-8') . '…' : $value;
    }

    return strlen($value) > $length ? substr($value, 0, $length) . '…' : $value;
}

function sr_content_sidebar_popular_contents(PDO $pdo, int $limit, int $excludeContentId = 0): array
{
    sr_content_publish_due_scheduled($pdo);
    $limit = max(1, min(10, $limit));
    $sql = "SELECT p.id, p.slug, p.title, p.view_count, p.published_at, p.updated_at
            FROM sr_content_items p
            WHERE p.status = 'published'";
    if ($excludeContentId > 0) {
        $sql .= ' AND p.id <> :exclude_content_id';
    }
    $sql .= ' ORDER BY p.view_count DESC, p.published_at DESC, p.id DESC LIMIT :limit_value';
    $stmt = $pdo->prepare($sql);
    if ($excludeContentId > 0) {
        $stmt->bindValue('exclude_content_id', $excludeContentId, PDO::PARAM_INT);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_content_sidebar_recent_comments(PDO $pdo, array $settings, int $limit): array
{
    if (!sr_content_comments_table_exists($pdo)) {
        return [];
    }

    $limit = max(1, min(10, $limit));
    $nicknameJoin = sr_member_nicknames_table_exists($pdo) ? 'LEFT JOIN sr_member_nicknames n ON n.account_id = a.id' : '';
    $nicknameSelect = sr_member_nicknames_table_exists($pdo) ? 'n.nickname AS author_nickname,' : "'' AS author_nickname,";
    $stmt = $pdo->prepare(
        "SELECT c.id, c.content_id, c.author_account_id, c.body_text, c.created_at, c.author_public_name_snapshot,
                p.slug AS content_slug, p.title AS content_title, p.comment_editor_key,
                a.display_name AS author_display_name, " . $nicknameSelect . " a.status AS author_account_status
         FROM sr_content_comments c
         INNER JOIN sr_content_items p ON p.id = c.content_id
            AND p.status = 'published'
            AND (p.asset_access_enabled <> 1 OR p.asset_access_amount <= 0)
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         " . $nicknameJoin . "
         WHERE c.status = 'published'
           AND c.is_secret = 0
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT :limit_value"
    );
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $memberSettings = sr_member_settings($pdo);
    $comments = [];
    foreach ($stmt->fetchAll() as $comment) {
        $snapshot = trim((string) ($comment['author_public_name_snapshot'] ?? ''));
        $comment['author_public_name'] = !in_array((string) ($comment['author_account_status'] ?? ''), ['withdrawn', 'anonymized'], true) && $snapshot !== ''
            ? $snapshot
            : sr_member_public_name([
                'display_name' => (string) ($comment['author_display_name'] ?? ''),
                'nickname' => (string) ($comment['author_nickname'] ?? ''),
                'status' => (string) ($comment['author_account_status'] ?? ''),
            ], $memberSettings, '회원');
        $commentSettings = $settings;
        $commentSettings['comment_editor_key'] = (string) ($comment['comment_editor_key'] ?? 'inherit');
        $comment['excerpt'] = sr_content_sidebar_excerpt(sr_content_comment_body_plain_text($pdo, $comment, $commentSettings));
        $comments[] = $comment;
    }

    return $comments;
}

function sr_content_sidebar_menu_context(PDO $pdo, array $settings, string $currentGroupKey = ''): array
{
    $menuType = sr_content_sidebar_menu_type((string) ($settings['sidebar_menu_type'] ?? 'groups'));
    if ($menuType === 'none') {
        return ['title' => '', 'html' => ''];
    }
    if ($menuType === 'site_menu') {
        $menuKey = sr_content_clean_layout_menu_key((string) ($settings['sidebar_site_menu_key'] ?? ''));
        if ($menuKey === '' || !sr_module_enabled($pdo, 'site_menu') || !is_file(SR_ROOT . '/modules/site_menu/helpers.php')) {
            return ['title' => '', 'html' => ''];
        }
        require_once SR_ROOT . '/modules/site_menu/helpers.php';
        $tree = function_exists('sr_site_menu_tree') ? sr_site_menu_tree($pdo, $menuKey) : [];

        return [
            'title' => trim((string) ($tree['label'] ?? '메뉴')),
            'html' => function_exists('sr_site_menu_render') ? sr_site_menu_render($pdo, $menuKey, 'content_sidebar') : '',
        ];
    }

    $html = '<nav class="content-sidebar-nav" aria-label="콘텐츠 그룹"><ul>';
    foreach (sr_content_sidebar_group_menu_rows($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_content_group_key_is_valid($groupKey)) {
            continue;
        }
        $html .= '<li><a href="' . sr_e(sr_url(sr_content_group_path($groupKey))) . '"'
            . ($currentGroupKey === $groupKey ? ' aria-current="page"' : '') . '>'
            . sr_e((string) ($group['title'] ?? $groupKey)) . '</a></li>';
    }
    $html .= '</ul></nav>';

    return ['title' => '콘텐츠 그룹', 'html' => $html];
}

function sr_content_sidebar_context(PDO $pdo, array $settings, array $subject = []): array
{
    if (empty($settings['sidebar_enabled'])) {
        return ['enabled' => false];
    }

    $currentContentId = max(0, (int) ($subject['id'] ?? 0));
    $currentGroupKey = trim((string) ($subject['content_group_key'] ?? $subject['group_key'] ?? ''));
    return [
        'enabled' => true,
        'menu' => sr_content_sidebar_menu_context($pdo, $settings, $currentGroupKey),
        'popular' => sr_content_sidebar_popular_contents($pdo, (int) ($settings['sidebar_popular_limit'] ?? 5), $currentContentId),
        'comments' => sr_content_sidebar_recent_comments($pdo, $settings, (int) ($settings['sidebar_comments_limit'] ?? 5)),
    ];
}
