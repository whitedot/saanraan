<?php

declare(strict_types=1);

function sr_admin_action_result(array $errors = [], string $notice = ''): array
{
    return [
        'errors' => array_values(array_map('strval', $errors)),
        'notice' => $notice,
    ];
}
