#!/usr/bin/env php
<?php

declare(strict_types=1);

$baseUrl = rtrim((string) ($argv[1] ?? ''), '/');
$identifier = (string) ($argv[2] ?? '');
$password = (string) ($argv[3] ?? '');
$boardKey = (string) ($argv[4] ?? 'free');
$recipientIdentifier = (string) ($argv[5] ?? '');
$postId = (int) ($argv[6] ?? 0);
$reporterIdentifier = (string) ($argv[7] ?? '');
$reporterPassword = (string) ($argv[8] ?? '');

if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl) || $identifier === '' || $password === '') {
    fwrite(STDERR, "Usage: php .tools/bin/smoke-community-auth.php http://127.0.0.1:8080 login@example.com password [board_key] [recipient_identifier] [post_id] [reporter_identifier] [reporter_password]\n");
    exit(2);
}

$cookies = [];
$errors = [];

function toy_auth_smoke_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function toy_auth_smoke_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function toy_auth_smoke_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function toy_auth_smoke_request(string $baseUrl, string $method, string $path, array $postData, array &$cookies): array
{
    $headers = ["User-Agent: Toycore-Community-Auth-Smoke"];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . toy_auth_smoke_cookie_header($cookies);
    }

    $content = '';
    if ($method === 'POST') {
        $content = http_build_query($postData);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($content);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'timeout' => 10,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headers) . "\r\n",
            'content' => $content,
        ],
    ]);

    $body = file_get_contents(toy_auth_smoke_url($baseUrl, $path), false, $context);
    $responseHeaders = $http_response_header ?? [];
    toy_auth_smoke_store_cookies($responseHeaders, $cookies);

    $status = 0;
    $location = '';
    foreach ($responseHeaders as $header) {
        if (preg_match('#\AHTTP/\S+\s+(\d{3})#', (string) $header, $matches) === 1) {
            $status = (int) $matches[1];
        }
        if (preg_match('#\ALocation:\s*(.+)\z#i', (string) $header, $matches) === 1) {
            $location = trim((string) $matches[1]);
        }
    }

    return [
        'status' => $status,
        'body' => is_string($body) ? $body : '',
        'location' => $location,
    ];
}

function toy_auth_smoke_csrf(array $response, string $label): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', (string) $response['body'], $matches) === 1) {
        return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
    }

    throw new RuntimeException($label . ' CSRF token not found.');
}

function toy_auth_smoke_assert_status(array &$errors, string $label, array $response, array $allowedStatuses): void
{
    $status = (int) $response['status'];
    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = $label . ' returned unexpected status ' . (string) $status . '.';
    }
    if (str_contains((string) $response['body'], 'Fatal error') || str_contains((string) $response['body'], 'Stack trace')) {
        $errors[] = $label . ' rendered a PHP failure page.';
    }
}

function toy_auth_smoke_location_path(string $location): string
{
    $path = parse_url($location, PHP_URL_PATH);
    if (!is_string($path) || $path === '') {
        return $location;
    }

    $query = parse_url($location, PHP_URL_QUERY);
    return is_string($query) && $query !== '' ? $path . '?' . $query : $path;
}

function toy_auth_smoke_login(string $baseUrl, string $identifier, string $password, array &$cookies, array &$errors, string $label): void
{
    $loginForm = toy_auth_smoke_request($baseUrl, 'GET', '/login', [], $cookies);
    toy_auth_smoke_assert_status($errors, $label . ' login form', $loginForm, [200]);
    $loginCsrf = toy_auth_smoke_csrf($loginForm, $label . ' login form');
    $loginResponse = toy_auth_smoke_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $loginCsrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => '/community',
    ], $cookies);
    toy_auth_smoke_assert_status($errors, $label . ' login submit', $loginResponse, [302]);
}

try {
    toy_auth_smoke_login($baseUrl, $identifier, $password, $cookies, $errors, 'primary account');

    $messages = toy_auth_smoke_request($baseUrl, 'GET', '/community/messages', [], $cookies);
    toy_auth_smoke_assert_status($errors, 'message box', $messages, [200]);

    $writeForm = toy_auth_smoke_request($baseUrl, 'GET', '/community/write?key=' . rawurlencode($boardKey), [], $cookies);
    toy_auth_smoke_assert_status($errors, 'post write form', $writeForm, [200]);
    $writeCsrf = toy_auth_smoke_csrf($writeForm, 'post write form');
    $title = 'Toycore auth smoke ' . date('YmdHis');
    $writeResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/write?key=' . rawurlencode($boardKey), [
        'csrf_token' => $writeCsrf,
        'title' => $title,
        'body_text' => "Toycore authenticated community smoke.\nThis post may be removed after verification.",
    ], $cookies);
    toy_auth_smoke_assert_status($errors, 'post write submit', $writeResponse, [302]);

    $createdPostId = $postId;
    $writeLocation = toy_auth_smoke_location_path((string) $writeResponse['location']);
    if (preg_match('/[?&]id=([1-9][0-9]*)/', $writeLocation, $matches) === 1) {
        $createdPostId = (int) $matches[1];
    }

    if ($createdPostId < 1) {
        $errors[] = 'post write submit did not expose a post id redirect.';
    } else {
        $postView = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'post view', $postView, [200]);
        $postViewCsrf = toy_auth_smoke_csrf($postView, 'post view');
        $commentResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/comment', [
            'csrf_token' => $postViewCsrf,
            'post_id' => (string) $createdPostId,
            'body_text' => 'Toycore authenticated community comment smoke.',
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'comment write submit', $commentResponse, [302]);
        $commentedPostView = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'commented post view', $commentedPostView, [200]);

        $scrapResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/scrap', [
            'csrf_token' => $postViewCsrf,
            'post_id' => (string) $createdPostId,
            'intent' => 'add',
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'scrap add', $scrapResponse, [302]);
        $scraps = toy_auth_smoke_request($baseUrl, 'GET', '/community/scraps', [], $cookies);
        toy_auth_smoke_assert_status($errors, 'scrap list', $scraps, [200]);
    }

    if ($recipientIdentifier !== '') {
        $messageForm = toy_auth_smoke_request($baseUrl, 'GET', '/community/message/write', [], $cookies);
        toy_auth_smoke_assert_status($errors, 'message write form', $messageForm, [200]);
        $messageCsrf = toy_auth_smoke_csrf($messageForm, 'message write form');
        $messageResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/message/write', [
            'csrf_token' => $messageCsrf,
            'recipient_identifier' => $recipientIdentifier,
            'body_text' => 'Toycore authenticated community message smoke.',
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'message write submit', $messageResponse, [302]);
    } else {
        echo "[skip] message send requires recipient_identifier\n";
    }

    if ($createdPostId > 0 && $reporterIdentifier !== '' && $reporterPassword !== '') {
        $reporterCookies = [];
        toy_auth_smoke_login($baseUrl, $reporterIdentifier, $reporterPassword, $reporterCookies, $errors, 'reporter account');
        $reporterPostView = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $reporterCookies);
        toy_auth_smoke_assert_status($errors, 'reporter post view', $reporterPostView, [200]);
        $reportCsrf = toy_auth_smoke_csrf($reporterPostView, 'reporter post view');
        $reportResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/report', [
            'csrf_token' => $reportCsrf,
            'target_type' => 'post',
            'target_id' => (string) $createdPostId,
            'reason_key' => 'spam',
            'memo_text' => 'Toycore authenticated community report smoke.',
        ], $reporterCookies);
        toy_auth_smoke_assert_status($errors, 'post report submit', $reportResponse, [302]);
    } else {
        echo "[skip] post report requires reporter_identifier and reporter_password\n";
    }
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
}

if ($errors !== []) {
    fwrite(STDERR, "toycore authenticated community smoke checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "toycore authenticated community smoke checks completed.\n";
