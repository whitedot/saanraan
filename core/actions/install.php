<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';

$errors = [];
$installErrorSteps = [];
$addInstallError = function (string $message, string $stepKey) use (&$errors, &$installErrorSteps): void {
    $errors[] = $message;
    if (!isset($installErrorSteps[$stepKey])) {
        $installErrorSteps[$stepKey] = [];
    }
    $installErrorSteps[$stepKey][] = $message;
};
$requiredModules = [
    'member' => [
        'name' => '회원',
        'version' => '2026.05.003',
        'label' => sr_t('install.module.member.label'),
        'description' => '회원가입, 로그인, 계정 화면, 비밀번호 재설정, 이메일 인증을 제공합니다.',
    ],
    'admin' => [
        'name' => '관리자',
        'version' => '2026.05.005',
        'label' => sr_t('install.module.admin.label'),
        'description' => '관리자 대시보드, 사이트 설정, 모듈 관리, 권한 관리 화면을 제공합니다.',
    ],
    'privacy' => [
        'name' => '개인정보',
        'version' => '2026.05.001',
        'label' => sr_t('install.module.privacy.label'),
        'description' => '개인정보 처리 요청과 개인정보 사본 제공 조정 기능을 제공합니다.',
    ],
];
$optionalModules = [
    'seo' => [
        'name' => 'SEO',
        'version' => '2026.04.002',
        'label' => 'SEO',
        'description' => 'robots.txt, sitemap.xml, 기본 meta 설정 화면을 설치합니다.',
    ],
    'site_menu' => [
        'name' => '사이트 메뉴',
        'version' => '2026.05.001',
        'label' => sr_t('install.module.site_menu.label'),
        'description' => '헤더 등 사이트 공통 메뉴를 관리하는 관리자 화면을 설치합니다.',
    ],
    'logo_manager' => [
        'name' => '로고매니저',
        'version' => '2026.05.001',
        'label' => sr_t('install.module.logo_manager.label'),
        'description' => '관리자/공개 화면 로고와 기간별 대체 적용을 관리합니다.',
    ],
    'banner' => [
        'name' => '배너',
        'version' => '2026.05.003',
        'label' => sr_t('install.module.banner.label'),
        'description' => '공통 출력 위치에 노출할 배너와 노출 규칙을 관리합니다.',
    ],
    'popup_layer' => [
        'name' => '팝업레이어',
        'version' => '2026.05.002',
        'label' => sr_t('install.module.popup_layer.label'),
        'description' => '화면별 팝업 노출 규칙과 관리자 등록 화면을 설치합니다.',
    ],
    'ckeditor' => [
        'name' => 'CKEditor',
        'version' => '2026.05.002',
        'label' => 'CKEditor',
        'description' => '관리자와 모듈 입력 화면에 CKEditor 5 편집기 선택지를 설치합니다.',
    ],
    'point' => [
        'name' => '포인트',
        'version' => '2026.04.001',
        'label' => sr_t('install.module.point.label'),
        'description' => '회원별 포인트 잔액과 거래 원장, 관리자 지급/차감 화면을 설치합니다.',
    ],
    'deposit' => [
        'name' => '예치금',
        'version' => '2026.04.001',
        'label' => sr_t('install.module.deposit.label'),
        'description' => '회원별 예치금 잔액과 입금/사용/환불/출금 원장을 설치합니다.',
    ],
    'reward' => [
        'name' => '적립금',
        'version' => '2026.04.001',
        'label' => sr_t('install.module.reward.label'),
        'description' => '회원별 적립금 잔액과 거래 원장, 관리자 지급/차감 화면을 설치합니다.',
    ],
    'asset_exchange' => [
        'name' => '포인트/금액 환전',
        'version' => '2026.05.007',
        'label' => '포인트/금액 환전',
        'description' => '설치된 포인트/금액 항목 간 환전 정책과 실행 로그를 관리합니다.',
    ],
    'coupon' => [
        'name' => '쿠폰·이용권',
        'version' => '2026.05.006',
        'label' => '쿠폰·이용권',
        'description' => '회원별 쿠폰·이용권 종류, 지급, 사용 내역을 관리합니다.',
    ],
    'notification' => [
        'name' => '알림',
        'version' => '2026.05.001',
        'label' => sr_t('install.module.notification.label'),
        'description' => '사이트 내 알림과 이메일 발송 작업을 관리합니다.',
    ],
    'content' => [
        'name' => '콘텐츠',
        'version' => '2026.06.001',
        'label' => sr_t('install.module.content.label'),
        'description' => '콘텐츠 작성과 공개 URL 관리 기능을 설치합니다.',
    ],
    'community' => [
        'name' => '커뮤니티',
        'version' => '2026.06.002',
        'label' => sr_t('install.module.community.label'),
        'description' => '게시판, 댓글, 신고, 쪽지, 스크랩 기능을 설치합니다.',
    ],
];

function sr_install_module_definition(string $moduleKey, array $defaults): array
{
    $metadata = sr_module_metadata($moduleKey);
    $metadataErrors = [];
    if ($metadata === []) {
        $metadataErrors[] = 'module.php 파일을 읽을 수 없습니다.';
    } else {
        foreach (sr_module_metadata_errors($metadata) as $metadataError) {
            $metadataErrors[] = $metadataError;
        }

        foreach (sr_module_contract_file_errors(SR_ROOT . '/modules/' . $moduleKey, $metadata) as $metadataError) {
            $metadataErrors[] = $metadataError;
        }
    }

    if (!is_file(SR_ROOT . '/modules/' . $moduleKey . '/install.sql')) {
        $metadataErrors[] = 'install.sql 파일을 찾을 수 없습니다.';
    }

    $name = is_string($metadata['name'] ?? null) && (string) $metadata['name'] !== ''
        ? (string) $metadata['name']
        : (string) ($defaults['name'] ?? $moduleKey);
    $version = is_string($metadata['version'] ?? null) && preg_match('/\A\d{4}\.\d{2}\.\d{3}\z/', (string) $metadata['version']) === 1
        ? (string) $metadata['version']
        : (string) ($defaults['version'] ?? '2026.04.001');

    $defaults['name'] = $name;
    $defaults['version'] = $version;
    $defaults['metadata_errors'] = array_values(array_unique($metadataErrors));
    return $defaults;
}

function sr_install_module_main_page_option(string $moduleKey): ?array
{
    $metadata = sr_module_metadata($moduleKey);
    $serviceDomain = is_array($metadata['service_domain'] ?? null) ? $metadata['service_domain'] : [];
    $mainPage = is_array($serviceDomain['main_page'] ?? null) ? $serviceDomain['main_page'] : [];
    $path = (string) ($mainPage['path'] ?? '');
    if ($path === '' || $path === '/' || !sr_is_safe_relative_url($path)) {
        return null;
    }

    return [
        'module_key' => $moduleKey,
        'label' => (string) ($mainPage['label'] ?? (string) ($metadata['name'] ?? $moduleKey)),
        'path' => $path,
    ];
}

function sr_install_database_owner_count(PDO $pdo): int
{
    try {
        $stmt = $pdo->query("SELECT COUNT(*) AS count_value FROM sr_admin_account_roles WHERE role_key = 'owner'");
        $row = $stmt->fetch();
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() === '42S02') {
            return 0;
        }

        throw $exception;
    }

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

foreach (array_keys($requiredModules) as $moduleKey) {
    $requiredModules[$moduleKey] = sr_install_module_definition($moduleKey, $requiredModules[$moduleKey]);
}

foreach (array_keys($optionalModules) as $moduleKey) {
    if (!is_file(SR_ROOT . '/modules/' . $moduleKey . '/module.php') || !is_file(SR_ROOT . '/modules/' . $moduleKey . '/install.sql')) {
        unset($optionalModules[$moduleKey]);
        continue;
    }

    $optionalModules[$moduleKey] = sr_install_module_definition($moduleKey, $optionalModules[$moduleKey]);
}

$selectedOptionalModuleKeys = [];
$values = [
    'db_host' => 'localhost',
    'db_name' => '',
    'db_user' => '',
    'db_password' => '',
    'db_table_prefix' => 'sr_',
    'site_name' => 'Saanraan',
    'base_url' => '',
    'timezone' => 'Asia/Seoul',
    'default_locale' => 'ko',
    'main_page_path' => '/',
    'member_login_identifier' => 'both',
    'admin_login_id' => '',
    'admin_email' => '',
    'admin_display_name' => '관리자',
];

$currentBaseUrl = sr_current_base_url();
if ($values['base_url'] === '' && sr_is_site_base_url($currentBaseUrl)) {
    $values['base_url'] = $currentBaseUrl;
}

$mainPageOptions = [
    '/' => [
        'module_key' => 'core',
        'label' => sr_t('install.home.default.label'),
        'path' => '/',
    ],
];
$mainPageOptionsByModule = [];
foreach (array_keys($requiredModules + $optionalModules) as $moduleKey) {
    $option = sr_install_module_main_page_option((string) $moduleKey);
    if (is_array($option)) {
        $mainPageOptions[(string) $option['path']] = $option;
        $mainPageOptionsByModule[(string) $moduleKey] = $option;
    }
}

$previousInstallFailure = null;
$previousInstallFailurePath = SR_ROOT . '/storage/install-failed.json';
if (is_file($previousInstallFailurePath) && is_readable($previousInstallFailurePath)) {
    $previousInstallFailureJson = file_get_contents($previousInstallFailurePath);
    $decodedPreviousInstallFailure = is_string($previousInstallFailureJson) ? json_decode($previousInstallFailureJson, true) : null;
    if (is_array($decodedPreviousInstallFailure)) {
        $previousInstallFailure = [
            'recorded_at' => (string) ($decodedPreviousInstallFailure['recorded_at'] ?? ''),
            'stage' => (string) ($decodedPreviousInstallFailure['stage'] ?? ''),
            'message' => sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($decodedPreviousInstallFailure['message'] ?? ''), 500)),
            'config_written' => !empty($decodedPreviousInstallFailure['config_written']),
            'installed_lock_written' => !empty($decodedPreviousInstallFailure['installed_lock_written']),
        ];
    }
}

$configPath = SR_ROOT . '/config/config.php';
$installedLockPath = SR_ROOT . '/storage/installed.lock';
$configWritable = is_file($configPath)
    ? is_writable($configPath)
    : (is_dir(SR_ROOT . '/config') ? is_writable(SR_ROOT . '/config') : is_writable(SR_ROOT));
$storageWritable = is_dir(SR_ROOT . '/storage')
    ? is_writable(SR_ROOT . '/storage')
    : is_writable(SR_ROOT);
$minimumPhpVersion = '8.1.0';
$minimumPhpVersionId = 80100;
$phpVersionSupported = PHP_VERSION_ID >= $minimumPhpVersionId;
$installChecks = [
    [
        'label' => 'PHP',
        'status' => $phpVersionSupported ? 'ok' : 'error',
        'message' => PHP_VERSION . ' / 필요: ' . $minimumPhpVersion . ' 이상',
        'guide' => $phpVersionSupported ? '현재 PHP 버전으로 설치를 진행할 수 있습니다.' : '호스팅 관리자에서 PHP 8.1 이상으로 변경한 뒤 설치하세요.',
    ],
    [
        'label' => 'PDO MySQL',
        'status' => extension_loaded('pdo_mysql') ? 'ok' : 'error',
        'message' => extension_loaded('pdo_mysql') ? '사용 가능' : 'pdo_mysql 확장이 필요합니다.',
        'guide' => extension_loaded('pdo_mysql') ? 'MySQL 연결에 필요한 PHP 확장이 활성화되어 있습니다.' : '호스팅 관리자에서 pdo_mysql 확장을 켜거나, PHP MySQL 확장을 지원하는 환경으로 변경하세요.',
    ],
    [
        'label' => sr_t('install.check.config.label'),
        'status' => $configWritable ? 'ok' : 'error',
        'message' => $configWritable ? 'config/config.php 생성 가능' : 'config/config.php를 만들 수 없습니다.',
        'guide' => $configWritable ? '설치 시 DB 접속 정보와 앱 비밀값을 config/config.php에 저장합니다. 설치 후에는 이 파일이 웹에서 직접 열리지 않도록 차단하세요.' : 'FTP 또는 호스팅 파일 관리자에서 config 디렉터리를 만든 뒤 쓰기 권한을 주세요. 보통 755로 충분하며, 공유호스팅에서 계속 실패하면 설치 중에만 775 또는 777을 임시로 적용하고 설치 후 755로 되돌리세요.',
    ],
    [
        'label' => sr_t('install.check.storage.label'),
        'status' => $storageWritable ? 'ok' : 'error',
        'message' => $storageWritable ? 'storage 디렉터리 쓰기 가능' : 'storage 디렉터리에 파일을 쓸 수 없습니다.',
        'guide' => $storageWritable ? '설치 잠금 파일, 실패 기록, 운영 로그를 storage 디렉터리에 저장할 수 있습니다. storage도 웹에서 직접 열리지 않도록 차단하세요.' : 'FTP 또는 호스팅 파일 관리자에서 storage 디렉터리를 만든 뒤 쓰기 권한을 주세요. 보통 755로 충분하며, 실패하면 설치 중에만 775 또는 777을 임시로 적용하고 설치 후 755로 되돌리세요.',
    ],
    [
        'label' => sr_t('install.check.current_url.label'),
        'status' => $currentBaseUrl === '' ? 'warning' : (sr_is_local_host($currentBaseUrl) || parse_url($currentBaseUrl, PHP_URL_SCHEME) === 'https' ? 'ok' : 'warning'),
        'message' => $currentBaseUrl === '' ? '요청 host를 확인할 수 없습니다.' : $currentBaseUrl,
        'guide' => $currentBaseUrl === '' ? '공개 기준 URL을 직접 입력하고, 운영 전 실제 접속 URL이 맞는지 확인하세요.' : (sr_is_local_host($currentBaseUrl) ? '로컬 테스트 설치로 인식했습니다.' : (parse_url($currentBaseUrl, PHP_URL_SCHEME) === 'https' ? '운영에 적합한 HTTPS URL입니다.' : 'HTTP 테스트 설치는 가능하지만, 운영 전에는 HTTPS로 전환하세요.')),
    ],
];
$timezoneOptions = timezone_identifiers_list();
$localeOptions = sr_available_locale_options([
    'default_locale' => $values['default_locale'],
    'supported_locales' => $values['default_locale'],
]);

if (sr_request_method() === 'POST') {
    sr_require_csrf();

    foreach ($values as $key => $default) {
        $values[$key] = sr_post_string($key, $key === 'db_password' ? 255 : 120);
    }
    $mainPageCandidatePath = sr_post_string('main_page_candidate_path', 255);
    if ($mainPageCandidatePath !== '') {
        $values['main_page_path'] = $mainPageCandidatePath;
    }

    $values['db_table_prefix'] = strtolower($values['db_table_prefix']);

    $postedOptionalModules = $_POST['optional_modules'] ?? [];
    $selectedOptionalModuleKeys = [];
    if (!is_array($postedOptionalModules)) {
        $addInstallError('선택 모듈 값이 올바르지 않습니다.', 'account_modules');
    } else {
        foreach ($postedOptionalModules as $moduleKey) {
            $moduleKey = is_string($moduleKey) ? $moduleKey : '';
            if (!array_key_exists($moduleKey, $optionalModules)) {
                $addInstallError('선택할 수 없는 모듈이 포함되어 있습니다.', 'account_modules');
                continue;
            }

            $selectedOptionalModuleKeys[$moduleKey] = $moduleKey;
        }

        $selectedOptionalModuleKeys = array_values($selectedOptionalModuleKeys);
    }
    $selectedOptionalModuleMap = array_fill_keys($selectedOptionalModuleKeys, true);

    foreach ($requiredModules as $moduleKey => $module) {
        $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : [];
        foreach ($moduleErrors as $moduleError) {
            $addInstallError((string) $module['label'] . '(' . (string) $moduleKey . ') 모듈 메타데이터 확인 필요: ' . (string) $moduleError, 'account_modules');
        }
    }

    foreach ($selectedOptionalModuleKeys as $moduleKey) {
        $module = $optionalModules[$moduleKey] ?? null;
        $moduleErrors = is_array($module) && isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : [];
        foreach ($moduleErrors as $moduleError) {
            $addInstallError((string) ($module['label'] ?? $moduleKey) . '(' . (string) $moduleKey . ') 모듈 메타데이터 확인 필요: ' . (string) $moduleError, 'account_modules');
        }
    }

    $adminPassword = sr_post_string_without_truncation('admin_password', 255);
    $adminPasswordConfirm = sr_post_string_without_truncation('admin_password_confirm', 255);

    if (!extension_loaded('pdo_mysql')) {
        $addInstallError('pdo_mysql PHP 확장을 사용할 수 없습니다.', 'environment');
    }

    if (!$phpVersionSupported) {
        $addInstallError('PHP 8.1 이상에서만 설치할 수 있습니다.', 'environment');
    }

    if (!$configWritable) {
        $addInstallError('config/config.php 파일을 만들 수 있도록 config 디렉터리 권한을 확인하세요.', 'environment');
    }

    if (!$storageWritable) {
        $addInstallError('storage 디렉터리에 설치 잠금 파일을 만들 수 있도록 권한을 확인하세요.', 'environment');
    }

    if (is_file($configPath) !== is_file($installedLockPath)) {
        $addInstallError('설치 상태 파일이 일치하지 않습니다. config/config.php와 storage/installed.lock 상태를 확인한 뒤 수동 복구하세요.', 'environment');
    }

    if ($values['db_host'] === '' || $values['db_name'] === '' || $values['db_user'] === '') {
        $addInstallError('DB host, DB 이름, DB 사용자를 입력하세요.', 'basic');
    }

    if (!sr_is_safe_table_prefix($values['db_table_prefix'])) {
        $addInstallError('DB 테이블 prefix는 영문 소문자로 시작하고, 영문 소문자/숫자를 사용하며, underscore로 끝나야 합니다. 예: sr_', 'basic');
    }

    if ($values['site_name'] === '') {
        $addInstallError('사이트 이름을 입력하세요.', 'basic');
    }

    if (!filter_var($values['admin_email'], FILTER_VALIDATE_EMAIL)) {
        $addInstallError('관리자 이메일 형식이 올바르지 않습니다.', 'account_modules');
    }

    if ($values['admin_display_name'] === '') {
        $addInstallError('관리자 표시 이름을 입력하세요.', 'account_modules');
    }

    $values['member_login_identifier'] = sr_member_normalize_login_identifier_setting($values['member_login_identifier']);
    $values['admin_login_id'] = sr_member_normalize_login_id($values['admin_login_id']);
    if ($values['admin_login_id'] !== '' && !sr_member_is_valid_login_id($values['admin_login_id'])) {
        $addInstallError('관리자 아이디는 영문 소문자로 시작하고 영문 소문자, 숫자, underscore를 사용해 4~40자로 입력하세요.', 'account_modules');
    }

    if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
        $addInstallError('timezone 값이 올바르지 않습니다.', 'basic');
    }

    if (
        preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $values['default_locale']) !== 1
        || !in_array($values['default_locale'], $localeOptions, true)
    ) {
        $addInstallError('기본 locale은 ko 또는 en-US 같은 형식으로 입력하세요.', 'basic');
    }

    if ($values['base_url'] !== '' && !sr_is_site_base_url($values['base_url'])) {
        $addInstallError('공개 기준 URL은 query, fragment, 사용자 정보를 제외한 http 또는 https URL이어야 합니다.', 'basic');
    }

    if (!isset($mainPageOptions[$values['main_page_path']])) {
        $addInstallError('메인 페이지 값을 선택하세요.', 'basic');
    } else {
        $mainPageModuleKey = (string) $mainPageOptions[$values['main_page_path']]['module_key'];
        if (
            $mainPageModuleKey !== 'core'
            && !array_key_exists($mainPageModuleKey, $requiredModules)
            && empty($selectedOptionalModuleMap[$mainPageModuleKey])
        ) {
            $addInstallError('메인 페이지로 사용할 모듈을 설치할 기능에서 함께 선택하세요.', 'account_modules');
        }
    }

    if ($adminPassword === null || $adminPasswordConfirm === null) {
        $addInstallError('관리자 비밀번호는 255자 이하로 입력하세요.', 'account_modules');
        $adminPassword = '';
        $adminPasswordConfirm = '';
    }

    if (strlen($adminPassword) < 8) {
        $addInstallError('관리자 비밀번호는 8자 이상이어야 합니다.', 'account_modules');
    }

    if ($adminPassword !== $adminPasswordConfirm) {
        $addInstallError('관리자 비밀번호 확인이 일치하지 않습니다.', 'account_modules');
    }

    if ($errors === []) {
        $checkBaseUrl = sr_current_base_url();
        if ($checkBaseUrl !== '' && !sr_is_local_host($checkBaseUrl)) {
            if (sr_is_public_http_url($checkBaseUrl)) {
                $publicFindings = sr_public_internal_access_findings($checkBaseUrl);
                if ($publicFindings !== []) {
                    foreach ($publicFindings as $finding) {
                        $addInstallError('내부 파일이 웹에서 직접 열립니다: ' . (string) $finding['url'], 'environment');
                    }
                }
            }
        }
    }

    if ($errors === []) {
        $installStage = 'prepare_config';
        $existingAppKey = '';
        if (is_file(SR_ROOT . '/config/config.php')) {
            try {
                $existingConfig = sr_load_config();
                $existingAppKey = is_string($existingConfig['app_key'] ?? null) ? $existingConfig['app_key'] : '';
            } catch (Throwable $ignored) {
                $existingAppKey = '';
            }
        }

        $config = [
            'env' => 'production',
            'debug' => false,
            'timezone' => $values['timezone'],
            'app_key' => $existingAppKey !== '' ? $existingAppKey : bin2hex(random_bytes(32)),
            'secrets' => [
                'app_key_env' => 'SR_APP_KEY',
            ],
            'security' => [
                'force_https' => false,
                'trusted_proxies' => [],
            ],
            'session' => [
                'handler' => 'database',
                'lifetime_seconds' => 86400,
            ],
            'storage' => [
                'default' => 'local',
                's3' => [
                    'bucket' => '',
                    'region' => 'us-east-1',
                    'endpoint' => '',
                    'public_base_url' => '',
                    'path_style' => false,
                    'access_key_env' => 'SR_S3_ACCESS_KEY',
                    'secret_key_env' => 'SR_S3_SECRET_KEY',
                ],
            ],
            'mail' => [
                'transport' => 'php_mail',
                'from_email' => '',
                'from_name' => '',
                'host' => '',
                'port' => 587,
                'encryption' => 'tls',
                'username' => '',
                'password' => '',
                'endpoint' => '',
                'bearer_token' => '',
                'timeout_seconds' => 10,
            ],
            'db' => [
                'host' => $values['db_host'],
                'name' => $values['db_name'],
                'user' => $values['db_user'],
                'password' => $values['db_password'],
                'password_env' => 'SR_DB_PASSWORD',
                'charset' => 'utf8mb4',
                'table_prefix' => $values['db_table_prefix'],
            ],
        ];

        try {
            $installStage = 'connect_database';
            $pdo = sr_db($config);

            $installStage = 'check_existing_owner';
            if (sr_install_database_owner_count($pdo) > 0) {
                throw new RuntimeException('설치 잠금 파일이 없지만 기존 소유자 계정이 있습니다.');
            }

            $installStage = 'write_config';
            sr_write_config($config);
            sr_set_runtime_config($config);
            sr_apply_runtime_config($config);

            $installStage = 'execute_schema';
            sr_execute_sql_file($pdo, SR_ROOT . '/database/core/install.sql');
            foreach (array_keys($requiredModules) as $moduleKey) {
                sr_execute_sql_file($pdo, SR_ROOT . '/modules/' . $moduleKey . '/install.sql');
            }
            foreach ($selectedOptionalModuleKeys as $moduleKey) {
                sr_execute_sql_file($pdo, SR_ROOT . '/modules/' . $moduleKey . '/install.sql');
            }

            $installStage = 'save_site_settings';
            $now = sr_now();
            sr_save_site_settings($pdo, [
                'site.name' => ['value' => $values['site_name'], 'type' => 'string'],
                'site.base_url' => ['value' => $values['base_url'], 'type' => 'string'],
                'site.timezone' => ['value' => $values['timezone'], 'type' => 'string'],
                'site.default_locale' => ['value' => $values['default_locale'], 'type' => 'string'],
                'site.supported_locales' => ['value' => $values['default_locale'], 'type' => 'string'],
                'site.status' => ['value' => 'active', 'type' => 'string'],
                'site.home_path' => ['value' => $values['main_page_path'], 'type' => 'string'],
                'public_layout_key' => ['value' => sr_public_layout_default_key(), 'type' => 'string'],
            ]);

            $modules = [];
            foreach ($requiredModules as $moduleKey => $module) {
                $modules[] = [
                    'module_key' => $moduleKey,
                    'name' => (string) $module['name'],
                    'version' => (string) $module['version'],
                    'status' => 'enabled',
                ];
            }

            foreach ($selectedOptionalModuleKeys as $moduleKey) {
                $module = $optionalModules[$moduleKey];
                $modules[] = [
                    'module_key' => $moduleKey,
                    'name' => (string) $module['name'],
                    'version' => (string) $module['version'],
                    'status' => 'enabled',
                ];
            }

            $installStage = 'register_modules';
            foreach ($modules as $module) {
                $stmt = $pdo->prepare(
                    'INSERT INTO sr_modules (module_key, name, version, status, is_bundled, installed_at, updated_at)
                     VALUES (:module_key, :name, :version, :status, :is_bundled, :installed_at, :updated_at)
                     ON DUPLICATE KEY UPDATE version = VALUES(version), status = VALUES(status), updated_at = VALUES(updated_at)'
                );
                $stmt->execute([
                    'module_key' => $module['module_key'],
                    'name' => $module['name'],
                    'version' => $module['version'],
                    'status' => $module['status'],
                    'is_bundled' => 1,
                    'installed_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $installStage = 'save_member_settings';
            $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'member' LIMIT 1");
            $stmt->execute();
            $memberModule = $stmt->fetch();
            if (!is_array($memberModule)) {
                throw new RuntimeException('Member module was not registered.');
            }
            $stmt = $pdo->prepare(
                'INSERT INTO sr_module_settings
                    (module_id, setting_key, setting_value, value_type, created_at, updated_at)
                 VALUES
                    (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    value_type = VALUES(value_type),
                    updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'module_id' => (int) $memberModule['id'],
                'setting_key' => 'login_identifier',
                'setting_value' => $values['member_login_identifier'],
                'value_type' => 'string',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $installStage = 'record_schema_versions';
            sr_record_installed_core_schema_versions($pdo, '2026.04.008');
            foreach ($requiredModules as $moduleKey => $module) {
                sr_record_installed_module_schema_versions($pdo, $moduleKey, (string) $module['version']);
            }
            foreach ($selectedOptionalModuleKeys as $moduleKey) {
                sr_record_installed_module_schema_versions($pdo, $moduleKey, (string) $optionalModules[$moduleKey]['version']);
            }

            require_once SR_ROOT . '/modules/member/helpers.php';
            require_once SR_ROOT . '/modules/admin/helpers.php';

            $installStage = 'create_owner_account';
            $accountId = sr_member_create_account($pdo, $config, [
                'email' => $values['admin_email'],
                'login_id' => $values['admin_login_id'],
                'password' => $adminPassword,
                'display_name' => $values['admin_display_name'],
                'locale' => $values['default_locale'],
                'status' => 'active',
                'email_verified_at' => $now,
                'allow_existing_update' => true,
            ]);

            $installStage = 'grant_owner_role';
            sr_admin_grant_role($pdo, $accountId, 'owner');
            sr_audit_log($pdo, [
                'actor_account_id' => $accountId,
                'actor_type' => 'system',
                'event_type' => 'install.completed',
                'target_type' => 'site',
                'target_id' => 'default',
                'result' => 'success',
                'message' => 'Initial installation completed.',
                'metadata' => [
                    'enabled_modules' => array_values(array_merge(array_keys($requiredModules), $selectedOptionalModuleKeys)),
                ],
            ]);

            $installStage = 'write_install_lock';
            $storageDir = SR_ROOT . '/storage';
            if (!is_dir($storageDir) && !mkdir($storageDir, 0755, true)) {
                throw new RuntimeException('storage directory cannot be created.');
            }

            $installedLock = [
                'installed_at' => $now,
                'app_fingerprint' => substr(hash('sha256', sr_app_key($config)), 0, 16),
                'table_prefix' => sr_table_prefix($config),
            ];
            $installedLockJson = json_encode($installedLock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (!is_string($installedLockJson) || file_put_contents($storageDir . '/installed.lock', $installedLockJson . "\n", LOCK_EX) === false) {
                throw new RuntimeException('installed.lock cannot be written.');
            }

            sr_clear_operational_marker('install-failed.json');
            sr_redirect('/login?next=/admin');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'install_failed_' . $installStage);
            sr_write_operational_marker('install-failed.json', [
                'stage' => $installStage,
                'message' => sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500)),
                'config_written' => is_file(SR_ROOT . '/config/config.php'),
                'installed_lock_written' => is_file(SR_ROOT . '/storage/installed.lock'),
            ]);
            $addInstallError('설치 중 오류가 발생했습니다. DB 정보와 권한을 확인하세요.', 'confirm');
            if ($installStage === 'check_existing_owner') {
                $addInstallError('기존 소유자 계정이 있거나 설치 상태를 확인할 수 없는 DB에는 공개 설치 화면에서 다시 설치할 수 없습니다. 기존 설치 파일 상태를 수동 복구하세요.', 'confirm');
            }
            if (!empty($config['debug'])) {
                $errors[] = sr_log_sensitive_text_sanitize(sr_log_line_value($exception->getMessage(), 500));
                if (!isset($installErrorSteps['confirm'])) {
                    $installErrorSteps['confirm'] = [];
                }
                $installErrorSteps['confirm'][] = (string) end($errors);
            }
        }
    }
}

$installWarnings = [];
if (
    $currentBaseUrl !== ''
    && !sr_is_local_host($currentBaseUrl)
    && parse_url($currentBaseUrl, PHP_URL_SCHEME) !== 'https'
) {
    $installWarnings['current_http'] = '현재 설치 URL이 HTTP입니다. 테스트 설치는 진행할 수 있지만, 실제 운영 전에는 HTTPS로 전환하세요.';
}

if (
    $values['base_url'] !== ''
    && sr_is_site_base_url($values['base_url'])
    && !sr_is_local_host($values['base_url'])
    && parse_url($values['base_url'], PHP_URL_SCHEME) !== 'https'
) {
    $installWarnings['base_url_http'] = '공개 기준 URL이 HTTP입니다. 임시 테스트에는 사용할 수 있지만, 로그인과 관리자 기능을 운영하려면 HTTPS URL을 권장합니다.';
}

if (
    $currentBaseUrl !== ''
    && !sr_is_local_host($currentBaseUrl)
    && !sr_is_public_http_url($currentBaseUrl)
) {
    $installWarnings['internal_check_skipped'] = '현재 설치 URL이 공개 라우팅 가능한 host가 아니어서 내부 파일 직접 접근 자동 점검을 생략합니다.';
}
$installWarnings = array_values($installWarnings);

include SR_ROOT . '/core/views/install.php';
