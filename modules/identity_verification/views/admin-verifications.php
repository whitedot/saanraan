<?php

$adminContainerClass = 'identity-verification-admin-verifications admin-ui-scope';
$adminPageTitle = '본인확인 이력';
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/identity-verifications');
$attemptSortOptions = isset($attemptSortOptions) && is_array($attemptSortOptions) ? $attemptSortOptions : sr_identity_verification_admin_attempt_sort_options();
$attemptDefaultSort = isset($attemptDefaultSort) && is_array($attemptDefaultSort) ? $attemptDefaultSort : sr_identity_verification_admin_attempt_default_sort();
$attemptSort = isset($attemptSort) && is_array($attemptSort) ? $attemptSort : $attemptDefaultSort;
$statusLabels = sr_identity_verification_attempt_status_labels();
$selectedPurpose = (string) ($filters['purpose'] ?? '');
$purposeFilterValue = $selectedPurpose !== '' ? sr_identity_verification_purpose_label($selectedPurpose) : '';
$attemptStatusDescriptions = [
    'ready' => '시도 기록이 만들어졌고 외부 제공자 호출을 준비하는 상태입니다.',
    'pending' => '외부 제공자 인증 화면 또는 callback/return 처리를 기다리는 상태입니다.',
    'verified' => '제공자 검증이 끝나 결과가 저장된 상태입니다.',
    'failed' => '제공자 검증 또는 결과 연결에 실패한 상태입니다.',
    'expired' => '시도 유효 시간이 지나 더 이상 처리하지 않는 상태입니다.',
    'canceled' => '사용자 또는 제공자 흐름에서 취소된 상태입니다.',
];
$attemptDetailFields = [
    'verification_key' => '시도 키',
    'provider_key' => '제공자',
    'purpose' => '사용처',
    'account_id' => '계정',
    'status' => '상태',
    'provider_transaction_id' => '제공자 거래 ID',
    'provider_reference' => '제공자 참조',
    'failure_code' => '실패 코드',
    'failure_message' => '실패 메시지',
    'requested_at' => '요청',
    'completed_at' => '완료',
    'failed_at' => '실패',
    'expires_at' => '만료',
];
$resultDetailFields = [
    'id' => '결과 ID',
    'provider_key' => '제공자',
    'provider_transaction_id' => '제공자 거래 ID',
    'birth_date' => '생년월일',
    'gender' => '성별',
    'nationality' => '국적',
    'age_over_14' => '만 14세 이상',
    'age_over_19' => '성인',
    'verified_at' => '검증',
    'expires_at' => '만료',
];
$selectedStatuses = (string) ($filters['status'] ?? '') !== '' ? [(string) $filters['status']] : [];
$selectedProviderKey = (string) ($filters['provider_key'] ?? '');
$providerLabels = [];
foreach ($providers as $key => $provider) {
    $providerLabels[(string) $key] = (string) ($provider['display_name'] ?? $key);
}
if ($selectedProviderKey !== '' && !isset($providerLabels[$selectedProviderKey])) {
    $providerLabels[$selectedProviderKey] = $selectedProviderKey;
}
$identityProviderLabel = static function (string $providerKey) use ($providerLabels): string {
    return (string) ($providerLabels[$providerKey] ?? $providerKey);
};
$detailFilterOpen = (string) ($filters['purpose'] ?? '') !== ''
    || (string) ($filters['status'] ?? '') !== ''
    || (int) ($filters['account_id'] ?? 0) > 0
    || (string) ($filters['date_from'] ?? '') !== ''
    || (string) ($filters['date_to'] ?? '') !== '';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>
<form method="get" action="<?php echo sr_e(sr_url('/admin/identity-verifications')); ?>" class="filtering-form identity-verification-attempt-filter ui-form-theme">
    <div class="filtering-fields identity-verification-filter-stack">
        <div class="filtering filtering-card<?php echo $detailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
            <div class="filtering-fields filtering-fields-fit">
                <label class="filtering-field" for="identity_verification_filter_provider">
                    <span class="filtering-label">제공자</span>
                    <select id="identity_verification_filter_provider" name="provider_key" class="form-select filtering-input">
                        <option value="">전체 제공자</option>
                        <?php foreach ($providerLabels as $providerKey => $providerLabel) { ?>
                            <option value="<?php echo sr_e((string) $providerKey); ?>"<?php echo $selectedProviderKey === (string) $providerKey ? ' selected' : ''; ?>><?php echo sr_e((string) $providerLabel); ?></option>
                        <?php } ?>
                    </select>
                </label>
                <label class="filtering-field-fill filtering-field" for="identity_verification_filter_q">
                    <span class="filtering-label">검색</span>
                    <input id="identity_verification_filter_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="시도 번호, 거래 참조, 사용처, 실패 코드">
                </label>
            </div>
            <div id="identity_verification_detail_filters" class="filtering-body identity-verification-detail-filters" data-filtering-body<?php echo $detailFilterOpen ? '' : ' hidden'; ?>>
                <div class="filtering-field identity-verification-filter-status">
                    <span class="filtering-label">상태</span>
                    <?php echo sr_admin_filter_radio_toggle_group_html('identity_verification_filter_status', 'status', $statusLabels, $selectedStatuses, '전체'); ?>
                </div>
                <label class="filtering-field identity-verification-filter-purpose" for="identity_verification_filter_purpose">
                    <span class="filtering-label">사용처</span>
                    <input id="identity_verification_filter_purpose" type="text" name="purpose" value="<?php echo sr_e($purposeFilterValue); ?>" class="form-input filtering-input" maxlength="80" placeholder="회원가입, 회원탈퇴, content.author_application">
                </label>
                <label class="filtering-field identity-verification-filter-account" for="identity_verification_filter_account_id">
                    <span class="filtering-label">회원</span>
                    <input id="identity_verification_filter_account_id" type="text" name="account_id" value="<?php echo (int) ($filters['account_id'] ?? 0) > 0 ? sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), (int) $filters['account_id'])) : ''; ?>" class="form-input filtering-input" maxlength="80" autocomplete="off">
                </label>
                <label class="filtering-field identity-verification-filter-date" for="identity_verification_filter_date_from">
                    <span class="filtering-label">시작일</span>
                    <input id="identity_verification_filter_date_from" type="date" name="date_from" value="<?php echo sr_e((string) ($filters['date_from'] ?? '')); ?>" class="form-input filtering-input">
                </label>
                <label class="filtering-field identity-verification-filter-date" for="identity_verification_filter_date_to">
                    <span class="filtering-label">종료일</span>
                    <input id="identity_verification_filter_date_to" type="date" name="date_to" value="<?php echo sr_e((string) ($filters['date_to'] ?? '')); ?>" class="form-input filtering-input">
                </label>
            </div>
            <div class="filtering-actions">
                <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $detailFilterOpen ? 'true' : 'false'; ?>" aria-controls="identity_verification_detail_filters">상세검색</button>
                <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
            </div>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form identity-verification-attempt-card">
    <div class="card-header">
        <div>
            <h2 class="card-title">본인확인 이력</h2>
            <p class="form-help">외부 제공자 시도와 검증 결과 연결 상태를 조회합니다.</p>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="<?php echo sr_e(sr_url('/admin/identity-providers')); ?>">환경설정</a>
    </div>
    <div class="admin-list-summary-row">
        <?php if (empty($attemptSort['is_default'])) { ?>
            <a href="<?php echo sr_e(sr_admin_sort_url($attemptSortOptions, $attemptDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="본인확인 이력 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
        <?php } ?>
        <?php echo sr_admin_pagination_summary_html($attemptPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list identity-verification-attempt-table">
            <caption class="sr-only">본인확인 이력</caption>
            <thead>
                <tr>
                    <th<?php echo sr_admin_sort_aria('requested_at', $attemptSort); ?>><?php echo sr_admin_sort_header_html('요청 시각', 'requested_at', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('provider_key', $attemptSort); ?>><?php echo sr_admin_sort_header_html('제공자', 'provider_key', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('purpose', $attemptSort); ?>><?php echo sr_admin_sort_header_html('사용처', 'purpose', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('account_id', $attemptSort); ?>><?php echo sr_admin_sort_header_html('회원', 'account_id', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $attemptSort); ?>><?php echo sr_admin_sort_header_html('상태', 'status', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('verified_at', $attemptSort); ?>><?php echo sr_admin_sort_header_html('검증 시각', 'verified_at', $attemptSort, $attemptSortOptions, $attemptDefaultSort); ?></th>
                    <th>상세</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attempts === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">본인확인 이력이 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($attempts as $attempt) { ?>
                    <?php
                    $attemptStatus = (string) ($attempt['status'] ?? '');
                    $attemptProviderKey = (string) ($attempt['provider_key'] ?? '');
                    $attemptAccountId = (int) ($attempt['account_id'] ?? 0);
                    $attemptId = (int) ($attempt['id'] ?? 0);
                    $attemptUrl = sr_url('/admin/identity-verifications/' . $attemptId);
                    $attemptDetailModalId = 'identity-verification-detail-modal-' . $attemptId;
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($attempt['requested_at'] ?? '')); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e($identityProviderLabel($attemptProviderKey)); ?></strong>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e(sr_identity_verification_purpose_label((string) ($attempt['purpose'] ?? ''))); ?></strong>
                        </td>
                        <td class="admin-table-break">
                            <?php if ($attemptAccountId > 0) { ?>
                                <code><?php echo sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $attemptAccountId)); ?></code>
                            <?php } else { ?>
                                <?php echo sr_e('계정 없음'); ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e(sr_identity_verification_attempt_status_class($attemptStatus)); ?>"><?php echo sr_e(sr_identity_verification_attempt_status_label($attemptStatus)); ?></span>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($attempt['verified_at'] ?? '')); ?></td>
                        <td>
                            <a href="<?php echo sr_e($attemptUrl); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="본인확인 상세" title="본인확인 상세" aria-haspopup="dialog" aria-expanded="<?php echo $openDetailId === $attemptId ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($attemptDetailModalId); ?>" data-overlay="#<?php echo sr_e($attemptDetailModalId); ?>"><?php echo sr_material_icon_html('visibility'); ?></a>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('identity_verification_attempt_status', $statusLabels, $attemptStatusDescriptions, '본인확인 상태 설명'); ?>
</section>

<?php echo sr_admin_pagination_html($attemptPagination, '본인확인 이력 페이지'); ?>

<?php foreach ($attempts as $attempt) { ?>
    <?php
    $attemptId = (int) ($attempt['id'] ?? 0);
    if ($attemptId <= 0) {
        continue;
    }
    $attemptStatus = (string) ($attempt['status'] ?? '');
    $attemptProviderKey = (string) ($attempt['provider_key'] ?? '');
    $attemptDetail = isset($attemptDetailsById[$attemptId]) && is_array($attemptDetailsById[$attemptId])
        ? $attemptDetailsById[$attemptId]
        : ['result' => null, 'links' => []];
    $detailResult = isset($attemptDetail['result']) && is_array($attemptDetail['result']) ? $attemptDetail['result'] : null;
    $detailLinks = isset($attemptDetail['links']) && is_array($attemptDetail['links']) ? $attemptDetail['links'] : [];
    $attemptDetailModalId = 'identity-verification-detail-modal-' . $attemptId;
    $attemptDetailModalOpen = $openDetailId === $attemptId;
    ?>
    <div id="<?php echo sr_e($attemptDetailModalId); ?>" class="modal-overlay modal-overlay-fade overlay<?php echo $attemptDetailModalOpen ? ' overlay-open open' : ' hidden pointer-events-none opacity-0'; ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($attemptDetailModalId); ?>_title" aria-hidden="<?php echo $attemptDetailModalOpen ? 'false' : 'true'; ?>"<?php echo $attemptDetailModalOpen ? '' : ' inert'; ?>>
        <div class="modal-dialog modal-dialog-lg identity-verification-detail-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h3 id="<?php echo sr_e($attemptDetailModalId); ?>_title" class="modal-title">본인확인 상세 #<?php echo sr_e((string) $attemptId); ?></h3>
                        <p class="identity-verification-modal-subtitle"><?php echo sr_e($identityProviderLabel($attemptProviderKey)); ?> · <?php echo sr_e(sr_identity_verification_purpose_label((string) ($attempt['purpose'] ?? ''))); ?></p>
                    </div>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($attemptDetailModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body identity-verification-detail-modal-body">
                    <div class="admin-summary-stats identity-verification-detail-summary">
                        <span class="badge badge-pill badge-soft-secondary"><?php echo sr_e($identityProviderLabel($attemptProviderKey)); ?></span>
                        <span class="badge badge-pill badge-soft-info"><?php echo sr_e(sr_identity_verification_purpose_label((string) ($attempt['purpose'] ?? ''))); ?></span>
                        <span class="admin-status <?php echo sr_e(sr_identity_verification_attempt_status_class($attemptStatus)); ?>"><?php echo sr_e(sr_identity_verification_attempt_status_label($attemptStatus)); ?></span>
                        <span class="badge badge-pill badge-outline-default"><?php echo sr_admin_time_html((string) ($attempt['requested_at'] ?? '')); ?></span>
                    </div>

                    <div class="identity-verification-detail-rows">
                        <?php foreach ($attemptDetailFields as $field => $label) { ?>
                            <div class="form-row">
                                <div class="form-row-label"><?php echo sr_e($label); ?></div>
                                <div class="form-field">
                                    <?php if ($field === 'status') { ?>
                                        <span class="admin-status <?php echo sr_e(sr_identity_verification_attempt_status_class($attemptStatus)); ?>"><?php echo sr_e(sr_identity_verification_attempt_status_label($attemptStatus)); ?></span>
                                    <?php } elseif ($field === 'provider_key') { ?>
                                        <?php echo sr_e($identityProviderLabel($attemptProviderKey)); ?>
                                        <small class="admin-summary-meta"><?php echo sr_e($attemptProviderKey); ?></small>
                                    <?php } elseif ($field === 'purpose') { ?>
                                        <?php echo sr_e(sr_identity_verification_purpose_label((string) ($attempt[$field] ?? ''))); ?>
                                        <small class="admin-summary-meta"><?php echo sr_e((string) ($attempt[$field] ?? '')); ?></small>
                                    <?php } elseif ($field === 'account_id' && (int) ($attempt['account_id'] ?? 0) > 0) { ?>
                                        <code><?php echo sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), (int) $attempt['account_id'])); ?></code>
                                    <?php } elseif ($field === 'failure_code') { ?>
                                        <?php if ((string) ($attempt[$field] ?? '') !== '') { ?>
                                            <?php echo sr_e(sr_identity_verification_failure_code_label((string) $attempt[$field])); ?>
                                            <small class="admin-summary-meta"><?php echo sr_e((string) $attempt[$field]); ?></small>
                                        <?php } else { ?>
                                            -
                                        <?php } ?>
                                    <?php } elseif (in_array($field, ['requested_at', 'completed_at', 'failed_at', 'expires_at'], true)) { ?>
                                        <?php echo sr_admin_time_html((string) ($attempt[$field] ?? '')); ?>
                                    <?php } else { ?>
                                        <?php echo (string) ($attempt[$field] ?? '') !== '' ? sr_e((string) $attempt[$field]) : '-'; ?>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <p class="form-help identity-verification-detail-note">원문 개인정보는 표시하지 않고 저장 여부와 최소 결과만 보여줍니다.</p>
                    <?php if ($detailResult === null) { ?>
                        <p class="admin-empty-state identity-verification-modal-empty">저장된 검증 결과가 없습니다.</p>
                    <?php } else { ?>
                        <div class="identity-verification-detail-rows">
                            <?php foreach ($resultDetailFields as $field => $label) { ?>
                                <div class="form-row">
                                    <div class="form-row-label"><?php echo sr_e($label); ?></div>
                                    <div class="form-field">
                                        <?php if ($field === 'provider_key') { ?>
                                            <?php echo sr_e($identityProviderLabel((string) ($detailResult[$field] ?? ''))); ?>
                                            <small class="admin-summary-meta"><?php echo sr_e((string) ($detailResult[$field] ?? '')); ?></small>
                                        <?php } elseif (in_array($field, ['verified_at', 'expires_at'], true)) { ?>
                                            <?php echo sr_admin_time_html((string) ($detailResult[$field] ?? '')); ?>
                                        <?php } else { ?>
                                            <?php echo (string) ($detailResult[$field] ?? '') !== '' ? sr_e((string) $detailResult[$field]) : '-'; ?>
                                        <?php } ?>
                                    </div>
                                </div>
                            <?php } ?>
                            <?php foreach (['ci_hash' => 'CI', 'di_hash' => 'DI', 'name_hash' => '이름', 'phone_hash' => '휴대폰'] as $field => $label) { ?>
                                <div class="form-row">
                                    <div class="form-row-label"><?php echo sr_e($label . ' 식별자'); ?></div>
                                    <div class="form-field"><?php echo (string) ($detailResult[$field] ?? '') !== '' ? sr_e('보관됨') : sr_e('없음'); ?></div>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>

                    <div class="identity-verification-detail-links">
                        <p class="form-help">본인확인 결과가 회원 계정의 사용처별 확인 근거로 연결된 기록입니다.</p>
                        <?php if ($detailLinks === []) { ?>
                            <p class="admin-empty-state identity-verification-modal-empty">계정 연결 기록이 없습니다.</p>
                        <?php } else { ?>
                            <div class="table-wrapper">
                                <table class="table table-list identity-verification-link-table">
                                    <caption class="sr-only">본인확인 계정 연결</caption>
                                    <thead>
                                        <tr>
                                            <th>계정</th>
                                            <th>사용처</th>
                                            <th>연결</th>
                                            <th>해제</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($detailLinks as $link) { ?>
                                            <?php $linkAccountId = (int) ($link['account_id'] ?? 0); ?>
                                            <tr>
                                                <td>
                                                    <?php if ($linkAccountId > 0) { ?>
                                                        <code><?php echo sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $linkAccountId)); ?></code>
                                                    <?php } else { ?>
                                                        -
                                                    <?php } ?>
                                                </td>
                                                <td>
                                                    <?php echo sr_e(sr_identity_verification_purpose_label((string) $link['purpose'])); ?>
                                                    <small class="admin-summary-meta"><?php echo sr_e((string) $link['purpose']); ?></small>
                                                </td>
                                                <td><?php echo sr_admin_time_html((string) $link['linked_at']); ?></td>
                                                <td><?php echo sr_admin_time_html((string) ($link['revoked_at'] ?? '')); ?></td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($attemptDetailModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
