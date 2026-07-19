<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'render_function' => 'sr_logo_manager_render_logo',
    'symbol_function' => 'sr_logo_manager_render_public_symbol_logo',
    'favicon_function' => 'sr_logo_manager_favicon_link_tag',
];
