<?php

return [
    'provider_key' => 'kcp',
    'display_name' => 'NHN KCP 휴대폰 본인확인',
    'supported_methods' => ['mobile_identity'],
    'default_method' => 'mobile_identity',
    'environment' => 'test',
    'sort_order' => 10,
    'settings_schema' => [
        'site_cd' => [
            'label' => '사이트 코드',
            'required' => true,
            'secret' => false,
            'help' => '테스트는 KCP 개발자센터 본인확인 가이드의 테스트 site_cd를 사용할 수 있습니다.',
        ],
        'enc_key' => [
            'label' => 'ENC_KEY',
            'required' => true,
            'secret' => true,
        ],
        'web_siteid' => [
            'label' => '웹사이트 식별코드',
            'required' => false,
            'secret' => false,
        ],
        'library_path' => [
            'label' => '암복호화 라이브러리 경로',
            'required' => false,
            'secret' => false,
            'help' => '저장소 루트 기준 상대 경로입니다. KCP 제공 PHP 라이브러리를 배치한 경우 입력합니다.',
        ],
        'encrypt_function' => [
            'label' => '암호화 함수',
            'required' => false,
            'secret' => false,
            'help' => '기본값은 encryptJson입니다. 필요하면 Class::method 형식도 사용할 수 있습니다.',
        ],
        'decrypt_function' => [
            'label' => '복호화 함수',
            'required' => false,
            'secret' => false,
            'help' => '기본값은 decryptJson입니다. 필요하면 Class::method 형식도 사용할 수 있습니다.',
        ],
    ],
    'handlers' => [
        'prepare' => 'helpers/provider.php:sr_identity_kcp_prepare',
        'verify_return' => 'helpers/provider.php:sr_identity_kcp_verify_return',
        'verify_callback' => 'helpers/provider.php:sr_identity_kcp_verify_return',
    ],
];
