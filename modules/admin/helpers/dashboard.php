<?php

declare(strict_types=1);

function sr_admin_dashboard_modules(PDO $pdo): array
{
    $modules = [];
    $stmt = $pdo->query('SELECT module_key, name, version, status FROM sr_modules ORDER BY id ASC');
    foreach ($stmt->fetchAll() as $row) {
        $modules[] = $row;
    }

    return $modules;
}

function sr_admin_dashboard_table_exists(PDO $pdo, string $tableName): bool
{
    if (preg_match('/\Asr_[a-z0-9_]{1,80}\z/', $tableName) !== 1) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
        return true;
    } catch (PDOException $exception) {
        return false;
    }
}

function sr_admin_dashboard_count(PDO $pdo, string $sql): int
{
    try {
        $stmt = $pdo->query($sql);
        $row = $stmt->fetch();
        return is_array($row) ? (int) $row['count_value'] : 0;
    } catch (PDOException $exception) {
        return 0;
    }
}

function sr_admin_dashboard_operation_summary(PDO $pdo): array
{
    $operationSummary = [];

    if (sr_admin_dashboard_table_exists($pdo, 'sr_site_menus') && sr_admin_dashboard_table_exists($pdo, 'sr_site_menu_items')) {
        $operationSummary[] = [
            'label' => '사이트 메뉴',
            'value' => (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_site_menus WHERE status = 'enabled'"),
            'detail' => '활성 메뉴 / 항목 ' . (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_site_menu_items WHERE status = 'enabled'"),
        ];
    }

    if (sr_admin_dashboard_table_exists($pdo, 'sr_banners')) {
        $operationSummary[] = [
            'label' => '배너',
            'value' => (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_banners WHERE status = 'enabled'"),
            'detail' => '활성 배너 / 임시저장 ' . (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_banners WHERE status = 'draft'"),
        ];
    }

    if (sr_admin_dashboard_table_exists($pdo, 'sr_notifications') && sr_admin_dashboard_table_exists($pdo, 'sr_notification_deliveries')) {
        $operationSummary[] = [
            'label' => '알림',
            'value' => (string) sr_admin_dashboard_count($pdo, 'SELECT COUNT(*) AS count_value FROM sr_notifications'),
            'detail' => '전체 알림 / 발송 대기 ' . (string) sr_admin_dashboard_count($pdo, "SELECT COUNT(*) AS count_value FROM sr_notification_deliveries WHERE status = 'queued'"),
        ];
    }

    return $operationSummary;
}

function sr_admin_dashboard_auth_runtime_summary(PDO $pdo, array $config): array
{
    $summary = [];
    $session = isset($config['session']) && is_array($config['session']) ? $config['session'] : [];
    $security = isset($config['security']) && is_array($config['security']) ? $config['security'] : [];
    $secrets = isset($config['secrets']) && is_array($config['secrets']) ? $config['secrets'] : [];
    $mail = isset($config['mail']) && is_array($config['mail']) ? $config['mail'] : [];

    $sessionHandler = (string) ($session['handler'] ?? 'database');
    $hasRuntimeSessionsTable = sr_admin_dashboard_table_exists($pdo, 'sr_sessions');
    $summary[] = [
        'label' => 'PHP 세션 저장소',
        'value' => $sessionHandler === 'database' ? 'DB' : '파일',
        'state' => $sessionHandler === 'database' && $hasRuntimeSessionsTable ? '정상' : '주의',
        'detail' => $sessionHandler === 'database'
            ? ($hasRuntimeSessionsTable ? 'sr_sessions 사용 가능' : 'sr_sessions 테이블이 없어 파일 세션으로 fallback')
            : '다중 인스턴스에서는 공유 세션 저장소가 필요',
    ];

    $hasRateLimitsTable = sr_admin_dashboard_table_exists($pdo, 'sr_rate_limits');
    $summary[] = [
        'label' => '인증 제한 저장소',
        'value' => $hasRateLimitsTable ? '전용 테이블' : '인증 로그 fallback',
        'state' => $hasRateLimitsTable ? '정상' : '주의',
        'detail' => $hasRateLimitsTable ? 'sr_rate_limits 사용 가능' : '업데이트 SQL 적용 전에는 인증 로그 COUNT를 사용',
    ];

    $secureCookie = sr_session_cookie_secure($config);
    $summary[] = [
        'label' => '세션 쿠키 Secure',
        'value' => $secureCookie ? '적용' : '미적용',
        'state' => $secureCookie ? '정상' : ((string) ($config['env'] ?? 'production') === 'production' ? '주의' : '확인'),
        'detail' => !empty($security['force_https'])
            ? 'force_https 설정으로 강제'
            : (sr_is_https_request($config) ? '현재 요청을 HTTPS로 인식' : '현재 요청을 HTTPS로 인식하지 않음'),
    ];

    $trustedProxies = sr_trusted_proxy_entries($config);
    $trustedProxyErrors = sr_trusted_proxy_config_errors($config);
    $hasForwardedHeaders = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '') !== '' || (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') !== '';
    $clientIp = sr_client_ip();
    $forwardedClientIp = sr_forwarded_client_ip($config);
    $summary[] = [
        'label' => 'Trusted proxy',
        'value' => (string) count($trustedProxies),
        'state' => $trustedProxyErrors === [] && $trustedProxies !== [] ? '정상' : ($hasForwardedHeaders || $trustedProxyErrors !== [] ? '주의' : '확인'),
        'detail' => $trustedProxyErrors !== []
            ? 'trusted_proxies에 올바르지 않은 IP/CIDR 값이 있음'
            : ($trustedProxies !== []
            ? '프록시 헤더 신뢰 범위가 설정됨'
            : ($hasForwardedHeaders ? '전달 헤더가 있지만 trusted_proxies가 비어 있음' : '프록시 없이 직접 요청으로 판단')),
    ];

    $summary[] = [
        'label' => '클라이언트 IP 판정',
        'value' => $clientIp !== '' ? $clientIp : '확인 불가',
        'state' => $clientIp !== '' ? '정상' : '주의',
        'detail' => $forwardedClientIp !== '' ? 'trusted proxy의 X-Forwarded-For 사용' : 'REMOTE_ADDR 기준',
    ];

    $memberSettings = sr_member_settings($pdo);
    $summary[] = [
        'label' => '로그인 제한',
        'value' => (string) $memberSettings['login_throttle_account_limit'] . '/' . (string) $memberSettings['login_throttle_ip_limit'],
        'state' => '정상',
        'detail' => (string) $memberSettings['login_throttle_window_seconds'] . '초 창 / 계정 기준, IP 기준',
    ];

    $summary[] = [
        'label' => '비밀번호 재설정 제한',
        'value' => (string) $memberSettings['password_reset_throttle_account_limit'] . '/' . (string) $memberSettings['password_reset_throttle_ip_limit'],
        'state' => '정상',
        'detail' => (string) $memberSettings['password_reset_throttle_window_seconds'] . '초 창 / 계정 기준, IP 기준',
    ];

    $appKeyEnv = (string) ($secrets['app_key_env'] ?? '');
    $appKeyFromEnv = $appKeyEnv !== '' && getenv($appKeyEnv) !== false && (string) getenv($appKeyEnv) !== '';
    $summary[] = [
        'label' => 'App key 출처',
        'value' => $appKeyFromEnv ? '환경변수' : 'config 파일',
        'state' => sr_app_key($config) !== '' ? '정상' : '주의',
        'detail' => $appKeyFromEnv ? $appKeyEnv . ' 값을 사용 중' : '환경변수 주입이 없으면 config/app_key를 사용',
    ];

    $transport = (string) ($mail['transport'] ?? 'php_mail');
    $mailReady = sr_admin_dashboard_mail_transport_ready($transport, $mail);
    $summary[] = [
        'label' => '메일 transport',
        'value' => $transport,
        'state' => $mailReady ? '정상' : '주의',
        'detail' => $mailReady ? '인증 메일 발송 설정 확인됨' : '인증 메일 발송 설정 보완 필요',
    ];

    $moduleSourcesEnabled = sr_admin_module_sources_enabled($pdo, $config);
    $summary[] = [
        'label' => '모듈 소스 반영',
        'value' => $moduleSourcesEnabled ? '허용' : '비활성화',
        'state' => $moduleSourcesEnabled && sr_admin_runtime_is_production($config) ? '주의' : '정상',
        'detail' => $moduleSourcesEnabled
            ? '소유자 재인증 후 모듈 zip 업로드 가능'
            : 'admin.module_sources_enabled 설정이 없으면 운영 환경에서 기본 비활성화',
    ];

    $storageDriver = sr_storage_default_driver($config);
    $s3ConfigErrors = sr_storage_s3_config_errors($config);
    $summary[] = [
        'label' => '파일 저장소',
        'value' => $storageDriver === 's3' ? 'S3' : '로컬',
        'state' => $storageDriver === 's3' ? (sr_storage_s3_ready($config) ? '정상' : '주의') : ($s3ConfigErrors === [] ? '정상' : '확인'),
        'detail' => $storageDriver === 's3'
            ? (sr_storage_s3_ready($config) ? 'S3 설정 확인됨' : 'S3 설정 확인 필요: ' . implode(' ', $s3ConfigErrors))
            : ($s3ConfigErrors === [] ? 'storage/ 로컬 파일 저장 사용' : '로컬 저장 사용 중. S3 예비 설정 확인 필요: ' . implode(' ', $s3ConfigErrors)),
    ];

    return $summary;
}

function sr_admin_dashboard_install_protection_summary(array $config): array
{
    $configPath = SR_ROOT . '/config/config.php';
    $lockPath = SR_ROOT . '/storage/installed.lock';
    $summary = [];

    $summary[] = [
        'label' => '설정 파일',
        'value' => is_file($configPath) ? '존재' : '없음',
        'state' => is_file($configPath) && is_readable($configPath) ? '정상' : '주의',
        'detail' => is_file($configPath) ? 'config/config.php 확인됨' : '설치 완료 상태를 판단할 설정 파일이 없음',
    ];

    $lockDetail = 'storage/installed.lock 확인됨';
    $lockState = is_file($lockPath) && is_readable($lockPath) ? '정상' : '주의';
    $lockValue = is_file($lockPath) ? '존재' : '없음';
    if (is_file($lockPath) && is_readable($lockPath)) {
        $content = file_get_contents($lockPath);
        $decoded = is_string($content) ? json_decode($content, true) : null;
        if (is_array($decoded)) {
            $installedAt = (string) ($decoded['installed_at'] ?? '');
            $fingerprint = (string) ($decoded['app_fingerprint'] ?? '');
            $expectedFingerprint = substr(hash('sha256', sr_app_key($config)), 0, 16);
            if ($fingerprint !== '' && !hash_equals($expectedFingerprint, $fingerprint)) {
                $lockState = '주의';
                $lockDetail = 'app fingerprint가 현재 설정과 일치하지 않음';
            } else {
                $lockDetail = '설치 시각 ' . ($installedAt !== '' ? $installedAt : '미기록') . ($fingerprint !== '' ? ' / fingerprint 확인' : '');
            }
        } else {
            $lockState = '확인';
            $lockDetail = '이전 형식의 설치 lock 파일 사용 중';
        }
    } elseif (!is_file($lockPath)) {
        $lockDetail = '설치 lock 파일이 없어 설치 흐름 재진입 위험이 있음';
    } else {
        $lockDetail = '설치 lock 파일을 읽을 수 없음';
    }

    $summary[] = [
        'label' => '설치 lock',
        'value' => $lockValue,
        'state' => $lockState,
        'detail' => $lockDetail,
    ];

    $summary[] = [
        'label' => '설치 판정',
        'value' => sr_is_installed() ? '완료' : '미완료',
        'state' => sr_is_installed() ? '정상' : '주의',
        'detail' => 'config/config.php와 storage/installed.lock가 모두 있어야 설치 완료로 판단',
    ];

    return $summary;
}

function sr_admin_dashboard_sensitive_setting_summary(PDO $pdo, array $config): array
{
    $labels = [
        'admin.module_sources_enabled' => '모듈 소스 반영',
    ];
    $settings = [];
    $stmt = $pdo->query(
        "SELECT setting_key, setting_value, value_type, updated_at
         FROM sr_site_settings
         WHERE setting_key IN ('admin.module_sources_enabled')
         ORDER BY setting_key ASC"
    );
    foreach ($stmt->fetchAll() as $row) {
        $settings[(string) $row['setting_key']] = $row;
    }

    $summary = [];
    foreach (sr_admin_sensitive_site_setting_keys() as $settingKey => $_enabled) {
        $row = is_array($settings[$settingKey] ?? null) ? $settings[$settingKey] : null;
        $valueType = is_array($row) ? (string) ($row['value_type'] ?? '') : '';
        $enabled = is_array($row) && $valueType === 'bool'
            ? (bool) sr_cast_setting_value($row['setting_value'] ?? '', $valueType)
            : false;
        $state = $enabled ? '주의' : '정상';
        $detail = '기본값 또는 비활성 상태';

        if ($settingKey === 'admin.module_sources_enabled' && $enabled) {
            $detail = sr_admin_runtime_is_production($config)
                ? '운영 환경에서 모듈 파일 반영 경로가 열려 있음'
                : '개발/스테이징에서 모듈 파일 반영 경로가 열려 있음';
        } elseif (is_array($row) && $valueType !== 'bool') {
            $state = '주의';
            $detail = '고위험 설정은 bool 타입으로 다시 저장해야 함';
        }

        $summary[] = [
            'label' => (string) ($labels[$settingKey] ?? $settingKey),
            'setting_key' => $settingKey,
            'value' => $enabled ? '활성' : '비활성',
            'state' => $state,
            'updated_at' => is_array($row) ? (string) ($row['updated_at'] ?? '') : '',
            'detail' => $detail,
        ];
    }

    return $summary;
}

function sr_admin_dashboard_mail_transport_ready(string $transport, array $mail): bool
{
    $fromEmail = (string) ($mail['from_email'] ?? '');

    if ($transport === 'php_mail') {
        return function_exists('mail') && ($fromEmail === '' || filter_var($fromEmail, FILTER_VALIDATE_EMAIL) !== false);
    }

    if ($transport === 'smtp') {
        return (string) ($mail['host'] ?? '') !== ''
            && (int) ($mail['port'] ?? 0) >= 1
            && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) !== false;
    }

    if ($transport === 'http_api') {
        return sr_mail_http_api_endpoint_is_allowed((string) ($mail['endpoint'] ?? ''))
            && filter_var($fromEmail, FILTER_VALIDATE_EMAIL) !== false;
    }

    return false;
}

function sr_admin_dashboard_recovery_marker(string $filename, string $label): ?array
{
    if (preg_match('/\A[a-z0-9_.-]+\.json\z/', $filename) !== 1) {
        return null;
    }

    $path = SR_ROOT . '/storage/' . $filename;
    if (!is_file($path) || !is_readable($path)) {
        return null;
    }

    $content = file_get_contents($path);
    $decoded = is_string($content) ? json_decode($content, true) : null;
    if (!is_array($decoded)) {
        return [
            'label' => $label,
            'filename' => $filename,
            'recorded_at' => '',
            'stage' => '',
            'message' => '복구 marker를 읽을 수 없습니다.',
        ];
    }

    return [
        'label' => $label,
        'filename' => $filename,
        'recorded_at' => (string) ($decoded['recorded_at'] ?? ''),
        'stage' => (string) ($decoded['stage'] ?? ''),
        'scope' => (string) ($decoded['scope'] ?? ''),
        'module_key' => (string) ($decoded['module_key'] ?? ''),
        'version' => (string) ($decoded['version'] ?? ''),
        'message' => sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($decoded['message'] ?? ''), 500)),
    ];
}

function sr_admin_dashboard_recovery_markers(): array
{
    $recoveryMarkers = [];
    foreach ([
        'install-failed.json' => '설치 실패',
        'update-failed.json' => '업데이트 실패',
    ] as $filename => $label) {
        $marker = sr_admin_dashboard_recovery_marker($filename, $label);
        if (is_array($marker)) {
            $recoveryMarkers[] = $marker;
        }
    }

    return $recoveryMarkers;
}

function sr_admin_dashboard_module_backup_summary(): array
{
    $backupRoot = SR_ROOT . '/storage/module-backups';
    $summary = [
        'count' => 0,
        'latest_name' => '',
        'latest_modified_at' => '',
    ];
    if (!is_dir($backupRoot)) {
        return $summary;
    }

    $directories = glob($backupRoot . '/*', GLOB_ONLYDIR);
    if (!is_array($directories)) {
        return $summary;
    }

    $latestTime = 0;
    foreach ($directories as $directory) {
        $summary['count']++;
        $modifiedAt = filemtime($directory);
        if ($modifiedAt !== false && $modifiedAt > $latestTime) {
            $latestTime = $modifiedAt;
            $summary['latest_name'] = basename($directory);
            $summary['latest_modified_at'] = date('Y-m-d H:i:s', $modifiedAt);
        }
    }

    return $summary;
}
