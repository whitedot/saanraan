<?php

declare(strict_types=1);

function sr_admin_form_draft_table_exists(PDO $pdo): bool
{
    static $existsByConnection = [];
    $connectionId = spl_object_id($pdo);
    if (array_key_exists($connectionId, $existsByConnection)) {
        return $existsByConnection[$connectionId];
    }

    try {
        $pdo->query('SELECT 1 FROM sr_admin_form_drafts LIMIT 1');
        $existsByConnection[$connectionId] = true;
    } catch (Throwable $exception) {
        $existsByConnection[$connectionId] = false;
    }

    return $existsByConnection[$connectionId];
}

function sr_admin_form_draft_normalize_key(string $value, int $maxLength): string
{
    $value = trim($value);
    if ($value === '' || strlen($value) > $maxLength || preg_match('/\A[a-z0-9][a-z0-9._:-]*\z/', $value) !== 1) {
        return '';
    }

    return $value;
}

function sr_admin_form_draft_payload_value(mixed $value, int $depth = 0, int &$itemCount = 0): mixed
{
    if ($depth > 8 || $itemCount > 3000) {
        throw new InvalidArgumentException('임시저장할 입력 항목이 너무 많습니다.');
    }

    if (is_array($value)) {
        $clean = [];
        foreach ($value as $key => $item) {
            $itemCount++;
            if (!is_int($key) && (!is_string($key) || strlen($key) > 190)) {
                continue;
            }
            $clean[$key] = sr_admin_form_draft_payload_value($item, $depth + 1, $itemCount);
        }
        return $clean;
    }

    if (is_string($value)) {
        if (strlen($value) > 500000) {
            throw new InvalidArgumentException('한 입력 항목의 임시저장 크기가 너무 큽니다.');
        }
        return $value;
    }

    if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
        return $value;
    }

    return '';
}

function sr_admin_form_draft_payload(array $source): array
{
    foreach (['csrf_token', 'admin_form_action', 'profile_removed_field_values_confirmed', 'level_max_change_confirmed', 'level_max_change_confirm_text'] as $excludedKey) {
        unset($source[$excludedKey]);
    }

    $itemCount = 0;
    $payload = sr_admin_form_draft_payload_value($source, 0, $itemCount);
    if (!is_array($payload)) {
        return [];
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (strlen($json) > 1048576) {
        throw new InvalidArgumentException('임시저장 데이터는 1MB를 넘을 수 없습니다.');
    }

    return $payload;
}

function sr_admin_form_draft_get(PDO $pdo, int $accountId, string $formKey, string $contextKey = 'default'): ?array
{
    $formKey = sr_admin_form_draft_normalize_key($formKey, 80);
    $contextKey = sr_admin_form_draft_normalize_key($contextKey, 190);
    if ($accountId < 1 || $formKey === '' || $contextKey === '' || !sr_admin_form_draft_table_exists($pdo)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT payload_json, base_fingerprint, created_at, updated_at
         FROM sr_admin_form_drafts
         WHERE account_id = :account_id AND form_key = :form_key AND context_key = :context_key
         LIMIT 1'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'form_key' => $formKey,
        'context_key' => $contextKey,
    ]);
    $row = $stmt->fetch();
    if (!is_array($row)) {
        return null;
    }

    $payload = json_decode((string) ($row['payload_json'] ?? ''), true);
    if (!is_array($payload)) {
        return null;
    }

    return [
        'payload' => $payload,
        'base_fingerprint' => (string) ($row['base_fingerprint'] ?? ''),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function sr_admin_form_draft_save(PDO $pdo, int $accountId, string $formKey, string $contextKey, array $source, string $baseFingerprint = ''): void
{
    $formKey = sr_admin_form_draft_normalize_key($formKey, 80);
    $contextKey = sr_admin_form_draft_normalize_key($contextKey, 190);
    if ($accountId < 1 || $formKey === '' || $contextKey === '') {
        throw new InvalidArgumentException('임시저장 대상을 확인할 수 없습니다.');
    }
    if (!sr_admin_form_draft_table_exists($pdo)) {
        throw new RuntimeException('관리자 임시저장 DB 업데이트를 먼저 적용하세요.');
    }

    $payload = sr_admin_form_draft_payload($source);
    $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $baseFingerprint = preg_match('/\A[a-f0-9]{64}\z/', $baseFingerprint) === 1 ? $baseFingerprint : '';
    $now = sr_now();

    if ((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_admin_form_drafts
                (account_id, form_key, context_key, payload_json, base_fingerprint, created_at, updated_at)
             VALUES
                (:account_id, :form_key, :context_key, :payload_json, :base_fingerprint, :created_at, :updated_at)
             ON CONFLICT(account_id, form_key, context_key) DO UPDATE SET
                payload_json = excluded.payload_json,
                base_fingerprint = excluded.base_fingerprint,
                updated_at = excluded.updated_at'
        );
    } else {
        $stmt = $pdo->prepare(
            'INSERT INTO sr_admin_form_drafts
                (account_id, form_key, context_key, payload_json, base_fingerprint, created_at, updated_at)
             VALUES
                (:account_id, :form_key, :context_key, :payload_json, :base_fingerprint, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                payload_json = VALUES(payload_json),
                base_fingerprint = VALUES(base_fingerprint),
                updated_at = VALUES(updated_at)'
        );
    }
    $stmt->execute([
        'account_id' => $accountId,
        'form_key' => $formKey,
        'context_key' => $contextKey,
        'payload_json' => $payloadJson,
        'base_fingerprint' => $baseFingerprint,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $cleanupSelect = $pdo->prepare('SELECT id FROM sr_admin_form_drafts WHERE updated_at < :cutoff ORDER BY id ASC LIMIT 100');
    $cleanupSelect->execute(['cutoff' => date('Y-m-d H:i:s', time() - (90 * 86400))]);
    $cleanupIds = array_values(array_filter(array_map('intval', $cleanupSelect->fetchAll(PDO::FETCH_COLUMN)), static fn (int $id): bool => $id > 0));
    if ($cleanupIds !== []) {
        $placeholders = implode(',', array_fill(0, count($cleanupIds), '?'));
        $pdo->prepare('DELETE FROM sr_admin_form_drafts WHERE id IN (' . $placeholders . ')')->execute($cleanupIds);
    }
}

function sr_admin_form_draft_delete(PDO $pdo, int $accountId, string $formKey, string $contextKey = 'default'): void
{
    $formKey = sr_admin_form_draft_normalize_key($formKey, 80);
    $contextKey = sr_admin_form_draft_normalize_key($contextKey, 190);
    if ($accountId < 1 || $formKey === '' || $contextKey === '' || !sr_admin_form_draft_table_exists($pdo)) {
        return;
    }

    $stmt = $pdo->prepare(
        'DELETE FROM sr_admin_form_drafts
         WHERE account_id = :account_id AND form_key = :form_key AND context_key = :context_key'
    );
    $stmt->execute([
        'account_id' => $accountId,
        'form_key' => $formKey,
        'context_key' => $contextKey,
    ]);
}

function sr_admin_form_draft_fingerprint(array $values): string
{
    ksort($values);
    return hash('sha256', json_encode($values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));
}

function sr_admin_form_draft_with_state(?array $draft, string $currentFingerprint): ?array
{
    if (!is_array($draft)) {
        return null;
    }

    $baseFingerprint = (string) ($draft['base_fingerprint'] ?? '');
    $draft['is_stale'] = $baseFingerprint !== ''
        && preg_match('/\A[a-f0-9]{64}\z/', $currentFingerprint) === 1
        && !hash_equals($baseFingerprint, $currentFingerprint);
    return $draft;
}

function sr_admin_form_draft_with_post(array $payload, callable $callback): mixed
{
    $previousPost = $_POST;
    $_POST = $payload;
    try {
        return $callback();
    } finally {
        $_POST = $previousPost;
    }
}

function sr_admin_form_draft_apply_settings(array $settings, array $payload, array $booleanKeys = []): array
{
    foreach ($booleanKeys as $key) {
        if (array_key_exists($key, $settings)) {
            $settings[$key] = isset($payload[$key]) && (string) $payload[$key] === '1';
        }
    }

    foreach ($payload as $key => $value) {
        if (!is_string($key) || !array_key_exists($key, $settings) || is_array($value)) {
            continue;
        }
        if (in_array($key, $booleanKeys, true)) {
            continue;
        }
        if (is_int($settings[$key])) {
            $settings[$key] = (int) $value;
        } elseif (is_bool($settings[$key])) {
            $settings[$key] = (string) $value === '1';
        } else {
            $settings[$key] = (string) $value;
        }
    }

    return $settings;
}

function sr_admin_form_draft_parallel_rows(array $payload, array $fieldMap, int $maxRows = 100): array
{
    $columns = [];
    $rowCount = 0;
    foreach ($fieldMap as $outputKey => $payloadKey) {
        $values = isset($payload[$payloadKey]) && is_array($payload[$payloadKey])
            ? array_values($payload[$payloadKey])
            : [];
        $columns[(string) $outputKey] = $values;
        $rowCount = max($rowCount, count($values));
    }

    $rows = [];
    $rowCount = min(max(0, $maxRows), $rowCount);
    for ($index = 0; $index < $rowCount; $index++) {
        $row = [];
        foreach ($columns as $outputKey => $values) {
            $value = $values[$index] ?? '';
            $row[$outputKey] = is_scalar($value) ? (string) $value : '';
        }
        $rows[] = $row;
    }

    return $rows;
}

function sr_admin_form_draft_status_html(?array $draft, string $formId): string
{
    if (!is_array($draft)) {
        return '';
    }

    $updatedAt = (string) ($draft['updated_at'] ?? '');
    ob_start();
    ?>
    <div class="alert alert-info admin-form-draft-status" role="status">
        <div>
            <strong>임시저장본을 불러왔습니다.</strong>
            <span>마지막 임시저장: <?php echo sr_admin_time_html($updatedAt, '저장 시각 확인 불가'); ?></span>
            <?php if (!empty($draft['is_stale'])) { ?>
                <span>임시저장 뒤 원본이 변경되었습니다. 최종 저장 전에 현재 값을 다시 확인하세요.</span>
            <?php } ?>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
}

function sr_admin_form_draft_restore_script(?array $draft, string $formId): string
{
    $payload = is_array($draft) && isset($draft['payload']) && is_array($draft['payload']) ? $draft['payload'] : [];
    if ($payload === []) {
        return '';
    }

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    if (!is_string($json)) {
        return '';
    }

    ob_start();
    ?>
    <script>
    (() => {
        const restore = () => {
            const form = document.getElementById(<?php echo json_encode($formId); ?>);
            const payload = <?php echo $json; ?>;
            if (!form || !payload || typeof payload !== 'object') return;
            Object.entries(payload).forEach(([name, rawValue]) => {
                if (name === 'intent') return;
                const values = Array.isArray(rawValue) ? rawValue.map(String) : [String(rawValue ?? '')];
                const controls = Array.from(form.elements).filter((control) => control.name === name || control.name === `${name}[]`);
                if (!controls.length) return;
                controls.forEach((control, index) => {
                    if (control.type === 'radio') {
                        control.checked = values.includes(control.value);
                    } else if (control.type === 'checkbox') {
                        control.checked = values.includes(control.value);
                    } else if (control.multiple) {
                        Array.from(control.options).forEach((option) => { option.selected = values.includes(option.value); });
                    } else {
                        control.value = values[Math.min(index, values.length - 1)] ?? '';
                    }
                });
            });
            form.querySelectorAll('input, select, textarea').forEach((control) => {
                if (control.name && Object.prototype.hasOwnProperty.call(payload, control.name.replace(/\[\]$/, ''))) {
                    control.dispatchEvent(new Event('change', {bubbles: true}));
                }
            });
        };
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', restore, {once: true}) : restore();
    })();
    </script>
    <?php
    return (string) ob_get_clean();
}
