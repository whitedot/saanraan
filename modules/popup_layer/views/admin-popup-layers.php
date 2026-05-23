<?php

$popupLayerAdminPage = isset($popupLayerAdminPage) ? (string) $popupLayerAdminPage : 'list';
$editing = is_array($editPopup);
$adminPageTitle = $popupLayerAdminPage === 'form' ? ($editing ? sr_t('popup_layer::ui.edit.b0a3dd3e') : sr_t('popup_layer::ui.text.628a32fc')) : sr_t('popup_layer::ui.text.1063d585');
$adminPageSubtitle = $popupLayerAdminPage === 'form' ? sr_t('popup_layer::ui.close.130bd932') : sr_t('popup_layer::ui.status.search.2a2d14e6');
$adminContainerClass = $popupLayerAdminPage === 'form' ? 'admin-page-popup-layer-form admin-ui-scope' : 'admin-page-popup-layer-list admin-ui-scope';
$filters = isset($filters) && is_array($filters) ? $filters : ['status' => '', 'target' => '', 'field' => 'all', 'q' => ''];
$popupStatusCounts = isset($popupStatusCounts) && is_array($popupStatusCounts) ? $popupStatusCounts : [];
$totalPopups = (int) ($popupStatusCounts['total'] ?? count($popups ?? []));
$targetLabels = [];
foreach ($availableTargets as $target) {
    $targetLabels[sr_popup_layer_target_option_value($target)] = sr_popup_layer_target_option_label($target);
}
$selectedTargetOption = sr_popup_layer_public_target_option_value();
if ($editing && (string) ($editPopup['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editPopup['module_key'] ?? '') . '|' . (string) ($editPopup['point_key'] ?? '') . '|' . (string) ($editPopup['slot_key'] ?? '');
}
$currentMatchType = $editing ? (string) ($editPopup['match_type'] ?? 'all') : 'all';
$subjectRequired = !sr_popup_layer_is_public_target_option($selectedTargetOption) && $currentMatchType === 'exact';

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($popupLayerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/save')); ?>" class="admin-form ui-form-theme" data-admin-subject-form data-public-target-value="<?php echo sr_e(sr_popup_layer_public_target_option_value()); ?>">
        <section class="admin-card card">
            <h2><?php echo $editing ? sr_t('popup_layer::ui.edit.b0a3dd3e') : sr_t('popup_layer::ui.text.628a32fc'); ?></h2>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="popup_id" value="<?php echo $editing ? sr_e((string) $editPopup['id']) : '0'; ?>">

                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_title"><?php echo sr_e(sr_t('popup_layer::ui.text.08b17e43')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="popup_layer_admin_popup_layers_title" type="text" name="title" value="<?php echo $editing ? sr_e((string) $editPopup['title']) : ''; ?>" class="form-input form-control-full" maxlength="120" required>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_body_text"><?php echo sr_e(sr_t('popup_layer::ui.text.cb0f2404')); ?></label>
                    <div class="admin-form-field">
                        <textarea id="popup_layer_admin_popup_layers_body_text" name="body_text" maxlength="5000" class="form-textarea"><?php echo $editing ? sr_e((string) $editPopup['body_text']) : ''; ?></textarea>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_status"><?php echo sr_e(sr_t('popup_layer::ui.status.e10195a1')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_status" name="status" class="form-select">
                                                    <?php foreach ($allowedStatuses as $status) { ?>
                                                        <?php $currentStatus = $editing ? (string) $editPopup['status'] : 'draft'; ?>
                                                        <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_skin_key"><?php echo sr_e(sr_t('popup_layer::ui.text.9c7f107d')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_skin_key" name="skin_key" class="form-select">
                                                    <?php foreach ($popupLayerSkinOptions as $skinKey => $skinOption) { ?>
                                                        <?php $currentSkinKey = $editing ? (string) ($editPopup['skin_key'] ?? $popupLayerSkinKey) : $popupLayerSkinKey; ?>
                                                        <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                                            <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_target_option"><?php echo sr_e(sr_t('popup_layer::ui.text.75911303')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_target_option" name="target_option" class="form-select">
                                                    <option value="<?php echo sr_e(sr_popup_layer_public_target_option_value()); ?>"<?php echo $selectedTargetOption === sr_popup_layer_public_target_option_value() ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_t('popup_layer::ui.text.11677edb')); ?>
                                                    </option>
                                                    <?php foreach ($availableTargets as $target) { ?>
                                                        <?php $optionValue = sr_popup_layer_target_option_value($target); ?>
                                                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_popup_layer_target_option_label($target)); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                        <br>
                                            <small><?php echo sr_e(sr_t('popup_layer::ui.settings.select.active.a35cb577')); ?></small>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_match_type"><?php echo sr_e(sr_t('popup_layer::ui.text.175f56ba')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <select id="popup_layer_admin_popup_layers_match_type" name="match_type" class="form-select">
                                                    <?php foreach ($allowedMatchTypes as $matchType) { ?>
                                                        <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                                            <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_subject_id"><?php echo sr_e(sr_t('popup_layer::ui.subject.id.14852174')); ?> <span class="sr-required-label" data-admin-subject-required<?php echo $subjectRequired ? '' : ' hidden'; ?>><?php echo sr_e(sr_t('popup_layer::ui.required.1f227c67')); ?></span></label>
                    <div class="admin-form-field">
                        <input id="popup_layer_admin_popup_layers_subject_id" type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editPopup['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80" data-admin-subject-id<?php echo $subjectRequired ? ' required' : ''; ?>>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.65bdaefd')); ?></label>
                    <div class="admin-form-field">
                        <input type="datetime-local" name="starts_at" id="popup_starts_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['starts_at'] ?? null)) : ''; ?>" class="form-input">
                        <div class="admin-date-quick-actions">
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick="now" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.df159f47')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="1" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.4ec242e3')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="3" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.3f9e052e')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="7" data-datetime-target="popup_starts_at"><?php echo sr_e(sr_t('popup_layer::ui.text.b330c2cb')); ?></button>
                                                </div>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_ends_at"><?php echo sr_e(sr_t('popup_layer::ui.text.26c25fca')); ?></label>
                    <div class="admin-form-field">
                        <input type="datetime-local" name="ends_at" id="popup_ends_at" value="<?php echo $editing ? sr_e(sr_popup_layer_admin_datetime_value($editPopup['ends_at'] ?? null)) : ''; ?>" class="form-input">
                        <div class="admin-date-quick-actions">
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="1" data-datetime-target="popup_ends_at"><?php echo sr_e(sr_t('popup_layer::ui.text.4ec242e3')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="3" data-datetime-target="popup_ends_at"><?php echo sr_e(sr_t('popup_layer::ui.text.3f9e052e')); ?></button>
                                                    <button type="button" class="btn btn-sm btn-solid-light" data-datetime-quick-days="7" data-datetime-target="popup_ends_at"><?php echo sr_e(sr_t('popup_layer::ui.text.b330c2cb')); ?></button>
                                                </div>
                    </div>
                </div>
                <div class="admin-form-row">
                    <label class="form-label" for="popup_layer_admin_popup_layers_dismiss_cookie_days"><?php echo sr_e(sr_t('popup_layer::ui.close.06cddc6e')); ?></label>
                    <div class="admin-form-field">
                        <input id="popup_layer_admin_popup_layers_dismiss_cookie_days" type="number" name="dismiss_cookie_days" value="<?php echo $editing ? sr_e((string) $editPopup['dismiss_cookie_days']) : '1'; ?>" class="form-input" min="0" max="365">
                    </div>
                </div>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('popup_layer::ui.list.f07b3200')); ?></a>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('popup_layer::ui.save.5fb92622')); ?></button>
        </div>
    </form>
<?php } else { ?>
    <div class="admin-local-nav-wrap">
        <div class="admin-local-nav">
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="btn btn-solid-light"><?php echo sr_e(sr_t('popup_layer::ui.all.e078b14a')); ?></a>
        </div>
        <div class="admin-summary-stats">
            <span class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.text.afecea43')); ?> <strong><?php echo sr_e((string) $totalPopups); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></strong></span>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=enabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.active.93c558d7')); ?> <?php echo sr_e((string) ($popupStatusCounts['enabled'] ?? 0)); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=draft')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.text.145b2413')); ?> <?php echo sr_e((string) ($popupStatusCounts['draft'] ?? 0)); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers?status=disabled')); ?>" class="admin-summary-meta"><?php echo sr_e(sr_t('popup_layer::ui.text.92cdef3c')); ?> <?php echo sr_e((string) ($popupStatusCounts['disabled'] ?? 0)); ?><?php echo sr_e(sr_t('popup_layer::ui.text.a57ab057')); ?></a>
        </div>
    </div>

    <form method="get" action="<?php echo sr_e(sr_url('/admin/popup-layers')); ?>" class="admin-filter admin-popup-layer-filter ui-form-theme">
        <div class="admin-filter-grid admin-popup-layer-search-grid">
            <div class="admin-filter-field admin-popup-layer-filter-status">
                <label for="modules_popup_layer_admin_popup_layers_status_filter" class="admin-filter-label"><?php echo sr_e(sr_t('popup_layer::ui.status.e10195a1')); ?></label>
                <select id="modules_popup_layer_admin_popup_layers_status_filter" name="status" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['status'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('popup_layer::ui.all.a4b69faf')); ?></option>
                    <?php foreach ($allowedStatuses as $status) { ?>
                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($filters['status'] ?? '') === $status ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-popup-layer-filter-target">
                <label for="modules_popup_layer_admin_popup_layers_target_filter" class="admin-filter-label"><?php echo sr_e(sr_t('popup_layer::ui.text.75911303')); ?></label>
                <select id="modules_popup_layer_admin_popup_layers_target_filter" name="target" class="form-select admin-filter-input">
                    <option value=""<?php echo (string) ($filters['target'] ?? '') === '' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('popup_layer::ui.all.a4b69faf')); ?></option>
                    <option value="<?php echo sr_e(sr_popup_layer_public_target_option_value()); ?>"<?php echo (string) ($filters['target'] ?? '') === sr_popup_layer_public_target_option_value() ? ' selected' : ''; ?>><?php echo sr_e(sr_t('popup_layer::ui.text.11677edb')); ?></option>
                    <?php foreach ($availableTargets as $target) { ?>
                        <?php $optionValue = sr_popup_layer_target_option_value($target); ?>
                        <option value="<?php echo sr_e($optionValue); ?>"<?php echo (string) ($filters['target'] ?? '') === $optionValue ? ' selected' : ''; ?>>
                            <?php echo sr_e(sr_popup_layer_target_option_label($target)); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-popup-layer-filter-field">
                <label for="modules_popup_layer_admin_popup_layers_field" class="admin-filter-label"><?php echo sr_e(sr_t('popup_layer::ui.search.b79bc9c8')); ?></label>
                <select id="modules_popup_layer_admin_popup_layers_field" name="field" class="form-select admin-filter-input">
                    <?php foreach (['all' => sr_t('popup_layer::ui.all.a4b69faf'), 'title' => sr_t('popup_layer::ui.text.08b17e43'), 'subject' => 'Subject ID'] as $fieldValue => $fieldLabel) { ?>
                        <option value="<?php echo sr_e($fieldValue); ?>"<?php echo (string) ($filters['field'] ?? 'all') === $fieldValue ? ' selected' : ''; ?>>
                            <?php echo sr_e($fieldLabel); ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-filter-field admin-popup-layer-filter-keyword">
                <label for="modules_popup_layer_admin_popup_layers_q" class="admin-filter-label"><?php echo sr_e(sr_t('popup_layer::ui.search.bda397fc')); ?></label>
                <input id="modules_popup_layer_admin_popup_layers_q" type="search" name="q" value="<?php echo sr_e((string) ($filters['q'] ?? '')); ?>" class="form-input admin-filter-input" maxlength="120" placeholder="<?php echo sr_e(sr_t('popup_layer::ui.subject.id.b70d79cb')); ?>">
            </div>
            <button type="submit" class="btn btn-solid-primary admin-filter-submit"><?php echo sr_e(sr_t('popup_layer::ui.search.4b8d541e')); ?></button>
        </div>
    </form>

    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <h2 class="card-title"><?php echo sr_e(sr_t('popup_layer::ui.list.f0aa41f6')); ?></h2>
            <a href="<?php echo sr_e(sr_url('/admin/popup-layers/new')); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('popup_layer::ui.text.bbd10514')); ?></a>
        </div>
        <div class="table-wrapper">
        <table class="table admin-popup-layer-table">
            <caption class="sr-only"><?php echo sr_e(sr_t('popup_layer::ui.list.f0aa41f6')); ?></caption>
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('popup_layer::ui.text.08b17e43')); ?></th>
                    <th><?php echo sr_e(sr_t('popup_layer::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('popup_layer::ui.text.776b723f')); ?></th>
                    <th><?php echo sr_e(sr_t('popup_layer::ui.text.8c609deb')); ?></th>
                    <th><?php echo sr_e(sr_t('popup_layer::ui.text.b918d5af')); ?></th>
                    <th><?php echo sr_e(sr_t('popup_layer::ui.close.06cddc6e')); ?></th>
                    <th><?php echo sr_e(sr_t('popup_layer::ui.edit.d3a98476')); ?></th>
                    <th class="text-end"><?php echo sr_e(sr_t('popup_layer::ui.text.29ae8f30')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($popups === []) { ?>
                    <tr>
                        <td colspan="8" class="admin-empty-state"><?php echo sr_e(sr_t('popup_layer::ui.create.88d48f71')); ?></td>
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
                            <td class="admin-table-break admin-popup-layer-title-cell"><?php echo sr_e((string) $popup['title']); ?></td>
                            <td class="admin-table-nowrap"><span class="admin-status <?php echo sr_e($statusClass); ?>"><?php echo sr_e(sr_admin_code_label($popupStatus, 'content_status')); ?></span></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(sr_popup_layer_skin_key(['popup_layer_skin_key' => (string) ($popup['skin_key'] ?? 'basic')])); ?></td>
                            <td class="admin-table-break admin-popup-layer-target-cell">
                                <?php echo sr_e($popupTargetLabel); ?><br>
                                <?php echo sr_e((string) $popup['match_type'] . ((string) ($popup['subject_id'] ?? '') !== '' ? ': ' . (string) $popup['subject_id'] : '')); ?>
                            </td>
                            <td class="admin-table-nowrap admin-popup-layer-date-cell">
                                <?php echo sr_e((string) ($popup['starts_at'] ?? '-')); ?><br>
                                <?php echo sr_e((string) ($popup['ends_at'] ?? '-')); ?>
                            </td>
                            <td class="admin-table-nowrap text-end"><?php echo sr_e((string) $popup['dismiss_cookie_days']); ?></td>
                            <td class="admin-table-nowrap admin-popup-layer-date-cell"><?php echo sr_e((string) $popup['updated_at']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/popup-layers/edit?id=' . rawurlencode((string) $popup['id']))); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e(sr_t('popup_layer::ui.edit.3537f0cc')); ?></a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/popup-layers/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="popup_id" value="<?php echo sr_e((string) $popup['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><?php echo sr_e(sr_t('popup_layer::ui.delete.6139b6c3')); ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
    </section>
<?php } ?>

<?php if ($popupLayerAdminPage === 'form') { ?>
    <script>
    (function () {
        var form = document.querySelector('[data-admin-subject-form]');
        if (!form) {
            return;
        }

        var target = form.querySelector('select[name="target_option"]');
        var match = form.querySelector('select[name="match_type"]');
        var subject = form.querySelector('[data-admin-subject-id]');
        var label = form.querySelector('[data-admin-subject-required]');
        var publicTarget = form.getAttribute('data-public-target-value') || '';

        function syncSubjectRequired() {
            var needed = !!(target && match && target.value !== publicTarget && match.value === 'exact');
            if (label) {
                label.hidden = !needed;
            }
            if (subject) {
                subject.required = needed;
            }
        }

        form.addEventListener('change', function (event) {
            if (event.target === target || event.target === match) {
                syncSubjectRequired();
            }
        });
        syncSubjectRequired();
    })();
    </script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
