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

function sr_policy_document_standard_template_revision_date_label(string $documentKey): string
{
    return match (trim($documentKey)) {
        'member_terms' => sr_t('policy_documents::ui.standard_template.revised_at', ['date' => '2015년 6월 26일']),
        'member_privacy_policy' => sr_t('policy_documents::ui.standard_template.revised_at', ['date' => '2026년 4월 23일']),
        default => '',
    };
}

function sr_policy_document_standard_template_notice_url(string $documentKey): string
{
    return match (trim($documentKey)) {
        'member_terms' => 'https://www.ftc.go.kr/www/selectBbsNttView.do?bordCd=201&key=202&nttSn=11139&pageIndex=1&pageUnit=10&searchCnd=all&searchKrwd=%EC%A0%84%EC%9E%90%EC%83%81%EA%B1%B0%EB%9E%98',
        'member_privacy_policy' => 'https://www.pipc.go.kr/np/cop/bbs/selectBoardArticle.do?bbsId=BS217&nttId=12018',
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
    $businessLabels = sr_policy_document_standard_business_info_labels();
    foreach ($businessItems as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = strtolower(trim((string) ($item['key'] ?? '')));
        $label = sr_clean_single_line((string) ($item['label'] ?? ''), 80);
        $value = sr_clean_single_line((string) ($item['value'] ?? ''), 255);
        if ($key !== '' && $value !== '') {
            $business[$key] = $value;
            if ($label !== '') {
                $businessLabels[$key] = $label;
            }
        }
    }

    return [
        'site_name' => $siteName,
        'base_url' => $baseUrl,
        'business' => $business,
        'business_labels' => $businessLabels,
    ];
}

function sr_policy_document_standard_value(array $context, string $key, string $default = ''): string
{
    $business = is_array($context['business'] ?? null) ? $context['business'] : [];
    $value = trim((string) ($business[$key] ?? ''));

    return $value !== '' ? $value : $default;
}

function sr_policy_document_standard_business_info_labels(): array
{
    return [
        'company_name' => '상호',
        'representative_name' => '대표자명',
        'business_registration_number' => '사업자등록번호',
        'mail_order_report_number' => '통신판매업 신고번호',
        'business_address' => '사업장 주소',
        'business_email' => '사업자 전자우편주소',
        'customer_service_phone' => '고객센터 전화번호',
        'customer_service_email' => '고객센터 전자우편주소',
        'privacy_officer_name' => '개인정보보호책임자',
        'privacy_officer_email' => '개인정보보호책임자 이메일',
        'hosting_provider' => '호스팅 제공자',
    ];
}

function sr_policy_document_standard_label(array $context, string $key, string $default = ''): string
{
    $labels = is_array($context['business_labels'] ?? null) ? $context['business_labels'] : [];

    return trim((string) ($labels[$key] ?? $default));
}

function sr_policy_document_standard_business_info_list_html(array $context, array $keys = []): string
{
    $labels = is_array($context['business_labels'] ?? null) ? $context['business_labels'] : sr_policy_document_standard_business_info_labels();
    $business = is_array($context['business'] ?? null) ? $context['business'] : [];
    $keys = $keys !== [] ? $keys : array_keys($labels);
    $items = [];
    foreach ($keys as $key) {
        $key = (string) $key;
        $label = trim((string) ($labels[$key] ?? $key));
        $value = trim((string) ($business[$key] ?? ''));
        if ($value !== '') {
            $items[] = '<li>' . sr_e($label) . ': ' . sr_e($value) . '</li>';
        }
    }

    return $items !== [] ? '<ul>' . implode('', $items) . '</ul>' : '';
}

function sr_policy_document_standard_contact_sentence(array $context): string
{
    $phone = sr_policy_document_standard_value($context, 'customer_service_phone');
    $email = sr_policy_document_standard_value($context, 'customer_service_email', sr_policy_document_standard_value($context, 'business_email'));
    $parts = [];
    if ($phone !== '') {
        $parts[] = '전화 ' . $phone;
    }
    if ($email !== '') {
        $parts[] = '전자우편 ' . $email;
    }

    return $parts !== [] ? implode(', ', $parts) : '서비스 화면의 문의 수단';
}

function sr_policy_document_standard_terms_html(PDO $pdo, ?array $site = null): string
{
    $context = sr_policy_document_standard_context($pdo, $site);
    $siteName = (string) $context['site_name'];
    $baseUrl = (string) $context['base_url'];
    $companyName = sr_policy_document_standard_value($context, 'company_name', $siteName);
    $representativeName = sr_policy_document_standard_value($context, 'representative_name');
    $businessAddress = sr_policy_document_standard_value($context, 'business_address');
    $businessEmail = sr_policy_document_standard_value($context, 'business_email');
    $customerServicePhone = sr_policy_document_standard_value($context, 'customer_service_phone');
    $contactSentence = sr_policy_document_standard_contact_sentence($context);
    $businessInfoHtml = sr_policy_document_standard_business_info_list_html($context, [
        'company_name',
        'representative_name',
        'business_registration_number',
        'mail_order_report_number',
        'business_address',
        'business_email',
        'customer_service_phone',
        'customer_service_email',
        'hosting_provider',
    ]);
    $serviceLabel = $baseUrl !== '' ? $siteName . '(' . $baseUrl . ')' : $siteName;

    $html = '<h2>제1조 목적</h2>';
    $html .= '<p>이 약관은 ' . sr_e($companyName) . '(이하 "회사")가 운영하는 ' . sr_e($serviceLabel) . '에서 제공하는 인터넷 관련 서비스와 재화 또는 용역(이하 "서비스 등")의 이용에 관한 회사와 이용자의 권리·의무 및 책임사항을 정합니다.</p>';
    $html .= '<h2>제2조 정의</h2>';
    $html .= '<ul><li>"몰"이란 회사가 서비스 등을 이용자에게 제공하기 위하여 컴퓨터 등 정보통신설비를 이용해 설정한 가상의 영업장을 말하며, 아울러 사이버몰을 운영하는 사업자의 의미로도 사용합니다.</li><li>"이용자"란 몰에 접속하여 이 약관에 따라 회사가 제공하는 서비스를 받는 회원 및 비회원을 말합니다.</li><li>"회원"이란 회사에 개인정보를 제공하여 회원등록을 한 자로서, 회사의 정보를 계속 제공받으며 회사가 제공하는 서비스를 계속 이용할 수 있는 자를 말합니다.</li><li>"비회원"이란 회원에 가입하지 않고 회사가 제공하는 서비스를 이용하는 자를 말합니다.</li></ul>';
    $html .= '<h2>제3조 약관의 명시와 개정</h2>';
    $html .= '<p>회사는 이 약관의 내용과 상호, 대표자, 영업소 소재지, 전화번호, 전자우편주소, 사업자등록번호 등 사업자 정보를 이용자가 쉽게 알 수 있도록 몰의 초기화면 또는 연결화면에 게시합니다.</p>';
    $html .= '<p>회사는 「약관의 규제에 관한 법률」, 「전자상거래 등에서의 소비자보호에 관한 법률」 등 관련 법령을 위반하지 않는 범위에서 이 약관을 개정할 수 있습니다. 약관을 개정할 때에는 적용일자, 개정내용 및 개정사유를 명시하여 적용일자 7일 전부터 공지합니다. 이용자에게 불리하게 변경하는 경우에는 최소 30일 이상의 사전 유예기간을 두고 변경 전·후 내용을 알기 쉽게 비교하여 고지합니다.</p>';
    $html .= '<h2>제4조 서비스의 제공 및 변경</h2>';
    $html .= '<p>회사는 이용자에게 정보 제공, 콘텐츠 제공, 재화 또는 용역에 대한 거래, 회원 관리, 문의 대응 등 몰에서 정한 서비스를 제공합니다. 회사는 운영상 또는 기술상 필요가 있는 경우 제공하는 서비스의 내용을 변경할 수 있으며, 중요한 변경이 있는 경우 사전에 공지합니다.</p>';
    $html .= '<h2>제5조 서비스의 중단</h2>';
    $html .= '<p>회사는 정보통신설비의 보수·점검·교체, 장애, 통신 두절, 천재지변 등 부득이한 사유가 있는 경우 서비스 제공을 일시적으로 중단할 수 있습니다. 회사는 서비스 중단 사유와 기간을 사전에 공지하되, 사전에 공지할 수 없는 부득이한 사유가 있는 경우 사후에 공지할 수 있습니다.</p>';
    $html .= '<h2>제6조 회원가입</h2>';
    $html .= '<p>이용자는 회사가 정한 가입 양식에 따라 회원정보를 입력하고 이 약관에 동의한다는 의사표시를 함으로써 회원가입을 신청합니다. 회사는 가입 신청자에게 허위 정보 입력, 타인 명의 사용, 이전 회원자격 상실, 기술상 지장 등 승낙하기 어려운 사유가 있는 경우 신청을 거절하거나 승낙을 유보할 수 있습니다.</p>';
    $html .= '<h2>제7조 회원 탈퇴 및 자격 상실</h2>';
    $html .= '<p>회원은 언제든지 회사에 탈퇴를 요청할 수 있으며, 회사는 관련 법령과 내부 절차에 따라 회원 탈퇴를 처리합니다. 회원이 허위 정보를 등록하거나, 다른 이용자의 이용을 방해하거나, 법령·약관·운영정책을 위반한 경우 회사는 회원자격을 제한하거나 정지·상실시킬 수 있습니다.</p>';
    $html .= '<h2>제8조 회원에 대한 통지</h2>';
    $html .= '<p>회사가 회원에게 통지하는 경우 회원이 제공한 전자우편주소, 서비스 알림, 문자메시지, 서비스 화면 공지 등 합리적인 방법으로 할 수 있습니다. 불특정 다수 회원에 대한 통지는 1주일 이상 서비스 화면에 게시함으로써 개별 통지를 갈음할 수 있습니다.</p>';
    $html .= '<h2>제9조 구매신청 및 이용신청</h2>';
    $html .= '<p>이용자는 몰에서 제공하는 절차에 따라 서비스 등 검색과 선택, 약관 및 개인정보 처리에 대한 확인, 결제방법 선택, 신청 정보 확인 등 필요한 사항을 입력하여 구매 또는 이용을 신청할 수 있습니다.</p>';
    $html .= '<h2>제10조 계약의 성립</h2>';
    $html .= '<p>회사는 이용자의 신청에 대해 재고 부족, 기술상 문제, 허위 신청, 미성년자의 법정대리인 동의 미확인, 기타 승낙하기 어려운 사유가 있는 경우 승낙하지 않을 수 있습니다. 회사의 승낙이 이용자에게 도달한 때 계약이 성립한 것으로 봅니다.</p>';
    $html .= '<h2>제11조 지급방법</h2>';
    $html .= '<p>서비스 등에 대한 대금 지급은 회사가 제공하는 결제수단 중 이용자가 선택한 방법으로 할 수 있습니다. 회사는 이용자의 결제 정보 처리에 필요한 범위에서 관련 법령과 개인정보처리방침을 준수합니다.</p>';
    $html .= '<h2>제12조 서비스 등의 공급</h2>';
    $html .= '<p>회사는 이용자와 서비스 등의 공급시기에 관하여 별도 약정이 없는 한 이용자가 청약을 한 날부터 관련 법령에서 정한 기간 안에 서비스 등을 제공할 수 있도록 필요한 조치를 합니다. 공급 방식, 배송, 이용기간 등 구체적인 내용은 서비스 화면의 안내를 따릅니다.</p>';
    $html .= '<h2>제13조 환급, 반품 및 교환</h2>';
    $html .= '<p>회사는 이용자가 신청한 서비스 등을 제공할 수 없거나 계약 내용과 다르게 제공한 경우 관련 법령과 서비스 안내에 따라 환급, 반품, 교환 또는 필요한 조치를 합니다. 디지털 콘텐츠나 서비스의 제공이 개시된 경우 청약철회와 환불이 법령상 허용되는 범위에서 제한될 수 있습니다.</p>';
    $html .= '<h2>제14조 청약철회 등</h2>';
    $html .= '<p>이용자는 관련 법령에서 정한 기간과 방법에 따라 청약철회를 할 수 있습니다. 다만 이용자의 책임 있는 사유로 재화 등이 멸실·훼손된 경우, 사용 또는 소비로 가치가 현저히 감소한 경우, 복제가 가능한 콘텐츠의 포장을 훼손한 경우, 디지털 콘텐츠 제공이 개시된 경우 등 법령이 정한 사유가 있으면 청약철회가 제한될 수 있습니다.</p>';
    $html .= '<h2>제15조 개인정보보호</h2>';
    $html .= '<p>회사는 이용자의 개인정보를 보호하기 위해 관계 법령을 준수하며, 개인정보의 처리 목적, 항목, 보유기간, 제3자 제공, 처리위탁, 권리 행사 방법 등은 개인정보처리방침에서 정합니다.</p>';
    $html .= '<h2>제16조 회사의 의무</h2>';
    $html .= '<p>회사는 법령과 이 약관이 금지하거나 공서양속에 반하는 행위를 하지 않으며, 안정적인 서비스 제공을 위해 노력합니다. 회사는 이용자가 안전하게 서비스를 이용할 수 있도록 개인정보 보호와 보안에 필요한 조치를 합니다.</p>';
    $html .= '<h2>제17조 이용자의 의무</h2>';
    $html .= '<p>이용자는 신청 또는 변경 시 허위 내용을 등록해서는 안 되며, 타인의 정보 도용, 회사 또는 제3자의 권리 침해, 서비스 운영 방해, 법령이나 약관에 위반되는 행위를 해서는 안 됩니다.</p>';
    $html .= '<h2>제18조 저작권의 귀속 및 이용제한</h2>';
    $html .= '<p>회사가 작성한 저작물에 대한 저작권과 지식재산권은 회사에 귀속합니다. 이용자는 회사의 사전 승낙 없이 서비스를 이용하여 얻은 정보를 영리 목적으로 복제, 송신, 출판, 배포, 방송하거나 제3자에게 이용하게 해서는 안 됩니다.</p>';
    $html .= '<h2>제19조 분쟁해결</h2>';
    $html .= '<p>회사는 이용자가 제기하는 정당한 의견이나 불만을 반영하고 피해를 보상하기 위한 절차를 마련합니다. 이용자의 불만과 문의는 ' . sr_e($contactSentence) . '로 접수할 수 있습니다.</p>';
    $html .= '<h2>제20조 재판권 및 준거법</h2>';
    $html .= '<p>회사와 이용자 사이에 발생한 전자상거래 분쟁에 관한 소송은 관련 법령이 정한 관할 법원에 제기하며, 대한민국 법을 적용합니다.</p>';
    if ($businessInfoHtml !== '') {
        $html .= '<h2>부칙 및 사업자 정보</h2>';
        if ($representativeName !== '' || $businessAddress !== '' || $businessEmail !== '' || $customerServicePhone !== '') {
            $html .= '<p>회사는 다음 사업자 정보를 기준으로 서비스를 운영하며, 정보가 변경되는 경우 사이트 설정과 정책 문서에 반영합니다.</p>';
        }
        $html .= $businessInfoHtml;
    }

    return $html;
}

function sr_policy_document_standard_privacy_policy_html(PDO $pdo, ?array $site = null): string
{
    $context = sr_policy_document_standard_context($pdo, $site);
    $siteName = (string) $context['site_name'];
    $baseUrl = (string) $context['base_url'];
    $companyName = sr_policy_document_standard_value($context, 'company_name', $siteName);
    $representativeName = sr_policy_document_standard_value($context, 'representative_name');
    $businessAddress = sr_policy_document_standard_value($context, 'business_address');
    $businessEmail = sr_policy_document_standard_value($context, 'business_email');
    $customerServicePhone = sr_policy_document_standard_value($context, 'customer_service_phone');
    $customerServiceEmail = sr_policy_document_standard_value($context, 'customer_service_email', $businessEmail);
    $privacyOfficerName = sr_policy_document_standard_value($context, 'privacy_officer_name', $siteName . ' 개인정보보호 담당자');
    $privacyOfficerEmail = sr_policy_document_standard_value($context, 'privacy_officer_email', $customerServiceEmail);
    $businessInfoHtml = sr_policy_document_standard_business_info_list_html($context, [
        'company_name',
        'representative_name',
        'business_registration_number',
        'mail_order_report_number',
        'business_address',
        'business_email',
        'customer_service_phone',
        'customer_service_email',
        'privacy_officer_name',
        'privacy_officer_email',
        'hosting_provider',
    ]);
    $serviceLabel = $baseUrl !== '' ? $siteName . '(' . $baseUrl . ')' : $siteName;

    $html = '<h2>1. 개인정보 처리방침의 목적</h2>';
    $html .= '<p>' . sr_e($companyName) . '(이하 "회사")는 ' . sr_e($serviceLabel) . ' 서비스를 제공하면서 정보주체의 자유와 권리 보호를 위해 개인정보 보호법 및 관계 법령이 정한 절차와 기준을 준수합니다. 이 처리방침은 회사가 처리하는 개인정보의 항목, 목적, 보유기간, 권리 행사 방법과 보호 조치를 안내합니다.</p>';
    $html .= '<h2>2. 개인정보의 처리 목적, 항목 및 보유기간</h2>';
    $html .= '<ul><li>회원가입 및 계정 관리: 이메일, 비밀번호, 이름 또는 닉네임, 가입·탈퇴 기록을 회원 식별, 로그인, 부정 이용 방지, 가입 의사 확인 목적으로 처리하며 회원 탈퇴 시까지 보유합니다. 다만 법령상 보존 의무 또는 분쟁 대응 필요가 있는 기록은 해당 기간 동안 보관합니다.</li><li>서비스 제공 및 운영: 서비스 이용 기록, 접속 로그, IP 주소, 쿠키, 기기·브라우저 정보, 알림·문의 기록을 서비스 제공, 보안, 장애 대응, 고객상담, 통계와 서비스 개선 목적으로 처리하며 목적 달성 또는 보존기간 경과 시 파기합니다.</li><li>유료서비스 및 거래 처리: 결제 내역, 환불·정산 기록, 주문·이용 내역 등 거래 처리에 필요한 정보를 결제, 청약철회, 환불, 분쟁 대응 및 법정 증빙 보존 목적으로 처리합니다.</li><li>이벤트, 마케팅 또는 선택 동의 항목: 정보주체가 별도로 동의한 경우에 한해 수신 동의 여부, 연락처, 참여 내역 등을 고지한 목적과 기간 안에서 처리합니다.</li></ul>';
    $html .= '<p>관계 법령에 따라 보존하는 주요 기록과 보존기간은 다음과 같습니다.</p>';
    $html .= '<ul><li>전자상거래 등에서의 소비자보호에 관한 법률: 계약 또는 청약철회 기록 5년, 대금결제 및 재화 등의 공급 기록 5년, 소비자 불만 또는 분쟁처리 기록 3년, 표시·광고 기록 6개월</li><li>통신비밀보호법: 해당 법 적용 대상인 통신사실확인자료는 법령에서 정한 기간</li><li>개인정보 보호법: 다른 법령에 따라 보존해야 하는 개인정보는 다른 개인정보와 분리하여 저장·관리</li></ul>';
    $html .= '<h2>3. 동의 없이 처리하는 개인정보와 동의를 받아 처리하는 개인정보</h2>';
    $html .= '<p>회사는 계약의 체결 및 이행, 법령상 의무 준수, 정당한 이익 등 개인정보 보호법에서 허용하는 근거가 있는 경우 필요한 범위에서 개인정보를 동의 없이 처리할 수 있습니다. 민감정보, 고유식별정보, 개인정보의 제3자 제공, 선택 마케팅 수신 등 별도 동의가 필요한 사항은 정보주체에게 명확히 알리고 동의를 받은 뒤 처리합니다.</p>';
    $html .= '<h2>4. 개인정보의 제3자 제공</h2>';
    $html .= '<p>회사는 정보주체의 동의가 있거나 법령에 특별한 근거가 있는 경우를 제외하고 개인정보를 제3자에게 제공하지 않습니다. 제3자 제공이 필요한 경우 제공받는 자, 제공 목적, 제공 항목, 보유 및 이용기간을 사전에 알리고 동의를 받습니다.</p>';
    $html .= '<h2>5. 개인정보 처리업무의 위탁</h2>';
    $html .= '<p>회사는 안정적인 서비스 제공을 위해 호스팅, 이메일 발송, 문자 발송, 결제 처리, 고객상담 도구 등 업무를 외부 업체에 위탁할 수 있습니다. 위탁이 발생하는 경우 수탁자와 위탁업무 내용을 이 처리방침 또는 별도 화면에 공개하고, 수탁자가 개인정보를 안전하게 처리하도록 계약과 점검으로 관리합니다.</p>';
    $html .= '<h2>6. 개인정보의 파기 절차 및 방법</h2>';
    $html .= '<p>회사는 개인정보 보유기간이 지나거나 처리 목적이 달성된 경우 지체 없이 개인정보를 파기합니다. 전자적 파일은 복구 및 재생되지 않도록 삭제하고, 종이 문서는 분쇄 또는 소각합니다. 다른 법령에 따라 보존해야 하는 개인정보는 해당 개인정보 또는 파일을 분리하여 보관합니다.</p>';
    $html .= '<h2>7. 정보주체와 법정대리인의 권리 및 행사 방법</h2>';
    $html .= '<p>정보주체는 회사에 대해 개인정보 열람, 정정·삭제, 처리정지, 동의 철회를 요구할 수 있습니다. 만 14세 미만 아동의 개인정보 처리와 관련한 권리는 법정대리인이 행사할 수 있습니다. 회사는 본인 또는 정당한 대리인 여부를 확인한 뒤 관련 법령에 따라 지체 없이 조치합니다.</p>';
    $html .= '<h2>8. 개인정보 자동 수집 장치의 설치·운영 및 거부</h2>';
    $html .= '<p>회사는 로그인 유지, 보안, 이용 통계, 서비스 개선을 위해 쿠키 등 자동 수집 장치를 사용할 수 있습니다. 이용자는 브라우저 설정을 통해 쿠키 저장을 거부하거나 삭제할 수 있으며, 이 경우 로그인 유지 등 일부 기능 이용이 제한될 수 있습니다.</p>';
    $html .= '<h2>9. 행태정보 및 맞춤형 광고</h2>';
    $html .= '<p>회사가 이용자의 웹사이트 방문 이력, 앱 이용 이력 등 행태정보를 맞춤형 광고에 활용하는 경우 수집 항목, 수집 방법, 이용 목적, 보유기간 및 통제 방법을 별도로 안내합니다. 현재 해당 기능을 사용하지 않는 경우 이 항목은 "해당 없음"으로 표시할 수 있습니다.</p>';
    $html .= '<h2>10. 자동화된 결정에 관한 사항</h2>';
    $html .= '<p>회사가 완전히 자동화된 시스템으로 정보주체의 권리 또는 의무에 중대한 영향을 미치는 결정을 하는 경우 그 기준과 절차, 정보주체의 설명 요구 및 이의제기 방법을 별도로 안내합니다. 현재 해당 처리를 하지 않는 경우 이 항목은 "해당 없음"으로 표시할 수 있습니다.</p>';
    $html .= '<h2>11. 개인정보의 안전성 확보 조치</h2>';
    $html .= '<p>회사는 개인정보 보호를 위해 개인정보취급자 접근 권한 관리, 비밀번호 및 주요 인증정보의 암호화 또는 해시 처리, 접속기록 보관과 점검, 악성코드 및 취약점 점검, 물리적 접근 통제 등 필요한 기술적·관리적·물리적 보호 조치를 시행합니다.</p>';
    $html .= '<h2>12. 개인정보보호책임자 및 문의처</h2>';
    $html .= '<p>개인정보 처리와 관련한 문의, 권리 행사, 피해 구제 요청은 아래 담당자 또는 고객센터로 연락할 수 있습니다.</p>';
    $html .= '<ul><li>개인정보보호책임자: ' . sr_e($privacyOfficerName) . '</li>';
    if ($privacyOfficerEmail !== '') {
        $html .= '<li>개인정보보호책임자 이메일: ' . sr_e($privacyOfficerEmail) . '</li>';
    }
    if ($customerServicePhone !== '') {
        $html .= '<li>고객센터 전화번호: ' . sr_e($customerServicePhone) . '</li>';
    }
    if ($customerServiceEmail !== '') {
        $html .= '<li>고객센터 전자우편주소: ' . sr_e($customerServiceEmail) . '</li>';
    }
    $html .= '</ul>';
    $html .= '<h2>13. 권익침해 구제 방법</h2>';
    $html .= '<p>정보주체는 개인정보 침해에 대한 상담이나 피해 구제를 위해 개인정보침해신고센터, 개인정보분쟁조정위원회 등 전문기관에 문의할 수 있습니다.</p>';
    $html .= '<h2>14. 사업자 정보</h2>';
    if ($businessInfoHtml !== '') {
        $html .= $businessInfoHtml;
    } else {
        $html .= '<p>사업자 정보는 사이트 설정에 등록된 값을 기준으로 표시합니다.</p>';
    }
    if ($representativeName !== '' || $businessAddress !== '') {
        $html .= '<p>개인정보 처리와 관련한 회사의 기본 정보는 위 사업자 정보와 같습니다.</p>';
    }
    $html .= '<h2>15. 개인정보처리방침의 변경</h2>';
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

function sr_policy_document_body_html_from_editor_data(array $data, ?PDO $pdo = null): string
{
    $mode = (string) ($data['body_editor_mode'] ?? 'html');
    if (!in_array($mode, ['plain', 'html', 'markdown', 'ckeditor'], true)) {
        $mode = 'html';
    }

    if ($mode === 'plain') {
        return sr_policy_document_sanitize_body(sr_policy_document_plain_text_to_html((string) ($data['body_plain'] ?? '')));
    }

    if ($mode === 'markdown') {
        $markdown = (string) ($data['body_markdown'] ?? '');
        $rendererPdo = $pdo instanceof PDO ? $pdo : ($GLOBALS['pdo'] ?? null);
        if (!$rendererPdo instanceof PDO || !sr_markdown_renderer_available($rendererPdo)) {
            throw new InvalidArgumentException('Markdown 문서를 저장하려면 Markdown Editor 플러그인을 활성화하세요.');
        }

        $rendered = sr_markdown_render($rendererPdo, $markdown, 'full');
        if (is_array($rendered)) {
            return sr_policy_document_sanitize_body((string) ($rendered['html'] ?? ''));
        }

        return sr_policy_document_sanitize_body(sr_markdown_text_html($markdown));
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

function sr_policy_document_notice_mail_subject(PDO $pdo, int $versionId): string
{
    $version = sr_policy_document_version_by_id($pdo, $versionId);
    $metadata = [
        'site_name' => '',
        'document_title' => '',
        'effective_date' => sr_t('policy_documents::ui.effective_from.empty'),
    ];
    if (is_array($version)) {
        $metadata['document_title'] = (string) ($version['title_snapshot'] ?? '');
        $metadata['effective_date'] = sr_policy_document_effective_date_label((string) ($version['effective_from'] ?? ''));
    }

    $rendered = sr_delivery_template_render($pdo, 'policy_documents.version_notice', $metadata);
    $subject = sr_clean_single_line((string) ($rendered['subject'] ?? ''), 190);
    return $subject !== '' ? $subject : '정책 문서 변경 안내';
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
    $bodyHtml = sr_policy_document_body_html_from_editor_data($data, $pdo);
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
    $bodyHtml = sr_policy_document_body_html_from_editor_data($data, $pdo);
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
                d.document_key, d.title AS document_title,
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
