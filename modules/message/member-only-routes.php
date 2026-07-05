<?php

declare(strict_types=1);

return [
    'protected_routes' => [
        'GET /messages',
        'GET /message',
        'GET /message/write',
        'POST /message/write',
        'POST /message/delete',
    ],
    'public_routes' => [],
    'public_path_prefixes' => [],
];
