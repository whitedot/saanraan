<?php

declare(strict_types=1);

return [
    'protected_routes' => [
        'GET /community/body-file',
        'GET /community/attachment',
        'POST /community/attachment',
    ],
    'public_routes' => [
        'GET /community',
        'GET /community/group',
        'GET /community/board',
        'GET /community/post',
        'POST /community/post',
        'GET /community/ui-kit',
    ],
    'public_path_prefixes' => [
        '/community',
    ],
];
