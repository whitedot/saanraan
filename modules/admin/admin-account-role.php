<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers/roles.php';

function sr_admin_account_role_is_owner(PDO $pdo, int $accountId): bool
{
    return sr_admin_is_owner($pdo, $accountId);
}

return [
    'is_owner_function' => 'sr_admin_account_role_is_owner',
];
