<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/core/helpers/common.php';

function sr_content_coupon_target_search(PDO $pdo, string $targetType, string $keyword, int $limit = 20): array
{
    if (!in_array($targetType, ['content', 'content_file'], true)) {
        return [];
    }

    $keyword = sr_content_clean_text($keyword, 120);
    $limit = max(1, min(30, $limit));
    if ($targetType === 'content_file') {
        $where = $keyword === '' ? '1 = 1' : '(f.id = :id OR f.title LIKE :keyword_title OR f.original_name LIKE :keyword_original)';
        $params = [];
        if ($keyword !== '') {
            $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
            $params = [
                'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
                'keyword_title' => $keywordLike,
                'keyword_original' => $keywordLike,
            ];
        }

        $stmt = $pdo->prepare(
            'SELECT f.id, f.content_id, f.title, f.original_name, f.status, f.updated_at, c.title AS content_title
             FROM sr_content_files f
             LEFT JOIN sr_content_items c ON c.id = f.content_id
             WHERE ' . $where . '
             ORDER BY f.id DESC
             LIMIT ' . $limit
        );
        $stmt->execute($params);

        return array_map(static function (array $row): array {
            return [
                'reference_type' => 'content_file',
                'reference_id' => (string) (int) ($row['id'] ?? 0),
                'title' => (string) ($row['title'] ?? ''),
                'reason' => '다운로드 파일 #' . (string) (int) ($row['id'] ?? 0),
                'member_name' => '콘텐츠: ' . (string) ($row['content_title'] ?? ''),
                'member_email' => '상태: ' . (string) ($row['status'] ?? ''),
                'created_at' => (string) ($row['updated_at'] ?? ''),
            ];
        }, $stmt->fetchAll());
    }

    $where = $keyword === '' ? '1 = 1' : "(id = :id OR title LIKE :keyword_title ESCAPE '\\\\' OR slug LIKE :keyword_slug ESCAPE '\\\\')";
    $params = [];
    if ($keyword !== '') {
        $keywordLike = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $keyword) . '%';
        $params = [
            'id' => preg_match('/\A[1-9][0-9]*\z/', $keyword) === 1 ? (int) $keyword : 0,
            'keyword_title' => $keywordLike,
            'keyword_slug' => $keywordLike,
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, slug, status, updated_at
         FROM sr_content_items
         WHERE ' . $where . '
         ORDER BY id DESC
         LIMIT ' . $limit
    );
    $stmt->execute($params);

    return array_map(static function (array $row): array {
        return [
            'reference_type' => 'content',
            'reference_id' => (string) (int) ($row['id'] ?? 0),
            'title' => (string) ($row['title'] ?? ''),
            'reason' => '콘텐츠 #' . (string) (int) ($row['id'] ?? 0),
            'member_name' => 'slug: ' . (string) ($row['slug'] ?? ''),
            'member_email' => '상태: ' . (string) ($row['status'] ?? ''),
            'created_at' => (string) ($row['updated_at'] ?? ''),
        ];
    }, $stmt->fetchAll());
}

function sr_content_coupon_revoke_access(PDO $pdo, int $accountId, string $dedupeKey): int
{
    require_once SR_ROOT . '/modules/content/helpers/assets.php';
    return sr_content_revoke_coupon_access_entitlements($pdo, $accountId, $dedupeKey);
}

function sr_content_coupon_target_health(PDO $pdo, string $targetType, string $targetId): array
{
    if (!in_array($targetType, ['content', 'content_file'], true) || preg_match('/\A[1-9][0-9]*\z/', $targetId) !== 1) {
        return ['status' => 'unknown', 'message' => '콘텐츠 대상 형식이 올바르지 않습니다.'];
    }

    if ($targetType === 'content_file') {
        $stmt = $pdo->prepare('SELECT id, status FROM sr_content_files WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => (int) $targetId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return ['status' => 'missing_target', 'message' => '다운로드 파일을 찾을 수 없습니다.'];
        }

        $status = (string) ($row['status'] ?? '');
        return $status === 'active'
            ? ['status' => 'ok', 'policy_status' => $status]
            : ['status' => 'disabled_target', 'policy_status' => $status, 'message' => '다운로드 파일이 사용 상태가 아닙니다.'];
    }

    $stmt = $pdo->prepare('SELECT id, status FROM sr_content_items WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $targetId]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return ['status' => 'missing_target', 'message' => '콘텐츠를 찾을 수 없습니다.'];
    }

    $status = (string) ($row['status'] ?? '');
    return in_array($status, ['published', 'scheduled'], true)
        ? ['status' => 'ok', 'policy_status' => $status]
        : ['status' => 'disabled_target', 'policy_status' => $status, 'message' => '콘텐츠가 공개 사용 상태가 아닙니다.'];
}

function sr_content_coupon_target_admin_url(string $targetType, string $targetId): string
{
    if (!in_array($targetType, ['content', 'content_file'], true) || preg_match('/\A[1-9][0-9]*\z/', $targetId) !== 1) {
        return '';
    }

    if ($targetType === 'content_file') {
        return '/admin/content/files?id=' . rawurlencode($targetId);
    }

    return '/admin/content/edit?id=' . rawurlencode($targetId);
}

function sr_content_coupon_target_pricing(PDO $pdo, string $targetType, string $targetId, int $accountId = 0, array $context = []): array
{
    if (!in_array($targetType, ['content', 'content_file'], true) || preg_match('/\A[1-9][0-9]*\z/', $targetId) !== 1) {
        return ['ok' => false, 'failure_code' => 'target_invalid', 'failure_message' => '콘텐츠 대상 형식이 올바르지 않습니다.'];
    }

    require_once SR_ROOT . '/modules/content/helpers/assets.php';
    if ($targetType === 'content_file') {
        $stmt = $pdo->prepare(
            'SELECT id, content_id, asset_download_enabled, asset_module, asset_download_amount, asset_download_amounts_json, asset_download_settlement_currency, asset_charge_policy
             FROM sr_content_files
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => (int) $targetId]);
        $row = $stmt->fetch();
        if (!is_array($row) || (int) ($row['asset_download_enabled'] ?? 0) !== 1) {
            return ['ok' => false, 'failure_code' => 'target_unavailable', 'failure_message' => '유료 다운로드 대상이 아닙니다.'];
        }

        $assetModules = sr_content_asset_module_keys_from_value($row['asset_module'] ?? '');
        $amounts = sr_content_asset_amounts_from_value($row['asset_download_amounts_json'] ?? '', $assetModules, (int) ($row['asset_download_amount'] ?? 0));
        $amount = sr_content_asset_amount_total($amounts, (int) ($row['asset_download_amount'] ?? 0));

        return [
            'ok' => true,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'price_amount' => $amount,
            'currency_code' => (string) ($row['asset_download_settlement_currency'] ?? 'KRW'),
            'asset_unit' => '',
            'already_entitled' => $accountId > 0 && sr_content_coupon_target_has_entitlement($pdo, $accountId, (int) ($row['content_id'] ?? 0), 'content_file', (int) $targetId, 'download'),
            'policy_summary' => '콘텐츠 다운로드 ' . number_format($amount) . (string) ($row['asset_download_settlement_currency'] ?? 'KRW'),
            'priced_at' => sr_now(),
        ];
    }

    $stmt = $pdo->prepare(
        'SELECT id, asset_access_enabled, asset_module, asset_access_amount, asset_access_amounts_json, asset_access_settlement_currency, asset_charge_policy
         FROM sr_content_items
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => (int) $targetId]);
    $row = $stmt->fetch();
    if (!is_array($row) || (int) ($row['asset_access_enabled'] ?? 0) !== 1) {
        return ['ok' => false, 'failure_code' => 'target_unavailable', 'failure_message' => '유료 열람 대상이 아닙니다.'];
    }

    $assetModules = sr_content_asset_module_keys_from_value($row['asset_module'] ?? '');
    $amounts = sr_content_asset_amounts_from_value($row['asset_access_amounts_json'] ?? '', $assetModules, (int) ($row['asset_access_amount'] ?? 0));
    $amount = sr_content_asset_amount_total($amounts, (int) ($row['asset_access_amount'] ?? 0));

    return [
        'ok' => true,
        'target_type' => $targetType,
        'target_id' => $targetId,
        'price_amount' => $amount,
        'currency_code' => (string) ($row['asset_access_settlement_currency'] ?? 'KRW'),
        'asset_unit' => '',
        'already_entitled' => $accountId > 0 && sr_content_coupon_target_has_entitlement($pdo, $accountId, (int) $targetId, 'content', (int) $targetId, 'view'),
        'policy_summary' => '콘텐츠 열람 ' . number_format($amount) . (string) ($row['asset_access_settlement_currency'] ?? 'KRW'),
        'priced_at' => sr_now(),
    ];
}

function sr_content_coupon_target_has_entitlement(PDO $pdo, int $accountId, int $contentId, string $subjectType, int $subjectId, string $accessKind): bool
{
    if ($accountId <= 0 || $contentId <= 0 || $subjectId <= 0) {
        return false;
    }

    $stmt = $pdo->prepare(
        'SELECT id
         FROM sr_content_access_entitlements
         WHERE account_id = :account_id
           AND content_id = :content_id
           AND subject_type = :subject_type
           AND subject_id = :subject_id
           AND access_kind = :access_kind
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'content_id' => $contentId,
        'subject_type' => $subjectType,
        'subject_id' => $subjectId,
        'access_kind' => $accessKind,
    ]);

    return is_array($stmt->fetch());
}

function sr_content_banner_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_content_banner_reference_rows($pdo, $target, $context));
}

function sr_content_banner_reference_rows(PDO $pdo, array $target, array $context): array
{
    $bannerId = (int) ($target['target_id'] ?? 0);
    if ($bannerId <= 0) {
        return [];
    }

    return array_merge(
        sr_content_display_reference_item_rows($pdo, 'banner', $bannerId, sr_content_public_banner_setting_labels()),
        sr_content_display_reference_group_setting_rows($pdo, 'banner', $bannerId, sr_content_public_banner_setting_labels())
    );
}

function sr_content_popup_layer_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_content_popup_layer_reference_rows($pdo, $target, $context));
}

function sr_content_popup_layer_reference_rows(PDO $pdo, array $target, array $context): array
{
    $popupLayerId = (int) ($target['target_id'] ?? 0);
    if ($popupLayerId <= 0) {
        return [];
    }

    return array_merge(
        sr_content_display_reference_item_rows($pdo, 'popup_layer', $popupLayerId, sr_content_public_popup_layer_setting_labels()),
        sr_content_display_reference_group_setting_rows($pdo, 'popup_layer', $popupLayerId, sr_content_public_popup_layer_setting_labels())
    );
}

function sr_content_display_reference_item_rows(PDO $pdo, string $kind, int $targetId, array $labels): array
{
    $columns = $kind === 'banner' ? array_keys(sr_content_public_banner_setting_labels()) : array_keys(sr_content_public_popup_layer_setting_labels());
    $conditions = [];
    $params = [];
    foreach ($columns as $index => $column) {
        $paramKey = 'target_id_' . (string) $index;
        $conditions[] = $column . ' = :' . $paramKey;
        $params[$paramKey] = $targetId;
    }

    $stmt = $pdo->prepare(
        'SELECT id, title, status, updated_at, ' . implode(', ', $columns) . '
         FROM sr_content_items
         WHERE ' . implode(' OR ', $conditions) . '
         ORDER BY id DESC'
    );
    $stmt->execute($params);

    $rows = [];
    foreach ($stmt->fetchAll() as $item) {
        foreach ($columns as $column) {
            if ((int) ($item[$column] ?? 0) !== $targetId) {
                continue;
            }
            $rows[] = [
                'consumer_module_key' => 'content',
                'reference_type' => $kind === 'banner' ? 'content_banner' : 'content_popup_layer',
                'reference_id' => 'content:' . (string) (int) ($item['id'] ?? 0) . ':' . $column,
                'title' => (string) ($item['title'] ?? '') . ' / ' . (string) ($labels[$column] ?? $column),
                'target_type' => $kind,
                'target_id' => (string) $targetId,
                'policy_status' => (string) ($item['status'] ?? ''),
                'updated_at' => (string) ($item['updated_at'] ?? ''),
                'metadata' => ['content_id' => (int) ($item['id'] ?? 0), 'setting_key' => $column],
            ];
        }
    }

    return $rows;
}

function sr_content_display_reference_group_setting_rows(PDO $pdo, string $kind, int $targetId, array $labels): array
{
    if (!sr_content_group_settings_table_exists($pdo) || !sr_content_groups_table_exists($pdo)) {
        return [];
    }

    $settingKeys = $kind === 'banner' ? array_keys(sr_content_public_banner_setting_labels()) : array_keys(sr_content_public_popup_layer_setting_labels());
    $placeholders = [];
    $params = ['target_id' => (string) $targetId];
    foreach ($settingKeys as $index => $settingKey) {
        $paramKey = 'setting_key_' . (string) $index;
        $placeholders[] = ':' . $paramKey;
        $params[$paramKey] = $settingKey;
    }

    $stmt = $pdo->prepare(
        'SELECT s.group_id, s.setting_key, s.setting_value, s.updated_at, g.title, g.status
         FROM sr_content_group_settings s
         INNER JOIN sr_content_groups g ON g.id = s.group_id
         WHERE s.setting_key IN (' . implode(', ', $placeholders) . ')
           AND s.setting_value = :target_id
         ORDER BY s.group_id DESC'
    );
    $stmt->execute($params);

    return array_map(static function (array $row) use ($kind, $targetId, $labels): array {
        $settingKey = (string) ($row['setting_key'] ?? '');
        return [
            'consumer_module_key' => 'content',
            'reference_type' => $kind === 'banner' ? 'content_banner' : 'content_popup_layer',
            'reference_id' => 'content_group:' . (string) (int) ($row['group_id'] ?? 0) . ':' . $settingKey,
            'title' => (string) ($row['title'] ?? '') . ' / ' . (string) ($labels[$settingKey] ?? $settingKey),
            'target_type' => $kind,
            'target_id' => (string) $targetId,
            'policy_status' => (string) ($row['status'] ?? ''),
            'updated_at' => (string) ($row['updated_at'] ?? ''),
            'metadata' => ['content_group_id' => (int) ($row['group_id'] ?? 0), 'setting_key' => $settingKey],
        ];
    }, $stmt->fetchAll());
}

function sr_content_display_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    $status = (string) ($row['policy_status'] ?? '');
    if (in_array($status, ['published', 'scheduled', 'enabled'], true)) {
        return ['status' => 'ok', 'policy_status' => $status];
    }
    if ($status !== '') {
        return ['status' => 'disabled_target', 'policy_status' => $status];
    }

    return ['status' => 'unknown'];
}

function sr_content_display_reference_admin_url(array $row, array $context): string
{
    $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
    $contentId = (int) ($metadata['content_id'] ?? 0);
    if ($contentId > 0) {
        return '/admin/content/edit?id=' . rawurlencode((string) $contentId);
    }

    $groupId = (int) ($metadata['content_group_id'] ?? 0);
    if ($groupId > 0) {
        return '/admin/content-groups/edit?id=' . rawurlencode((string) $groupId);
    }

    return '/admin/content';
}

function sr_content_member_group_reference_count(PDO $pdo, array $target, array $context): int
{
    return count(sr_content_member_group_reference_rows($pdo, $target, $context));
}

function sr_content_member_group_reference_rows(PDO $pdo, array $target, array $context): array
{
    $groupKey = (string) ($target['target_key'] ?? '');
    if ($groupKey === '') {
        return [];
    }

    $like = '%"group_key":"' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $groupKey) . '"%';
    $rows = [];
    $stmt = $pdo->prepare(
        'SELECT id, title, status, updated_at
         FROM sr_content_items
         WHERE asset_access_group_policies_json LIKE :group_key_access ESCAPE \'\\\\\'
            OR asset_action_group_policies_json LIKE :group_key_action ESCAPE \'\\\\\'
         ORDER BY id DESC'
    );
    $stmt->execute([
        'group_key_access' => $like,
        'group_key_action' => $like,
    ]);
    foreach ($stmt->fetchAll() as $item) {
        $rows[] = [
            'consumer_module_key' => 'content',
            'reference_type' => 'content_member_group_policy',
            'reference_id' => 'content:' . (string) (int) ($item['id'] ?? 0),
            'title' => (string) ($item['title'] ?? ''),
            'target_type' => 'member_group',
            'target_id' => (string) (int) ($target['target_id'] ?? 0),
            'target_key' => $groupKey,
            'policy_status' => (string) ($item['status'] ?? ''),
            'updated_at' => (string) ($item['updated_at'] ?? ''),
            'metadata' => ['content_id' => (int) ($item['id'] ?? 0)],
        ];
    }

    return $rows;
}

function sr_content_member_group_reference_health(PDO $pdo, array $target, array $row, array $context): array
{
    return sr_content_display_reference_health($pdo, $target, $row, $context);
}

function sr_content_public_banner_setting_labels(): array
{
    return [
        'banner_before_content_id' => '본문 상단 배너',
        'banner_after_content_id' => '본문 하단 배너',
    ];
}

function sr_content_public_popup_layer_setting_labels(): array
{
    return [
        'popup_layer_id' => '콘텐츠 팝업레이어',
    ];
}

function sr_content_public_display_setting_labels(): array
{
    return sr_content_public_banner_setting_labels() + sr_content_public_popup_layer_setting_labels();
}
