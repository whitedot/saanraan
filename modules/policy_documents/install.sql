CREATE TABLE IF NOT EXISTS sr_policy_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_key VARCHAR(80) NOT NULL,
    document_type VARCHAR(40) NOT NULL DEFAULT 'custom',
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'enabled',
    sort_order INT NOT NULL DEFAULT 100,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_policy_documents_key (document_key),
    KEY idx_sr_policy_documents_status_sort (status, sort_order, id)
);

CREATE TABLE IF NOT EXISTS sr_policy_document_versions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    version_key VARCHAR(40) NOT NULL,
    title_snapshot VARCHAR(190) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    summary_text TEXT NULL,
    body_hash CHAR(64) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    effective_from DATETIME NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_policy_document_versions_key (document_id, version_key),
    KEY idx_sr_policy_document_versions_status (document_id, status, effective_from, published_at, id)
);

CREATE TABLE IF NOT EXISTS sr_policy_document_mail_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_id BIGINT UNSIGNED NOT NULL,
    version_id BIGINT UNSIGNED NOT NULL,
    job_key VARCHAR(120) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    target_status_snapshot VARCHAR(120) NOT NULL DEFAULT 'active',
    subject_snapshot VARCHAR(190) NOT NULL,
    body_snapshot TEXT NOT NULL,
    dry_run TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_policy_document_mail_jobs_key (job_key),
    KEY idx_sr_policy_document_mail_jobs_status (status, id)
);

CREATE TABLE IF NOT EXISTS sr_policy_document_mail_deliveries (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    account_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'queued',
    failure_code VARCHAR(80) NOT NULL DEFAULT '',
    claimed_at DATETIME NULL,
    sent_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_policy_document_mail_delivery_account (job_id, account_id),
    KEY idx_sr_policy_document_mail_deliveries_account (account_id),
    KEY idx_sr_policy_document_mail_deliveries_status (job_id, status, id)
);

INSERT INTO sr_policy_documents (document_key, document_type, title, description, status, sort_order, created_at, updated_at)
SELECT seed.document_key, seed.document_type, seed.title, seed.description, 'enabled', seed.sort_order, NOW(), NOW()
FROM (
    SELECT 'member_terms' AS document_key, 'terms' AS document_type, '이용약관' AS title, '회원가입 필수 이용약관 문서입니다.' AS description, 10 AS sort_order
    UNION ALL SELECT 'member_privacy_collection', 'privacy_collection', '개인정보 수집 및 이용 동의', '회원가입 필수 개인정보 수집 및 이용 동의 문서입니다.', 20
    UNION ALL SELECT 'member_privacy_policy', 'privacy_policy', '개인정보처리방침', '공개 개인정보처리방침 문서입니다.', 30
    UNION ALL SELECT 'member_marketing', 'marketing_consent', '마케팅 수신 동의', '선택 마케팅 수신 동의 문서입니다.', 40
    UNION ALL SELECT 'community_privacy_default', 'privacy_collection', '게시판 개인정보 수집 및 이용 동의', '커뮤니티 제출 개인정보 수집 및 이용 동의 기본 문서입니다.', 50
) seed
LEFT JOIN sr_policy_documents existing_document ON existing_document.document_key = seed.document_key
WHERE existing_document.id IS NULL;
