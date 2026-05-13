<?php

declare(strict_types=1);

return static function (PDO $pdo, int $accountId): array {
    require_once TOY_ROOT . '/modules/member/helpers.php';

    return toy_member_privacy_export_data($pdo, $accountId);
};
