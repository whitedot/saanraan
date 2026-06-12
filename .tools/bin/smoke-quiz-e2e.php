#!/usr/bin/env php
<?php

declare(strict_types=1);

function sr_quiz_e2e_argument(array $argv, int $index, string $environmentKey, string $default = ''): string
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

function sr_quiz_e2e_usage(): string
{
    return "Usage: php .tools/bin/smoke-quiz-e2e.php http://127.0.0.1:8080 admin_identifier admin_password [reward_module]\n"
        . "Env: SR_SMOKE_ALLOW_MUTATION=1 SR_SMOKE_BASE_URL SR_SMOKE_ADMIN_IDENTIFIER SR_SMOKE_ADMIN_PASSWORD SR_SMOKE_QUIZ_REWARD_MODULE\n";
}

$allowMutation = getenv('SR_SMOKE_ALLOW_MUTATION') === '1';
$baseUrl = rtrim(sr_quiz_e2e_argument($argv, 1, 'SR_SMOKE_BASE_URL'), '/');
$adminIdentifier = sr_quiz_e2e_argument($argv, 2, 'SR_SMOKE_ADMIN_IDENTIFIER');
$adminPassword = sr_quiz_e2e_argument($argv, 3, 'SR_SMOKE_ADMIN_PASSWORD');
$configuredRewardModule = sr_quiz_e2e_argument($argv, 4, 'SR_SMOKE_QUIZ_REWARD_MODULE');

if (!$allowMutation) {
    fwrite(STDERR, "saanraan quiz E2E smoke refused to run because it creates quiz and attempt data. Set SR_SMOKE_ALLOW_MUTATION=1 only on local or staging disposable data.\n");
    fwrite(STDERR, sr_quiz_e2e_usage());
    exit(2);
}

if ($baseUrl === '' || !preg_match('#\Ahttps?://#', $baseUrl) || $adminIdentifier === '' || $adminPassword === '') {
    fwrite(STDERR, sr_quiz_e2e_usage());
    exit(2);
}

$cookies = [];
$errors = [];
$createdQuizId = 0;

function sr_quiz_e2e_url(string $baseUrl, string $path): string
{
    return $baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
}

function sr_quiz_e2e_cookie_header(array $cookies): string
{
    $pairs = [];
    foreach ($cookies as $name => $value) {
        $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
    }

    return implode('; ', $pairs);
}

function sr_quiz_e2e_store_cookies(array $headers, array &$cookies): void
{
    foreach ($headers as $header) {
        if (preg_match('/\ASet-Cookie:\s*([^=;\s]+)=([^;]*)/i', (string) $header, $matches) === 1) {
            $cookies[(string) $matches[1]] = urldecode((string) $matches[2]);
        }
    }
}

function sr_quiz_e2e_request(string $baseUrl, string $method, string $path, array $postData, array &$cookies): array
{
    $headers = ["User-Agent: Saanraan-Quiz-E2E-Smoke"];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . sr_quiz_e2e_cookie_header($cookies);
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
            'timeout' => 15,
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
    $body = file_get_contents(sr_quiz_e2e_url($baseUrl, $path), false, $context);
    restore_error_handler();
    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    sr_quiz_e2e_store_cookies($responseHeaders, $cookies);

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

function sr_quiz_e2e_csrf(array $response, string $label): string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', (string) $response['body'], $matches) === 1) {
        return html_entity_decode((string) $matches[1], ENT_QUOTES, 'UTF-8');
    }

    throw new RuntimeException($label . ' CSRF token not found.');
}

function sr_quiz_e2e_assert_status(array &$errors, string $label, array $response, array $allowedStatuses): void
{
    $status = (int) $response['status'];
    if (!in_array($status, $allowedStatuses, true)) {
        $errors[] = $label . ' returned unexpected status ' . (string) $status . '.';
    }
    if (str_contains((string) $response['body'], 'Fatal error') || str_contains((string) $response['body'], 'Stack trace')) {
        $errors[] = $label . ' rendered a PHP failure page.';
    }
}

function sr_quiz_e2e_assert_body_contains(array &$errors, string $label, array $response, string $needle): void
{
    if (!str_contains((string) $response['body'], $needle)) {
        $plainBody = trim(preg_replace('/\s+/', ' ', strip_tags((string) $response['body'])) ?? '');
        if (function_exists('mb_substr')) {
            $plainBody = mb_substr($plainBody, 0, 300);
        } else {
            $plainBody = substr($plainBody, 0, 300);
        }
        $errors[] = $label . ' did not contain expected text "' . $needle . '". Body excerpt: ' . $plainBody;
    }
}

function sr_quiz_e2e_login(string $baseUrl, string $identifier, string $password, array &$cookies, array &$errors): void
{
    $loginForm = sr_quiz_e2e_request($baseUrl, 'GET', '/login', [], $cookies);
    sr_quiz_e2e_assert_status($errors, 'login form', $loginForm, [200]);
    $loginCsrf = sr_quiz_e2e_csrf($loginForm, 'login form');
    $loginResponse = sr_quiz_e2e_request($baseUrl, 'POST', '/login', [
        'csrf_token' => $loginCsrf,
        'identifier' => $identifier,
        'password' => $password,
        'next' => '/admin/quiz',
    ], $cookies);
    sr_quiz_e2e_assert_status($errors, 'login submit', $loginResponse, [302]);
}

function sr_quiz_e2e_reward_module(array $response, string $configuredRewardModule): string
{
    if ($configuredRewardModule !== '') {
        return $configuredRewardModule;
    }

    if (preg_match('/<select\b[^>]*name="reward_module"[^>]*>(.*?)<\/select>/s', (string) $response['body'], $matches) !== 1) {
        return '';
    }
    if (preg_match_all('/<option\b[^>]*value="([^"]+)"[^>]*>/', (string) $matches[1], $options) === false) {
        return '';
    }
    foreach ($options[1] as $value) {
        $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function sr_quiz_e2e_quiz_id_for_key(array $response, string $quizKey): int
{
    $quotedKey = preg_quote($quizKey, '/');
    if (preg_match('/<tr\b[^>]*>.*?' . $quotedKey . '.*?mode=edit&amp;id=([1-9][0-9]*).*?<\/tr>/s', (string) $response['body'], $matches) === 1) {
        return (int) $matches[1];
    }
    if (preg_match('/<tr\b[^>]*>.*?' . $quotedKey . '.*?name="quiz_id"\s+value="([1-9][0-9]*)".*?<\/tr>/s', (string) $response['body'], $matches) === 1) {
        return (int) $matches[1];
    }

    return 0;
}

function sr_quiz_e2e_choice(array $response, string $label): array
{
    $quotedLabel = preg_quote($label, '/');
    if (preg_match('/<label\b[^>]*>\s*<input\b([^>]*\bname="answers\[([1-9][0-9]*)\](?:\[\])?"[^>]*)>\s*<span>' . $quotedLabel . '<\/span>\s*<\/label>/s', (string) $response['body'], $matches) !== 1) {
        throw new RuntimeException('quiz choice not found for label: ' . $label);
    }
    if (preg_match('/\bvalue="([1-9][0-9]*)"/', (string) $matches[1], $valueMatches) !== 1) {
        throw new RuntimeException('quiz choice value not found for label: ' . $label);
    }

    return [
        'question_id' => (int) $matches[2],
        'choice_id' => (int) $valueMatches[1],
    ];
}

function sr_quiz_e2e_cleanup(string $baseUrl, int $quizId, array &$cookies, array &$errors): void
{
    if ($quizId < 1) {
        return;
    }

    $list = sr_quiz_e2e_request($baseUrl, 'GET', '/admin/quiz', [], $cookies);
    if ((int) $list['status'] !== 200) {
        $errors[] = 'cleanup admin quiz list returned unexpected status ' . (string) $list['status'] . '.';
        return;
    }
    $csrf = sr_quiz_e2e_csrf($list, 'cleanup admin quiz list');
    $deleteResponse = sr_quiz_e2e_request($baseUrl, 'POST', '/admin/quiz', [
        'csrf_token' => $csrf,
        'intent' => 'delete',
        'quiz_id' => (string) $quizId,
    ], $cookies);
    sr_quiz_e2e_assert_status($errors, 'cleanup quiz delete', $deleteResponse, [302]);
}

try {
    sr_quiz_e2e_login($baseUrl, $adminIdentifier, $adminPassword, $cookies, $errors);

    $runToken = strtolower(date('ymdHis') . bin2hex(random_bytes(3)));
    $quizKey = 'qa_quiz_' . $runToken;
    $title = 'Saanraan quiz E2E ' . $runToken;
    $choiceA = 'E2E A ' . $runToken;
    $choiceB = 'E2E B ' . $runToken;
    $choiceC = 'E2E C ' . $runToken;
    $singleA = 'E2E Single A ' . $runToken;
    $singleB = 'E2E Single B ' . $runToken;

    $adminNew = sr_quiz_e2e_request($baseUrl, 'GET', '/admin/quiz?mode=new', [], $cookies);
    sr_quiz_e2e_assert_status($errors, 'admin quiz new form', $adminNew, [200]);
    $adminCsrf = sr_quiz_e2e_csrf($adminNew, 'admin quiz new form');
    $rewardModule = sr_quiz_e2e_reward_module($adminNew, $configuredRewardModule);
    $rewardEnabled = $rewardModule !== '';
    if (!$rewardEnabled) {
        echo "[skip] quiz reward assertion requires an active ledger asset option\n";
    }

    $saveResponse = sr_quiz_e2e_request($baseUrl, 'POST', '/admin/quiz', [
        'csrf_token' => $adminCsrf,
        'intent' => 'save',
        'quiz_id' => '0',
        'quiz_key' => $quizKey,
        'title' => $title,
        'description' => 'Automated quiz E2E smoke. This row may be deleted after verification.',
        'status' => 'active',
        'quiz_mode' => 'scored',
        'scoring_model' => 'correct_answer',
        'pass_score' => '2',
        'starts_at' => '',
        'ends_at' => '',
        'attempt_limit_policy' => 'per_quiz_once',
        'attempt_limit_period_seconds' => '',
        'member_group_keys' => [],
        'reward_enabled' => $rewardEnabled ? '1' : '0',
        'reward_provider' => 'ledger_asset',
        'reward_module' => $rewardModule,
        'reward_coupon_definition_id' => '',
        'reward_amount' => $rewardEnabled ? '1' : '',
        'reward_dedupe_scope' => 'per_quiz',
        'content_source_ids' => '',
        'community_source_ids' => '',
        'result_rules' => 'pass_result|통과 결과|2||||자동 E2E 통과',
        'question_uid' => ['qrow_0', 'qrow_1'],
        'question_type' => ['multiple_choice', 'single_choice'],
        'question_key' => ['q1', 'q2'],
        'question_prompt' => ['복수 선택 E2E 문제 ' . $runToken, '단일 선택 E2E 문제 ' . $runToken],
        'question_score' => ['1', '1'],
        'choice_key' => [
            'qrow_0' => ['ca', 'cb', 'cc'],
            'qrow_1' => ['sa', 'sb'],
        ],
        'choice_label' => [
            'qrow_0' => [$choiceA, $choiceB, $choiceC],
            'qrow_1' => [$singleA, $singleB],
        ],
        'choice_category_key' => [
            'qrow_0' => ['alpha', 'beta', 'alpha'],
            'qrow_1' => ['', ''],
        ],
        'choice_category_weight' => [
            'qrow_0' => ['1', '0', '1'],
            'qrow_1' => ['0', '0'],
        ],
        'correct_choice' => [
            'qrow_0' => ['0', '2'],
            'qrow_1' => ['1'],
        ],
    ], $cookies);
    sr_quiz_e2e_assert_status($errors, 'admin quiz save', $saveResponse, [302]);

    $adminList = sr_quiz_e2e_request($baseUrl, 'GET', '/admin/quiz', [], $cookies);
    sr_quiz_e2e_assert_status($errors, 'admin quiz list after save', $adminList, [200]);
    sr_quiz_e2e_assert_body_contains($errors, 'admin quiz list after save', $adminList, $quizKey);
    $createdQuizId = sr_quiz_e2e_quiz_id_for_key($adminList, $quizKey);

    $quizView = sr_quiz_e2e_request($baseUrl, 'GET', '/quiz/' . rawurlencode($quizKey), [], $cookies);
    sr_quiz_e2e_assert_status($errors, 'quiz view', $quizView, [200]);
    sr_quiz_e2e_assert_body_contains($errors, 'quiz view', $quizView, $title);
    $quizCsrf = sr_quiz_e2e_csrf($quizView, 'quiz view');
    $choiceAValue = sr_quiz_e2e_choice($quizView, $choiceA);
    $choiceCValue = sr_quiz_e2e_choice($quizView, $choiceC);
    $singleBValue = sr_quiz_e2e_choice($quizView, $singleB);

    $submitResponse = sr_quiz_e2e_request($baseUrl, 'POST', '/quiz/' . rawurlencode($quizKey), [
        'csrf_token' => $quizCsrf,
        'return_to' => '/quiz',
        'source_module' => '',
        'source_type' => '',
        'source_id' => '',
        'answers' => [
            (string) $choiceAValue['question_id'] => [
                (string) $choiceAValue['choice_id'],
                (string) $choiceCValue['choice_id'],
            ],
            (string) $singleBValue['question_id'] => (string) $singleBValue['choice_id'],
        ],
    ], $cookies);
    sr_quiz_e2e_assert_status($errors, 'quiz submit', $submitResponse, [200]);
    sr_quiz_e2e_assert_body_contains($errors, 'quiz submit', $submitResponse, '통과했습니다.');
    sr_quiz_e2e_assert_body_contains($errors, 'quiz submit', $submitResponse, '점수: 2');
    sr_quiz_e2e_assert_body_contains($errors, 'quiz submit', $submitResponse, '통과 결과');
    if ($rewardEnabled) {
        sr_quiz_e2e_assert_body_contains($errors, 'quiz submit', $submitResponse, '보상이 지급되었습니다.');
    }

    $secondView = sr_quiz_e2e_request($baseUrl, 'GET', '/quiz/' . rawurlencode($quizKey), [], $cookies);
    sr_quiz_e2e_assert_status($errors, 'quiz second view', $secondView, [200]);
    sr_quiz_e2e_assert_body_contains($errors, 'quiz second view', $secondView, '응시 제한에 따라 다시 제출할 수 없습니다.');
} catch (Throwable $exception) {
    $errors[] = $exception->getMessage();
} finally {
    sr_quiz_e2e_cleanup($baseUrl, $createdQuizId, $cookies, $errors);
}

if ($errors !== []) {
    fwrite(STDERR, "saanraan quiz E2E smoke checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "saanraan quiz E2E smoke checks completed.\n";
