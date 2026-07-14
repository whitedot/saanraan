<?php

declare(strict_types=1);

require_once SR_ROOT . '/core/helpers/privacy-export.php';

return static function (PDO $pdo, int $accountId): array {
    require_once SR_ROOT . '/modules/member/helpers.php';

    return sr_member_privacy_export_data($pdo, $accountId);
};
