<?php

$adminPageTitle = '리액션 관리';
$adminContainerClass = 'admin-page-reactions admin-ui-scope';
$reactionDefinitions = isset($reactionDefinitions) && is_array($reactionDefinitions) ? $reactionDefinitions : [];
$reactionPresets = isset($reactionPresets) && is_array($reactionPresets) ? $reactionPresets : [];
$reactionPresetItems = isset($reactionPresetItems) && is_array($reactionPresetItems) ? $reactionPresetItems : [];
$definitionStatuses = sr_reaction_definition_statuses();
$presetStatuses = sr_reaction_preset_statuses();
$iconTypes = sr_reaction_icon_types();
$disabledDefinitions = array_values(array_filter($reactionDefinitions, static function (array $definition): bool {
    return (string) ($definition['status'] ?? '') === 'disabled' && (int) ($definition['record_count'] ?? 0) > 0;
}));
$activeDefinitions = array_values(array_filter($reactionDefinitions, static function (array $definition): bool {
    return (string) ($definition['status'] ?? '') === 'active';
}));
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <span class="admin-summary-meta">정의 <strong><?php echo sr_e((string) count($reactionDefinitions)); ?>개</strong></span>
        <span class="admin-summary-meta">Preset <strong><?php echo sr_e((string) count($reactionPresets)); ?>개</strong></span>
        <span class="admin-summary-meta">공개 preset key 수는 최대 12개입니다.</span>
    </div>
</div>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">리액션 정의 추가</h2>
    </div>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="admin-form ui-form-theme">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="save_definition">
        <div class="admin-form-grid">
            <div class="admin-form-field">
                <label for="reaction_new_key">키 <span class="sr-required-label">(필수)</span></label>
                <input id="reaction_new_key" type="text" name="reaction_key" class="form-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input required>
                <p class="admin-form-help">영문 소문자, 숫자, _ 조합으로 입력하세요. 생성 후 변경하지 않습니다.</p>
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_label">표시명 <span class="sr-required-label">(필수)</span></label>
                <input id="reaction_new_label" type="text" name="label" class="form-input" maxlength="80" required>
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_icon_type">아이콘 유형</label>
                <select id="reaction_new_icon_type" name="icon_type" class="form-select">
                    <?php foreach ($iconTypes as $iconType) { ?>
                        <option value="<?php echo sr_e($iconType); ?>"><?php echo sr_e($iconType); ?></option>
                    <?php } ?>
                </select>
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_icon_value">아이콘 값</label>
                <input id="reaction_new_icon_value" type="text" name="icon_value" class="form-input" maxlength="40">
                <p class="admin-form-help">이모지 또는 Material icon key를 입력하세요.</p>
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_color_hex">색상</label>
                <input id="reaction_new_color_hex" type="text" name="color_hex" class="form-input" maxlength="20" placeholder="#2563eb">
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_sort">정렬</label>
                <input id="reaction_new_sort" type="number" name="sort_order" class="form-input" min="0" max="999999" value="100">
            </div>
        </div>
        <div class="admin-form-field">
            <label for="reaction_new_description">설명</label>
            <input id="reaction_new_description" type="text" name="description" class="form-input" maxlength="255">
        </div>
        <input type="hidden" name="status" value="active">
        <div class="admin-form-actions">
            <button type="submit" class="btn btn-solid-primary">정의 추가</button>
        </div>
    </form>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">사용 중지 key의 기존 레코드 처리</h2>
    </div>
    <p class="admin-summary-meta">사용 중지된 key는 신규 적용/변경이 차단되고 공개 UI에서 숨겨집니다. 기존 레코드는 보관하거나, 삭제하거나, 다른 active key로 병합할 수 있습니다.</p>
    <?php if ($disabledDefinitions === []) { ?>
        <p class="admin-empty-state">처리할 사용 중지 key 레코드가 없습니다.</p>
    <?php } else { ?>
        <div class="admin-grid-two">
            <?php foreach ($disabledDefinitions as $definition) { ?>
                <?php $reactionKey = (string) ($definition['reaction_key'] ?? ''); ?>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="admin-card card ui-form-theme">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="cleanup_records">
                    <input type="hidden" name="reaction_key" value="<?php echo sr_e($reactionKey); ?>">
                    <h3 class="card-title"><?php echo sr_e((string) ($definition['label'] ?? $reactionKey)); ?> <code><?php echo sr_e($reactionKey); ?></code></h3>
                    <p class="admin-summary-meta">
                        기존 레코드 <?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?>개
                    </p>
                    <div class="admin-form-field">
                        <label for="reaction_cleanup_policy_<?php echo sr_e($reactionKey); ?>">처리 방식</label>
                        <select id="reaction_cleanup_policy_<?php echo sr_e($reactionKey); ?>" name="cleanup_policy" class="form-select">
                            <option value="keep_public_hidden">보관하고 공개 UI에서 숨김</option>
                            <option value="keep_admin_statistics">보관하고 관리자/통계에만 표시</option>
                            <option value="delete">기존 레코드 삭제</option>
                            <option value="merge">다른 reaction key로 병합</option>
                        </select>
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_merge_target_<?php echo sr_e($reactionKey); ?>">병합 대상 key</label>
                        <select id="reaction_merge_target_<?php echo sr_e($reactionKey); ?>" name="merge_target_key" class="form-select">
                            <option value="">선택 안 함</option>
                            <?php foreach ($activeDefinitions as $activeDefinition) { ?>
                                <?php $activeKey = (string) ($activeDefinition['reaction_key'] ?? ''); ?>
                                <option value="<?php echo sr_e($activeKey); ?>"><?php echo sr_e((string) ($activeDefinition['label'] ?? $activeKey)); ?> (<?php echo sr_e($activeKey); ?>)</option>
                            <?php } ?>
                        </select>
                        <p class="admin-form-help">병합을 선택할 때만 사용합니다. 같은 회원/target에 대상 key가 이미 있으면 source row는 삭제됩니다.</p>
                    </div>
                    <div class="admin-form-field">
                        <label for="reaction_cleanup_confirm_<?php echo sr_e($reactionKey); ?>">확인 문구</label>
                        <input id="reaction_cleanup_confirm_<?php echo sr_e($reactionKey); ?>" type="text" name="confirmation_key" class="form-input" maxlength="80">
                        <p class="admin-form-help">삭제 또는 병합을 실행하려면 <code><?php echo sr_e($reactionKey); ?></code>를 입력하세요.</p>
                    </div>
                    <button type="submit" class="btn btn-outline-danger">처리 적용</button>
                </form>
            <?php } ?>
        </div>
    <?php } ?>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">리액션 정의</h2>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <caption class="sr-only">리액션 정의 목록</caption>
            <thead class="ui-table-head">
                <tr>
                    <th>키</th>
                    <th>표시</th>
                    <th>상태</th>
                    <th>사용 레코드</th>
                    <th>정렬</th>
                    <th class="text-end">저장</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reactionDefinitions === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state">등록된 리액션 정의가 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($reactionDefinitions as $definition) { ?>
                    <?php $definitionId = (int) ($definition['id'] ?? 0); ?>
                    <tr>
                        <td class="admin-table-nowrap">
                            <code><?php echo sr_e((string) ($definition['reaction_key'] ?? '')); ?></code>
                        </td>
                        <td>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="admin-inline-form ui-form-theme">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="save_definition">
                                <input type="hidden" name="id" value="<?php echo sr_e((string) $definitionId); ?>">
                                <div class="admin-form-grid">
                                    <input type="text" name="label" class="form-input" maxlength="80" value="<?php echo sr_e((string) ($definition['label'] ?? '')); ?>" required>
                                    <select name="icon_type" class="form-select">
                                        <?php foreach ($iconTypes as $iconType) { ?>
                                            <option value="<?php echo sr_e($iconType); ?>"<?php echo (string) ($definition['icon_type'] ?? '') === $iconType ? ' selected' : ''; ?>><?php echo sr_e($iconType); ?></option>
                                        <?php } ?>
                                    </select>
                                    <input type="text" name="icon_value" class="form-input" maxlength="40" value="<?php echo sr_e((string) ($definition['icon_value'] ?? '')); ?>">
                                    <input type="text" name="color_hex" class="form-input" maxlength="20" value="<?php echo sr_e((string) ($definition['color_hex'] ?? '')); ?>">
                                    <input type="hidden" name="color_swatch" value="<?php echo sr_e((string) ($definition['color_swatch'] ?? '')); ?>">
                                </div>
                                <input type="text" name="description" class="form-input" maxlength="255" value="<?php echo sr_e((string) ($definition['description'] ?? '')); ?>">
                        </td>
                        <td class="admin-table-nowrap">
                                <select name="status" class="form-select">
                                    <?php foreach ($definitionStatuses as $status) { ?>
                                        <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($definition['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?></option>
                                    <?php } ?>
                                </select>
                                <?php if ((string) ($definition['status'] ?? '') === 'disabled') { ?>
                                    <p class="admin-form-help">사용 중지된 key의 기존 레코드는 기본적으로 보관됩니다.</p>
                                <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap">
                                <input type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo sr_e((string) (int) ($definition['sort_order'] ?? 100)); ?>">
                        </td>
                        <td class="admin-table-actions-cell">
                                <button type="submit" class="btn btn-sm btn-solid-primary">저장</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
</section>

<section class="admin-card card">
    <div class="card-header">
        <h2 class="card-title">Preset</h2>
    </div>
    <p class="admin-summary-meta">1차 정책은 단일 선택만 지원합니다. 선택한 key 순서대로 공개 버튼이 표시됩니다.</p>
    <div class="admin-grid-two">
        <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_preset">
            <div class="admin-form-field">
                <label for="reaction_new_preset_key">Preset 키 <span class="sr-required-label">(필수)</span></label>
                <input id="reaction_new_preset_key" type="text" name="preset_key" class="form-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input required>
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_preset_label">이름 <span class="sr-required-label">(필수)</span></label>
                <input id="reaction_new_preset_label" type="text" name="label" class="form-input" maxlength="80" required>
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_preset_limit">공개 표시 개수</label>
                <input id="reaction_new_preset_limit" type="number" name="visible_key_limit" class="form-input" min="1" max="12" value="6">
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_preset_sort">정렬</label>
                <input id="reaction_new_preset_sort" type="number" name="sort_order" class="form-input" min="0" max="999999" value="100">
            </div>
            <div class="admin-form-field">
                <label for="reaction_new_preset_description">설명</label>
                <input id="reaction_new_preset_description" type="text" name="description" class="form-input" maxlength="255">
            </div>
            <input type="hidden" name="status" value="active">
            <fieldset class="admin-form-field">
                <legend>리액션 key <span class="sr-required-label">(필수)</span></legend>
                <?php foreach ($reactionDefinitions as $definition) { ?>
                    <label class="admin-check-row">
                        <input type="checkbox" name="reaction_keys[]" value="<?php echo sr_e((string) ($definition['reaction_key'] ?? '')); ?>">
                        <span><?php echo sr_e((string) ($definition['label'] ?? $definition['reaction_key'] ?? '')); ?></span>
                    </label>
                <?php } ?>
            </fieldset>
            <button type="submit" class="btn btn-solid-primary">Preset 추가</button>
        </form>

        <div>
            <?php foreach ($reactionPresets as $preset) { ?>
                <?php
                $presetKey = (string) ($preset['preset_key'] ?? '');
                $selectedKeys = [];
                foreach ((array) ($reactionPresetItems[$presetKey] ?? []) as $item) {
                    $selectedKeys[] = (string) ($item['reaction_key'] ?? '');
                }
                ?>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/reactions')); ?>" class="admin-card card ui-form-theme">
                    <?php echo sr_csrf_field(); ?>
                    <input type="hidden" name="intent" value="save_preset">
                    <input type="hidden" name="id" value="<?php echo sr_e((string) (int) ($preset['id'] ?? 0)); ?>">
                    <h3 class="card-title"><code><?php echo sr_e($presetKey); ?></code></h3>
                    <div class="admin-form-grid">
                        <input type="text" name="label" class="form-input" maxlength="80" value="<?php echo sr_e((string) ($preset['label'] ?? '')); ?>" required>
                        <select name="status" class="form-select">
                            <?php foreach ($presetStatuses as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($preset['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?></option>
                            <?php } ?>
                        </select>
                        <input type="number" name="visible_key_limit" class="form-input" min="1" max="12" value="<?php echo sr_e((string) (int) ($preset['visible_key_limit'] ?? 6)); ?>">
                        <input type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo sr_e((string) (int) ($preset['sort_order'] ?? 100)); ?>">
                    </div>
                    <input type="text" name="description" class="form-input" maxlength="255" value="<?php echo sr_e((string) ($preset['description'] ?? '')); ?>">
                    <fieldset class="admin-form-field">
                        <legend>리액션 key</legend>
                        <?php foreach ($reactionDefinitions as $definition) { ?>
                            <?php $key = (string) ($definition['reaction_key'] ?? ''); ?>
                            <label class="admin-check-row">
                                <input type="checkbox" name="reaction_keys[]" value="<?php echo sr_e($key); ?>"<?php echo in_array($key, $selectedKeys, true) ? ' checked' : ''; ?>>
                                <span><?php echo sr_e((string) ($definition['label'] ?? $key)); ?></span>
                            </label>
                        <?php } ?>
                    </fieldset>
                    <button type="submit" class="btn btn-solid-primary">Preset 저장</button>
                </form>
            <?php } ?>
        </div>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
