<?php

$assetAdjustLookup = isset($assetAdjustLookup) && is_array($assetAdjustLookup) ? $assetAdjustLookup : [];
$assetAdjustPrefix = (string) ($assetAdjustLookup['field_prefix'] ?? 'asset_adjust');
$assetAdjustMemberInputId = (string) ($assetAdjustLookup['member_input_id'] ?? '');
$assetAdjustReferenceTypeId = (string) ($assetAdjustLookup['reference_type_id'] ?? '');
$assetAdjustReferenceIdId = (string) ($assetAdjustLookup['reference_id_id'] ?? '');
$assetAdjustMemberSearchUrl = (string) ($assetAdjustLookup['member_search_url'] ?? sr_url('/admin/members/search'));
$assetAdjustReferenceSearchUrl = (string) ($assetAdjustLookup['reference_search_url'] ?? '');
$assetAdjustReferenceOptions = $assetAdjustLookup['reference_options'] ?? [];
if (!is_array($assetAdjustReferenceOptions)) {
    $assetAdjustReferenceOptions = [];
}
$assetAdjustMemberModalId = $assetAdjustPrefix . '_member_lookup_modal';
$assetAdjustMemberResultsId = $assetAdjustPrefix . '_member_lookup_results';
$assetAdjustReferenceModalId = $assetAdjustPrefix . '_reference_lookup_modal';
$assetAdjustReferenceResultsId = $assetAdjustPrefix . '_reference_lookup_results';
?>

<?php if ($assetAdjustMemberInputId !== '') { ?>
    <div id="<?php echo sr_e($assetAdjustMemberModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" data-overlay-stack="true" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($assetAdjustMemberModalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog admin-lookup-dialog">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($assetAdjustMemberModalId); ?>_title" class="modal-title">회원 검색</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($assetAdjustMemberModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="admin-lookup-search-form" data-admin-member-search-form data-endpoint="<?php echo sr_e($assetAdjustMemberSearchUrl); ?>" data-target="#<?php echo sr_e($assetAdjustMemberInputId); ?>" data-results="#<?php echo sr_e($assetAdjustMemberResultsId); ?>">
                        <select name="field" class="form-select" aria-label="회원 검색 조건">
                            <option value="all">전체</option>
                            <option value="hash">해시 아이디</option>
                            <option value="email">이메일</option>
                            <option value="name">이름</option>
                        </select>
                        <input type="text" name="q" maxlength="120" class="form-input" placeholder="해시 아이디, 이메일, 이름" data-overlay-focus>
                        <button type="submit" class="btn btn-solid-primary">검색</button>
                    </form>
                    <div id="<?php echo sr_e($assetAdjustMemberResultsId); ?>" class="admin-lookup-results">
                        <p class="admin-empty-state admin-lookup-empty">검색어 없이 검색하면 최근 회원이 표시됩니다.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($assetAdjustMemberModalId); ?>">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php if ($assetAdjustReferenceSearchUrl !== '' && $assetAdjustReferenceTypeId !== '' && $assetAdjustReferenceIdId !== '') { ?>
    <div id="<?php echo sr_e($assetAdjustReferenceModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" data-overlay-stack="true" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($assetAdjustReferenceModalId); ?>_title" aria-hidden="true" inert>
        <div class="modal-dialog admin-lookup-dialog">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($assetAdjustReferenceModalId); ?>_title" class="modal-title">참조 검색</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($assetAdjustReferenceModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="admin-lookup-search-form" data-admin-reference-search-form data-endpoint="<?php echo sr_e($assetAdjustReferenceSearchUrl); ?>" data-type-target="#<?php echo sr_e($assetAdjustReferenceTypeId); ?>" data-id-target="#<?php echo sr_e($assetAdjustReferenceIdId); ?>" data-results="#<?php echo sr_e($assetAdjustReferenceResultsId); ?>">
                        <select name="reference_type" class="form-select" aria-label="참조 유형">
                            <?php foreach ($assetAdjustReferenceOptions as $referenceTypeValue => $referenceTypeLabel) { ?>
                                <option value="<?php echo sr_e((string) $referenceTypeValue); ?>"><?php echo sr_e((string) $referenceTypeLabel); ?></option>
                            <?php } ?>
                        </select>
                        <input type="text" name="q" maxlength="120" class="form-input" placeholder="참조 ID, 사유, 회원" data-overlay-focus>
                        <button type="submit" class="btn btn-solid-primary">검색</button>
                    </form>
                    <div id="<?php echo sr_e($assetAdjustReferenceResultsId); ?>" class="admin-lookup-results">
                        <p class="admin-empty-state admin-lookup-empty">기존 거래에 연결된 참조를 검색해 적용합니다.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($assetAdjustReferenceModalId); ?>">닫기</button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>
