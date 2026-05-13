<?php

declare(strict_types=1);

function toy_admin_settings_allowed_types(): array
{
    return ['string', 'int', 'bool', 'json'];
}

function toy_admin_code_label(string $value, string $context = ''): string
{
    $contextLabels = [
        'member_status' => [
            'active' => '활성',
            'pending' => '대기',
            'suspended' => '정지',
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
            'export' => '내보내기',
            'delete' => '삭제',
            'correction' => '정정',
            'withdraw' => '탈퇴',
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
            'grant_only' => '조건 충족 시 부여',
            'sync' => '조건에 맞춰 동기화',
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
            'admin' => '관리자',
            'manager' => '매니저',
        ],
        'target_type' => [
            'member_account' => '회원 계정',
            'module' => '모듈',
            'module_setting' => '모듈 설정',
            'site_setting' => '사이트 설정',
            'privacy_request' => '개인정보 요청',
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

function toy_admin_event_type_label(string $eventType): string
{
    $labels = [
        'member.sessions.revoked' => '회원 세션 폐기',
        'member.status.updated' => '회원 상태 변경',
        'privacy.request.updated' => '개인정보 요청 상태 변경',
        'module.installed' => '모듈 설치',
        'module.status.updated' => '모듈 상태 변경',
        'module.setting.saved' => '모듈 설정 저장',
        'module.setting.deleted' => '모듈 설정 삭제',
        'module.version.synced' => '모듈 설치 버전 동기화',
        'site.setting.saved' => '사이트 설정 저장',
        'site.setting.deleted' => '사이트 설정 삭제',
        'admin.settings.updated' => '관리자 설정 변경',
        'admin.role.changed' => '관리자 역할 변경',
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
        'point' => '포인트',
        'popup' => '팝업',
        'privacy' => '개인정보',
        'registered' => '가입',
        'request' => '요청',
        'requested' => '요청',
        'revoke' => '회수',
        'revoked' => '회수',
        'reward' => '적립금',
        'role' => '역할',
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

function toy_admin_module_name_label(string $name): string
{
    $labels = [
        'Admin' => '관리자',
        'Banner' => '배너',
        'Community' => '커뮤니티',
        'Deposit' => '예치금',
        'Member' => '회원',
        'Notification' => '알림',
        'Point' => '포인트',
        'Popup Layer' => '팝업레이어',
        'Reward' => '적립금',
        'SEO' => 'SEO',
        'Site Menu' => '사이트 메뉴',
    ];

    return (string) ($labels[$name] ?? $name);
}

function toy_admin_module_description_label(string $description): string
{
    $labels = [
        'Admin dashboard module.' => '관리자 대시보드 모듈입니다.',
        'Content banner management module for public output slots.' => '공개 출력 슬롯용 배너 관리 모듈입니다.',
        'Board-style community module.' => '게시판형 커뮤니티 모듈입니다.',
        'Member deposit balance and transaction ledger module.' => '회원 예치금 잔액과 거래 장부 모듈입니다.',
        'Member account and authentication module.' => '회원 계정과 인증 모듈입니다.',
        'Site notification and external delivery queue module.' => '사이트 알림과 외부 발송 대기열 모듈입니다.',
        'Member point balance and transaction ledger module.' => '회원 포인트 잔액과 거래 장부 모듈입니다.',
        'Popup layer management and rendering module.' => '팝업레이어 관리와 출력 모듈입니다.',
        'Member reward balance and transaction ledger module.' => '회원 적립금 잔액과 거래 장부 모듈입니다.',
        'SEO output helpers and sitemap endpoint.' => 'SEO 출력 helper와 사이트맵 엔드포인트 모듈입니다.',
        'Site-wide navigation menu management module.' => '사이트 공통 내비게이션 메뉴 관리 모듈입니다.',
    ];

    return (string) ($labels[$description] ?? $description);
}

function toy_admin_settings(PDO $pdo): array
{
    $metadata = toy_module_metadata('admin');
    $defaults = isset($metadata['settings']) && is_array($metadata['settings']) ? $metadata['settings'] : [];

    return array_merge(['admin_skin_key' => 'basic'], $defaults, toy_module_settings($pdo, 'admin'));
}

function toy_admin_skin_options(): array
{
    return [
        'basic' => [
            'label' => '기본',
            'views' => [
                'layout-header' => TOY_ROOT . '/modules/admin/skins/basic/layout-header.php',
                'layout-footer' => TOY_ROOT . '/modules/admin/skins/basic/layout-footer.php',
            ],
        ],
    ];
}

function toy_admin_skin_key(array $settings): string
{
    $skinKey = (string) ($settings['admin_skin_key'] ?? 'basic');

    return isset(toy_admin_skin_options()[$skinKey]) ? $skinKey : 'basic';
}

function toy_admin_skin_view(string $skinKey, string $viewKey): string
{
    $options = toy_admin_skin_options();
    $view = (string) ($options[$skinKey]['views'][$viewKey] ?? $options['basic']['views'][$viewKey] ?? '');

    return is_file($view) ? $view : (string) ($options['basic']['views'][$viewKey] ?? '');
}

function toy_admin_save_skin_key(PDO $pdo, string $skinKey): void
{
    $skinKey = toy_admin_skin_key(['admin_skin_key' => $skinKey]);
    $stmt = $pdo->prepare("SELECT id FROM toy_modules WHERE module_key = 'admin' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('관리자 모듈이 등록되어 있지 않습니다.');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO toy_module_settings
            (module_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:module_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            value_type = VALUES(value_type),
            updated_at = VALUES(updated_at)'
    );
    $now = toy_now();
    $stmt->execute([
        'module_id' => (int) $module['id'],
        'setting_key' => 'admin_skin_key',
        'setting_value' => $skinKey,
        'value_type' => 'string',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    toy_clear_module_settings_cache('admin');
}

function toy_admin_reserved_site_setting_keys(): array
{
    return [
        'site.name' => true,
        'site.base_url' => true,
        'site.timezone' => true,
        'site.default_locale' => true,
        'site.supported_locales' => true,
        'site.status' => true,
        'public_layout_key' => true,
        'ui_color_scheme' => true,
    ];
}

function toy_admin_sensitive_site_setting_keys(): array
{
    return [
        'admin.module_sources_enabled' => true,
    ];
}

function toy_admin_site_setting_requires_reauth(string $settingKey): bool
{
    return isset(toy_admin_sensitive_site_setting_keys()[$settingKey]);
}

function toy_admin_site_setting_requires_bool(string $settingKey): bool
{
    return toy_admin_site_setting_requires_reauth($settingKey);
}

function toy_admin_setting_value_is_secret(string $settingKey): bool
{
    return preg_match(
        '/(?:^|[._-])(?:password|token|secret|credential|bearer|api[._-]?key|access[._-]?key|private[._-]?key|client[._-]?secret|app[._-]?key)(?:$|[._-])/',
        strtolower($settingKey)
    ) === 1;
}

function toy_admin_setting_display_value(array $setting): string
{
    $settingKey = (string) ($setting['setting_key'] ?? '');
    $settingValue = (string) ($setting['setting_value'] ?? '');

    if (toy_admin_setting_value_is_secret($settingKey)) {
        return $settingValue === '' ? '' : '[masked]';
    }

    return $settingValue;
}

function toy_admin_site_setting_value_is_secret(string $settingKey): bool
{
    return toy_admin_setting_value_is_secret($settingKey);
}

function toy_admin_site_setting_display_value(array $setting): string
{
    return toy_admin_setting_display_value($setting);
}

function toy_admin_module_setting_display_value(array $setting): string
{
    return toy_admin_setting_display_value($setting);
}

function toy_admin_setting_value_type_errors(string $settingValue, string $valueType): array
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

function toy_admin_normalize_setting_value(string $settingValue, string $valueType): string
{
    if ($valueType === 'int') {
        return (string) (int) $settingValue;
    }

    if ($valueType === 'bool') {
        return in_array(strtolower($settingValue), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
    }

    return $settingValue;
}

function toy_admin_site_setting_values(?array $site): array
{
    return [
        'name' => (string) ($site['name'] ?? ''),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => (string) ($site['timezone'] ?? 'Asia/Seoul'),
        'default_locale' => (string) ($site['default_locale'] ?? 'ko'),
        'supported_locales' => (string) ($site['supported_locales'] ?? (string) ($site['default_locale'] ?? 'ko')),
        'status' => (string) ($site['status'] ?? 'active'),
        'public_layout_key' => toy_public_layout_key($site),
        'ui_color_scheme' => toy_color_scheme($site),
    ];
}

function toy_admin_previous_site_setting_values(?array $site): array
{
    return [
        'name' => (string) ($site['name'] ?? ''),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => (string) ($site['timezone'] ?? ''),
        'default_locale' => (string) ($site['default_locale'] ?? ''),
        'supported_locales' => (string) ($site['supported_locales'] ?? ''),
        'status' => (string) ($site['status'] ?? ''),
        'public_layout_key' => toy_public_layout_key($site),
        'ui_color_scheme' => toy_color_scheme($site),
    ];
}

function toy_admin_post_site_setting_values(): array
{
    return [
        'name' => toy_post_string('name', 120),
        'base_url' => toy_post_string('base_url', 255),
        'timezone' => toy_post_string('timezone', 80),
        'default_locale' => toy_post_string('default_locale', 20),
        'supported_locales' => toy_post_string('supported_locales', 255),
        'status' => toy_post_string('status', 30),
        'public_layout_key' => toy_post_string('public_layout_key', 60),
        'ui_color_scheme' => toy_post_string('ui_color_scheme', 20),
    ];
}

function toy_admin_validate_supported_locales(array &$values, array &$errors): void
{
    $supportedLocales = [];
    foreach (preg_split('/[\s,]+/', $values['supported_locales']) ?: [] as $locale) {
        if ($locale === '') {
            continue;
        }

        if (preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $locale) !== 1) {
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

function toy_admin_handle_settings_post(
    PDO $pdo,
    array $account,
    ?array $site,
    bool $canManageAdvancedSettings,
    array $allowedSettingTypes,
    array $reservedSiteSettingKeys
): array {
    $errors = [];
    $notice = '';
    $values = toy_admin_site_setting_values($site);
    $intent = toy_post_string('intent', 40);

    if (!in_array($intent, ['site', 'site_setting', 'delete_site_setting'], true)) {
        $errors[] = '사이트 설정 작업 값이 올바르지 않습니다.';
    }

    if ($errors === [] && $intent === 'site_setting') {
        if (!$canManageAdvancedSettings) {
            $errors[] = '고급 사이트 설정은 소유자 권한이 필요합니다.';
        }

        $settingKey = toy_post_string('setting_key', 120);
        $settingValue = toy_post_string('setting_value', 5000);
        $valueType = toy_post_string('value_type', 20);

        if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $settingKey) !== 1) {
            $errors[] = '설정 key 형식이 올바르지 않습니다.';
        }

        if (!in_array($valueType, $allowedSettingTypes, true)) {
            $errors[] = '설정 타입이 올바르지 않습니다.';
        }

        if (toy_admin_site_setting_requires_bool($settingKey) && $valueType !== 'bool') {
            $errors[] = '고위험 사이트 설정은 bool 타입으로만 저장할 수 있습니다.';
        }

        if (isset($reservedSiteSettingKeys[$settingKey])) {
            $errors[] = '기본 사이트 설정은 위의 전용 양식에서 수정하세요.';
        }

        foreach (toy_admin_setting_value_type_errors($settingValue, $valueType) as $valueError) {
            $errors[] = $valueError;
        }

        if ($errors === [] && toy_admin_site_setting_requires_reauth($settingKey)) {
            foreach (toy_admin_site_setting_reauth_errors($pdo, $account, $settingKey, 'save') as $reauthError) {
                $errors[] = $reauthError;
            }
        }

        if ($errors === []) {
            $settingValue = toy_admin_normalize_setting_value($settingValue, $valueType);
            toy_save_site_setting($pdo, $settingKey, $settingValue, $valueType);

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site.setting.saved',
                'target_type' => 'site_setting',
                'target_id' => $settingKey,
                'result' => 'success',
                'message' => 'Site setting saved.',
                'metadata' => [
                    'value_type' => $valueType,
                ],
            ]);

            $notice = '사이트 설정 항목을 저장했습니다.';
        }
    } elseif ($errors === [] && $intent === 'delete_site_setting') {
        if (!$canManageAdvancedSettings) {
            $errors[] = '고급 사이트 설정은 소유자 권한이 필요합니다.';
        }

        $settingKey = toy_post_string('setting_key', 120);
        if (preg_match('/\A[a-z][a-z0-9_.-]{1,119}\z/', $settingKey) !== 1) {
            $errors[] = '설정 key 형식이 올바르지 않습니다.';
        }

        if (isset($reservedSiteSettingKeys[$settingKey])) {
            $errors[] = '기본 사이트 설정은 삭제할 수 없습니다.';
        }

        if ($errors === [] && toy_admin_site_setting_requires_reauth($settingKey)) {
            foreach (toy_admin_site_setting_reauth_errors($pdo, $account, $settingKey, 'delete') as $reauthError) {
                $errors[] = $reauthError;
            }
        }

        if ($errors === []) {
            $stmt = $pdo->prepare('DELETE FROM toy_site_settings WHERE setting_key = :setting_key');
            $stmt->execute(['setting_key' => $settingKey]);
            toy_clear_site_settings_cache();

            toy_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'site.setting.deleted',
                'target_type' => 'site_setting',
                'target_id' => $settingKey,
                'result' => 'success',
                'message' => 'Site setting deleted.',
            ]);

            $notice = '사이트 설정 항목을 삭제했습니다.';
        }
    } elseif ($errors === [] && $intent === 'site') {
        $values = toy_admin_post_site_setting_values();

        if ($values['name'] === '') {
            $errors[] = '사이트 이름을 입력하세요.';
        }

        if ($values['base_url'] !== '' && !toy_is_site_base_url($values['base_url'])) {
            $errors[] = 'Base URL은 query, fragment, 사용자 정보를 제외한 http 또는 https URL이어야 합니다.';
        }

        if (!in_array($values['timezone'], timezone_identifiers_list(), true)) {
            $errors[] = 'timezone 값이 올바르지 않습니다.';
        }

        if (!preg_match('/\A[a-z]{2}(?:-[A-Z]{2})?\z/', $values['default_locale'])) {
            $errors[] = '기본 locale 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            toy_admin_validate_supported_locales($values, $errors);
        }

        if (!in_array($values['status'], ['active', 'maintenance'], true)) {
            $errors[] = '운영 상태 값이 올바르지 않습니다.';
        }

        if (!isset(toy_public_layout_options()[$values['public_layout_key']])) {
            $errors[] = '공통 레이아웃 값이 올바르지 않습니다.';
        }

        if (!isset(toy_color_scheme_options()[$values['ui_color_scheme']])) {
            $errors[] = 'UI 색상 모드 값이 올바르지 않습니다.';
        }

        if ($errors === []) {
            $previousValues = toy_admin_previous_site_setting_values($site);

            toy_save_site_settings($pdo, [
                'site.name' => ['value' => $values['name'], 'type' => 'string'],
                'site.base_url' => ['value' => $values['base_url'], 'type' => 'string'],
                'site.timezone' => ['value' => $values['timezone'], 'type' => 'string'],
                'site.default_locale' => ['value' => $values['default_locale'], 'type' => 'string'],
                'site.supported_locales' => ['value' => $values['supported_locales'], 'type' => 'string'],
                'site.status' => ['value' => $values['status'], 'type' => 'string'],
                'public_layout_key' => ['value' => $values['public_layout_key'], 'type' => 'string'],
                'ui_color_scheme' => ['value' => $values['ui_color_scheme'], 'type' => 'string'],
            ]);

            toy_audit_log($pdo, [
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

            $site = toy_load_site($pdo);
            $values = toy_admin_site_setting_values(is_array($site) ? $site : null);
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

function toy_admin_site_setting_reauth_errors(PDO $pdo, array $account, string $settingKey, string $action): array
{
    $password = toy_post_string('owner_password', 255);
    $accountId = (int) ($account['id'] ?? 0);
    if ($accountId < 1) {
        return ['소유자 재인증 계정을 확인할 수 없습니다.'];
    }

    $throttle = toy_member_reauth_throttle_status($pdo, $accountId);
    if (!empty($throttle['limited'])) {
        toy_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'site.setting.reauth_blocked',
            'target_type' => 'site_setting',
            'target_id' => $settingKey,
            'result' => 'failure',
            'message' => 'Sensitive site setting reauthentication blocked by throttle.',
            'metadata' => [
                'action' => $action,
            ],
        ]);
        return ['재인증 시도가 많습니다. 잠시 후 다시 시도하세요.'];
    }

    if ($password === '' || !password_verify($password, (string) ($account['password_hash'] ?? ''))) {
        toy_member_log_auth($pdo, $accountId, 'site_setting_reauth', 'failure');
        toy_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'site.setting.reauth_failed',
            'target_type' => 'site_setting',
            'target_id' => $settingKey,
            'result' => 'failure',
            'message' => 'Sensitive site setting reauthentication failed.',
            'metadata' => [
                'action' => $action,
            ],
        ]);
        return ['고위험 사이트 설정 변경 전 소유자 비밀번호를 다시 입력하세요.'];
    }

    toy_member_log_auth($pdo, $accountId, 'site_setting_reauth', 'success');
    return [];
}

function toy_admin_site_settings(PDO $pdo): array
{
    $siteSettings = [];
    $stmt = $pdo->query(
        "SELECT setting_key, setting_value, value_type, updated_at
         FROM toy_site_settings
         WHERE setting_key NOT IN ('site.name', 'site.base_url', 'site.timezone', 'site.default_locale', 'site.supported_locales', 'site.status', 'public_layout_key', 'ui_color_scheme')
         ORDER BY setting_key ASC"
    );
    foreach ($stmt->fetchAll() as $row) {
        $siteSettings[] = $row;
    }

    return $siteSettings;
}
