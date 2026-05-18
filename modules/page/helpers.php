<?php

declare(strict_types=1);

function sr_page_allowed_statuses(): array
{
    return ['draft', 'published', 'hidden'];
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

    return [
        'status' => $status,
        'q' => sr_page_clean_single_line(sr_get_string('q', 120), 120),
    ];
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
        $where[] = '(p.title LIKE :keyword OR p.slug LIKE :keyword)';
        $params['keyword'] = '%' . (string) $filters['q'] . '%';
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

function sr_page_input_values(): array
{
    $values = [
        'title' => sr_page_clean_single_line(sr_post_string('title', 160), 160),
        'slug' => sr_page_clean_slug(sr_post_string('slug', 120)),
        'summary' => sr_page_clean_text(sr_post_string('summary', 1000), 1000),
        'body_text' => sr_page_clean_text(sr_post_string('body_text', 100000), 100000),
        'body_format' => 'plain',
        'status' => sr_post_string('status', 30),
        'seo_title' => sr_page_clean_single_line(sr_post_string('seo_title', 160), 160),
        'seo_description' => sr_page_clean_single_line(sr_post_string('seo_description', 255), 255),
    ];

    foreach (sr_page_public_display_setting_labels() as $settingKey => $settingLabel) {
        $rawValue = sr_post_string($settingKey, 20);
        $values[$settingKey] = preg_match('/\A[0-9]{1,9}\z/', $rawValue) === 1 ? (int) $rawValue : -1;
    }

    return $values;
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
                    (slug, title, summary, body_text, body_format, status, banner_before_content_id, banner_after_content_id, popup_layer_id, seo_title, seo_description, created_by, updated_by, published_at, created_at, updated_at)
                 VALUES
                    (:slug, :title, :summary, :body_text, :body_format, :status, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :seo_title, :seo_description, :created_by, :updated_by, :published_at, :created_at, :updated_at)'
            );
            $stmt->execute([
                'slug' => (string) $values['slug'],
                'title' => (string) $values['title'],
                'summary' => (string) $values['summary'],
                'body_text' => (string) $values['body_text'],
                'body_format' => 'plain',
                'status' => (string) $values['status'],
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
            (page_id, title, summary, body_text, body_format, status, banner_before_content_id, banner_after_content_id, popup_layer_id, created_by, created_at)
         VALUES
            (:page_id, :title, :summary, :body_text, :body_format, :status, :banner_before_content_id, :banner_after_content_id, :popup_layer_id, :created_by, :created_at)'
    );
    $stmt->execute([
        'page_id' => $pageId,
        'title' => (string) $values['title'],
        'summary' => (string) $values['summary'],
        'body_text' => (string) $values['body_text'],
        'body_format' => 'plain',
        'status' => (string) $values['status'],
        'banner_before_content_id' => (int) ($values['banner_before_content_id'] ?? 0),
        'banner_after_content_id' => (int) ($values['banner_after_content_id'] ?? 0),
        'popup_layer_id' => (int) ($values['popup_layer_id'] ?? 0),
        'created_by' => $accountId,
        'created_at' => $now,
    ]);
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
