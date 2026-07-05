<?php

declare(strict_types=1);

ob_start();

define('SR_ROOT', dirname(__DIR__, 2));

require SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';
require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/notification/helpers.php';
require_once SR_ROOT . '/modules/quiz/helpers.php';
require_once SR_ROOT . '/modules/survey/helpers.php';
require_once SR_ROOT . '/modules/survey/helpers/admin-surveys.php';

$baseUrl = rtrim((string) ($argv[1] ?? getenv('SR_SEED_BASE_URL') ?: ''), '/');
$adminIdentifier = (string) ($argv[2] ?? getenv('SR_SEED_ADMIN_IDENTIFIER') ?: 'admin');
$adminPassword = (string) ($argv[3] ?? getenv('SR_SEED_ADMIN_PASSWORD') ?: '');
$count = (int) ($argv[4] ?? getenv('SR_SEED_COUNT') ?: 10);
$skipMembers = in_array(strtolower((string) getenv('SR_SEED_SKIP_MEMBERS')), ['1', 'true', 'yes', 'on'], true);
$skipFoundation = in_array(strtolower((string) getenv('SR_SEED_SKIP_FOUNDATION')), ['1', 'true', 'yes', 'on'], true);
$skipCommunity = in_array(strtolower((string) getenv('SR_SEED_SKIP_COMMUNITY')), ['1', 'true', 'yes', 'on'], true);
$skipRichFixtures = in_array(strtolower((string) getenv('SR_SEED_SKIP_RICH_FIXTURES')), ['1', 'true', 'yes', 'on'], true);
$skipOperations = in_array(strtolower((string) getenv('SR_SEED_SKIP_OPERATIONS')), ['1', 'true', 'yes', 'on'], true);
$ensureFreeBoard = in_array(strtolower((string) getenv('SR_SEED_ENSURE_FREE_BOARD')), ['1', 'true', 'yes', 'on'], true);
$trimDisplay = in_array(strtolower((string) getenv('SR_SEED_TRIM_DISPLAY')), ['1', 'true', 'yes', 'on'], true);
$allowMutation = getenv('SR_SEED_ALLOW_MUTATION') === '1';

if ($baseUrl === '' || $adminPassword === '') {
    fwrite(STDERR, "Usage: SR_SEED_ALLOW_MUTATION=1 php .tools/bin/seed-dummy-http.php <base-url> <admin-identifier> <admin-password> [count]\n");
    fwrite(STDERR, "Env: SR_SEED_ALLOW_MUTATION=1 SR_SEED_BASE_URL SR_SEED_ADMIN_IDENTIFIER SR_SEED_ADMIN_PASSWORD SR_SEED_COUNT SR_SEED_RUN_KEY SR_SEED_SKIP_RICH_FIXTURES\n");
    exit(1);
}

if (!$allowMutation) {
    fwrite(STDERR, "saanraan dummy HTTP seed refused to run because it creates and trims QA data. Set SR_SEED_ALLOW_MUTATION=1 only on local or staging disposable data.\n");
    fwrite(STDERR, "Usage: SR_SEED_ALLOW_MUTATION=1 php .tools/bin/seed-dummy-http.php <base-url> <admin-identifier> <admin-password> [count]\n");
    exit(2);
}

$count = max(10, min(15, $count));
$runKey = strtolower((string) (getenv('SR_SEED_RUN_KEY') ?: 'qa' . date('ymdhi')));
$runKey = preg_replace('/[^a-z0-9_]/', '', $runKey) ?: 'qa' . date('ymdhi');
if (!preg_match('/^[a-z]/', $runKey)) {
    $runKey = 'qa' . $runKey;
}
$runKey = substr($runKey, 0, 24);

$config = sr_load_config();
sr_set_runtime_config($config);
$pdo = sr_db($config);
$adminLoggedIn = false;

$cookieFile = tempnam(sys_get_temp_dir(), 'sr-seed-cookie-');
if (!is_string($cookieFile)) {
    fwrite(STDERR, "Could not create cookie jar.\n");
    exit(1);
}

register_shutdown_function(static function () use ($cookieFile): void {
    if (is_file($cookieFile)) {
        @unlink($cookieFile);
    }
});

function seed_count(PDO $pdo, string $table): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

function seed_http(string $method, string $path, array $data = []): array
{
    global $baseUrl, $cookieFile;

    $ch = curl_init($baseUrl . $path);
    if ($ch === false) {
        throw new RuntimeException('Could not initialize cURL.');
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 8,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HEADER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'saanraan-http-seed/1.0',
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if (!is_string($body)) {
        throw new RuntimeException($method . ' ' . $path . ' failed: ' . $error);
    }
    if ($status >= 400) {
        throw new RuntimeException($method . ' ' . $path . ' returned HTTP ' . $status . ' at ' . $effectiveUrl);
    }

    return [
        'status' => $status,
        'body' => $body,
        'url' => $effectiveUrl,
    ];
}

function seed_csrf(string $path): string
{
    $response = seed_http('GET', $path);
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', (string) $response['body'], $matches) !== 1) {
        throw new RuntimeException('CSRF token not found on ' . $path);
    }

    return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
}

function seed_post(string $csrfPath, string $postPath, array $data): void
{
    $data['csrf_token'] = seed_csrf($csrfPath);
    $response = seed_http('POST', $postPath, $data);
    $messages = seed_response_error_messages((string) $response['body']);
    if ($messages !== []) {
        throw new RuntimeException('POST ' . $postPath . ' returned form errors: ' . implode(' / ', $messages));
    }
}

function seed_admin_login(): void
{
    global $adminIdentifier, $adminPassword, $adminLoggedIn;

    if ($adminLoggedIn) {
        return;
    }

    $adminAccount = seed_assert_admin_credentials();

    $loginData = [
        'identifier' => $adminIdentifier,
        'password' => $adminPassword,
        'next' => '/admin',
        'csrf_token' => seed_csrf('/login'),
    ];
    $loginResponse = seed_http('POST', '/login', $loginData);
    $loginMessages = seed_response_error_messages((string) $loginResponse['body']);
    if ($loginMessages !== []) {
        throw new RuntimeException('Admin login failed: ' . implode(' / ', $loginMessages));
    }

    $adminPage = seed_http('GET', '/admin');
    if (strpos((string) $adminPage['url'], '/login') !== false) {
        $adminMessages = seed_response_error_messages((string) $adminPage['body']);
        $messageSuffix = $adminMessages !== [] ? ': ' . implode(' / ', $adminMessages) : '.';
        seed_admin_login_direct($adminAccount, 'HTTP admin login did not persist session' . $messageSuffix);
        $adminPage = seed_http('GET', '/admin');
        if (strpos((string) $adminPage['url'], '/login') !== false) {
            throw new RuntimeException('Admin login failed after direct session fallback.');
        }
    }

    $adminLoggedIn = true;
}

function seed_admin_owner_hint(PDO $pdo): string
{
    if (!seed_table_exists($pdo, 'sr_admin_account_roles') || !seed_table_exists($pdo, 'sr_member_accounts')) {
        return '';
    }

    $stmt = $pdo->query(
        "SELECT a.email, a.display_name
         FROM sr_admin_account_roles r
         INNER JOIN sr_member_accounts a ON a.id = r.account_id
         WHERE r.role_key = 'owner' AND a.status = 'active'
         ORDER BY a.id ASC
         LIMIT 5"
    );
    $rows = $stmt !== false ? $stmt->fetchAll() : [];
    $labels = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $email = trim((string) ($row['email'] ?? ''));
        $displayName = trim((string) ($row['display_name'] ?? ''));
        if ($email !== '' && $displayName !== '') {
            $labels[] = $email . ' (' . $displayName . ')';
        } elseif ($email !== '') {
            $labels[] = $email;
        }
    }

    return $labels !== [] ? ' Active owner accounts: ' . implode(', ', $labels) . '.' : '';
}

function seed_assert_admin_credentials(): array
{
    global $pdo, $config, $adminIdentifier, $adminPassword;

    $memberSettings = sr_member_settings($pdo);
    $account = sr_member_find_by_identifier($pdo, $config, $adminIdentifier, sr_member_email_login_enabled($memberSettings));
    if ($account === null) {
        throw new RuntimeException(
            'Admin login preflight failed: no member account matches SR_SEED_ADMIN_IDENTIFIER='
            . $adminIdentifier
            . '. Use the admin email if the account has no login ID.'
            . seed_admin_owner_hint($pdo)
        );
    }

    if (!sr_member_verify_login_password($account, $adminPassword)) {
        throw new RuntimeException(
            'Admin login preflight failed: password did not verify for SR_SEED_ADMIN_IDENTIFIER='
            . $adminIdentifier
            . '.'
            . seed_admin_owner_hint($pdo)
        );
    }

    if ((string) ($account['status'] ?? '') !== 'active') {
        throw new RuntimeException('Admin login preflight failed: account status is ' . (string) ($account['status'] ?? 'unknown') . '.');
    }

    if (!sr_admin_has_permission($pdo, (int) $account['id'], '/admin', 'view')) {
        throw new RuntimeException('Admin login preflight failed: account has no admin dashboard permission.');
    }

    return $account;
}

function seed_admin_login_direct(array $account, string $reason): void
{
    global $pdo, $config, $baseUrl, $cookieFile;

    echo "admin_login\tdirect session fallback: " . $reason . "\n";

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $_SERVER['REMOTE_ADDR'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    $_SERVER['HTTP_USER_AGENT'] = 'saanraan-http-seed/1.0';
    session_name('sr_session');
    session_id(bin2hex(random_bytes(16)));
    sr_start_session($config, $pdo);
    if (!sr_member_login($pdo, $account)) {
        session_write_close();
        throw new RuntimeException('Admin direct session fallback failed: member session could not be created.');
    }

    $sessionId = session_id();
    session_write_close();
    seed_write_session_cookie($sessionId);
}

function seed_write_session_cookie(string $sessionId): void
{
    global $baseUrl, $cookieFile;

    if (preg_match('/\A[a-zA-Z0-9,-]{22,128}\z/', $sessionId) !== 1) {
        throw new RuntimeException('Admin direct session fallback failed: generated session ID is invalid.');
    }

    $host = (string) parse_url($baseUrl, PHP_URL_HOST);
    if ($host === '') {
        throw new RuntimeException('Admin direct session fallback failed: seed base URL host is invalid.');
    }

    $path = (string) parse_url($baseUrl, PHP_URL_PATH);
    $path = $path === '' ? '/' : '/' . trim($path, '/');
    if ($path !== '/') {
        $path .= '/';
    }

    $cookie = "# Netscape HTTP Cookie File\n"
        . $host . "\tFALSE\t" . $path . "\tFALSE\t0\tsr_session\t" . $sessionId . "\n";
    if (file_put_contents($cookieFile, $cookie) === false) {
        throw new RuntimeException('Admin direct session fallback failed: could not write cURL cookie jar.');
    }
}

function seed_member_payload(string $runKey, int $index): array
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);

    return [
        'email' => $runKey . 'member' . $n . '@example.test',
        'login_id' => $runKey . 'm' . $n,
        'display_name' => 'QA회원' . $n,
        'nickname' => $runKey . 'nick' . $n,
        'password' => 'SaanraanQA1!',
        'password_confirm' => 'SaanraanQA1!',
    ];
}

function seed_register_member(string $runKey, int $index): void
{
    seed_post('/register', '/register', seed_member_payload($runKey, $index) + [
        'terms_consent' => '1',
        'privacy_consent' => '1',
        'marketing_consent' => '1',
    ]);
}

function seed_admin_create_member(string $runKey, int $index): void
{
    global $pdo;

    $before = seed_count($pdo, 'sr_member_accounts');
    seed_post('/admin/members/new', '/admin/members', seed_member_payload($runKey, $index) + [
        'intent' => 'create',
        'locale' => 'ko',
        'status' => 'active',
        'email_verified' => '1',
    ]);
    if (seed_count($pdo, 'sr_member_accounts') > $before) {
        return;
    }

    echo "members\tadmin HTTP create fallback: no row created for member " . (string) $index . "\n";
    seed_create_member_direct($runKey, $index);
}

function seed_create_member_direct(string $runKey, int $index): void
{
    global $pdo, $config;

    $payload = seed_member_payload($runKey, $index);
    $runtimeConfig = sr_runtime_config();
    $memberSettings = sr_member_settings($pdo);
    $email = sr_normalize_identifier((string) $payload['email']);
    $loginId = sr_member_normalize_login_id((string) $payload['login_id']);
    $nickname = sr_member_normalize_nickname((string) $payload['nickname']);

    if (sr_member_find_by_identifier($pdo, $config, $email, true) !== null) {
        return;
    }

    try {
        $pdo->beginTransaction();
        $accountId = sr_member_create_account($pdo, $runtimeConfig, [
            'email' => $email,
            'login_id' => $loginId,
            'password' => (string) $payload['password'],
            'display_name' => sr_member_normalize_display_name((string) $payload['display_name']),
            'locale' => 'ko',
            'status' => 'active',
            'email_verified_at' => sr_now(),
        ]);
        if (!empty($memberSettings['nickname_enabled']) && $nickname !== '') {
            sr_member_set_nickname($pdo, $accountId, $nickname);
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw new RuntimeException('Direct member fixture create failed: ' . $exception->getMessage(), 0, $exception);
    }
}

function seed_member_group_payload(string $runKey, int $index): array
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);

    return [
        'group_key' => $runKey . 'mg' . $n,
        'title' => 'QA 회원그룹 ' . $n,
        'description' => 'HTTP 시더가 실제 관리자 경로로 만든 회원 그룹입니다.',
        'status' => 'enabled',
        'sort_order' => (string) ($index * 10),
    ];
}

function seed_create_member_group(string $runKey, int $index): void
{
    global $pdo;

    $before = seed_count($pdo, 'sr_member_groups');
    try {
        seed_post('/admin/member-groups', '/admin/member-groups', seed_member_group_payload($runKey, $index) + [
            'intent' => 'save_group',
        ]);
    } catch (RuntimeException $exception) {
        echo "member_groups\tadmin HTTP create fallback: " . $exception->getMessage() . "\n";
        seed_create_member_group_direct($runKey, $index);
        return;
    }

    if (seed_count($pdo, 'sr_member_groups') > $before) {
        return;
    }

    echo "member_groups\tadmin HTTP create fallback: no row created for group " . (string) $index . "\n";
    seed_create_member_group_direct($runKey, $index);
}

function seed_create_member_group_direct(string $runKey, int $index): void
{
    global $pdo;

    $payload = seed_member_group_payload($runKey, $index);
    if (sr_member_group_by_key($pdo, (string) $payload['group_key']) !== null) {
        return;
    }

    sr_member_group_save($pdo, [
        'id' => 0,
        'group_key' => (string) $payload['group_key'],
        'title' => (string) $payload['title'],
        'description' => (string) $payload['description'],
        'status' => (string) $payload['status'],
        'sort_order' => (int) $payload['sort_order'],
    ]);
}

function seed_content_group_payload(string $runKey, int $index): array
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $data = [
        'intent' => 'create_group',
        'group_key' => $runKey . 'cg' . $n,
        'title' => 'QA 콘텐츠 그룹 ' . $n,
        'description' => 'HTTP 시더가 실제 관리자 경로로 만든 콘텐츠 그룹입니다.',
        'status' => 'enabled',
        'sort_order' => (string) ($index * 10),
        'group_content_status' => 'published',
        'group_layout_key' => '',
        'group_asset_access_enabled' => '0',
        'group_asset_access_amount' => '0',
        'group_asset_charge_policy' => 'once',
        'group_asset_action_enabled' => '0',
        'group_asset_action_amount' => '0',
        'group_asset_action_direction' => 'grant',
        'group_asset_action_label' => '완료',
        'group_file_asset_download_enabled' => '0',
        'group_file_asset_download_amount' => '0',
        'group_file_asset_charge_policy' => 'once',
    ];

    foreach (array_keys(sr_content_public_display_setting_labels()) as $field) {
        $data['group_' . $field] = '0';
    }

    return $data;
}

function seed_create_content_group(string $runKey, int $index): void
{
    global $pdo;

    $before = seed_count($pdo, 'sr_content_groups');
    try {
        seed_post('/admin/content-groups/new', '/admin/content-groups', seed_content_group_payload($runKey, $index));
    } catch (RuntimeException $exception) {
        echo "content_groups\tadmin HTTP create fallback: " . $exception->getMessage() . "\n";
        seed_create_content_group_direct($runKey, $index);
        return;
    }

    if (seed_count($pdo, 'sr_content_groups') > $before) {
        return;
    }

    echo "content_groups\tadmin HTTP create fallback: no row created for group " . (string) $index . "\n";
    seed_create_content_group_direct($runKey, $index);
}

function seed_create_content_group_direct(string $runKey, int $index): void
{
    global $pdo;

    $payload = seed_content_group_payload($runKey, $index);
    if (sr_content_group_by_key($pdo, (string) $payload['group_key']) !== null) {
        return;
    }

    $groupId = sr_content_create_group($pdo, [
        'group_key' => (string) $payload['group_key'],
        'title' => (string) $payload['title'],
        'description' => (string) $payload['description'],
        'status' => (string) $payload['status'],
        'sort_order' => (int) $payload['sort_order'],
    ]);

    foreach (sr_content_group_setting_keys() as $settingKey) {
        $postKey = 'group_' . $settingKey;
        if (!array_key_exists($postKey, $payload)) {
            continue;
        }
        sr_content_set_group_setting($pdo, $groupId, $settingKey, (string) $payload[$postKey], seed_setting_value_type((string) $payload[$postKey]));
    }
}

function seed_setting_value_type(string $value): string
{
    return preg_match('/\A-?[0-9]+\z/', $value) === 1 ? 'int' : 'string';
}

function seed_with_direct_fallback(string $label, string $table, int $index, callable $httpCreate, callable $directCreate): void
{
    global $pdo;

    $before = seed_count($pdo, $table);
    try {
        $httpCreate();
    } catch (RuntimeException $exception) {
        echo $label . "\tdirect fallback: " . $exception->getMessage() . "\n";
        $directCreate();
        return;
    }

    if (seed_count($pdo, $table) > $before) {
        return;
    }

    echo $label . "\tdirect fallback: no row created for item " . (string) $index . "\n";
    $directCreate();
}

function seed_content_payload(string $runKey, int $index, int $groupId): array
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $data = [
        'content_id' => '0',
        'content_group_scope' => 'here_only',
        'content_group_id' => (string) $groupId,
        'source_status' => 'content',
        'source_layout_key' => 'content',
        'title' => 'QA 콘텐츠 ' . $n,
        'slug' => $runKey . '-content-' . $n,
        'summary' => '실제 관리자 콘텐츠 저장 경로로 만든 더미 콘텐츠입니다.',
        'cover_image_url' => '',
        'body_text' => "QA 콘텐츠 본문 {$n}\n\n목록, 상세, 검색, SEO 검수에 사용할 문장입니다.",
        'body_format' => 'plain',
        'status' => 'published',
        'layout_key' => '',
        'asset_access_enabled' => '0',
        'asset_module' => '',
        'asset_access_amount' => '0',
        'asset_access_amounts_json' => '{}',
        'asset_access_group_policies_json' => '',
        'asset_access_policy_set_id' => '0',
        'asset_charge_policy' => 'once',
        'asset_action_enabled' => '0',
        'asset_action_module' => '',
        'asset_action_amount' => '0',
        'asset_action_amounts_json' => '{}',
        'asset_action_group_policies_json' => '',
        'asset_action_policy_set_id' => '0',
        'asset_action_direction' => 'grant',
        'asset_action_label' => '완료',
        'file_asset_download_enabled' => '0',
        'file_asset_download_amount' => '0',
        'file_asset_charge_policy' => 'once',
        'seo_title' => 'QA 콘텐츠 SEO ' . $n,
        'seo_description' => 'QA 더미 콘텐츠 SEO 설명 ' . $n,
        'series_id' => '0',
        'series_episode_label' => '',
        'series_sort_order' => '0',
        'banner_before_content_id' => '0',
        'banner_after_content_id' => '0',
        'popup_layer_id' => '0',
        'reaction_preset_key' => '',
        'reaction_comment_preset_key' => '',
    ];
    foreach (array_keys(sr_content_public_display_setting_labels()) as $field) {
        $data[$field] = '0';
        $data['source_' . $field] = 'content';
    }

    return $data;
}

function seed_create_content_item(string $runKey, int $index, int $groupId): void
{
    global $pdo;

    $payload = seed_content_payload($runKey, $index, $groupId);
    seed_with_direct_fallback('content_items', 'sr_content_items', $index, static function () use ($payload): void {
        seed_post('/admin/content/new', '/admin/content/save', $payload);
    }, static function () use ($pdo, $payload): void {
        seed_create_content_item_direct($payload);
    });
}

function seed_create_content_item_direct(array $payload): void
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM sr_content_items WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => (string) $payload['slug']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    sr_content_save($pdo, $payload, seed_first_account_id($pdo), 0);
}

function seed_community_board_group_payload(string $runKey, int $index): array
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $data = [
        'intent' => 'create_group',
        'group_key' => $runKey . 'bg' . $n,
        'title' => 'QA 게시판 그룹 ' . $n,
        'description' => 'HTTP 시더가 실제 관리자 경로로 만든 게시판 그룹입니다.',
        'status' => 'enabled',
        'sort_order' => (string) ($index * 10),
        'group_read_policy' => 'public',
        'group_write_policy' => 'member',
        'group_comment_policy' => 'member',
        'group_post_editor' => 'textarea',
        'group_attachment_max_bytes' => '2097152',
        'group_attachment_max_count' => '1',
        'group_file_attachment_max_bytes' => '5242880',
        'group_file_attachment_max_count' => '1',
        'group_file_allowed_extensions' => 'pdf,zip',
        'group_read_min_level' => '0',
        'group_write_min_level' => '0',
        'group_comment_min_level' => '0',
        'group_level_post_score' => '0',
        'group_level_comment_score' => '0',
        'group_paid_read_charge_policy' => 'once',
        'group_paid_attachment_download_charge_policy' => 'once',
    ];
    foreach (array_keys(sr_community_public_display_setting_labels()) as $field) {
        $data['group_' . $field] = '0';
    }
    foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
        $data['group_' . $assetPrefix . '_enabled'] = '0';
        $data['group_' . $assetPrefix . '_asset_module'] = '';
        $data['group_' . $assetPrefix . '_amount'] = '0';
    }
    $data['group_paid_attachment_download_publisher_reward_enabled'] = '0';
    $data['group_paid_attachment_download_publisher_reward_rate'] = '0';

    return $data;
}

function seed_create_community_board_group(string $runKey, int $index): void
{
    $payload = seed_community_board_group_payload($runKey, $index);
    seed_with_direct_fallback('community_board_groups', 'sr_community_board_groups', $index, static function () use ($payload): void {
        seed_post('/admin/community/board-groups/new', '/admin/community/board-groups', $payload);
    }, static function () use ($payload): void {
        seed_create_community_board_group_direct($payload);
    });
}

function seed_create_community_board_group_direct(array $payload): void
{
    global $pdo;

    if (sr_community_board_group_by_key($pdo, (string) $payload['group_key']) !== null) {
        return;
    }

    $groupId = sr_community_create_board_group($pdo, $payload);
    foreach (sr_community_board_group_all_setting_keys() as $settingKey) {
        $postKey = 'group_' . $settingKey;
        if (array_key_exists($postKey, $payload)) {
            sr_community_set_board_group_setting($pdo, $groupId, $settingKey, (string) $payload[$postKey], seed_setting_value_type((string) $payload[$postKey]));
        }
    }
}

function seed_community_board_payload(string $runKey, int $index, int $groupId, bool $freeBoard = false): array
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $data = [
        'board_key' => $freeBoard ? 'free' : $runKey . 'board' . $n,
        'title' => $freeBoard ? '자유 게시판' : 'QA 게시판 ' . $n,
        'description' => $freeBoard ? 'HTTP smoke와 기본 커뮤니티 검수를 위한 자유 게시판입니다.' : 'HTTP 시더가 실제 관리자 경로로 만든 게시판입니다.',
        'status' => 'enabled',
        'read_policy' => 'public',
        'write_policy' => 'member',
        'comment_policy' => 'member',
        'skin_key' => 'basic',
        'post_editor' => 'textarea',
        'sort_order' => $freeBoard ? '0' : (string) ($index * 10),
        'attachment_max_bytes' => '2097152',
        'attachment_max_count' => '1',
        'file_attachment_max_bytes' => '5242880',
        'file_attachment_max_count' => '1',
        'file_allowed_extensions' => 'pdf,zip',
        'read_min_level' => '0',
        'write_min_level' => '0',
        'comment_min_level' => '0',
        'level_post_score' => '0',
        'level_comment_score' => '0',
        'board_group_id' => (string) $groupId,
        'paid_read_charge_policy' => 'once',
        'paid_attachment_download_charge_policy' => 'once',
    ];
    foreach (array_keys(sr_community_public_banner_setting_labels() + sr_community_public_popup_layer_setting_labels()) as $field) {
        $data[$field] = '0';
    }
    foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
        $data[$assetPrefix . '_enabled'] = '0';
        $data[$assetPrefix . '_asset_module'] = '';
        $data[$assetPrefix . '_amount'] = '0';
        $data['source_' . $assetPrefix] = 'board';
        $data['source_' . $assetPrefix . '_asset_module'] = 'board';
        foreach (sr_community_asset_prefix_setting_keys((string) $assetPrefix) as $settingKey) {
            $data['source_' . $settingKey] = 'board';
        }
    }
    $data['paid_attachment_download_publisher_reward_enabled'] = '0';
    $data['paid_attachment_download_publisher_reward_rate'] = '0';
    $data['source_paid_attachment_download_publisher_reward_enabled'] = 'board';
    $data['source_paid_attachment_download_publisher_reward_rate'] = 'board';

    return $data;
}

function seed_create_community_board(string $runKey, int $index, int $groupId, bool $freeBoard = false): void
{
    $payload = seed_community_board_payload($runKey, $index, $groupId, $freeBoard);
    seed_with_direct_fallback($freeBoard ? 'community_free_board' : 'community_boards', 'sr_community_boards', $index, static function () use ($payload): void {
        seed_post('/admin/community/boards/new', '/admin/community/boards/create', $payload);
    }, static function () use ($payload): void {
        seed_create_community_board_direct($payload);
    });
}

function seed_create_community_board_direct(array $payload): void
{
    global $pdo;

    if (sr_community_board_by_key($pdo, (string) $payload['board_key']) !== null) {
        return;
    }

    $boardId = sr_community_create_board($pdo, $payload);
    foreach (sr_community_board_group_all_setting_keys() as $settingKey) {
        if (array_key_exists($settingKey, $payload)) {
            sr_community_set_board_setting($pdo, $boardId, $settingKey, (string) $payload[$settingKey], seed_setting_value_type((string) $payload[$settingKey]));
        }
    }
}

function seed_create_community_post(string $runKey, int $index, array $literatureText): void
{
    global $pdo;

    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $bodyText = (string) ($literatureText['author'] ?? '')
        . ', '
        . (string) ($literatureText['title'] ?? '')
        . ' ('
        . (string) ($literatureText['year'] ?? '')
        . ") 원문 전문\n\n"
        . (string) ($literatureText['body_text'] ?? '')
        . "\n\n더미 게시글 {$n}: 실제 공개 글쓰기 경로로 만든 게시글입니다.";
    $payload = [
        'title' => (string) ($literatureText['title'] ?? '근대 문학') . ' 원문 ' . $n,
        'category_id' => '0',
        'body_text' => $bodyText,
        'series_mode' => 'none',
        'series_id' => '0',
        'new_series_title' => '',
        'series_episode_label' => '',
        'series_sort_order' => '0',
    ];

    seed_with_direct_fallback('community_posts', 'sr_community_posts', $index, static function () use ($runKey, $n, $payload): void {
        seed_post('/community/write?key=' . $runKey . 'board' . $n, '/community/write?key=' . $runKey . 'board' . $n, $payload);
    }, static function () use ($pdo, $runKey, $n, $payload): void {
        $board = sr_community_board_by_key($pdo, $runKey . 'board' . $n);
        $boardId = is_array($board) ? (int) $board['id'] : 0;
        if ($boardId < 1) {
            return;
        }
        sr_community_create_post($pdo, $boardId, seed_first_account_id($pdo), $payload);
    });
}

function seed_create_banner(string $runKey, int $index): void
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $payload = [
        'banner_id' => '0',
        'title' => 'QA 배너 ' . $n,
        'body_text' => '실제 배너 저장 경로로 만든 더미 배너입니다.',
        'link_url' => '/',
        'image_url' => '',
        'status' => 'enabled',
        'skin_key' => 'basic',
        'starts_at' => '',
        'ends_at' => '',
        'sort_order' => (string) ($index * 10),
        'target_option' => '__public__',
        'match_type' => 'all',
        'subject_id' => '',
    ];
    seed_with_direct_fallback('banners', 'sr_banners', $index, static function () use ($payload): void {
        seed_post('/admin/banners/new', '/admin/banners/save', $payload);
    }, static function () use ($payload): void {
        seed_create_banner_direct($payload);
    });
}

function seed_create_banner_direct(array $payload): void
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM sr_banners WHERE title = :title LIMIT 1');
    $stmt->execute(['title' => (string) $payload['title']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_banners
            (title, content_type, body_text, html_code, link_url, image_url, status, skin_key, starts_at, ends_at, sort_order, click_count, created_at, updated_at)
         VALUES
            (:title, \'text\', :body_text, NULL, :link_url, :image_url, :status, :skin_key, NULL, NULL, :sort_order, 0, :created_at, :updated_at)'
    );
    $stmt->execute([
        'title' => (string) $payload['title'],
        'body_text' => (string) $payload['body_text'],
        'link_url' => (string) $payload['link_url'],
        'image_url' => (string) $payload['image_url'],
        'status' => (string) $payload['status'],
        'skin_key' => (string) $payload['skin_key'],
        'sort_order' => (int) $payload['sort_order'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function seed_create_popup_layer(int $index): void
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $payload = [
        'popup_id' => '0',
        'title' => 'QA 팝업레이어 ' . $n,
        'body_text' => '실제 팝업레이어 저장 경로로 만든 더미 팝업입니다.',
        'status' => 'enabled',
        'skin_key' => 'basic',
        'starts_at' => '',
        'ends_at' => '',
        'dismiss_cookie_days' => '1',
        'target_option' => '__public__',
        'match_type' => 'all',
        'subject_id' => '',
    ];
    seed_with_direct_fallback('popup_layers', 'sr_popup_layers', $index, static function () use ($payload): void {
        seed_post('/admin/popup-layers/new', '/admin/popup-layers/save', $payload);
    }, static function () use ($payload): void {
        seed_create_popup_layer_direct($payload);
    });
}

function seed_create_popup_layer_direct(array $payload): void
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM sr_popup_layers WHERE title = :title LIMIT 1');
    $stmt->execute(['title' => (string) $payload['title']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_popup_layers
            (title, body_text, body_format, coupon_claim_campaign_key, status, skin_key, starts_at, ends_at, dismiss_cookie_days, created_at, updated_at)
         VALUES
            (:title, :body_text, \'plain\', \'\', :status, :skin_key, NULL, NULL, :dismiss_cookie_days, :created_at, :updated_at)'
    );
    $stmt->execute([
        'title' => (string) $payload['title'],
        'body_text' => (string) $payload['body_text'],
        'status' => (string) $payload['status'],
        'skin_key' => (string) $payload['skin_key'],
        'dismiss_cookie_days' => (int) $payload['dismiss_cookie_days'],
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function seed_create_coupon_definition(string $runKey, int $index): void
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $payload = [
        'intent' => 'create_definition',
        'coupon_key' => $runKey . 'coupon' . $n,
        'title' => 'QA 쿠폰 ' . $n,
        'description' => '실제 쿠폰 정의 생성 경로로 만든 더미 쿠폰입니다.',
        'status' => 'active',
        'coupon_type' => 'access',
        'target_type' => 'all',
        'target_id' => '',
        'refundable_policy' => 'none',
        'max_uses_per_issue' => '1',
    ];
    seed_with_direct_fallback('coupon_definitions', 'sr_coupon_definitions', $index, static function () use ($payload): void {
        seed_post('/admin/coupons', '/admin/coupons', $payload);
    }, static function () use ($payload): void {
        seed_create_coupon_definition_direct($payload);
    });
}

function seed_create_coupon_definition_direct(array $payload): void
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM sr_coupon_definitions WHERE coupon_key = :coupon_key LIMIT 1');
    $stmt->execute(['coupon_key' => (string) $payload['coupon_key']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    sr_coupon_create_definition($pdo, $payload);
}

function seed_create_notification(int $index): void
{
    $n = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
    $payload = [
        'audience' => 'all',
        'account_identifier' => '',
        'title' => 'QA 알림 ' . $n,
        'body_text' => '실제 알림 등록 경로로 만든 더미 사이트 알림입니다.',
        'body_format' => 'plain',
        'link_url' => '/',
        'channels' => ['site'],
    ];
    seed_with_direct_fallback('notifications', 'sr_notifications', $index, static function () use ($payload): void {
        seed_post('/admin/notifications/new', '/admin/notifications/create', $payload);
    }, static function () use ($payload): void {
        seed_create_notification_direct($payload);
    });
}

function seed_create_notification_direct(array $payload): void
{
    global $pdo;

    $stmt = $pdo->prepare('SELECT id FROM sr_notifications WHERE title = :title LIMIT 1');
    $stmt->execute(['title' => (string) $payload['title']]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    sr_notification_create($pdo, [
        'audience' => (string) $payload['audience'],
        'title' => (string) $payload['title'],
        'body_text' => (string) $payload['body_text'],
        'body_format' => (string) $payload['body_format'],
        'link_url' => (string) $payload['link_url'],
        'channels' => ['site'],
        'created_by_account_id' => seed_first_account_id($pdo),
    ]);
}

function seed_response_error_messages(string $body): array
{
    $messages = [];
    $patterns = [
        '/<div\b[^>]*class="[^"]*\badmin-flash-message-error\b[^"]*"[^>]*>.*?<\/div>/is',
        '/<div\b[^>]*class="[^"]*\balert-danger\b[^"]*"[^>]*>.*?<\/div>/is',
        '/<div\b[^>]*role="alert"[^>]*class="[^"]*\balert\b[^"]*"[^>]*>.*?<\/div>/is',
        '/<ul\b[^>]*class="[^"]*\bpublic-ui-feedback-error\b[^"]*"[^>]*>.*?<\/ul>/is',
        '/<main\b[^>]*>.*?<h1\b[^>]*>회원가입<\/h1>.*?(<ul\b[^>]*>.*?<\/ul>).*?<form\b/is',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $body, $matches) === false) {
            continue;
        }
        $errorHtmlList = isset($matches[1]) && $matches[1] !== [] ? $matches[1] : $matches[0];
        foreach ($errorHtmlList as $errorHtml) {
            $message = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $errorHtml), ENT_QUOTES, 'UTF-8')) ?? '');
            if ($message !== '') {
                $messages[] = $message;
            }
        }
    }

    return array_values(array_unique($messages));
}

function seed_reset_session(): void
{
    global $cookieFile;

    if (is_file($cookieFile)) {
        @unlink($cookieFile);
    }
    @touch($cookieFile);
}

function seed_ensure_created(PDO $pdo, string $label, string $table, int $before, int $expectedIncrease): void
{
    $after = seed_count($pdo, $table);
    $increase = $after - $before;
    if ($increase < $expectedIncrease) {
        throw new RuntimeException($label . ' creation incomplete: expected +' . $expectedIncrease . ', got +' . $increase);
    }

    echo $label . "\t" . $before . " -> " . $after . "\n";
}

function seed_table_exists(PDO $pdo, string $table): bool
{
    if (preg_match('/\Asr_[a-z0-9_]+\z/', $table) !== 1) {
        return false;
    }

    try {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function seed_first_account_id(PDO $pdo): int
{
    if (!seed_table_exists($pdo, 'sr_member_accounts')) {
        return 0;
    }

    return (int) $pdo->query("SELECT id FROM sr_member_accounts WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetchColumn();
}

function seed_rows_by_prefix(PDO $pdo, string $table, string $keyColumn, string $prefix, int $limit): array
{
    if (!seed_table_exists($pdo, $table) || preg_match('/\A[a-z0-9_]+\z/', $keyColumn) !== 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT * FROM ' . $table . ' WHERE ' . $keyColumn . ' LIKE :prefix ORDER BY id ASC LIMIT ' . (string) max(1, $limit)
    );
    $stmt->execute(['prefix' => $prefix . '%']);
    return $stmt->fetchAll();
}

function seed_dummy_storage_file(string $storageKey, string $body): array
{
    if (!sr_storage_key_is_safe($storageKey)) {
        throw new RuntimeException('Dummy storage key is invalid: ' . $storageKey);
    }

    $tempPath = tempnam(sys_get_temp_dir(), 'sr-seed-file-');
    if (!is_string($tempPath)) {
        throw new RuntimeException('Could not create dummy storage temp file.');
    }

    if (file_put_contents($tempPath, $body) === false) {
        @unlink($tempPath);
        throw new RuntimeException('Could not write dummy storage temp file.');
    }

    try {
        $stored = sr_storage_local_put_file($tempPath, $storageKey, ['overwrite' => true]);
    } finally {
        @unlink($tempPath);
    }

    return [
        'path' => 'storage/' . $storageKey,
        'key' => $storageKey,
        'bytes' => strlen($body),
        'checksum' => hash('sha256', $body),
    ];
}

function seed_content_download_fixtures(PDO $pdo, string $runKey, int $accountId): int
{
    if (!seed_table_exists($pdo, 'sr_content_files') || !seed_table_exists($pdo, 'sr_content_file_links')) {
        return 0;
    }

    $items = seed_rows_by_prefix($pdo, 'sr_content_items', 'slug', $runKey . '-content-', 8);
    if ($items === []) {
        return 0;
    }

    $created = 0;
    $fileStmt = $pdo->prepare(
        'INSERT INTO sr_content_files
            (content_id, title, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256,
             status, asset_download_enabled, asset_module, asset_download_amount, asset_download_settlement_currency, asset_download_amounts_json,
             asset_download_group_policies_json, asset_download_policy_set_id, asset_charge_policy, created_by, created_at, updated_at)
         VALUES
            (:content_id, :title, :original_name, :stored_name, :storage_path, \'local\', :storage_key, :mime_type, :size_bytes, :checksum_sha256,
             \'active\', :asset_download_enabled, :asset_module, :asset_download_amount, \'KRW\', NULL, NULL, 0, :asset_charge_policy, :created_by, :created_at, :updated_at)'
    );
    $linkStmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_content_file_links
            (content_id, file_id, sort_order, status, created_at, updated_at)
         VALUES
            (:content_id, :file_id, :sort_order, \'active\', :created_at, :updated_at)'
    );
    $existsStmt = $pdo->prepare('SELECT id FROM sr_content_files WHERE original_name = :original_name LIMIT 1');
    $now = sr_now();
    $variants = [
        ['suffix' => 'free-guide.txt', 'title' => '무료 안내문', 'mime' => 'text/plain', 'module' => '', 'amount' => 0, 'policy' => 'once'],
        ['suffix' => 'point-pack.csv', 'title' => '포인트 차감 CSV', 'mime' => 'text/csv', 'module' => 'point', 'amount' => 25, 'policy' => 'once'],
        ['suffix' => 'reward-archive.zip', 'title' => '리워드 차감 ZIP', 'mime' => 'application/zip', 'module' => 'reward', 'amount' => 40, 'policy' => 'every_download'],
    ];

    foreach ($items as $itemIndex => $item) {
        foreach ($variants as $variantIndex => $variant) {
            $contentId = (int) ($item['id'] ?? 0);
            if ($contentId < 1) {
                continue;
            }

            $originalName = $runKey . '-content-' . (string) $contentId . '-' . (string) $variant['suffix'];
            $existsStmt->execute(['original_name' => $originalName]);
            $existingFileId = (int) $existsStmt->fetchColumn();
            if ($existingFileId > 0) {
                $linkStmt->execute([
                    'content_id' => $contentId,
                    'file_id' => $existingFileId,
                    'sort_order' => ($itemIndex * 10) + $variantIndex,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            $storageKey = 'content/downloads/' . $runKey . '/' . $originalName;
            $stored = seed_dummy_storage_file(
                $storageKey,
                "saanraan QA dummy download\nrun={$runKey}\ncontent_id={$contentId}\nvariant=" . (string) $variant['title'] . "\n"
            );
            $fileStmt->execute([
                'content_id' => $contentId,
                'title' => 'QA ' . (string) $variant['title'],
                'original_name' => $originalName,
                'stored_name' => basename($storageKey),
                'storage_path' => (string) $stored['path'],
                'storage_key' => (string) $stored['key'],
                'mime_type' => (string) $variant['mime'],
                'size_bytes' => (int) $stored['bytes'],
                'checksum_sha256' => (string) $stored['checksum'],
                'asset_download_enabled' => (string) $variant['module'] !== '' ? 1 : 0,
                'asset_module' => (string) $variant['module'],
                'asset_download_amount' => (int) $variant['amount'],
                'asset_charge_policy' => (string) $variant['policy'],
                'created_by' => $accountId > 0 ? $accountId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $fileId = (int) $pdo->lastInsertId();
            $linkStmt->execute([
                'content_id' => $contentId,
                'file_id' => $fileId,
                'sort_order' => ($itemIndex * 10) + $variantIndex,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $created++;
        }
    }

    $contentVariants = [
        ['asset_access_enabled' => 1, 'asset_module' => 'point', 'asset_access_amount' => 30, 'asset_charge_policy' => 'once', 'asset_action_enabled' => 0],
        ['asset_access_enabled' => 1, 'asset_module' => 'reward', 'asset_access_amount' => 45, 'asset_charge_policy' => 'every_view', 'asset_action_enabled' => 0],
        ['asset_access_enabled' => 0, 'asset_module' => '', 'asset_access_amount' => 0, 'asset_charge_policy' => 'once', 'asset_action_enabled' => 1, 'asset_action_module' => 'point', 'asset_action_amount' => 15, 'asset_action_direction' => 'grant', 'asset_action_label' => '학습 완료'],
        ['asset_access_enabled' => 0, 'asset_module' => '', 'asset_access_amount' => 0, 'asset_charge_policy' => 'once', 'asset_action_enabled' => 1, 'asset_action_module' => 'reward', 'asset_action_amount' => 10, 'asset_action_direction' => 'use', 'asset_action_label' => '자료 신청'],
    ];
    $updateStmt = $pdo->prepare(
        'UPDATE sr_content_items
         SET asset_access_enabled = :asset_access_enabled,
             asset_module = :asset_module,
             asset_access_amount = :asset_access_amount,
             asset_charge_policy = :asset_charge_policy,
             asset_action_enabled = :asset_action_enabled,
             asset_action_module = :asset_action_module,
             asset_action_amount = :asset_action_amount,
             asset_action_direction = :asset_action_direction,
             asset_action_label = :asset_action_label,
             updated_at = :updated_at
         WHERE id = :id'
    );
    foreach ($items as $index => $item) {
        $variant = $contentVariants[$index % count($contentVariants)];
        $updateStmt->execute([
            'asset_access_enabled' => (int) ($variant['asset_access_enabled'] ?? 0),
            'asset_module' => (string) ($variant['asset_module'] ?? ''),
            'asset_access_amount' => (int) ($variant['asset_access_amount'] ?? 0),
            'asset_charge_policy' => (string) ($variant['asset_charge_policy'] ?? 'once'),
            'asset_action_enabled' => (int) ($variant['asset_action_enabled'] ?? 0),
            'asset_action_module' => (string) ($variant['asset_action_module'] ?? ''),
            'asset_action_amount' => (int) ($variant['asset_action_amount'] ?? 0),
            'asset_action_direction' => (string) ($variant['asset_action_direction'] ?? 'grant'),
            'asset_action_label' => (string) ($variant['asset_action_label'] ?? '완료'),
            'updated_at' => $now,
            'id' => (int) ($item['id'] ?? 0),
        ]);
    }

    return $created;
}

function seed_community_download_fixtures(PDO $pdo, string $runKey, int $accountId): int
{
    if ($accountId < 1 || !seed_table_exists($pdo, 'sr_community_attachments')) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'SELECT p.*, b.board_key
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE b.board_key LIKE :prefix
         ORDER BY p.id ASC
         LIMIT 8'
    );
    $stmt->execute(['prefix' => $runKey . 'board%']);
    $posts = $stmt->fetchAll();
    if ($posts === []) {
        return 0;
    }

    $created = 0;
    $now = sr_now();
    $existsStmt = $pdo->prepare('SELECT id FROM sr_community_attachments WHERE original_name = :original_name LIMIT 1');
    $insertStmt = $pdo->prepare(
        'INSERT INTO sr_community_attachments
            (post_id, uploader_account_id, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type,
             size_bytes, checksum_sha256, width, height, status, created_at)
         VALUES
            (:post_id, :uploader_account_id, :original_name, :stored_name, :storage_path, \'local\', :storage_key, :mime_type,
             :size_bytes, :checksum_sha256, NULL, NULL, \'active\', :created_at)'
    );
    $boardSettingStmt = $pdo->prepare(
        'INSERT INTO sr_community_board_settings
            (board_id, setting_key, setting_value, value_type, created_at, updated_at)
         VALUES
            (:board_id, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), value_type = VALUES(value_type), updated_at = VALUES(updated_at)'
    );
    $sourceStmt = $pdo->prepare(
        'INSERT INTO sr_community_board_setting_sources
            (board_id, setting_key, source, created_at, updated_at)
         VALUES
            (:board_id, :setting_key, \'board\', :created_at, :updated_at)
         ON DUPLICATE KEY UPDATE source = VALUES(source), updated_at = VALUES(updated_at)'
    );

    foreach ($posts as $index => $post) {
        $postId = (int) ($post['id'] ?? 0);
        $boardId = (int) ($post['board_id'] ?? 0);
        if ($postId < 1 || $boardId < 1) {
            continue;
        }

        $downloadEnabled = $index % 2 === 1;
        $settings = [
            'paid_attachment_download_enabled' => [$downloadEnabled ? '1' : '0', 'bool'],
            'paid_attachment_download_asset_module' => [$index % 3 === 0 ? 'reward' : 'point', 'string'],
            'paid_attachment_download_amount' => [(string) (20 + ($index * 5)), 'int'],
            'paid_attachment_download_charge_policy' => [$index % 3 === 0 ? 'every_download' : 'once', 'string'],
            'paid_attachment_download_publisher_reward_enabled' => [$index % 3 === 0 ? '1' : '0', 'bool'],
            'paid_attachment_download_publisher_reward_rate' => [$index % 3 === 0 ? '30' : '0', 'int'],
        ];
        foreach ($settings as $settingKey => $setting) {
            $boardSettingStmt->execute([
                'board_id' => $boardId,
                'setting_key' => $settingKey,
                'setting_value' => $setting[0],
                'value_type' => $setting[1],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sourceStmt->execute([
                'board_id' => $boardId,
                'setting_key' => $settingKey,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $originalName = $runKey . '-community-post-' . (string) $postId . '-download.txt';
        $existsStmt->execute(['original_name' => $originalName]);
        if ((int) $existsStmt->fetchColumn() > 0) {
            continue;
        }

        $storageKey = 'community/attachments/' . $runKey . '/' . $originalName;
        $stored = seed_dummy_storage_file(
            $storageKey,
            "saanraan QA community attachment\nrun={$runKey}\npost_id={$postId}\npaid_download=" . ($downloadEnabled ? 'yes' : 'no') . "\n"
        );
        $insertStmt->execute([
            'post_id' => $postId,
            'uploader_account_id' => $accountId,
            'original_name' => $originalName,
            'stored_name' => basename($storageKey),
            'storage_path' => (string) $stored['path'],
            'storage_key' => (string) $stored['key'],
            'mime_type' => 'text/plain',
            'size_bytes' => (int) $stored['bytes'],
            'checksum_sha256' => (string) $stored['checksum'],
            'created_at' => $now,
        ]);
        $created++;
    }

    return $created;
}

function seed_quiz_fixtures(PDO $pdo, string $runKey, int $accountId): int
{
    if (!seed_table_exists($pdo, 'sr_quiz_sets')) {
        return 0;
    }

    $contentIds = array_map('intval', array_column(seed_rows_by_prefix($pdo, 'sr_content_items', 'slug', $runKey . '-content-', 4), 'id'));
    $postStmt = $pdo->prepare(
        'SELECT p.id
         FROM sr_community_posts p
         INNER JOIN sr_community_boards b ON b.id = p.board_id
         WHERE b.board_key LIKE :prefix
         ORDER BY p.id ASC
         LIMIT 4'
    );
    $postStmt->execute(['prefix' => $runKey . 'board%']);
    $postIds = array_map('intval', array_column($postStmt->fetchAll(), 'id'));
    $created = 0;
    $templates = [
        [
            'key' => 'scored_free',
            'title' => 'QA 점수형 무료 퀴즈',
            'mode' => 'scored',
            'model' => 'correct_answer',
            'pass_score' => 2,
            'reward' => false,
            'dedupe' => 'per_quiz',
        ],
        [
            'key' => 'scored_point_reward',
            'title' => 'QA 통과 포인트 보상 퀴즈',
            'mode' => 'scored',
            'model' => 'correct_answer',
            'pass_score' => 2,
            'reward' => true,
            'module' => 'point',
            'amount' => 35,
            'dedupe' => 'per_quiz',
        ],
        [
            'key' => 'personality_reward',
            'title' => 'QA 유형 결과 리워드 퀴즈',
            'mode' => 'personality',
            'model' => 'category_weight',
            'pass_score' => '',
            'reward' => true,
            'module' => 'reward',
            'amount' => 20,
            'dedupe' => 'per_attempt',
        ],
    ];

    foreach ($templates as $index => $template) {
        $quizKey = $runKey . '_quiz_' . (string) $template['key'];
        if (sr_quiz_key_exists($pdo, $quizKey)) {
            continue;
        }

        sr_quiz_save_admin_quiz($pdo, [
            'id' => 0,
            'quiz_key' => $quizKey,
            'title' => (string) $template['title'],
            'description' => 'QA 시더가 만든 더미 퀴즈입니다. 정답형, 유형형, 보상 유무를 함께 검수합니다.',
            'cover_image_url' => '',
            'skin_key' => '',
            'status' => 'active',
            'quiz_mode' => (string) $template['mode'],
            'scoring_model' => (string) $template['model'],
            'pass_score' => (string) $template['pass_score'],
            'starts_at' => '',
            'ends_at' => '',
            'attempt_limit_policy' => $index === 2 ? 'per_period' : 'unlimited',
            'attempt_limit_period_seconds' => '86400',
            'member_group_keys' => [],
            'comments_enabled' => 1,
            'secret_comments_enabled' => 1,
            'reaction_preset_key' => '',
            'reaction_comment_preset_key' => '',
            'reward_enabled' => !empty($template['reward']) ? 1 : 0,
            'reward_provider' => 'ledger_asset',
            'reward_module' => (string) ($template['module'] ?? 'point'),
            'reward_coupon_definition_id' => '',
            'reward_amount' => (string) ($template['amount'] ?? 0),
            'reward_dedupe_scope' => (string) $template['dedupe'],
            'questions' => [
                [
                    'question_key' => 'first_choice',
                    'question_type' => 'single_choice',
                    'prompt' => '테스트 화면에서 가장 먼저 확인할 것은 무엇인가요?',
                    'score_value' => 1,
                    'choices' => [
                        ['choice_key' => 'flow', 'label' => '요청 흐름', 'is_correct' => 1, 'category_key' => 'flow', 'category_weight' => 2],
                        ['choice_key' => 'color', 'label' => '색상 이름', 'is_correct' => 0, 'category_key' => 'style', 'category_weight' => 1],
                    ],
                ],
                [
                    'question_key' => 'second_choice',
                    'question_type' => 'single_choice',
                    'prompt' => '보상 퀴즈 검수에서 필요한 케이스는?',
                    'score_value' => 1,
                    'choices' => [
                        ['choice_key' => 'dedupe', 'label' => '중복 지급 기준', 'is_correct' => 1, 'category_key' => 'reward', 'category_weight' => 2],
                        ['choice_key' => 'memo', 'label' => '관리 메모 길이만 확인', 'is_correct' => 0, 'category_key' => 'flow', 'category_weight' => 1],
                    ],
                ],
            ],
            'result_rules' => "pass|통과|2|2|||QA 통과 결과\nreview|재검토|0|1|||QA 재시도 결과\nreward|보상형|||reward|2|보상 흐름을 확인합니다.",
            'content_source_ids' => implode(',', array_slice($contentIds, 0, 2)),
            'community_source_ids' => implode(',', array_slice($postIds, 0, 2)),
        ], $accountId);
        $created++;
    }

    return $created;
}

function seed_survey_fixtures(PDO $pdo, string $runKey, int $accountId): int
{
    if (!seed_table_exists($pdo, 'sr_survey_forms')) {
        return 0;
    }

    $created = 0;
    $now = sr_now();
    $insertStmt = $pdo->prepare(
        'INSERT INTO sr_survey_forms
            (survey_key, title, description, cover_image_url, skin_key, research_purpose, target_population, recruitment_method, estimated_minutes,
             project_brief, sponsor_name, research_region, research_language, fieldwork_method, sample_frame, sample_method, target_sample_size,
             quota_policy, response_rate_basis, analysis_plan, weighting_policy, margin_error_note, methodology_disclosure, ethics_note,
             sensitive_data_policy, recontact_policy, withdrawal_policy, vendor_name, external_channel_policy, invite_token_policy,
             qa_status, qa_note, questionnaire_version, revision_locked,
             organizer_name, contact_text, consent_required, consent_text, privacy_notice, anonymous_allowed, login_required,
             public_listed, robots_policy, status, starts_at, ends_at, response_limit_policy, response_limit_period_seconds, member_group_keys_json,
             comments_enabled, secret_comments_enabled, reaction_preset_key, reaction_comment_preset_key, reward_enabled,
             created_by_account_id, updated_by_account_id, created_at, updated_at)
         VALUES
            (:survey_key, :title, :description, \'\', \'\', :research_purpose, :target_population, :recruitment_method, :estimated_minutes,
             :project_brief, :sponsor_name, \'KR\', \'ko\', :fieldwork_method, :sample_frame, :sample_method, :target_sample_size,
             :quota_policy, :response_rate_basis, :analysis_plan, :weighting_policy, :margin_error_note, :methodology_disclosure, :ethics_note,
             :sensitive_data_policy, :recontact_policy, :withdrawal_policy, :vendor_name, :external_channel_policy, :invite_token_policy,
             :qa_status, :qa_note, 1, :revision_locked,
             :organizer_name, :contact_text, :consent_required, :consent_text, :privacy_notice, :anonymous_allowed, :login_required,
             1, \'auto\', \'active\', NULL, NULL, :response_limit_policy, :response_limit_period_seconds, \'[]\',
             1, 1, \'\', \'\', :reward_enabled,
             :created_by_account_id, :updated_by_account_id, :created_at, :updated_at)'
    );
    $templates = [
        [
            'key' => 'public_no_reward',
            'title' => 'QA 공개 만족도 설문',
            'reward' => false,
            'login_required' => 0,
            'anonymous_allowed' => 1,
            'consent_required' => 0,
            'limit' => 'unlimited',
            'amount' => 0,
            'module' => 'point',
        ],
        [
            'key' => 'member_point_reward',
            'title' => 'QA 회원 포인트 보상 설문',
            'reward' => true,
            'login_required' => 1,
            'anonymous_allowed' => 0,
            'consent_required' => 1,
            'limit' => 'per_survey_once',
            'amount' => 50,
            'module' => 'point',
        ],
        [
            'key' => 'research_reward',
            'title' => 'QA 리워드 지급 연구 설문',
            'reward' => true,
            'login_required' => 1,
            'anonymous_allowed' => 0,
            'consent_required' => 1,
            'limit' => 'per_period',
            'amount' => 80,
            'module' => 'reward',
        ],
    ];

    foreach ($templates as $index => $template) {
        $surveyKey = $runKey . '_survey_' . (string) $template['key'];
        $existsStmt = $pdo->prepare('SELECT id FROM sr_survey_forms WHERE survey_key = :survey_key LIMIT 1');
        $existsStmt->execute(['survey_key' => $surveyKey]);
        if ((int) $existsStmt->fetchColumn() > 0) {
            continue;
        }

        $insertStmt->execute([
            'survey_key' => $surveyKey,
            'title' => (string) $template['title'],
            'description' => 'QA 시더가 만든 더미 설문입니다. 공개/회원/보상/연구 메타데이터를 함께 검수합니다.',
            'research_purpose' => '테스트 데이터가 통계, 응답 목록, CSV export에서 어떻게 보이는지 확인합니다.',
            'target_population' => 'QA 검수자 및 내부 테스트 회원',
            'recruitment_method' => '더미 링크와 회원 화면을 통한 임의 모집',
            'estimated_minutes' => 4 + $index,
            'project_brief' => '더미 프로젝트 개요입니다.',
            'sponsor_name' => 'saanraan QA',
            'fieldwork_method' => '온라인 자기기입식',
            'sample_frame' => '로컬 또는 스테이징 테스트 회원',
            'sample_method' => '편의 표본',
            'target_sample_size' => 120 + ($index * 40),
            'quota_policy' => '성별/연령 quota가 없는 기본 더미입니다.',
            'response_rate_basis' => '테스트 응답 수 기준',
            'analysis_plan' => '문항별 빈도와 간단한 평균을 확인합니다.',
            'weighting_policy' => '가중치 없음',
            'margin_error_note' => '비확률 더미 표본이므로 표본오차를 산출하지 않습니다.',
            'methodology_disclosure' => 'QA 더미 데이터로 실제 조사 결과가 아닙니다.',
            'ethics_note' => '민감정보 없이 테스트합니다.',
            'sensitive_data_policy' => '민감정보 수집 안함',
            'recontact_policy' => '재접촉 없음',
            'withdrawal_policy' => '테스트 응답은 운영자가 삭제할 수 있습니다.',
            'vendor_name' => '',
            'external_channel_policy' => '',
            'invite_token_policy' => '',
            'qa_status' => $index === 0 ? 'unchecked' : 'approved',
            'qa_note' => 'QA 시더 생성',
            'revision_locked' => $index === 2 ? 1 : 0,
            'organizer_name' => 'QA 운영자',
            'contact_text' => 'qa@example.test',
            'consent_required' => !empty($template['consent_required']) ? 1 : 0,
            'consent_text' => !empty($template['consent_required']) ? '더미 설문 참여 및 테스트 데이터 저장에 동의합니다.' : '',
            'privacy_notice' => '테스트 목적의 더미 응답만 입력하세요.',
            'anonymous_allowed' => !empty($template['anonymous_allowed']) ? 1 : 0,
            'login_required' => !empty($template['login_required']) ? 1 : 0,
            'response_limit_policy' => (string) $template['limit'],
            'response_limit_period_seconds' => (string) $template['limit'] === 'per_period' ? 86400 : null,
            'reward_enabled' => !empty($template['reward']) ? 1 : 0,
            'created_by_account_id' => $accountId > 0 ? $accountId : null,
            'updated_by_account_id' => $accountId > 0 ? $accountId : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $surveyId = (int) $pdo->lastInsertId();
        sr_survey_replace_questions($pdo, $surveyId, [
            [
                'question_key' => 'satisfaction',
                'question_type' => 'scale',
                'prompt' => '현재 테스트 화면의 전반적 만족도는 어느 정도인가요?',
                'analysis_note' => '5점 척도 평균 확인',
                'required' => 1,
                'min_choices' => null,
                'max_choices' => null,
                'scale_points' => 5,
                'scale_min_label' => '낮음',
                'scale_max_label' => '높음',
                'number_unit' => '',
                'number_min' => null,
                'number_max' => null,
                'allow_decimal' => 0,
                'allow_other' => 0,
                'nonresponse_policy' => 'none',
                'choices' => [],
            ],
            [
                'question_key' => 'features',
                'question_type' => 'multiple_choice',
                'prompt' => '이번 검수에서 함께 확인할 기능을 선택하세요.',
                'analysis_note' => '복수 선택/기타/무응답 선택지 확인',
                'required' => 1,
                'min_choices' => 1,
                'max_choices' => 3,
                'scale_points' => null,
                'scale_min_label' => '',
                'scale_max_label' => '',
                'number_unit' => '',
                'number_min' => null,
                'number_max' => null,
                'allow_decimal' => 0,
                'allow_other' => 1,
                'nonresponse_policy' => 'allow_na',
                'choices' => [
                    ['choice_key' => 'download', 'label' => '다운로드'],
                    ['choice_key' => 'reward', 'label' => '보상 지급'],
                    ['choice_key' => 'export', 'label' => 'CSV export'],
                ],
            ],
            [
                'question_key' => 'budget',
                'question_type' => 'number',
                'prompt' => '테스트용 예상 지급 금액을 입력하세요.',
                'analysis_note' => '숫자 문항 min/max 확인',
                'required' => 0,
                'min_choices' => null,
                'max_choices' => null,
                'scale_points' => null,
                'scale_min_label' => '',
                'scale_max_label' => '',
                'number_unit' => '원',
                'number_min' => 0,
                'number_max' => 100000,
                'allow_decimal' => 0,
                'allow_other' => 0,
                'nonresponse_policy' => 'none',
                'choices' => [],
            ],
            [
                'question_key' => 'comment',
                'question_type' => 'long_text',
                'prompt' => '화면에서 어색한 부분을 자유롭게 적어주세요.',
                'analysis_note' => '텍스트 응답 미리보기 확인',
                'required' => 0,
                'min_choices' => null,
                'max_choices' => null,
                'scale_points' => null,
                'scale_min_label' => '',
                'scale_max_label' => '',
                'number_unit' => '',
                'number_min' => null,
                'number_max' => null,
                'allow_decimal' => 0,
                'allow_other' => 0,
                'nonresponse_policy' => 'none',
                'choices' => [],
            ],
        ], $now);
        sr_survey_replace_reward_policy(
            $pdo,
            $surveyId,
            !empty($template['reward']),
            'ledger_asset',
            (string) $template['module'],
            0,
            (int) $template['amount'],
            $index === 2 ? 'per_response' : 'per_survey',
            $now
        );
        $created++;
    }

    return $created;
}

function seed_content_group_ids(PDO $pdo, string $runKey): array
{
    $stmt = $pdo->prepare("SELECT id FROM sr_content_groups WHERE group_key LIKE :prefix ORDER BY id ASC");
    $stmt->execute(['prefix' => $runKey . 'cg%']);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function seed_board_group_ids(PDO $pdo, string $runKey): array
{
    $stmt = $pdo->prepare("SELECT id FROM sr_community_board_groups WHERE group_key LIKE :prefix ORDER BY id ASC");
    $stmt->execute(['prefix' => $runKey . 'bg%']);
    return array_map('intval', array_column($stmt->fetchAll(), 'id'));
}

function seed_trim_rows(PDO $pdo, string $table, string $titleColumn, string $titlePrefix, int $keep, string $csrfPath, string $postPath, string $idField): void
{
    $stmt = $pdo->prepare(
        'SELECT id FROM ' . $table . ' WHERE ' . $titleColumn . ' LIKE :prefix ORDER BY id ASC'
    );
    $stmt->execute(['prefix' => $titlePrefix . '%']);
    $ids = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    if (count($ids) <= $keep) {
        echo $table . "\ttrim skipped\n";
        return;
    }

    $deleteIds = array_slice($ids, $keep);
    foreach ($deleteIds as $id) {
        seed_post($csrfPath, $postPath, [$idField => (string) $id]);
    }
    echo $table . "\ttrimmed " . count($deleteIds) . "\n";
}

echo "run_key\t" . $runKey . "\n";
echo "base_url\t" . $baseUrl . "\n";

if (!$skipMembers) {
    $beforeMembers = seed_count($pdo, 'sr_member_accounts');
    $publicRegistrationError = '';
    for ($i = 1; $i <= $count; $i++) {
        try {
            seed_register_member($runKey, $i);
        } catch (RuntimeException $exception) {
            $publicRegistrationError = $exception->getMessage();
            break;
        }
        seed_reset_session();
    }

    $afterPublicMembers = seed_count($pdo, 'sr_member_accounts');
    $publicIncrease = $afterPublicMembers - $beforeMembers;
    if ($publicIncrease < $count) {
        if ($publicRegistrationError !== '') {
            echo "members\tpublic registration fallback: " . $publicRegistrationError . "\n";
        } else {
            echo "members\tpublic registration fallback: expected +" . $count . ', got +' . $publicIncrease . "\n";
        }

        seed_reset_session();
        seed_admin_login();
        for ($i = $publicIncrease + 1; $i <= $count; $i++) {
            seed_admin_create_member($runKey, $i);
        }
    }

    seed_ensure_created($pdo, 'members', 'sr_member_accounts', $beforeMembers, $count);
} else {
    seed_reset_session();
    echo "members\tskipped\n";
}

seed_admin_login();

if ($ensureFreeBoard) {
    $stmt = $pdo->prepare('SELECT id FROM sr_community_boards WHERE board_key = :board_key LIMIT 1');
    $stmt->execute(['board_key' => 'free']);
    if (!is_array($stmt->fetch())) {
        $beforeFreeBoard = seed_count($pdo, 'sr_community_boards');
        seed_create_community_board($runKey, 0, 0, true);
        seed_ensure_created($pdo, 'community_free_board', 'sr_community_boards', $beforeFreeBoard, 1);
    } else {
        echo "community_free_board\texists\n";
    }
}

if ($trimDisplay) {
    seed_trim_rows($pdo, 'sr_banners', 'title', 'QA 배너', 12, '/admin/banners', '/admin/banners/delete', 'banner_id');
    seed_trim_rows($pdo, 'sr_popup_layers', 'title', 'QA 팝업레이어', 12, '/admin/popup-layers', '/admin/popup-layers/delete', 'popup_id');
}

if (!$skipFoundation) {
    $beforeMemberGroups = seed_count($pdo, 'sr_member_groups');
    for ($i = 1; $i <= $count; $i++) {
        seed_create_member_group($runKey, $i);
    }
    seed_ensure_created($pdo, 'member_groups', 'sr_member_groups', $beforeMemberGroups, $count);
} else {
    echo "member_groups\tskipped\n";
}

if (!$skipFoundation) {
    $beforeContentGroups = seed_count($pdo, 'sr_content_groups');
    for ($i = 1; $i <= $count; $i++) {
        seed_create_content_group($runKey, $i);
    }
    seed_ensure_created($pdo, 'content_groups', 'sr_content_groups', $beforeContentGroups, $count);
} else {
    echo "content_groups\tskipped\n";
}

$contentGroupIds = seed_content_group_ids($pdo, $runKey);
$contentDisplayFields = array_keys(sr_content_public_display_setting_labels());
if (!$skipFoundation) {
    $beforeContent = seed_count($pdo, 'sr_content_items');
    for ($i = 1; $i <= $count; $i++) {
        $groupId = $contentGroupIds[($i - 1) % max(1, count($contentGroupIds))] ?? 0;
        seed_create_content_item($runKey, $i, (int) $groupId);
    }
    seed_ensure_created($pdo, 'content_items', 'sr_content_items', $beforeContent, $count);
} else {
    echo "content_items\tskipped\n";
}

if (!$skipCommunity) {
    $beforeBoardGroups = seed_count($pdo, 'sr_community_board_groups');
    for ($i = 1; $i <= $count; $i++) {
        seed_create_community_board_group($runKey, $i);
    }
    seed_ensure_created($pdo, 'community_board_groups', 'sr_community_board_groups', $beforeBoardGroups, $count);
} else {
    echo "community_board_groups\tskipped\n";
}

$boardGroupIds = seed_board_group_ids($pdo, $runKey);
if (!$skipCommunity) {
    $beforeBoards = seed_count($pdo, 'sr_community_boards');
    for ($i = 1; $i <= $count; $i++) {
        $groupId = $boardGroupIds[($i - 1) % max(1, count($boardGroupIds))] ?? 0;
        seed_create_community_board($runKey, $i, (int) $groupId);
    }
    seed_ensure_created($pdo, 'community_boards', 'sr_community_boards', $beforeBoards, $count);
} else {
    echo "community_boards\tskipped\n";
}

if (!$skipCommunity) {
    $beforePosts = seed_count($pdo, 'sr_community_posts');
    $communityLiteratureTexts = require SR_ROOT . '/.tools/fixtures/community-literature-texts.php';
    for ($i = 1; $i <= $count; $i++) {
        $literatureText = $communityLiteratureTexts[($i - 1) % count($communityLiteratureTexts)];
        seed_create_community_post($runKey, $i, $literatureText);
    }
    seed_ensure_created($pdo, 'community_posts', 'sr_community_posts', $beforePosts, $count);
} else {
    echo "community_posts\tskipped\n";
}

if (!$skipRichFixtures) {
    $fixtureAccountId = seed_first_account_id($pdo);

    $beforeContentFiles = seed_table_exists($pdo, 'sr_content_files') ? seed_count($pdo, 'sr_content_files') : 0;
    $createdContentFiles = seed_content_download_fixtures($pdo, $runKey, $fixtureAccountId);
    echo "content_download_fixtures\t" . $beforeContentFiles . " -> " . (seed_table_exists($pdo, 'sr_content_files') ? seed_count($pdo, 'sr_content_files') : $beforeContentFiles) . " (created " . $createdContentFiles . ")\n";

    $beforeCommunityAttachments = seed_table_exists($pdo, 'sr_community_attachments') ? seed_count($pdo, 'sr_community_attachments') : 0;
    $createdCommunityAttachments = seed_community_download_fixtures($pdo, $runKey, $fixtureAccountId);
    echo "community_download_fixtures\t" . $beforeCommunityAttachments . " -> " . (seed_table_exists($pdo, 'sr_community_attachments') ? seed_count($pdo, 'sr_community_attachments') : $beforeCommunityAttachments) . " (created " . $createdCommunityAttachments . ")\n";

    $beforeQuizzes = seed_table_exists($pdo, 'sr_quiz_sets') ? seed_count($pdo, 'sr_quiz_sets') : 0;
    $createdQuizzes = seed_quiz_fixtures($pdo, $runKey, $fixtureAccountId);
    echo "quiz_fixtures\t" . $beforeQuizzes . " -> " . (seed_table_exists($pdo, 'sr_quiz_sets') ? seed_count($pdo, 'sr_quiz_sets') : $beforeQuizzes) . " (created " . $createdQuizzes . ")\n";

    $beforeSurveys = seed_table_exists($pdo, 'sr_survey_forms') ? seed_count($pdo, 'sr_survey_forms') : 0;
    $createdSurveys = seed_survey_fixtures($pdo, $runKey, $fixtureAccountId);
    echo "survey_fixtures\t" . $beforeSurveys . " -> " . (seed_table_exists($pdo, 'sr_survey_forms') ? seed_count($pdo, 'sr_survey_forms') : $beforeSurveys) . " (created " . $createdSurveys . ")\n";
} else {
    echo "rich_fixtures\tskipped\n";
}

if (!$skipOperations) {
    $beforeBanners = seed_count($pdo, 'sr_banners');
    for ($i = 1; $i <= $count; $i++) {
        seed_create_banner($runKey, $i);
    }
    seed_ensure_created($pdo, 'banners', 'sr_banners', $beforeBanners, $count);
} else {
    echo "banners\tskipped\n";
}

if (!$skipOperations) {
    $beforePopups = seed_count($pdo, 'sr_popup_layers');
    for ($i = 1; $i <= $count; $i++) {
        seed_create_popup_layer($i);
    }
    seed_ensure_created($pdo, 'popup_layers', 'sr_popup_layers', $beforePopups, $count);
} else {
    echo "popup_layers\tskipped\n";
}

if (!$skipOperations) {
    $beforeCoupons = seed_count($pdo, 'sr_coupon_definitions');
    for ($i = 1; $i <= $count; $i++) {
        seed_create_coupon_definition($runKey, $i);
    }
    seed_ensure_created($pdo, 'coupon_definitions', 'sr_coupon_definitions', $beforeCoupons, $count);
} else {
    echo "coupon_definitions\tskipped\n";
}

if (!$skipOperations) {
    $beforeNotifications = seed_count($pdo, 'sr_notifications');
    for ($i = 1; $i <= $count; $i++) {
        seed_create_notification($i);
    }
    seed_ensure_created($pdo, 'notifications', 'sr_notifications', $beforeNotifications, $count);
} else {
    echo "notifications\tskipped\n";
}

echo "done\n";
