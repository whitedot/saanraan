<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/core/helpers.php';

if (!class_exists('SrIdentityVerificationDuplicateException')) {
    class SrIdentityVerificationDuplicateException extends RuntimeException
    {
    }
}

function sr_identity_verification_default_settings(): array
{
    return [
        'enabled' => false,
        'default_provider_key' => '',
        'attempt_ttl_seconds' => 600,
        'result_valid_days' => 365,
        'require_https' => true,
        'use_birth_date' => false,
    ];
}

function sr_identity_verification_settings(PDO $pdo): array
{
    $settings = array_merge(sr_identity_verification_default_settings(), sr_module_settings($pdo, 'identity_verification'));
    $settings['enabled'] = sr_truthy($settings['enabled'] ?? false);
    $settings['default_provider_key'] = sr_identity_verification_provider_key((string) ($settings['default_provider_key'] ?? ''));
    $settings['attempt_ttl_seconds'] = min(3600, max(60, (int) ($settings['attempt_ttl_seconds'] ?? 600)));
    $settings['result_valid_days'] = min(3650, max(0, (int) ($settings['result_valid_days'] ?? 365)));
    $settings['require_https'] = sr_truthy($settings['require_https'] ?? true);
    $settings['use_birth_date'] = sr_truthy($settings['use_birth_date'] ?? false);

    return $settings;
}

function sr_identity_verification_birth_date_enabled(PDO $pdo): bool
{
    return !empty(sr_identity_verification_settings($pdo)['use_birth_date']);
}

function sr_identity_verification_adult_verification_available(PDO $pdo): bool
{
    return sr_identity_verification_birth_date_enabled($pdo);
}

function sr_identity_verification_adult_setting_errors(PDO $pdo, bool $adultRequired, string $label = '성인 본인확인'): array
{
    if (!$adultRequired || sr_identity_verification_adult_verification_available($pdo)) {
        return [];
    }

    return [$label . '을 사용하려면 본인확인 환경설정에서 생년월일 사용을 먼저 켜야 합니다.'];
}

function sr_identity_verification_save_settings(PDO $pdo, array $settings): void
{
    $stmt = $pdo->prepare("SELECT id FROM sr_modules WHERE module_key = 'identity_verification' LIMIT 1");
    $stmt->execute();
    $module = $stmt->fetch();
    if (!is_array($module)) {
        throw new RuntimeException('Identity verification module is not installed.');
    }

    $save = $pdo->prepare(
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
    foreach ($settings as $key => $value) {
        if (!is_string($key) || preg_match('/\A[a-z][a-z0-9_]{1,120}\z/', $key) !== 1) {
            continue;
        }
        $valueType = is_bool($value) ? 'bool' : (is_int($value) ? 'int' : 'string');
        $save->execute([
            'module_id' => (int) $module['id'],
            'setting_key' => $key,
            'setting_value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
            'value_type' => $valueType,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    sr_clear_module_settings_cache('identity_verification');
}

function sr_identity_verification_provider_key(string $providerKey): string
{
    $providerKey = strtolower(trim($providerKey));
    return preg_match('/\A[a-z][a-z0-9_]{1,59}\z/', $providerKey) === 1 ? $providerKey : '';
}

function sr_identity_verification_purpose(string $purpose): string
{
    $purpose = strtolower(trim($purpose));
    return preg_match('/\A[a-z][a-z0-9_.]{1,79}\z/', $purpose) === 1 ? $purpose : '';
}

function sr_identity_verification_attempt_status_labels(): array
{
    return [
        'ready' => '요청 준비',
        'pending' => '인증 진행 중',
        'verified' => '검증 완료',
        'failed' => '확인 실패',
        'expired' => '시간 만료',
        'canceled' => '사용자 취소',
    ];
}

function sr_identity_verification_attempt_status_label(string $status): string
{
    $labels = sr_identity_verification_attempt_status_labels();

    return (string) ($labels[$status] ?? $status);
}

function sr_identity_verification_attempt_status_class(string $status): string
{
    return match ($status) {
        'verified' => 'is-success',
        'ready', 'pending' => 'is-warning',
        'failed', 'expired', 'canceled' => 'is-danger',
        default => 'is-warning',
    };
}

function sr_identity_verification_purpose_labels(): array
{
    return [
        'asset.exchange' => '자산 환전 신청',
        'community.adult_board' => '커뮤니티 성인 게시판',
        'community.restricted_board' => '커뮤니티 제한 게시판',
        'content.view' => '콘텐츠 열람 본인확인',
        'content.view.adult' => '콘텐츠 열람 성인 확인',
        'content.author_application' => '콘텐츠 작성자 신청',
        'content.author_application.adult' => '콘텐츠 작성자 성인 확인',
        'deposit.refund_request' => '예치금 환불 신청',
        'member.account_security' => '계정 보안 작업',
        'member.mfa.login' => '로그인 2차 인증',
        'member.registration' => '회원가입',
        'member.withdrawal' => '회원탈퇴',
        'quiz.view' => '퀴즈 참여 본인확인',
        'quiz.view.adult' => '퀴즈 참여 성인 확인',
        'reward.withdrawal_request' => '적립금 출금 신청',
        'survey.view' => '설문 참여 본인확인',
        'survey.view.adult' => '설문 참여 성인 확인',
    ];
}

function sr_identity_verification_purpose_label(string $purpose): string
{
    $labels = sr_identity_verification_purpose_labels();

    return (string) ($labels[$purpose] ?? $purpose);
}

function sr_identity_verification_admin_purpose_filter_value(string $input): string
{
    $input = trim($input);
    $purpose = sr_identity_verification_purpose($input);
    if ($purpose !== '') {
        return $purpose;
    }

    foreach (sr_identity_verification_purpose_labels() as $purposeKey => $purposeLabel) {
        if ($input === (string) $purposeLabel) {
            return (string) $purposeKey;
        }
    }

    return '';
}

function sr_identity_verification_failure_code_labels(): array
{
    return [
        'attempt_expired' => '시도 유효 시간이 지났습니다.',
        'duplicate_identity' => '이미 다른 계정에 연결된 본인확인 정보입니다.',
        'provider_callback_exception' => '제공자 callback 처리 중 예외가 발생했습니다.',
        'provider_callback_failed' => '제공자 callback 검증에 실패했습니다.',
        'provider_prepare_failed' => '제공자 인증 요청 준비에 실패했습니다.',
        'provider_verification_failed' => '제공자 검증 결과가 실패로 돌아왔습니다.',
        'provider_verify_failed' => '제공자 return 검증 처리에 실패했습니다.',
        'inicis_mtx_id_mismatch' => 'KG이니시스 거래 식별값이 일치하지 않습니다.',
        'inicis_query_failed' => 'KG이니시스 결과 조회에 실패했습니다.',
        'inicis_result_failed' => 'KG이니시스 인증 결과가 실패입니다.',
        'inicis_result_query_invalid' => 'KG이니시스 결과 조회 응답이 올바르지 않습니다.',
        'kcp_query_failed' => 'KCP 결과 조회에 실패했습니다.',
        'kcp_reg_cert_key_mismatch' => 'KCP 인증 등록 키가 일치하지 않습니다.',
        'kcp_result_failed' => 'KCP 인증 결과가 실패입니다.',
    ];
}

function sr_identity_verification_failure_code_label(string $failureCode): string
{
    $failureCode = trim($failureCode);
    if ($failureCode === '') {
        return '';
    }

    $labels = sr_identity_verification_failure_code_labels();
    if (isset($labels[$failureCode])) {
        return (string) $labels[$failureCode];
    }

    if (str_starts_with($failureCode, 'kcp_')) {
        return 'KCP 제공자 오류입니다.';
    }
    if (str_starts_with($failureCode, 'inicis_')) {
        return 'KG이니시스 제공자 오류입니다.';
    }

    return $failureCode;
}

function sr_identity_verification_requirement_mode(string $mode): string
{
    $mode = strtolower(trim($mode));
    return in_array($mode, ['off', 'optional', 'required'], true) ? $mode : 'off';
}

function sr_identity_verification_requirement_mode_options(): array
{
    return [
        'off' => '사용 안 함',
        'optional' => '선택',
        'required' => '필수',
    ];
}

function sr_identity_verification_setting_key(string $providerKey, string $settingKey): string
{
    $providerKey = sr_identity_verification_provider_key($providerKey);
    if ($providerKey === '' || preg_match('/\A[a-z][a-z0-9_]{1,80}\z/', $settingKey) !== 1) {
        return '';
    }

    return 'provider_' . $providerKey . '_' . $settingKey;
}

function sr_identity_verification_available(PDO $pdo, string $purpose = ''): bool
{
    $purpose = sr_identity_verification_purpose($purpose);
    $settings = sr_identity_verification_settings($pdo);
    if (empty($settings['enabled'])) {
        return false;
    }
    if ($purpose !== '' && str_ends_with($purpose, '.adult') && empty($settings['use_birth_date'])) {
        return false;
    }
    if ($purpose === 'community.adult_board' && empty($settings['use_birth_date'])) {
        return false;
    }

    return sr_identity_verification_select_provider($pdo, '', $purpose) !== null;
}

function sr_identity_verification_simple_auth_preferred_purposes(): array
{
    return [
        'asset.exchange',
        'content.author_application',
        'deposit.refund_request',
        'member.account_security',
        'member.mfa.login',
        'member.withdrawal',
        'reward.withdrawal_request',
    ];
}

function sr_identity_verification_preferred_provider_keys_for_purpose(string $purpose): array
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($purpose === '' || !in_array($purpose, sr_identity_verification_simple_auth_preferred_purposes(), true)) {
        return [];
    }

    return ['inicis_simple_auth'];
}

function sr_identity_verification_identity_provider_required_purposes(): array
{
    return [
        'community.adult_board',
        'community.restricted_board',
        'content.author_application.adult',
        'content.view.adult',
        'member.registration',
        'quiz.view.adult',
        'survey.view.adult',
    ];
}

function sr_identity_verification_purpose_requires_identity_provider(string $purpose): bool
{
    $purpose = sr_identity_verification_purpose($purpose);
    return $purpose !== '' && in_array($purpose, sr_identity_verification_identity_provider_required_purposes(), true);
}

function sr_identity_verification_provider_supports_purpose(array $provider, string $purpose): bool
{
    if (!sr_identity_verification_purpose_requires_identity_provider($purpose)) {
        return true;
    }

    foreach ((array) ($provider['supported_methods'] ?? []) as $method) {
        if (in_array((string) $method, ['identity', 'integrated_identity', 'mobile_identity'], true)) {
            return true;
        }
    }

    return false;
}

function sr_identity_verification_account_satisfies(PDO $pdo, int $accountId, string $purpose, ?int $maxAgeDays = null): bool
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($accountId <= 0 || $purpose === '') {
        return false;
    }

    $params = [
        'account_id' => $accountId,
        'purpose' => $purpose,
        'now' => sr_now(),
    ];
    $ageSql = '';
    if ($maxAgeDays !== null && $maxAgeDays > 0) {
        $ageSql = ' AND r.verified_at >= :min_verified_at';
        $params['min_verified_at'] = gmdate('Y-m-d H:i:s', time() - ($maxAgeDays * 86400));
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM sr_identity_verification_links l
         INNER JOIN sr_identity_verification_results r ON r.id = l.result_id
         WHERE l.account_id = :account_id
           AND l.purpose = :purpose
           AND l.revoked_at IS NULL
           AND (r.expires_at IS NULL OR r.expires_at > :now)' . $ageSql . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function sr_identity_verification_account_satisfies_adult(PDO $pdo, int $accountId, string $purpose, ?int $maxAgeDays = null): bool
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($accountId <= 0 || $purpose === '') {
        return false;
    }
    if (!sr_identity_verification_adult_verification_available($pdo)) {
        return false;
    }

    $params = [
        'account_id' => $accountId,
        'purpose' => $purpose,
        'now' => sr_now(),
    ];
    $ageSql = '';
    if ($maxAgeDays !== null && $maxAgeDays > 0) {
        $ageSql = ' AND r.verified_at >= :min_verified_at';
        $params['min_verified_at'] = gmdate('Y-m-d H:i:s', time() - ($maxAgeDays * 86400));
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM sr_identity_verification_links l
         INNER JOIN sr_identity_verification_results r ON r.id = l.result_id
         WHERE l.account_id = :account_id
           AND l.purpose = :purpose
           AND l.revoked_at IS NULL
           AND r.age_over_19 = 1
           AND (r.expires_at IS NULL OR r.expires_at > :now)' . $ageSql . '
         LIMIT 1'
    );
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function sr_identity_verification_duplicate_identity_message(): string
{
    return '이미 다른 계정에 연결된 본인확인 정보입니다.';
}

function sr_identity_verification_require_provider_response(): void
{
    sr_request_contract_mark('csrf_checked');
}

function sr_identity_verification_utc_timestamp(string $dateTime): ?int
{
    $dateTime = trim($dateTime);
    if ($dateTime === '') {
        return null;
    }

    try {
        return (new DateTimeImmutable($dateTime, new DateTimeZone('UTC')))->getTimestamp();
    } catch (Throwable $exception) {
        return null;
    }
}

function sr_identity_verification_attempt_expired(array $attempt, ?int $now = null): bool
{
    $expiresAt = sr_identity_verification_utc_timestamp((string) ($attempt['expires_at'] ?? ''));
    if ($expiresAt === null) {
        return false;
    }

    return $expiresAt < ($now ?? time());
}

function sr_identity_verification_result_identity_hashes(PDO $pdo, int $resultId): ?array
{
    if ($resultId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, ci_hash FROM sr_identity_verification_results WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $resultId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_duplicate_account_by_ci_hash(PDO $pdo, string $ciHash, int $accountId = 0): ?array
{
    $ciHash = trim($ciHash);
    if ($ciHash === '') {
        return null;
    }

    $params = ['ci_hash' => $ciHash];
    $accountSql = '';
    if ($accountId > 0) {
        $accountSql = ' AND lock_row.account_id <> :account_id';
        $params['account_id'] = $accountId;
    }
    $stmt = $pdo->prepare(
        'SELECT lock_row.account_id, a.status
         FROM sr_identity_verification_identity_locks lock_row
         INNER JOIN sr_member_accounts a ON a.id = lock_row.account_id
         WHERE lock_row.ci_hash = :ci_hash
           AND a.status NOT IN (\'withdrawn\', \'anonymized\')' . $accountSql . '
         LIMIT 1'
    );
    $stmt->execute($params);
    $row = $stmt->fetch();
    if (is_array($row)) {
        return $row;
    }

    $params = [
        'ci_hash' => $ciHash,
        'now' => sr_now(),
    ];
    $accountSql = '';
    if ($accountId > 0) {
        $accountSql = ' AND l.account_id <> :account_id';
        $params['account_id'] = $accountId;
    }
    $stmt = $pdo->prepare(
        'SELECT l.account_id, a.status
         FROM sr_identity_verification_links l
         INNER JOIN sr_identity_verification_results r ON r.id = l.result_id
         INNER JOIN sr_member_accounts a ON a.id = l.account_id
         WHERE r.ci_hash = :ci_hash
           AND l.revoked_at IS NULL
           AND (r.expires_at IS NULL OR r.expires_at > :now)
           AND a.status NOT IN (\'withdrawn\', \'anonymized\')' . $accountSql . '
         LIMIT 1'
    );
    $stmt->execute($params);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_result_duplicate_account(PDO $pdo, int $resultId, int $accountId = 0): ?array
{
    $identity = sr_identity_verification_result_identity_hashes($pdo, $resultId);
    if (!is_array($identity)) {
        return null;
    }

    return sr_identity_verification_duplicate_account_by_ci_hash($pdo, (string) ($identity['ci_hash'] ?? ''), $accountId);
}

function sr_identity_verification_start_url(string $purpose, string $returnUrl): string
{
    $purpose = sr_identity_verification_purpose($purpose);
    $returnUrl = sr_identity_verification_safe_return_url($returnUrl);
    $query = ['purpose' => $purpose !== '' ? $purpose : 'default'];
    if ($returnUrl !== '/') {
        $query['return_url'] = $returnUrl;
    }

    return sr_url('/identity/verify/start?' . http_build_query($query));
}

function sr_identity_verification_requirement_policy(PDO $pdo, int $accountId, string $purpose, string $mode, string $returnUrl = '', ?int $maxAgeDays = null): array
{
    $purpose = sr_identity_verification_purpose($purpose);
    $mode = sr_identity_verification_requirement_mode($mode);
    $settings = sr_identity_verification_settings($pdo);
    $enabled = $purpose !== '' && $mode !== 'off';
    $available = $enabled && !empty($settings['enabled']) && sr_identity_verification_select_provider($pdo, '', $purpose) !== null;
    $satisfied = $enabled && $accountId > 0
        ? sr_identity_verification_account_satisfies($pdo, $accountId, $purpose, $maxAgeDays)
        : false;

    return [
        'mode' => $mode,
        'enabled' => $enabled,
        'available' => $available,
        'required' => $enabled && $mode === 'required',
        'optional' => $enabled && $mode === 'optional',
        'satisfied' => $satisfied,
        'purpose' => $purpose,
        'start_url' => $available ? sr_identity_verification_start_url($purpose, $returnUrl) : '',
    ];
}

function sr_identity_verification_session_key(): string
{
    return 'sr_identity_verification_results';
}

function sr_identity_verification_registration_snapshot_session_key(): string
{
    return 'sr_identity_verification_registration_snapshots';
}

function sr_identity_verification_identity_snapshot(array $identity): array
{
    $name = trim((string) ($identity['name'] ?? ''));
    $phone = sr_identity_verification_digits((string) ($identity['phone'] ?? ''));
    $birthDate = sr_identity_verification_birth_date((string) ($identity['birth_date'] ?? ''));

    $ageOver14 = $identity['age_over_14'] ?? null;
    $ageOver19 = $identity['age_over_19'] ?? null;

    return [
        'name' => $name !== '' ? substr($name, 0, 120) : '',
        'phone' => $phone !== '' ? substr($phone, 0, 30) : '',
        'birth_date' => $birthDate ?? '',
        'age_over_14' => $ageOver14 === null || $ageOver14 === '' ? '' : (sr_truthy($ageOver14) ? '1' : '0'),
        'age_over_19' => $ageOver19 === null || $ageOver19 === '' ? '' : (sr_truthy($ageOver19) ? '1' : '0'),
    ];
}

function sr_identity_verification_purge_registration_snapshots(int $maxAgeSeconds = 300): void
{
    $sessionKey = sr_identity_verification_registration_snapshot_session_key();
    if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
        return;
    }

    $minCreatedAt = time() - max(30, $maxAgeSeconds);
    foreach ($_SESSION[$sessionKey] as $key => $snapshot) {
        if (!is_array($snapshot) || (int) ($snapshot['created_at'] ?? 0) < $minCreatedAt) {
            unset($_SESSION[$sessionKey][$key]);
        }
    }
}

function sr_identity_verification_remember_registration_snapshot(array $config, string $stateToken, array $identitySnapshot): void
{
    $stateToken = trim($stateToken);
    if ($stateToken === '') {
        return;
    }

    sr_identity_verification_purge_registration_snapshots();
    $sessionKey = sr_identity_verification_registration_snapshot_session_key();
    if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [];
    }

    $_SESSION[$sessionKey][sr_identity_verification_hash_token($stateToken, $config)] = [
        'identity' => sr_identity_verification_identity_snapshot($identitySnapshot),
        'created_at' => time(),
    ];
}

function sr_identity_verification_take_registration_snapshot(array $config, string $stateToken, ?int $maxAgeSeconds = 300): array
{
    $stateToken = trim($stateToken);
    if ($stateToken === '') {
        return [];
    }

    sr_identity_verification_purge_registration_snapshots($maxAgeSeconds ?? 300);
    $sessionKey = sr_identity_verification_registration_snapshot_session_key();
    $snapshotKey = sr_identity_verification_hash_token($stateToken, $config);
    $snapshot = isset($_SESSION[$sessionKey][$snapshotKey]) && is_array($_SESSION[$sessionKey][$snapshotKey])
        ? $_SESSION[$sessionKey][$snapshotKey]
        : null;
    unset($_SESSION[$sessionKey][$snapshotKey]);
    if (!is_array($snapshot)) {
        return [];
    }
    if ($maxAgeSeconds !== null && $maxAgeSeconds > 0 && (int) ($snapshot['created_at'] ?? 0) < time() - $maxAgeSeconds) {
        return [];
    }

    $identity = isset($snapshot['identity']) && is_array($snapshot['identity']) ? $snapshot['identity'] : [];
    return sr_identity_verification_identity_snapshot($identity);
}

function sr_identity_verification_remember_session_result(array $attempt, int $resultId, array $identitySnapshot = []): void
{
    if ($resultId < 1) {
        return;
    }

    $purpose = sr_identity_verification_purpose((string) ($attempt['purpose'] ?? ''));
    if ($purpose === '') {
        return;
    }

    $sessionKey = sr_identity_verification_session_key();
    if (!isset($_SESSION[$sessionKey]) || !is_array($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [];
    }
    $_SESSION[$sessionKey][$purpose] = [
        'result_id' => $resultId,
        'account_id' => (int) ($attempt['account_id'] ?? 0),
        'purpose' => $purpose,
        'verified_at' => time(),
        'identity' => sr_identity_verification_identity_snapshot($identitySnapshot),
    ];
}

function sr_identity_verification_session_identity_snapshot(string $purpose, int $accountId, ?int $maxAgeSeconds = 900): array
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($purpose === '') {
        return [];
    }

    $sessionKey = sr_identity_verification_session_key();
    $sessionResults = isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : [];
    $sessionResult = isset($sessionResults[$purpose]) && is_array($sessionResults[$purpose]) ? $sessionResults[$purpose] : null;
    if (!is_array($sessionResult)) {
        return [];
    }
    if ($maxAgeSeconds !== null && $maxAgeSeconds > 0 && (int) ($sessionResult['verified_at'] ?? 0) < time() - $maxAgeSeconds) {
        return [];
    }
    if ((int) ($sessionResult['account_id'] ?? 0) !== $accountId) {
        return [];
    }

    $identity = isset($sessionResult['identity']) && is_array($sessionResult['identity']) ? $sessionResult['identity'] : [];
    return sr_identity_verification_identity_snapshot($identity);
}

function sr_identity_verification_session_result(PDO $pdo, string $purpose, int $accountId, ?int $maxAgeSeconds = 900): ?array
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($purpose === '') {
        return null;
    }

    $sessionKey = sr_identity_verification_session_key();
    $sessionResults = isset($_SESSION[$sessionKey]) && is_array($_SESSION[$sessionKey]) ? $_SESSION[$sessionKey] : [];
    $sessionResult = isset($sessionResults[$purpose]) && is_array($sessionResults[$purpose]) ? $sessionResults[$purpose] : null;
    if (!is_array($sessionResult)) {
        return null;
    }

    if ($maxAgeSeconds !== null && $maxAgeSeconds > 0 && (int) ($sessionResult['verified_at'] ?? 0) < time() - $maxAgeSeconds) {
        unset($_SESSION[$sessionKey][$purpose]);
        return null;
    }
    if ((int) ($sessionResult['account_id'] ?? 0) !== $accountId) {
        return null;
    }

    $resultId = (int) ($sessionResult['result_id'] ?? 0);
    if ($resultId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT r.*, a.purpose, a.status AS attempt_status
         FROM sr_identity_verification_results r
         INNER JOIN sr_identity_verification_attempts a ON a.id = r.attempt_id
         WHERE r.id = :id
           AND a.purpose = :purpose
           AND a.status = :status
           AND (r.expires_at IS NULL OR r.expires_at > :now)
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $resultId,
        'purpose' => $purpose,
        'status' => 'verified',
        'now' => sr_now(),
    ]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_consume_session_result(PDO $pdo, string $purpose, int $accountId, ?int $maxAgeSeconds = 900): ?array
{
    $purpose = sr_identity_verification_purpose($purpose);
    $result = sr_identity_verification_session_result($pdo, $purpose, $accountId, $maxAgeSeconds);
    if ($purpose !== '') {
        unset($_SESSION[sr_identity_verification_session_key()][$purpose]);
    }

    return $result;
}

function sr_identity_verification_result_for_return_token(PDO $pdo, array $config, string $stateToken, string $purpose, int $accountId, ?int $maxAgeSeconds = 900): ?array
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($purpose === '') {
        return null;
    }

    $attempt = sr_identity_verification_attempt_by_state($pdo, $config, $stateToken);
    if (!is_array($attempt)
        || (string) ($attempt['status'] ?? '') !== 'verified'
        || (string) ($attempt['purpose'] ?? '') !== $purpose
        || (int) ($attempt['account_id'] ?? 0) !== $accountId
    ) {
        return null;
    }

    $completedAt = sr_identity_verification_utc_timestamp((string) ($attempt['completed_at'] ?? ''));
    if ($maxAgeSeconds !== null && $maxAgeSeconds > 0 && ($completedAt === null || $completedAt < time() - $maxAgeSeconds)) {
        return null;
    }

    $result = sr_identity_verification_result_by_attempt($pdo, (int) ($attempt['id'] ?? 0));
    if (!is_array($result) || (int) ($result['id'] ?? 0) < 1) {
        return null;
    }
    if ((string) ($result['expires_at'] ?? '') !== '') {
        $resultExpiresAt = sr_identity_verification_utc_timestamp((string) $result['expires_at']);
        if ($resultExpiresAt !== null && $resultExpiresAt < time()) {
            return null;
        }
    }

    $result['purpose'] = $purpose;
    $result['attempt_status'] = 'verified';

    return $result;
}

function sr_identity_verification_claim_return_token(PDO $pdo, array $config, string $stateToken, string $purpose, int $accountId, ?int $maxAgeSeconds = 900): ?array
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($purpose === '') {
        return null;
    }

    $attempt = sr_identity_verification_attempt_by_state($pdo, $config, $stateToken);
    $result = sr_identity_verification_result_for_return_token($pdo, $config, $stateToken, $purpose, $accountId, $maxAgeSeconds);
    if (!is_array($attempt) || !is_array($result)) {
        return null;
    }

    sr_identity_verification_remember_session_result($attempt, (int) $result['id'], [
        'birth_date' => (string) ($result['birth_date'] ?? ''),
        'age_over_14' => array_key_exists('age_over_14', $result) ? (string) $result['age_over_14'] : '',
        'age_over_19' => array_key_exists('age_over_19', $result) ? (string) $result['age_over_19'] : '',
    ]);

    return $result;
}

function sr_identity_verification_link_result_to_account(PDO $pdo, int $resultId, int $accountId, string $purpose): bool
{
    $purpose = sr_identity_verification_purpose($purpose);
    if ($resultId < 1 || $accountId < 1 || $purpose === '') {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT r.id, r.attempt_id, r.account_id, a.purpose, a.status
         FROM sr_identity_verification_results r
         INNER JOIN sr_identity_verification_attempts a ON a.id = r.attempt_id
         WHERE r.id = :id
           AND a.purpose = :purpose
           AND a.status = :status
           AND (r.account_id IS NULL OR r.account_id = :account_id)
           AND (r.expires_at IS NULL OR r.expires_at > :now)
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $resultId,
        'account_id' => $accountId,
        'purpose' => $purpose,
        'status' => 'verified',
        'now' => sr_now(),
    ]);
    if (!is_array($stmt->fetch())) {
        return false;
    }

    $now = sr_now();
    $startedTransaction = !$pdo->inTransaction();
    if ($startedTransaction) {
        $pdo->beginTransaction();
    }
    try {
        sr_identity_verification_claim_identity_lock($pdo, $resultId, $accountId);
        $updateResult = $pdo->prepare('UPDATE sr_identity_verification_results SET account_id = :account_id WHERE id = :id AND account_id IS NULL');
        $updateResult->execute([
            'account_id' => $accountId,
            'id' => $resultId,
        ]);
        $updateAttempt = $pdo->prepare(
            'UPDATE sr_identity_verification_attempts
             SET account_id = :account_id, updated_at = :updated_at
             WHERE id = (SELECT attempt_id FROM sr_identity_verification_results WHERE id = :id)
               AND account_id IS NULL'
        );
        $updateAttempt->execute([
            'account_id' => $accountId,
            'updated_at' => $now,
            'id' => $resultId,
        ]);
        $link = $pdo->prepare(
            'INSERT INTO sr_identity_verification_links
                (account_id, result_id, purpose, linked_at, created_at)
             VALUES
                (:account_id, :result_id, :purpose, :linked_at, :created_at)
             ON DUPLICATE KEY UPDATE
                revoked_at = NULL,
                linked_at = VALUES(linked_at)'
        );
        $link->execute([
            'account_id' => $accountId,
            'result_id' => $resultId,
            'purpose' => $purpose,
            'linked_at' => $now,
            'created_at' => $now,
        ]);
        if ($startedTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($startedTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return true;
}

function sr_identity_verification_claim_identity_lock(PDO $pdo, int $resultId, int $accountId): void
{
    if ($resultId < 1 || $accountId < 1) {
        return;
    }

    $identity = sr_identity_verification_result_identity_hashes($pdo, $resultId);
    $ciHash = is_array($identity) ? trim((string) ($identity['ci_hash'] ?? '')) : '';
    if ($ciHash === '') {
        return;
    }

    $duplicate = sr_identity_verification_duplicate_account_by_ci_hash($pdo, $ciHash, $accountId);
    if ($duplicate !== null) {
        throw new SrIdentityVerificationDuplicateException(sr_identity_verification_duplicate_identity_message());
    }

    $now = sr_now();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_identity_verification_identity_locks
                (ci_hash, account_id, result_id, first_linked_at, updated_at)
             VALUES
                (:ci_hash, :account_id, :result_id, :first_linked_at, :updated_at)'
        );
        $stmt->execute([
            'ci_hash' => $ciHash,
            'account_id' => $accountId,
            'result_id' => $resultId,
            'first_linked_at' => $now,
            'updated_at' => $now,
        ]);
        return;
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() !== '23000') {
            throw $exception;
        }
    }

    $stmt = $pdo->prepare('SELECT account_id FROM sr_identity_verification_identity_locks WHERE ci_hash = :ci_hash LIMIT 1');
    $stmt->execute(['ci_hash' => $ciHash]);
    $lock = $stmt->fetch();
    if (!is_array($lock) || (int) ($lock['account_id'] ?? 0) !== $accountId) {
        throw new SrIdentityVerificationDuplicateException(sr_identity_verification_duplicate_identity_message());
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_identity_verification_identity_locks
         SET result_id = :result_id,
             updated_at = :updated_at
         WHERE ci_hash = :ci_hash
           AND account_id = :account_id'
    );
    $stmt->execute([
        'result_id' => $resultId,
        'updated_at' => $now,
        'ci_hash' => $ciHash,
        'account_id' => $accountId,
    ]);
}

function sr_identity_verification_safe_return_url(string $returnUrl): string
{
    $returnUrl = trim($returnUrl);
    if ($returnUrl !== '' && sr_is_safe_relative_url($returnUrl)) {
        return $returnUrl;
    }

    return '/';
}

function sr_identity_verification_providers(PDO $pdo): array
{
    $settings = sr_identity_verification_settings($pdo);
    $providers = [];
    foreach (sr_enabled_module_contract_files($pdo, 'identity-provider.php', ['identity_verification']) as $moduleKey => $contractFile) {
        $contract = sr_load_module_contract_file((string) $moduleKey, (string) $contractFile);
        if (!is_array($contract)) {
            continue;
        }

        $items = isset($contract['provider_key']) ? [$contract] : $contract;
        foreach ($items as $key => $provider) {
            if (!is_array($provider)) {
                continue;
            }
            $providerKey = sr_identity_verification_provider_key((string) ($provider['provider_key'] ?? (is_string($key) ? $key : '')));
            if ($providerKey === '') {
                continue;
            }
            $provider['provider_key'] = $providerKey;
            $provider['provider_module_key'] = (string) $moduleKey;
            $providers[$providerKey] = sr_identity_verification_apply_provider_settings($provider, $settings);
        }
    }
    ksort($providers);

    return $providers;
}

function sr_identity_verification_apply_provider_settings(array $provider, array $settings): array
{
    $providerKey = sr_identity_verification_provider_key((string) ($provider['provider_key'] ?? ''));
    if ($providerKey === '') {
        return $provider;
    }

    $enabledKey = sr_identity_verification_setting_key($providerKey, 'enabled');
    $environmentKey = sr_identity_verification_setting_key($providerKey, 'environment');
    if ($enabledKey !== '' && array_key_exists($enabledKey, $settings)) {
        $provider['enabled'] = sr_truthy($settings[$enabledKey]);
    }
    if ($environmentKey !== '' && array_key_exists($environmentKey, $settings)) {
        $environment = (string) $settings[$environmentKey];
        $provider['environment'] = in_array($environment, ['test', 'production'], true) ? $environment : 'test';
    }

    foreach ((array) ($provider['settings_schema'] ?? []) as $settingKey => $definition) {
        if (!is_string($settingKey) || !is_array($definition)) {
            continue;
        }
        $storedKey = sr_identity_verification_setting_key($providerKey, $settingKey);
        if ($storedKey !== '' && array_key_exists($storedKey, $settings)) {
            $provider['settings'][$settingKey] = (string) $settings[$storedKey];
        }
    }

    return $provider;
}

function sr_identity_verification_provider_setting(array $provider, string $settingKey): string
{
    $settings = isset($provider['settings']) && is_array($provider['settings']) ? $provider['settings'] : [];
    return trim((string) ($settings[$settingKey] ?? ''));
}

function sr_identity_verification_provider_setting_required(array $definition, string $environment): bool
{
    if (empty($definition['required'])) {
        return false;
    }

    $environments = [];
    foreach ((array) ($definition['required_environments'] ?? []) as $requiredEnvironment) {
        $requiredEnvironment = is_scalar($requiredEnvironment) ? strtolower(trim((string) $requiredEnvironment)) : '';
        if (in_array($requiredEnvironment, ['test', 'production'], true)) {
            $environments[$requiredEnvironment] = true;
        }
    }
    if ($environments === []) {
        return true;
    }

    $environment = in_array($environment, ['test', 'production'], true) ? $environment : 'test';
    return isset($environments[$environment]);
}

function sr_identity_verification_public_providers(PDO $pdo): array
{
    $providers = array_values(array_filter(sr_identity_verification_providers($pdo), static function (array $provider): bool {
        return !empty($provider['enabled']);
    }));
    usort($providers, static function (array $left, array $right): int {
        $leftOrder = (int) ($left['sort_order'] ?? 0);
        $rightOrder = (int) ($right['sort_order'] ?? 0);
        if ($leftOrder !== $rightOrder) {
            return $leftOrder <=> $rightOrder;
        }

        return strcmp((string) ($left['display_name'] ?? $left['provider_key'] ?? ''), (string) ($right['display_name'] ?? $right['provider_key'] ?? ''));
    });

    return $providers;
}

function sr_identity_verification_select_provider(PDO $pdo, string $providerKey = '', string $purpose = ''): ?array
{
    $providers = sr_identity_verification_providers($pdo);
    $settings = sr_identity_verification_settings($pdo);
    $providerKey = sr_identity_verification_provider_key($providerKey);
    if ($providerKey !== ''
        && isset($providers[$providerKey])
        && !empty($providers[$providerKey]['enabled'])
        && sr_identity_verification_provider_supports_purpose($providers[$providerKey], $purpose)
    ) {
        return $providers[$providerKey];
    }

    if ($providerKey === '') {
        foreach (sr_identity_verification_preferred_provider_keys_for_purpose($purpose) as $preferredProviderKey) {
            if (isset($providers[$preferredProviderKey])
                && !empty($providers[$preferredProviderKey]['enabled'])
                && sr_identity_verification_provider_supports_purpose($providers[$preferredProviderKey], $purpose)
            ) {
                return $providers[$preferredProviderKey];
            }
        }
    }

    $defaultProviderKey = (string) ($settings['default_provider_key'] ?? '');
    if ($defaultProviderKey !== ''
        && isset($providers[$defaultProviderKey])
        && !empty($providers[$defaultProviderKey]['enabled'])
        && sr_identity_verification_provider_supports_purpose($providers[$defaultProviderKey], $purpose)
    ) {
        return $providers[$defaultProviderKey];
    }

    foreach ($providers as $provider) {
        if (!empty($provider['enabled']) && sr_identity_verification_provider_supports_purpose($provider, $purpose)) {
            return $provider;
        }
    }

    return null;
}

function sr_identity_verification_create_attempt(PDO $pdo, array $config, array $provider, int $accountId, string $purpose, string $returnUrl, array $options = []): array
{
    $settings = sr_identity_verification_settings($pdo);
    $now = sr_now();
    $ttl = (int) ($settings['attempt_ttl_seconds'] ?? 600);
    $verificationKey = 'iv_' . bin2hex(random_bytes(24));
    $stateToken = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(24));
    $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttl);

    $stmt = $pdo->prepare(
        'INSERT INTO sr_identity_verification_attempts
            (verification_key, provider_key, method, account_id, purpose, subject_module, subject_type, subject_id,
             status, state_token_hash, nonce_hash, return_url, confirm_path, requested_at, expires_at, created_at, updated_at)
         VALUES
            (:verification_key, :provider_key, :method, :account_id, :purpose, :subject_module, :subject_type, :subject_id,
             :status, :state_token_hash, :nonce_hash, :return_url, :confirm_path, :requested_at, :expires_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        'verification_key' => $verificationKey,
        'provider_key' => (string) $provider['provider_key'],
        'method' => (string) (($provider['default_method'] ?? '') ?: ((array) ($provider['supported_methods'] ?? ['identity']))[0]),
        'account_id' => $accountId > 0 ? $accountId : null,
        'purpose' => $purpose,
        'subject_module' => (string) ($options['subject_module'] ?? ''),
        'subject_type' => (string) ($options['subject_type'] ?? ''),
        'subject_id' => (string) ($options['subject_id'] ?? ''),
        'status' => 'ready',
        'state_token_hash' => sr_identity_verification_hash_token($stateToken, $config),
        'nonce_hash' => sr_identity_verification_hash_token($nonce, $config),
        'return_url' => $returnUrl,
        'confirm_path' => (string) ($options['confirm_path'] ?? ''),
        'requested_at' => $now,
        'expires_at' => $expiresAt,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $attempt = sr_identity_verification_attempt_by_key($pdo, $verificationKey);
    if ($attempt === null) {
        throw new RuntimeException('Identity verification attempt was not created.');
    }
    $attempt['state_token'] = $stateToken;
    $attempt['nonce'] = $nonce;

    return $attempt;
}

function sr_identity_verification_attempt_by_key(PDO $pdo, string $verificationKey): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_attempts WHERE verification_key = :verification_key LIMIT 1');
    $stmt->execute(['verification_key' => $verificationKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_attempt_by_state(PDO $pdo, array $config, string $stateToken): ?array
{
    $stateToken = trim($stateToken);
    if ($stateToken === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_attempts WHERE state_token_hash = :state_hash LIMIT 1');
    $stmt->execute(['state_hash' => sr_identity_verification_hash_token($stateToken, $config)]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_mark_attempt(PDO $pdo, int $attemptId, string $status, array $fields = []): void
{
    $allowed = ['ready', 'pending', 'verified', 'failed', 'expired', 'canceled'];
    if (!in_array($status, $allowed, true)) {
        throw new InvalidArgumentException('Invalid identity verification status.');
    }

    $sets = ['status = :status', 'updated_at = :updated_at'];
    $params = [
        'id' => $attemptId,
        'status' => $status,
        'updated_at' => sr_now(),
    ];
    foreach (['provider_transaction_id', 'provider_reference', 'failure_code', 'failure_message'] as $field) {
        if (array_key_exists($field, $fields)) {
            $sets[] = $field . ' = :' . $field;
            $params[$field] = (string) $fields[$field];
        }
    }
    if ($status === 'verified') {
        $sets[] = 'completed_at = COALESCE(completed_at, :completed_at)';
        $params['completed_at'] = sr_now();
    } elseif (in_array($status, ['failed', 'expired', 'canceled'], true)) {
        $sets[] = 'failed_at = COALESCE(failed_at, :failed_at)';
        $params['failed_at'] = sr_now();
    }

    $stmt = $pdo->prepare('UPDATE sr_identity_verification_attempts SET ' . implode(', ', $sets) . ' WHERE id = :id');
    $stmt->execute($params);
}

function sr_identity_verification_complete(PDO $pdo, array $config, array $attempt, array $verification): int
{
    if ((string) ($attempt['status'] ?? '') === 'verified') {
        $existing = sr_identity_verification_result_by_attempt($pdo, (int) $attempt['id']);
        return $existing !== null ? (int) $existing['id'] : 0;
    }
    if (in_array((string) ($attempt['status'] ?? ''), ['failed', 'expired', 'canceled'], true)) {
        throw new RuntimeException('Identity verification attempt is already closed.');
    }

    $settings = sr_identity_verification_settings($pdo);
    $identity = isset($verification['identity']) && is_array($verification['identity']) ? $verification['identity'] : [];
    $ciHash = sr_identity_verification_hmac_field($config, 'ci', (string) ($identity['ci'] ?? ''));
    if ($ciHash !== '' && sr_identity_verification_duplicate_account_by_ci_hash($pdo, $ciHash, (int) ($attempt['account_id'] ?? 0)) !== null) {
        throw new SrIdentityVerificationDuplicateException(sr_identity_verification_duplicate_identity_message());
    }
    $now = sr_now();
    $expiresAt = null;
    $validDays = (int) ($settings['result_valid_days'] ?? 0);
    if ($validDays > 0) {
        $expiresAt = gmdate('Y-m-d H:i:s', time() + ($validDays * 86400));
    }
    $summary = isset($verification['summary']) && is_array($verification['summary']) ? $verification['summary'] : [];
    $summaryJson = json_encode(sr_identity_verification_public_summary($summary), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_identity_verification_results
                (attempt_id, account_id, provider_key, provider_transaction_id, ci_hash, di_hash, name_hash,
                 phone_hash, birth_date, gender, nationality, age_over_14, age_over_19, result_summary_json,
                 verified_at, expires_at, created_at)
             VALUES
                (:attempt_id, :account_id, :provider_key, :provider_transaction_id, :ci_hash, :di_hash, :name_hash,
                 :phone_hash, :birth_date, :gender, :nationality, :age_over_14, :age_over_19, :result_summary_json,
                 :verified_at, :expires_at, :created_at)'
        );
        $stmt->execute([
            'attempt_id' => (int) $attempt['id'],
            'account_id' => $attempt['account_id'] !== null ? (int) $attempt['account_id'] : null,
            'provider_key' => (string) $attempt['provider_key'],
            'provider_transaction_id' => (string) ($verification['provider_transaction_id'] ?? $attempt['provider_transaction_id'] ?? ''),
            'ci_hash' => $ciHash,
            'di_hash' => sr_identity_verification_hmac_field($config, 'di', (string) ($identity['di'] ?? '')),
            'name_hash' => sr_identity_verification_hmac_field($config, 'name', (string) ($identity['name'] ?? '')),
            'phone_hash' => sr_identity_verification_hmac_field($config, 'phone', sr_identity_verification_digits((string) ($identity['phone'] ?? ''))),
            'birth_date' => sr_identity_verification_birth_date((string) ($identity['birth_date'] ?? '')),
            'gender' => substr((string) ($identity['gender'] ?? ''), 0, 20),
            'nationality' => substr((string) ($identity['nationality'] ?? ''), 0, 20),
            'age_over_14' => array_key_exists('age_over_14', $identity) ? (sr_truthy($identity['age_over_14']) ? 1 : 0) : null,
            'age_over_19' => array_key_exists('age_over_19', $identity) ? (sr_truthy($identity['age_over_19']) ? 1 : 0) : null,
            'result_summary_json' => is_string($summaryJson) ? $summaryJson : '{}',
            'verified_at' => $now,
            'expires_at' => $expiresAt,
            'created_at' => $now,
        ]);
        $resultId = (int) $pdo->lastInsertId();
        sr_identity_verification_mark_attempt($pdo, (int) $attempt['id'], 'verified', [
            'provider_transaction_id' => (string) ($verification['provider_transaction_id'] ?? ''),
        ]);

        if ((int) ($attempt['account_id'] ?? 0) > 0) {
            sr_identity_verification_claim_identity_lock($pdo, $resultId, (int) $attempt['account_id']);
            $link = $pdo->prepare(
                'INSERT INTO sr_identity_verification_links
                    (account_id, result_id, purpose, linked_at, created_at)
                 VALUES
                    (:account_id, :result_id, :purpose, :linked_at, :created_at)
                 ON DUPLICATE KEY UPDATE
                    revoked_at = NULL,
                    linked_at = VALUES(linked_at)'
            );
            $link->execute([
                'account_id' => (int) $attempt['account_id'],
                'result_id' => $resultId,
                'purpose' => (string) $attempt['purpose'],
                'linked_at' => $now,
                'created_at' => $now,
            ]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return $resultId;
}

function sr_identity_verification_result_by_attempt(PDO $pdo, int $attemptId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM sr_identity_verification_results WHERE attempt_id = :attempt_id LIMIT 1');
    $stmt->execute(['attempt_id' => $attemptId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_identity_verification_admin_attempt_sort_options(): array
{
    return [
        'requested_at' => ['columns' => ['a.requested_at', 'a.id']],
        'provider_key' => ['columns' => ['a.provider_key', 'a.id']],
        'purpose' => ['columns' => ['a.purpose', 'a.id']],
        'account_id' => ['columns' => ['a.account_id', 'a.id']],
        'status' => ['columns' => ['a.status', 'a.id']],
        'verified_at' => ['columns' => ['r.verified_at', 'a.id']],
    ];
}

function sr_identity_verification_admin_attempt_default_sort(): array
{
    return sr_admin_sort_default('requested_at', 'desc');
}

function sr_identity_verification_admin_attempt_filters_from_request(PDO $pdo): array
{
    $status = sr_get_string('status', 30);
    if (!isset(sr_identity_verification_attempt_status_labels()[$status])) {
        $status = '';
    }

    $dateFrom = sr_get_string('date_from', 10);
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateFrom) !== 1) {
        $dateFrom = '';
    }

    $dateTo = sr_get_string('date_to', 10);
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $dateTo) !== 1) {
        $dateTo = '';
    }

    return [
        'status' => $status,
        'provider_key' => sr_identity_verification_provider_key(sr_get_string('provider_key', 60)),
        'purpose' => sr_identity_verification_admin_purpose_filter_value(sr_get_string('purpose', 80)),
        'account_id' => sr_admin_member_account_id_from_identifier($pdo, sr_runtime_config(), sr_get_string('account_id', 80)),
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'q' => sr_get_string('q', 120),
    ];
}

function sr_identity_verification_admin_attempt_query_parts(array $filters): array
{
    $where = [];
    $params = [];

    $id = (int) ($filters['id'] ?? 0);
    if ($id > 0) {
        $where[] = 'a.id = :id';
        $params['id'] = $id;
    }

    $status = (string) ($filters['status'] ?? '');
    if ($status !== '' && isset(sr_identity_verification_attempt_status_labels()[$status])) {
        $where[] = 'a.status = :status';
        $params['status'] = $status;
    }

    $providerKey = sr_identity_verification_provider_key((string) ($filters['provider_key'] ?? ''));
    if ($providerKey !== '') {
        $where[] = 'a.provider_key = :provider_key';
        $params['provider_key'] = $providerKey;
    }

    $purpose = sr_identity_verification_purpose((string) ($filters['purpose'] ?? ''));
    if ($purpose !== '') {
        $where[] = 'a.purpose = :purpose';
        $params['purpose'] = $purpose;
    }

    $accountId = (int) ($filters['account_id'] ?? 0);
    if ($accountId > 0) {
        $where[] = 'a.account_id = :account_id';
        $params['account_id'] = $accountId;
    }

    $dateFrom = (string) ($filters['date_from'] ?? '');
    if ($dateFrom !== '') {
        $where[] = 'a.requested_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    $dateTo = (string) ($filters['date_to'] ?? '');
    if ($dateTo !== '') {
        $where[] = 'a.requested_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $qWhere = [
            "a.verification_key LIKE :q ESCAPE '\\\\'",
            "a.provider_transaction_id LIKE :q ESCAPE '\\\\'",
            "a.provider_reference LIKE :q ESCAPE '\\\\'",
            "a.purpose LIKE :q ESCAPE '\\\\'",
            "a.failure_code LIKE :q ESCAPE '\\\\'",
        ];
        if (preg_match('/\A[1-9][0-9]*\z/', $q) === 1) {
            $qWhere[] = 'a.id = :q_id';
            $params['q_id'] = (int) $q;
        }
        $where[] = '(' . implode(' OR ', $qWhere) . ')';
        $params['q'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    }

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function sr_identity_verification_admin_attempt_count(PDO $pdo, array $filters): int
{
    $queryParts = sr_identity_verification_admin_attempt_query_parts($filters);
    $sql = 'SELECT COUNT(*) FROM sr_identity_verification_attempts a';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParts['params']);

    return (int) $stmt->fetchColumn();
}

function sr_identity_verification_admin_attempts(PDO $pdo, array $filters, int $limit, int $offset, array $sort = []): array
{
    $queryParts = sr_identity_verification_admin_attempt_query_parts($filters);
    $limit = max(1, min(500, $limit));
    $offset = max(0, $offset);

    $sql = 'SELECT a.*, r.id AS result_id, r.verified_at, r.expires_at AS result_expires_at
            FROM sr_identity_verification_attempts a
            LEFT JOIN sr_identity_verification_results r ON r.attempt_id = a.id';
    if ($queryParts['where'] !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $queryParts['where']);
    }
    $sql .= sr_admin_sort_order_sql(
        sr_identity_verification_admin_attempt_sort_options(),
        $sort,
        sr_identity_verification_admin_attempt_default_sort()
    );
    $sql .= ' LIMIT :limit_value OFFSET :offset_value';

    $stmt = $pdo->prepare($sql);
    foreach ($queryParts['params'] as $key => $value) {
        if (is_int($value)) {
            $stmt->bindValue(':' . $key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':' . $key, $value);
        }
    }
    $stmt->bindValue(':limit_value', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset_value', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function sr_identity_verification_call_provider(array $provider, string $handlerKey, array $args): mixed
{
    $handlers = isset($provider['handlers']) && is_array($provider['handlers']) ? $provider['handlers'] : [];
    $handler = (string) ($handlers[$handlerKey] ?? '');
    if ($handler === '' || !str_contains($handler, ':')) {
        throw new RuntimeException('Identity provider handler is not configured.');
    }

    [$relativePath, $functionName] = explode(':', $handler, 2);
    $relativePath = trim($relativePath);
    $functionName = trim($functionName);
    $moduleKey = (string) ($provider['provider_module_key'] ?? '');
    if (!sr_is_safe_module_key($moduleKey) || $relativePath === '' || str_contains($relativePath, '..')) {
        throw new RuntimeException('Identity provider handler path is invalid.');
    }

    $path = SR_ROOT . '/modules/' . $moduleKey . '/' . ltrim($relativePath, '/');
    if (!is_file($path)) {
        throw new RuntimeException('Identity provider handler file is missing.');
    }
    require_once $path;
    if (!function_exists($functionName)) {
        throw new RuntimeException('Identity provider handler function is missing.');
    }

    return $functionName(...$args);
}

function sr_identity_verification_hash_token(string $token, array $config): string
{
    return sr_hmac_hash('identity.token|' . $token, $config);
}

function sr_identity_verification_hmac_field(array $config, string $field, string $value): string
{
    $value = trim($value);
    return $value === '' ? '' : sr_hmac_hash('identity.' . $field . '|' . $value, $config);
}

function sr_identity_verification_digits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function sr_identity_verification_birth_date(string $value): ?string
{
    $value = trim($value);
    if (preg_match('/\A(\d{4})-?(\d{2})-?(\d{2})\z/', $value, $matches) !== 1) {
        return null;
    }
    $date = $matches[1] . '-' . $matches[2] . '-' . $matches[3];

    return checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1]) ? $date : null;
}

function sr_identity_verification_public_summary(array $summary): array
{
    $allowed = [];
    foreach (['provider_result_code', 'provider_result_message', 'method', 'age_over_14', 'age_over_19'] as $key) {
        if (array_key_exists($key, $summary) && is_scalar($summary[$key])) {
            $allowed[$key] = (string) $summary[$key];
        }
    }

    return $allowed;
}

function sr_identity_verification_request_data(): array
{
    return sr_request_method() === 'POST' ? $_POST : $_GET;
}

function sr_identity_verification_extract_state(array $request): string
{
    foreach (['state', 'param_opt_1', 'MSTR', 'mstr'] as $key) {
        $value = $request[$key] ?? '';
        if (is_scalar($value)) {
            $value = trim((string) $value);
            if ($value !== '') {
                if (str_contains($value, 'state=')) {
                    parse_str(str_replace('|', '&', $value), $parsed);
                    if (isset($parsed['state']) && is_scalar($parsed['state'])) {
                        return trim((string) $parsed['state']);
                    }
                }
                return $value;
            }
        }
    }

    return '';
}

function sr_identity_verification_http_json(string $url, array $headers, string $body, int $timeoutSeconds = 10): array
{
    if (!sr_is_public_http_url($url)) {
        throw new RuntimeException('Identity provider endpoint is invalid.');
    }

    $headerLines = [];
    foreach ($headers as $name => $value) {
        if (is_string($name) && is_scalar($value)) {
            $headerLines[] = $name . ': ' . (string) $value;
        }
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $headerLines),
            'content' => $body,
            'timeout' => $timeoutSeconds,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if (!is_string($response)) {
        throw new RuntimeException('Identity provider request failed.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Identity provider response is invalid.');
    }

    return $decoded;
}

function sr_identity_verification_render_provider_form(array $prepared): void
{
    $action = (string) ($prepared['action'] ?? '');
    $method = strtoupper((string) ($prepared['method'] ?? 'POST'));
    $fields = isset($prepared['fields']) && is_array($prepared['fields']) ? $prepared['fields'] : [];
    if (!sr_is_public_http_url($action)) {
        sr_render_error(500, '본인확인 제공자 호출 주소가 올바르지 않습니다.');
    }
    if (!in_array($method, ['GET', 'POST'], true)) {
        $method = 'POST';
    }
    include SR_ROOT . '/modules/identity_verification/views/provider-form.php';
    exit;
}
