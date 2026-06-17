<?php

declare(strict_types=1);

return [
    'protected_routes' => [],
    'public_routes' => [
        'GET /quiz',
        'GET /quiz/ui-kit',
        'GET /quiz/*',
        'POST /quiz/*',
    ],
    'public_path_prefixes' => [
        '/quiz',
    ],
];
