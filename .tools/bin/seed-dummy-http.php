<?php

declare(strict_types=1);

define('SR_ROOT', dirname(__DIR__, 2));

require SR_ROOT . '/core/helpers.php';
require_once SR_ROOT . '/modules/content/helpers.php';
require_once SR_ROOT . '/modules/community/helpers.php';

$baseUrl = rtrim((string) ($argv[1] ?? getenv('SR_SEED_BASE_URL') ?: ''), '/');
$adminIdentifier = (string) ($argv[2] ?? getenv('SR_SEED_ADMIN_IDENTIFIER') ?: 'admin');
$adminPassword = (string) ($argv[3] ?? getenv('SR_SEED_ADMIN_PASSWORD') ?: '');
$count = (int) ($argv[4] ?? getenv('SR_SEED_COUNT') ?: 10);
$skipMembers = in_array(strtolower((string) getenv('SR_SEED_SKIP_MEMBERS')), ['1', 'true', 'yes', 'on'], true);
$skipFoundation = in_array(strtolower((string) getenv('SR_SEED_SKIP_FOUNDATION')), ['1', 'true', 'yes', 'on'], true);
$skipCommunity = in_array(strtolower((string) getenv('SR_SEED_SKIP_COMMUNITY')), ['1', 'true', 'yes', 'on'], true);
$skipOperations = in_array(strtolower((string) getenv('SR_SEED_SKIP_OPERATIONS')), ['1', 'true', 'yes', 'on'], true);
$ensureFreeBoard = in_array(strtolower((string) getenv('SR_SEED_ENSURE_FREE_BOARD')), ['1', 'true', 'yes', 'on'], true);
$trimDisplay = in_array(strtolower((string) getenv('SR_SEED_TRIM_DISPLAY')), ['1', 'true', 'yes', 'on'], true);
$allowMutation = getenv('SR_SEED_ALLOW_MUTATION') === '1';

if ($baseUrl === '' || $adminPassword === '') {
    fwrite(STDERR, "Usage: SR_SEED_ALLOW_MUTATION=1 php .tools/bin/seed-dummy-http.php <base-url> <admin-identifier> <admin-password> [count]\n");
    fwrite(STDERR, "Env: SR_SEED_ALLOW_MUTATION=1 SR_SEED_BASE_URL SR_SEED_ADMIN_IDENTIFIER SR_SEED_ADMIN_PASSWORD SR_SEED_COUNT SR_SEED_RUN_KEY\n");
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
    seed_http('POST', $postPath, $data);
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
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seed_post('/register', '/register', [
            'email' => $runKey . 'member' . $n . '@example.test',
            'login_id' => $runKey . 'm' . $n,
            'display_name' => 'QA회원' . $n,
            'nickname' => $runKey . 'nick' . $n,
            'password' => 'SaanraanQA1!',
            'password_confirm' => 'SaanraanQA1!',
            'terms_consent' => '1',
            'privacy_consent' => '1',
            'marketing_consent' => '1',
        ]);
        seed_reset_session();
    }
    seed_ensure_created($pdo, 'members', 'sr_member_accounts', $beforeMembers, $count);
} else {
    seed_reset_session();
    echo "members\tskipped\n";
}

$loginData = [
    'identifier' => $adminIdentifier,
    'password' => $adminPassword,
    'next' => '/admin',
    'csrf_token' => seed_csrf('/login'),
];
seed_http('POST', '/login', $loginData);
$adminPage = seed_http('GET', '/admin');
if (strpos((string) $adminPage['url'], '/login') !== false) {
    throw new RuntimeException('Admin login failed.');
}

if ($ensureFreeBoard) {
    $stmt = $pdo->prepare('SELECT id FROM sr_community_boards WHERE board_key = :board_key LIMIT 1');
    $stmt->execute(['board_key' => 'free']);
    if (!is_array($stmt->fetch())) {
        $data = [
            'board_key' => 'free',
            'title' => '자유 게시판',
            'description' => 'HTTP smoke와 기본 커뮤니티 검수를 위한 자유 게시판입니다.',
            'status' => 'enabled',
            'read_policy' => 'public',
            'write_policy' => 'member',
            'comment_policy' => 'member',
            'skin_key' => 'basic',
            'post_editor' => 'textarea',
            'sort_order' => '0',
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
            'board_group_id' => '0',
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
        $beforeFreeBoard = seed_count($pdo, 'sr_community_boards');
        seed_post('/admin/community/boards/new', '/admin/community/boards/create', $data);
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
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seed_post('/admin/member-groups', '/admin/member-groups', [
            'intent' => 'save_group',
            'group_key' => $runKey . 'mg' . $n,
            'title' => 'QA 회원그룹 ' . $n,
            'description' => 'HTTP 시더가 실제 관리자 경로로 만든 회원 그룹입니다.',
            'status' => 'enabled',
            'sort_order' => (string) ($i * 10),
        ]);
    }
    seed_ensure_created($pdo, 'member_groups', 'sr_member_groups', $beforeMemberGroups, $count);
} else {
    echo "member_groups\tskipped\n";
}

$contentDisplayFields = array_keys(sr_content_public_display_setting_labels());
if (!$skipFoundation) {
    $beforeContentGroups = seed_count($pdo, 'sr_content_groups');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $data = [
            'intent' => 'create_group',
            'group_key' => $runKey . 'cg' . $n,
            'title' => 'QA 콘텐츠 그룹 ' . $n,
            'description' => 'HTTP 시더가 실제 관리자 경로로 만든 콘텐츠 그룹입니다.',
            'status' => 'enabled',
            'sort_order' => (string) ($i * 10),
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
        foreach ($contentDisplayFields as $field) {
            $data['group_' . $field] = '0';
        }
        seed_post('/admin/content-groups/new', '/admin/content-groups', $data);
    }
    seed_ensure_created($pdo, 'content_groups', 'sr_content_groups', $beforeContentGroups, $count);
} else {
    echo "content_groups\tskipped\n";
}

$contentGroupIds = seed_content_group_ids($pdo, $runKey);
if (!$skipFoundation) {
    $beforeContent = seed_count($pdo, 'sr_content_items');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $groupId = $contentGroupIds[($i - 1) % max(1, count($contentGroupIds))] ?? 0;
        $data = [
            'content_id' => '0',
            'content_group_scope' => 'here_only',
            'content_group_id' => (string) $groupId,
            'source_status' => 'content',
            'source_layout_key' => 'content',
            'title' => 'QA 콘텐츠 ' . $n,
            'slug' => $runKey . '-content-' . $n,
            'summary' => '실제 관리자 콘텐츠 저장 경로로 만든 더미 콘텐츠입니다.',
            'body_text' => "QA 콘텐츠 본문 {$n}\n\n목록, 상세, 검색, SEO 검수에 사용할 문장입니다.",
            'body_format' => 'plain',
            'status' => 'published',
            'layout_key' => '',
            'asset_access_enabled' => '0',
            'asset_access_amount' => '0',
            'asset_charge_policy' => 'once',
            'asset_action_enabled' => '0',
            'asset_action_amount' => '0',
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
        ];
        foreach ($contentDisplayFields as $field) {
            $data[$field] = '0';
            $data['source_' . $field] = 'content';
        }
        seed_post('/admin/content/new', '/admin/content/save', $data);
    }
    seed_ensure_created($pdo, 'content_items', 'sr_content_items', $beforeContent, $count);
} else {
    echo "content_items\tskipped\n";
}

$communityDisplayFields = array_keys(sr_community_public_display_setting_labels());
if (!$skipCommunity) {
    $beforeBoardGroups = seed_count($pdo, 'sr_community_board_groups');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $data = [
            'intent' => 'create_group',
            'group_key' => $runKey . 'bg' . $n,
            'title' => 'QA 게시판 그룹 ' . $n,
            'description' => 'HTTP 시더가 실제 관리자 경로로 만든 게시판 그룹입니다.',
            'status' => 'enabled',
            'sort_order' => (string) ($i * 10),
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
        foreach ($communityDisplayFields as $field) {
            $data['group_' . $field] = '0';
        }
        foreach (sr_community_asset_setting_prefixes() as $assetPrefix) {
            $data['group_' . $assetPrefix . '_enabled'] = '0';
            $data['group_' . $assetPrefix . '_asset_module'] = '';
            $data['group_' . $assetPrefix . '_amount'] = '0';
        }
        seed_post('/admin/community/board-groups/new', '/admin/community/board-groups', $data);
    }
    seed_ensure_created($pdo, 'community_board_groups', 'sr_community_board_groups', $beforeBoardGroups, $count);
} else {
    echo "community_board_groups\tskipped\n";
}

$boardGroupIds = seed_board_group_ids($pdo, $runKey);
$boardDisplayFields = array_keys(sr_community_public_banner_setting_labels() + sr_community_public_popup_layer_setting_labels());
if (!$skipCommunity) {
    $beforeBoards = seed_count($pdo, 'sr_community_boards');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        $groupId = $boardGroupIds[($i - 1) % max(1, count($boardGroupIds))] ?? 0;
        $data = [
            'board_key' => $runKey . 'board' . $n,
            'title' => 'QA 게시판 ' . $n,
            'description' => 'HTTP 시더가 실제 관리자 경로로 만든 게시판입니다.',
            'status' => 'enabled',
            'read_policy' => 'public',
            'write_policy' => 'member',
            'comment_policy' => 'member',
            'skin_key' => 'basic',
            'post_editor' => 'textarea',
            'sort_order' => (string) ($i * 10),
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
        foreach ($boardDisplayFields as $field) {
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
        seed_post('/admin/community/boards/new', '/admin/community/boards/create', $data);
    }
    seed_ensure_created($pdo, 'community_boards', 'sr_community_boards', $beforeBoards, $count);
} else {
    echo "community_boards\tskipped\n";
}

if (!$skipCommunity) {
    $beforePosts = seed_count($pdo, 'sr_community_posts');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seed_post('/community/write?key=' . $runKey . 'board' . $n, '/community/write?key=' . $runKey . 'board' . $n, [
            'title' => 'QA 게시글 ' . $n,
            'category_id' => '0',
            'body_text' => "QA 게시글 본문 {$n}\n\n실제 공개 글쓰기 경로로 만든 게시글입니다.",
            'body_format' => 'plain',
            'series_mode' => 'none',
            'series_id' => '0',
            'new_series_title' => '',
            'series_episode_label' => '',
            'series_sort_order' => '0',
        ]);
    }
    seed_ensure_created($pdo, 'community_posts', 'sr_community_posts', $beforePosts, $count);
} else {
    echo "community_posts\tskipped\n";
}

if (!$skipOperations) {
    $beforeBanners = seed_count($pdo, 'sr_banners');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seed_post('/admin/banners/new', '/admin/banners/save', [
            'banner_id' => '0',
            'title' => 'QA 배너 ' . $n,
            'body_text' => '실제 배너 저장 경로로 만든 더미 배너입니다.',
            'link_url' => '/',
            'image_url' => '',
            'status' => 'enabled',
            'skin_key' => 'basic',
            'starts_at' => '',
            'ends_at' => '',
            'sort_order' => (string) ($i * 10),
            'target_option' => '__public__',
            'match_type' => 'all',
            'subject_id' => '',
        ]);
    }
    seed_ensure_created($pdo, 'banners', 'sr_banners', $beforeBanners, $count);
} else {
    echo "banners\tskipped\n";
}

if (!$skipOperations) {
    $beforePopups = seed_count($pdo, 'sr_popup_layers');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seed_post('/admin/popup-layers/new', '/admin/popup-layers/save', [
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
        ]);
    }
    seed_ensure_created($pdo, 'popup_layers', 'sr_popup_layers', $beforePopups, $count);
} else {
    echo "popup_layers\tskipped\n";
}

if (!$skipOperations) {
    $beforeCoupons = seed_count($pdo, 'sr_coupon_definitions');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seed_post('/admin/coupons', '/admin/coupons', [
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
        ]);
    }
    seed_ensure_created($pdo, 'coupon_definitions', 'sr_coupon_definitions', $beforeCoupons, $count);
} else {
    echo "coupon_definitions\tskipped\n";
}

if (!$skipOperations) {
    $beforeNotifications = seed_count($pdo, 'sr_notifications');
    for ($i = 1; $i <= $count; $i++) {
        $n = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        seed_post('/admin/notifications/new', '/admin/notifications/create', [
            'audience' => 'all',
            'account_identifier' => '',
            'title' => 'QA 알림 ' . $n,
            'body_text' => '실제 알림 등록 경로로 만든 더미 사이트 알림입니다.',
            'body_format' => 'plain',
            'link_url' => '/',
            'channels' => ['site'],
        ]);
    }
    seed_ensure_created($pdo, 'notifications', 'sr_notifications', $beforeNotifications, $count);
} else {
    echo "notifications\tskipped\n";
}

echo "done\n";
