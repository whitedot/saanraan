<?php

declare(strict_types=1);

function sr_member_public_identity_assets(): array
{
    return [
        'stylesheets' => ['/modules/member/assets/public-identity.css'],
        'scripts' => ['/modules/member/assets/profile-menu.js'],
    ];
}

function sr_member_public_layout_account_model(PDO $pdo, ?array $account, array $config = []): array
{
    $displayName = '내 계정';
    $displayLabel = '내 계정';
    $email = '';
    $initial = 'M';
    $avatarColorClass = 'member-avatar-color-8';

    if (is_array($account)) {
        $accountId = (int) ($account['id'] ?? 0);
        $displayName = sr_member_public_name_for_account_id($pdo, $accountId, '내 계정');
        $displayLabel = $displayName . ' 님';
        $email = trim((string) ($account['email'] ?? ''));
        $initialSource = $displayName !== '' ? $displayName : ($email !== '' ? $email : 'M');
        $initial = function_exists('mb_substr') ? mb_substr($initialSource, 0, 1) : substr($initialSource, 0, 1);
        $avatarColorClass = sr_member_default_avatar_color_class(sr_member_public_account_hash($config, $accountId));
    }

    return [
        'account' => $account,
        'display_name' => $displayName,
        'display_label' => $displayLabel,
        'email' => $email,
        'initial' => $initial,
        'avatar_color_class' => $avatarColorClass,
    ];
}

function sr_member_public_layout_account_context(PDO $pdo, array $config = []): array
{
    return sr_member_public_layout_account_model($pdo, sr_member_current_account($pdo), $config);
}

function sr_member_public_identity_context(PDO $pdo, ?array $viewerAccount, array $accountIds, array $options = []): array
{
    $normalizedAccountIds = [];
    foreach ($accountIds as $accountId) {
        $accountId = (int) $accountId;
        if ($accountId > 0) {
            $normalizedAccountIds[$accountId] = $accountId;
        }
    }
    $normalizedAccountIds = array_values($normalizedAccountIds);
    $viewerAccountId = is_array($viewerAccount) ? (int) ($viewerAccount['id'] ?? 0) : 0;
    $settings = function_exists('sr_member_settings') ? sr_member_settings($pdo) : [];
    $profileImagesEnabled = sr_member_public_profile_images_enabled($pdo);
    $includeFollowStatuses = !array_key_exists('include_follow_statuses', $options) || !empty($options['include_follow_statuses']);
    $sizePixels = static function (string $sizeKey) use ($settings): int {
        if (function_exists('sr_member_profile_image_size_pixels')) {
            return sr_member_profile_image_size_pixels($sizeKey, $settings);
        }

        return $sizeKey === 'small' ? 24 : ($sizeKey === 'large' ? 40 : 32);
    };

    return [
        'viewer_account' => is_array($viewerAccount) ? $viewerAccount : null,
        'profile_images_enabled' => $profileImagesEnabled,
        'profile_image_sources' => sr_member_public_profile_image_sources($pdo, $normalizedAccountIds),
        'follow_statuses' => $includeFollowStatuses ? sr_member_follow_statuses($pdo, $viewerAccountId, $normalizedAccountIds) : [],
        'size_pixels' => [
            'small' => $sizePixels('small'),
            'medium' => $sizePixels('medium'),
            'large' => $sizePixels('large'),
        ],
    ];
}

function sr_member_public_identity_parts(
    PDO $pdo,
    array $context,
    int $accountId,
    string $label,
    array $options = []
): array {
    $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
    $sizeKey = function_exists('sr_member_profile_image_size_key')
        ? sr_member_profile_image_size_key((string) ($options['size'] ?? 'medium'))
        : (in_array((string) ($options['size'] ?? ''), ['small', 'medium', 'large'], true) ? (string) $options['size'] : 'medium');
    $sizePixels = (int) (($context['size_pixels'][$sizeKey] ?? 0));
    if ((int) ($options['size_pixels'] ?? 0) > 0) {
        $sizePixels = max(16, min(128, (int) $options['size_pixels']));
    }
    $profileImageSources = is_array($context['profile_image_sources'] ?? null)
        ? $context['profile_image_sources']
        : [];
    $imageClass = (string) ($options['image_class'] ?? '');
    $profileImageHtml = sr_member_public_profile_image_html(
        (string) ($profileImageSources[$accountId] ?? ''),
        $imageClass,
        $sizeKey,
        $label,
        $sizePixels
    );

    $menuOptions = is_array($options['menu_options'] ?? null) ? $options['menu_options'] : [];
    $followStatuses = is_array($context['follow_statuses'] ?? null) ? $context['follow_statuses'] : [];
    if ($accountId > 0 && !array_key_exists('is_following', $menuOptions)) {
        $menuOptions['is_following'] = (string) ($followStatuses[$accountId] ?? '') === 'active';
    }
    $viewerAccount = is_array($context['viewer_account'] ?? null) ? $context['viewer_account'] : null;
    $nameHtml = !array_key_exists('menu', $options) || !empty($options['menu'])
        ? sr_member_public_name_menu_html($pdo, $viewerAccount, $accountId, $label, $menuOptions)
        : sr_e($label);

    return [
        'profile_image_html' => $profileImageHtml,
        'name_html' => $nameHtml,
    ];
}
