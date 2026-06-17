<?php

declare(strict_types=1);

return [
    'protected_routes' => [],
    'public_routes' => [
        'GET /survey',
        'GET /survey/ui-kit',
        'GET /survey/*',
        'POST /survey/*',
    ],
    'public_path_prefixes' => [
        '/survey',
    ],
];
