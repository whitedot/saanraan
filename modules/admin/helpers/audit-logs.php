<?php

declare(strict_types=1);

function sr_admin_audit_log_filters(): array
{
    $field = sr_admin_audit_log_search_field(sr_get_string('field', 30));
    $keyword = sr_get_string('q', 80);
    $eventType = sr_admin_audit_log_identifier_filter(sr_get_string('event_type', 80), 80);
    $targetType = sr_admin_audit_log_identifier_filter(sr_get_string('target_type', 60), 60);
    $targetId = sr_admin_audit_log_target_id_filter(sr_get_string('target_id', 80), 80);
    $actorType = sr_admin_audit_log_identifier_filter(sr_get_string('actor_type', 40), 40);
    $ipAddress = sr_admin_audit_log_ip_filter(sr_get_string('ip_address', 45));

    if ($keyword === '') {
        $legacyActorAccountId = sr_get_string('actor_account_id', 20);

        if ($eventType !== '' && $targetType === '' && $targetId === '') {
            $field = 'event_type';
            $keyword = $eventType;
            $eventType = '';
        } elseif ($targetType !== '' && $eventType === '' && $targetId === '') {
            $field = 'target_type';
            $keyword = $targetType;
            $targetType = '';
        } elseif ($targetId !== '' && $eventType === '' && $targetType === '') {
            $field = 'target_id';
            $keyword = $targetId;
            $targetId = '';
        } elseif ($legacyActorAccountId !== '') {
            $field = 'actor_account_id';
            $keyword = $legacyActorAccountId;
        }
    }

    return [
        'field' => $field,
        'q' => $keyword,
        'event_type' => $eventType,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'actor_type' => $actorType,
        'ip_address' => $ipAddress,
        'result' => sr_get_string('result', 30),
        'date_from' => sr_get_string('date_from', 30),
        'date_to' => sr_get_string('date_to', 30),
    ];
}

function sr_admin_audit_log_search_field(string $value): string
{
    return in_array($value, ['event_type', 'target_type', 'target_id', 'actor_account_id', 'actor_type', 'ip_address'], true) ? $value : 'event_type';
}

function sr_admin_audit_log_identifier_filter(string $value, int $maxLength): string
{
    if ($value === '' || strlen($value) > $maxLength) {
        return '';
    }

    return preg_match('/\A[a-z][a-z0-9_.-]*\z/', $value) === 1 ? $value : '';
}

function sr_admin_audit_log_target_id_filter(string $value, int $maxLength): string
{
    if ($value === '' || strlen($value) > $maxLength) {
        return '';
    }

    return preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9_.:-]*\z/', $value) === 1 ? $value : '';
}

function sr_admin_audit_log_ip_filter(string $value): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > 45) {
        return '';
    }

    return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : '';
}

function sr_admin_audit_asset_compare_value(mixed $value): mixed
{
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    if (!is_array($value)) {
        return $value;
    }

    $normalized = [];
    foreach ($value as $key => $childValue) {
        $normalized[(string) $key] = sr_admin_audit_asset_compare_value($childValue);
    }
    ksort($normalized);

    return $normalized;
}

function sr_admin_asset_setting_changed_keys(array $beforeSettings, array $afterSettings): array
{
    $keys = array_values(array_unique(array_merge(array_keys($beforeSettings), array_keys($afterSettings))));
    sort($keys);

    $changedKeys = [];
    foreach ($keys as $key) {
        $beforeExists = array_key_exists($key, $beforeSettings);
        $afterExists = array_key_exists($key, $afterSettings);
        if ($beforeExists !== $afterExists) {
            $changedKeys[] = (string) $key;
            continue;
        }

        if (sr_admin_audit_asset_compare_value($beforeSettings[$key]) !== sr_admin_audit_asset_compare_value($afterSettings[$key])) {
            $changedKeys[] = (string) $key;
        }
    }

    return $changedKeys;
}

function sr_admin_asset_settings_audit_url(string $eventType, string $targetType, string $targetId): string
{
    $query = http_build_query([
        'field' => 'event_type',
        'q' => $eventType,
        'target_type' => $targetType,
        'target_id' => $targetId,
    ]);

    return sr_url('/admin/audit-logs?' . $query);
}

function sr_admin_audit_asset_settings_update(PDO $pdo, array $data): void
{
    $beforeSettings = is_array($data['before_asset_settings'] ?? null) ? $data['before_asset_settings'] : [];
    $afterSettings = is_array($data['after_asset_settings'] ?? null) ? $data['after_asset_settings'] : [];
    $changedKeys = sr_admin_asset_setting_changed_keys($beforeSettings, $afterSettings);
    if ($changedKeys === []) {
        return;
    }

    $metadata = [
        'asset_settings_scope' => (string) ($data['asset_settings_scope'] ?? ''),
        'before_asset_settings' => $beforeSettings,
        'after_asset_settings' => $afterSettings,
        'changed_asset_setting_keys' => $changedKeys,
    ];
    if (is_array($data['metadata'] ?? null)) {
        $metadata = array_merge($metadata, $data['metadata']);
    }

    sr_audit_log($pdo, [
        'actor_account_id' => (int) ($data['actor_account_id'] ?? 0),
        'actor_type' => (string) ($data['actor_type'] ?? 'admin'),
        'event_type' => (string) ($data['event_type'] ?? ''),
        'target_type' => (string) ($data['target_type'] ?? ''),
        'target_id' => (string) ($data['target_id'] ?? ''),
        'result' => (string) ($data['result'] ?? 'success'),
        'message' => (string) ($data['message'] ?? 'Asset settings updated.'),
        'metadata' => $metadata,
    ]);
}

function sr_admin_audit_log_result_filter(string $value): string
{
    return in_array($value, ['success', 'failure'], true) ? $value : '';
}

function sr_admin_audit_log_date_filter(string $value): string
{
    if ($value === '' || preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $value) !== 1) {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (
        !$date instanceof DateTimeImmutable
        || (is_array($dateErrors) && ((int) $dateErrors['warning_count'] > 0 || (int) $dateErrors['error_count'] > 0))
    ) {
        return '';
    }

    return $date->format('Y-m-d') === $value ? $value : '';
}

function sr_admin_audit_metadata_redact(mixed $value, string $key = ''): mixed
{
    if ($key !== '' && sr_admin_setting_value_is_secret($key)) {
        return $value === '' ? '' : '[masked]';
    }

    if (is_string($value)) {
        return sr_log_sensitive_text_sanitize($value);
    }

    if (!is_array($value)) {
        return $value;
    }

    $redacted = [];
    foreach ($value as $childKey => $childValue) {
        $redacted[$childKey] = sr_admin_audit_metadata_redact($childValue, is_string($childKey) ? $childKey : '');
    }

    return $redacted;
}

function sr_admin_audit_log_display_metadata(array $log): string
{
    $metadataJson = (string) ($log['metadata_json'] ?? '');
    if ($metadataJson === '') {
        return '';
    }

    $metadata = json_decode($metadataJson, true);
    if (!is_array($metadata)) {
        return '[invalid metadata]';
    }

    $encoded = json_encode(sr_admin_audit_metadata_redact($metadata), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($encoded) ? $encoded : '[invalid metadata]';
}

function sr_admin_audit_log_display_message(array $log): string
{
    $message = sr_log_sensitive_text_sanitize(sr_log_line_value((string) ($log['message'] ?? ''), 1000));
    $labels = [
        'Initial installation completed.' => '초기 설치가 완료되었습니다.',
        'Banner settings updated.' => '배너 설정이 변경되었습니다.',
        'Banner deleted.' => '배너가 삭제되었습니다.',
        'Banner saved.' => '배너가 저장되었습니다.',
        'Popup layer settings updated.' => '팝업레이어 설정이 변경되었습니다.',
        'Popup layer deleted.' => '팝업레이어가 삭제되었습니다.',
        'Popup layer saved.' => '팝업레이어가 저장되었습니다.',
        'SEO settings updated.' => 'SEO 설정이 변경되었습니다.',
        'Schema update lock could not be acquired.' => '스키마 업데이트 잠금을 얻지 못했습니다.',
        'Schema update started.' => '스키마 업데이트를 시작했습니다.',
        'Schema update completed.' => '스키마 업데이트가 완료되었습니다.',
        'Schema update failed.' => '스키마 업데이트에 실패했습니다.',
        'Member sessions could not be revoked.' => '회원 세션을 폐기하지 못했습니다.',
        'Member sessions revoked.' => '회원 세션이 폐기되었습니다.',
        'Member account created by admin.' => '관리자가 회원 계정을 생성했습니다.',
        'Member account updated by admin.' => '관리자가 회원 계정을 변경했습니다.',
        'Member nickname updated by admin.' => '관리자가 회원 닉네임을 변경했습니다.',
        'Community nickname created by member.' => '회원이 커뮤니티 닉네임을 설정했습니다.',
        'Community nickname reset by admin.' => '관리자가 커뮤니티 닉네임을 초기화했습니다.',
        'Community nickname updated by admin.' => '관리자가 커뮤니티 닉네임을 변경했습니다.',
        'Member account basics updated.' => '회원 기본 정보가 변경되었습니다.',
        'Member account withdrawn and anonymized.' => '회원 계정이 탈퇴 처리되고 익명화되었습니다.',
        'Member profile updated.' => '회원 프로필이 변경되었습니다.',
        'Member status update failed.' => '회원 상태 변경에 실패했습니다.',
        'Member status updated.' => '회원 상태가 변경되었습니다.',
        'Member registration blocked by throttle.' => '회원 가입이 제한 정책으로 차단되었습니다.',
        'Member registered.' => '회원 가입이 완료되었습니다.',
        'Member login blocked by throttle.' => '회원 로그인이 제한 정책으로 차단되었습니다.',
        'Member login blocked until email verification.' => '이메일 인증 전이라 회원 로그인이 차단되었습니다.',
        'Member login succeeded.' => '회원 로그인이 성공했습니다.',
        'Member login failed.' => '회원 로그인이 실패했습니다.',
        'Member login session could not be created.' => '회원 로그인 세션을 만들지 못했습니다.',
        'Member logout completed.' => '회원 로그아웃이 완료되었습니다.',
        'Member email verified.' => '회원 이메일 인증이 완료되었습니다.',
        'Member email verification request blocked by throttle.' => '회원 이메일 인증 요청이 제한 정책으로 차단되었습니다.',
        'Member email verification requested.' => '회원 이메일 인증이 요청되었습니다.',
        'Member password reset request blocked by throttle.' => '회원 비밀번호 재설정 요청이 제한 정책으로 차단되었습니다.',
        'Member password reset requested.' => '회원 비밀번호 재설정이 요청되었습니다.',
        'Member password reset completed.' => '회원 비밀번호 재설정이 완료되었습니다.',
        'Member password changed.' => '회원 비밀번호가 변경되었습니다.',
        'Member password was changed but current session could not be rotated.' => '회원 비밀번호는 변경되었지만 현재 세션을 갱신하지 못했습니다.',
        'Member privacy export downloaded.' => '회원 개인정보 사본이 다운로드되었습니다.',
        'Member privacy export reauthentication blocked by throttle.' => '회원 개인정보 사본 다운로드 재인증이 제한 정책으로 차단되었습니다.',
        'Member privacy export reauthentication failed.' => '회원 개인정보 사본 다운로드 재인증에 실패했습니다.',
        'Retention cleanup completed.' => '보관 정리가 완료되었습니다.',
        'Retention auto cleanup completed.' => '요청 기반 자동 정리가 완료되었습니다.',
        'Retention settings updated.' => '보관 기간 설정이 변경되었습니다.',
        'Privacy request created.' => '개인정보 처리 요청이 생성되었습니다.',
        'Privacy request updated.' => '개인정보 처리 요청이 변경되었습니다.',
        'Privacy request export reauthentication blocked by throttle.' => '개인정보 처리 자료 다운로드 재인증이 제한 정책으로 차단되었습니다.',
        'Privacy request export reauthentication failed.' => '개인정보 처리 자료 다운로드 재인증에 실패했습니다.',
        'Privacy request export downloaded.' => '개인정보 처리 자료 파일이 다운로드되었습니다.',
        'Site setting saved.' => '사이트 설정 항목이 저장되었습니다.',
        'Site setting deleted.' => '사이트 설정 항목이 삭제되었습니다.',
        'Site settings updated.' => '사이트 설정이 변경되었습니다.',
        'Sensitive site setting reauthentication blocked by throttle.' => '민감한 사이트 설정 재인증이 제한 정책으로 차단되었습니다.',
        'Sensitive site setting reauthentication failed.' => '민감한 사이트 설정 재인증에 실패했습니다.',
        'Admin role changed.' => '관리자 권한이 변경되었습니다.',
        'Admin permissions changed.' => '관리자 권한이 변경되었습니다.',
        'Admin settings updated.' => '관리자 설정이 변경되었습니다.',
        'Admin menu display settings updated.' => '관리자 메뉴 표시 설정이 변경되었습니다.',
        'Privacy request list viewed.' => '개인정보 처리 요청 목록을 조회했습니다.',
        'Module source zip uploaded.' => '모듈 소스 zip이 업로드되었습니다.',
        'Module source zip upload failed.' => '모듈 소스 zip 업로드에 실패했습니다.',
        'Module installed.' => '모듈이 설치되었습니다.',
        'Module setting saved.' => '모듈 설정 항목이 저장되었습니다.',
        'Module setting deleted.' => '모듈 설정 항목이 삭제되었습니다.',
        'Module installed version synced to code version.' => '모듈 설치 버전이 코드 버전에 맞춰 동기화되었습니다.',
        'Module status updated.' => '모듈 상태가 변경되었습니다.',
        'Sensitive module setting reauthentication blocked by throttle.' => '민감한 모듈 설정 재인증이 제한 정책으로 차단되었습니다.',
        'Sensitive module setting reauthentication failed.' => '민감한 모듈 설정 재인증에 실패했습니다.',
        'Module source write reauthentication blocked by throttle.' => '모듈 소스 쓰기 재인증이 제한 정책으로 차단되었습니다.',
        'Module source write reauthentication failed.' => '모듈 소스 쓰기 재인증에 실패했습니다.',
        'Module installed version synced from updates screen.' => '업데이트 화면에서 모듈 설치 버전을 동기화했습니다.',
        'Module installed version synced after schema updates.' => '스키마 업데이트 후 모듈 설치 버전을 동기화했습니다.',
        'Deposit transaction created.' => '예치금 거래가 생성되었습니다.',
        'Point transaction created.' => '포인트 거래가 생성되었습니다.',
        'Reward transaction created.' => '적립금 거래가 생성되었습니다.',
        'Notification deleted.' => '알림이 삭제되었습니다.',
        'Notification delivery status updated.' => '이메일 발송 작업 상태가 변경되었습니다.',
        'Notification created.' => '알림이 생성되었습니다.',
        'Community board skin updated.' => '커뮤니티 게시판 스킨이 변경되었습니다.',
        'Community board created.' => '커뮤니티 게시판이 생성되었습니다.',
        'Community board updated.' => '커뮤니티 게시판이 변경되었습니다.',
        'Community board group saved.' => '커뮤니티 게시판 그룹이 저장되었습니다.',
        'Community attachment created.' => '커뮤니티 첨부파일이 생성되었습니다.',
        'Community post created.' => '커뮤니티 게시글이 생성되었습니다.',
        'Community post updated by author.' => '커뮤니티 게시글이 작성자에 의해 변경되었습니다.',
        'Community post deleted by author.' => '커뮤니티 게시글이 작성자에 의해 삭제되었습니다.',
        'Community post status updated.' => '커뮤니티 게시글 상태가 변경되었습니다.',
        'Community comment created.' => '커뮤니티 댓글이 생성되었습니다.',
        'Community comment notifications created.' => '커뮤니티 댓글 알림 처리가 기록되었습니다.',
        'Community comment updated by author.' => '커뮤니티 댓글이 작성자에 의해 변경되었습니다.',
        'Community comment deleted by author.' => '커뮤니티 댓글이 작성자에 의해 삭제되었습니다.',
        'Community comment status updated.' => '커뮤니티 댓글 상태가 변경되었습니다.',
        'Community report created.' => '커뮤니티 신고가 생성되었습니다.',
        'Community report status updated.' => '커뮤니티 신고 상태가 변경되었습니다.',
        'Community message sent.' => '커뮤니티 쪽지가 발송되었습니다.',
        'Community message deleted by account.' => '커뮤니티 쪽지가 계정에 의해 삭제되었습니다.',
        'Community scrap added.' => '커뮤니티 스크랩이 추가되었습니다.',
        'Community scrap removed.' => '커뮤니티 스크랩이 해제되었습니다.',
        'Community settings updated.' => '커뮤니티 설정이 변경되었습니다.',
        'Community asset settings updated.' => '커뮤니티 포인트/금액 설정이 변경되었습니다.',
        'Community board asset settings updated.' => '커뮤니티 게시판 포인트/금액 설정이 변경되었습니다.',
        'Community board group asset settings updated.' => '커뮤니티 게시판 그룹 포인트/금액 설정이 변경되었습니다.',
        'Community level definitions updated.' => '커뮤니티 레벨 설정이 변경되었습니다.',
        'Community levels recalculated.' => '커뮤니티 레벨이 재계산되었습니다.',
        'Content asset settings updated.' => '콘텐츠 포인트/금액 설정이 변경되었습니다.',
        'Content comment created.' => '콘텐츠 댓글이 생성되었습니다.',
        'Content group asset settings updated.' => '콘텐츠 그룹 포인트/금액 설정이 변경되었습니다.',
        'Content hidden.' => '콘텐츠가 숨김 처리되었습니다.',
        'Site menu saved.' => '사이트 메뉴가 저장되었습니다.',
        'Site menu item saved.' => '사이트 메뉴 항목이 저장되었습니다.',
        'Site menu item order saved.' => '사이트 메뉴 항목 순서가 저장되었습니다.',
        'Site menu item deleted.' => '사이트 메뉴 항목이 삭제되었습니다.',
        'Site menu deleted.' => '사이트 메뉴가 삭제되었습니다.',
        'Logo asset uploaded and assigned.' => '로고 이미지가 업로드되고 배치되었습니다.',
        'Logo assignment created.' => '로고 배치가 생성되었습니다.',
        'Logo asset status changed.' => '로고 이미지 상태가 변경되었습니다.',
        'Logo assignment status changed.' => '로고 배치 상태가 변경되었습니다.',
        'Member group saved.' => '회원 그룹이 저장되었습니다.',
        'Member group rule saved.' => '회원 그룹 규칙이 저장되었습니다.',
        'Member group rules evaluated.' => '회원 그룹 규칙이 평가되었습니다.',
        'Member group membership changed.' => '회원 그룹 배정이 변경되었습니다.',
        'Member settings updated.' => '회원 설정이 변경되었습니다.',
    ];

    if (isset($labels[$message])) {
        return (string) $labels[$message];
    }

    if (preg_match('/\A[A-Za-z0-9 .:_-]+\.\z/', $message) === 1) {
        $fallbackMessage = sr_admin_event_type_label((string) ($log['event_type'] ?? ''));
        return $fallbackMessage !== '' ? $fallbackMessage : $message;
    }

    return $message;
}

function sr_admin_audit_log_display_actor_type(array $log): string
{
    return sr_admin_code_label((string) ($log['actor_type'] ?? '-'), 'actor_type');
}

function sr_admin_audit_log_display_target(array $log): string
{
    $targetType = (string) ($log['target_type'] ?? '');
    $targetId = (string) ($log['target_id'] ?? '');
    $moduleLabels = [
        'admin' => '관리자',
        'banner' => '배너',
        'community' => '커뮤니티',
        'content' => '콘텐츠',
        'deposit' => '예치금',
        'logo_manager' => '로고 관리',
        'member' => '회원',
        'notification' => '알림',
        'point' => '포인트',
        'popup_layer' => '팝업레이어',
        'privacy' => '개인정보 처리',
        'reward' => '적립금',
        'seo' => 'SEO',
        'site_menu' => '사이트 메뉴',
    ];

    if ($targetType === 'retention') {
        return '데이터 정리';
    }

    if ($targetType === 'site_settings' || $targetType === 'site_setting') {
        return '환경설정';
    }

    if ($targetType === 'module' && isset($moduleLabels[$targetId])) {
        return $moduleLabels[$targetId];
    }

    if ($targetType === 'module' && str_contains($targetId, ':')) {
        $moduleKey = strtok($targetId, ':');
        if (is_string($moduleKey) && isset($moduleLabels[$moduleKey])) {
            return $moduleLabels[$moduleKey];
        }
    }

    return sr_admin_code_label($targetType, 'target_type');
}

function sr_admin_audit_log_query_parts(array &$filters): array
{
    $where = [];
    $params = [];
    $filters['field'] = sr_admin_audit_log_search_field((string) ($filters['field'] ?? 'event_type'));
    $filters['q'] = trim((string) ($filters['q'] ?? ''));
    $filters['event_type'] = sr_admin_audit_log_identifier_filter((string) ($filters['event_type'] ?? ''), 80);
    $filters['target_type'] = sr_admin_audit_log_identifier_filter((string) ($filters['target_type'] ?? ''), 60);
    $filters['target_id'] = sr_admin_audit_log_target_id_filter((string) ($filters['target_id'] ?? ''), 80);
    $filters['actor_type'] = sr_admin_audit_log_identifier_filter((string) ($filters['actor_type'] ?? ''), 40);
    $filters['ip_address'] = sr_admin_audit_log_ip_filter((string) ($filters['ip_address'] ?? ''));
    $filters['result'] = sr_admin_audit_log_result_filter($filters['result']);
    $filters['date_from'] = sr_admin_audit_log_date_filter($filters['date_from']);
    $filters['date_to'] = sr_admin_audit_log_date_filter($filters['date_to']);

    if ($filters['q'] !== '') {
        if ($filters['field'] === 'event_type') {
            $filters['q'] = sr_admin_audit_log_identifier_filter($filters['q'], 80);
            if ($filters['q'] !== '') {
                $where[] = 'event_type = :audit_keyword';
                $params['audit_keyword'] = $filters['q'];
            }
        } elseif ($filters['field'] === 'target_type') {
            $filters['q'] = sr_admin_audit_log_identifier_filter($filters['q'], 60);
            if ($filters['q'] !== '') {
                $where[] = 'target_type = :audit_keyword';
                $params['audit_keyword'] = $filters['q'];
            }
        } elseif ($filters['field'] === 'target_id') {
            $filters['q'] = sr_admin_audit_log_target_id_filter($filters['q'], 80);
            if ($filters['q'] !== '') {
                $where[] = 'target_id = :audit_keyword';
                $params['audit_keyword'] = $filters['q'];
            }
        } elseif ($filters['field'] === 'actor_type') {
            $filters['q'] = sr_admin_audit_log_identifier_filter($filters['q'], 40);
            if ($filters['q'] !== '') {
                $where[] = 'actor_type = :audit_keyword';
                $params['audit_keyword'] = $filters['q'];
            }
        } elseif ($filters['field'] === 'ip_address') {
            $filters['q'] = sr_admin_audit_log_ip_filter($filters['q']);
            if ($filters['q'] !== '') {
                $where[] = 'ip_address = :audit_keyword';
                $params['audit_keyword'] = $filters['q'];
            }
        } elseif (ctype_digit($filters['q'])) {
            $where[] = 'actor_account_id = :actor_account_id';
            $params['actor_account_id'] = (int) $filters['q'];
        } else {
            $filters['q'] = '';
        }
    }

    if ($filters['event_type'] !== '') {
        $where[] = 'event_type = :event_type';
        $params['event_type'] = $filters['event_type'];
    }

    if ($filters['target_type'] !== '') {
        $where[] = 'target_type = :target_type';
        $params['target_type'] = $filters['target_type'];
    }

    if ($filters['target_id'] !== '') {
        $where[] = 'target_id = :target_id';
        $params['target_id'] = $filters['target_id'];
    }

    if ($filters['actor_type'] !== '') {
        $where[] = 'actor_type = :actor_type';
        $params['actor_type'] = $filters['actor_type'];
    }

    if ($filters['ip_address'] !== '') {
        $where[] = 'ip_address = :ip_address';
        $params['ip_address'] = $filters['ip_address'];
    }

    if ($filters['result'] !== '') {
        $where[] = 'result = :result';
        $params['result'] = $filters['result'];
    }

    if ($filters['date_from'] !== '') {
        $dateFrom = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['date_from']);
        if ($dateFrom instanceof DateTimeImmutable) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $dateFrom->format('Y-m-d 00:00:00');
        }
    }

    if ($filters['date_to'] !== '') {
        $dateTo = DateTimeImmutable::createFromFormat('!Y-m-d', $filters['date_to']);
        if ($dateTo instanceof DateTimeImmutable) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $dateTo->format('Y-m-d 23:59:59');
        }
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_admin_audit_log_page_number(string $value): int
{
    if (preg_match('/\A[1-9][0-9]*\z/', $value) !== 1) {
        return 1;
    }

    return max(1, min(1000000, (int) $value));
}

function sr_admin_audit_log_count(PDO $pdo, array $filters): int
{
    $queryParts = sr_admin_audit_log_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];

    $sql = 'SELECT COUNT(*) AS count_value FROM sr_audit_logs';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? (int) ($row['count_value'] ?? 0) : 0;
}

function sr_admin_audit_log_page_url(array $filters, int $page): string
{
    $query = [];
    $field = sr_admin_audit_log_search_field((string) ($filters['field'] ?? 'event_type'));
    $keyword = trim((string) ($filters['q'] ?? ''));
    if ($keyword !== '') {
        $query['field'] = $field;
        $query['q'] = $keyword;
    }

    foreach (['event_type', 'target_type', 'target_id', 'actor_type', 'ip_address', 'result', 'date_from', 'date_to'] as $filterKey) {
        $value = trim((string) ($filters[$filterKey] ?? ''));
        if ($value !== '') {
            $query[$filterKey] = $value;
        }
    }

    if ($page > 1) {
        $query['page'] = $page;
    }

    return sr_url('/admin/audit-logs' . ($query !== [] ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''));
}

function sr_admin_audit_logs(PDO $pdo, array &$filters, int $limit = 100, int $offset = 0, array $sort = []): array
{
    $queryParts = sr_admin_audit_log_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];
    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);

    $sql = 'SELECT id, actor_account_id, actor_type, event_type, target_type, target_id, result, ip_address, user_agent, message, metadata_json, created_at
            FROM sr_audit_logs';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= sr_admin_sort_order_sql(sr_admin_audit_log_sort_options(), $sort, sr_admin_audit_log_default_sort())
        . ' LIMIT ' . (string) $limit . ' OFFSET ' . (string) $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $logs = [];
    foreach ($stmt->fetchAll() as $row) {
        $logs[] = $row;
    }

    return $logs;
}
