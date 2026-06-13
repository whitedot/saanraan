<?php

declare(strict_types=1);

function sr_admin_code_label_context_options(): array
{
    return [
        'module_key' => [
            'admin' => '관리자',
            'asset_exchange' => '자산 환전',
            'asset_ledger' => '자산 원장',
            'banner' => '배너',
            'content' => '콘텐츠',
            'core' => '코어',
            'coupon' => '쿠폰·이용권',
            'community' => '커뮤니티',
            'deposit' => '예치금',
            'embed_manager' => '임베드 매니저',
            'logo_manager' => '로고 관리',
            'member' => '회원',
            'notification' => '알림',
            'point' => '포인트',
            'privacy' => '개인정보',
            'quiz' => '퀴즈',
            'reward' => '적립금',
            'seo' => 'SEO',
            'site_menu' => '사이트 메뉴',
            'survey' => '설문',
        ],
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
        'policy' => [
            'public' => '전체 공개',
            'guest' => '비회원',
            'member' => '회원',
            'group' => '회원 그룹',
            'admin' => '관리자',
            'disabled' => '사용 안 함',
        ],
        'content_status' => [
            'draft' => '임시 저장',
            'scheduled' => '예약',
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
            'plugin' => '플러그인',
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
            'reclaim' => '회수',
            'withdraw' => '출금',
            'exchange_in' => '환전 입금',
            'exchange_out' => '환전 출금',
            'exchange_fee' => '환전 수수료',
        ],
        'reference_type' => [
            '' => '없음',
            'order' => '주문',
            'payment' => '결제',
            'refund' => '환불',
            'reclaim' => '회수',
            'point_expiration' => '포인트 만료',
            'support_ticket' => '고객문의',
            'event' => '이벤트',
            'migration' => '데이터 이관',
            'reward_withdrawal' => '적립금 출금',
            'deposit_refund' => '예치금 환불 신청',
            'asset_exchange' => '자산 환전',
            'member.withdrawal' => '회원 탈퇴',
            'content.view' => '콘텐츠 열람',
            'content.download' => '콘텐츠 다운로드',
            'content.action' => '콘텐츠 완료 처리',
            'community.post' => '커뮤니티 게시글',
            'community.comment' => '커뮤니티 댓글',
            'community.attachment.publisher_reward' => '첨부 다운로드 리워드',
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
            'queued' => '등록 대기',
            'active' => '등록됨',
            'deleted' => '삭제됨',
        ],
        'delivery_status' => [
            'queued' => '발송 대기',
            'sent' => '발송 완료',
            'failed' => '실패',
            'canceled' => '취소',
        ],
        'embed_manager_status' => [
            'active' => '정상',
            'removed' => '제거됨',
            'broken' => '깨짐',
            'private' => '비공개',
            'deleted' => '삭제됨',
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
        'actor_type' => [
            'admin' => '관리자',
            'member' => '회원',
            'system' => '시스템',
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
            'core' => '코어',
            'site' => '사이트',
            'retention' => '데이터 정리',
            'member_account' => '회원 계정',
            'member_group' => '회원 그룹',
            'member_group_rule' => '회원 그룹 규칙',
            'module' => '모듈',
            'module_source' => '모듈 소스',
            'module_setting' => '모듈 설정',
            'site_setting' => '사이트 설정',
            'site_settings' => '사이트 설정',
            'privacy_request' => '개인정보 처리 요청',
            'community_attachment' => '커뮤니티 첨부파일',
            'community_message' => '커뮤니티 쪽지',
            'community_post' => '커뮤니티 게시글',
            'community_comment' => '커뮤니티 댓글',
            'community_report' => '커뮤니티 신고',
            'community_board' => '커뮤니티 게시판',
            'community_board_group' => '커뮤니티 게시판 그룹',
            'community_series' => '커뮤니티 시리즈',
            'content' => '콘텐츠',
            'content_group' => '콘텐츠 그룹',
            'post' => '게시글',
            'comment' => '댓글',
            'message' => '쪽지',
            'banner' => '배너',
            'popup_layer' => '팝업레이어',
            'logo_manager_logo' => '로고 배치',
            'site_menu' => '사이트 메뉴',
            'site_menu_item' => '사이트 메뉴 항목',
            'notification' => '알림',
            'notification_delivery' => '이메일 발송 작업',
            'coupon_definition' => '쿠폰 종류',
            'coupon_issue' => '지급 쿠폰',
        ],
        'boolean' => [
            '0' => '아니오',
            '1' => '예',
        ],
    ];
}

function sr_admin_code_label(string $value, string $context = ''): string
{
    $contextLabels = sr_admin_code_label_context_options();

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

function sr_admin_event_type_label_options(): array
{
    return [
        'member.account.created' => '회원 계정 생성',
        'member.nickname.updated' => '회원 닉네임 변경',
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
        'site.currency.changed' => '기본 통화 변경',
        'site.currency.reauth_blocked' => '기본 통화 변경 재인증 제한',
        'site.currency.reauth_failed' => '기본 통화 변경 재인증 실패',
        'admin.settings.updated' => '관리자 설정 변경',
        'admin.menu.updated' => '관리자 메뉴 표시 설정 변경',
        'admin.role.changed' => '관리자 권한 변경',
        'admin.permissions.changed' => '관리자 권한 변경',
        'member.group.manual_grant_skipped' => '회원 그룹 수동 배정 중복 건너뜀',
        'content.settings.updated' => '콘텐츠 환경설정 변경',
        'content.asset_settings.updated' => '콘텐츠 포인트/금액 설정 변경',
        'content_group.asset_settings.updated' => '콘텐츠 그룹 포인트/금액 설정 변경',
        'community.nickname.created' => '커뮤니티 닉네임 설정',
        'community.nickname.reset' => '커뮤니티 닉네임 초기화',
        'community.nickname.updated' => '커뮤니티 닉네임 변경',
        'community.settings.asset_settings.updated' => '커뮤니티 포인트/금액 설정 변경',
        'community.series_scrap.added' => '커뮤니티 시리즈 스크랩 추가',
        'community.series_scrap.removed' => '커뮤니티 시리즈 스크랩 해제',
        'community.board.asset_settings.updated' => '커뮤니티 게시판 포인트/금액 설정 변경',
        'community.board_group.asset_settings.updated' => '커뮤니티 게시판 그룹 포인트/금액 설정 변경',
        'retention.settings.updated' => '보관 기간 설정 변경',
        'retention.cleanup.completed' => '보관 정리 완료',
        'retention.auto_cleanup.completed' => '요청 기반 자동 정리 완료',
    ];
}

function sr_admin_event_type_label(string $eventType): string
{
    $labels = sr_admin_event_type_label_options();
    if (isset($labels[$eventType])) {
        return $labels[$eventType];
    }

    $segmentLabels = [
        'account' => '계정',
        'added' => '추가',
        'admin' => '관리자',
        'asset' => '포인트/금액 항목',
        'attachment' => '첨부파일',
        'author' => '작성자',
        'banner' => '배너',
        'blocked' => '차단',
        'board' => '게시판',
        'by' => '',
        'changed' => '변경',
        'comment' => '댓글',
        'community' => '커뮤니티',
        'completed' => '완료',
        'created' => '생성',
        'deleted' => '삭제',
        'deposit' => '예치금',
        'downloaded' => '다운로드',
        'email' => '이메일',
        'export' => '사본 제공',
        'exported' => '사본 제공',
        'failed' => '실패',
        'file' => '파일',
        'grant' => '부여',
        'granted' => '부여',
        'group' => '그룹',
        'item' => '항목',
        'level' => '레벨',
        'levels' => '레벨',
        'list' => '목록',
        'login' => '로그인',
        'logout' => '로그아웃',
        'member' => '회원',
        'message' => '쪽지',
        'module' => '모듈',
        'notification' => '알림',
        'password' => '비밀번호',
        'reauth' => '재인증',
        'recalculated' => '재계산',
        'content' => '콘텐츠',
        'hidden' => '숨김',
        'order' => '순서',
        'point' => '포인트',
        'popup' => '팝업',
        'privacy' => '개인정보',
        'registered' => '가입',
        'request' => '요청',
        'requested' => '요청',
        'removed' => '해제',
        'revoke' => '회수',
        'revoked' => '회수',
        'reward' => '적립금',
        'role' => '권한',
        'permissions' => '권한',
        'saved' => '저장',
        'settings' => '설정',
        'schema' => '스키마',
        'sent' => '발송',
        'sessions' => '세션',
        'source' => '소스',
        'started' => '시작',
        'status' => '상태',
        'sync' => '동기화',
        'synced' => '동기화',
        'transaction' => '거래',
        'update' => '업데이트',
        'updated' => '변경',
        'upload' => '업로드',
        'uploaded' => '업로드',
        'verified' => '인증',
        'viewed' => '조회',
        'withdrawn' => '탈퇴',
        'write' => '쓰기',
    ];

    $parts = preg_split('/[._-]+/', $eventType) ?: [];
    $labels = [];
    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }
        $label = (string) ($segmentLabels[$part] ?? $part);
        if ($label === '') {
            continue;
        }
        $labels[] = $label;
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
        'Site notification and external delivery queue module.' => '사이트 알림과 이메일 발송 작업을 관리합니다.',
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
    $baseSettings = array_merge(['admin_skin_key' => 'basic', 'admin_color_scheme' => 'light', 'admin_editor' => 'textarea'], $defaults);

    if (!isset($settings['admin_color_scheme'])) {
        $legacySiteSettings = sr_site_settings($pdo);
        $legacyColorScheme = (string) ($legacySiteSettings['ui_color_scheme'] ?? '');
        if (isset(sr_color_scheme_options()[$legacyColorScheme])) {
            $baseSettings['admin_color_scheme'] = $legacyColorScheme;
        }
    }

    $mergedSettings = array_merge($baseSettings, $settings);
    if (!isset($settings['list_pagination_per_page']) && isset($settings['audit_logs_per_page'])) {
        $mergedSettings['list_pagination_per_page'] = $settings['audit_logs_per_page'];
    }

    $mergedSettings['admin_editor'] = sr_editor_normalize_key((string) ($mergedSettings['admin_editor'] ?? 'textarea'));

    return $mergedSettings;
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

function sr_admin_list_pagination_per_page(array $settings): int
{
    $perPage = $settings['list_pagination_per_page'] ?? ($settings['audit_logs_per_page'] ?? 50);

    return max(10, min(500, (int) $perPage));
}

function sr_admin_editor_key(PDO $pdo, ?array $settings = null): string
{
    $settings = is_array($settings) ? $settings : sr_admin_settings($pdo);
    return sr_editor_effective_key($pdo, (string) ($settings['admin_editor'] ?? 'textarea'));
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

function sr_admin_save_list_pagination_per_page(PDO $pdo, int $perPage): void
{
    $perPage = sr_admin_list_pagination_per_page(['list_pagination_per_page' => $perPage]);
    sr_admin_save_module_setting($pdo, 'list_pagination_per_page', (string) $perPage, 'int');
}

function sr_admin_save_editor_key(PDO $pdo, string $editorKey): void
{
    sr_admin_save_module_setting($pdo, 'admin_editor', sr_editor_normalize_key($editorKey));
}

function sr_admin_save_icon_key_overrides(PDO $pdo, array $iconKeyOverrides): void
{
    sr_admin_save_module_setting($pdo, 'icon_key_overrides', json_encode($iconKeyOverrides, JSON_UNESCAPED_SLASHES), 'json');
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
        'default_currency' => $pdo instanceof PDO ? sr_site_default_currency($pdo) : (string) ($site['default_currency'] ?? 'KRW'),
        'status' => (string) ($site['status'] ?? 'active'),
        'member_only_enabled' => !empty($site['member_only_enabled']) ? '1' : '0',
        'public_layout_key' => sr_public_layout_key($site, $pdo),
        'home_path' => (string) ($site['home_path'] ?? '/'),
    ];
}

function sr_admin_public_layout_options(PDO $pdo, bool $includeInstalledModules = false): array
{
    $options = sr_public_layout_options($pdo, $includeInstalledModules);
    if (count($options) < 2) {
        return $options;
    }

    $moduleOrder = sr_admin_sidebar_module_order_map($pdo);
    $defaultKey = sr_public_layout_default_key();

    uasort($options, static function (array $left, array $right) use ($moduleOrder, $defaultKey): int {
        $leftKey = (string) ($left['key'] ?? '');
        $rightKey = (string) ($right['key'] ?? '');

        if ($leftKey === $defaultKey || $rightKey === $defaultKey) {
            return ($leftKey === $defaultKey ? 0 : 1) <=> ($rightKey === $defaultKey ? 0 : 1);
        }

        $leftModuleKey = sr_admin_public_layout_provider_module_key($leftKey, $left);
        $rightModuleKey = sr_admin_public_layout_provider_module_key($rightKey, $right);

        return [
            (int) ($moduleOrder[$leftModuleKey] ?? 100000),
            (string) ($left['provider_label'] ?? ''),
            (string) ($left['label'] ?? $leftKey),
            $leftKey,
        ] <=> [
            (int) ($moduleOrder[$rightModuleKey] ?? 100000),
            (string) ($right['provider_label'] ?? ''),
            (string) ($right['label'] ?? $rightKey),
            $rightKey,
        ];
    });

    return $options;
}

function sr_admin_public_layout_provider_module_key(string $layoutKey, array $layoutOption): string
{
    $moduleKey = (string) ($layoutOption['provider_module_key'] ?? '');
    if (sr_is_safe_module_key($moduleKey) || $moduleKey === 'core') {
        return $moduleKey;
    }

    $separatorPosition = strpos($layoutKey, '.');
    if ($separatorPosition === false) {
        return '';
    }

    $moduleKey = substr($layoutKey, 0, $separatorPosition);
    return is_string($moduleKey) && sr_is_safe_module_key($moduleKey) ? $moduleKey : '';
}

function sr_admin_sidebar_module_order_map(PDO $pdo): array
{
    if (!function_exists('sr_admin_navigation_groups') && !function_exists('sr_admin_navigation_source_groups')) {
        return [];
    }

    $orderMap = [];
    $position = 0;
    $groups = [];

    try {
        if (function_exists('sr_admin_navigation_groups')) {
            $groups = sr_admin_navigation_groups($pdo);
        }
    } catch (Throwable $exception) {
        $groups = [];
    }

    if ($groups === [] && function_exists('sr_admin_navigation_source_groups')) {
        try {
            $groups = sr_admin_navigation_source_groups($pdo);
        } catch (Throwable $exception) {
            $groups = [];
        }
    }

    foreach ($groups as $category) {
        foreach ((array) ($category['module_groups'] ?? []) as $moduleGroup) {
            if (!is_array($moduleGroup)) {
                continue;
            }

            $moduleKey = (string) ($moduleGroup['module_key'] ?? '');
            if ($moduleKey === '' || isset($orderMap[$moduleKey])) {
                continue;
            }

            $orderMap[$moduleKey] = $position;
            $position++;
        }
    }

    return $orderMap;
}

function sr_admin_previous_site_setting_values(?array $site, ?PDO $pdo = null): array
{
    return [
        'name' => (string) ($site['name'] ?? ''),
        'base_url' => (string) ($site['base_url'] ?? ''),
        'timezone' => (string) ($site['timezone'] ?? ''),
        'default_locale' => (string) ($site['default_locale'] ?? ''),
        'supported_locales' => (string) ($site['supported_locales'] ?? ''),
        'default_currency' => $pdo instanceof PDO ? sr_site_default_currency($pdo) : (string) ($site['default_currency'] ?? 'KRW'),
        'status' => (string) ($site['status'] ?? ''),
        'member_only_enabled' => !empty($site['member_only_enabled']) ? '1' : '0',
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
        'default_currency' => (string) ($site['default_currency'] ?? 'KRW'),
        'status' => sr_post_string('status', 30),
        'member_only_enabled' => sr_post_string('member_only_enabled', 1) === '1' ? '1' : '0',
        'public_layout_key' => sr_public_layout_normalize_key(sr_post_string('public_layout_key', 80)),
        'home_path' => sr_post_string('home_path', 255),
    ];
}

function sr_admin_currency_change_confirmation_phrase(string $currentCurrency, string $newCurrency): string
{
    return sr_normalize_currency_code($currentCurrency) . '에서 ' . sr_normalize_currency_code($newCurrency) . '로 변경';
}

function sr_admin_currency_change_known_currency_options(): array
{
    return array_keys(sr_known_currency_min_units());
}

function sr_admin_currency_change_impact_specs(): array
{
    return [
        [
            'label' => '콘텐츠 열람 정책',
            'table' => 'sr_content_items',
            'columns' => ['asset_access_settlement_currency'],
            'kind' => 'policy',
        ],
        [
            'label' => '콘텐츠 완료 정책',
            'table' => 'sr_content_items',
            'columns' => ['asset_action_settlement_currency'],
            'kind' => 'policy',
        ],
        [
            'label' => '콘텐츠 revision 열람 정책',
            'table' => 'sr_content_revisions',
            'columns' => ['asset_access_settlement_currency'],
            'kind' => 'snapshot',
        ],
        [
            'label' => '콘텐츠 revision 완료 정책',
            'table' => 'sr_content_revisions',
            'columns' => ['asset_action_settlement_currency'],
            'kind' => 'snapshot',
        ],
        [
            'label' => '콘텐츠 다운로드 정책',
            'table' => 'sr_content_files',
            'columns' => ['asset_download_settlement_currency'],
            'kind' => 'policy',
        ],
        [
            'label' => '콘텐츠 열람 로그',
            'table' => 'sr_content_asset_access_logs',
            'columns' => ['settlement_currency'],
            'kind' => 'log',
        ],
        [
            'label' => '콘텐츠 완료 버튼 로그',
            'table' => 'sr_content_asset_action_logs',
            'columns' => ['settlement_currency'],
            'kind' => 'log',
        ],
        [
            'label' => '커뮤니티 자산 처리 로그',
            'table' => 'sr_community_asset_logs',
            'columns' => ['settlement_currency'],
            'kind' => 'log',
        ],
    ];
}

function sr_admin_currency_change_table_exists(PDO $pdo, string $tableName): bool
{
    if (preg_match('/\Asr_[a-z0-9_]+\z/', $tableName) !== 1) {
        return false;
    }

    $physicalTableName = $tableName;
    if ($pdo instanceof SrPrefixedPDO && $pdo->srTablePrefix() !== 'sr_') {
        $physicalTableName = $pdo->srTablePrefix() . substr($tableName, 3);
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS table_count
               FROM INFORMATION_SCHEMA.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name'
        );
        $stmt->execute(['table_name' => $physicalTableName]);
        return (int) ($stmt->fetchColumn() ?: 0) > 0;
    } catch (Throwable $exception) {
        try {
            $stmt = $pdo->query('SELECT 1 FROM ' . $tableName . ' LIMIT 1');
            if ($stmt instanceof PDOStatement) {
                $stmt->closeCursor();
            }
            return true;
        } catch (Throwable $ignored) {
            return false;
        }
    }
}

function sr_admin_currency_change_column_distribution(PDO $pdo, string $tableName, string $columnName): array
{
    if (
        preg_match('/\Asr_[a-z0-9_]+\z/', $tableName) !== 1
        || preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $columnName) !== 1
    ) {
        return [];
    }

    if (!sr_admin_currency_change_table_exists($pdo, $tableName)) {
        return [];
    }

    try {
        $stmt = $pdo->query(
            'SELECT ' . $columnName . ' AS currency_code, COUNT(*) AS row_count
               FROM ' . $tableName . '
              GROUP BY ' . $columnName . '
              ORDER BY ' . $columnName . ' ASC'
        );
    } catch (Throwable $exception) {
        return [];
    }

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $currencyCode = sr_normalize_currency_code((string) ($row['currency_code'] ?? ''));
        if ($currencyCode === '') {
            $currencyCode = '(empty)';
        }
        $rows[$currencyCode] = (int) ($row['row_count'] ?? 0);
    }

    return $rows;
}

function sr_admin_currency_change_impact_summary(PDO $pdo): array
{
    $summaryRows = [];
    $totalsByCurrency = [];
    $knownCurrencyMap = array_fill_keys(sr_admin_currency_change_known_currency_options(), true);
    $unknownCurrencies = [];

    foreach (sr_admin_currency_change_impact_specs() as $spec) {
        $tableName = (string) ($spec['table'] ?? '');
        foreach ((array) ($spec['columns'] ?? []) as $columnName) {
            $columnName = (string) $columnName;
            $distribution = sr_admin_currency_change_column_distribution($pdo, $tableName, $columnName);
            if ($distribution === []) {
                continue;
            }

            foreach ($distribution as $currencyCode => $rowCount) {
                $totalsByCurrency[$currencyCode] = (int) ($totalsByCurrency[$currencyCode] ?? 0) + $rowCount;
                if (!isset($knownCurrencyMap[$currencyCode])) {
                    $unknownCurrencies[$currencyCode] = $currencyCode;
                }
            }

            $summaryRows[] = [
                'label' => (string) ($spec['label'] ?? $tableName),
                'table' => $tableName,
                'column' => $columnName,
                'kind' => (string) ($spec['kind'] ?? ''),
                'distribution' => $distribution,
            ];
        }
    }

    ksort($totalsByCurrency);
    ksort($unknownCurrencies);

    return [
        'rows' => $summaryRows,
        'totals_by_currency' => $totalsByCurrency,
        'unknown_currencies' => array_values($unknownCurrencies),
        'asset_purchase_power' => sr_admin_currency_change_asset_purchase_power_summary($pdo),
    ];
}

function sr_admin_currency_change_asset_purchase_power_summary(PDO $pdo): array
{
    $assetRows = [];
    $assetHelperPath = SR_ROOT . '/modules/member/helpers/assets.php';
    if (!is_file($assetHelperPath)) {
        return $assetRows;
    }

    require_once $assetHelperPath;
    if (!function_exists('sr_member_assets')) {
        return $assetRows;
    }

    try {
        $assets = sr_member_assets($pdo);
    } catch (Throwable $exception) {
        return $assetRows;
    }

    foreach ($assets as $moduleKey => $asset) {
        if (!is_array($asset)) {
            continue;
        }
        $purchasePower = is_array($asset['purchase_power'] ?? null) ? $asset['purchase_power'] : [];
        $assetRows[] = [
            'module_key' => (string) $moduleKey,
            'label' => (string) ($asset['label'] ?? $moduleKey),
            'asset_units' => max(1, (int) ($purchasePower['asset_units'] ?? 1)),
            'settlement_units' => max(1, (int) ($purchasePower['settlement_units'] ?? 1)),
            'settlement_currency' => sr_normalize_currency_code((string) ($purchasePower['settlement_currency'] ?? sr_site_default_currency($pdo))),
        ];
    }

    return $assetRows;
}

function sr_admin_handle_currency_change_post(PDO $pdo, array $account, ?array $site): array
{
    $errors = [];
    $notice = '';
    $accountId = (int) ($account['id'] ?? 0);
    $currentCurrency = sr_site_default_currency($pdo);
    $newCurrency = sr_normalize_currency_code(sr_post_string('new_default_currency', 20));
    $confirmation = sr_clean_single_line(sr_post_string('currency_change_confirmation', 120), 120);
    $reason = sr_clean_text(sr_post_string('currency_change_reason', 1000), 1000);
    $password = sr_post_string('currency_change_password', 255);
    $impactSummary = sr_admin_currency_change_impact_summary($pdo);
    $values = sr_admin_site_setting_values($site, $pdo);

    if (!sr_currency_is_known($newCurrency)) {
        $errors[] = '변경할 기본 통화 값이 올바르지 않습니다.';
    }

    if ($newCurrency === $currentCurrency) {
        $errors[] = '현재 기본 통화와 다른 값을 선택하세요.';
    }

    $expectedConfirmation = sr_admin_currency_change_confirmation_phrase($currentCurrency, $newCurrency);
    if ($confirmation !== $expectedConfirmation) {
        $errors[] = '확인 문구가 일치하지 않습니다. "' . $expectedConfirmation . '"를 정확히 입력하세요.';
    }

    if ($reason === '') {
        $errors[] = '기본 통화 변경 사유를 입력하세요.';
    }

    if ($accountId < 1) {
        $errors[] = '재인증 계정을 확인할 수 없습니다.';
    }

    if ($errors === [] && !empty(sr_member_reauth_throttle_status($pdo, $accountId)['limited'])) {
        sr_member_log_auth($pdo, $accountId, 'reauth_blocked', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'site.currency.reauth_blocked',
            'target_type' => 'site_settings',
            'target_id' => 'site.default_currency',
            'result' => 'failure',
            'message' => 'Site default currency reauthentication blocked by throttle.',
            'metadata' => [
                'before' => ['default_currency' => $currentCurrency],
                'after' => ['default_currency' => $newCurrency],
            ],
        ]);
        $errors[] = '재인증 시도가 많습니다. 잠시 후 다시 시도하세요.';
    }

    if ($errors === [] && ($password === '' || !password_verify($password, (string) ($account['password_hash'] ?? '')))) {
        sr_member_log_auth($pdo, $accountId, 'site_setting_reauth', 'failure');
        sr_audit_log($pdo, [
            'actor_account_id' => $accountId,
            'actor_type' => 'admin',
            'event_type' => 'site.currency.reauth_failed',
            'target_type' => 'site_settings',
            'target_id' => 'site.default_currency',
            'result' => 'failure',
            'message' => 'Site default currency reauthentication failed.',
            'metadata' => [
                'before' => ['default_currency' => $currentCurrency],
                'after' => ['default_currency' => $newCurrency],
            ],
        ]);
        $errors[] = '현재 관리자 비밀번호가 올바르지 않습니다.';
    }

    if ($errors === []) {
        $beforeValues = sr_admin_previous_site_setting_values($site, $pdo);
        sr_member_log_auth($pdo, $accountId, 'site_setting_reauth', 'success');

        sr_save_site_setting($pdo, 'site.default_currency', $newCurrency, 'string');

        sr_audit_log($pdo, [
            'actor_account_id' => (int) $account['id'],
            'actor_type' => 'admin',
            'event_type' => 'site.currency.changed',
            'target_type' => 'site_settings',
            'target_id' => 'site.default_currency',
            'result' => 'success',
            'message' => 'Site default currency changed.',
            'metadata' => [
                'before' => ['default_currency' => $currentCurrency],
                'after' => ['default_currency' => $newCurrency],
                'reason' => $reason,
                'confirmation_phrase' => $confirmation,
                'impact_summary' => $impactSummary,
                'site_settings_before' => $beforeValues,
            ],
        ]);

        $notice = '기본 통화를 변경했습니다. 기존 가격, 로그, 구매력 snapshot은 변환되지 않습니다.';
        $site = sr_load_site($pdo);
        $values = sr_admin_site_setting_values(is_array($site) ? $site : null, $pdo);
    }

    return [
        'errors' => $errors,
        'notice' => $notice,
        'values' => $values,
        'site' => $site,
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

    if ($supportedLocales === []) {
        $errors[] = '지원 locale 목록은 최소 한 개 이상 선택하세요.';
        return;
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

        if (!in_array($values['member_only_enabled'], ['0', '1'], true)) {
            $errors[] = '회원 전용 모드 값이 올바르지 않습니다.';
        }

        if ($values['member_only_enabled'] === '1' && !sr_module_enabled($pdo, 'member')) {
            $errors[] = '회원 전용 모드를 사용하려면 회원 모듈이 활성화되어 있어야 합니다.';
        }

        if (!isset(sr_admin_public_layout_options($pdo)[$values['public_layout_key']])) {
            $errors[] = '공통 레이아웃 값이 올바르지 않습니다.';
        }

        $homepageCandidates = sr_admin_homepage_candidate_options($pdo, (string) ($values['home_path'] ?? '/'));
        if (!isset($homepageCandidates[$values['home_path']]) || empty($homepageCandidates[$values['home_path']]['available'])) {
            $errors[] = '초기화면 후보가 올바르지 않거나 현재 사용할 수 없습니다.';
        }

        if ($errors === []) {
            $previousValues = sr_admin_previous_site_setting_values($site, $pdo);

            if ((string) ($previousValues['name'] ?? '') !== (string) $values['name']) {
                $referenceResult = sr_read_reference_collect($pdo, 'site-setting-references.php', [
                    'owner_module_key' => 'admin',
                    'target_type' => 'site_setting',
                    'target_id' => 0,
                    'target_key' => 'site.name',
                ], [
                    'old_value' => (string) ($previousValues['name'] ?? ''),
                    'new_value' => (string) $values['name'],
                ]);
                if (($referenceResult['errors'] ?? []) !== []) {
                    $errors[] = '사이트명 참조 계약 오류가 있어 저장할 수 없습니다.';
                } elseif (($referenceResult['rows'] ?? []) !== []) {
                    $errors[] = '이전 사이트명이 다른 모듈 설정에 직접 포함되어 있어 저장할 수 없습니다. 참조 현황을 먼저 확인하세요.';
                }
            }
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
                'site.member_only_enabled' => ['value' => $values['member_only_enabled'], 'type' => 'bool'],
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
