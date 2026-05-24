<?php

declare(strict_types=1);

function sr_admin_code_label(string $value, string $context = ''): string
{
    $contextLabels = [
        'member_status' => [
            'active' => '정상',
            'pending' => '대기',
            'suspended' => '차단',
            'withdrawn' => '탈퇴',
            'anonymized' => '익명화',
        ],
        'site_status' => [
            'active' => '운영',
            'maintenance' => '점검',
        ],
        'module_status' => [
            'enabled' => '사용',
            'disabled' => '미사용',
            'installing' => '설치 중',
            'failed' => '실패',
        ],
        'content_status' => [
            'draft' => '임시 저장',
            'enabled' => '사용',
            'disabled' => '미사용',
            'archived' => '보관',
            'published' => '공개',
            'hidden' => '숨김',
            'deleted' => '삭제됨',
            'pending' => '대기',
        ],
        'privacy_request_status' => [
            'requested' => '요청됨',
            'reviewing' => '검토 중',
            'completed' => '완료',
            'rejected' => '거절',
            'cancelled' => '취소',
        ],
        'privacy_request_type' => [
            'access' => '열람',
            'portability' => '사본 제공',
            'rectification' => '정정',
            'erasure' => '삭제',
            'restriction' => '처리 제한',
            'objection' => '처리 거부',
            'withdrawal' => '동의 철회',
            'export' => '사본 제공',
            'delete' => '삭제',
            'correction' => '정정',
            'withdraw' => '동의 철회',
        ],
        'report_status' => [
            'open' => '접수',
            'reviewing' => '검토 중',
            'resolved' => '처리 완료',
            'dismissed' => '기각',
        ],
        'setting_type' => [
            'string' => '문자열',
            'int' => '정수',
            'bool' => '참/거짓',
            'json' => 'JSON',
        ],
        'module_type' => [
            'module' => '모듈',
            'theme' => '테마',
            'skin' => '스킨',
        ],
        'transaction_type' => [
            'adjustment' => '조정',
            'grant' => '지급',
            'deposit' => '예치',
            'use' => '사용',
            'refund' => '환불',
            'expire' => '만료',
            'withdraw' => '출금',
        ],
        'notification_audience' => [
            'account' => '개별 회원',
            'all' => '전체 회원',
        ],
        'notification_channel' => [
            'site' => '사이트',
            'email' => '이메일',
            'sms' => '문자',
        ],
        'notification_status' => [
            'queued' => '대기',
            'active' => '활성',
            'deleted' => '삭제됨',
        ],
        'delivery_status' => [
            'queued' => '대기',
            'ready' => '발송 준비',
            'sent' => '발송 완료',
            'failed' => '실패',
            'canceled' => '취소',
        ],
        'policy' => [
            'public' => '전체 공개',
            'member' => '회원',
            'group' => '회원 그룹',
            'admin' => '관리자',
            'disabled' => '사용 안 함',
        ],
        'match_type' => [
            'all' => '전체',
            'exact' => '정확히 일치',
        ],
        'menu_target' => [
            'self' => '현재 창',
            'blank' => '새 창',
        ],
        'evaluation_policy' => [
            'grant_only' => '조건 달성 후 계속 유지',
            'sync' => '조건 미충족 시 자동 회수',
        ],
        'assignment_type' => [
            'manual' => '수동',
            'auto' => '자동',
        ],
        'membership_status' => [
            'active' => '활성',
            'revoked' => '회수됨',
            'expired' => '만료',
        ],
        'result' => [
            'success' => '성공',
            'failure' => '실패',
        ],
        'role' => [
            'owner' => '소유자',
        ],
        'admin_permission_action' => [
            'view' => '조회',
            'edit' => '수정',
            'delete' => '삭제',
        ],
        'admin_menu_scope' => [
            'category' => '분류',
            'group' => '모듈',
            'item' => '항목',
        ],
        'target_type' => [
            'member_account' => '회원 계정',
            'module' => '모듈',
            'module_setting' => '모듈 설정',
            'site_setting' => '사이트 설정',
            'site_settings' => '사이트 설정',
            'privacy_request' => '개인정보 처리 요청',
            'community_post' => '커뮤니티 게시글',
            'community_comment' => '커뮤니티 댓글',
            'community_report' => '커뮤니티 신고',
            'post' => '게시글',
            'comment' => '댓글',
            'message' => '쪽지',
            'banner' => '배너',
            'popup_layer' => '팝업레이어',
            'notification' => '알림',
            'notification_delivery' => '알림 발송',
        ],
        'boolean' => [
            '0' => '아니오',
            '1' => '예',
        ],
    ];

    if (isset($contextLabels[$context][$value])) {
        return $contextLabels[$context][$value];
    }

    foreach ($contextLabels as $labels) {
        if (isset($labels[$value])) {
            return $labels[$value];
        }
    }

    return $value;
}

function sr_admin_event_type_label(string $eventType): string
{
    $labels = [
        'member.account.created' => '회원 계정 생성',
        'member.sessions.revoked' => '회원 세션 폐기',
        'member.status.updated' => '회원 상태 변경',
        'privacy.request.updated' => '개인정보 처리 요청 상태 변경',
        'module.installed' => '모듈 설치',
        'module.status.updated' => '모듈 상태 변경',
        'module.setting.saved' => '모듈 설정 저장',
        'module.setting.deleted' => '모듈 설정 삭제',
        'module.version.synced' => '모듈 설치 버전 동기화',
        'site.setting.saved' => '사이트 설정 저장',
        'site.setting.deleted' => '사이트 설정 삭제',
        'site.homepage.updated' => '초기화면 설정 변경',
        'admin.settings.updated' => '관리자 설정 변경',
        'admin.menu.updated' => '관리자 메뉴 표시 설정 변경',
        'admin.role.changed' => '관리자 권한 변경',
        'admin.permissions.changed' => '관리자 권한 변경',
        'retention.settings.updated' => '보관 기간 설정 변경',
        'retention.cleanup.completed' => '보관 정리 완료',
        'retention.auto_cleanup.completed' => '요청 기반 자동 정리 완료',
    ];
    if (isset($labels[$eventType])) {
        return $labels[$eventType];
    }

    $segmentLabels = [
        'account' => '계정',
        'admin' => '관리자',
        'banner' => '배너',
        'blocked' => '차단',
        'comment' => '댓글',
        'community' => '커뮤니티',
        'completed' => '완료',
        'created' => '생성',
        'deleted' => '삭제',
        'deposit' => '예치금',
        'email' => '이메일',
        'failed' => '실패',
        'grant' => '부여',
        'granted' => '부여',
        'group' => '그룹',
        'login' => '로그인',
        'logout' => '로그아웃',
        'member' => '회원',
        'message' => '쪽지',
        'module' => '모듈',
        'notification' => '알림',
        'password' => '비밀번호',
        'content' => '콘텐츠',
        'hidden' => '숨김',
        'point' => '포인트',
        'popup' => '팝업',
        'privacy' => '개인정보',
        'registered' => '가입',
        'request' => '요청',
        'requested' => '요청',
        'revoke' => '회수',
        'revoked' => '회수',
        'reward' => '적립금',
        'role' => '권한',
        'permissions' => '권한',
        'settings' => '설정',
        'sessions' => '세션',
        'status' => '상태',
        'transaction' => '거래',
        'updated' => '변경',
        'upload' => '업로드',
        'verified' => '인증',
        'withdrawn' => '탈퇴',
    ];

    $parts = preg_split('/[._-]+/', $eventType) ?: [];
    $labels = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $labels[] = (string) ($segmentLabels[$part] ?? $part);
    }

    return $labels === [] ? $eventType : implode(' ', $labels);
}

function sr_admin_module_name_label(string $name): string
{
    $labels = [
        'Admin' => '관리자',
        'Banner' => '배너',
        'Community' => '커뮤니티',
        'Deposit' => '예치금',
        'Member' => '회원',
        'Notification' => '알림',
        'Page' => '콘텐츠',
        'Point' => '포인트',
        'Popup Layer' => '팝업레이어',
        'Reward' => '적립금',
        'SEO' => 'SEO',
        'Site Menu' => '사이트 메뉴',
    ];

    return (string) ($labels[$name] ?? $name);
}

function sr_admin_module_description_label(string $description): string
{
    $labels = [
        'Admin dashboard module.' => '관리자 대시보드 모듈입니다.',
        'Content banner management module for public output slots.' => '공개 출력 슬롯용 배너 관리 모듈입니다.',
        'Board-style community module.' => '게시판형 커뮤니티 모듈입니다.',
        'Member deposit balance and transaction ledger module.' => '회원 예치금 잔액과 거래 장부 모듈입니다.',
        'Member account and authentication module.' => '회원 계정과 인증 모듈입니다.',
        'Site notification and external delivery queue module.' => '사이트 알림과 외부 발송 대기열 모듈입니다.',
        'Content publishing and public URL management module.' => '콘텐츠 작성과 공개 URL 관리 모듈입니다.',
        '단일 페이지 작성과 공개 URL을 관리하는 모듈입니다.' => '콘텐츠 작성과 공개 URL을 관리하는 모듈입니다.',
        'Member point balance and transaction ledger module.' => '회원 포인트 잔액과 거래 장부 모듈입니다.',
        'Popup layer management and rendering module.' => '팝업레이어 관리와 출력 모듈입니다.',
        'Member reward balance and transaction ledger module.' => '회원 적립금 잔액과 거래 장부 모듈입니다.',
        'SEO output helpers and sitemap endpoint.' => 'SEO 출력 helper와 사이트맵 엔드포인트 모듈입니다.',
        'Site-wide navigation menu management module.' => '사이트 공통 내비게이션 메뉴 관리 모듈입니다.',
    ];

    return (string) ($labels[$description] ?? $description);
}

function sr_admin_settings(PDO $pdo): array
{
    $metadata = sr_module_metadata('admin');
    $defaults = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];
    $settings = sr_module_settings($pdo, 'admin');
    $baseSettings = array_merge(['admin_skin_key' => 'basic', 'admin_color_scheme' => 'light'], $defaults);

    if (!isset($settings['admin_color_scheme'])) {
        $legacySiteSettings = sr_site_settings($pdo);
        $legacyColorScheme = (string) ($legacySiteSettings['ui_color_scheme'] ?? '');
        if (isset(sr_color_scheme_options()[$legacyColorScheme])) {
            $baseSettings['admin_color_scheme'] = $legacyColorScheme;
        }
    }

    return array_merge($baseSettings, $settings);
}

function sr_admin_skin_options(): array
{
    return sr_filter_view_options([
        'basic' => [
            'label' => sr_t('admin::settings.skin.basic'),
            'views' => [
                'layout-header' => SR_ROOT . '/modules/admin/skins/basic/layout-header.php',
                'layout-footer' => SR_ROOT . '/modules/admin/skins/basic/layout-footer.php',
            ],
        ],
    ], ['layout-header', 'layout-footer'], 'admin skin');
}

function sr_admin_skin_key(array $settings): string
{
    $skinKey = (string) ($settings['admin_skin_key'] ?? 'basic');

    return isset(sr_admin_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function sr_admin_color_scheme(array $settings): string
{
    $colorScheme = (string) ($settings['admin_color_scheme'] ?? 'light');

    return isset(sr_color_scheme_options()[$colorScheme]) ? $colorScheme : 'light';
}

function sr_admin_skin_view(string $skinKey, string $viewKey): string
{
    $options = sr_admin_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    if (is_file($view)) {
        return $view;
    }

    $fallback = (string) ($options['basic']['views'][$viewKey] ?? '');
    if (is_file($fallback)) {
        return $fallback;
    }

    throw new RuntimeException('기본 관리자 스킨 view 파일이 누락되었습니다.');
}

function sr_admin_save_skin_key(PDO $pdo, string $skinKey): void
{
    $skinKey = sr_admin_skin_key(['admin_skin_key' => $skinKey]);
    sr_admin_save_module_setting($pdo, 'admin_skin_key', $skinKey);
}

function sr_admin_save_color_scheme(PDO $pdo, string $colorScheme): void
{
    $colorScheme = sr_admin_color_scheme(['admin_color_scheme' => $colorScheme]);
    sr_admin_save_module_setting($pdo, 'admin_color_scheme', $colorScheme);
}

function sr_admin_save_module_setting(PDO $pdo, string $settingKey, string $settingValue, string $valueType = 'string'): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'admin' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('관리자 모듈이 등록되어 있지 않습니다.');
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
    $now = sr_now();
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => $settingKey,
        'setting_value' => $settingValue,
        'value_type' => $valueType,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    sr_clear_module_settings_cache('admin');
}

function sr_admin_sensitive_site_setting_keys(): array
{
    return [
        'admin.module_sources_enabled' => true,
    ];
}

function sr_admin_setting_value_is_secret(string $settingKey): bool
{
    return preg_match(
        '/(?:^|[._-])(?:password|token|secret|credential|bearer|api[._-]?key|access[._-]?key|private[._-]?key|client[._-]?secret|app[._-]?key)(?:$|[._-])/',
        strtolower($settingKey)
    ) === 1;
}

function sr_admin_setting_display_value(array $setting): string
{
    $settingKey = (string) ($setting['setting_key'] ?? '');
    $settingValue = (string) ($setting['setting_value'] ?? '');

    if (sr_admin_setting_value_is_secret($settingKey)) {
        return $settingValue === '' ? '' : '[masked]';
    }

    return $settingValue;
}

function sr_admin_setting_value_type_errors(string $settingValue, string $valueType): array
{
    if ($valueType === 'int' && preg_match('/\A-?\d+\z/', $settingValue) !== 1) {
        return ['int 설정값은 정수 문자열이어야 합니다.'];
    }

    if ($valueType === 'bool' && !in_array(strtolower($settingValue), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true)) {
        return ['bool 설정값은 1/0, true/false, yes/no, on/off 중 하나여야 합니다.'];
    }

    if ($valueType === 'json' && json_decode($settingValue, true) === null && json_last_error() !== JSON_ERROR_NONE) {
        return ['JSON 설정값이 올바르지 않습니다.'];
    }

    return [];
}

function sr_admin_normalize_setting_value(string $settingValue, string $valueType): string
{
    if ($valueType === 'int') {
        return (string) (int) $settingValue;
    }

    if ($valueType === 'bool') {
        return in_array(strtolower($settingValue), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    return $settingValue;
}

function sr_admin_site_setting_values(?array $site, ?PDO $pdo = null): array
{
    return [
        'name' => (string) ($site['name'] ?? ''),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => (string) ($site['timezone'] ?? 'Asia/Seoul'),
        'default_locale' => (string) ($site['default_locale'] ?? 'ko'),
        'supported_locales' => (string) ($site['supported_locales'] ?? (string) ($site['default_locale'] ?? 'ko')),
        'status' => (string) ($site['status'] ?? 'active'),
        'public_layout_key' => sr_public_layout_key($site, $pdo),
        'home_path' => (string) ($site['home_path'] ?? '/'),
    ];
}

function sr_admin_previous_site_setting_values(?array $site, ?PDO $pdo = null): array
{
    return [
        'name' => (string) ($site['name'] ?? ''),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => (string) ($site['timezone'] ?? ''),
        'default_locale' => (string) ($site['default_locale'] ?? ''),
        'supported_locales' => (string) ($site['supported_locales'] ?? ''),
        'status' => (string) ($site['status'] ?? ''),
        'public_layout_key' => sr_public_layout_key($site, $pdo),
        'home_path' => (string) ($site['home_path'] ?? ''),
    ];
}

function sr_admin_post_site_setting_values(?array $site): array
{
    return [
        'name' => sr_post_string('name', 120),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => sr_post_string('timezone', 80),
        'default_locale' => sr_post_string('default_locale', 20),
        'supported_locales' => sr_admin_post_supported_locales(),
        'status' => sr_post_string('status', 30),
        'public_layout_key' => sr_public_layout_normalize_key(sr_post_string('public_layout_key', 80)),
        'home_path' => sr_post_string('home_path', 255),
    ];
}

function sr_admin_post_supported_locales(): string
{
    $rawLocales = $_POST['supported_locales'] ?? [];
    if (!is_array($rawLocales)) {
        return sr_post_string('supported_locales', 255);
    }

    $locales = [];
    foreach ($rawLocales as $locale) {
        if (!is_string($locale)) {
            continue;
        }

        $locale = trim($locale);
        if ($locale !== '') {
            $locales[] = $locale;
        }
    }

    return implode(',', $locales);
}

function sr_admin_validate_supported_locales(array &$values, array &$errors): void
{
    $supportedLocales = [];
    $availableLocales = sr_available_locale_options();
    foreach (preg_split('/[\s,]+/', $values['supported_locales']) ?: [] as $locale) {
        if ($locale === '') {
            continue;
        }

        if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1 || !in_array($locale, $availableLocales, true)) {
            $errors[] = '지원 locale 목록 값이 올바르지 않습니다.';
            return;
        }

        $supportedLocales[$locale] = $locale;
    }

    if (!isset($supportedLocales[$values['default_locale']])) {
        $supportedLocales[$values['default_locale']] = $values['default_locale'];
    }

    $values['supported_locales'] = implode(',', array_values($supportedLocales));
}

function sr_admin_handle_settings_post(
    PDO $pdo,
    array $account,
    ?array $site
): array {
    $errors = [];
    $notice = '';
    $values = sr_admin_site_setting_values($site, $pdo);
    $intent = sr_post_string('intent', 40);

    if ($intent !== 'site') {
        $errors[] = '사이트 설정 작업 값이 올바르지 않습니다.';
    }

    if ($errors === []) {
        $values = sr_admin_post_site_setting_values($site);

        if ($values['name'] === '') {
            $errors[] = '사이트 이름을 입력하세요.';
        }

        if ($values['base_url'] !== '' && !sr_is_site_base_url($values['base_url'])) {
            $errors[] = '공개 기준 URL은 query, fragment, 사용자 정보를 제외한 http 또는 https URL이어야 합니다.';
        }

        if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
            $errors[] = 'timezone 값이 올바르지 않습니다.';
        }

        if (
            !preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $values['default_locale'])
            || !in_array($values['default_locale'], sr_available_locale_options($site), true)
        ) {
            $errors[] = '기본 locale 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            sr_admin_validate_supported_locales($values, $errors);
        }

        if (!in_array($values['status'], ['active', 'maintenance'], true)) {
            $errors[] = '운영 상태 값이 올바르지 않습니다.';
        }

        if (!isset(sr_public_layout_options($pdo)[$values['public_layout_key']])) {
            $errors[] = '공통 레이아웃 값이 올바르지 않습니다.';
        }

        $homepageCandidates = sr_admin_homepage_candidate_options($pdo, (string) ($values['home_path'] ?? '/'));
        if (!isset($homepageCandidates[$values['home_path']]) || empty($homepageCandidates[$values['home_path']]['available'])) {
            $errors[] = '초기화면 후보가 올바르지 않거나 현재 사용할 수 없습니다.';
        }

        if ($errors === []) {
            $previousValues = sr_admin_previous_site_setting_values($site, $pdo);

            sr_save_site_settings($pdo, [
                'site.name' => ['value' => $values['name'], 'type' => 'string'],
                'site.base_url' => ['value' => $values['base_url'], 'type' => 'string'],
                'site.timezone' => ['value' => $values['timezone'], 'type' => 'string'],
                'site.default_locale' => ['value' => $values['default_locale'], 'type' => 'string'],
                'site.supported_locales' => ['value' => $values['supported_locales'], 'type' => 'string'],
                'site.status' => ['value' => $values['status'], 'type' => 'string'],
                'public_layout_key' => ['value' => $values['public_layout_key'], 'type' => 'string'],
                'site.home_path' => ['value' => $values['home_path'], 'type' => 'string'],
            ]);

            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site.settings.updated',
                'target_type' => 'site_settings',
                'target_id' => 'site',
                'result' => 'success',
                'message' => 'Site settings updated.',
                'metadata' => [
                    'before' => $previousValues,
                    'after' => $values,
                ],
            ]);

            $site = sr_load_site($pdo);
            $values = sr_admin_site_setting_values(is_array($site) ? $site : null, $pdo);
            $notice = '사이트 설정을 저장했습니다.';
        }
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
        'values' => $values,
        'site' => $site,
    ];
}
