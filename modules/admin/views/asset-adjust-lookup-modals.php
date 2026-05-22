<?php

$assetAdjustLookup = isset($assetAdjustLookup) && is_array($assetAdjustLookup) ? $assetAdjustLookup : [];
$assetAdjustPrefix = (string) ($assetAdjustLookup['field_prefix'] ?? 'asset_adjust');
$assetAdjustMemberInputId = (string) ($assetAdjustLookup['member_input_id'] ?? '');
$assetAdjustReferenceTypeId = (string) ($assetAdjustLookup['reference_type_id'] ?? '');
$assetAdjustReferenceIdId = (string) ($assetAdjustLookup['reference_id_id'] ?? '');
$assetAdjustReturnOverlayId = (string) ($assetAdjustLookup['return_overlay_id'] ?? '');
$assetAdjustReturnLabel = trim((string) ($assetAdjustLookup['return_label'] ?? ''));
if ($assetAdjustReturnLabel === '') {
    $assetAdjustReturnLabel = sr_t('admin::ui.text.141cac84');
}
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
    <div id="<?php echo sr_e($assetAdjustMemberModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($assetAdjustMemberModalId); ?>_title" aria-hidden="true" inert data-admin-return-overlay="#<?php echo sr_e($assetAdjustReturnOverlayId); ?>">
        <div class="modal-dialog admin-lookup-dialog">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($assetAdjustMemberModalId); ?>_title" class="modal-title"><?php echo sr_e(sr_t('admin::ui.member.search.f7a330b0')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($assetAdjustMemberModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="admin-lookup-search-form" data-admin-member-search-form data-endpoint="<?php echo sr_e($assetAdjustMemberSearchUrl); ?>" data-target="#<?php echo sr_e($assetAdjustMemberInputId); ?>" data-results="#<?php echo sr_e($assetAdjustMemberResultsId); ?>" data-return-overlay="#<?php echo sr_e($assetAdjustReturnOverlayId); ?>">
                        <select name="field" class="form-select" aria-label="<?php echo sr_e(sr_t('admin::ui.member.search.cb8a60d7')); ?>">
                            <option value="all"><?php echo sr_e(sr_t('admin::ui.all.a4b69faf')); ?></option>
                            <option value="hash"><?php echo sr_e(sr_t('admin::ui.text.93971787')); ?></option>
                            <option value="email"><?php echo sr_e(sr_t('admin::ui.email.3b7dbc4c')); ?></option>
                            <option value="login_id"><?php echo sr_e(sr_t('admin::ui.login.0cdb28b5')); ?></option>
                            <option value="name"><?php echo sr_e(sr_t('admin::ui.name.253d1510')); ?></option>
                        </select>
                        <input type="text" name="q" maxlength="120" class="form-input" placeholder="<?php echo sr_e(sr_t('admin::ui.email.login.name.c26ba637')); ?>" data-overlay-focus>
                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.search.4b8d541e')); ?></button>
                    </form>
                    <div id="<?php echo sr_e($assetAdjustMemberResultsId); ?>" class="admin-lookup-results">
                        <p class="admin-empty-state admin-lookup-empty"><?php echo sr_e(sr_t('admin::ui.search.search.member.3f9d9039')); ?></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if ($assetAdjustReturnOverlayId !== '') { ?>
                        <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#<?php echo sr_e($assetAdjustReturnOverlayId); ?>"><?php echo sr_e($assetAdjustReturnLabel); ?></button>
                    <?php } ?>
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($assetAdjustMemberModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>

<?php if ($assetAdjustReferenceSearchUrl !== '' && $assetAdjustReferenceTypeId !== '' && $assetAdjustReferenceIdId !== '') { ?>
    <div id="<?php echo sr_e($assetAdjustReferenceModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($assetAdjustReferenceModalId); ?>_title" aria-hidden="true" inert data-admin-return-overlay="#<?php echo sr_e($assetAdjustReturnOverlayId); ?>">
        <div class="modal-dialog admin-lookup-dialog">
            <div class="modal-content ui-form-theme">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($assetAdjustReferenceModalId); ?>_title" class="modal-title"><?php echo sr_e(sr_t('admin::ui.search.3acacadd')); ?></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($assetAdjustReferenceModalId); ?>">
                        <?php echo sr_material_icon_html('close'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <form class="admin-lookup-search-form" data-admin-reference-search-form data-endpoint="<?php echo sr_e($assetAdjustReferenceSearchUrl); ?>" data-type-target="#<?php echo sr_e($assetAdjustReferenceTypeId); ?>" data-id-target="#<?php echo sr_e($assetAdjustReferenceIdId); ?>" data-results="#<?php echo sr_e($assetAdjustReferenceResultsId); ?>" data-return-overlay="#<?php echo sr_e($assetAdjustReturnOverlayId); ?>">
                        <select name="reference_type" class="form-select" aria-label="<?php echo sr_e(sr_t('admin::ui.text.200e7df1')); ?>">
                            <?php foreach ($assetAdjustReferenceOptions as $referenceTypeValue => $referenceTypeLabel) { ?>
                                <option value="<?php echo sr_e((string) $referenceTypeValue); ?>"><?php echo sr_e((string) $referenceTypeLabel); ?></option>
                            <?php } ?>
                        </select>
                        <input type="text" name="q" maxlength="120" class="form-input" placeholder="<?php echo sr_e(sr_t('admin::ui.id.member.8b1d51c8')); ?>" data-overlay-focus>
                        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('admin::ui.search.4b8d541e')); ?></button>
                    </form>
                    <div id="<?php echo sr_e($assetAdjustReferenceResultsId); ?>" class="admin-lookup-results">
                        <p class="admin-empty-state admin-lookup-empty"><?php echo sr_e(sr_t('admin::ui.search.095de9a5')); ?></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if ($assetAdjustReturnOverlayId !== '') { ?>
                        <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#<?php echo sr_e($assetAdjustReturnOverlayId); ?>"><?php echo sr_e($assetAdjustReturnLabel); ?></button>
                    <?php } ?>
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($assetAdjustReferenceModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                </div>
            </div>
        </div>
    </div>
<?php } ?>
