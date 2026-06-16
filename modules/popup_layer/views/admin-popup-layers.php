<?php

$popupLayerAdminPage = isset($popupLayerAdminPage) ? (string) $popupLayerAdminPage : 'list';
$editing = is_array($editPopup);
$adminPageTitle = $popupLayerAdminPage === 'form' ? ($editing ? sr_t('popup_layer::ui.edit.b0a3dd3e') : sr_t('popup_layer::ui.text.628a32fc')) : sr_t('popup_layer::ui.list.2144397c');
$adminPageSubtitle = $popupLayerAdminPage === 'form' ? sr_t('popup_layer::ui.close.130bd932') : sr_t('popup_layer::ui.status.search.2a2d14e6');
$adminContainerClass = $popupLayerAdminPage === 'form' ? 'admin-page-popup-layer-form admin-ui-scope' : 'admin-page-popup-layer-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'target' => '', 'field' => 'all', 'q' => ''];
$adminPageTitleUrl = sr_admin_page_title_reset_url($popupLayerAdminPage !== 'form', '/admin/popup-layers');
$popupSortOptions = isset($popupSortOptions) && is_array($popupSortOptions) ? $popupSortOptions : [
    'title' => ['columns' => ['p.title', 'p.id']],
    'status' => ['columns' => ['p.status', 'p.id']],
    'skin_key' => ['columns' => ['p.skin_key', 'p.id']],
    'target' => ['columns' => ['t.module_key', 't.point_key', 't.slot_key', 't.match_type', 't.subject_id', 'p.id']],
    'starts_at' => ['columns' => ['p.starts_at', 'p.id']],
    'ends_at' => ['columns' => ['p.ends_at', 'p.id']],
    'dismiss_cookie_days' => ['columns' => ['p.dismiss_cookie_days', 'p.id']],
    'updated_at' => ['columns' => ['p.updated_at', 'p.id']],
];
$popupDefaultSort = isset($popupDefaultSort) && is_array($popupDefaultSort) ? $popupDefaultSort : sr_admin_sort_default('updated_at', 'desc');
$popupSort = isset($popupSort) && is_array($popupSort) ? $popupSort : $popupDefaultSort;
$popupStatusCounts = isset($popupStatusCounts) && is_array($popupStatusCounts) ? $popupStatusCounts : [];
$totalPopups = (int) ($popupStatusCounts['total'] ?? count($popups ?? []));
$targetLabels = [];
foreach ($availableTargets as $target) {
    $targetLabels[sr_popup_layer_target_option_value($target)] = sr_popup_layer_target_option_label($target);
}
$selectedTargetOption = isset($popupLayerDefaultTargetOption) ? (string) $popupLayerDefaultTargetOption : sr_popup_layer_public_target_option_value();
if ($editing && (string) ($editPopup['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editPopup['module_key'] ?? '') . '|' . (string) ($editPopup['point_key'] ?? '') . '|' . (string) ($editPopup['slot_key'] ?? '');
} elseif ($editing) {
    $selectedTargetOption = sr_popup_layer_public_target_option_value();
}
$currentMatchType = $editing ? (string) ($editPopup['match_type'] ?? 'all') : (isset($popupLayerDefaultMatchType) ? (string) $popupLayerDefaultMatchType : 'all');
if (sr_popup_layer_is_public_target_option($selectedTargetOption)) {
    $currentMatchType = 'all';
}
$popupLayerTargetServiceOptions = sr_popup_layer_target_service_options($availableTargets, true);
$selectedTargetServiceKey = sr_popup_layer_selected_target_service_key($selectedTargetOption);
if ($selectedTargetServiceKey === '') {
    $selectedTargetServiceKey = sr_popup_layer_public_target_option_value();
}
$subjectRequired = !sr_popup_layer_is_public_target_option($selectedTargetOption) && $currentMatchType === 'exact';
$popupLayerHelpOpenLabel = sr_t('popup_layer::help.open');
$popupLayerHelpButtonHtml = static function (string $label, string $modalId) use ($popupLayerHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $popupLayerHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$popupLayerHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$popupLayerCopyModals = '';
$popupLayerReferenceModals = '';
$popupLayerCopyModalHtml = static function (array $popup, string $returnTo): string {
    $popupId = (int) ($popup['id'] ?? 0);
    if ($popupId < 1) {
        return '';
    }
    $modalId = 'popup-layer-copy-modal-' . (string) $popupId;
    $title = sr_popup_layer_clean_single_line((string) ($popup['title'] ?? '') . ' 복사본', 120);
    ob_start();
    ?>
    <div id="<?php echo sr_e($modalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($modalId); ?>-label" aria-hidden="true" inert>
        <div class="modal-dialog">
            <div class="modal-content ui-form-theme">
                <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/copy')); ?>">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($modalId); ?>-label" class="modal-title"><?php echo sr_e('팝업레이어 복사'); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="popup_id" value="<?php echo sr_e((string) $popupId); ?>">
                        <input type="hidden" name="return_to" value="<?php echo sr_e($returnTo); ?>">
                        <p class="form-help"><?php echo sr_e((string) ($popup['title'] ?? '')); ?></p>
                        <div class="form-row">
                            <label class="form-label" for="<?php echo sr_e($modalId); ?>-title"><?php echo sr_e('새 제목'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
                            <div class="form-field">
                                <input id="<?php echo sr_e($modalId); ?>-title" type="text" name="title" value="<?php echo sr_e($title); ?>" class="form-input form-control-full" maxlength="120" required data-overlay-focus>
                                <p class="form-help"><?php echo sr_e('복사본은 draft로 저장됩니다.'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($modalId); ?>"><?php echo sr_e('취소'); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e('복사본 만들기'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php
    return (string) ob_get_clean();
};
$popupLayerHelp = [
    'status' => [
        'id' => 'popup_layer_admin_help_status',
        'title' => sr_t('popup_layer::help.status.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.status.body.1', 'popup_layer::help.status.body.2', 'popup_layer::help.status.body.3']),
    ],
    'skin_key' => [
        'id' => 'popup_layer_admin_help_skin_key',
        'title' => sr_t('popup_layer::help.skin_key.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.skin_key.body.1', 'popup_layer::help.skin_key.body.2']),
    ],
    'target_option' => [
        'id' => 'popup_layer_admin_help_target_option',
        'title' => sr_t('popup_layer::help.target_option.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.target_option.body.1', 'popup_layer::help.target_option.body.2']),
    ],
    'match_type' => [
        'id' => 'popup_layer_admin_help_match_type',
        'title' => sr_t('popup_layer::help.match_type.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.match_type.body.1', 'popup_layer::help.match_type.body.2']),
    ],
    'subject_id' => [
        'id' => 'popup_layer_admin_help_subject_id',
        'title' => sr_t('popup_layer::help.subject_id.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.subject_id.body.1', 'popup_layer::help.subject_id.body.2']),
    ],
    'starts_at' => [
        'id' => 'popup_layer_admin_help_starts_at',
        'title' => sr_t('popup_layer::help.starts_at.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.starts_at.body.1', 'popup_layer::help.starts_at.body.2']),
    ],
    'ends_at' => [
        'id' => 'popup_layer_admin_help_ends_at',
        'title' => sr_t('popup_layer::help.ends_at.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.ends_at.body.1', 'popup_layer::help.ends_at.body.2']),
    ],
    'dismiss_cookie_days' => [
        'id' => 'popup_layer_admin_help_dismiss_cookie_days',
        'title' => sr_t('popup_layer::help.dismiss_cookie_days.title'),
        'body' => $popupLayerHelpBodyHtml(['popup_layer::help.dismiss_cookie_days.body.1', 'popup_layer::help.dismiss_cookie_days.body.2']),
    ],
];

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($popupLayerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/save')); ?>" class="admin-form ui-form-theme" data-admin-subject-form data-public-target-value="<?php echo sr_e(sr_popup_layer_public_target_option_value()); ?>">
        <section class="card">
            <h2><?php echo sr_e($editing ? sr_t('popup_layer::ui.edit.b0a3dd3e') : sr_t('popup_layer::ui.text.628a32fc')); ?></h2>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="popup_id" value="<?php echo $editing ? sr_e((string) $editPopup['id']) : '0'; ?>">

                <div class="form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_title"><?php echo sr_e(sr_t('popup_layer::ui.text.08b17e43')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <input id="popup_layer_admin_popup_layers_title" type="text" name="title" value="<?php echo $editing ? sr_e((string) $editPopup['title']) : ''; ?>" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_body_text"><?php echo sr_e(sr_t('popup_layer::ui.text.cb0f2404')); ?></label>
                    <div class="form-field">
                        <textarea id="popup_layer_admin_popup_layers_body_text" name="body_text" maxlength="5000" class="form-textarea"<?php echo $popupLayerEditorAttributes; ?>><?php echo $editing ? sr_e((string) $editPopup['body_text']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layers_status', sr_t('popup_layer::ui.status.e10195a1'), $popupLayerHelp['status']['id'], $popupLayerHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="popup_layer_admin_popup_layers_status" name="status" class="form-select" required>
                                                    <?php foreach ($allowedStatuses as $status) { ?>
                                                        <?php $currentStatus = $editing ? (string) $editPopup['status'] : (isset($popupLayerDefaultStatus) ? (string) $popupLayerDefaultStatus : 'draft'); ?>
                                                        <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layers_skin_key', sr_t('popup_layer::ui.text.9c7f107d'), $popupLayerHelp['skin_key']['id'], $popupLayerHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="popup_layer_admin_popup_layers_skin_key" name="skin_key" class="form-select" required>
                                                    <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                                                        <?php $currentSkinKey = $editing ? (string) ($editPopup['skin_key'] ?? $popupLayerSkinKey) : $popupLayerSkinKey; ?>
                                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <input type="hidden" name="target_option" value="<?php echo sr_e($selectedTargetOption); ?>" data-admin-target-option>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layers_target_service_key', '서비스', $popupLayerHelp['target_option']['id'], $popupLayerHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="popup_layer_admin_popup_layers_target_service_key" name="target_service_key" class="form-select" required data-admin-target-service>
                            <?php foreach ($popupLayerTargetServiceOptions as $serviceKey => $serviceLabel) { ?>
                                <option value="<?php echo sr_e((string) $serviceKey); ?>"<?php echo $selectedTargetServiceKey === (string) $serviceKey ? ' selected' : ''; ?>><?php echo sr_e((string) $serviceLabel); ?></option>
                            <?php } ?>
                        </select>
                        <p class="form-help"><?php echo sr_e('공용은 다른 화면에서 직접 선택하는 팝업레이어이고, 서비스 선택 시 상세 노출 위치를 고릅니다.'); ?></p>
                    </div>
                </div>
                <div class="form-row" data-admin-target-detail-row<?php echo sr_popup_layer_is_public_target_option($selectedTargetOption) ? ' hidden' : ''; ?>>
                    <label class="form-label" for="popup_layer_admin_popup_layers_target_detail_option"><?php echo sr_e('상세'); ?> <span class="sr-required-label" data-admin-target-detail-required<?php echo sr_popup_layer_is_public_target_option($selectedTargetOption) ? ' hidden' : ''; ?>><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="form-field">
                        <select id="popup_layer_admin_popup_layers_target_detail_option" name="target_detail_option" class="form-select" data-admin-target-detail<?php echo sr_popup_layer_is_public_target_option($selectedTargetOption) ? ' disabled' : ' required'; ?>>
                            <?php foreach ($availableTargets as $target) { ?>
                                <?php $optionValue = sr_popup_layer_target_option_value($target); ?>
                                <option value="<?php echo sr_e($optionValue); ?>" data-service="<?php echo sr_e(sr_popup_layer_target_service_key($target)); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_popup_layer_target_detail_label($target)); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <p class="form-help"><?php echo sr_e(sr_t('popup_layer::ui.settings.select.active.a35cb577')); ?></p>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layers_match_type', sr_t('popup_layer::ui.text.175f56ba'), $popupLayerHelp['match_type']['id'], $popupLayerHelpOpenLabel, true); ?>
                    <div class="form-field">
                        <select id="popup_layer_admin_popup_layers_match_type" name="match_type" class="form-select" required>
                                                    <?php foreach ($allowedMatchTypes as $matchType) { ?>
                                                        <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-label form-label-help"><?php echo $popupLayerHelpButtonHtml(sr_t('popup_layer::ui.subject.id.14852174'), $popupLayerHelp['subject_id']['id']); ?><label for="popup_layer_admin_popup_layers_subject_id"><?php echo sr_e(sr_t('popup_layer::ui.subject.id.14852174')); ?> <span class="sr-required-label" data-admin-subject-required<?php echo $subjectRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label></div>
                    <div class="form-field">
                        <input id="popup_layer_admin_popup_layers_subject_id" type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editPopup['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80" data-admin-subject-id<?php echo $subjectRequired ? ' required' : ''; ?>>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('popup_starts_at', sr_t('popup_layer::ui.text.65bdaefd'), $popupLayerHelp['starts_at']['id'], $popupLayerHelpOpenLabel); ?>
                    <div class="form-field">
                        <input type="datetime-local" name="starts_at" id="popup_starts_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['starts_at'] ?? null)) : ''; ?>" class="form-input">
                        <div class="admin-date-quick-actions">
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick="now" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.df159f47')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="1" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.4ec242e3')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="3" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.3f9e052e')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="7" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.b330c2cb')); ?></button>
                                                </div>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('popup_ends_at', sr_t('popup_layer::ui.text.26c25fca'), $popupLayerHelp['ends_at']['id'], $popupLayerHelpOpenLabel); ?>
                    <div class="form-field">
                        <input type="datetime-local" name="ends_at" id="popup_ends_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['ends_at'] ?? null)) : ''; ?>" class="form-input">
                        <div class="admin-date-quick-actions">
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="1" data-datetime-target="popup_ends_at"><?php echo sr_e(sr_t('popup_layer::ui.text.4ec242e3')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="3" data-datetime-target="popup_ends_at"><?php echo sr_e(sr_t('popup_layer::ui.text.3f9e052e')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="7" data-datetime-target="popup_ends_at"><?php echo sr_e(sr_t('popup_layer::ui.text.b330c2cb')); ?></button>
                                                </div>
                    </div>
                </div>
                <div class="form-row">
                    <?php echo sr_admin_form_label_help_html('popup_layer_admin_popup_layers_dismiss_cookie_days', sr_t('popup_layer::ui.close.06cddc6e'), $popupLayerHelp['dismiss_cookie_days']['id'], $popupLayerHelpOpenLabel); ?>
                    <div class="form-field">
                        <input id="popup_layer_admin_popup_layers_dismiss_cookie_days" type="number" name="dismiss_cookie_days" value="<?php echo $editing ? sr_e((string) $editPopup['dismiss_cookie_days']) : sr_e((string) ($popupLayerDefaultDismissCookieDays ?? 1)); ?>" class="form-input" min="0" max="365">
                    </div>
                </div>
        </section>
        <div class="form-sticky-actions form-actions form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('popup_layer::ui.list.f07b3200')); ?></a>
            <?php if ($editing) { ?>
                <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="popup-layer-copy-modal-<?php echo sr_e((string) (int) $editPopup['id']); ?>" data-overlay="#popup-layer-copy-modal-<?php echo sr_e((string) (int) $editPopup['id']); ?>"><?php echo sr_e('복사'); ?></button>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('popup_layer::ui.save.5fb92622')); ?></button>
        </div>
    </form>
    <?php echo $editing ? $popupLayerCopyModalHtml($editPopup, '/admin/popup-layers/edit?id=' . rawurlencode((string) $editPopup['id'])) : ''; ?>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.text.afecea43')); ?> <strong><?php echo sr_e((string) $totalPopups); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($popupStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=draft')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.text.145b2413')); ?> <?php echo sr_e((string) ($popupStatusCounts['draft'] ?? 0)); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.text.92cdef3c')); ?> <?php echo sr_e((string) ($popupStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <?php
    $selectedPopupStatuses = is_array($filters['status'] ?? null) ? $filters['status'] : [];
    $selectedPopupTargets = is_array($filters['target'] ?? null) ? $filters['target'] : [];
    $selectedPopupTarget = (string) ($selectedPopupTargets[0] ?? '');
    $selectedPopupTargetService = (string) ($filters['target_service'] ?? '');
    if ($selectedPopupTargetService === '' && $selectedPopupTarget !== '') {
        $selectedPopupTargetService = sr_popup_layer_selected_target_service_key($selectedPopupTarget);
    }
    $popupDetailFilterOpen = $selectedPopupStatuses !== [] || $selectedPopupTargets !== [] || $selectedPopupTargetService !== '';
    $popupTargetServiceOptions = sr_popup_layer_target_service_options($availableTargets, true);
    ?>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="filtering-form admin-popup-layer-filter ui-form-theme">
        <div class="filtering-fields admin-popup-layer-search-grid">
            <div class="filtering filtering-card<?php echo $popupDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
                <div class="filtering-fields">
                    <div class="filtering-field admin-popup-layer-filter-field">
                        <label for="modules_popup_layer_admin_popup_layers_field" class="filtering-label">검색조건</label>
                        <select id="modules_popup_layer_admin_popup_layers_field" name="field" class="form-select filtering-input">
                            <?php foreach (['all' => sr_t('popup_layer::ui.all.a4b69faf'), 'title' => sr_t('popup_layer::ui.text.08b17e43'), 'subject' => sr_t('popup_layer::ui.subject.id.14852174')] as $fieldValue => $fieldLabel) { ?>
                                <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                                    <?php echo sr_e($fieldLabel); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field-fill filtering-field admin-popup-layer-filter-keyword">
                        <label for="modules_popup_layer_admin_popup_layers_q" class="filtering-label"><?php echo sr_e(sr_t('popup_layer::ui.search.bda397fc')); ?></label>
                        <input id="modules_popup_layer_admin_popup_layers_q" type="text" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('popup_layer::ui.subject.id.b70d79cb')); ?>">
                    </div>
                </div>
                <div id="modules_popup_layer_admin_popup_layers_detail_filters" class="filtering-body" data-filtering-body<?php echo $popupDetailFilterOpen ? '' : ' hidden'; ?>>
                    <div class="filtering-field admin-popup-layer-filter-status">
                        <span class="filtering-label"><?php echo sr_e(sr_t('popup_layer::ui.status.e10195a1')); ?></span>
                        <?php echo sr_admin_filter_toggle_group_html('modules_popup_layer_admin_popup_layers_status_filter', 'status', sr_admin_code_label_options($allowedStatuses, 'content_status'), $selectedPopupStatuses, sr_t('popup_layer::ui.all.a4b69faf')); ?>
                    </div>
                    <div class="filtering-field admin-popup-layer-filter-target">
                        <label for="modules_popup_layer_admin_popup_layers_target_service_filter" class="filtering-label"><?php echo sr_e('서비스'); ?></label>
                        <select id="modules_popup_layer_admin_popup_layers_target_service_filter" name="target_service" class="form-select filtering-input" data-admin-target-service>
                            <option value=""><?php echo sr_e(sr_t('popup_layer::ui.all.a4b69faf')); ?></option>
                            <?php foreach ($popupTargetServiceOptions as $serviceKey => $serviceLabel) { ?>
                                <option value="<?php echo sr_e((string) $serviceKey); ?>"<?php echo $selectedPopupTargetService === (string) $serviceKey ? ' selected' : ''; ?>><?php echo sr_e((string) $serviceLabel); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="filtering-field admin-popup-layer-filter-target">
                        <label for="modules_popup_layer_admin_popup_layers_target_filter" class="filtering-label"><?php echo sr_e('상세'); ?></label>
                        <select id="modules_popup_layer_admin_popup_layers_target_filter" name="target" class="form-select filtering-input" data-admin-target-detail>
                            <option value=""><?php echo sr_e(sr_t('popup_layer::ui.all.a4b69faf')); ?></option>
                            <?php foreach ($availableTargets as $target) { ?>
                                <?php $targetValue = sr_popup_layer_target_option_value($target); ?>
                                <option value="<?php echo sr_e($targetValue); ?>" data-service="<?php echo sr_e(sr_popup_layer_target_service_key($target)); ?>"<?php echo $selectedPopupTarget === $targetValue ? ' selected' : ''; ?>><?php echo sr_e(sr_popup_layer_target_detail_label($target)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="filtering-actions">
                    <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $popupDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="modules_popup_layer_admin_popup_layers_detail_filters">상세검색</button>
                    <button type="button" class="btn btn-outline-light" data-filtering-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><?php echo sr_e(sr_t('ui.text.893f3d94')); ?></button>
                    <button type="submit" class="btn btn-solid-primary filtering-submit"><?php echo sr_e(sr_t('popup_layer::ui.search.4b8d541e')); ?></button>
                </div>
            </div>
        </div>
    </form>

    <section class="card admin-list-card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('popup_layer::ui.list.f0aa41f6')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers/new')); ?>" class="btn btn-sm btn-outline-secondary"><?php echo sr_e(sr_t('popup_layer::ui.text.bbd10514')); ?></a>
        </div>
        <div class="admin-list-summary-row">
            <?php if (empty($popupSort['is_default'])) { ?>
                <a href="<?php echo sr_e(sr_admin_sort_url($popupSortOptions, $popupDefaultSort)); ?>" class="btn btn-sm btn-icon btn-outline-danger admin-sort-reset" aria-label="팝업레이어 목록 기본 정렬로 초기화" title="기본 정렬로 초기화"><?php echo sr_material_icon_html('restart_alt'); ?></a>
            <?php } ?>
            <form id="popup-layer-bulk-status-form" method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="admin-popup-layer-bulk-form" data-popup-layer-bulk-form>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="batch_status">
                <input type="hidden" name="operation_key" value="popup_layer.set_status">
                <input type="hidden" name="return_to" value="<?php echo sr_e((string) ($_SERVER['REQUEST_URI'] ?? '/admin/popup-layers')); ?>">
                <div class="admin-popup-layer-bulk-actions admin-row-actions" data-popup-layer-bulk-bar>
                    <div class="admin-popup-layer-bulk-controls admin-row-actions">
                        <button type="submit" name="target_status" value="enabled" class="btn btn-sm btn-outline-warning" data-popup-layer-bulk-submit data-status-label="사용" disabled>사용</button>
                        <button type="submit" name="target_status" value="disabled" class="btn btn-sm btn-outline-warning" data-popup-layer-bulk-submit data-status-label="사용 안 함" disabled>사용 안 함</button>
                        <button type="button" class="btn btn-sm btn-outline-light" data-popup-layer-bulk-clear aria-label="선택 해제" title="선택 해제" hidden><?php echo sr_material_icon_html('close'); ?><span data-popup-layer-selected-count>0</span></button>
                    </div>
                </div>
            </form>
            <?php echo sr_admin_pagination_summary_html($popupPagination); ?>
        </div>
        <div class="table-wrapper">
        <table class="table table-list admin-popup-layer-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('popup_layer::ui.list.f0aa41f6')); ?></caption>
            <thead>
                <tr>
                    <th class="admin-table-checkbox-cell admin-popup-layer-select-cell">
                        <label class="sr-only" for="popup_layer_bulk_select_all">현재 페이지 팝업레이어 전체 선택</label>
                        <input id="popup_layer_bulk_select_all" type="checkbox" class="form-checkbox" data-popup-layer-select-all<?php echo $popups === [] ? ' disabled' : ''; ?>>
                    </th>
                    <th<?php echo sr_admin_sort_aria('title', $popupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('popup_layer::ui.text.08b17e43'), 'title', $popupSort, $popupSortOptions, $popupDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('status', $popupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('popup_layer::ui.status.e10195a1'), 'status', $popupSort, $popupSortOptions, $popupDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('target', $popupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('popup_layer::ui.text.8c609deb'), 'target', $popupSort, $popupSortOptions, $popupDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('starts_at', $popupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('popup_layer::ui.text.65bdaefd'), 'starts_at', $popupSort, $popupSortOptions, $popupDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('ends_at', $popupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('popup_layer::ui.text.26c25fca'), 'ends_at', $popupSort, $popupSortOptions, $popupDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('dismiss_cookie_days', $popupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('popup_layer::ui.close.06cddc6e'), 'dismiss_cookie_days', $popupSort, $popupSortOptions, $popupDefaultSort); ?></th>
                    <th<?php echo sr_admin_sort_aria('updated_at', $popupSort); ?>><?php echo sr_admin_sort_header_html(sr_t('popup_layer::ui.edit.d3a98476'), 'updated_at', $popupSort, $popupSortOptions, $popupDefaultSort); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('popup_layer::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($popups === []) { ?>
                    <tr>
                        <td colspan="9" class="admin-empty-state"><?php echo sr_e(sr_t('popup_layer::ui.create.88d48f71')); ?></td>
                    </tr>
                <?php } else { ?>
                    <?php foreach ($popups as $popup) { ?>
                        <?php
                        if ((string) ($popup['module_key'] ?? '') === '') {
                            $popupTargetLabel = sr_t('popup_layer::ui.text.11677edb');
                        } else {
                            $popupTargetOption = (string) $popup['module_key'] . '|' . (string) $popup['point_key'] . '|' . (string) $popup['slot_key'];
                            $popupTargetLabel = (string) ($targetLabels[$popupTargetOption] ?? ((string) $popup['module_key'] . ' / ' . (string) $popup['point_key'] . ' / ' . (string) $popup['slot_key']));
                        }
                        $popupStatus = (string) $popup['status'];
                        $statusClass = match ($popupStatus) {
                            'enabled' => 'is-normal',
                            'draft' => 'is-blocked',
                            default => 'is-left',
                        };
                        ?>
                        <tr>
                            <td class="admin-table-checkbox-cell admin-popup-layer-select-cell">
                                <label class="sr-only" for="popup_layer_bulk_select_<?php echo sr_e((string) (int) $popup['id']); ?>"><?php echo sr_e((string) $popup['title']); ?> 선택</label>
                                <input id="popup_layer_bulk_select_<?php echo sr_e((string) (int) $popup['id']); ?>" type="checkbox" name="selected_popup_ids[]" value="<?php echo sr_e((string) (int) $popup['id']); ?>" class="form-checkbox" form="popup-layer-bulk-status-form" data-popup-layer-row-select>
                            </td>
                            <td class="admin-table-break admin-popup-layer-title-cell"><?php echo sr_e((string) $popup['title']); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($popupStatus, 'content_status')); ?></span></td>
                            <td class="admin-table-break admin-popup-layer-target-cell">
                                <?php echo sr_e($popupTargetLabel); ?><br>
                                <?php echo sr_e((string) $popup['match_type'] . ((string) ($popup['subject_id'] ?? '') !== '' ? ': ' . (string) $popup['subject_id'] : '')); ?>
                            </td>
                            <td class="admin-table-nowrap admin-popup-layer-date-cell"><?php echo sr_e((string) ($popup['starts_at'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap admin-popup-layer-date-cell"><?php echo sr_e((string) ($popup['ends_at'] ?? '-')); ?></td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $popup['dismiss_cookie_days']); ?></td>
                            <td class="admin-table-nowrap admin-popup-layer-date-cell"><?php echo sr_popup_layer_time_html((string) $popup['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <?php
                                    $popupLayerCopyModalId = 'popup-layer-copy-modal-' . (string) (int) $popup['id'];
                                    $popupLayerCopyModals .= $popupLayerCopyModalHtml($popup, (string) ($_SERVER['REQUEST_URI'] ?? '/admin/popup-layers'));
                                    $popupLayerReferenceModalId = 'popup-layer-reference-modal-' . (string) (int) $popup['id'];
                                    $popupLayerReferenceResult = $popupLayerReadReferencesById[(int) $popup['id']] ?? ['rows' => [], 'errors' => []];
                                    $popupLayerReferenceModals .= sr_admin_read_reference_modal_html($popupLayerReferenceModalId, '팝업레이어 참조 현황', $popupLayerReferenceResult);
                                    ?>
                                    <?php echo sr_admin_read_reference_button_html($popupLayerReferenceModalId, $popupLayerReferenceResult); ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/popup-layers/edit?id=' . rawurlencode((string) $popup['id']))); ?>" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="<?php echo sr_e(sr_t('popup_layer::ui.edit.3537f0cc')); ?>" title="<?php echo sr_e(sr_t('popup_layer::ui.edit.3537f0cc')); ?>"><?php echo sr_material_icon_html('edit'); ?></a>
                                    <button type="button" class="btn btn-sm btn-icon btn-solid-light" aria-label="<?php echo sr_e('복사'); ?>" title="<?php echo sr_e('복사'); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($popupLayerCopyModalId); ?>" data-overlay="#<?php echo sr_e($popupLayerCopyModalId); ?>"><?php echo sr_material_icon_html('content_copy'); ?></button>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="popup_id" value="<?php echo sr_e((string) $popup['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-outline-danger" aria-label="<?php echo sr_e(sr_t('popup_layer::ui.delete.6139b6c3')); ?>" title="<?php echo sr_e(sr_t('popup_layer::ui.delete.6139b6c3')); ?>"><?php echo sr_material_icon_html('delete'); ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
	        </table>
	        </div>
        <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('travel_explore'); ?> 참조 현황</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> <?php echo sr_e(sr_t('popup_layer::ui.edit.3537f0cc')); ?></span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('content_copy'); ?> 복사</span>
            <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('delete'); ?> <?php echo sr_e(sr_t('popup_layer::ui.delete.6139b6c3')); ?></span>
        </div>
        <?php echo sr_admin_status_description_list_html('content_status', sr_admin_code_label_options(['enabled', 'disabled'], 'content_status')); ?>
	    </section>
    <?php echo $popupLayerCopyModals; ?>
    <?php echo $popupLayerReferenceModals; ?>
    <?php echo sr_admin_pagination_html($popupPagination, '팝업레이어 목록 페이지'); ?>
    <script>
    (function () {
        var form = document.querySelector('[data-popup-layer-bulk-form]');
        if (!form) {
            return;
        }
        var countNode = document.querySelector('[data-popup-layer-selected-count]');
        var submitButtons = Array.prototype.slice.call(document.querySelectorAll('[data-popup-layer-bulk-submit]'));
        var clear = document.querySelector('[data-popup-layer-bulk-clear]');
        var selectAll = document.querySelector('[data-popup-layer-select-all]');
        var rowChecks = Array.prototype.slice.call(document.querySelectorAll('[data-popup-layer-row-select]'));

        function checkedRows() {
            return rowChecks.filter(function (input) {
                return input.checked && !input.disabled;
            });
        }

        function syncBulkState() {
            var selectedCount = checkedRows().length;
            if (countNode) {
                countNode.textContent = String(selectedCount);
            }
            submitButtons.forEach(function (button) {
                button.disabled = selectedCount < 1;
            });
            if (clear) {
                clear.hidden = selectedCount < 1;
            }
            if (selectAll) {
                selectAll.checked = selectedCount > 0 && selectedCount === rowChecks.length;
                selectAll.indeterminate = selectedCount > 0 && selectedCount < rowChecks.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                rowChecks.forEach(function (input) {
                    if (!input.disabled) {
                        input.checked = selectAll.checked;
                    }
                });
                syncBulkState();
            });
        }
        rowChecks.forEach(function (input) {
            input.addEventListener('change', syncBulkState);
        });
        if (clear) {
            clear.addEventListener('click', function () {
                rowChecks.forEach(function (input) {
                    input.checked = false;
                });
                syncBulkState();
            });
        }
        form.addEventListener('submit', function (event) {
            var selectedCount = checkedRows().length;
            if (selectedCount < 1) {
                event.preventDefault();
                syncBulkState();
                return;
            }
            var submitter = event.submitter || document.activeElement;
            var statusLabel = submitter && submitter.getAttribute ? submitter.getAttribute('data-status-label') : '';
            if (!statusLabel) {
                statusLabel = submitter && submitter.textContent ? submitter.textContent.replace(/\s+/g, ' ').trim() : '선택한 상태';
            }
            if (!window.confirm('선택한 팝업레이어 ' + selectedCount + '건의 상태를 "' + statusLabel + '"(으)로 변경합니다.')) {
                event.preventDefault();
            }
        });
        syncBulkState();
    }());
    </script>
<?php } ?>

<?php if ($popupLayerAdminPage === 'form') { ?>
    <?php foreach ($popupLayerHelp as $popupLayerHelpModal) { ?>
        <?php echo sr_admin_help_modal_html((string) $popupLayerHelpModal['id'], (string) $popupLayerHelpModal['title'], (string) $popupLayerHelpModal['body']); ?>
    <?php } ?>
<?php } ?>

<?php if ($popupLayerAdminPage === 'form') { ?>
    <?php echo sr_editor_assets_html($pdo, $popupLayerEditorKey, 'admin_basic'); ?>
    <script>
    (function () {
        var form = document.querySelector('[data-admin-subject-form]');
        if (!form) {
            return;
        }

        var targetService = form.querySelector('[data-admin-target-service]');
        var targetDetail = form.querySelector('[data-admin-target-detail]');
        var targetOption = form.querySelector('[data-admin-target-option]');
        var targetDetailRow = form.querySelector('[data-admin-target-detail-row]');
        var targetDetailRequired = form.querySelector('[data-admin-target-detail-required]');
        var match = form.querySelector('select[name="match_type"]');
        var subject = form.querySelector('[data-admin-subject-id]');
        var label = form.querySelector('[data-admin-subject-required]');
        var publicTarget = form.getAttribute('data-public-target-value') || '';

        function syncTargetDetail() {
            var service = targetService ? targetService.value : publicTarget;
            var isPublic = service === publicTarget;
            if (targetDetailRow) {
                targetDetailRow.hidden = isPublic;
            }
            if (targetDetailRequired) {
                targetDetailRequired.hidden = isPublic;
            }
            if (targetDetail) {
                var visibleOptions = [];
                Array.prototype.forEach.call(targetDetail.options, function (option) {
                    var visible = !isPublic && option.getAttribute('data-service') === service;
                    option.hidden = !visible;
                    option.disabled = !visible;
                    if (visible) {
                        visibleOptions.push(option);
                    }
                });
                targetDetail.disabled = isPublic;
                targetDetail.required = !isPublic;
                if (!isPublic && (!targetDetail.value || targetDetail.selectedIndex < 0 || targetDetail.options[targetDetail.selectedIndex].disabled) && visibleOptions.length > 0) {
                    targetDetail.value = visibleOptions[0].value;
                }
            }
            if (targetOption) {
                targetOption.value = isPublic ? publicTarget : (targetDetail ? targetDetail.value : '');
            }
            if (isPublic && match) {
                match.value = 'all';
            }
        }

        function syncSubjectRequired() {
            syncTargetDetail();
            var needed = !!(targetOption && match && targetOption.value !== publicTarget && match.value === 'exact');
            if (label) {
                label.hidden = !needed;
            }
            if (subject) {
                subject.required = needed;
                subject.disabled = !needed;
            }
        }

        form.addEventListener('change', function (event) {
            if (event.target === targetService || event.target === targetDetail || event.target === match) {
                syncSubjectRequired();
            }
        });
        syncSubjectRequired();
    })();
    </script>
<?php } ?>

<script>
(function () {
    document.querySelectorAll('form').forEach(function (form) {
        var service = form.querySelector('[data-admin-target-service]');
        var detail = form.querySelector('[data-admin-target-detail]');
        if (!service || !detail || form.hasAttribute('data-admin-subject-form')) {
            return;
        }

        function syncDetail() {
            var serviceValue = service.value || '';
            Array.prototype.forEach.call(detail.options, function (option) {
                if (option.value === '') {
                    option.hidden = false;
                    option.disabled = false;
                    return;
                }
                var visible = serviceValue === '' || option.getAttribute('data-service') === serviceValue;
                option.hidden = !visible;
                option.disabled = !visible;
            });
            if (detail.value && detail.options[detail.selectedIndex] && detail.options[detail.selectedIndex].disabled) {
                detail.value = '';
            }
        }

        service.addEventListener('change', syncDetail);
        form.addEventListener('reset', function () {
            window.setTimeout(syncDetail, 0);
        });
        syncDetail();
    });
}());
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
