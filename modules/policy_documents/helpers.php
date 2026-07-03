<?php

declare(strict_types=1);

function sr_policy_document_valid_key(string $key): bool
{
    return preg_match('/\A[a-z][a-z0-9_]{2,79}\z/', $key) === 1;
}

function sr_policy_document_body_hash(string $bodyHtml): string
{
    return hash('sha256', $bodyHtml);
}

function sr_policy_document_sanitize_body(string $bodyHtml): string
{
    return sr_sanitize_rich_text_html($bodyHtml);
}

function sr_policy_document_standard_template_html(PDO $pdo, string $documentKey, ?array $site = null): string
{
    $documentKey = trim($documentKey);
    if ($documentKey === 'member_terms') {
        return sr_policy_document_standard_terms_html($pdo, $site);
    }

    if ($documentKey === 'member_privacy_policy') {
        return sr_policy_document_standard_privacy_policy_html($pdo, $site);
    }

    return '';
}

function sr_policy_document_standard_template_button_label(string $documentKey): string
{
    return match ($documentKey) {
        'member_terms' => sr_t('policy_documents::ui.standard_template.terms'),
        'member_privacy_policy' => sr_t('policy_documents::ui.standard_template.privacy_policy'),
        default => '',
    };
}

function sr_policy_document_standard_context(PDO $pdo, ?array $site = null): array
{
    $settings = sr_site_settings($pdo);
    $siteName = sr_site_display_name($site, $pdo);
    $baseUrl = trim((string) ($site['base_url'] ?? $settings['site.base_url'] ?? ''));
    $businessItems = is_array($settings['site.business_info_items'] ?? null) ? $settings['site.business_info_items'] : [];
    $business = [];
    foreach ($businessItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = strtolower(trim((string) ($item['key'] ?? '')));
        $value = sr_clean_single_line((string) ($item['value'] ?? ''), 255);
        if ($key !== '' && $value !== '') {
            $business[$key] = $value;
        }
    }

    return [
        'site_name' => $siteName,
        'base_url' => $baseUrl,
        'business' => $business,
    ];
}

function sr_policy_document_standard_value(array $context, string $key, string $default = ''): string
{
    $business = is_array($context['business'] ?? null) ? $context['business'] : [];
    $value = trim((string) ($business[$key] ?? ''));

    return $value !== '' ? $value : $default;
}

function sr_policy_document_standard_business_info_list_html(array $context): string
{
    $labels = [
        'company_name' => '상호',
        'representative_name' => '대표자명',
        'business_registration_number' => '사업자등록번호',
        'business_address' => '사업장 주소',
        'customer_service_phone' => '고객센터 전화번호',
        'privacy_officer_name' => '개인정보보호책임자',
        'privacy_officer_email' => '개인정보보호책임자 이메일',
    ];
    $business = is_array($context['business'] ?? null) ? $context['business'] : [];
    $items = [];
    foreach ($labels as $key => $label) {
        $value = trim((string) ($business[$key] ?? ''));
        if ($value !== '') {
            $items[] = '<li>' . sr_e($label) . ': ' . sr_e($value) . '</li>';
        }
    }

    return $items !== [] ? '<ul>' . implode('', $items) . '</ul>' : '';
}

function sr_policy_document_standard_terms_html(PDO $pdo, ?array $site = null): string
{
    $context = sr_policy_document_standard_context($pdo, $site);
    $siteName = (string) $context['site_name'];
    $baseUrl = (string) $context['base_url'];
    $customerServicePhone = sr_policy_document_standard_value($context, 'customer_service_phone');
    $businessInfoHtml = sr_policy_document_standard_business_info_list_html($context);
    $serviceLabel = $baseUrl !== '' ? $siteName . '(' . $baseUrl . ')' : $siteName;

    $html = '<h2>제1조 목적</h2>';
    $html .= '<p>이 약관은 ' . sr_e($siteName) . '(이하 "회사")가 제공하는 ' . sr_e($serviceLabel) . ' 및 관련 서비스의 이용 조건, 절차, 회원과 회사의 권리와 의무, 책임 사항을 정합니다.</p>';
    $html .= '<h2>제2조 용어의 정의</h2>';
    $html .= '<p>"서비스"란 회사가 온라인으로 제공하는 모든 기능과 콘텐츠를 말합니다. "회원"이란 이 약관에 동의하고 회사가 정한 절차에 따라 계정을 만든 이용자를 말합니다.</p>';
    $html .= '<h2>제3조 약관의 게시와 변경</h2>';
    $html .= '<p>회사는 이 약관의 내용을 회원이 쉽게 확인할 수 있도록 서비스 화면에 게시합니다. 회사는 관련 법령을 위반하지 않는 범위에서 약관을 변경할 수 있으며, 변경 시 적용일자와 주요 변경 내용을 사전에 공지합니다.</p>';
    $html .= '<h2>제4조 이용계약의 성립</h2>';
    $html .= '<p>이용계약은 이용자가 회원가입 절차에서 약관에 동의하고 가입을 신청한 뒤 회사가 이를 승낙함으로써 성립합니다. 회사는 허위 정보, 타인 명의 사용, 서비스 운영상 지장이 있는 신청을 거절하거나 사후에 이용을 제한할 수 있습니다.</p>';
    $html .= '<h2>제5조 회원의 의무</h2>';
    $html .= '<p>회원은 관련 법령, 이 약관, 서비스 안내와 공지사항을 준수해야 하며, 타인의 권리 침해, 계정 공유 또는 도용, 서비스의 정상 운영을 방해하는 행위를 해서는 안 됩니다.</p>';
    $html .= '<h2>제6조 서비스의 제공과 변경</h2>';
    $html .= '<p>회사는 안정적인 서비스 제공을 위해 노력합니다. 다만 설비 점검, 장애, 운영상 필요, 천재지변 등 부득이한 사유가 있는 경우 서비스의 전부 또는 일부를 변경하거나 일시 중단할 수 있습니다.</p>';
    $html .= '<h2>제7조 게시물과 콘텐츠</h2>';
    $html .= '<p>회원이 서비스에 게시한 콘텐츠의 권리와 책임은 해당 회원에게 있습니다. 회사는 법령, 약관, 운영정책에 위반되거나 권리 침해 소지가 있는 콘텐츠를 사전 통지 없이 숨김, 삭제, 접근 제한할 수 있습니다.</p>';
    $html .= '<h2>제8조 유료서비스와 환불</h2>';
    $html .= '<p>유료서비스가 제공되는 경우 결제, 이용기간, 청약철회, 환불 조건은 서비스 화면에 표시된 안내와 관련 법령을 따릅니다. 이미 사용되었거나 디지털 콘텐츠 제공이 개시된 경우에는 법령상 허용되는 범위에서 환불이 제한될 수 있습니다.</p>';
    $html .= '<h2>제9조 계약해지와 이용제한</h2>';
    $html .= '<p>회원은 언제든지 서비스에서 제공하는 절차에 따라 탈퇴를 신청할 수 있습니다. 회사는 회원이 약관 또는 운영정책을 위반한 경우 이용을 제한하거나 계약을 해지할 수 있습니다.</p>';
    $html .= '<h2>제10조 책임의 제한</h2>';
    $html .= '<p>회사는 회사의 고의 또는 중대한 과실이 없는 한 무료로 제공되는 서비스의 이용과 관련하여 발생한 손해에 대해 책임을 부담하지 않습니다. 회원의 귀책사유로 발생한 서비스 이용 장애나 분쟁에 대해서도 회사는 책임을 부담하지 않습니다.</p>';
    $html .= '<h2>제11조 준거법과 관할</h2>';
    $html .= '<p>이 약관은 대한민국 법령에 따라 해석됩니다. 서비스 이용과 관련하여 분쟁이 발생한 경우 회사와 회원은 성실히 협의하며, 협의가 이루어지지 않을 때에는 관련 법령이 정한 관할 법원에 따릅니다.</p>';
    if ($customerServicePhone !== '' || $businessInfoHtml !== '') {
        $html .= '<h2>제12조 고객문의와 사업자 정보</h2>';
        if ($customerServicePhone !== '') {
            $html .= '<p>서비스 이용 문의는 고객센터 전화번호 ' . sr_e($customerServicePhone) . ' 또는 서비스에서 안내하는 문의 수단을 통해 접수할 수 있습니다.</p>';
        }
        if ($businessInfoHtml !== '') {
            $html .= $businessInfoHtml;
        }
    }

    return $html;
}

function sr_policy_document_standard_privacy_policy_html(PDO $pdo, ?array $site = null): string
{
    $context = sr_policy_document_standard_context($pdo, $site);
    $siteName = (string) $context['site_name'];
    $baseUrl = (string) $context['base_url'];
    $privacyOfficerName = sr_policy_document_standard_value($context, 'privacy_officer_name', $siteName . ' 개인정보보호 담당자');
    $privacyOfficerEmail = sr_policy_document_standard_value($context, 'privacy_officer_email');
    $businessInfoHtml = sr_policy_document_standard_business_info_list_html($context);
    $serviceLabel = $baseUrl !== '' ? $siteName . '(' . $baseUrl . ')' : $siteName;

    $html = '<h2>1. 개인정보의 처리 목적</h2>';
    $html .= '<p>' . sr_e($siteName) . '(이하 "회사")는 ' . sr_e($serviceLabel) . ' 서비스 제공, 회원 관리, 본인 확인, 문의 대응, 부정 이용 방지, 서비스 개선과 고지사항 전달을 위해 개인정보를 처리합니다.</p>';
    $html .= '<h2>2. 처리하는 개인정보 항목</h2>';
    $html .= '<ul><li>회원가입 및 계정 관리: 이메일, 비밀번호, 이름 또는 닉네임, 로그인 기록</li><li>서비스 이용 과정: IP 주소, 쿠키, 접속 일시, 기기 및 브라우저 정보, 서비스 이용 기록</li><li>문의 및 운영 대응: 문의 내용, 답변 기록, 필요한 경우 연락처</li><li>유료서비스 이용 시: 결제 및 환불 처리에 필요한 거래 정보</li></ul>';
    $html .= '<h2>3. 개인정보의 보유 및 이용기간</h2>';
    $html .= '<p>회사는 개인정보 처리 목적이 달성되면 지체 없이 해당 정보를 파기합니다. 다만 관계 법령에 따라 보존해야 하는 정보는 법령에서 정한 기간 동안 보관하며, 서비스 부정 이용 방지와 분쟁 대응을 위해 필요한 최소한의 기록은 운영정책에 따라 보관할 수 있습니다.</p>';
    $html .= '<h2>4. 개인정보의 제3자 제공</h2>';
    $html .= '<p>회사는 이용자의 동의가 있거나 법령에 특별한 근거가 있는 경우를 제외하고 개인정보를 제3자에게 제공하지 않습니다. 제3자 제공이 필요한 경우 제공받는 자, 제공 목적, 제공 항목, 보유기간을 사전에 안내하고 동의를 받습니다.</p>';
    $html .= '<h2>5. 개인정보 처리의 위탁</h2>';
    $html .= '<p>회사는 안정적인 서비스 제공을 위해 호스팅, 이메일 발송, 결제 처리 등 업무를 외부 업체에 위탁할 수 있습니다. 위탁이 발생하는 경우 위탁받는 자와 업무 내용을 서비스 화면 또는 별도 고지로 안내하고, 수탁자가 개인정보를 안전하게 처리하도록 관리합니다.</p>';
    $html .= '<h2>6. 정보주체의 권리와 행사 방법</h2>';
    $html .= '<p>이용자는 언제든지 본인의 개인정보 열람, 정정, 삭제, 처리정지, 동의 철회를 요청할 수 있습니다. 회사는 본인 확인 후 관련 법령에 따라 지체 없이 필요한 조치를 합니다.</p>';
    $html .= '<h2>7. 쿠키 등 자동 수집 장치</h2>';
    $html .= '<p>회사는 로그인 유지, 보안, 이용 통계와 서비스 개선을 위해 쿠키를 사용할 수 있습니다. 이용자는 브라우저 설정을 통해 쿠키 저장을 거부하거나 삭제할 수 있으나, 이 경우 일부 서비스 이용이 제한될 수 있습니다.</p>';
    $html .= '<h2>8. 개인정보의 안전성 확보 조치</h2>';
    $html .= '<p>회사는 개인정보 보호를 위해 접근 권한 관리, 비밀번호 및 중요 정보 암호화 또는 해시 처리, 보안 로그 관리, 취약점 점검 등 필요한 기술적·관리적 보호 조치를 시행합니다.</p>';
    $html .= '<h2>9. 개인정보보호책임자</h2>';
    $html .= '<p>개인정보 처리와 관련한 문의, 권리 행사, 피해 구제 요청은 아래 담당자에게 연락할 수 있습니다.</p>';
    $html .= '<ul><li>개인정보보호책임자: ' . sr_e($privacyOfficerName) . '</li>';
    if ($privacyOfficerEmail !== '') {
        $html .= '<li>이메일: ' . sr_e($privacyOfficerEmail) . '</li>';
    }
    $html .= '</ul>';
    if ($businessInfoHtml !== '') {
        $html .= '<h2>10. 사업자 정보</h2>' . $businessInfoHtml;
        $html .= '<h2>11. 개인정보처리방침의 변경</h2>';
    } else {
        $html .= '<h2>10. 개인정보처리방침의 변경</h2>';
    }
    $html .= '<p>이 개인정보처리방침은 관련 법령, 서비스 정책, 개인정보 처리 항목의 변경에 따라 개정될 수 있습니다. 변경 시 적용일자와 주요 변경 내용을 서비스 화면에 공지합니다.</p>';

    return $html;
}

function sr_policy_document_plain_text_to_html(string $bodyText): string
{
    $bodyText = trim(str_replace(["\r\n", "\r"], "\n", $bodyText));
    if ($bodyText === '') {
        return '';
    }

    $paragraphs = preg_split("/\n{2,}/", $bodyText) ?: [];
    $html = [];
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph === '') {
            continue;
        }
        $html[] = '<p>' . nl2br(sr_e($paragraph), false) . '</p>';
    }

    return implode("\n", $html);
}

function sr_policy_document_body_html_from_editor_data(array $data): string
{
    $mode = (string) ($data['body_editor_mode'] ?? 'html');
    if (!in_array($mode, ['plain', 'html', 'markdown', 'ckeditor'], true)) {
        $mode = 'html';
    }

    if ($mode === 'plain') {
        return sr_policy_document_sanitize_body(sr_policy_document_plain_text_to_html((string) ($data['body_plain'] ?? '')));
    }

    if ($mode === 'markdown') {
        return sr_policy_document_sanitize_body(sr_markdown_text_html((string) ($data['body_markdown'] ?? '')));
    }

    if ($mode === 'ckeditor') {
        return sr_policy_document_sanitize_body((string) ($data['body_ckeditor_html'] ?? ''));
    }

    return sr_policy_document_sanitize_body((string) ($data['body_html'] ?? ''));
}

function sr_policy_document_by_key(PDO $pdo, string $documentKey): ?array
{
    if (!sr_policy_document_valid_key($documentKey)) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, document_key, title, description, status, sort_order, created_at, updated_at
         FROM sr_policy_documents
         WHERE document_key = :document_key
         LIMIT 1'
    );
    $stmt->execute(['document_key' => $documentKey]);
    $document = $stmt->fetch();

    return is_array($document) ? $document : null;
}

function sr_policy_document_by_id(PDO $pdo, int $documentId): ?array
{
    if ($documentId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT id, document_key, title, description, status, sort_order, created_at, updated_at
         FROM sr_policy_documents
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $documentId]);
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
        'SELECT v.id, v.document_id, v.title_snapshot, v.body_html, v.summary_text,
                v.body_hash, v.append_previous_versions,
                v.status, v.effective_from, v.published_at, v.created_at, v.updated_at,
                d.document_key, d.title AS document_title
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
    $renderData = sr_policy_document_public_render_data($pdo, $documentKey);
    if (!is_array($renderData)) {
        throw new RuntimeException('Published policy document version is missing: ' . $documentKey);
    }

    return [
        'document_id' => (int) $renderData['document_id'],
        'document_key' => (string) $renderData['document_key'],
        'version_id' => (int) $renderData['version_id'],
        'title' => (string) $renderData['title'],
        'body_hash' => (string) $renderData['body_hash'],
        'summary_text' => (string) ($renderData['summary_text'] ?? ''),
        'published_at' => (string) ($renderData['published_at'] ?? ''),
        'effective_from' => (string) ($renderData['effective_from'] ?? ''),
    ];
}

function sr_policy_document_public_render_data(PDO $pdo, string $documentKey): ?array
{
    $version = sr_policy_document_published_version($pdo, $documentKey);
    if (!is_array($version)) {
        return null;
    }

    $bodyHtml = sr_policy_document_render_body_html($pdo, $version);

    return [
        'document_id' => (int) $version['document_id'],
        'document_key' => (string) $version['document_key'],
        'version_id' => (int) $version['id'],
        'title' => (string) $version['title_snapshot'],
        'body_html' => $bodyHtml,
        'body_hash' => sr_policy_document_body_hash($bodyHtml),
        'stored_body_hash' => (string) $version['body_hash'],
        'summary_text' => (string) ($version['summary_text'] ?? ''),
        'append_previous_versions' => !empty($version['append_previous_versions']) ? 1 : 0,
        'published_at' => (string) ($version['published_at'] ?? ''),
        'effective_from' => (string) ($version['effective_from'] ?? ''),
    ];
}

function sr_policy_document_public_version_by_id(PDO $pdo, int $versionId): ?array
{
    if ($versionId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT v.id, v.document_id, v.title_snapshot, v.body_html, v.summary_text,
                v.body_hash, v.append_previous_versions,
                v.status, v.effective_from, v.published_at, v.created_at, v.updated_at,
                d.document_key, d.title AS document_title, d.status AS document_status
         FROM sr_policy_document_versions v
         INNER JOIN sr_policy_documents d ON d.id = v.document_id
         WHERE v.id = :id
           AND v.status IN ("published", "archived")
           AND d.status = "enabled"
         LIMIT 1'
    );
    $stmt->execute(['id' => $versionId]);
    $version = $stmt->fetch();

    return is_array($version) ? $version : null;
}

function sr_policy_document_version_public_url(int $versionId): string
{
    return sr_url('/policy-documents/version?id=' . (string) $versionId);
}

function sr_policy_document_history_date_source(array $version): string
{
    foreach (['effective_from', 'published_at', 'created_at'] as $key) {
        $value = trim((string) ($version[$key] ?? ''));
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

function sr_policy_document_history_date_label(string $date): string
{
    if (preg_match('/\A(\d{4})-(\d{2})-(\d{2})/', $date, $matches) === 1) {
        return (string) (int) $matches[1] . '년 ' . (string) (int) $matches[2] . '월 ' . (string) (int) $matches[3] . '일';
    }

    return $date;
}

function sr_policy_document_effective_date_label(?string $date): string
{
    $date = trim((string) $date);
    if ($date === '') {
        return sr_t('policy_documents::ui.effective_from.empty');
    }

    return sr_policy_document_history_date_label($date);
}

function sr_policy_document_notice_mail_body(PDO $pdo, int $versionId): string
{
    $version = sr_policy_document_version_by_id($pdo, $versionId);
    if (!is_array($version)) {
        return sr_t('policy_documents::mail.notice.body', [
            'title' => '',
            'effective_date' => sr_t('policy_documents::ui.effective_from.empty'),
        ]);
    }

    return sr_t('policy_documents::mail.notice.body', [
        'title' => (string) ($version['title_snapshot'] ?? ''),
        'effective_date' => sr_policy_document_effective_date_label((string) ($version['effective_from'] ?? '')),
    ]);
}

function sr_policy_document_render_body_html(PDO $pdo, array $version): string
{
    $bodyHtml = sr_policy_document_sanitize_body((string) ($version['body_html'] ?? ''));
    if (empty($version['append_previous_versions'])) {
        return $bodyHtml;
    }

    $previousVersions = sr_policy_document_previous_versions_for_history(
        $pdo,
        (int) ($version['document_id'] ?? 0),
        (int) ($version['id'] ?? 0)
    );
    if ($previousVersions === []) {
        return $bodyHtml;
    }

    return $bodyHtml . "\n" . sr_policy_document_previous_version_history_html((int) ($version['id'] ?? 0), $previousVersions);
}

function sr_policy_document_previous_versions_for_history(PDO $pdo, int $documentId, int $currentVersionId): array
{
    if ($documentId < 1 || $currentVersionId < 1) {
        return [];
    }

    $stmt = $pdo->prepare(
        'SELECT id, document_id, title_snapshot, body_html, summary_text, body_hash,
                append_previous_versions,
                status, effective_from, published_at, created_at, updated_at
         FROM sr_policy_document_versions
         WHERE document_id = :document_id
           AND id < :current_version_id
           AND status IN ("published", "archived")
         ORDER BY COALESCE(effective_from, published_at, created_at) DESC, id DESC'
    );
    $stmt->execute([
        'document_id' => $documentId,
        'current_version_id' => $currentVersionId,
    ]);

    return $stmt->fetchAll();
}

function sr_policy_document_previous_version_history_html(int $currentVersionId, array $previousVersions): string
{
    if ($previousVersions === []) {
        return '';
    }

    $sectionId = 'policy-document-version-history-' . (string) $currentVersionId;
    $html = '<section class="policy-document-version-history" aria-labelledby="' . sr_e($sectionId) . '-title">';
    $html .= '<h2 id="' . sr_e($sectionId) . '-title">' . sr_e(sr_t('policy_documents::ui.previous_versions.heading')) . '</h2>';
    $html .= '<ul class="policy-document-version-history-links">';
    foreach ($previousVersions as $previousVersion) {
        $versionId = (int) ($previousVersion['id'] ?? 0);
        if ($versionId < 1) {
            continue;
        }
        $dateSource = sr_policy_document_history_date_source($previousVersion);
        if ($dateSource === '') {
            continue;
        }
        $dateLabel = sr_policy_document_history_date_label($dateSource);
        $html .= '<li><a href="' . sr_e(sr_policy_document_version_public_url($versionId)) . '" target="_blank" rel="noopener noreferrer"><time datetime="' . sr_e(str_replace(' ', 'T', $dateSource)) . '">' . sr_e($dateLabel) . '</time></a></li>';
    }

    return $html . '</ul></section>';
}

function sr_policy_document_enabled_choices(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT d.id, d.document_key, d.title, d.status,
                v.id AS published_version_id, v.published_at
         FROM sr_policy_documents d
         LEFT JOIN sr_policy_document_versions v ON v.id = (
            SELECT pv.id
            FROM sr_policy_document_versions pv
            WHERE pv.document_id = d.id
              AND pv.status = "published"
              AND (pv.effective_from IS NULL OR pv.effective_from <= :effective_at)
            ORDER BY COALESCE(pv.effective_from, pv.published_at, pv.created_at) DESC, pv.id DESC
            LIMIT 1
         )
         WHERE d.status = "enabled"
         ORDER BY d.sort_order ASC, d.id ASC'
    );
    $stmt->execute(['effective_at' => sr_now()]);

    return $stmt->fetchAll();
}

function sr_policy_documents_with_current_versions(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT d.id, d.document_key, d.title, d.description, d.status, d.sort_order,
                d.created_at, d.updated_at,
                v.id AS published_version_id, v.published_at
         FROM sr_policy_documents d
         LEFT JOIN sr_policy_document_versions v ON v.id = (
            SELECT pv.id
            FROM sr_policy_document_versions pv
            WHERE pv.document_id = d.id
              AND pv.status = "published"
              AND (pv.effective_from IS NULL OR pv.effective_from <= :effective_at)
            ORDER BY COALESCE(pv.effective_from, pv.published_at, pv.created_at) DESC, pv.id DESC
            LIMIT 1
         )
         ORDER BY d.sort_order ASC, d.id ASC'
    );
    $stmt->execute(['effective_at' => sr_now()]);

    return $stmt->fetchAll();
}

function sr_policy_document_versions(PDO $pdo, int $documentId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, document_id, title_snapshot, summary_text, body_hash,
                append_previous_versions,
                status,
                effective_from, published_at, created_at, updated_at
         FROM sr_policy_document_versions
         WHERE document_id = :document_id
         ORDER BY id DESC'
    );
    $stmt->execute(['document_id' => $documentId]);

    return $stmt->fetchAll();
}

function sr_policy_document_all_versions(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT v.id, v.document_id, v.title_snapshot, v.body_html, v.summary_text, v.body_hash,
                v.append_previous_versions,
                v.status, v.effective_from, v.published_at, v.created_at, v.updated_at,
                d.document_key, d.title AS document_title, d.sort_order AS document_sort_order
         FROM sr_policy_document_versions v
         INNER JOIN sr_policy_documents d ON d.id = v.document_id
         ORDER BY d.sort_order ASC, d.id ASC, v.id DESC'
    );

    return $stmt->fetchAll();
}

function sr_policy_document_version_by_id(PDO $pdo, int $versionId): ?array
{
    if ($versionId < 1) {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT v.id, v.document_id, v.title_snapshot, v.body_html, v.summary_text,
                v.body_hash, v.append_previous_versions,
                v.status, v.effective_from, v.published_at, v.created_at, v.updated_at,
                d.document_key, d.title AS document_title, d.status AS document_status
         FROM sr_policy_document_versions v
         INNER JOIN sr_policy_documents d ON d.id = v.document_id
         WHERE v.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $versionId]);
    $version = $stmt->fetch();

    return is_array($version) ? $version : null;
}

function sr_policy_document_create_document(PDO $pdo, array $data): int
{
    $documentKey = strtolower(trim((string) ($data['document_key'] ?? '')));
    $title = sr_clean_single_line((string) ($data['title'] ?? ''), 190);
    $description = sr_clean_text((string) ($data['description'] ?? ''), 2000);
    $status = (string) ($data['status'] ?? 'enabled');
    $sortOrder = (int) ($data['sort_order'] ?? 100);

    if (!sr_policy_document_valid_key($documentKey)) {
        throw new InvalidArgumentException(sr_t('policy_documents::error.document_key_invalid'));
    }
    if ($title === '') {
        throw new InvalidArgumentException(sr_t('policy_documents::error.title_required'));
    }
    if (!in_array($status, ['enabled', 'disabled'], true)) {
        $status = 'enabled';
    }
    if (is_array(sr_policy_document_by_key($pdo, $documentKey))) {
        throw new InvalidArgumentException(sr_t('policy_documents::error.document_key_duplicate'));
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_policy_documents
            (document_key, title, description, status, sort_order, created_at, updated_at)
         VALUES
            (:document_key, :title, :description, :status, :sort_order, :created_at, :updated_at)'
    );
    $stmt->execute([
        'document_key' => $documentKey,
        'title' => $title,
        'description' => $description,
        'status' => $status,
        'sort_order' => $sortOrder,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return (int) $pdo->lastInsertId();
}

function sr_policy_document_create_version(PDO $pdo, int $documentId, array $data): int
{
    if (!is_array(sr_policy_document_by_id($pdo, $documentId))) {
        throw new InvalidArgumentException(sr_t('policy_documents::error.document_required'));
    }

    $title = sr_clean_single_line((string) ($data['title'] ?? ''), 190);
    $bodyHtml = sr_policy_document_body_html_from_editor_data($data);
    $bodyHash = sr_policy_document_body_hash($bodyHtml);
    $summaryText = sr_clean_text((string) ($data['summary_text'] ?? ''), 1000);
    $appendPreviousVersions = !empty($data['append_previous_versions']) ? 1 : 0;
    $status = (string) ($data['status'] ?? 'draft');
    $effectiveFrom = sr_clean_admin_datetime((string) ($data['effective_from'] ?? ''));
    $allowedStatuses = ['draft', 'published', 'archived'];

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
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        if ($status === 'published' && ($effectiveFrom === '' || $effectiveFrom <= $now)) {
            $archiveStmt = $pdo->prepare(
                'UPDATE sr_policy_document_versions
                 SET status = "archived",
                     updated_at = :updated_at
                 WHERE document_id = :document_id
                   AND status = "published"
                   AND (effective_from IS NULL OR effective_from <= :effective_at)'
            );
            $archiveStmt->execute([
                'document_id' => $documentId,
                'effective_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO sr_policy_document_versions
                (document_id, title_snapshot, body_html, summary_text, body_hash, append_previous_versions, status, effective_from, published_at, created_at, updated_at)
             VALUES
                (:document_id, :title_snapshot, :body_html, :summary_text, :body_hash, :append_previous_versions, :status, :effective_from, :published_at, :created_at, :updated_at)'
        );
        $stmt->execute([
            'document_id' => $documentId,
            'title_snapshot' => $title,
            'body_html' => $bodyHtml,
            'summary_text' => $summaryText,
            'body_hash' => $bodyHash,
            'append_previous_versions' => $appendPreviousVersions,
            'status' => $status,
            'effective_from' => $effectiveFrom,
            'published_at' => $status === 'published' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $versionId = (int) $pdo->lastInsertId();

        if ($ownsTransaction) {
            $pdo->commit();
        }

        return $versionId;
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function sr_policy_document_update_draft_version(PDO $pdo, int $versionId, array $data): void
{
    $version = sr_policy_document_version_by_id($pdo, $versionId);
    if (!is_array($version)) {
        throw new InvalidArgumentException(sr_t('policy_documents::error.version_required'));
    }
    if ((string) ($version['status'] ?? '') !== 'draft') {
        throw new InvalidArgumentException(sr_t('policy_documents::error.version_edit_draft_only'));
    }

    $title = sr_clean_single_line((string) ($data['title'] ?? ''), 190);
    $bodyHtml = sr_policy_document_body_html_from_editor_data($data);
    $summaryText = sr_clean_text((string) ($data['summary_text'] ?? ''), 1000);
    $appendPreviousVersions = !empty($data['append_previous_versions']) ? 1 : 0;
    $effectiveFrom = sr_clean_admin_datetime((string) ($data['effective_from'] ?? ''));

    if ($title === '') {
        throw new InvalidArgumentException(sr_t('policy_documents::error.title_required'));
    }
    if (trim(strip_tags($bodyHtml)) === '') {
        throw new InvalidArgumentException(sr_t('policy_documents::error.body_required'));
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_policy_document_versions
         SET title_snapshot = :title_snapshot,
             body_html = :body_html,
             summary_text = :summary_text,
             body_hash = :body_hash,
             append_previous_versions = :append_previous_versions,
             effective_from = :effective_from,
             updated_at = :updated_at
         WHERE id = :id
           AND status = "draft"'
    );
    $stmt->execute([
        'title_snapshot' => $title,
        'body_html' => $bodyHtml,
        'summary_text' => $summaryText,
        'body_hash' => sr_policy_document_body_hash($bodyHtml),
        'append_previous_versions' => $appendPreviousVersions,
        'effective_from' => $effectiveFrom,
        'updated_at' => sr_now(),
        'id' => $versionId,
    ]);
}

function sr_policy_document_publish_draft_version(PDO $pdo, int $versionId): array
{
    $version = sr_policy_document_version_by_id($pdo, $versionId);
    if (!is_array($version)) {
        throw new InvalidArgumentException(sr_t('policy_documents::error.version_required'));
    }
    if ((string) ($version['status'] ?? '') !== 'draft') {
        throw new InvalidArgumentException(sr_t('policy_documents::error.version_publish_draft_only'));
    }

    $documentId = (int) $version['document_id'];
    $effectiveFrom = sr_clean_admin_datetime((string) ($version['effective_from'] ?? ''));
    $now = sr_now();
    $ownsTransaction = !$pdo->inTransaction();
    if ($ownsTransaction) {
        $pdo->beginTransaction();
    }

    try {
        if ($effectiveFrom === '' || $effectiveFrom <= $now) {
            $archiveStmt = $pdo->prepare(
                'UPDATE sr_policy_document_versions
                 SET status = "archived",
                     updated_at = :updated_at
                 WHERE document_id = :document_id
                   AND id <> :version_id
                   AND status = "published"
                   AND (effective_from IS NULL OR effective_from <= :effective_at)'
            );
            $archiveStmt->execute([
                'document_id' => $documentId,
                'version_id' => $versionId,
                'effective_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $stmt = $pdo->prepare(
            'UPDATE sr_policy_document_versions
             SET status = "published",
                 published_at = :published_at,
                 updated_at = :updated_at
             WHERE id = :id
               AND status = "draft"'
        );
        $stmt->execute([
            'published_at' => $now,
            'updated_at' => $now,
            'id' => $versionId,
        ]);

        if ($ownsTransaction) {
            $pdo->commit();
        }
    } catch (Throwable $exception) {
        if ($ownsTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    return [
        'document_id' => $documentId,
        'version_id' => $versionId,
    ];
}

function sr_policy_document_create_notice_job(PDO $pdo, int $documentId, int $versionId, string $subject, string $body, bool $dryRun = false): int
{
    $versionStmt = $pdo->prepare(
        'SELECT id
         FROM sr_policy_document_versions
         WHERE id = :id
           AND document_id = :document_id
         LIMIT 1'
    );
    $versionStmt->execute([
        'id' => $versionId,
        'document_id' => $documentId,
    ]);
    if ((int) $versionStmt->fetchColumn() !== $versionId) {
        throw new InvalidArgumentException(sr_t('policy_documents::error.version_required'));
    }

    $jobKey = 'policy_document_' . (string) $versionId . '_notice';
    $now = sr_now();
    $existingStmt = $pdo->prepare(
        'SELECT id
         FROM sr_policy_document_mail_jobs
         WHERE job_key = :job_key
         LIMIT 1'
    );
    $existingStmt->execute(['job_key' => $jobKey]);
    $existingJobId = $existingStmt->fetchColumn();
    if (is_numeric($existingJobId) && (int) $existingJobId > 0) {
        sr_policy_document_seed_notice_deliveries($pdo, (int) $existingJobId);
        return (int) $existingJobId;
    }

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

    $jobId = (int) $pdo->lastInsertId();
    sr_policy_document_cancel_unfinished_notice_jobs($pdo, $documentId, $jobId);
    sr_policy_document_seed_notice_deliveries($pdo, $jobId);

    return $jobId;
}

function sr_policy_document_cancel_unfinished_notice_jobs(PDO $pdo, int $documentId, int $currentJobId = 0): int
{
    if ($documentId < 1) {
        return 0;
    }

    $sql = 'SELECT id
            FROM sr_policy_document_mail_jobs
            WHERE document_id = :document_id
              AND status IN ("queued", "processing", "failed")';
    $params = ['document_id' => $documentId];
    if ($currentJobId > 0) {
        $sql .= ' AND id <> :current_job_id';
        $params['current_job_id'] = $currentJobId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $jobIds = array_map('intval', array_column($stmt->fetchAll(), 'id'));
    if ($jobIds === []) {
        return 0;
    }

    $cancelled = 0;
    $deliveryStmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_deliveries
         SET status = "cancelled",
             failure_code = "superseded_by_new_version",
             updated_at = :updated_at
         WHERE job_id = :job_id
           AND status IN ("queued", "processing", "failed")'
    );
    $jobStmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_jobs
         SET status = "cancelled",
             updated_at = :updated_at
         WHERE id = :id
           AND status IN ("queued", "processing", "failed")'
    );
    foreach ($jobIds as $jobId) {
        $now = sr_now();
        $deliveryStmt->execute([
            'updated_at' => $now,
            'job_id' => $jobId,
        ]);
        $jobStmt->execute([
            'updated_at' => $now,
            'id' => $jobId,
        ]);
        if ($jobStmt->rowCount() > 0) {
            $cancelled++;
        }
    }

    return $cancelled;
}

function sr_policy_document_seed_notice_deliveries(PDO $pdo, int $jobId): int
{
    if ($jobId < 1) {
        return 0;
    }

    $now = sr_now();
    $stmt = $pdo->prepare(
        'INSERT INTO sr_policy_document_mail_deliveries
            (job_id, account_id, status, failure_code, created_at, updated_at)
         SELECT :job_id, a.id, "queued", "", :created_at, :updated_at
         FROM sr_member_accounts a
         LEFT JOIN sr_policy_document_mail_deliveries existing_delivery
            ON existing_delivery.job_id = :existing_job_id
           AND existing_delivery.account_id = a.id
         WHERE a.status = "active"
           AND existing_delivery.id IS NULL'
    );
    $stmt->execute([
        'job_id' => $jobId,
        'existing_job_id' => $jobId,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return $stmt->rowCount();
}

function sr_policy_document_mail_jobs(PDO $pdo): array
{
    $stmt = $pdo->query(
        'SELECT j.id, j.job_key, j.status, j.subject_snapshot, j.dry_run, j.created_at, j.updated_at,
                d.document_key,
                (SELECT COUNT(*) FROM sr_policy_document_mail_deliveries q WHERE q.job_id = j.id) AS delivery_count,
                (SELECT COUNT(*) FROM sr_policy_document_mail_deliveries q WHERE q.job_id = j.id AND q.status = "queued") AS queued_count,
                (SELECT COUNT(*) FROM sr_policy_document_mail_deliveries q WHERE q.job_id = j.id AND q.status = "processing") AS processing_count,
                (SELECT COUNT(*) FROM sr_policy_document_mail_deliveries q WHERE q.job_id = j.id AND q.status = "sent") AS sent_count,
                (SELECT COUNT(*) FROM sr_policy_document_mail_deliveries q WHERE q.job_id = j.id AND q.status = "failed") AS failed_count,
                (SELECT COUNT(*) FROM sr_policy_document_mail_deliveries q WHERE q.job_id = j.id AND q.status = "skipped") AS skipped_count,
                (SELECT COUNT(*) FROM sr_policy_document_mail_deliveries q WHERE q.job_id = j.id AND q.status = "cancelled") AS cancelled_count
         FROM sr_policy_document_mail_jobs j
         INNER JOIN sr_policy_documents d ON d.id = j.document_id
         INNER JOIN sr_policy_document_versions v ON v.id = j.version_id
         ORDER BY j.id DESC
         LIMIT 100'
    );

    return $stmt->fetchAll();
}

function sr_policy_document_process_mail_batch(PDO $pdo, array $site, int $jobId, int $limit = 20): array
{
    $limit = min(100, max(1, $limit));
    $now = sr_now();
    $staleClaimCutoff = date('Y-m-d H:i:s', time() - 900);
    $skipSelectStmt = $pdo->prepare(
        'SELECT d.id
         FROM sr_policy_document_mail_deliveries d
         LEFT JOIN sr_member_accounts a ON a.id = d.account_id
         WHERE d.job_id = :job_id
           AND (d.status = "queued" OR (d.status = "processing" AND d.claimed_at < :stale_claim_cutoff))
           AND (a.id IS NULL OR a.status <> "active")'
    );
    $skipSelectStmt->execute([
        'job_id' => $jobId,
        'stale_claim_cutoff' => $staleClaimCutoff,
    ]);
    $skipIds = array_map('intval', array_column($skipSelectStmt->fetchAll(), 'id'));
    $skipUpdateStmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_deliveries
         SET status = "skipped",
             failure_code = "account_not_active",
             updated_at = :updated_at
         WHERE id = :id
           AND (status = "queued" OR (status = "processing" AND claimed_at < :stale_claim_cutoff))'
    );
    $skipped = 0;
    foreach ($skipIds as $skipId) {
        $skipUpdateStmt->execute([
            'id' => $skipId,
            'updated_at' => $now,
            'stale_claim_cutoff' => $staleClaimCutoff,
        ]);
        $skipped += $skipUpdateStmt->rowCount() > 0 ? 1 : 0;
    }

    $stmt = $pdo->prepare(
        'SELECT d.id, d.account_id, a.email, j.subject_snapshot, j.body_snapshot, j.dry_run
         FROM sr_policy_document_mail_deliveries d
         INNER JOIN sr_policy_document_mail_jobs j ON j.id = d.job_id
         INNER JOIN sr_member_accounts a ON a.id = d.account_id
         WHERE d.job_id = :job_id
           AND (d.status = "queued" OR (d.status = "processing" AND d.claimed_at < :stale_claim_cutoff))
           AND a.status = "active"
         ORDER BY d.id ASC
         LIMIT ' . (string) $limit
    );
    $stmt->execute([
        'job_id' => $jobId,
        'stale_claim_cutoff' => $staleClaimCutoff,
    ]);
    $rows = $stmt->fetchAll();

    $claimedRows = [];
    $claimStmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_deliveries
         SET status = "processing",
             failure_code = "",
             claimed_at = :claimed_at,
             updated_at = :updated_at
         WHERE id = :id
           AND job_id = :job_id
           AND (status = "queued" OR (status = "processing" AND claimed_at < :stale_claim_cutoff))'
    );
    foreach ($rows as $row) {
        $claimStmt->execute([
            'claimed_at' => $now,
            'updated_at' => $now,
            'id' => (int) $row['id'],
            'job_id' => $jobId,
            'stale_claim_cutoff' => $staleClaimCutoff,
        ]);
        if ($claimStmt->rowCount() > 0) {
            $claimedRows[] = $row;
        }
    }

    $sent = 0;
    $failed = 0;
    $updateStmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_deliveries
         SET status = :status,
             failure_code = :failure_code,
             sent_at = :sent_at,
             updated_at = :updated_at
         WHERE id = :id
           AND job_id = :job_id
           AND status = "processing"
           AND claimed_at = :claimed_at'
    );

    foreach ($claimedRows as $row) {
        $dryRun = !empty($row['dry_run']);
        $ok = $dryRun ? true : sr_send_mail($site, (string) $row['email'], (string) $row['subject_snapshot'], (string) $row['body_snapshot']);
        $updateStmt->execute([
            'status' => $ok ? 'sent' : 'failed',
            'failure_code' => $ok ? '' : 'send_failed',
            'sent_at' => $ok ? $now : null,
            'updated_at' => $now,
            'id' => (int) $row['id'],
            'job_id' => $jobId,
            'claimed_at' => $now,
        ]);
        if ($updateStmt->rowCount() > 0) {
            if ($ok) {
                $sent++;
            } else {
                $failed++;
            }
        }
    }

    sr_policy_document_refresh_mail_job_status($pdo, $jobId);

    return [
        'claimed' => count($claimedRows),
        'sent' => $sent,
        'failed' => $failed,
        'skipped' => $skipped,
    ];
}

function sr_policy_document_requeue_failed_mail_deliveries(PDO $pdo, int $jobId): int
{
    if ($jobId < 1) {
        return 0;
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_deliveries
         SET status = "queued",
             failure_code = "",
             claimed_at = NULL,
             updated_at = :updated_at
         WHERE job_id = :job_id
           AND status = "failed"'
    );
    $stmt->execute([
        'updated_at' => sr_now(),
        'job_id' => $jobId,
    ]);
    $changed = $stmt->rowCount();
    sr_policy_document_refresh_mail_job_status($pdo, $jobId);

    return $changed;
}

function sr_policy_document_cancel_pending_mail_deliveries(PDO $pdo, int $jobId, string $failureCode = 'manual_cancelled'): int
{
    if ($jobId < 1) {
        return 0;
    }

    $failureCode = sr_clean_single_line($failureCode, 80);
    if ($failureCode === '') {
        $failureCode = 'manual_cancelled';
    }

    $stmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_deliveries
         SET status = "cancelled",
             failure_code = :failure_code,
             updated_at = :updated_at
         WHERE job_id = :job_id
           AND status IN ("queued", "processing", "failed")'
    );
    $stmt->execute([
        'failure_code' => $failureCode,
        'updated_at' => sr_now(),
        'job_id' => $jobId,
    ]);
    $changed = $stmt->rowCount();
    sr_policy_document_refresh_mail_job_status($pdo, $jobId);

    return $changed;
}

function sr_policy_document_refresh_mail_job_status(PDO $pdo, int $jobId): void
{
    $stmt = $pdo->prepare(
        'SELECT
            COUNT(*) AS delivery_count,
            SUM(CASE WHEN status IN ("queued", "processing") THEN 1 ELSE 0 END) AS queued_count,
            SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) AS failed_count,
            SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS cancelled_count
         FROM sr_policy_document_mail_deliveries
         WHERE job_id = :job_id'
    );
    $stmt->execute(['job_id' => $jobId]);
    $row = $stmt->fetch();
    $deliveryCount = (int) ($row['delivery_count'] ?? 0);
    $queued = (int) ($row['queued_count'] ?? 0);
    $failed = (int) ($row['failed_count'] ?? 0);
    $cancelled = (int) ($row['cancelled_count'] ?? 0);
    $status = $queued > 0 ? 'queued' : ($failed > 0 ? 'failed' : ($deliveryCount > 0 && $cancelled > 0 ? 'cancelled' : 'sent'));

    $updateStmt = $pdo->prepare(
        'UPDATE sr_policy_document_mail_jobs
         SET status = :status,
             updated_at = :updated_at
         WHERE id = :id'
    );
    $updateStmt->execute([
        'status' => $status,
        'updated_at' => sr_now(),
        'id' => $jobId,
    ]);
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
