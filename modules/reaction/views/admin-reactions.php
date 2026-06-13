<?php

$adminPageTitle = '리액션 관리';
$adminContainerClass = 'admin-page-reactions admin-ui-scope';
$reactionDefinitions = isset($reactionDefinitions) && is_array($reactionDefinitions) ? $reactionDefinitions : [];
$reactionPresets = isset($reactionPresets) && is_array($reactionPresets) ? $reactionPresets : [];
$reactionPresetItems = isset($reactionPresetItems) && is_array($reactionPresetItems) ? $reactionPresetItems : [];
$reactionRecordFilters = isset($reactionRecordFilters) && is_array($reactionRecordFilters) ? sr_reaction_admin_record_filters($reactionRecordFilters) : sr_reaction_admin_record_filters([]);
$reactionRecords = isset($reactionRecords) && is_array($reactionRecords) ? $reactionRecords : [];
$reactionRecordTargets = isset($reactionRecordTargets) && is_array($reactionRecordTargets) ? $reactionRecordTargets : [];
$definitionStatuses = sr_reaction_definition_statuses();
$presetStatuses = sr_reaction_preset_statuses();
$iconTypes = sr_reaction_icon_types();
$disabledDefinitions = array_values(array_filter($reactionDefinitions, static function (array $definition): bool {
    return (string) ($definition['status'] ?? '') === 'disabled' && (int) ($definition['record_count'] ?? 0) > 0;
}));
$activeDefinitions = array_values(array_filter($reactionDefinitions, static function (array $definition): bool {
    return (string) ($definition['status'] ?? '') === 'active';
}));
$reactionPostedIntent = sr_request_method() === 'POST' ? sr_post_string('intent', 40) : '';
$reactionPostedId = sr_request_method() === 'POST' ? (int) sr_post_string('id', 20) : 0;
$reactionPostedKey = sr_request_method() === 'POST' ? sr_post_string('reaction_key', 80) : '';
$reactionPostedPresetKeys = sr_request_method() === 'POST' && isset($_POST['reaction_keys']) && is_array($_POST['reaction_keys'])
    ? array_values(array_map('strval', $_POST['reaction_keys']))
    : [];
$reactionModalClass = static function (bool $open): string {
    return 'modal-overlay modal-overlay-fade overlay' . ($open ? ' overlay-open open' : ' hidden pointer-events-none opacity-0');
};
$reactionModalHiddenAttrs = static function (bool $open): string {
    return ' aria-hidden="' . ($open ? 'false' : 'true') . '"' . ($open ? '' : ' inert');
};
$reactionCloseLabel = sr_t('admin::ui.close.1e8c1020');
$reactionCreateDefinitionModalOpen = $errors !== [] && $reactionPostedIntent === 'save_definition' && $reactionPostedId < 1;
$reactionCreatePresetModalOpen = $errors !== [] && $reactionPostedIntent === 'save_preset' && $reactionPostedId < 1;
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">정의 <strong><?php echo sr_e((string) count($reactionDefinitions)); ?>개</strong></span>
        <span class="admin-summary-meta">Preset <strong><?php echo sr_e((string) count($reactionPresets)); ?>개</strong></span>
        <span class="admin-summary-meta">공개 preset key 수는 최대 12개입니다.</span>
    </div>
    <div class="admin-page-title-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $reactionCreateDefinitionModalOpen ? 'true' : 'false'; ?>" aria-controls="reaction-definition-create-modal" data-overlay="#reaction-definition-create-modal"><?php echo sr_material_icon_html('add'); ?>정의 추가</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $reactionCreatePresetModalOpen ? 'true' : 'false'; ?>" aria-controls="reaction-preset-create-modal" data-overlay="#reaction-preset-create-modal"><?php echo sr_material_icon_html('add'); ?>Preset 추가</button>
    </div>
</div>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">리액션 레코드 점검</h2>
    </div>
    <form method="get" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="admin-form ui-form-theme">
        <div class="admin-form-grid">
            <div class="admin-form-field">
                <label for="reaction_record_account_id">회원 ID</label>
                <input id="reaction_record_account_id" type="number" name="account_id" class="form-input" min="1" value="<?php echo (int) ($reactionRecordFilters['account_id'] ?? 0) > 0 ? sr_e((string) (int) $reactionRecordFilters['account_id']) : ''; ?>">
            </div>
            <div class="admin-form-field">
                <label for="reaction_record_target_module">대상 모듈</label>
                <input id="reaction_record_target_module" type="text" name="target_module" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]*" data-admin-key-input value="<?php echo sr_e((string) ($reactionRecordFilters['target_module'] ?? '')); ?>">
            </div>
            <div class="admin-form-field">
                <label for="reaction_record_target_type">대상 유형</label>
                <input id="reaction_record_target_type" type="text" name="target_type" class="form-input" maxlength="60" pattern="[a-z][a-z0-9_]*" data-admin-key-input value="<?php echo sr_e((string) ($reactionRecordFilters['target_type'] ?? '')); ?>">
            </div>
            <div class="admin-form-field">
                <label for="reaction_record_target_id">대상 ID</label>
                <input id="reaction_record_target_id" type="number" name="target_id" class="form-input" min="1" value="<?php echo (string) ($reactionRecordFilters['target_id'] ?? '') !== '' ? sr_e((string) $reactionRecordFilters['target_id']) : ''; ?>">
            </div>
            <div class="admin-form-field">
                <label for="reaction_record_key">리액션 key</label>
                <input id="reaction_record_key" type="text" name="reaction_key" class="form-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input value="<?php echo sr_e((string) ($reactionRecordFilters['reaction_key'] ?? '')); ?>">
            </div>
        </div>
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary">조회</button>
            <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url('/admin/reactions')); ?>">초기화</a>
        </div>
    </form>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">리액션 레코드 최근 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>대상</th>
                    <th>상태</th>
                    <th>리액션</th>
                    <th>수정</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reactionRecords === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state">조회된 리액션 레코드가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($reactionRecords as $record) { ?>
                    <?php
                    $recordTargetKey = (string) ($record['target_module'] ?? '') . '/' . (string) ($record['target_type'] ?? '') . '/' . (string) ($record['target_id'] ?? '');
                    $recordTarget = isset($reactionRecordTargets[$recordTargetKey]) && is_array($reactionRecordTargets[$recordTargetKey]) ? $reactionRecordTargets[$recordTargetKey] : null;
                    $recordTargetStatus = is_array($recordTarget) ? (string) ($recordTarget['status'] ?? 'broken') : 'unknown';
                    $recordTargetLabel = is_array($recordTarget) && (string) ($recordTarget['label'] ?? '') !== '' ? (string) $recordTarget['label'] : '#' . (string) ($record['target_id'] ?? '');
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($record['id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($record['account_id'] ?? 0)); ?></td>
                        <td class="admin-table-break">
                            <code><?php echo sr_e((string) ($record['target_module'] ?? '')); ?>/<?php echo sr_e((string) ($record['target_type'] ?? '')); ?>/<?php echo sr_e((string) ($record['target_id'] ?? '')); ?></code>
                            <br>
                            <?php if (is_array($recordTarget) && (string) ($recordTarget['public_url'] ?? '') !== '') { ?>
                                <a href="<?php echo sr_e(sr_url((string) $recordTarget['public_url'])); ?>" target="_blank" rel="noopener"><?php echo sr_e($recordTargetLabel); ?></a>
                            <?php } else { ?>
                                <?php echo sr_e($recordTargetLabel); ?>
                            <?php } ?>
                            <?php if (is_array($recordTarget) && (string) ($recordTarget['admin_url'] ?? '') !== '') { ?>
                                <br><a href="<?php echo sr_e(sr_url((string) $recordTarget['admin_url'])); ?>">관리 화면</a>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_e($recordTargetStatus); ?>
                            <?php if (is_array($recordTarget) && empty($recordTarget['can_write'])) { ?>
                                <br><span class="admin-summary-meta">쓰기 불가</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_e((string) ($record['reaction_label'] ?? $record['reaction_key'] ?? '')); ?>
                            <br><code><?php echo sr_e((string) ($record['reaction_key'] ?? '')); ?></code>
                            <?php if ((string) ($record['reaction_status'] ?? '') === 'disabled') { ?>
                                <br><span class="admin-summary-meta">사용 중지</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($record['updated_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">리액션 정의</h2>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $reactionCreateDefinitionModalOpen ? 'true' : 'false'; ?>" aria-controls="reaction-definition-create-modal" data-overlay="#reaction-definition-create-modal"><?php echo sr_material_icon_html('add'); ?>추가</button>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">리액션 정의 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>키</th>
                    <th>표시</th>
                    <th>아이콘</th>
                    <th>상태</th>
                    <th>사용 레코드</th>
                    <th>정렬</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reactionDefinitions === []) { ?>
                    <tr><td colspan="7" class="admin-empty-state">등록된 리액션 정의가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($reactionDefinitions as $definition) { ?>
                    <?php
                    $definitionId = (int) ($definition['id'] ?? 0);
                    $definitionModalId = 'reaction-definition-edit-modal-' . (string) $definitionId;
                    $definitionModalOpen = $errors !== [] && $reactionPostedIntent === 'save_definition' && $reactionPostedId === $definitionId;
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><code><?php echo sr_e((string) ($definition['reaction_key'] ?? '')); ?></code></td>
                        <td class="admin-table-break">
                            <?php echo sr_e((string) ($definition['label'] ?? $definition['reaction_key'] ?? '')); ?>
                            <?php if ((string) ($definition['description'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($definition['description'] ?? '')); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_e((string) ($definition['icon_type'] ?? '')); ?>
                            <?php if ((string) ($definition['icon_value'] ?? '') !== '') { ?>
                                <br><code><?php echo sr_e((string) ($definition['icon_value'] ?? '')); ?></code>
                            <?php } ?>
                            <?php if ((string) ($definition['color_hex'] ?? '') !== '') { ?>
                                <br><code><?php echo sr_e((string) ($definition['color_hex'] ?? '')); ?></code>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_code_label((string) ($definition['status'] ?? ''), 'module_status')); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($definition['sort_order'] ?? 100)); ?></td>
                        <td class="admin-table-actions-cell">
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="리액션 정의 수정" title="수정" aria-haspopup="dialog" aria-expanded="<?php echo $definitionModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($definitionModalId); ?>" data-overlay="#<?php echo sr_e($definitionModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">사용 중지 key의 기존 레코드 처리</h2>
    </div>
    <p class="admin-summary-meta">사용 중지된 key는 신규 적용/변경이 차단되고 공개 UI에서 숨겨집니다. 기존 레코드는 보관하거나, 삭제하거나, 다른 active key로 병합할 수 있습니다.</p>
    <?php if ($disabledDefinitions === []) { ?>
        <p class="admin-empty-state">처리할 사용 중지 key 레코드가 없습니다.</p>
    <?php } else { ?>
        <div class="table-wrapper">
            <table class="table">
                <caption class="sr-only">사용 중지 리액션 레코드 처리 대상</caption>
                <thead class="ui-table-head">
                    <tr>
                        <th>키</th>
                        <th>표시명</th>
                        <th>기존 레코드</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($disabledDefinitions as $definition) { ?>
                        <?php
                        $reactionKey = (string) ($definition['reaction_key'] ?? '');
                        $cleanupModalId = 'reaction-cleanup-modal-' . $reactionKey;
                        $cleanupModalOpen = $errors !== [] && $reactionPostedIntent === 'cleanup_records' && $reactionPostedKey === $reactionKey;
                        ?>
                        <tr>
                            <td class="admin-table-nowrap"><code><?php echo sr_e($reactionKey); ?></code></td>
                            <td><?php echo sr_e((string) ($definition['label'] ?? $reactionKey)); ?></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?>개</td>
                            <td class="admin-table-actions-cell">
                                <button type="button" class="btn btn-sm btn-outline-danger" aria-haspopup="dialog" aria-expanded="<?php echo $cleanupModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($cleanupModalId); ?>" data-overlay="#<?php echo sr_e($cleanupModalId); ?>">처리</button>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">Preset</h2>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $reactionCreatePresetModalOpen ? 'true' : 'false'; ?>" aria-controls="reaction-preset-create-modal" data-overlay="#reaction-preset-create-modal"><?php echo sr_material_icon_html('add'); ?>추가</button>
        </div>
    </div>
    <p class="admin-summary-meta">1차 정책은 단일 선택만 지원합니다. 선택한 key 순서대로 공개 버튼이 표시됩니다.</p>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">리액션 preset 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>키</th>
                    <th>이름</th>
                    <th>상태</th>
                    <th>공개 표시</th>
                    <th>리액션 key</th>
                    <th>정렬</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reactionPresets === []) { ?>
                    <tr><td colspan="7" class="admin-empty-state">등록된 preset이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($reactionPresets as $preset) { ?>
                    <?php
                    $presetId = (int) ($preset['id'] ?? 0);
                    $presetKey = (string) ($preset['preset_key'] ?? '');
                    $presetModalId = 'reaction-preset-edit-modal-' . (string) $presetId;
                    $presetModalOpen = $errors !== [] && $reactionPostedIntent === 'save_preset' && $reactionPostedId === $presetId;
                    $selectedKeys = [];
                    foreach ((array) ($reactionPresetItems[$presetKey] ?? []) as $item) {
                        $selectedKeys[] = (string) ($item['reaction_key'] ?? '');
                    }
                    ?>
                    <tr>
                        <td class="admin-table-nowrap"><code><?php echo sr_e($presetKey); ?></code></td>
                        <td class="admin-table-break">
                            <?php echo sr_e((string) ($preset['label'] ?? $presetKey)); ?>
                            <?php if ((string) ($preset['description'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($preset['description'] ?? '')); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e(sr_admin_code_label((string) ($preset['status'] ?? ''), 'module_status')); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($preset['visible_key_limit'] ?? 6)); ?>개</td>
                        <td class="admin-table-break">
                            <?php if ($selectedKeys === []) { ?>
                                <span class="admin-summary-meta">없음</span>
                            <?php } else { ?>
                                <?php foreach ($selectedKeys as $selectedKey) { ?>
                                    <code><?php echo sr_e($selectedKey); ?></code>
                                <?php } ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($preset['sort_order'] ?? 100)); ?></td>
                        <td class="admin-table-actions-cell">
                            <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="Preset 수정" title="수정" aria-haspopup="dialog" aria-expanded="<?php echo $presetModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($presetModalId); ?>" data-overlay="#<?php echo sr_e($presetModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<div id="reaction-definition-create-modal" class="<?php echo sr_e($reactionModalClass($reactionCreateDefinitionModalOpen)); ?>" role="dialog" tabindex="-1" aria-labelledby="reaction-definition-create-modal-title"<?php echo $reactionModalHiddenAttrs($reactionCreateDefinitionModalOpen); ?>>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="modal-content admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_definition">
            <input type="hidden" name="status" value="active">
            <div class="modal-header">
                <h3 id="reaction-definition-create-modal-title" class="modal-title">리액션 정의 추가</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#reaction-definition-create-modal"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <div class="admin-form-grid">
                    <div class="admin-form-field">
                        <label for="reaction_new_key">키 <span class="sr-required-label">(필수)</span></label>
                        <input id="reaction_new_key" type="text" name="reaction_key" class="form-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input required value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('reaction_key', 80)) : ''; ?>">
                        <p class="admin-form-help">영문 소문자, 숫자, _ 조합으로 입력하세요. 생성 후 변경하지 않습니다.</p>
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_label">표시명 <span class="sr-required-label">(필수)</span></label>
                        <input id="reaction_new_label" type="text" name="label" class="form-input" maxlength="80" required value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('label', 80)) : ''; ?>">
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_icon_type">아이콘 유형</label>
                        <select id="reaction_new_icon_type" name="icon_type" class="form-select">
                            <?php foreach ($iconTypes as $iconType) { ?>
                                <option value="<?php echo sr_e($iconType); ?>"<?php echo $reactionCreateDefinitionModalOpen && sr_post_string('icon_type', 20) === $iconType ? ' selected' : ''; ?>><?php echo sr_e($iconType); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_icon_value">아이콘 값</label>
                        <input id="reaction_new_icon_value" type="text" name="icon_value" class="form-input" maxlength="40" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('icon_value', 40)) : ''; ?>">
                        <p class="admin-form-help">이모지 또는 Material icon key를 입력하세요.</p>
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_color_hex">색상</label>
                        <input id="reaction_new_color_hex" type="text" name="color_hex" class="form-input" maxlength="20" placeholder="#2563eb" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('color_hex', 20)) : ''; ?>">
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_sort">정렬</label>
                        <input id="reaction_new_sort" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('sort_order', 20)) : '100'; ?>">
                    </div>
                </div>
                <div class="admin-form-field">
                    <label for="reaction_new_description">설명</label>
                    <input id="reaction_new_description" type="text" name="description" class="form-input" maxlength="255" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('description', 255)) : ''; ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#reaction-definition-create-modal"><?php echo sr_e($reactionCloseLabel); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action">정의 추가</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($reactionDefinitions as $definition) { ?>
    <?php
    $definitionId = (int) ($definition['id'] ?? 0);
    $definitionModalId = 'reaction-definition-edit-modal-' . (string) $definitionId;
    $definitionModalTitleId = $definitionModalId . '-title';
    $definitionModalOpen = $errors !== [] && $reactionPostedIntent === 'save_definition' && $reactionPostedId === $definitionId;
    ?>
    <div id="<?php echo sr_e($definitionModalId); ?>" class="<?php echo sr_e($reactionModalClass($definitionModalOpen)); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($definitionModalTitleId); ?>"<?php echo $reactionModalHiddenAttrs($definitionModalOpen); ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="modal-content admin-form ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="save_definition">
                <input type="hidden" name="id" value="<?php echo sr_e((string) $definitionId); ?>">
                <input type="hidden" name="color_swatch" value="<?php echo sr_e((string) ($definition['color_swatch'] ?? '')); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($definitionModalTitleId); ?>" class="modal-title">리액션 정의 수정 <code><?php echo sr_e((string) ($definition['reaction_key'] ?? '')); ?></code></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#<?php echo sr_e($definitionModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <div class="admin-form-grid">
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($definitionModalId); ?>_label">표시명 <span class="sr-required-label">(필수)</span></label>
                            <input id="<?php echo sr_e($definitionModalId); ?>_label" type="text" name="label" class="form-input" maxlength="80" value="<?php echo sr_e((string) ($definition['label'] ?? '')); ?>" required>
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($definitionModalId); ?>_status">상태</label>
                            <select id="<?php echo sr_e($definitionModalId); ?>_status" name="status" class="form-select">
                                <?php foreach ($definitionStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($definition['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?></option>
                                <?php } ?>
                            </select>
                            <?php if ((string) ($definition['status'] ?? '') === 'disabled') { ?>
                                <p class="admin-form-help">사용 중지된 key의 기존 레코드는 기본적으로 보관됩니다.</p>
                            <?php } ?>
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($definitionModalId); ?>_icon_type">아이콘 유형</label>
                            <select id="<?php echo sr_e($definitionModalId); ?>_icon_type" name="icon_type" class="form-select">
                                <?php foreach ($iconTypes as $iconType) { ?>
                                    <option value="<?php echo sr_e($iconType); ?>"<?php echo (string) ($definition['icon_type'] ?? '') === $iconType ? ' selected' : ''; ?>><?php echo sr_e($iconType); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($definitionModalId); ?>_icon_value">아이콘 값</label>
                            <input id="<?php echo sr_e($definitionModalId); ?>_icon_value" type="text" name="icon_value" class="form-input" maxlength="40" value="<?php echo sr_e((string) ($definition['icon_value'] ?? '')); ?>">
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($definitionModalId); ?>_color_hex">색상</label>
                            <input id="<?php echo sr_e($definitionModalId); ?>_color_hex" type="text" name="color_hex" class="form-input" maxlength="20" value="<?php echo sr_e((string) ($definition['color_hex'] ?? '')); ?>">
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($definitionModalId); ?>_sort_order">정렬</label>
                            <input id="<?php echo sr_e($definitionModalId); ?>_sort_order" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo sr_e((string) (int) ($definition['sort_order'] ?? 100)); ?>">
                        </div>
                    </div>
                    <div class="admin-form-field">
                        <label for="<?php echo sr_e($definitionModalId); ?>_description">설명</label>
                        <input id="<?php echo sr_e($definitionModalId); ?>_description" type="text" name="description" class="form-input" maxlength="255" value="<?php echo sr_e((string) ($definition['description'] ?? '')); ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($definitionModalId); ?>"><?php echo sr_e($reactionCloseLabel); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php foreach ($disabledDefinitions as $definition) { ?>
    <?php
    $reactionKey = (string) ($definition['reaction_key'] ?? '');
    $cleanupModalId = 'reaction-cleanup-modal-' . $reactionKey;
    $cleanupModalTitleId = $cleanupModalId . '-title';
    $cleanupModalOpen = $errors !== [] && $reactionPostedIntent === 'cleanup_records' && $reactionPostedKey === $reactionKey;
    ?>
    <div id="<?php echo sr_e($cleanupModalId); ?>" class="<?php echo sr_e($reactionModalClass($cleanupModalOpen)); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($cleanupModalTitleId); ?>"<?php echo $reactionModalHiddenAttrs($cleanupModalOpen); ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="modal-content admin-form ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="cleanup_records">
                <input type="hidden" name="reaction_key" value="<?php echo sr_e($reactionKey); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($cleanupModalTitleId); ?>" class="modal-title">기존 레코드 처리 <code><?php echo sr_e($reactionKey); ?></code></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#<?php echo sr_e($cleanupModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <p class="admin-summary-meta">기존 레코드 <?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?>개</p>
                    <div class="admin-form-field">
                        <label for="<?php echo sr_e($cleanupModalId); ?>_policy">처리 방식</label>
                        <select id="<?php echo sr_e($cleanupModalId); ?>_policy" name="cleanup_policy" class="form-select">
                            <option value="keep_public_hidden">보관하고 공개 UI에서 숨김</option>
                            <option value="keep_admin_statistics">보관하고 관리자/통계에만 표시</option>
                            <option value="delete">기존 레코드 삭제</option>
                            <option value="merge">다른 reaction key로 병합</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="<?php echo sr_e($cleanupModalId); ?>_merge_target">병합 대상 key</label>
                        <select id="<?php echo sr_e($cleanupModalId); ?>_merge_target" name="merge_target_key" class="form-select">
                            <option value="">선택 안 함</option>
                            <?php foreach ($activeDefinitions as $activeDefinition) { ?>
                                <?php $activeKey = (string) ($activeDefinition['reaction_key'] ?? ''); ?>
                                <option value="<?php echo sr_e($activeKey); ?>"><?php echo sr_e((string) ($activeDefinition['label'] ?? $activeKey)); ?> (<?php echo sr_e($activeKey); ?>)</option>
                            <?php } ?>
                        </select>
                        <p class="admin-form-help">병합을 선택할 때만 사용합니다. 같은 회원/target에 대상 key가 이미 있으면 source row는 삭제됩니다.</p>
                    </div>
                    <div class="admin-form-field">
                        <label for="<?php echo sr_e($cleanupModalId); ?>_confirm">확인 문구</label>
                        <input id="<?php echo sr_e($cleanupModalId); ?>_confirm" type="text" name="confirmation_key" class="form-input" maxlength="80">
                        <p class="admin-form-help">삭제 또는 병합을 실행하려면 <code><?php echo sr_e($reactionKey); ?></code>를 입력하세요.</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($cleanupModalId); ?>"><?php echo sr_e($reactionCloseLabel); ?></button>
                    <button type="submit" class="btn btn-outline-danger modal-action">처리 적용</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<div id="reaction-preset-create-modal" class="<?php echo sr_e($reactionModalClass($reactionCreatePresetModalOpen)); ?>" role="dialog" tabindex="-1" aria-labelledby="reaction-preset-create-modal-title"<?php echo $reactionModalHiddenAttrs($reactionCreatePresetModalOpen); ?>>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="modal-content admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_preset">
            <input type="hidden" name="status" value="active">
            <div class="modal-header">
                <h3 id="reaction-preset-create-modal-title" class="modal-title">Preset 추가</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#reaction-preset-create-modal"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <div class="admin-form-grid">
                    <div class="admin-form-field">
                        <label for="reaction_new_preset_key">Preset 키 <span class="sr-required-label">(필수)</span></label>
                        <input id="reaction_new_preset_key" type="text" name="preset_key" class="form-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input required value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('preset_key', 80)) : ''; ?>">
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_preset_label">이름 <span class="sr-required-label">(필수)</span></label>
                        <input id="reaction_new_preset_label" type="text" name="label" class="form-input" maxlength="80" required value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('label', 80)) : ''; ?>">
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_preset_limit">공개 표시 개수</label>
                        <input id="reaction_new_preset_limit" type="number" name="visible_key_limit" class="form-input" min="1" max="12" value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('visible_key_limit', 20)) : '6'; ?>">
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_new_preset_sort">정렬</label>
                        <input id="reaction_new_preset_sort" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('sort_order', 20)) : '100'; ?>">
                    </div>
                </div>
                <div class="admin-form-field">
                    <label for="reaction_new_preset_description">설명</label>
                    <input id="reaction_new_preset_description" type="text" name="description" class="form-input" maxlength="255" value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('description', 255)) : ''; ?>">
                </div>
                <fieldset class="admin-form-field">
                    <legend>리액션 key <span class="sr-required-label">(필수)</span></legend>
                    <?php foreach ($reactionDefinitions as $definition) { ?>
                        <?php $key = (string) ($definition['reaction_key'] ?? ''); ?>
                        <label class="admin-check-row">
                            <input type="checkbox" name="reaction_keys[]" value="<?php echo sr_e($key); ?>"<?php echo in_array($key, $reactionPostedPresetKeys, true) ? ' checked' : ''; ?>>
                            <span><?php echo sr_e((string) ($definition['label'] ?? $key)); ?></span>
                        </label>
                    <?php } ?>
                </fieldset>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#reaction-preset-create-modal"><?php echo sr_e($reactionCloseLabel); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action">Preset 추가</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($reactionPresets as $preset) { ?>
    <?php
    $presetId = (int) ($preset['id'] ?? 0);
    $presetKey = (string) ($preset['preset_key'] ?? '');
    $presetModalId = 'reaction-preset-edit-modal-' . (string) $presetId;
    $presetModalTitleId = $presetModalId . '-title';
    $presetModalOpen = $errors !== [] && $reactionPostedIntent === 'save_preset' && $reactionPostedId === $presetId;
    $selectedKeys = [];
    foreach ((array) ($reactionPresetItems[$presetKey] ?? []) as $item) {
        $selectedKeys[] = (string) ($item['reaction_key'] ?? '');
    }
    if ($presetModalOpen && $reactionPostedPresetKeys !== []) {
        $selectedKeys = $reactionPostedPresetKeys;
    }
    ?>
    <div id="<?php echo sr_e($presetModalId); ?>" class="<?php echo sr_e($reactionModalClass($presetModalOpen)); ?>" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($presetModalTitleId); ?>"<?php echo $reactionModalHiddenAttrs($presetModalOpen); ?>>
        <div class="modal-dialog">
            <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="modal-content admin-form ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="save_preset">
                <input type="hidden" name="id" value="<?php echo sr_e((string) $presetId); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($presetModalTitleId); ?>" class="modal-title">Preset 수정 <code><?php echo sr_e($presetKey); ?></code></h3>
                    <button type="button" class="modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#<?php echo sr_e($presetModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <div class="admin-form-grid">
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($presetModalId); ?>_label">이름 <span class="sr-required-label">(필수)</span></label>
                            <input id="<?php echo sr_e($presetModalId); ?>_label" type="text" name="label" class="form-input" maxlength="80" value="<?php echo sr_e((string) ($preset['label'] ?? '')); ?>" required>
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($presetModalId); ?>_status">상태</label>
                            <select id="<?php echo sr_e($presetModalId); ?>_status" name="status" class="form-select">
                                <?php foreach ($presetStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($preset['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($presetModalId); ?>_limit">공개 표시 개수</label>
                            <input id="<?php echo sr_e($presetModalId); ?>_limit" type="number" name="visible_key_limit" class="form-input" min="1" max="12" value="<?php echo sr_e((string) (int) ($preset['visible_key_limit'] ?? 6)); ?>">
                        </div>
                        <div class="admin-form-field">
                            <label for="<?php echo sr_e($presetModalId); ?>_sort_order">정렬</label>
                            <input id="<?php echo sr_e($presetModalId); ?>_sort_order" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo sr_e((string) (int) ($preset['sort_order'] ?? 100)); ?>">
                        </div>
                    </div>
                    <div class="admin-form-field">
                        <label for="<?php echo sr_e($presetModalId); ?>_description">설명</label>
                        <input id="<?php echo sr_e($presetModalId); ?>_description" type="text" name="description" class="form-input" maxlength="255" value="<?php echo sr_e((string) ($preset['description'] ?? '')); ?>">
                    </div>
                    <fieldset class="admin-form-field">
                        <legend>리액션 key <span class="sr-required-label">(필수)</span></legend>
                        <?php foreach ($reactionDefinitions as $definition) { ?>
                            <?php $key = (string) ($definition['reaction_key'] ?? ''); ?>
                            <label class="admin-check-row">
                                <input type="checkbox" name="reaction_keys[]" value="<?php echo sr_e($key); ?>"<?php echo in_array($key, $selectedKeys, true) ? ' checked' : ''; ?>>
                                <span><?php echo sr_e((string) ($definition['label'] ?? $key)); ?></span>
                            </label>
                        <?php } ?>
                    </fieldset>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($presetModalId); ?>"><?php echo sr_e($reactionCloseLabel); ?></button>
                    <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
                </div>
            </form>
        </div>
    </div>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
