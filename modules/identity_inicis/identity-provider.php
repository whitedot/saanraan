<?php

$inicisSettingsSchema = [
    'mid' => [
        'label' => 'MID 상점아이디',
        'required' => true,
        'required_environments' => ['production'],
        'secret' => false,
    ],
    'api_key' => [
        'label' => 'apikey 대칭키',
        'required' => true,
        'required_environments' => ['production'],
        'secret' => true,
    ],
    'di_code' => [
        'label' => 'DI_CODE',
        'required' => false,
        'secret' => false,
    ],
    'direct_agency' => [
        'label' => '제휴사 코드',
        'required' => false,
        'secret' => false,
        'help' => '특정 인증서를 바로 노출할 때만 입력합니다.',
    ],
    'logo_url' => [
        'label' => '로고 URL',
        'required' => false,
        'secret' => false,
    ],
    'library_path' => [
        'label' => '복호화 라이브러리 경로',
        'required' => false,
        'secret' => false,
        'help' => '저장소 루트 기준 상대 경로입니다. KG이니시스 샘플의 복호화 라이브러리를 배치한 경우 입력합니다.',
    ],
    'decrypt_function' => [
        'label' => '복호화 함수',
        'required' => false,
        'secret' => false,
        'help' => '암호화되어 돌아오는 사용자 필드를 복호화할 callable입니다.',
    ],
];

$inicisHandlers = [
    'prepare' => 'helpers/provider.php:sr_identity_inicis_prepare',
    'verify_return' => 'helpers/provider.php:sr_identity_inicis_verify_return',
    'verify_callback' => 'helpers/provider.php:sr_identity_inicis_verify_return',
];

return [
    [
        'provider_key' => 'inicis',
        'display_name' => 'KG이니시스 통합인증 본인확인',
        'usage_help' => '회원가입, 본인확인, 중복 계정 방지, 성인확인처럼 본인 여부를 계정에 연결해야 하는 흐름에 사용합니다.',
        'supported_methods' => ['integrated_identity'],
        'default_method' => 'integrated_identity',
        'inicis_req_svc_cd' => '03',
        'environment' => 'production',
        'sort_order' => 20,
        'settings_schema' => $inicisSettingsSchema,
        'handlers' => $inicisHandlers,
    ],
    [
        'provider_key' => 'inicis_simple_auth',
        'display_name' => 'KG이니시스 통합인증 간편인증',
        'usage_help' => '회원관리, ID/PW 찾기, 계정보안작업, 출금·환불 신청처럼 기존 회원을 짧게 재확인하는 1회성 흐름에 우선 사용합니다.',
        'supported_methods' => ['simple_auth'],
        'default_method' => 'simple_auth',
        'inicis_req_svc_cd' => '01',
        'environment' => 'production',
        'sort_order' => 15,
        'settings_schema' => $inicisSettingsSchema,
        'handlers' => $inicisHandlers,
    ],
];
