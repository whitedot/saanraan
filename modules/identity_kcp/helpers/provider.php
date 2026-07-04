<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/identity_verification/helpers.php';

function sr_identity_kcp_prepare(PDO $pdo, array $config, array $site, array $provider, array $attempt): array
{
    $siteCd = sr_identity_verification_provider_setting($provider, 'site_cd');
    $encKey = sr_identity_verification_provider_setting($provider, 'enc_key');
    if ($siteCd === '' || $encKey === '') {
        throw new RuntimeException('KCP site_cd and ENC_KEY are required.');
    }
    sr_identity_kcp_load_library($provider);

    $returnUrl = sr_absolute_url($site, '/identity/verify/return?state=' . rawurlencode((string) $attempt['state_token']));
    $payload = [
        'site_cd' => $siteCd,
        'ordr_idxx' => (string) $attempt['verification_key'],
        'Ret_URL' => $returnUrl,
        'param_opt_1' => (string) $attempt['state_token'],
        'param_opt_2' => (string) $attempt['purpose'],
    ];
    $webSiteId = sr_identity_verification_provider_setting($provider, 'web_siteid');
    if ($webSiteId !== '') {
        $payload['web_siteid'] = $webSiteId;
    }

    $encrypted = sr_identity_kcp_encrypt_json($provider, $payload, $encKey, $siteCd);
    $endpoint = sr_identity_kcp_register_endpoint($provider);
    $response = sr_identity_verification_http_json($endpoint, [
        'Content-Type' => 'application/json; charset=UTF-8',
        'site_cd' => $siteCd,
        'rv' => (string) $encrypted['rv'],
    ], (string) $encrypted['enc_data']);

    if ((string) ($response['res_cd'] ?? '') !== '0000') {
        throw new RuntimeException('KCP identity registration failed.');
    }
    $callUrl = trim((string) ($response['call_url'] ?? ''));
    $regCertKey = trim((string) ($response['reg_cert_key'] ?? ''));
    if (!sr_is_public_http_url($callUrl) || $regCertKey === '') {
        throw new RuntimeException('KCP identity registration response is invalid.');
    }

    return [
        'action' => $callUrl,
        'method' => 'POST',
        'fields' => [
            'reg_cert_key' => $regCertKey,
            'kcp_page_submit_yn' => 'Y',
        ],
        'provider_reference' => $regCertKey,
        'provider_transaction_id' => $regCertKey,
    ];
}

function sr_identity_kcp_verify_return(PDO $pdo, array $config, array $site, array $provider, array $attempt, array $request): array
{
    $resCd = trim((string) ($request['res_cd'] ?? ''));
    if ($resCd !== '0000') {
        return [
            'status' => $resCd === '3001' ? 'canceled' : 'failed',
            'failure_code' => $resCd !== '' ? $resCd : 'kcp_result_failed',
            'failure_message' => trim((string) ($request['res_msg'] ?? 'KCP identity verification failed.')),
        ];
    }

    $regCertKey = trim((string) ($request['reg_cert_key'] ?? ''));
    if ($regCertKey === '' || (string) ($attempt['provider_reference'] ?? '') !== $regCertKey) {
        return [
            'status' => 'failed',
            'failure_code' => 'kcp_reg_cert_key_mismatch',
            'failure_message' => 'KCP reg_cert_key did not match the stored attempt.',
        ];
    }

    $siteCd = sr_identity_verification_provider_setting($provider, 'site_cd');
    $encKey = sr_identity_verification_provider_setting($provider, 'enc_key');
    if ($siteCd === '' || $encKey === '') {
        throw new RuntimeException('KCP site_cd and ENC_KEY are required.');
    }
    sr_identity_kcp_load_library($provider);

    $queryResponse = sr_identity_verification_http_json(sr_identity_kcp_result_endpoint($provider), [
        'Content-Type' => 'application/json; charset=UTF-8',
        'site_cd' => $siteCd,
    ], json_encode([
        'reg_cert_key' => $regCertKey,
        'ordr_idxx' => (string) $attempt['verification_key'],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}');

    if ((string) ($queryResponse['res_cd'] ?? '') !== '0000') {
        return [
            'status' => 'failed',
            'failure_code' => (string) ($queryResponse['res_cd'] ?? 'kcp_query_failed'),
            'failure_message' => (string) ($queryResponse['res_msg'] ?? 'KCP identity result query failed.'),
        ];
    }

    $decoded = sr_identity_kcp_decrypt_json(
        $provider,
        (string) ($queryResponse['enc_cert_data'] ?? ''),
        (string) ($queryResponse['rv'] ?? ''),
        $encKey,
        $siteCd
    );

    return [
        'status' => 'verified',
        'provider_transaction_id' => $regCertKey,
        'identity' => sr_identity_kcp_identity($decoded),
        'summary' => [
            'provider_result_code' => (string) ($decoded['res_cd'] ?? '0000'),
            'provider_result_message' => (string) ($decoded['res_msg'] ?? '정상처리'),
            'method' => 'mobile_identity',
            'age_over_14' => sr_identity_kcp_age_over($decoded, 14) ? '1' : '0',
            'age_over_19' => sr_identity_kcp_age_over($decoded, 19) ? '1' : '0',
        ],
    ];
}

function sr_identity_kcp_register_endpoint(array $provider): string
{
    return (string) ($provider['environment'] ?? 'test') === 'production'
        ? 'https://cert.kcp.co.kr/api/reg/certDataReg.do'
        : 'https://testcert.kcp.co.kr/api/reg/certDataReg.do';
}

function sr_identity_kcp_result_endpoint(array $provider): string
{
    return (string) ($provider['environment'] ?? 'test') === 'production'
        ? 'https://cert.kcp.co.kr/api/query/getCertData.do'
        : 'https://testcert.kcp.co.kr/api/query/getCertData.do';
}

function sr_identity_kcp_load_library(array $provider): void
{
    $path = sr_identity_verification_provider_setting($provider, 'library_path');
    if ($path === '') {
        return;
    }
    if (str_contains($path, '..') || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
        throw new RuntimeException('KCP library path is invalid.');
    }
    $fullPath = SR_ROOT . '/' . ltrim($path, '/');
    if (!is_file($fullPath)) {
        throw new RuntimeException('KCP library file is missing.');
    }
    require_once $fullPath;
}

function sr_identity_kcp_crypto_function(array $provider, string $settingKey, string $defaultFunction): string
{
    $function = sr_identity_verification_provider_setting($provider, $settingKey);
    return $function !== '' ? $function : $defaultFunction;
}

function sr_identity_kcp_encrypt_json(array $provider, array $payload, string $encKey, string $siteCd): array
{
    $function = sr_identity_kcp_crypto_function($provider, 'encrypt_function', 'encryptJson');
    if (!is_callable($function) && is_callable('encrypJson')) {
        $function = 'encrypJson';
    }
    if (!is_callable($function)) {
        throw new RuntimeException('KCP encryptJson function is not available.');
    }
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        throw new RuntimeException('KCP payload encoding failed.');
    }
    $rv = '';
    $result = $function($json, $encKey, $siteCd, $rv);
    if (is_array($result)) {
        $encData = (string) ($result['enc_data'] ?? $result[0] ?? '');
        $rvValue = (string) ($result['rv'] ?? $result[1] ?? $rv);
    } else {
        $encData = (string) $result;
        $rvValue = $rv;
    }
    if ($encData === '' || $rvValue === '') {
        throw new RuntimeException('KCP encryption result is invalid.');
    }

    return ['enc_data' => $encData, 'rv' => $rvValue];
}

function sr_identity_kcp_decrypt_json(array $provider, string $encCertData, string $rv, string $encKey, string $siteCd): array
{
    $function = sr_identity_kcp_crypto_function($provider, 'decrypt_function', 'decryptJson');
    if (!is_callable($function)) {
        throw new RuntimeException('KCP decryptJson function is not available.');
    }
    if ($encCertData === '' || $rv === '') {
        throw new RuntimeException('KCP encrypted result is missing.');
    }
    $result = $function($encCertData, $rv, $encKey, $siteCd);
    $decoded = is_array($result) ? $result : json_decode((string) $result, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('KCP decrypted result is invalid.');
    }

    return $decoded;
}

function sr_identity_kcp_identity(array $decoded): array
{
    return [
        'ci' => (string) ($decoded['ci'] ?? $decoded['CI'] ?? ''),
        'di' => (string) ($decoded['di'] ?? $decoded['DI'] ?? ''),
        'name' => (string) ($decoded['user_name'] ?? $decoded['name'] ?? ''),
        'phone' => (string) ($decoded['phone_no'] ?? $decoded['phone'] ?? ''),
        'birth_date' => (string) ($decoded['birth_day'] ?? $decoded['birth_date'] ?? ''),
        'gender' => (string) ($decoded['sex_code'] ?? $decoded['gender'] ?? ''),
        'nationality' => (string) ($decoded['local_code'] ?? $decoded['nationality'] ?? ''),
        'age_over_14' => sr_identity_kcp_age_over($decoded, 14),
        'age_over_19' => sr_identity_kcp_age_over($decoded, 19),
    ];
}

function sr_identity_kcp_age_over(array $decoded, int $age): bool
{
    $birthDate = sr_identity_verification_birth_date((string) ($decoded['birth_day'] ?? $decoded['birth_date'] ?? ''));
    if ($birthDate === null) {
        return false;
    }
    $birth = strtotime($birthDate . ' 00:00:00 UTC');
    if ($birth === false) {
        return false;
    }

    return $birth <= strtotime('-' . $age . ' years');
}
