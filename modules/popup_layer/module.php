<?php

return [
    'name' => '팝업레이어',
    'version' => '2026.05.002',
    'type' => 'module',
    'description' => '팝업레이어 관리와 출력 모듈입니다.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => ['member', 'admin'],
    ],
    'contracts' => [
        'provides' => [
            'paths.php',
            'admin-menu.php',
            'output-slots.php',
        ],
        'consumes' => [
            'extension-points.php',
        ],
    ],
    'settings' => [
        'popup_layer_skin_key' => 'basic',
    ],
];
