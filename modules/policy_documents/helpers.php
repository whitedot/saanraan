<?php

declare(strict_types=1);

function sr_policy_document_valid_key(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{2,79}\z/', $key) === 1;
}

function sr_policy_document_valid_version_key(string $key): bool
{
    return preg_match('/\A[0-9]{4}\.[0-9]{2}\.[0-9]{3}\z/', $key) === 1
        || preg_match('/\Av[0-9][a-z0-9_]{0,38}\z/', $key) === 1;
}

function sr_policy_document_body_hash(string $bodyHtml): string
{
    return hash('sha256', $bodyHtml);
}

function sr_policy_document_sanitize_body(string $bodyHtml): string
{
    return sr_sanitize_rich_text_html($bodyHtml);
}

function sr_policy_document_by_key(PDO $pdo, string $documentKey): ?array
{
    if (!sr_policy_document_valid_key($documentKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, document_key, document_type, title, description, status, sort_order, created_at, updated_at
         FROM sr_policy_documents
         WHERE document_key = :document_key
         LIMIT 1'
    );
    $stmt->execute(['document_key' => $documentKey]);
    $document = $stmt->fetch();

    return is_array($document) ? $document : null;
}

function sr_policy_document_published_version(PDO $pdo, string $documentKey, ?string $at = null): ?array
{
    $document = sr_policy_document_by_key($pdo, $documentKey);
    if (!is_array($document) || (string) ($document['status'] ?? '') !== 'enabled') {
        return null;
    }

    $effectiveAt = $at !== null && $at !== '' ? $at : sr_now();
    $stmt = $pdo->prepare(
        'SELECT v.id, v.document_id, v.version_key, v.title_snapshot, v.body_html, v.summary_text,
                v.body_hash, v.status, v.effective_from, v.published_at, v.created_at, v.updated_at,
                d.document_key, d.document_type, d.title AS document_title
         FROM sr_policy_document_versions v
         INNER JOIN sr_policy_documents d ON d.id = v.document_id
         WHERE d.id = :document_id
           AND d.status = "enabled"
           AND v.status = "published"
           AND (v.effective_from IS NULL OR v.effective_from <= :effective_at)
         ORDER BY COALESCE(v.effective_from, v.published_at, v.created_at) DESC, v.id DESC
         LIMIT 1'
    );
    $stmt->execute([
        'document_id' => (int) $document['id'],
        'effective_at' => $effectiveAt,
    ]);
    $version = $stmt->fetch();

    return is_array($version) ? $version : null;
}

function sr_policy_document_snapshot(PDO $pdo, string $documentKey): array
{
    $version = sr_policy_document_published_version($pdo, $documentKey);
    if (!is_array($version)) {
        throw new RuntimeException('Published policy document version is missing: ' . $documentKey);
    }

    return [
        'document_id' => (int) $version['document_id'],
        'document_key' => (string) $version['document_key'],
        'document_type' => (string) $version['document_type'],
        'version_id' => (int) $version['id'],
        'version_key' => (string) $version['version_key'],
        'title' => (string) $version['title_snapshot'],
        'body_hash' => (string) $version['body_hash'],
        'summary_text' => (string) ($version['summary_text'] ?? ''),
        'published_at' => (string) ($version['published_at'] ?? ''),
        'effective_from' => (string) ($version['effective_from'] ?? ''),
    ];
}

function sr_policy_document_public_render_data(PDO $pdo, string $documentKey): ?array
{
    $version = sr_policy_document_published_version($pdo, $documentKey);
    if (!is_array($version)) {
        return null;
    }

    return [
        'document_key' => (string) $version['document_key'],
        'version_id' => (int) $version['id'],
        'version_key' => (string) $version['version_key'],
        'title' => (string) $version['title_snapshot'],
        'body_html' => (string) $version['body_html'],
        'body_hash' => (string) $version['body_hash'],
        'published_at' => (string) ($version['published_at'] ?? ''),
        'effective_from' => (string) ($version['effective_from'] ?? ''),
    ];
}

function sr_policy_document_enabled_choices(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT d.id, d.document_key, d.document_type, d.title, d.status,
                v.id AS published_version_id, v.version_key AS published_version_key, v.published_at
         FROM sr_policy_documents d
         LEFT JOIN sr_policy_document_versions v ON v.id = (
            SELECT pv.id
            FROM sr_policy_document_versions pv
            WHERE pv.document_id = d.id AND pv.status = "published"
            ORDER BY COALESCE(pv.effective_from, pv.published_at, pv.created_at) DESC, pv.id DESC
            LIMIT 1
         )
         WHERE d.status = "enabled"
         ORDER BY d.sort_order ASC, d.id ASC'
    );

    return $stmt->fetchAll();
}

function sr_policy_documents_with_current_versions(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT d.id, d.document_key, d.document_type, d.title, d.description, d.status, d.sort_order,
                d.created_at, d.updated_at,
                v.id AS published_version_id, v.version_key AS published_version_key, v.published_at
         FROM sr_policy_documents d
         LEFT JOIN sr_policy_document_versions v ON v.id = (
            SELECT pv.id
            FROM sr_policy_document_versions pv
            WHERE pv.document_id = d.id AND pv.status = "published"
            ORDER BY COALESCE(pv.effective_from, pv.published_at, pv.created_at) DESC, pv.id DESC
            LIMIT 1
         )
         ORDER BY d.sort_order ASC, d.id ASC'
    );

    return $stmt->fetchAll();
}

function sr_policy_document_versions(PDO $pdo, int $documentId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, document_id, version_key, title_snapshot, summary_text, body_hash, status,
                effective_from, published_at, created_at, updated_at
         FROM sr_policy_document_versions
         WHERE document_id = :document_id
         ORDER BY id DESC'
    );
    $stmt->execute(['document_id' => $documentId]);

    return $stmt->fetchAll();
}

function sr_policy_document_create_version(PDO $pdo, int $documentId, array $data): int
{
    $versionKey = trim((string) ($data['version_key'] ?? ''));
    $title = sr_clean_single_line((string) ($data['title'] ?? ''), 190);
    $bodyHtml = sr_policy_document_sanitize_body((string) ($data['body_html'] ?? ''));
    $summaryText = sr_clean_text((string) ($data['summary_text'] ?? ''), 1000);
    $status = (string) ($data['status'] ?? 'draft');
    $effectiveFrom = sr_clean_admin_datetime((string) ($data['effective_from'] ?? ''));
    $allowedStatuses = ['draft', 'published', 'archived'];

    if (!sr_policy_document_valid_version_key($versionKey)) {
        throw new InvalidArgumentException(sr_t('policy_documents::error.version_key_invalid'));
    }
    if ($title === '') {
        throw new InvalidArgumentException(sr_t('policy_documents::error.title_required'));
    }
    if (trim(strip_tags($bodyHtml)) === '') {
        throw new InvalidArgumentException(sr_t('policy_documents::error.body_required'));
    }
    if (!in_array($status, $allowedStatuses, true)) {
        $status = 'draft';
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_policy_document_versions
            (document_id, version_key, title_snapshot, body_html, summary_text, body_hash, status, effective_from, published_at, created_at, updated_at)
         VALUES
            (:document_id, :version_key, :title_snapshot, :body_html, :summary_text, :body_hash, :status, :effective_from, :published_at, :created_at, :updated_at)'
    );
    $stmt->execute([
        'document_id' => $documentId,
        'version_key' => $versionKey,
        'title_snapshot' => $title,
        'body_html' => $bodyHtml,
        'summary_text' => $summaryText,
        'body_hash' => sr_policy_document_body_hash($bodyHtml),
        'status' => $status,
        'effective_from' => $effectiveFrom,
        'published_at' => $status === 'published' ? $now : null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_policy_document_create_notice_job(PDO $pdo, int $documentId, int $versionId, string $subject, string $body, bool $dryRun = false): int
{
    $jobKey = 'policy_document_' . (string) $versionId . '_notice';
    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_policy_document_mail_jobs
            (document_id, version_id, job_key, status, target_status_snapshot, subject_snapshot, body_snapshot, dry_run, created_at, updated_at)
         VALUES
            (:document_id, :version_id, :job_key, "queued", "active", :subject_snapshot, :body_snapshot, :dry_run, :created_at, :updated_at)'
    );
    $stmt->execute([
        'document_id' => $documentId,
        'version_id' => $versionId,
        'job_key' => $jobKey,
        'subject_snapshot' => sr_clean_single_line($subject, 190),
        'body_snapshot' => sr_clean_text($body, 4000),
        'dry_run' => $dryRun ? 1 : 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_policy_document_module_ready(PDO $pdo): bool
{
    return sr_module_enabled($pdo, 'policy_documents')
        && sr_policy_document_table_exists($pdo, 'sr_policy_documents')
        && sr_policy_document_table_exists($pdo, 'sr_policy_document_versions');
}

function sr_policy_document_table_exists(PDO $pdo, string $table): bool
{
    if (!preg_match('/\Asr_[a-z0-9_]+\z/', $table)) {
        return false;
    }

    try {
        $stmt = $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        return $stmt !== false;
    } catch (Throwable) {
        return false;
    }
}
