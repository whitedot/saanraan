<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/core/helpers/public-data-cache.php';

function sr_quiz_sidebar_group_menu_rows_from_cache(array $rows): ?array
{
    $normalized = [];
    foreach ($rows as $row) {
        if (!is_array($row)
            || !is_string($row['group_key'] ?? null)
            || !sr_quiz_group_key_is_valid((string) $row['group_key'])
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

function sr_quiz_sidebar_group_menu_rows(PDO $pdo): array
{
    $cacheGeneration = sr_public_data_cache_generation('public-side-menu', 'quiz.groups', 'quiz_sidebar_groups_v1');
    $cachedPayload = sr_public_data_cache_read(
        'public-side-menu',
        'quiz.groups',
        'quiz_sidebar_groups_v1',
        $cacheGeneration
    );
    $cached = is_array($cachedPayload) ? sr_quiz_sidebar_group_menu_rows_from_cache($cachedPayload) : null;
    if (is_array($cached)) {
        return $cached;
    }
    if (is_array($cachedPayload)) {
        sr_quiz_sidebar_clear_group_menu_cache();
        $cacheGeneration = sr_public_data_cache_generation('public-side-menu', 'quiz.groups', 'quiz_sidebar_groups_v1');
    }

    $groups = [];
    foreach (sr_quiz_groups($pdo, true) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_quiz_group_key_is_valid($groupKey)) {
            continue;
        }
        $groups[] = [
            'group_key' => $groupKey,
            'title' => (string) ($group['title'] ?? $groupKey),
        ];
    }
    sr_public_data_cache_write(
        'public-side-menu',
        'quiz.groups',
        'quiz_sidebar_groups_v1',
        $groups,
        $cacheGeneration
    );

    return $groups;
}

function sr_quiz_sidebar_excerpt(string $value, int $length = 72): string
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

function sr_quiz_sidebar_popular_quizzes(PDO $pdo, int $limit, int $excludeQuizId = 0): array
{
    $limit = max(1, min(10, $limit));
    $sql = "SELECT id, quiz_key, title, view_count, created_at
            FROM sr_quiz_sets
            WHERE status = 'active'
              AND deleted_at IS NULL
              AND (starts_at IS NULL OR starts_at <= :now_start)
              AND (ends_at IS NULL OR ends_at >= :now_end)";
    if ($excludeQuizId > 0) {
        $sql .= ' AND id <> :exclude_quiz_id';
    }
    $sql .= ' ORDER BY view_count DESC, created_at DESC, id DESC LIMIT :limit_value';
    $stmt = $pdo->prepare($sql);
    $now = sr_now();
    $stmt->bindValue('now_start', $now);
    $stmt->bindValue('now_end', $now);
    if ($excludeQuizId > 0) {
        $stmt->bindValue('exclude_quiz_id', $excludeQuizId, PDO::PARAM_INT);
    }
    $stmt->bindValue('limit_value', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_quiz_sidebar_recent_comments(PDO $pdo, array $settings, int $limit): array
{
    $viewer = sr_member_current_account($pdo);
    if (!sr_quiz_comments_table_exists($pdo)
        || !is_array($viewer)
        || !empty($settings['identity_view_required'])
        || !empty($settings['identity_view_adult_required'])) {
        return [];
    }

    $limit = max(1, min(10, $limit));
    $stmt = $pdo->prepare(
        "SELECT c.id, c.quiz_id, c.author_account_id, c.body_text, c.created_at, c.author_public_name_snapshot,
                q.quiz_key, q.title AS quiz_title, q.comment_editor_key,
                a.display_name AS author_display_name, a.status AS author_account_status
         FROM sr_quiz_comments c
         INNER JOIN sr_quiz_sets q ON q.id = c.quiz_id
            AND q.status = 'active'
            AND q.deleted_at IS NULL
            AND q.comments_enabled = 1
            AND (q.starts_at IS NULL OR q.starts_at <= :now_start)
            AND (q.ends_at IS NULL OR q.ends_at >= :now_end)
         LEFT JOIN sr_member_accounts a ON a.id = c.author_account_id
         WHERE c.status = 'published'
           AND c.is_secret = 0
           AND EXISTS (
               SELECT 1
               FROM sr_quiz_attempts viewer_attempt
               WHERE viewer_attempt.quiz_id = q.id
                 AND viewer_attempt.account_id = :viewer_account_id
                 AND viewer_attempt.submitted_at IS NOT NULL
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
        $comment['excerpt'] = sr_quiz_sidebar_excerpt(sr_quiz_comment_body_plain_text($pdo, $comment, $commentSettings));
        $comments[] = $comment;
    }

    return $comments;
}

function sr_quiz_sidebar_menu_context(PDO $pdo, array $settings, string $currentGroupKey = ''): array
{
    $menuType = sr_quiz_sidebar_menu_type((string) ($settings['sidebar_menu_type'] ?? 'groups'));
    if ($menuType === 'none') {
        return ['title' => '', 'html' => ''];
    }
    if ($menuType === 'site_menu') {
        $menuKey = sr_quiz_clean_layout_menu_key((string) ($settings['sidebar_site_menu_key'] ?? ''));
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
            [$menuKey, 'quiz_sidebar'],
            ''
        );

        return [
            'title' => trim((string) ($tree['label'] ?? '메뉴')),
            'html' => is_string($html) ? $html : '',
        ];
    }

    $html = '<nav class="quiz-sidebar-nav" aria-label="퀴즈 그룹"><ul>';
    foreach (sr_quiz_sidebar_group_menu_rows($pdo) as $group) {
        $groupKey = (string) ($group['group_key'] ?? '');
        if (!sr_quiz_group_key_is_valid($groupKey)) {
            continue;
        }
        $html .= '<li><a href="' . sr_e(sr_url('/quiz/list?group=' . rawurlencode($groupKey))) . '"'
            . ($currentGroupKey === $groupKey ? ' aria-current="page"' : '') . '>'
            . sr_e((string) ($group['title'] ?? $groupKey)) . '</a></li>';
    }
    $html .= '</ul></nav>';

    return ['title' => '퀴즈 그룹', 'html' => $html];
}

function sr_quiz_sidebar_context(PDO $pdo, array $settings, array $subject = []): array
{
    if (empty($settings['sidebar_enabled'])) {
        return ['enabled' => false];
    }

    $currentGroupKey = trim((string) ($subject['group_key'] ?? ''));
    if ($currentGroupKey === '' && (int) ($subject['quiz_group_id'] ?? 0) > 0) {
        $currentGroup = sr_quiz_group_by_id($pdo, (int) $subject['quiz_group_id']);
        $currentGroupKey = is_array($currentGroup) ? (string) ($currentGroup['group_key'] ?? '') : '';
    }

    return [
        'enabled' => true,
        'menu' => sr_quiz_sidebar_menu_context($pdo, $settings, $currentGroupKey),
        'popular' => sr_quiz_sidebar_popular_quizzes($pdo, (int) ($settings['sidebar_popular_limit'] ?? 5), max(0, (int) ($subject['id'] ?? 0))),
        'comments' => sr_quiz_sidebar_recent_comments($pdo, $settings, (int) ($settings['sidebar_comments_limit'] ?? 5)),
    ];
}
