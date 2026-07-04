<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/identity_verification/helpers.php';

function sr_identity_inicis_prepare(PDO $pdo, array $config, array $site, array $provider, array $attempt): array
{
    $mid = sr_identity_verification_provider_setting($provider, 'mid');
    $apiKey = sr_identity_verification_provider_setting($provider, 'api_key');
    if ($mid === '' || $apiKey === '') {
        throw new RuntimeException('KG Inicis MID and apikey are required.');
    }

    $reqSvcCd = sr_identity_inicis_req_svc_cd($provider);
    $mTxId = sr_identity_inicis_mtx_id((string) $attempt['verification_key']);
    $returnPath = '/identity/verify/return?state=' . rawurlencode((string) $attempt['state_token']);
    $returnUrl = sr_absolute_url($site, $returnPath);
    $fields = [
        'mid' => $mid,
        'reqSvcCd' => $reqSvcCd,
        'mTxId' => $mTxId,
        'successUrl' => $returnUrl,
        'failUrl' => $returnUrl,
        'authHash' => strtolower(hash('sha256', $mid . $mTxId . $apiKey)),
        'flgFixedUser' => 'N',
        'reservedMsg' => 'isUseToken=Y',
    ];
    foreach (['di_code' => 'DI_CODE', 'direct_agency' => 'directAgency', 'logo_url' => 'logoUrl'] as $settingKey => $fieldKey) {
        $value = sr_identity_verification_provider_setting($provider, $settingKey);
        if ($value !== '') {
            $fields[$fieldKey] = $value;
        }
    }

    return [
        'action' => 'https://sa.inicis.com/id/auth',
        'method' => 'POST',
        'fields' => $fields,
        'provider_reference' => $mTxId,
        'provider_transaction_id' => $mTxId,
    ];
}

function sr_identity_inicis_verify_return(PDO $pdo, array $config, array $site, array $provider, array $attempt, array $request): array
{
    $resultCode = trim((string) ($request['resultCode'] ?? ''));
    if ($resultCode !== '0000') {
        return [
            'status' => 'failed',
            'failure_code' => $resultCode !== '' ? $resultCode : 'inicis_result_failed',
            'failure_message' => rawurldecode((string) ($request['resultMsg'] ?? 'KG Inicis identity verification failed.')),
        ];
    }

    $mTxId = trim((string) ($request['mTxId'] ?? ''));
    if ($mTxId === '' || $mTxId !== (string) ($attempt['provider_reference'] ?? '')) {
        return [
            'status' => 'failed',
            'failure_code' => 'inicis_mtx_id_mismatch',
            'failure_message' => 'KG Inicis mTxId did not match the stored attempt.',
        ];
    }

    $authRequestUrl = trim((string) ($request['authRequestUrl'] ?? ''));
    $txId = trim((string) ($request['txId'] ?? ''));
    if (!sr_identity_inicis_auth_request_url_is_allowed($authRequestUrl) || $txId === '') {
        return [
            'status' => 'failed',
            'failure_code' => 'inicis_result_query_invalid',
            'failure_message' => 'KG Inicis result query URL or txId is invalid.',
        ];
    }

    $mid = sr_identity_verification_provider_setting($provider, 'mid');
    $queryBody = json_encode(['mid' => $mid, 'txId' => $txId], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
    $queryResponse = sr_identity_verification_http_json($authRequestUrl, [
        'Content-Type' => 'application/json;charset=utf-8',
    ], $queryBody, 5);

    if ((string) ($queryResponse['resultCode'] ?? '') !== '0000') {
        return [
            'status' => 'failed',
            'failure_code' => (string) ($queryResponse['resultCode'] ?? 'inicis_query_failed'),
            'failure_message' => rawurldecode((string) ($queryResponse['resultMsg'] ?? 'KG Inicis result query failed.')),
        ];
    }

    $decoded = sr_identity_inicis_decrypt_response_fields($provider, $queryResponse);
    $method = (string) ($provider['default_method'] ?? 'integrated_identity');

    return [
        'status' => 'verified',
        'provider_transaction_id' => $txId,
        'identity' => [
            'ci' => (string) ($decoded['userCi2'] ?? $decoded['userCi'] ?? ''),
            'di' => (string) ($decoded['userDi'] ?? ''),
            'name' => (string) ($decoded['userName'] ?? ''),
            'phone' => (string) ($decoded['userPhone'] ?? ''),
            'birth_date' => (string) ($decoded['userBirthday'] ?? ''),
            'gender' => (string) ($decoded['userGender'] ?? ''),
            'nationality' => (string) ($decoded['isForeign'] ?? ''),
            'age_over_14' => sr_identity_inicis_age_over((string) ($decoded['userBirthday'] ?? ''), 14),
            'age_over_19' => sr_identity_inicis_age_over((string) ($decoded['userBirthday'] ?? ''), 19),
        ],
        'summary' => [
            'provider_result_code' => (string) ($queryResponse['resultCode'] ?? '0000'),
            'provider_result_message' => rawurldecode((string) ($queryResponse['resultMsg'] ?? '성공')),
            'method' => $method,
            'age_over_14' => sr_identity_inicis_age_over((string) ($decoded['userBirthday'] ?? ''), 14) ? '1' : '0',
            'age_over_19' => sr_identity_inicis_age_over((string) ($decoded['userBirthday'] ?? ''), 19) ? '1' : '0',
        ],
    ];
}

function sr_identity_inicis_req_svc_cd(array $provider): string
{
    $reqSvcCd = (string) ($provider['inicis_req_svc_cd'] ?? '');
    return in_array($reqSvcCd, ['01', '02', '03'], true) ? $reqSvcCd : '03';
}

function sr_identity_inicis_mtx_id(string $verificationKey): string
{
    return 'SR' . substr(hash('sha1', $verificationKey), 0, 18);
}

function sr_identity_inicis_auth_request_url_is_allowed(string $url): bool
{
    if (!sr_is_public_http_url($url) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
        return false;
    }
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    return $host === 'fcsa.inicis.com' || $host === 'kssa.inicis.com' || str_ends_with($host, '.inicis.com');
}

function sr_identity_inicis_load_library(array $provider): void
{
    $path = sr_identity_verification_provider_setting($provider, 'library_path');
    if ($path === '') {
        return;
    }
    if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        throw new RuntimeException('KG Inicis library path is invalid.');
    }
    $fullPath = SR_ROOT . '/' . ltrim($path, '/');
    if (!is_file($fullPath)) {
        throw new RuntimeException('KG Inicis library file is missing.');
    }
    require_once $fullPath;
}

function sr_identity_inicis_decrypt_response_fields(array $provider, array $response): array
{
    $fields = ['userName', 'userPhone', 'userBirthday', 'userCi', 'userCi2', 'userDi', 'userGender', 'isForeign', 'signedData'];
    $hasProtectedField = false;
    foreach ($fields as $field) {
        if (trim((string) ($response[$field] ?? '')) !== '') {
            $hasProtectedField = true;
            break;
        }
    }
    if (!$hasProtectedField) {
        return $response;
    }

    sr_identity_inicis_load_library($provider);
    $function = sr_identity_verification_provider_setting($provider, 'decrypt_function');
    if ($function === '') {
        if ((string) ($response['token'] ?? '') !== '') {
            throw new RuntimeException('KG Inicis decrypt function is required for token protected identity fields.');
        }

        return $response;
    }
    if (!is_callable($function)) {
        throw new RuntimeException('KG Inicis decrypt function is not available.');
    }

    $token = (string) ($response['token'] ?? '');
    $decoded = $response;
    foreach ($fields as $field) {
        $value = (string) ($response[$field] ?? '');
        if ($value === '') {
            continue;
        }
        $decrypted = $token !== '' ? $function($value, $token) : $function($value);
        if (is_scalar($decrypted)) {
            $decoded[$field] = (string) $decrypted;
        }
    }

    return $decoded;
}

function sr_identity_inicis_age_over(string $birthDate, int $age): bool
{
    $birthDate = sr_identity_verification_birth_date($birthDate);
    if ($birthDate === null) {
        return false;
    }
    $birth = strtotime($birthDate . ' 00:00:00 UTC');
    if ($birth === false) {
        return false;
    }

    return $birth <= strtotime('-' . $age . ' years');
}
