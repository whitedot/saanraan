#!/usr/bin/env php
<?php

declare(strict_types=1);

function toy_auth_smoke_argument(array $argv, int $index, string $environmentKey, string $default = ''): string
{
    $argument = (string) ($argv[$index] ?? '');
    if ($argument !== '') {
        return $argument;
    }

    $environmentValue = getenv($environmentKey);
    if (is_string($environmentValue) && $environmentValue !== '') {
        return $environmentValue;
    }

    return $default;
}

function toy_auth_smoke_usage(): string
{
    return "Usage: php .tools/bin/smoke-community-auth.php http://127.0.0.1:8080 login@example.com password [board_key] [recipient_identifier] [post_id] [reporter_identifier] [reporter_password] [admin_identifier] [admin_password] [recipient_password]\n"
        . "Env: TOY_SMOKE_BASE_URL TOY_SMOKE_IDENTIFIER TOY_SMOKE_PASSWORD TOY_SMOKE_BOARD_KEY TOY_SMOKE_RECIPIENT_IDENTIFIER TOY_SMOKE_POST_ID TOY_SMOKE_REPORTER_IDENTIFIER TOY_SMOKE_REPORTER_PASSWORD TOY_SMOKE_ADMIN_IDENTIFIER TOY_SMOKE_ADMIN_PASSWORD TOY_SMOKE_RECIPIENT_PASSWORD\n";
}

$baseUrl = rtrim(toy_auth_smoke_argument($argv, 1, 'TOY_SMOKE_BASE_URL'), '/');
$identifier = toy_auth_smoke_argument($argv, 2, 'TOY_SMOKE_IDENTIFIER');
$password = toy_auth_smoke_argument($argv, 3, 'TOY_SMOKE_PASSWORD');
$boardKey = toy_auth_smoke_argument($argv, 4, 'TOY_SMOKE_BOARD_KEY', 'free');
$recipientIdentifier = toy_auth_smoke_argument($argv, 5, 'TOY_SMOKE_RECIPIENT_IDENTIFIER');
$postId = (int) toy_auth_smoke_argument($argv, 6, 'TOY_SMOKE_POST_ID', '0');
$reporterIdentifier = toy_auth_smoke_argument($argv, 7, 'TOY_SMOKE_REPORTER_IDENTIFIER');
$reporterPassword = toy_auth_smoke_argument($argv, 8, 'TOY_SMOKE_REPORTER_PASSWORD');
$adminIdentifier = toy_auth_smoke_argument($argv, 9, 'TOY_SMOKE_ADMIN_IDENTIFIER');
$adminPassword = toy_auth_smoke_argument($argv, 10, 'TOY_SMOKE_ADMIN_PASSWORD');
$recipientPassword = toy_auth_smoke_argument($argv, 11, 'TOY_SMOKE_RECIPIENT_PASSWORD');

if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl) || $identifier === '' || $password === '') {
    fwrite(STDERR, toy_auth_smoke_usage());
    exit(2);
}

$configurationErrors = [];
if (($reporterIdentifier === '') !== ($reporterPassword === '')) {
    $configurationErrors[] = 'reporter_identifier and reporter_password must be provided together.';
}
if (($adminIdentifier === '') !== ($adminPassword === '')) {
    $configurationErrors[] = 'admin_identifier and admin_password must be provided together.';
}
if ($recipientPassword !== '' && $recipientIdentifier === '') {
    $configurationErrors[] = 'recipient_password requires recipient_identifier.';
}
if ($configurationErrors !== []) {
    fwrite(STDERR, "toycore authenticated community smoke configuration failed:\n");
    foreach ($configurationErrors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
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

    set_error_handler(static function (): bool {
        return true;
    });
    $body = file_get_contents(toy_auth_smoke_url($baseUrl, $path), false, $context);
    restore_error_handler();
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

function toy_auth_smoke_assert_body_contains(array &$errors, string $label, array $response, string $needle): void
{
    if (!str_contains((string) $response['body'], $needle)) {
        $errors[] = $label . ' did not contain expected text "' . $needle . '".';
    }
}

function toy_auth_smoke_assert_body_not_contains(array &$errors, string $label, array $response, string $needle): void
{
    if (str_contains((string) $response['body'], $needle)) {
        $errors[] = $label . ' contained forbidden text "' . $needle . '".';
    }
}

function toy_auth_smoke_assert_body_matches(array &$errors, string $label, array $response, string $pattern): void
{
    if (preg_match($pattern, (string) $response['body']) !== 1) {
        $errors[] = $label . ' did not match expected pattern ' . $pattern . '.';
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

function toy_auth_smoke_report_id_for_post(array $response, int $postId): string
{
    $body = (string) $response['body'];
    if (preg_match_all('/<tr>.*?<\/tr>/s', $body, $rows) !== false) {
        foreach ($rows[0] as $row) {
            if (str_contains((string) $row, 'post #' . (string) $postId)
                && preg_match('/name="report_id"\s+value="([^"]+)"/', (string) $row, $matches) === 1
            ) {
                return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
            }
        }
    }

    throw new RuntimeException('admin report list did not contain report for post #' . (string) $postId);
}

function toy_auth_smoke_first_message_path(array $response): string
{
    if (preg_match('/href="([^"]*\/community\/message\?id=[0-9]+)"/', (string) $response['body'], $matches) === 1) {
        return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
    }

    throw new RuntimeException('message box did not contain a message view link.');
}

function toy_auth_smoke_message_id_from_path(string $messagePath): string
{
    if (preg_match('/[?&]id=([1-9][0-9]*)/', $messagePath, $matches) === 1) {
        return (string) $matches[1];
    }

    throw new RuntimeException('message view path did not contain a message id: ' . $messagePath);
}

function toy_auth_smoke_comment_id_for_body(array $response, string $commentBody): string
{
    $body = (string) $response['body'];
    $quotedBody = preg_quote($commentBody, '/');
    if (preg_match('/<tr\b[^>]*>.*?' . $quotedBody . '.*?name="comment_id"\s+value="([1-9][0-9]*)".*?<\/tr>/s', $body, $matches) === 1) {
        return (string) $matches[1];
    }

    throw new RuntimeException('admin comment list did not contain comment body: ' . $commentBody);
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
        $commentBody = 'Toycore authenticated community comment smoke.';
        $postView = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'post view', $postView, [200]);
        toy_auth_smoke_assert_body_contains($errors, 'post view', $postView, $title);
        $postViewCsrf = toy_auth_smoke_csrf($postView, 'post view');
        $editForm = toy_auth_smoke_request($baseUrl, 'GET', '/community/edit?id=' . (string) $createdPostId, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'post edit form', $editForm, [200]);
        $editCsrf = toy_auth_smoke_csrf($editForm, 'post edit form');
        $editedTitle = $title . ' edited';
        $editedBody = "Toycore authenticated community smoke edited.\nThis post may be removed after verification.";
        $editResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/edit?id=' . (string) $createdPostId, [
            'csrf_token' => $editCsrf,
            'post_id' => (string) $createdPostId,
            'title' => $editedTitle,
            'body_text' => $editedBody,
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'post edit submit', $editResponse, [302]);
        $editedPostView = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'edited post view', $editedPostView, [200]);
        toy_auth_smoke_assert_body_contains($errors, 'edited post view', $editedPostView, $editedTitle);
        toy_auth_smoke_assert_body_contains($errors, 'edited post view', $editedPostView, 'Toycore authenticated community smoke edited.');
        $title = $editedTitle;
        $commentResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/comment', [
            'csrf_token' => $postViewCsrf,
            'post_id' => (string) $createdPostId,
            'body_text' => $commentBody,
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'comment write submit', $commentResponse, [302]);
        $commentedPostView = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'commented post view', $commentedPostView, [200]);
        toy_auth_smoke_assert_body_contains($errors, 'commented post view', $commentedPostView, $commentBody);

        $scrapResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/scrap', [
            'csrf_token' => $postViewCsrf,
            'post_id' => (string) $createdPostId,
            'intent' => 'add',
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'scrap add', $scrapResponse, [302]);
        $scraps = toy_auth_smoke_request($baseUrl, 'GET', '/community/scraps', [], $cookies);
        toy_auth_smoke_assert_status($errors, 'scrap list', $scraps, [200]);
        toy_auth_smoke_assert_body_contains($errors, 'scrap list', $scraps, $title);
        $scrapRemoveResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/scrap', [
            'csrf_token' => $postViewCsrf,
            'post_id' => (string) $createdPostId,
            'intent' => 'remove',
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'scrap remove', $scrapRemoveResponse, [302]);
        $scrapsAfterRemove = toy_auth_smoke_request($baseUrl, 'GET', '/community/scraps', [], $cookies);
        toy_auth_smoke_assert_status($errors, 'scrap list after remove', $scrapsAfterRemove, [200]);
        toy_auth_smoke_assert_body_not_contains($errors, 'scrap list after remove', $scrapsAfterRemove, $title);
    }

    if ($recipientIdentifier !== '') {
        $messageForm = toy_auth_smoke_request($baseUrl, 'GET', '/community/message/write', [], $cookies);
        toy_auth_smoke_assert_status($errors, 'message write form', $messageForm, [200]);
        toy_auth_smoke_assert_body_not_contains($errors, 'message write form', $messageForm, 'name="recipient_account_id"');
        toy_auth_smoke_assert_body_not_contains($errors, 'message write form', $messageForm, '/community/message/write?to=');
        $messageCsrf = toy_auth_smoke_csrf($messageForm, 'message write form');
        $messageBody = 'Toycore authenticated community message smoke.';
        $messageResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/message/write', [
            'csrf_token' => $messageCsrf,
            'recipient_identifier' => $recipientIdentifier,
            'body_text' => $messageBody,
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'message write submit', $messageResponse, [302]);
        $sentMessages = toy_auth_smoke_request($baseUrl, 'GET', '/community/messages?box=sent', [], $cookies);
        toy_auth_smoke_assert_status($errors, 'sent message box', $sentMessages, [200]);
        $sentMessagePath = toy_auth_smoke_first_message_path($sentMessages);
        $sentMessageId = toy_auth_smoke_message_id_from_path($sentMessagePath);
        $sentMessageView = toy_auth_smoke_request($baseUrl, 'GET', $sentMessagePath, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'sent message view', $sentMessageView, [200]);
        toy_auth_smoke_assert_body_contains($errors, 'sent message view', $sentMessageView, $messageBody);
        toy_auth_smoke_assert_body_matches($errors, 'sent message view reply link', $sentMessageView, '#/community/message/write\?to_account=[a-f0-9]{32}#');
        toy_auth_smoke_assert_body_not_contains($errors, 'sent message view', $sentMessageView, '/community/message/write?to=');
        if ($recipientPassword !== '') {
            $recipientCookies = [];
            toy_auth_smoke_login($baseUrl, $recipientIdentifier, $recipientPassword, $recipientCookies, $errors, 'message recipient account');
            $inboxMessages = toy_auth_smoke_request($baseUrl, 'GET', '/community/messages', [], $recipientCookies);
            toy_auth_smoke_assert_status($errors, 'recipient message box', $inboxMessages, [200]);
            $inboxMessageView = toy_auth_smoke_request($baseUrl, 'GET', $sentMessagePath, [], $recipientCookies);
            toy_auth_smoke_assert_status($errors, 'recipient message view', $inboxMessageView, [200]);
            toy_auth_smoke_assert_body_contains($errors, 'recipient message view', $inboxMessageView, $messageBody);
            toy_auth_smoke_assert_body_matches($errors, 'recipient message view reply link', $inboxMessageView, '#/community/message/write\?to_account=[a-f0-9]{32}#');
            toy_auth_smoke_assert_body_not_contains($errors, 'recipient message view', $inboxMessageView, '/community/message/write?to=');
        } else {
            echo "[skip] message receive requires recipient_password\n";
        }
        $messageDeleteCsrf = toy_auth_smoke_csrf($sentMessageView, 'sent message view');
        $messageDeleteResponse = toy_auth_smoke_request($baseUrl, 'POST', '/community/message/delete', [
            'csrf_token' => $messageDeleteCsrf,
            'message_id' => $sentMessageId,
        ], $cookies);
        toy_auth_smoke_assert_status($errors, 'sent message delete', $messageDeleteResponse, [302]);
        $sentMessagesAfterDelete = toy_auth_smoke_request($baseUrl, 'GET', '/community/messages?box=sent', [], $cookies);
        toy_auth_smoke_assert_status($errors, 'sent message box after delete', $sentMessagesAfterDelete, [200]);
        toy_auth_smoke_assert_body_not_contains($errors, 'sent message box after delete', $sentMessagesAfterDelete, $sentMessagePath);
        $deletedSentMessageView = toy_auth_smoke_request($baseUrl, 'GET', $sentMessagePath, [], $cookies);
        toy_auth_smoke_assert_status($errors, 'deleted sent message view', $deletedSentMessageView, [404]);
    } else {
        echo "[skip] message send requires recipient_identifier\n";
    }

    $reportedPost = false;
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
        $reportedPost = true;
    } else {
        echo "[skip] post report requires reporter_identifier and reporter_password\n";
    }

    if ($createdPostId > 0 && $adminIdentifier !== '' && $adminPassword !== '') {
        $adminCookies = [];
        toy_auth_smoke_login($baseUrl, $adminIdentifier, $adminPassword, $adminCookies, $errors, 'admin account');
        if ($reportedPost) {
            $adminReports = toy_auth_smoke_request($baseUrl, 'GET', '/admin/community/reports', [], $adminCookies);
            toy_auth_smoke_assert_status($errors, 'admin report list', $adminReports, [200]);
            $adminReportCsrf = toy_auth_smoke_csrf($adminReports, 'admin report list');
            $reportId = toy_auth_smoke_report_id_for_post($adminReports, $createdPostId);
            $reportReviewResponse = toy_auth_smoke_request($baseUrl, 'POST', '/admin/community/reports', [
                'csrf_token' => $adminReportCsrf,
                'report_id' => $reportId,
                'status' => 'resolved',
                'review_note' => 'Toycore authenticated community admin report smoke.',
            ], $adminCookies);
            toy_auth_smoke_assert_status($errors, 'admin report resolve', $reportReviewResponse, [200]);
        } else {
            echo "[skip] admin report resolve requires reporter credentials\n";
        }

        $adminPosts = toy_auth_smoke_request($baseUrl, 'GET', '/admin/community/posts', [], $adminCookies);
        toy_auth_smoke_assert_status($errors, 'admin post list', $adminPosts, [200]);
        $adminPostCsrf = toy_auth_smoke_csrf($adminPosts, 'admin post list');
        if (isset($commentBody) && is_string($commentBody) && $commentBody !== '') {
            $commentId = toy_auth_smoke_comment_id_for_body($adminPosts, $commentBody);
            $commentHideResponse = toy_auth_smoke_request($baseUrl, 'POST', '/admin/community/posts', [
                'csrf_token' => $adminPostCsrf,
                'intent' => 'comment_status',
                'comment_id' => $commentId,
                'status' => 'hidden',
            ], $adminCookies);
            toy_auth_smoke_assert_status($errors, 'admin comment hide', $commentHideResponse, [200]);
            $postAfterCommentHide = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $cookies);
            toy_auth_smoke_assert_status($errors, 'post view after comment hide', $postAfterCommentHide, [200]);
            toy_auth_smoke_assert_body_not_contains($errors, 'post view after comment hide', $postAfterCommentHide, $commentBody);
        }

        $postHideResponse = toy_auth_smoke_request($baseUrl, 'POST', '/admin/community/posts', [
            'csrf_token' => $adminPostCsrf,
            'intent' => 'post_status',
            'post_id' => (string) $createdPostId,
            'status' => 'hidden',
        ], $adminCookies);
        toy_auth_smoke_assert_status($errors, 'admin post hide', $postHideResponse, [200]);

        $viewerCookies = isset($reporterCookies) && is_array($reporterCookies) ? $reporterCookies : [];
        $publicPostAfterHide = toy_auth_smoke_request($baseUrl, 'GET', '/community/post?id=' . (string) $createdPostId, [], $viewerCookies);
        toy_auth_smoke_assert_status($errors, 'hidden post public view', $publicPostAfterHide, [404]);
    } else {
        echo "[skip] admin moderation requires admin_identifier and admin_password\n";
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
