<?php

return [
    'email' => [
        'label' => '이메일 인증 코드',
        'description' => '회원 계정 이메일로 발송한 일회용 코드로 로그인 2차 인증을 처리합니다.',
        'method' => 'email',
        'login_supported' => true,
        'account_setup_supported' => false,
        'built_in' => true,
    ],
    'totp' => [
        'label' => '인증 앱 OTP',
        'description' => 'TOTP 인증 앱과 백업 코드로 로그인 2차 인증을 처리합니다.',
        'method' => 'totp',
        'login_supported' => true,
        'account_setup_supported' => true,
        'built_in' => true,
    ],
    'identity' => [
        'label' => '본인확인',
        'description' => '연동된 외부 본인확인 provider로 로그인 2차 인증을 처리합니다.',
        'method' => 'identity',
        'login_supported' => true,
        'account_setup_supported' => false,
        'built_in' => true,
    ],
];
