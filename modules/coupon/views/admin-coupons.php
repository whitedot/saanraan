<?php

$couponAdminPage = isset($couponAdminPage) ? (string) $couponAdminPage : 'definitions';
if (!in_array($couponAdminPage, ['definitions', 'issues'], true)) {
    $couponAdminPage = 'definitions';
}
$adminPageTitle = $couponAdminPage === 'issues' ? '지급 내역' : '쿠폰 관리';
$adminPageSubtitle = $couponAdminPage === 'issues'
    ? '쿠폰 지급 내역을 확인하고 상태를 관리합니다.'
    : '지급할 쿠폰 종류를 관리합니다.';
$targetTypes = sr_coupon_target_types($pdo);
$couponSearchableTargetTypes = array_filter($targetTypes, static function (string $targetType): bool {
    return $targetType !== 'all';
}, ARRAY_FILTER_USE_KEY);
$refundablePolicies = sr_coupon_refundable_policies();
$definitionStatusLabels = [
    'active' => '사용 중',
    'disabled' => '중지',
];
$couponCreateModalId = 'coupon-create-modal';
$couponCreateModalOpen = isset($couponCreateModalOpen) && $couponCreateModalOpen === true;
$couponCreateModalClass = $couponCreateModalOpen
    ? 'modal-overlay modal-overlay-fade overlay overlay-open'
    : 'modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0';
$couponIssueModalOpenDefinitionId = isset($couponIssueModalOpenDefinitionId) ? (int) $couponIssueModalOpenDefinitionId : 0;
$couponTargetLookupModalId = 'coupon-target-lookup-modal';
$couponTargetLookupResultsId = 'coupon-target-lookup-results';
$couponInitialTargetType = (string) array_key_first($targetTypes);
$couponTargetSearchEnabled = $couponInitialTargetType !== 'all' && array_key_exists($couponInitialTargetType, $couponSearchableTargetTypes);
$couponMemberLookupModalId = 'coupon-member-lookup-modal';
$couponMemberLookupResultsId = 'coupon-member-lookup-results';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($couponAdminPage === 'definitions') { ?>
<section class="admin-card card">
    <div class="card-header">
        <div>
            <h2>쿠폰 종류</h2>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($couponCreateModalId); ?>" data-overlay="#<?php echo sr_e($couponCreateModalId); ?>">쿠폰 추가</button>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>관리용 키</th>
                <th>쿠폰 이름</th>
                <th>사용처</th>
                <th>상태</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($definitions === []) { ?>
                <tr>
                    <td colspan="5" class="admin-empty-state">등록된 쿠폰 종류가 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($definitions as $definition) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $definition['coupon_key']); ?></td>
                        <td><?php echo sr_e((string) $definition['title']); ?></td>
                        <td><?php echo sr_e((string) ($targetTypes[(string) $definition['target_type']] ?? $definition['target_type'])); ?> <?php echo sr_e((string) $definition['target_id']); ?></td>
                        <td><?php echo sr_e((string) ($definitionStatusLabels[(string) $definition['status']] ?? $definition['status'])); ?></td>
                        <td>
                            <?php
                            $definitionId = (int) ($definition['id'] ?? 0);
                            $issueModalId = 'coupon-issue-modal-' . $definitionId;
                            ?>
                            <?php if ((string) $definition['status'] === 'active') { ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($issueModalId); ?>" data-overlay="#<?php echo sr_e($issueModalId); ?>">지급하기</button>
                            <?php } ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="set_definition_status">
                                <input type="hidden" name="definition_id" value="<?php echo sr_e((string) $definition['id']); ?>">
                                <input type="hidden" name="status" value="<?php echo (string) $definition['status'] === 'active' ? 'disabled' : 'active'; ?>">
                                <button type="submit" class="btn btn-sm btn-solid-light"><?php echo (string) $definition['status'] === 'active' ? '사용 중지' : '다시 사용'; ?></button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php foreach ($definitions as $definition) { ?>
    <?php
    $definitionId = (int) ($definition['id'] ?? 0);
    $issueModalId = 'coupon-issue-modal-' . $definitionId;
    $issueModalOpen = $couponIssueModalOpenDefinitionId === $definitionId;
    $issueModalClass = $issueModalOpen
        ? 'modal-overlay modal-overlay-fade overlay overlay-open'
        : 'modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0';
    ?>
    <div id="<?php echo sr_e($issueModalId); ?>" class="<?php echo sr_e($issueModalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($issueModalId); ?>_title" aria-hidden="<?php echo $issueModalOpen ? 'false' : 'true'; ?>"<?php echo $issueModalOpen ? '' : ' inert'; ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="modal-content ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="issue_coupon">
                <input type="hidden" name="coupon_definition_id" value="<?php echo sr_e((string) $definitionId); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($issueModalId); ?>_title" class="modal-title">지급하기</h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($issueModalId); ?>">
                        <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="admin-form-row">
                        <label class="form-label" for="coupon_admin_issue_mode_<?php echo sr_e((string) $definitionId); ?>">지급 대상 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <select id="coupon_admin_issue_mode_<?php echo sr_e((string) $definitionId); ?>" name="issue_target_mode" class="form-select" data-coupon-issue-mode required>
                                <option value="member">회원</option>
                                <option value="all">전체</option>
                                <option value="group"<?php echo $memberGroups === [] ? ' disabled' : ''; ?>>그룹</option>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row" data-coupon-issue-member-row>
                        <label class="form-label" for="coupon_admin_issue_account_<?php echo sr_e((string) $definitionId); ?>">회원 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field" data-coupon-issue-member-field>
                            <div class="admin-lookup-control">
                                <input id="coupon_admin_issue_account_<?php echo sr_e((string) $definitionId); ?>" type="text" name="account_identifier" class="form-control form-input" maxlength="80" data-overlay-focus required>
                                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($couponMemberLookupModalId); ?>" data-overlay="#<?php echo sr_e($couponMemberLookupModalId); ?>" data-overlay-stack="true" data-admin-member-lookup-open data-target="#coupon_admin_issue_account_<?php echo sr_e((string) $definitionId); ?>">회원 검색</button>
                            </div>
                            <p class="admin-form-help">회원 관리 화면의 공개 해시 또는 회원 ID를 입력합니다.</p>
                        </div>
                    </div>
                    <div class="admin-form-row" data-coupon-issue-group-row hidden>
                        <label class="form-label" for="coupon_admin_issue_group_<?php echo sr_e((string) $definitionId); ?>">그룹 <span class="sr-required-label">(필수)</span></label>
                        <div class="admin-form-field">
                            <select id="coupon_admin_issue_group_<?php echo sr_e((string) $definitionId); ?>" name="group_key" class="form-select" data-coupon-issue-group disabled>
                                <option value="">그룹 선택</option>
                                <?php foreach ($memberGroups as $memberGroup) { ?>
                                    <option value="<?php echo sr_e((string) ($memberGroup['group_key'] ?? '')); ?>"><?php echo sr_e((string) ($memberGroup['title'] ?? '')); ?> (<?php echo sr_e((string) ($memberGroup['active_member_count'] ?? '0')); ?>명)</option>
                                <?php } ?>
                            </select>
                        </div>
                    </div>
                    <div class="admin-form-row">
                        <label class="form-label" for="coupon_admin_issue_reason_<?php echo sr_e((string) $definitionId); ?>">지급 사유</label>
                        <div class="admin-form-field">
                            <input id="coupon_admin_issue_reason_<?php echo sr_e((string) $definitionId); ?>" type="text" name="issued_reason" class="form-control" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($issueModalId); ?>">닫기</button>
                    <button type="submit" class="btn btn-solid-primary modal-action">쿠폰 지급</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<div id="<?php echo sr_e($couponMemberLookupModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($couponMemberLookupModalId); ?>_title" aria-hidden="true" inert data-overlay-stack="true">
    <div class="modal-dialog admin-lookup-dialog">
        <div class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($couponMemberLookupModalId); ?>_title" class="modal-title">회원 검색</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($couponMemberLookupModalId); ?>">
                    <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="admin-lookup-search-form" data-admin-member-search-form data-endpoint="<?php echo sr_e(sr_url('/admin/coupons/member-search')); ?>" data-target="" data-results="#<?php echo sr_e($couponMemberLookupResultsId); ?>">
                    <select name="field" class="form-select" aria-label="회원 검색 조건">
                        <option value="all">전체</option>
                        <option value="hash">공개 해시</option>
                        <option value="email">이메일</option>
                        <option value="login_id">로그인 ID</option>
                        <option value="name">이름</option>
                    </select>
                    <input type="text" name="q" maxlength="120" class="form-input" placeholder="이메일, 로그인 ID, 이름" data-overlay-focus>
                    <button type="submit" class="btn btn-solid-primary">검색</button>
                </form>
                <div id="<?php echo sr_e($couponMemberLookupResultsId); ?>" class="admin-lookup-results">
                    <p class="admin-empty-state admin-lookup-empty">검색어 없이 검색하면 최근 회원이 표시됩니다.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($couponMemberLookupModalId); ?>">닫기</button>
            </div>
        </div>
    </div>
</div>

<div id="<?php echo sr_e($couponCreateModalId); ?>" class="<?php echo sr_e($couponCreateModalClass); ?>" role="dialog" tabindex="-1" aria-labelledby="coupon_create_modal_title" aria-hidden="<?php echo $couponCreateModalOpen ? 'false' : 'true'; ?>"<?php echo $couponCreateModalOpen ? '' : ' inert'; ?>>
    <div class="modal-dialog modal-dialog-lg">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons')); ?>" class="modal-content ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="create_definition">
            <div class="modal-header">
                <h3 id="coupon_create_modal_title" class="modal-title">쿠폰 추가</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($couponCreateModalId); ?>">
                    <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="admin-form-row">
                    <label class="form-label" for="coupon_admin_coupon_key">관리용 키 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <input id="coupon_admin_coupon_key" type="text" name="coupon_key" class="form-control" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" data-admin-key-input data-overlay-focus required>
                        <p class="admin-form-help">관리자가 구분하기 위한 고유값입니다. 영문 소문자로 시작하고 소문자, 숫자, 밑줄만 사용합니다.</p>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="coupon_admin_title">쿠폰 이름 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <input id="coupon_admin_title" type="text" name="title" class="form-control" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="coupon_admin_description">설명</label>
                    <div class="admin-form-field">
                        <textarea id="coupon_admin_description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="coupon_admin_target_type">사용처 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <select id="coupon_admin_target_type" name="target_type" class="form-select" required>
                            <?php foreach ($targetTypes as $targetType => $targetTypeLabel) { ?>
                                <option value="<?php echo sr_e((string) $targetType); ?>"><?php echo sr_e((string) $targetTypeLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="coupon_admin_target_id">대상 번호</label>
                    <div class="admin-form-field">
                        <div class="admin-lookup-control">
                            <input id="coupon_admin_target_id" type="text" name="target_id" class="form-control form-input" maxlength="80">
                            <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($couponTargetLookupModalId); ?>" data-overlay="#<?php echo sr_e($couponTargetLookupModalId); ?>" data-overlay-stack="true" data-admin-reference-lookup-open data-coupon-target-search-button data-type-target="#coupon_admin_target_type" data-id-target="#coupon_admin_target_id"<?php echo $couponTargetSearchEnabled ? '' : ' disabled hidden'; ?>>검색</button>
                        </div>
                        <p class="admin-form-help">특정 콘텐츠나 게시글에만 쓰게 할 때 해당 번호를 입력합니다. 비워 두면 선택한 사용처 전체에 사용할 수 있습니다.</p>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="coupon_admin_refundable_policy">환급 정책 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <select id="coupon_admin_refundable_policy" name="refundable_policy" class="form-select" required>
                            <?php foreach ($refundablePolicies as $policy => $policyLabel) { ?>
                                <option value="<?php echo sr_e((string) $policy); ?>"><?php echo sr_e((string) $policyLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="coupon_admin_max_uses">사용 횟수 <span class="sr-required-label">(필수)</span></label>
                    <div class="admin-form-field">
                        <input id="coupon_admin_max_uses" type="number" name="max_uses_per_issue" class="form-control" min="1" max="1000" value="1" required>
                        <p class="admin-form-help">발급된 쿠폰 1장이 몇 번까지 사용할 수 있는지 정합니다.</p>
                    </div>
                </div>
                <input type="hidden" name="status" value="active">
                <input type="hidden" name="coupon_type" value="access">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($couponCreateModalId); ?>">닫기</button>
                <button type="submit" class="btn btn-solid-primary modal-action">쿠폰 추가</button>
            </div>
        </form>
    </div>
</div>

<div id="<?php echo sr_e($couponTargetLookupModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($couponTargetLookupModalId); ?>_title" aria-hidden="true" inert data-overlay-stack="true">
    <div class="modal-dialog admin-lookup-dialog">
        <div class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="<?php echo sr_e($couponTargetLookupModalId); ?>_title" class="modal-title">대상 검색</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($couponTargetLookupModalId); ?>">
                    <span class="sr-icon" aria-hidden="true" data-sr-material-icon>close</span>
                </button>
            </div>
            <div class="modal-body">
                <form class="admin-lookup-search-form" data-admin-reference-search-form data-endpoint="<?php echo sr_e(sr_url('/admin/coupons/target-search')); ?>" data-type-target="#coupon_admin_target_type" data-id-target="#coupon_admin_target_id" data-results="#<?php echo sr_e($couponTargetLookupResultsId); ?>">
                    <select name="reference_type" class="form-select" aria-label="대상 유형">
                        <?php foreach ($couponSearchableTargetTypes as $targetType => $targetTypeLabel) { ?>
                            <option value="<?php echo sr_e((string) $targetType); ?>"><?php echo sr_e((string) $targetTypeLabel); ?></option>
                        <?php } ?>
                    </select>
                    <input type="text" name="q" maxlength="120" class="form-input" placeholder="번호, 제목, key" data-overlay-focus>
                    <button type="submit" class="btn btn-solid-primary">검색</button>
                </form>
                <div id="<?php echo sr_e($couponTargetLookupResultsId); ?>" class="admin-lookup-results">
                    <p class="admin-empty-state admin-lookup-empty">사용처를 선택하고 검색어 없이 검색하면 최근 대상이 표시됩니다.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($couponTargetLookupModalId); ?>">닫기</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    var targetType = document.getElementById('coupon_admin_target_type');
    var searchButton = document.querySelector('[data-coupon-target-search-button]');
    var targetId = document.getElementById('coupon_admin_target_id');
    var searchableTypes = <?php echo json_encode(array_keys($couponSearchableTargetTypes), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function syncTargetSearchButton() {
        if (!targetType || !searchButton) {
            return;
        }

        var searchable = searchableTypes.indexOf(targetType.value) !== -1;
        searchButton.disabled = !searchable;
        searchButton.hidden = !searchable;
        searchButton.classList.toggle('hidden', !searchable);
        if (!searchable && targetId) {
            targetId.value = '';
        }
    }

    if (targetType) {
        targetType.addEventListener('change', syncTargetSearchButton);
    }
    syncTargetSearchButton();

    function syncIssueTargetMode(select) {
        var modal = select.closest('.modal-content');
        if (!modal) {
            return;
        }

        var memberRow = modal.querySelector('[data-coupon-issue-member-row]');
        var memberInput = modal.querySelector('input[name="account_identifier"]');
        var groupRow = modal.querySelector('[data-coupon-issue-group-row]');
        var groupSelect = modal.querySelector('[data-coupon-issue-group]');
        var mode = select.value;

        if (memberRow) {
            memberRow.hidden = mode !== 'member';
        }
        if (memberInput) {
            memberInput.required = mode === 'member';
            memberInput.disabled = mode !== 'member';
        }
        if (groupRow) {
            groupRow.hidden = mode !== 'group';
        }
        if (groupSelect) {
            groupSelect.required = mode === 'group';
            groupSelect.disabled = mode !== 'group';
        }
    }

    document.querySelectorAll('[data-coupon-issue-mode]').forEach(function (select) {
        select.addEventListener('change', function () {
            syncIssueTargetMode(select);
        });
        syncIssueTargetMode(select);
    });
})();
</script>
<?php } ?>

<?php if ($couponAdminPage === 'issues') { ?>
<section class="admin-card card">
    <h2>최근 지급 내역</h2>
    <table class="table">
        <thead>
            <tr>
                <th>회원</th>
                <th>쿠폰</th>
                <th>상태</th>
                <th>사용 횟수</th>
                <th>지급일</th>
                <th>관리</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($issues === []) { ?>
                <tr>
                    <td colspan="6" class="admin-empty-state">최근 지급 내역이 없습니다.</td>
                </tr>
            <?php } else { ?>
                <?php foreach ($issues as $issue) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $issue['account_public_hash']); ?></td>
                        <td><?php echo sr_e((string) $issue['title']); ?></td>
                        <td><?php echo sr_e(sr_coupon_issue_status_label((string) $issue['status'])); ?></td>
                        <td><?php echo sr_e((string) $issue['used_count']); ?></td>
                        <td><?php echo sr_e((string) $issue['issued_at']); ?></td>
                        <td>
                            <?php if ((string) $issue['status'] === 'active') { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/coupons/issues')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="set_issue_status">
                                    <input type="hidden" name="issue_id" value="<?php echo sr_e((string) $issue['id']); ?>">
                                    <input type="hidden" name="status" value="revoked">
                                    <button type="submit" class="btn btn-sm btn-solid-light">지급 취소</button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </tbody>
    </table>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
