<?php

declare(strict_types=1);

function sr_admin_audit_log_filters(): array
{
    return [
        'event_type' => sr_get_string('event_type', 80),
        'target_type' => sr_get_string('target_type', 60),
        'actor_account_id' => sr_get_string('actor_account_id', 20),
        'result' => sr_get_string('result', 30),
        'date_from' => sr_get_string('date_from', 30),
        'date_to' => sr_get_string('date_to', 30),
    ];
}

function sr_admin_audit_log_identifier_filter(string $value, int $maxLength): string
{
    if ($value === '' || strlen($value) > $maxLength) {
        return '';
    }

    return preg_match('/\A[a-z][a-z0-9_.-]*\z/', $value) === 1 ? $value : '';
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

    $encoded = json_encode(sr_admin_audit_metadata_redact($metadata), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
        'Member sessions could not be revoked.' => '회원 세션을 폐기하지 못했습니다.',
        'Member sessions revoked.' => '회원 세션이 폐기되었습니다.',
        'Member status update failed.' => '회원 상태 변경에 실패했습니다.',
        'Member status updated.' => '회원 상태가 변경되었습니다.',
        'Retention cleanup completed.' => '보관 정리가 완료되었습니다.',
        'Privacy request updated.' => '개인정보 처리 요청이 변경되었습니다.',
        'Privacy request export reauthentication blocked by throttle.' => '개인정보 처리 자료 다운로드 재인증이 제한 정책으로 차단되었습니다.',
        'Privacy request export reauthentication failed.' => '개인정보 처리 자료 다운로드 재인증에 실패했습니다.',
        'Privacy request export downloaded.' => '개인정보 처리 자료 파일이 다운로드되었습니다.',
        'Site setting saved.' => '사이트 설정 항목이 저장되었습니다.',
        'Site setting deleted.' => '사이트 설정 항목이 삭제되었습니다.',
        'Site settings updated.' => '사이트 설정이 변경되었습니다.',
        'Sensitive site setting reauthentication blocked by throttle.' => '민감한 사이트 설정 재인증이 제한 정책으로 차단되었습니다.',
        'Sensitive site setting reauthentication failed.' => '민감한 사이트 설정 재인증에 실패했습니다.',
        'Admin role changed.' => '관리자 역할이 변경되었습니다.',
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
        'Deposit transaction created.' => '예치금 거래가 생성되었습니다.',
        'Point transaction created.' => '포인트 거래가 생성되었습니다.',
        'Reward transaction created.' => '적립금 거래가 생성되었습니다.',
        'Notification deleted.' => '알림이 삭제되었습니다.',
        'Notification delivery status updated.' => '알림 발송 상태가 변경되었습니다.',
        'Notification created.' => '알림이 생성되었습니다.',
        'Community board skin updated.' => '커뮤니티 게시판 스킨이 변경되었습니다.',
        'Community board created.' => '커뮤니티 게시판이 생성되었습니다.',
        'Community board updated.' => '커뮤니티 게시판이 변경되었습니다.',
        'Community board group saved.' => '커뮤니티 게시판 그룹이 저장되었습니다.',
        'Community post status updated.' => '커뮤니티 게시글 상태가 변경되었습니다.',
        'Community comment status updated.' => '커뮤니티 댓글 상태가 변경되었습니다.',
        'Community report status updated.' => '커뮤니티 신고 상태가 변경되었습니다.',
        'Community settings updated.' => '커뮤니티 설정이 변경되었습니다.',
        'Community level definitions updated.' => '커뮤니티 레벨 정의가 변경되었습니다.',
        'Community levels recalculated.' => '커뮤니티 레벨이 재계산되었습니다.',
        'Site menu saved.' => '사이트 메뉴가 저장되었습니다.',
        'Site menu item saved.' => '사이트 메뉴 항목이 저장되었습니다.',
        'Site menu item deleted.' => '사이트 메뉴 항목이 삭제되었습니다.',
        'Site menu deleted.' => '사이트 메뉴가 삭제되었습니다.',
        'Member group saved.' => '회원 그룹이 저장되었습니다.',
        'Member group rule saved.' => '회원 그룹 규칙이 저장되었습니다.',
        'Member group rules evaluated.' => '회원 그룹 규칙이 평가되었습니다.',
        'Member group membership changed.' => '회원 그룹 배정이 변경되었습니다.',
        'Member settings updated.' => '회원 설정이 변경되었습니다.',
    ];

    return (string) ($labels[$message] ?? $message);
}

function sr_admin_audit_log_query_parts(array &$filters): array
{
    $where = [];
    $params = [];
    $filters['event_type'] = sr_admin_audit_log_identifier_filter($filters['event_type'], 80);
    $filters['target_type'] = sr_admin_audit_log_identifier_filter($filters['target_type'], 60);
    $filters['result'] = sr_admin_audit_log_result_filter($filters['result']);
    $filters['date_from'] = sr_admin_audit_log_date_filter($filters['date_from']);
    $filters['date_to'] = sr_admin_audit_log_date_filter($filters['date_to']);

    if ($filters['event_type'] !== '') {
        $where[] = 'event_type = :event_type';
        $params['event_type'] = $filters['event_type'];
    }

    if ($filters['target_type'] !== '') {
        $where[] = 'target_type = :target_type';
        $params['target_type'] = $filters['target_type'];
    }

    if ($filters['actor_account_id'] !== '') {
        if (ctype_digit($filters['actor_account_id'])) {
            $where[] = 'actor_account_id = :actor_account_id';
            $params['actor_account_id'] = (int) $filters['actor_account_id'];
        } else {
            $filters['actor_account_id'] = '';
        }
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

function sr_admin_audit_logs(PDO $pdo, array &$filters): array
{
    $queryParts = sr_admin_audit_log_query_parts($filters);
    $where = $queryParts['where'];
    $params = $queryParts['params'];

    $sql = 'SELECT id, actor_account_id, actor_type, event_type, target_type, target_id, result, ip_address, message, metadata_json, created_at
            FROM sr_audit_logs';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY id DESC LIMIT 100';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $logs = [];
    foreach ($stmt->fetchAll() as $row) {
        $logs[] = $row;
    }

    return $logs;
}
