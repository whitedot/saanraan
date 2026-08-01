<?php

declare(strict_types=1);

function sr_admin_public_account_access_model(bool $enabled, bool $isOwner, string $firstPermittedPath = ''): array
{
    $firstPermittedPath = trim($firstPermittedPath);

    if ($isOwner) {
        return [
            'enabled' => true,
            'is_owner' => true,
            'badge_label' => '매니저',
            'badge_class' => 'badge-soft-primary',
            'admin_url' => sr_url('/admin'),
        ];
    }

    if ($enabled) {
        return [
            'enabled' => true,
            'is_owner' => false,
            'badge_label' => '스탭',
            'badge_class' => 'badge-soft-info',
            'admin_url' => sr_url($firstPermittedPath !== '' ? $firstPermittedPath : '/admin'),
        ];
    }

    return [
        'enabled' => false,
        'is_owner' => false,
        'badge_label' => '회원',
        'badge_class' => 'badge-soft-secondary',
        'admin_url' => sr_url('/admin'),
    ];
}

function sr_admin_public_account_access_context(PDO $pdo, int $accountId): array
{
    $enabled = sr_admin_has_admin_access($pdo, $accountId);
    $isOwner = $enabled && sr_admin_is_owner($pdo, $accountId);
    $firstPermittedPath = $enabled && !$isOwner ? sr_admin_first_permitted_menu_path($pdo, $accountId) : '';

    return sr_admin_public_account_access_model($enabled, $isOwner, $firstPermittedPath);
}
