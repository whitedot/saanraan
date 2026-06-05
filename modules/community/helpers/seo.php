<?php

declare(strict_types=1);

function sr_community_seo_text(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_community_seo_setting_keys(): array
{
    return [
        'seo_title',
        'seo_description',
        'og_title',
        'og_description',
        'og_image_url',
    ];
}

function sr_community_board_seo_defaults(array $board): array
{
    return [
        'seo_title' => (string) ($board['title'] ?? ''),
        'seo_description' => (string) ($board['description'] ?? ''),
        'og_title' => '',
        'og_description' => '',
        'og_image_url' => '',
    ];
}

function sr_community_board_seo_values(PDO $pdo, array $board): array
{
    $values = sr_community_board_seo_defaults($board);
    $boardId = (int) ($board['id'] ?? 0);
    if ($boardId < 1) {
        return $values;
    }

    foreach (sr_community_seo_setting_keys() as $settingKey) {
        $settingValue = sr_community_effective_board_setting($pdo, $board, $settingKey, '');
        if (is_string($settingValue) && $settingValue !== '') {
            $values[$settingKey] = $settingValue;
        }
    }

    return $values;
}

function sr_community_seo_og_from_values(array $values): array
{
    $og = [];
    if (trim((string) ($values['og_title'] ?? '')) !== '') {
        $og['title'] = sr_community_seo_text((string) $values['og_title'], 120);
    }
    if (trim((string) ($values['og_description'] ?? '')) !== '') {
        $og['description'] = sr_community_seo_text((string) $values['og_description'], 200);
    }
    if (trim((string) ($values['og_image_url'] ?? '')) !== '') {
        $og['image'] = trim((string) $values['og_image_url']);
    }

    return $og;
}

function sr_community_home_seo_meta(): array
{
    return [
        'title' => '커뮤니티',
        'canonical' => '/community',
        'robots' => 'index, follow',
    ];
}

function sr_community_board_seo_meta(PDO $pdo, array $board, array $options = []): array
{
    $values = sr_community_board_seo_values($pdo, $board);
    $category = is_array($options['category'] ?? null) ? $options['category'] : null;
    $keyword = trim((string) ($options['keyword'] ?? ''));
    $page = max(1, (int) ($options['page'] ?? 1));
    $categoryInvalid = !empty($options['category_invalid']);

    $path = '/community/board?key=' . rawurlencode((string) ($board['board_key'] ?? ''));
    if (is_array($category)) {
        $path .= '&category=' . rawurlencode((string) ($category['category_key'] ?? ''));
    }
    if ($keyword !== '') {
        $path .= '&q=' . rawurlencode($keyword);
    }
    if ($page > 1) {
        $path .= '&page=' . (string) $page;
    }

    $title = trim((string) ($values['seo_title'] ?? '')) !== '' ? (string) $values['seo_title'] : (string) ($board['title'] ?? '');
    if (is_array($category) && trim((string) ($category['title'] ?? '')) !== '') {
        $title = (string) $category['title'] . ' - ' . $title;
    }
    $robots = $categoryInvalid
        ? 'noindex, follow'
        : ((string) ($board['effective_read_policy'] ?? $board['read_policy'] ?? '') === 'public'
            ? ($keyword === '' ? 'index, follow' : 'noindex, follow')
            : 'noindex, nofollow');

    $seo = [
        'title' => sr_community_seo_text($title, 160),
        'description' => sr_community_seo_text((string) ($values['seo_description'] ?? ''), 200),
        'canonical' => $path,
        'robots' => $robots,
    ];
    $og = sr_community_seo_og_from_values($values);
    if ($og !== []) {
        $seo['og'] = $og;
    }

    return $seo;
}

function sr_community_post_og_image_url(PDO $pdo, array $post): string
{
    $hasExplicitColumn = array_key_exists('og_image_attachment_id', $post);
    $rawAttachmentId = $post['og_image_attachment_id'] ?? null;
    $attachmentId = (int) ($post['og_image_attachment_id'] ?? 0);
    if ($hasExplicitColumn && (string) $rawAttachmentId === '0') {
        return '';
    }
    if ($attachmentId < 1) {
        $attachmentId = sr_community_first_public_image_attachment_id($pdo, (int) ($post['id'] ?? 0));
    }
    if ($attachmentId < 1) {
        return '';
    }

    $attachment = sr_community_attachment_by_id($pdo, $attachmentId);
    if (!is_array($attachment)
        || (int) ($attachment['post_id'] ?? 0) !== (int) ($post['id'] ?? 0)
        || !sr_community_attachment_is_image($attachment)
        || (string) ($attachment['status'] ?? '') !== 'active'
    ) {
        return '';
    }

    return sr_url('/community/attachment?id=' . (string) $attachmentId);
}

function sr_community_post_seo_meta(PDO $pdo, array $post, bool $bodyAllowed = true): array
{
    $title = trim((string) ($post['seo_title'] ?? ''));
    if ($title === '') {
        $title = (string) ($post['title'] ?? '');
    }
    $description = trim((string) ($post['seo_description'] ?? ''));
    if ($description === '' && $bodyAllowed) {
        $description = (string) ($post['body_text'] ?? '');
    }
    $robots = (string) ($post['read_policy'] ?? '') === 'public' && $bodyAllowed ? 'index, follow' : 'noindex, nofollow';

    $seo = [
        'title' => sr_community_seo_text($title, 160),
        'description' => sr_community_seo_text($description, 200),
        'canonical' => '/community/post?id=' . (string) (int) ($post['id'] ?? 0),
        'robots' => $robots,
    ];
    $ogValues = [
        'og_title' => trim((string) ($post['og_title'] ?? '')),
        'og_description' => trim((string) ($post['og_description'] ?? '')),
        'og_image_url' => sr_community_post_og_image_url($pdo, $post),
    ];
    if ($ogValues['og_title'] === '') {
        $ogValues['og_title'] = $seo['title'];
    }
    if ($ogValues['og_description'] === '') {
        $ogValues['og_description'] = $seo['description'];
    }
    $og = sr_community_seo_og_from_values($ogValues);
    if ($og !== []) {
        $seo['og'] = $og;
    }

    return $seo;
}

function sr_community_account_can_remove_post_og_image(PDO $pdo, array $post, ?array $account): bool
{
    $accountId = is_array($account) ? (int) ($account['id'] ?? 0) : 0;
    if ($accountId < 1 || (int) ($post['og_image_attachment_id'] ?? 0) < 1 || (string) ($post['status'] ?? '') !== 'published') {
        return false;
    }

    if ((int) ($post['author_account_id'] ?? 0) === $accountId) {
        return true;
    }

    return sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'edit')
        || sr_admin_has_permission($pdo, $accountId, '/admin/community/posts', 'delete')
        || sr_community_account_has_board_management_permission($pdo, (int) ($post['board_id'] ?? 0), $accountId, 'remove_post_og_image');
}
