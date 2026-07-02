<?php

return [
    'totp' => [
        'label' => '인증 앱 OTP',
        'description' => 'TOTP 인증 앱과 백업 코드로 로그인 2차 인증을 처리합니다.',
        'method' => 'totp',
        'login_supported' => true,
        'account_setup_supported' => true,
        'built_in' => true,
    ],
];
