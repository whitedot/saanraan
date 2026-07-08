CREATE TABLE IF NOT EXISTS sr_policy_documents (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    document_key VARCHAR(80) NOT NULL,
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
    title_snapshot VARCHAR(190) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    summary_text TEXT NULL,
    body_hash CHAR(64) NOT NULL,
    append_previous_versions TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    effective_from DATETIME NULL,
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
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

INSERT INTO sr_policy_documents (document_key, title, description, status, sort_order, created_at, updated_at)
SELECT seed.document_key, seed.title, seed.description, 'enabled', seed.sort_order, NOW(), NOW()
FROM (
    SELECT 'member_terms' AS document_key, '이용약관' AS title, '회원가입 필수 이용약관 문서입니다.' AS description, 10 AS sort_order
    UNION ALL SELECT 'member_privacy_collection', '개인정보 수집 및 이용 동의', '회원가입 필수 개인정보 수집 및 이용 동의 문서입니다.', 20
    UNION ALL SELECT 'member_privacy_policy', '개인정보처리방침', '공개 개인정보처리방침 문서입니다.', 30
    UNION ALL SELECT 'member_marketing', '마케팅 수신 동의', '선택 마케팅 수신 동의 문서입니다.', 40
    UNION ALL SELECT 'community_privacy_default', '게시판 개인정보 수집 및 이용 동의', '커뮤니티 제출 개인정보 수집 및 이용 동의 기본 문서입니다.', 50
) seed
LEFT JOIN sr_policy_documents existing_document ON existing_document.document_key = seed.document_key
WHERE existing_document.id IS NULL;

INSERT INTO sr_policy_document_versions
    (document_id, title_snapshot, body_html, summary_text, body_hash, append_previous_versions, status, effective_from, published_at, created_at, updated_at)
SELECT d.id,
       d.title,
       seed.body_html,
       seed.summary_text,
       SHA2(seed.body_html, 256),
       0,
       'published',
       NOW(),
       NOW(),
       NOW(),
       NOW()
FROM sr_policy_documents d
INNER JOIN (
    SELECT 'member_terms' AS document_key, '<p>서비스 이용 조건과 회원의 기본 의무에 동의합니다.</p>' AS body_html, '회원가입 필수 이용약관입니다.' AS summary_text
    UNION ALL SELECT 'member_privacy_collection', '<p>회원가입과 서비스 제공에 필요한 개인정보 수집 및 이용에 동의합니다.</p>', '회원가입 필수 개인정보 수집 및 이용 동의입니다.'
    UNION ALL SELECT 'member_privacy_policy', '<p>개인정보 처리 목적, 보관 기간, 권리 행사 방법을 안내합니다.</p><p>관계 법령에 따른 주요 보존기간은 계약 또는 청약철회 기록 5년, 대금결제 및 재화 등의 공급 기록 5년, 소비자 불만 또는 분쟁처리 기록 3년, 표시·광고 기록 6개월입니다. 다른 법령에 따라 보존해야 하는 개인정보는 다른 개인정보와 분리하여 저장·관리합니다.</p>', '공개 개인정보처리방침입니다.'
    UNION ALL SELECT 'member_marketing', '<p>서비스 소식과 혜택 안내 수신에 동의합니다.</p>', '선택 마케팅 수신 동의입니다.'
    UNION ALL SELECT 'community_privacy_default', '<p>게시판 제출과 첨부 처리에 필요한 개인정보 수집 및 이용에 동의합니다.</p>', '게시판 제출 개인정보 수집 및 이용 동의입니다.'
) seed ON seed.document_key = d.document_key
LEFT JOIN sr_policy_document_versions existing_version
    ON existing_version.document_id = d.id
   AND existing_version.status = 'published'
WHERE existing_version.id IS NULL;
