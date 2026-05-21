<?php

declare(strict_types=1);

function sr_page_allowed_statuses(): array
{
    return ['draft', 'published', 'hidden'];
}

function sr_page_asset_modules(): array
{
    return [
        'point' => [
            'label' => '포인트',
            'module_key' => 'point',
            'helper' => SR_ROOT . '/modules/point/helpers.php',
            'balance_function' => 'sr_point_balance',
            'transaction_function' => 'sr_point_create_transaction',
            'transaction_table' => 'sr_point_transactions',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
        'reward' => [
            'label' => '적립금',
            'module_key' => 'reward',
            'helper' => SR_ROOT . '/modules/reward/helpers.php',
            'balance_function' => 'sr_reward_balance',
            'transaction_function' => 'sr_reward_create_transaction',
            'transaction_table' => 'sr_reward_transactions',
            'use_type' => 'use',
            'credit_type' => 'grant',
            'refund_type' => 'refund',
        ],
        'deposit' => [
            'label' => '예치금',
            'module_key' => 'deposit',
            'helper' => SR_ROOT . '/modules/deposit/helpers.php',
            'balance_function' => 'sr_deposit_balance',
            'transaction_function' => 'sr_deposit_create_transaction',
            'transaction_table' => 'sr_deposit_transactions',
            'use_type' => 'use',
            'credit_type' => 'deposit',
            'refund_type' => 'refund',
        ],
    ];
}

function sr_page_asset_charge_policies(): array
{
    return sr_page_asset_view_charge_policies() + sr_page_asset_download_charge_policies();
}

function sr_page_asset_view_charge_policies(): array
{
    return [
        'once' => '최초 1회',
        'every_view' => '매 열람',
    ];
}

function sr_page_asset_download_charge_policies(): array
{
    return [
        'once' => '최초 1회',
        'every_download' => '매 다운로드',
    ];
}

function sr_page_asset_action_directions(): array
{
    return [
        'grant' => '지급',
        'use' => '차감',
    ];
}

function sr_page_asset_module_is_available(PDO $pdo, string $assetModule): bool
{
    $options = sr_page_asset_modules();
    if (!isset($options[$assetModule])) {
        return false;
    }

    $option = $options[$assetModule];
    $moduleKey = (string) ($option['module_key'] ?? '');
    $helper = (string) ($option['helper'] ?? '');
    if (!sr_module_enabled($pdo, $moduleKey) || !is_file($helper)) {
        return false;
    }

    require_once $helper;

    return function_exists((string) ($option['balance_function'] ?? ''))
        && function_exists((string) ($option['transaction_function'] ?? ''));
}

function sr_page_asset_module_options(PDO $pdo): array
{
    $available = [];
    foreach (sr_page_asset_modules() as $assetModule => $option) {
        if (sr_page_asset_module_is_available($pdo, (string) $assetModule)) {
            $available[$assetModule] = $option;
        }
    }

    return $available;
}

function sr_page_asset_module_label(string $assetModule): string
{
    $options = sr_page_asset_modules();
    return isset($options[$assetModule]) ? (string) $options[$assetModule]['label'] : '회원 자산';
}

function sr_page_reserved_slugs(): array
{
    return ['account', 'admin', 'api', 'assets', 'community', 'login', 'logout', 'modules', 'pages', 'register'];
}

function sr_page_clean_single_line(string $value, int $maxLength): string
{
    $value = trim(preg_replace('/\s+/', ' ', str_replace(["\r", "\n"], ' ', $value)) ?? '');
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $maxLength);
    }

    return substr($value, 0, $maxLength);
}

function sr_page_clean_text(string $value, int $maxLength): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    if (function_exists('mb_substr')) {
        return trim(mb_substr($value, 0, $maxLength));
    }

    return trim(substr($value, 0, $maxLength));
}

function sr_page_clean_slug(string $value): string
{
    return strtolower(trim($value));
}

function sr_page_slug_is_valid(string $slug): bool
{
    return preg_match('/\A[a-z0-9][a-z0-9-]{1,118}[a-z0-9]\z/', $slug) === 1
        && !in_array($slug, sr_page_reserved_slugs(), true);
}

function sr_page_path(string $slug): string
{
    return '/pages/' . rawurlencode($slug);
}

function sr_page_slug_from_request_path(): string
{
    $path = sr_request_path();
    $prefix = '/pages/';
    if (!str_starts_with($path, $prefix)) {
        return '';
    }

    $slug = substr($path, strlen($prefix));
    if (!is_string($slug) || $slug === '' || strpos($slug, '/') !== false) {
        return '';
    }

    return sr_page_clean_slug($slug);
}

function sr_page_by_id(PDO $pdo, int $pageId): ?array
{
    if ($pageId < 1) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT * FROM sr_pages WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $pageId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_page_published_by_slug(PDO $pdo, string $slug): ?array
{
    if (!sr_page_slug_is_valid($slug)) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_pages
         WHERE slug = :slug
           AND status = 'published'
         LIMIT 1"
    );
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_page_slug_exists(PDO $pdo, string $slug, int $exceptPageId = 0): bool
{
    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_pages
         WHERE slug = :slug
           AND id <> :except_id
         LIMIT 1'
    );
    $stmt->execute([
        'slug' => $slug,
        'except_id' => $exceptPageId,
    ]);

    return is_array($stmt->fetch());
}

function sr_page_admin_filters(): array
{
    $status = sr_get_string('status', 30);
    if ($status !== '' && !in_array($status, sr_page_allowed_statuses(), true)) {
        $status = '';
    }

    $field = sr_get_string('field', 20);
    if (!in_array($field, ['all', 'title', 'slug'], true)) {
        $field = 'all';
    }

    return [
        'status' => $status,
        'field' => $field,
        'q' => sr_page_clean_single_line(sr_get_string('q', 120), 120),
    ];
}

function sr_page_admin_status_counts(PDO $pdo): array
{
    $counts = [
        'total' => 0,
        'draft' => 0,
        'published' => 0,
        'hidden' => 0,
    ];

    $stmt = $pdo->query('SELECT status, COUNT(*) AS count_value FROM sr_pages GROUP BY status');
    foreach ($stmt->fetchAll() as $row) {
        $status = (string) ($row['status'] ?? '');
        $count = (int) ($row['count_value'] ?? 0);
        if (array_key_exists($status, $counts)) {
            $counts[$status] = $count;
        }
        $counts['total'] += $count;
    }

    return $counts;
}

function sr_page_admin_list(PDO $pdo, array $filters): array
{
    $where = [];
    $params = [];
    if ((string) ($filters['status'] ?? '') !== '') {
        $where[] = 'p.status = :status';
        $params['status'] = (string) $filters['status'];
    }

    if ((string) ($filters['q'] ?? '') !== '') {
        $field = (string) ($filters['field'] ?? 'all');
        if ($field === 'title') {
            $where[] = 'p.title LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } elseif ($field === 'slug') {
            $where[] = 'p.slug LIKE :keyword';
            $params['keyword'] = '%' . (string) $filters['q'] . '%';
        } else {
            $where[] = '(p.title LIKE :title_keyword OR p.slug LIKE :slug_keyword)';
            $params['title_keyword'] = '%' . (string) $filters['q'] . '%';
            $params['slug_keyword'] = '%' . (string) $filters['q'] . '%';
        }
    }

    $sql = 'SELECT p.*, creator.display_name AS created_by_name, updater.display_name AS updated_by_name
            FROM sr_pages p
            LEFT JOIN sr_member_accounts creator ON creator.id = p.created_by
            LEFT JOIN sr_member_accounts updater ON updater.id = p.updated_by';
    if ($where !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY p.updated_at DESC, p.id DESC LIMIT 200';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function sr_page_homepage_candidates(PDO $pdo): array
{
    $stmt = $pdo->query(
        "SELECT id, slug, title, updated_at
         FROM sr_pages
         WHERE status = 'published'
         ORDER BY updated_at DESC, id DESC
         LIMIT 200"
    );

    $candidates = [];
    foreach ($stmt->fetchAll() as $page) {
        $slug = (string) ($page['slug'] ?? '');
        if (!sr_page_slug_is_valid($slug)) {
            continue;
        }

        $path = sr_page_path($slug);
        $candidates[] = [
            'module_key' => 'page',
            'label' => '페이지: ' . (string) ($page['title'] ?? $slug),
            'path' => $path,
            'detail' => $path,
            'available' => true,
        ];
    }

    return $candidates;
}

function sr_page_public_banner_setting_labels(): array
{
    return [
        'banner_before_content_id' => '본문 상단 배너',
        'banner_after_content_id' => '본문 하단 배너',
    ];
}

function sr_page_public_popup_layer_setting_labels(): array
{
    return [
        'popup_layer_id' => '페이지 팝업레이어',
    ];
}

function sr_page_public_display_setting_labels(): array
{
    return sr_page_public_banner_setting_labels() + sr_page_public_popup_layer_setting_labels();
}

function sr_page_normalize_asset_values(array $values, bool $coerceInvalid = true): array
{
    $assetModule = (string) ($values['asset_module'] ?? 'point');
    if ($coerceInvalid && !isset(sr_page_asset_modules()[$assetModule])) {
        $assetModule = 'point';
    }

    $chargePolicy = (string) ($values['asset_charge_policy'] ?? 'once');
    if ($coerceInvalid && !isset(sr_page_asset_charge_policies()[$chargePolicy])) {
        $chargePolicy = 'once';
    }

    $values['asset_access_enabled'] = (int) ($values['asset_access_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_module'] = $assetModule;
    $values['asset_access_amount'] = max(0, (int) ($values['asset_access_amount'] ?? 0));
    $values['asset_charge_policy'] = $chargePolicy;

    if ((int) $values['asset_access_enabled'] !== 1) {
        $values['asset_module'] = 'point';
        $values['asset_access_amount'] = 0;
        $values['asset_charge_policy'] = 'once';
    }

    $actionModule = (string) ($values['asset_action_module'] ?? 'point');
    if ($coerceInvalid && !isset(sr_page_asset_modules()[$actionModule])) {
        $actionModule = 'point';
    }

    $actionDirection = (string) ($values['asset_action_direction'] ?? 'grant');
    if ($coerceInvalid && !isset(sr_page_asset_action_directions()[$actionDirection])) {
        $actionDirection = 'grant';
    }

    $values['asset_action_enabled'] = (int) ($values['asset_action_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_action_module'] = $actionModule;
    $values['asset_action_amount'] = max(0, (int) ($values['asset_action_amount'] ?? 0));
    $values['asset_action_direction'] = $actionDirection;
    $values['asset_action_label'] = sr_page_clean_single_line((string) ($values['asset_action_label'] ?? '완료'), 80);
    if ((string) $values['asset_action_label'] === '') {
        $values['asset_action_label'] = '완료';
    }

    if ((int) $values['asset_action_enabled'] !== 1) {
        $values['asset_action_module'] = 'point';
        $values['asset_action_amount'] = 0;
        $values['asset_action_direction'] = 'grant';
        $values['asset_action_label'] = '완료';
    }

    return $values;
}

function sr_page_input_values(): array
{
    $values = [
        'title' => sr_page_clean_single_line(sr_post_string('title', 160), 160),
        'slug' => sr_page_clean_slug(sr_post_string('slug', 120)),
        'summary' => sr_page_clean_text(sr_post_string('summary', 1000), 1000),
        'body_text' => sr_page_clean_text(sr_post_string('body_text', 100000), 100000),
        'body_format' => 'plain',
        'status' => sr_post_string('status', 30),
        'asset_access_enabled' => sr_post_string('asset_access_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_page_clean_slug(sr_post_string('asset_module', 20)),
        'asset_access_amount' => (int) sr_post_string('asset_access_amount', 20),
        'asset_charge_policy' => sr_page_clean_slug(sr_post_string('asset_charge_policy', 20)),
        'asset_action_enabled' => sr_post_string('asset_action_enabled', 1) === '1' ? 1 : 0,
        'asset_action_module' => sr_page_clean_slug(sr_post_string('asset_action_module', 20)),
        'asset_action_amount' => (int) sr_post_string('asset_action_amount', 20),
        'asset_action_direction' => sr_page_clean_slug(sr_post_string('asset_action_direction', 20)),
        'asset_action_label' => sr_page_clean_single_line(sr_post_string('asset_action_label', 80), 80),
        'seo_title' => sr_page_clean_single_line(sr_post_string('seo_title', 160), 160),
        'seo_description' => sr_page_clean_single_line(sr_post_string('seo_description', 255), 255),
    ];

    foreach (sr_page_public_display_setting_labels() as $settingKey => $settingLabel) {
        $rawValue = sr_post_string($settingKey, 20);
        $values[$settingKey] = preg_match('/\A[0-9]{1,9}\z/', $rawValue) === 1 ? (int) $rawValue : -1;
    }

    return sr_page_normalize_asset_values($values, false);
}

function sr_page_validate_input(PDO $pdo, array $values, int $pageId = 0, array $publicBannerIds = [], array $publicPopupLayerIds = []): array
{
    $errors = [];
    if ((string) ($values['title'] ?? '') === '') {
        $errors[] = '제목을 입력하세요.';
    }

    $slug = (string) ($values['slug'] ?? '');
    if (!sr_page_slug_is_valid($slug)) {
        $errors[] = 'slug는 3-120자의 소문자 영문, 숫자, 하이픈만 사용할 수 있으며 예약어는 사용할 수 없습니다.';
    } elseif (sr_page_slug_exists($pdo, $slug, $pageId)) {
        $errors[] = '이미 사용 중인 slug입니다.';
    }

    if (!in_array((string) ($values['status'] ?? ''), sr_page_allowed_statuses(), true)) {
        $errors[] = '상태 값이 올바르지 않습니다.';
    }

    if ((string) ($values['body_format'] ?? 'plain') !== 'plain') {
        $errors[] = '본문 형식이 올바르지 않습니다.';
    }

    if ((int) ($values['asset_access_enabled'] ?? 0) === 1) {
        $assetModule = (string) ($values['asset_module'] ?? '');
        if (!isset(sr_page_asset_modules()[$assetModule])) {
            $errors[] = '유료 열람 자산이 올바르지 않습니다.';
        } elseif (!sr_page_asset_module_is_available($pdo, $assetModule)) {
            $errors[] = sr_page_asset_module_label($assetModule) . ' 모듈이 활성 상태일 때만 유료 열람 자산으로 사용할 수 있습니다.';
        }

        $amount = (int) ($values['asset_access_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '유료 열람 금액은 1부터 999999999 사이로 입력하세요.';
        }

        if (!isset(sr_page_asset_view_charge_policies()[(string) ($values['asset_charge_policy'] ?? '')])) {
            $errors[] = '유료 열람 과금 방식이 올바르지 않습니다.';
        }
    }

    if ((int) ($values['asset_action_enabled'] ?? 0) === 1) {
        $assetModule = (string) ($values['asset_action_module'] ?? '');
        if (!isset(sr_page_asset_modules()[$assetModule])) {
            $errors[] = '완료 액션 자산이 올바르지 않습니다.';
        } elseif (!sr_page_asset_module_is_available($pdo, $assetModule)) {
            $errors[] = sr_page_asset_module_label($assetModule) . ' 모듈이 활성 상태일 때만 완료 액션 자산으로 사용할 수 있습니다.';
        }

        $amount = (int) ($values['asset_action_amount'] ?? 0);
        if ($amount < 1 || $amount > 999999999) {
            $errors[] = '완료 액션 금액은 1부터 999999999 사이로 입력하세요.';
        }

        if (!isset(sr_page_asset_action_directions()[(string) ($values['asset_action_direction'] ?? '')])) {
            $errors[] = '완료 액션 지급/차감 방향이 올바르지 않습니다.';
        }

        if ((string) ($values['asset_action_label'] ?? '') === '') {
            $errors[] = '완료 액션 버튼 문구를 입력하세요.';
        }
    }

    foreach (sr_page_public_display_setting_labels() as $settingKey => $settingLabel) {
        $displayId = (int) ($values[$settingKey] ?? 0);
        if ($displayId < 0) {
            $errors[] = $settingLabel . ' 값이 올바르지 않습니다.';
            continue;
        }

        if (isset(sr_page_public_banner_setting_labels()[$settingKey]) && $displayId > 0 && !isset($publicBannerIds[$displayId])) {
            $errors[] = $settingLabel . '는 공용 배너 중에서 선택하세요.';
        }

        if (isset(sr_page_public_popup_layer_setting_labels()[$settingKey]) && $displayId > 0 && !isset($publicPopupLayerIds[$displayId])) {
            $errors[] = $settingLabel . '는 공용 팝업레이어 중에서 선택하세요.';
        }
    }

    return $errors;
}

function sr_page_save(PDO $pdo, array $values, int $accountId, int $pageId = 0): int
{
    $values = sr_page_normalize_asset_values($values);
    $now = sr_now();
    $pdo->beginTransaction();

    try {
        $existing = $pageId > 0 ? sr_page_by_id($pdo, $pageId) : null;
        $publishedAt = null;
        if ((string) $values['status'] === 'published') {
            $publishedAt = is_array($existing) && !empty($existing['published_at']) ? (string) $existing['published_at'] : $now;
        }

        if (is_array($existing)) {
            $stmt = $pdo->prepare(
                'UPDATE sr_pages
                 SET slug = :slug, title = :title, summary = :summary, body_text = :body_text,
                     body_format = :body_format, status = :status,
                     asset_access_enabled = :asset_access_enabled,
                     asset_module = :asset_module,
                     asset_access_amount = :asset_access_amount,
                     asset_charge_policy = :asset_charge_policy,
                     asset_action_enabled = :asset_action_enabled,
                     asset_action_module = :asset_action_module,
                     asset_action_amount = :asset_action_amount,
                     asset_action_direction = :asset_action_direction,
                     asset_action_label = :asset_action_label,
                     banner_before_content_id = :banner_before_content_id,
                     banner_after_content_id = :banner_after_content_id,
                     popup_layer_id = :popup_layer_id,
                     seo_title = :seo_title,
                     seo_description = :seo_description, updated_by = :updated_by,
                     published_at = :published_at, updated_at = :updated_at
                 WHERE id = :id'
            );
            $stmt->execute([
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'body_text' => (string) $values['body_text'],
                'body_format' => 'plain',
                'status' => (string) $values['status'],
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? 'point'),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? 'point'),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
                'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
                'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
                'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
                'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
                'seo_title' => (string) $values['seo_title'],
                'seo_description' => (string) $values['seo_description'],
                'updated_by' => $accountId,
                'published_at' => $publishedAt,
                'updated_at' => $now,
                'id' => $pageId,
            ]);
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO sr_pages
                    (slug, title, summary, body_text, body_format, status, asset_access_enabled, asset_module, asset_access_amount, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, seo_title, seo_description, created_by, updated_by, published_at, created_at, updated_at)
                 VALUES
                    (:slug, :title, :summary, :body_text, :body_format, :status, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :seo_title, :seo_description, :created_by, :updated_by, :published_at, :created_at, :updated_at)'
            );
            $stmt->execute([
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'body_text' => (string) $values['body_text'],
                'body_format' => 'plain',
                'status' => (string) $values['status'],
                'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
                'asset_module' => (string) ($values['asset_module'] ?? 'point'),
                'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
                'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
                'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
                'asset_action_module' => (string) ($values['asset_action_module'] ?? 'point'),
                'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
                'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
                'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
                'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
                'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
                'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
                'seo_title' => (string) $values['seo_title'],
                'seo_description' => (string) $values['seo_description'],
                'created_by' => $accountId,
                'updated_by' => $accountId,
                'published_at' => $publishedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $pageId = (int) $pdo->lastInsertId();
        }

        sr_page_record_revision($pdo, $pageId, $values, $accountId, $now);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }

    return $pageId;
}

function sr_page_record_revision(PDO $pdo, int $pageId, array $values, int $accountId, string $now): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO sr_page_revisions
            (page_id, title, summary, body_text, body_format, status, asset_access_enabled, asset_module, asset_access_amount, asset_charge_policy, asset_action_enabled, asset_action_module, asset_action_amount, asset_action_direction, asset_action_label, banner_before_content_id, banner_after_content_id, popup_layer_id, created_by, created_at)
         VALUES
            (:page_id, :title, :summary, :body_text, :body_format, :status, :asset_access_enabled, :asset_module, :asset_access_amount, :asset_charge_policy, :asset_action_enabled, :asset_action_module, :asset_action_amount, :asset_action_direction, :asset_action_label, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :created_by, :created_at)'
    );
    $stmt->execute([
        'page_id' => $pageId,
        'title' => (string) $values['title'],
        'summary' => (string) $values['summary'],
        'body_text' => (string) $values['body_text'],
        'body_format' => 'plain',
        'status' => (string) $values['status'],
        'asset_access_enabled' => (int) ($values['asset_access_enabled'] ?? 0),
        'asset_module' => (string) ($values['asset_module'] ?? 'point'),
        'asset_access_amount' => (int) ($values['asset_access_amount'] ?? 0),
        'asset_charge_policy' => (string) ($values['asset_charge_policy'] ?? 'once'),
        'asset_action_enabled' => (int) ($values['asset_action_enabled'] ?? 0),
        'asset_action_module' => (string) ($values['asset_action_module'] ?? 'point'),
        'asset_action_amount' => (int) ($values['asset_action_amount'] ?? 0),
        'asset_action_direction' => (string) ($values['asset_action_direction'] ?? 'grant'),
        'asset_action_label' => (string) ($values['asset_action_label'] ?? '완료'),
        'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
        'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
        'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
        'created_by' => $accountId,
        'created_at' => $now,
    ]);
}

function sr_page_file_extension_mime_map(): array
{
    return [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'hwp' => ['application/x-hwp', 'application/haansofthwp'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];
}

function sr_page_file_allowed_extensions(): array
{
    return array_keys(sr_page_file_extension_mime_map());
}

function sr_page_file_mime_types_for_extensions(array $extensions): array
{
    $map = sr_page_file_extension_mime_map();
    $mimeTypes = [];
    foreach (sr_upload_normalize_extensions($extensions) as $extension) {
        foreach ($map[$extension] ?? [] as $mimeType) {
            $mimeTypes[$mimeType] = true;
        }
    }

    return array_keys($mimeTypes);
}

function sr_page_file_upload_max_bytes(): int
{
    return 20971520;
}

function sr_page_format_bytes(int $bytes): string
{
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 1) . ' MB';
    }

    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 1) . ' KB';
    }

    return number_format(max(0, $bytes)) . ' bytes';
}

function sr_page_file_upload_was_provided(mixed $file): bool
{
    return is_array($file) && (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
}

function sr_page_file_mime_is_allowed(string $mimeType): bool
{
    return in_array(strtolower(trim($mimeType)), sr_page_file_mime_types_for_extensions(sr_page_file_allowed_extensions()), true);
}

function sr_page_file_storage_driver(array $file): string
{
    $driver = strtolower((string) ($file['storage_driver'] ?? 'local'));
    return in_array($driver, ['local', 's3'], true) ? $driver : 'local';
}

function sr_page_file_storage_key(array $file): string
{
    $key = (string) ($file['storage_key'] ?? '');
    if ($key !== '' && sr_storage_key_is_safe($key)) {
        return $key;
    }

    $storagePath = (string) ($file['storage_path'] ?? '');
    if (str_starts_with($storagePath, SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR)) {
        $storagePath = substr($storagePath, strlen(SR_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR));
    } elseif (str_starts_with($storagePath, 'storage/')) {
        $storagePath = substr($storagePath, strlen('storage/'));
    }

    $storagePath = str_replace('\\', '/', ltrim($storagePath, '/'));
    return sr_storage_key_is_safe($storagePath) ? $storagePath : '';
}

function sr_page_file_path(array $file): ?string
{
    $driver = sr_page_file_storage_driver($file);
    $key = sr_page_file_storage_key($file);
    if ($driver === 'local' && $key !== '') {
        return sr_storage_local_path($key);
    }

    return null;
}

function sr_page_files_for_page(PDO $pdo, int $pageId): array
{
    if ($pageId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        "SELECT *
         FROM sr_page_files
         WHERE page_id = :page_id
           AND status = 'active'
         ORDER BY id ASC
         LIMIT 50"
    );
    $stmt->execute(['page_id' => $pageId]);

    return $stmt->fetchAll();
}

function sr_page_file_by_id(PDO $pdo, int $fileId): ?array
{
    if ($fileId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT f.*, p.slug, p.title AS page_title, p.status AS page_status
         FROM sr_page_files f
         INNER JOIN sr_pages p ON p.id = f.page_id
         WHERE f.id = :id
           AND f.status = 'active'
         LIMIT 1"
    );
    $stmt->execute(['id' => $fileId]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_page_published_file_by_id(PDO $pdo, int $fileId): ?array
{
    $file = sr_page_file_by_id($pdo, $fileId);
    if (!is_array($file) || (string) ($file['page_status'] ?? '') !== 'published') {
        return null;
    }

    return $file;
}

function sr_page_normalize_file_asset_values(array $values, bool $coerceInvalid = true): array
{
    $assetModule = (string) ($values['asset_module'] ?? 'point');
    if ($coerceInvalid && !isset(sr_page_asset_modules()[$assetModule])) {
        $assetModule = 'point';
    }

    $chargePolicy = (string) ($values['asset_charge_policy'] ?? 'once');
    if ($coerceInvalid && !isset(sr_page_asset_download_charge_policies()[$chargePolicy])) {
        $chargePolicy = 'once';
    }

    $values['asset_download_enabled'] = (int) ($values['asset_download_enabled'] ?? 0) === 1 ? 1 : 0;
    $values['asset_module'] = $assetModule;
    $values['asset_download_amount'] = max(0, (int) ($values['asset_download_amount'] ?? 0));
    $values['asset_charge_policy'] = $chargePolicy;

    if ((int) $values['asset_download_enabled'] !== 1) {
        $values['asset_module'] = 'point';
        $values['asset_download_amount'] = 0;
        $values['asset_charge_policy'] = 'once';
    }

    return $values;
}

function sr_page_file_asset_validation_errors(PDO $pdo, array $values, string $labelPrefix = '파일 다운로드'): array
{
    $errors = [];
    if ((int) ($values['asset_download_enabled'] ?? 0) !== 1) {
        return [];
    }

    $assetModule = (string) ($values['asset_module'] ?? '');
    if (!isset(sr_page_asset_modules()[$assetModule])) {
        $errors[] = $labelPrefix . ' 자산이 올바르지 않습니다.';
    } elseif (!sr_page_asset_module_is_available($pdo, $assetModule)) {
        $errors[] = sr_page_asset_module_label($assetModule) . ' 모듈이 활성 상태일 때만 ' . $labelPrefix . ' 자산으로 사용할 수 있습니다.';
    }

    $amount = (int) ($values['asset_download_amount'] ?? 0);
    if ($amount < 1 || $amount > 999999999) {
        $errors[] = $labelPrefix . ' 금액은 1부터 999999999 사이로 입력하세요.';
    }

    if (!isset(sr_page_asset_download_charge_policies()[(string) ($values['asset_charge_policy'] ?? '')])) {
        $errors[] = $labelPrefix . ' 과금 방식이 올바르지 않습니다.';
    }

    return $errors;
}

function sr_page_validate_file_request(PDO $pdo, int $pageId): array
{
    $errors = [];
    $existingIds = $_POST['page_file_ids'] ?? [];
    if (is_array($existingIds)) {
        foreach ($existingIds as $rawFileId) {
            $fileId = (int) $rawFileId;
            if ($fileId < 1) {
                continue;
            }

            $file = sr_page_file_by_id($pdo, $fileId);
            if (!is_array($file) || (int) $file['page_id'] !== $pageId) {
                $errors[] = '수정할 페이지 파일을 확인할 수 없습니다.';
                continue;
            }

            $values = sr_page_file_values_from_post($fileId);
            $errors = array_merge($errors, sr_page_file_asset_validation_errors($pdo, $values));
        }
    }

    $upload = $_FILES['page_file_upload'] ?? null;
    if (sr_page_file_upload_was_provided($upload)) {
        try {
            sr_upload_validate_file($upload, [
                'max_bytes' => sr_page_file_upload_max_bytes(),
                'allowed_extensions' => sr_page_file_allowed_extensions(),
                'allowed_mime_types' => sr_page_file_mime_types_for_extensions(sr_page_file_allowed_extensions()),
            ]);
        } catch (Throwable $exception) {
            $errors[] = $exception->getMessage();
        }

        $values = sr_page_new_file_values_from_post();
        $errors = array_merge($errors, sr_page_file_asset_validation_errors($pdo, $values, '새 파일 다운로드'));
    }

    return $errors;
}

function sr_page_file_values_from_post(int $fileId): array
{
    $titleValues = is_array($_POST['page_file_title'] ?? null) ? $_POST['page_file_title'] : [];
    $enabledValues = is_array($_POST['page_file_asset_download_enabled'] ?? null) ? $_POST['page_file_asset_download_enabled'] : [];
    $moduleValues = is_array($_POST['page_file_asset_module'] ?? null) ? $_POST['page_file_asset_module'] : [];
    $amountValues = is_array($_POST['page_file_asset_download_amount'] ?? null) ? $_POST['page_file_asset_download_amount'] : [];
    $policyValues = is_array($_POST['page_file_asset_charge_policy'] ?? null) ? $_POST['page_file_asset_charge_policy'] : [];

    return sr_page_normalize_file_asset_values([
        'title' => sr_page_clean_single_line((string) ($titleValues[$fileId] ?? ''), 160),
        'asset_download_enabled' => (string) ($enabledValues[$fileId] ?? '') === '1' ? 1 : 0,
        'asset_module' => sr_page_clean_slug((string) ($moduleValues[$fileId] ?? '')),
        'asset_download_amount' => (int) ($amountValues[$fileId] ?? 0),
        'asset_charge_policy' => sr_page_clean_slug((string) ($policyValues[$fileId] ?? '')),
    ], false);
}

function sr_page_new_file_values_from_post(): array
{
    return sr_page_normalize_file_asset_values([
        'title' => sr_page_clean_single_line(sr_post_string('new_page_file_title', 160), 160),
        'asset_download_enabled' => sr_post_string('new_page_file_asset_download_enabled', 1) === '1' ? 1 : 0,
        'asset_module' => sr_page_clean_slug(sr_post_string('new_page_file_asset_module', 20)),
        'asset_download_amount' => (int) sr_post_string('new_page_file_asset_download_amount', 20),
        'asset_charge_policy' => sr_page_clean_slug(sr_post_string('new_page_file_asset_charge_policy', 20)),
    ], false);
}

function sr_page_save_files_from_request(PDO $pdo, int $pageId, int $accountId): void
{
    if ($pageId < 1) {
        return;
    }

    $deleteValues = is_array($_POST['page_file_delete'] ?? null) ? $_POST['page_file_delete'] : [];
    $existingIds = is_array($_POST['page_file_ids'] ?? null) ? $_POST['page_file_ids'] : [];
    foreach ($existingIds as $rawFileId) {
        $fileId = (int) $rawFileId;
        if ($fileId < 1) {
            continue;
        }

        $file = sr_page_file_by_id($pdo, $fileId);
        if (!is_array($file) || (int) $file['page_id'] !== $pageId) {
            continue;
        }

        if ((string) ($deleteValues[$fileId] ?? '') === '1') {
            sr_page_hide_file($pdo, $fileId);
            continue;
        }

        sr_page_update_file($pdo, $fileId, sr_page_file_values_from_post($fileId));
    }

    $upload = $_FILES['page_file_upload'] ?? null;
    if (sr_page_file_upload_was_provided($upload)) {
        sr_page_upload_file($pdo, $pageId, $accountId, $upload, sr_page_new_file_values_from_post());
    }
}

function sr_page_update_file(PDO $pdo, int $fileId, array $values): void
{
    $values = sr_page_normalize_file_asset_values($values);
    $title = (string) ($values['title'] ?? '');
    if ($title === '') {
        $title = '첨부 파일';
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_page_files
         SET title = :title,
             asset_download_enabled = :asset_download_enabled,
             asset_module = :asset_module,
             asset_download_amount = :asset_download_amount,
             asset_charge_policy = :asset_charge_policy,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $stmt->execute([
        'title' => $title,
        'asset_download_enabled' => (int) $values['asset_download_enabled'],
        'asset_module' => (string) $values['asset_module'],
        'asset_download_amount' => (int) $values['asset_download_amount'],
        'asset_charge_policy' => (string) $values['asset_charge_policy'],
        'updated_at' => sr_now(),
        'id' => $fileId,
    ]);
}

function sr_page_hide_file(PDO $pdo, int $fileId): void
{
    $stmt = $pdo->prepare(
        "UPDATE sr_page_files
         SET status = 'hidden', updated_at = :updated_at
         WHERE id = :id"
    );
    $stmt->execute([
        'updated_at' => sr_now(),
        'id' => $fileId,
    ]);
}

function sr_page_upload_file(PDO $pdo, int $pageId, int $accountId, array $file, array $values): int
{
    $validated = sr_upload_validate_file($file, [
        'max_bytes' => sr_page_file_upload_max_bytes(),
        'allowed_extensions' => sr_page_file_allowed_extensions(),
        'allowed_mime_types' => sr_page_file_mime_types_for_extensions(sr_page_file_allowed_extensions()),
    ]);
    $values = sr_page_normalize_file_asset_values($values);

    $storedName = sr_upload_random_filename((string) $validated['extension']);
    $storedMimeType = sr_upload_detect_mime((string) $validated['tmp_name']);
    $sizeBytes = filesize((string) $validated['tmp_name']);
    if (!sr_page_file_mime_is_allowed($storedMimeType) || !is_int($sizeBytes)) {
        throw new RuntimeException('저장된 페이지 파일 metadata를 확인할 수 없습니다.');
    }

    $storageKey = 'page/files/' . date('Y/m') . '/' . $storedName;
    $stored = sr_storage_put_file((string) $validated['tmp_name'], $storageKey, [
        'content_type' => $storedMimeType,
    ]);

    try {
        $title = (string) ($values['title'] ?? '');
        if ($title === '') {
            $title = (string) $validated['original_name'];
        }

        $stmt = $pdo->prepare(
            "INSERT INTO sr_page_files
                (page_id, title, original_name, stored_name, storage_path, storage_driver, storage_key, mime_type, size_bytes, checksum_sha256, status, asset_download_enabled, asset_module, asset_download_amount, asset_charge_policy, created_by, created_at, updated_at)
             VALUES
                (:page_id, :title, :original_name, :stored_name, :storage_path, :storage_driver, :storage_key, :mime_type, :size_bytes, :checksum_sha256, 'active', :asset_download_enabled, :asset_module, :asset_download_amount, :asset_charge_policy, :created_by, :created_at, :updated_at)"
        );
        $now = sr_now();
        $stmt->execute([
            'page_id' => $pageId,
            'title' => $title,
            'original_name' => (string) $validated['original_name'],
            'stored_name' => $storedName,
            'storage_path' => (string) ($stored['path'] ?? ''),
            'storage_driver' => (string) $stored['driver'],
            'storage_key' => $storageKey,
            'mime_type' => $storedMimeType,
            'size_bytes' => $sizeBytes,
            'checksum_sha256' => (string) $validated['checksum'],
            'asset_download_enabled' => (int) $values['asset_download_enabled'],
            'asset_module' => (string) $values['asset_module'],
            'asset_download_amount' => (int) $values['asset_download_amount'],
            'asset_charge_policy' => (string) $values['asset_charge_policy'],
            'created_by' => $accountId,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $pdo->lastInsertId();
    } catch (Throwable $exception) {
        sr_storage_delete((string) $stored['driver'], $storageKey);
        throw $exception;
    }
}

function sr_page_asset_access_required(array $page): bool
{
    return (int) ($page['asset_access_enabled'] ?? 0) === 1
        && (int) ($page['asset_access_amount'] ?? 0) > 0;
}

function sr_page_asset_access_reference_id(int $pageId): string
{
    return (string) $pageId;
}

function sr_page_asset_access_dedupe_key(string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): string
{
    return 'page.' . $accessKind . ':' . $assetModule . ':' . (string) $accountId . ':' . (string) $subjectId;
}

function sr_page_asset_access_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_page_asset_access_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_page_has_paid_access(PDO $pdo, string $assetModule, int $accountId, int $subjectId, string $accessKind = 'view'): bool
{
    $dedupeKey = sr_page_asset_access_dedupe_key($assetModule, $accountId, $subjectId, $accessKind);
    $log = sr_page_asset_access_log($pdo, $dedupeKey);

    return is_array($log) && (int) ($log['transaction_id'] ?? 0) > 0;
}

function sr_page_asset_balance(PDO $pdo, string $assetModule, int $accountId): int
{
    if (!sr_page_asset_module_is_available($pdo, $assetModule)) {
        return 0;
    }

    $option = sr_page_asset_modules()[$assetModule];
    $balanceFunction = (string) $option['balance_function'];

    return (int) $balanceFunction($pdo, $accountId);
}

function sr_page_create_asset_transaction(PDO $pdo, string $assetModule, array $data): int
{
    if (!sr_page_asset_module_is_available($pdo, $assetModule)) {
        throw new RuntimeException('Page asset module is not available.');
    }

    $option = sr_page_asset_modules()[$assetModule];
    $transactionFunction = (string) $option['transaction_function'];

    return (int) $transactionFunction($pdo, $data);
}

function sr_page_insert_asset_access_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, int $amount, string $chargePolicy, string $dedupeKey, string $referenceType = 'page.view', ?string $referenceId = null, string $accessKind = 'view'): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_page_asset_access_logs
            (page_id, account_id, asset_module, transaction_id, reference_type, reference_id, access_kind, charge_policy, amount, dedupe_key, created_at)
         VALUES
            (:page_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :access_kind, :charge_policy, :amount, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'page_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => $referenceType,
        'reference_id' => $referenceId ?? sr_page_asset_access_reference_id($pageId),
        'access_kind' => $accessKind,
        'charge_policy' => $chargePolicy,
        'amount' => $amount,
        'dedupe_key' => $dedupeKey,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_page_update_asset_access_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_page_asset_access_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_page_delete_asset_access_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_page_asset_access_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_page_charge_view_access(PDO $pdo, array $page, int $accountId): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModule = (string) ($page['asset_module'] ?? '');
    $chargePolicy = (string) ($page['asset_charge_policy'] ?? 'once');
    $amount = (int) ($page['asset_access_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_page_asset_access_required($page)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if (!isset(sr_page_asset_modules()[$assetModule]) || !isset(sr_page_asset_view_charge_policies()[$chargePolicy])) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '페이지 유료 열람 설정이 올바르지 않아 열람할 수 없습니다.',
        ];
    }

    if (!sr_page_asset_module_is_available($pdo, $assetModule)) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 모듈을 사용할 수 없어 페이지를 열람할 수 없습니다.',
        ];
    }

    if ($chargePolicy === 'once' && sr_page_has_paid_access($pdo, $assetModule, $accountId, $pageId)) {
        return [
            'allowed' => true,
            'charged' => false,
            'already_paid' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '',
        ];
    }

    if (sr_page_asset_balance($pdo, $assetModule, $accountId) < $amount) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 잔액이 부족해 페이지를 열람할 수 없습니다.',
        ];
    }

    $assetOption = sr_page_asset_modules()[$assetModule];
    $dedupeKey = $chargePolicy === 'once'
        ? sr_page_asset_access_dedupe_key($assetModule, $accountId, $pageId)
        : 'page.view:' . $assetModule . ':' . (string) $accountId . ':' . (string) $pageId . ':' . bin2hex(random_bytes(8));
    $inserted = sr_page_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $amount, $chargePolicy, $dedupeKey);
    if (!$inserted) {
        return [
            'allowed' => sr_page_has_paid_access($pdo, $assetModule, $accountId, $pageId),
            'charged' => false,
            'already_paid' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '',
        ];
    }

    try {
        $transactionId = sr_page_create_asset_transaction($pdo, $assetModule, [
            'account_id' => $accountId,
            'amount' => -$amount,
            'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
            'reason' => '페이지 열람',
            'reference_type' => 'page.view',
            'reference_id' => sr_page_asset_access_reference_id($pageId),
            'created_by_account_id' => null,
        ]);
        sr_page_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
    } catch (Throwable $exception) {
        sr_page_delete_asset_access_placeholder($pdo, $dedupeKey);
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'page_asset_access_charge_failed');
        }

        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 차감에 실패해 페이지를 열람할 수 없습니다.',
        ];
    }

    return [
        'allowed' => true,
        'charged' => true,
        'asset_module' => $assetModule,
        'asset_label' => sr_page_asset_module_label($assetModule),
        'amount' => $amount,
        'message' => '',
    ];
}

function sr_page_file_download_required(array $file): bool
{
    return (int) ($file['asset_download_enabled'] ?? 0) === 1
        && (int) ($file['asset_download_amount'] ?? 0) > 0;
}

function sr_page_charge_file_download(PDO $pdo, array $file, int $accountId): array
{
    $pageId = (int) ($file['page_id'] ?? 0);
    $fileId = (int) ($file['id'] ?? 0);
    $assetModule = (string) ($file['asset_module'] ?? '');
    $chargePolicy = (string) ($file['asset_charge_policy'] ?? 'once');
    $amount = (int) ($file['asset_download_amount'] ?? 0);

    if ($pageId <= 0 || $fileId <= 0 || $accountId <= 0 || !sr_page_file_download_required($file)) {
        return ['allowed' => true, 'charged' => false, 'message' => ''];
    }

    if (!isset(sr_page_asset_modules()[$assetModule]) || !isset(sr_page_asset_download_charge_policies()[$chargePolicy])) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '페이지 파일 다운로드 설정이 올바르지 않아 다운로드할 수 없습니다.',
        ];
    }

    if (!sr_page_asset_module_is_available($pdo, $assetModule)) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 모듈을 사용할 수 없어 파일을 다운로드할 수 없습니다.',
        ];
    }

    if ($chargePolicy === 'once' && sr_page_has_paid_access($pdo, $assetModule, $accountId, $fileId, 'download')) {
        return [
            'allowed' => true,
            'charged' => false,
            'already_paid' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '',
        ];
    }

    if (sr_page_asset_balance($pdo, $assetModule, $accountId) < $amount) {
        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 잔액이 부족해 파일을 다운로드할 수 없습니다.',
        ];
    }

    $assetOption = sr_page_asset_modules()[$assetModule];
    $dedupeKey = $chargePolicy === 'once'
        ? sr_page_asset_access_dedupe_key($assetModule, $accountId, $fileId, 'download')
        : 'page.download:' . $assetModule . ':' . (string) $accountId . ':' . (string) $fileId . ':' . bin2hex(random_bytes(8));
    $inserted = sr_page_insert_asset_access_placeholder($pdo, $pageId, $accountId, $assetModule, $amount, $chargePolicy, $dedupeKey, 'page.download', (string) $fileId, 'download');
    if (!$inserted) {
        return [
            'allowed' => sr_page_has_paid_access($pdo, $assetModule, $accountId, $fileId, 'download'),
            'charged' => false,
            'already_paid' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '',
        ];
    }

    try {
        $transactionId = sr_page_create_asset_transaction($pdo, $assetModule, [
            'account_id' => $accountId,
            'amount' => -$amount,
            'transaction_type' => (string) ($assetOption['use_type'] ?? 'use'),
            'reason' => '페이지 파일 다운로드',
            'reference_type' => 'page.download',
            'reference_id' => (string) $fileId,
            'created_by_account_id' => null,
        ]);
        sr_page_update_asset_access_transaction($pdo, $dedupeKey, $transactionId);
    } catch (Throwable $exception) {
        sr_page_delete_asset_access_placeholder($pdo, $dedupeKey);
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'page_file_download_charge_failed');
        }

        return [
            'allowed' => false,
            'charged' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 차감에 실패해 파일을 다운로드할 수 없습니다.',
        ];
    }

    return [
        'allowed' => true,
        'charged' => true,
        'asset_module' => $assetModule,
        'asset_label' => sr_page_asset_module_label($assetModule),
        'amount' => $amount,
        'message' => '',
    ];
}

function sr_page_asset_action_required(array $page): bool
{
    return (int) ($page['asset_action_enabled'] ?? 0) === 1
        && (int) ($page['asset_action_amount'] ?? 0) > 0;
}

function sr_page_asset_action_dedupe_key(string $assetModule, int $accountId, int $pageId): string
{
    return 'page.action:' . $assetModule . ':' . (string) $accountId . ':' . (string) $pageId . ':complete';
}

function sr_page_asset_action_log(PDO $pdo, string $dedupeKey): ?array
{
    $stmt = $pdo->prepare(
        'SELECT *
         FROM sr_page_asset_action_logs
         WHERE dedupe_key = :dedupe_key
         LIMIT 1'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
    $row = $stmt->fetch();

    return is_array($row) ? $row : null;
}

function sr_page_has_completed_asset_action(PDO $pdo, string $assetModule, int $accountId, int $pageId): bool
{
    $log = sr_page_asset_action_log($pdo, sr_page_asset_action_dedupe_key($assetModule, $accountId, $pageId));

    return is_array($log) && (int) ($log['transaction_id'] ?? 0) > 0;
}

function sr_page_insert_asset_action_placeholder(PDO $pdo, int $pageId, int $accountId, string $assetModule, string $direction, int $amount, string $dedupeKey): bool
{
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO sr_page_asset_action_logs
            (page_id, account_id, asset_module, transaction_id, reference_type, reference_id, action_key, direction, amount, dedupe_key, created_at)
         VALUES
            (:page_id, :account_id, :asset_module, 0, :reference_type, :reference_id, :action_key, :direction, :amount, :dedupe_key, :created_at)'
    );
    $stmt->execute([
        'page_id' => $pageId,
        'account_id' => $accountId,
        'asset_module' => $assetModule,
        'reference_type' => 'page.action',
        'reference_id' => (string) $pageId,
        'action_key' => 'complete',
        'direction' => $direction,
        'amount' => $amount,
        'dedupe_key' => $dedupeKey,
        'created_at' => sr_now(),
    ]);

    return $stmt->rowCount() > 0;
}

function sr_page_update_asset_action_transaction(PDO $pdo, string $dedupeKey, int $transactionId): void
{
    $stmt = $pdo->prepare(
        'UPDATE sr_page_asset_action_logs
         SET transaction_id = :transaction_id
         WHERE dedupe_key = :dedupe_key'
    );
    $stmt->execute([
        'transaction_id' => $transactionId,
        'dedupe_key' => $dedupeKey,
    ]);
}

function sr_page_delete_asset_action_placeholder(PDO $pdo, string $dedupeKey): void
{
    $stmt = $pdo->prepare(
        'DELETE FROM sr_page_asset_action_logs
         WHERE dedupe_key = :dedupe_key
           AND transaction_id = 0'
    );
    $stmt->execute(['dedupe_key' => $dedupeKey]);
}

function sr_page_run_asset_action(PDO $pdo, array $page, int $accountId): array
{
    $pageId = (int) ($page['id'] ?? 0);
    $assetModule = (string) ($page['asset_action_module'] ?? '');
    $direction = (string) ($page['asset_action_direction'] ?? 'grant');
    $amount = (int) ($page['asset_action_amount'] ?? 0);

    if ($pageId <= 0 || $accountId <= 0 || !sr_page_asset_action_required($page)) {
        return ['allowed' => false, 'completed' => false, 'message' => '완료 액션을 사용할 수 없습니다.'];
    }

    if (!isset(sr_page_asset_modules()[$assetModule]) || !isset(sr_page_asset_action_directions()[$direction])) {
        return ['allowed' => false, 'completed' => false, 'message' => '페이지 완료 액션 설정이 올바르지 않습니다.'];
    }

    if (!sr_page_asset_module_is_available($pdo, $assetModule)) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 모듈을 사용할 수 없어 완료 처리할 수 없습니다.',
        ];
    }

    if (sr_page_has_completed_asset_action($pdo, $assetModule, $accountId, $pageId)) {
        return [
            'allowed' => true,
            'completed' => false,
            'already_completed' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '이미 완료 처리되었습니다.',
        ];
    }

    if ($direction === 'use' && sr_page_asset_balance($pdo, $assetModule, $accountId) < $amount) {
        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 잔액이 부족해 완료 처리할 수 없습니다.',
        ];
    }

    $dedupeKey = sr_page_asset_action_dedupe_key($assetModule, $accountId, $pageId);
    $inserted = sr_page_insert_asset_action_placeholder($pdo, $pageId, $accountId, $assetModule, $direction, $amount, $dedupeKey);
    if (!$inserted) {
        return [
            'allowed' => sr_page_has_completed_asset_action($pdo, $assetModule, $accountId, $pageId),
            'completed' => false,
            'already_completed' => true,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => '이미 완료 처리되었습니다.',
        ];
    }

    $assetOption = sr_page_asset_modules()[$assetModule];
    $signedAmount = $direction === 'grant' ? $amount : -$amount;
    $transactionType = $direction === 'grant'
        ? (string) ($assetOption['credit_type'] ?? 'grant')
        : (string) ($assetOption['use_type'] ?? 'use');

    try {
        $transactionId = sr_page_create_asset_transaction($pdo, $assetModule, [
            'account_id' => $accountId,
            'amount' => $signedAmount,
            'transaction_type' => $transactionType,
            'reason' => '페이지 완료 액션',
            'reference_type' => 'page.action',
            'reference_id' => (string) $pageId,
            'created_by_account_id' => null,
        ]);
        sr_page_update_asset_action_transaction($pdo, $dedupeKey, $transactionId);
    } catch (Throwable $exception) {
        sr_page_delete_asset_action_placeholder($pdo, $dedupeKey);
        if (function_exists('sr_log_exception')) {
            sr_log_exception($exception, 'page_asset_action_failed');
        }

        return [
            'allowed' => false,
            'completed' => false,
            'asset_module' => $assetModule,
            'asset_label' => sr_page_asset_module_label($assetModule),
            'amount' => $amount,
            'message' => sr_page_asset_module_label($assetModule) . ' 처리에 실패했습니다.',
        ];
    }

    return [
        'allowed' => true,
        'completed' => true,
        'asset_module' => $assetModule,
        'asset_label' => sr_page_asset_module_label($assetModule),
        'amount' => $amount,
        'direction' => $direction,
        'message' => '',
    ];
}

function sr_page_hide(PDO $pdo, int $pageId, int $accountId): bool
{
    $page = sr_page_by_id($pdo, $pageId);
    if (!is_array($page)) {
        return false;
    }

    $now = sr_now();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "UPDATE sr_pages
             SET status = 'hidden', updated_by = :updated_by, updated_at = :updated_at
             WHERE id = :id"
        );
        $stmt->execute([
            'updated_by' => $accountId,
            'updated_at' => $now,
            'id' => $pageId,
        ]);

        $page['status'] = 'hidden';
        sr_page_record_revision($pdo, $pageId, $page, $accountId, $now);
        $pdo->commit();

        return $stmt->rowCount() > 0;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}
