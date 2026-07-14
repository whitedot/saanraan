<?php

declare(strict_types=1);

return [
    'protected_routes' => [
        'GET /content/download',
        'POST /content/download',
        'GET /content/cover-image',
        'GET /content/body-file',
    ],
    'public_routes' => [
        'GET /content',
        'GET /content/search',
        'GET /content/group',
        'GET /content/ui-kit',
        'GET /content/*',
        'POST /content/*',
    ],
    'public_path_prefixes' => [
        '/content',
    ],
];
