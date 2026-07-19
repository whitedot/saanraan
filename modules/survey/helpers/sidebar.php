<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/core/helpers/public-data-cache.php';

function sr_survey_sidebar_group_menu_rows_from_cache(array $rows): ?array
{
    $normalized = [];
    foreach ($rows as $row) {
        if (!is_array($row)
            || !is_string($row['group_key'] ?? null)
            || !sr_survey_group_key_is_valid((string) $row['group_key'])
            || !is_string($row['title'] ?? null)
        ) {
            return null;
        }
        $normalized[] = [
            'group_key' => (string) $row['group_key'],
            'title' => (string) $row['title'],
        ];
    }

    return $normalized;
}

function sr_survey_sidebar_group_menu_rows(PDO $pdo): array
{
    $cacheGeneration = sr_public_data_cache_generation('public-side-menu', 'survey.groups', 'survey_sidebar_groups_v1');
    $cachedPayload = sr_public_data_cache_read(
        'public-side-menu',
        'survey.groups',
        'survey_sidebar_groups_v1',
        $cacheGeneration
    );
    $cached = is_array($cachedPayload) ? sr_survey_sidebar_group_menu_rows_from_cache($cachedPayload) : null;
    if (is_array($cached)) {
        return $cached;
    }
    if (is_array($cachedPayload)) {
        sr_survey_sidebar_clear_group_menu_cache();
        $cacheGeneration = sr_public_data_cache_generation('public-side-menu', 'survey.groups', 'survey_sidebar_groups_v1');
    }

    $groups = [];
    foreach (sr_survey_groups($pdo, true) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_survey_group_key_is_valid($groupKey)) {
            continue;
        }
        $groups[] = [
            'group_key' => $groupKey,
            'title' => (string) ($group['title'] ?? $groupKey),
        ];
    }
    sr_public_data_cache_write(
        'public-side-menu',
        'survey.groups',
        'survey_sidebar_groups_v1',
        $groups,
        $cacheGeneration
    );

    return $groups;
}

function sr_survey_sidebar_excerpt(string $value, int $length = 72): string
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

function sr_survey_sidebar_popular_surveys(PDO $pdo, int $limit, int $excludeSurveyId = 0): array
{
    $limit = max(1, min(10, $limit));
    $sql = "SELECT id, survey_key, title, view_count, updated_at
            FROM sr_survey_forms
            WHERE status = 'active'
              AND deleted_at IS NULL
              AND public_listed = 1
              AND (starts_at IS NULL OR starts_at <= :now_start)
              AND (ends_at IS NULL OR ends_at >= :now_end)";
    if ($excludeSurveyId > 0) {
        $sql .= ' AND id <> :exclude_survey_id';
    }
    $sql .= ' ORDER BY view_count DESC, updated_at DESC, id DESC LIMIT :limit_value';
    $stmt = $pdo->prepare($sql);
    $now = sr_now();
    $stmt->bindValue('now_start', $now);
    $stmt->bindValue('now_end', $now);
    if ($excludeSurveyId > 0) {
        $stmt->bindValue('exclude_survey_id', $excludeSurveyId, PDO::PARAM_INT);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_survey_sidebar_recent_comments(PDO $pdo, array $settings, int $limit): array
{
    $viewer = sr_member_current_account($pdo);
    if (!sr_survey_comments_table_exists($pdo)
        || !is_array($viewer)
        || !empty($settings['identity_view_required'])
        || !empty($settings['identity_view_adult_required'])) {
        return [];
    }

    $limit = max(1, min(10, $limit));
    $stmt = $pdo->prepare(
        "SELECT c.id, c.survey_id, c.author_account_id, c.body_text, c.created_at, c.author_public_name_snapshot,
                s.survey_key, s.title AS survey_title, s.comment_editor_key,
                a.display_name AS author_display_name, a.status AS author_account_status
         FROM sr_survey_comments c
         INNER JOIN sr_survey_forms s ON s.id = c.survey_id
            AND s.status = 'active'
            AND s.deleted_at IS NULL
            AND s.public_listed = 1
            AND s.comments_enabled = 1
            AND (s.starts_at IS NULL OR s.starts_at <= :now_start)
            AND (s.ends_at IS NULL OR s.ends_at >= :now_end)
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         WHERE c.status = 'published'
           AND c.is_secret = 0
           AND EXISTS (
               SELECT 1
               FROM sr_survey_responses viewer_response
               WHERE viewer_response.survey_id = s.id
                 AND viewer_response.account_id = :viewer_account_id
                 AND viewer_response.submitted_at IS NOT NULL
           )
         ORDER BY c.created_at DESC, c.id DESC
         LIMIT :limit_value"
    );
    $now = sr_now();
    $stmt->bindValue('now_start', $now);
    $stmt->bindValue('now_end', $now);
    $stmt->bindValue('viewer_account_id', (int) ($viewer['id'] ?? 0), PDO::PARAM_INT);
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
                'status' => (string) ($comment['author_account_status'] ?? ''),
            ], $memberSettings, '회원');
        $commentSettings = $settings;
        $commentSettings['comment_editor_key'] = (string) ($comment['comment_editor_key'] ?? 'inherit');
        $comment['excerpt'] = sr_survey_sidebar_excerpt(sr_survey_comment_body_plain_text($pdo, $comment, $commentSettings));
        $comments[] = $comment;
    }

    return $comments;
}

function sr_survey_sidebar_menu_context(PDO $pdo, array $settings, string $currentGroupKey = ''): array
{
    $menuType = sr_survey_sidebar_menu_type((string) ($settings['sidebar_menu_type'] ?? 'groups'));
    if ($menuType === 'none') {
        return ['title' => '', 'html' => ''];
    }
    if ($menuType === 'site_menu') {
        $menuKey = sr_survey_clean_layout_menu_key((string) ($settings['sidebar_site_menu_key'] ?? ''));
        if ($menuKey === '') {
            return ['title' => '', 'html' => ''];
        }
        $tree = sr_module_contract_invoke(
            $pdo,
            'site_menu',
            'site-menu-provider.php',
            'tree_function',
            [$menuKey],
            []
        );
        $tree = is_array($tree) ? $tree : [];
        $html = sr_module_contract_invoke(
            $pdo,
            'site_menu',
            'site-menu-provider.php',
            'render_function',
            [$menuKey, 'survey_sidebar'],
            ''
        );

        return [
            'title' => trim((string) ($tree['label'] ?? '메뉴')),
            'html' => is_string($html) ? $html : '',
        ];
    }

    $html = '<nav class="survey-sidebar-nav" aria-label="설문 그룹"><ul>';
    foreach (sr_survey_sidebar_group_menu_rows($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_survey_group_key_is_valid($groupKey)) {
            continue;
        }
        $html .= '<li><a href="' . sr_e(sr_url('/survey/list?group=' . rawurlencode($groupKey))) . '"'
            . ($currentGroupKey === $groupKey ? ' aria-current="page"' : '') . '>'
            . sr_e((string) ($group['title'] ?? $groupKey)) . '</a></li>';
    }
    $html .= '</ul></nav>';

    return ['title' => '설문 그룹', 'html' => $html];
}

function sr_survey_sidebar_context(PDO $pdo, array $settings, array $subject = []): array
{
    if (empty($settings['sidebar_enabled'])) {
        return ['enabled' => false];
    }

    $currentGroupKey = trim((string) ($subject['group_key'] ?? ''));
    if ($currentGroupKey === '' && (int) ($subject['survey_group_id'] ?? 0) > 0) {
        $currentGroup = sr_survey_group_by_id($pdo, (int) $subject['survey_group_id']);
        $currentGroupKey = is_array($currentGroup) ? (string) ($currentGroup['group_key'] ?? '') : '';
    }

    return [
        'enabled' => true,
        'menu' => sr_survey_sidebar_menu_context($pdo, $settings, $currentGroupKey),
        'popular' => sr_survey_sidebar_popular_surveys($pdo, (int) ($settings['sidebar_popular_limit'] ?? 5), max(0, (int) ($subject['id'] ?? 0))),
        'comments' => sr_survey_sidebar_recent_comments($pdo, $settings, (int) ($settings['sidebar_comments_limit'] ?? 5)),
    ];
}
