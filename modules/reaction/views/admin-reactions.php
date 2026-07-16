<?php

$reactionAdminPage = isset($reactionAdminPage) && is_string($reactionAdminPage) ? $reactionAdminPage : 'definitions';
$reactionAdminPath = $reactionAdminPage === 'presets' ? '/admin/reactions/presets' : ($reactionAdminPage === 'records' ? '/admin/reactions/records' : '/admin/reactions');
$reactionAdminFormAction = sr_url($reactionAdminPath);
$adminPageTitle = $reactionAdminPage === 'presets' ? '리액션 묶음 관리' : ($reactionAdminPage === 'records' ? '리액션 사용 기록 점검' : '리액션 정의 관리');
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
$reactionMaterialIconNames = function_exists('sr_admin_common_material_icon_names') ? sr_admin_common_material_icon_names() : ['favorite', 'sentiment_satisfied', 'thumb_up', 'mood'];
$reactionStatusClasses = [
    'active' => 'is-success',
    'disabled' => 'is-warning',
];
$reactionTargetStatusClasses = [
    'active' => 'is-success',
    'published' => 'is-success',
    'enabled' => 'is-success',
    'private' => 'is-danger',
    'unknown' => 'is-danger',
    'broken' => 'is-warning',
    'deleted' => 'is-warning',
    'disabled' => 'is-warning',
    'hidden' => 'is-warning',
];
$reactionTargetStatusLabels = [
    'active' => '사용',
    'published' => '공개',
    'enabled' => '사용',
    'private' => '접근 제한',
    'unknown' => '확인 필요',
    'broken' => '대상 확인 실패',
    'deleted' => '삭제됨',
    'disabled' => '중지',
    'hidden' => '숨김',
];
$reactionTargetTypeLabelCache = [];
$reactionTargetTypeLabel = static function (string $targetModule, string $targetType) use ($pdo, &$reactionTargetTypeLabelCache): string {
    $targetKey = $targetModule . '/' . $targetType;
    if (array_key_exists($targetKey, $reactionTargetTypeLabelCache)) {
        return $reactionTargetTypeLabelCache[$targetKey];
    }

    $contract = function_exists('sr_reaction_target_contract')
        ? sr_reaction_target_contract($pdo, $targetModule, $targetType)
        : null;
    $label = is_array($contract) ? trim((string) ($contract['label'] ?? '')) : '';
    if ($label === '') {
        $label = '대상';
    }

    $reactionTargetTypeLabelCache[$targetKey] = $label;
    return $label;
};
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
$reactionHelpOpenLabel = '도움말 보기';
$reactionHelpButtonHtml = static function (string $label, string $modalId) use ($reactionHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $reactionHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$reactionHelp = [
    'definition' => [
        'id' => 'reaction-help-definition',
        'title' => '리액션 기본 정보 도움말',
        'body' => '<p>리액션 식별값은 게시물·댓글의 사용 기록, 리액션 묶음과 알림이 같은 반응을 찾을 때 사용하는 내부 고유값입니다. 만든 뒤에는 바꿀 수 없으므로 용도를 알아볼 수 있게 입력하세요.</p>'
            . '<p>사용 중지하면 공개 화면에서 선택할 수 없고 새 기록도 만들 수 없습니다. 기존 사용 기록은 자동으로 삭제하거나 다른 리액션으로 바꾸지 않으며, 필요하면 별도의 기존 기록 처리 작업을 실행해야 합니다.</p>',
    ],
    'icon' => [
        'id' => 'reaction-help-icon',
        'title' => '리액션 아이콘 도움말',
        'body' => '<p>이모지는 아이콘 내용에 이모지 문자 자체를 입력합니다. Material 아이콘은 시스템 아이콘 이름을 입력하고, 이미지 업로드는 JPG·PNG·WebP 파일을 선택하면 저장 위치가 자동으로 기록됩니다.</p>'
            . '<p>수정할 때 이미지 업로드 방식을 유지하면서 새 파일을 선택하지 않으면 현재 이미지를 그대로 사용합니다. 아이콘 방식과 입력 내용이 맞지 않으면 저장할 수 없습니다.</p>',
    ],
    'display' => [
        'id' => 'reaction-help-display',
        'title' => '표시 정보와 정렬 도움말',
        'body' => '<p>표시명은 공개 버튼과 알림에 사용합니다. 설명은 현재 관리자용 정보이며 기본 공개 버튼에는 표시하지 않습니다. 색상은 메타데이터로 저장되지만 현재 기본 공개 리액션 화면에는 자동 적용하지 않습니다.</p>'
            . '<p>정렬 순서는 관리자 리액션 목록의 순서이며 숫자가 작을수록 먼저 표시됩니다. 리액션 묶음 안의 공개 순서는 묶음을 저장할 때의 선택 목록 순서로 따로 저장되므로, 정의 정렬을 바꾼 뒤 기존 묶음도 같은 순서로 맞추려면 묶음을 다시 저장하세요.</p>',
    ],
    'cleanup' => [
        'id' => 'reaction-help-cleanup',
        'title' => '기존 사용 기록 처리 도움말',
        'body' => '<p>기록 보관을 선택하면 사용 기록을 바꾸지 않습니다. 사용 중지된 리액션은 이미 공개 화면에서 숨겨지므로 ‘공개 UI에서 숨김’과 ‘관리자/통계에만 표시’는 현재 같은 결과이며, 선택한 처리 이름만 감사 로그에 남습니다.</p>'
            . '<p>삭제는 해당 식별값의 모든 사용 기록을 영구 삭제합니다. 병합은 기록을 사용 중인 다른 리액션으로 바꾸며, 같은 회원이 같은 대상에 병합할 리액션을 이미 남겼다면 중복되는 기존 기록을 삭제합니다.</p>'
            . '<p>삭제와 병합에는 삭제 권한과 정확한 확인 문구가 필요합니다. 실행 전 화면에 표시된 전체 기존 기록 수를 확인하세요.</p>',
    ],
    'preset' => [
        'id' => 'reaction-help-preset',
        'title' => '리액션 묶음 도움말',
        'body' => '<p>리액션 묶음은 공개 화면에 함께 보여줄 반응을 모아 둔 구성입니다. 묶음 식별값은 게시판이나 콘텐츠 설정에서 이 구성을 찾을 때 사용하며 만든 뒤에는 바꿀 수 없습니다.</p>'
            . '<p>공개 표시 개수만큼 선택 목록의 앞쪽 리액션을 보여주되 사용 중인 정의만 셉니다. 묶음을 저장할 때 현재 리액션 정의 정렬 순서가 묶음 안의 순서로 저장됩니다. 모든 묶음은 한 회원이 한 대상에 하나만 고를 수 있고 다른 반응을 고르면 기존 선택을 바꿉니다.</p>'
            . '<p>묶음을 사용 중지하면 그 묶음을 지정한 화면은 가능한 경우 사이트 기본 묶음으로 대체합니다. 화면에서 리액션을 완전히 끄려면 해당 게시판이나 콘텐츠 모듈의 리액션 사용 설정도 확인하세요.</p>',
    ],
];
$reactionCreateDefinitionModalOpen = $errors !== [] && $reactionPostedIntent === 'save_definition' && $reactionPostedId < 1;
$reactionCreatePresetModalOpen = $errors !== [] && $reactionPostedIntent === 'save_preset' && $reactionPostedId < 1;
$reactionRecordFilterOpen = array_filter($reactionRecordFilters, static function (mixed $value): bool {
    return (string) $value !== '' && (string) $value !== '0';
}) !== [];
$adminPageTitleUrl = sr_admin_page_title_reset_url($reactionAdminPage === 'records', '/admin/reactions/records');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-local-nav-wrap">
    <div class="admin-summary-stats">
        <?php if ($reactionAdminPage === 'definitions') { ?>
            <span class="admin-summary-meta">정의 <strong><?php echo sr_e((string) count($reactionDefinitions)); ?>개</strong></span>
            <span class="admin-summary-meta">사용 중지 처리 대상 <strong><?php echo sr_e((string) count($disabledDefinitions)); ?>개</strong></span>
        <?php } elseif ($reactionAdminPage === 'presets') { ?>
            <span class="admin-summary-meta">묶음 <strong><?php echo sr_e((string) count($reactionPresets)); ?>개</strong></span>
            <span class="admin-summary-meta">공개 리액션 키 수는 최대 12개입니다.</span>
        <?php } else { ?>
            <span class="admin-summary-meta">전체 사용 기록 <strong><?php echo sr_e(number_format((int) ($reactionRecordPagination['total'] ?? 0))); ?>개</strong></span>
        <?php } ?>
    </div>
</div>

<?php if ($reactionAdminPage === 'records') { ?>
<form method="get" action="<?php echo sr_e($reactionAdminFormAction); ?>" class="filtering-form admin-reaction-record-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $reactionRecordFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields">
            <div class="filtering-field">
                <label for="reaction_record_account_id" class="filtering-label">회원</label>
                <input id="reaction_record_account_id" type="text" name="account_id" class="form-input filtering-input" maxlength="80" autocomplete="off" value="<?php echo (int) ($reactionRecordFilters['account_id'] ?? 0) > 0 ? sr_e(sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), (int) $reactionRecordFilters['account_id'])) : ''; ?>">
            </div>
            <div class="filtering-field filtering-field-fill">
                <label for="reaction_record_key" class="filtering-label">리액션 키</label>
                <input id="reaction_record_key" type="text" name="reaction_key" class="form-input filtering-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input value="<?php echo sr_e((string) ($reactionRecordFilters['reaction_key'] ?? '')); ?>">
            </div>
        </div>
        <div id="reaction_record_detail_filters" class="filtering-body" data-filtering-body<?php echo $reactionRecordFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-fields">
                <div class="filtering-field">
                    <label for="reaction_record_target_module" class="filtering-label">대상 모듈</label>
                    <input id="reaction_record_target_module" type="text" name="target_module" class="form-input filtering-input" maxlength="60" pattern="[a-z][a-z0-9_]*" data-admin-key-input value="<?php echo sr_e((string) ($reactionRecordFilters['target_module'] ?? '')); ?>">
                </div>
                <div class="filtering-field">
                    <label for="reaction_record_target_type" class="filtering-label">대상 유형</label>
                    <input id="reaction_record_target_type" type="text" name="target_type" class="form-input filtering-input" maxlength="60" pattern="[a-z][a-z0-9_]*" data-admin-key-input value="<?php echo sr_e((string) ($reactionRecordFilters['target_type'] ?? '')); ?>">
                </div>
                <div class="filtering-field">
                    <label for="reaction_record_target_id" class="filtering-label">대상 ID</label>
                    <input id="reaction_record_target_id" type="number" name="target_id" class="form-input filtering-input" min="1" value="<?php echo (string) ($reactionRecordFilters['target_id'] ?? '') !== '' ? sr_e((string) $reactionRecordFilters['target_id']) : ''; ?>">
                </div>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $reactionRecordFilterOpen ? 'true' : 'false'; ?>" aria-controls="reaction_record_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>
<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">리액션 사용 기록</h2>
    </div>
    <?php echo sr_admin_pagination_summary_html($reactionRecordPagination); ?>
    <div class="table-wrapper">
        <table class="table table-list admin-reaction-record-table">
            <caption class="sr-only">리액션 사용 기록 최근 목록</caption>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>회원</th>
                    <th>대상</th>
                    <th>대상 상태</th>
                    <th>리액션</th>
                    <th>수정일</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reactionRecords === []) { ?>
                    <tr><td colspan="6" class="admin-empty-state">조회된 리액션 사용 기록이 없습니다.</td></tr>
                <?php } ?>
                <?php foreach ($reactionRecords as $record) { ?>
                    <?php
                    $recordTargetKey = (string) ($record['target_module'] ?? '') . '/' . (string) ($record['target_type'] ?? '') . '/' . (string) ($record['target_id'] ?? '');
                    $recordTarget = isset($reactionRecordTargets[$recordTargetKey]) && is_array($reactionRecordTargets[$recordTargetKey]) ? $reactionRecordTargets[$recordTargetKey] : null;
                    $recordTargetStatus = is_array($recordTarget) ? (string) ($recordTarget['status'] ?? 'broken') : 'unknown';
                    $recordTargetLabel = is_array($recordTarget) && (string) ($recordTarget['label'] ?? '') !== '' ? (string) $recordTarget['label'] : '#' . (string) ($record['target_id'] ?? '');
                    $recordTargetTypeLabel = $reactionTargetTypeLabel((string) ($record['target_module'] ?? ''), (string) ($record['target_type'] ?? ''));
                    $recordReactionLabel = trim((string) ($record['reaction_label'] ?? ''));
                    if ($recordReactionLabel === '') {
                        $recordReactionLabel = '정의 없음';
                    }
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($record['id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($record['account_id'] ?? 0)); ?></td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e($recordTargetTypeLabel); ?> #<?php echo sr_e((string) ($record['target_id'] ?? '')); ?></strong>
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
                            <span class="badge-status <?php echo sr_e((string) ($reactionTargetStatusClasses[$recordTargetStatus] ?? 'is-warning')); ?>"><?php echo sr_e((string) ($reactionTargetStatusLabels[$recordTargetStatus] ?? '확인 필요')); ?></span>
                            <?php if (is_array($recordTarget) && empty($recordTarget['can_write'])) { ?>
                                <br><span class="admin-summary-meta">쓰기 불가</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_e($recordReactionLabel); ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_admin_time_html((string) ($record['updated_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('reaction_target_status', $reactionTargetStatusLabels, [], '대상 상태 설명'); ?>
    <?php echo sr_admin_pagination_html($reactionRecordPagination, '리액션 사용 기록 페이지'); ?>
</section>
<?php } ?>

<?php if ($reactionAdminPage === 'definitions') { ?>
<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">리액션 정의</h2>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $reactionCreateDefinitionModalOpen ? 'true' : 'false'; ?>" aria-controls="reaction-definition-create-modal" data-overlay="#reaction-definition-create-modal"><?php echo sr_material_icon_html('add'); ?>추가</button>
        </div>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-reaction-definition-table">
            <caption class="sr-only">리액션 정의 목록</caption>
            <thead>
                <tr>
                    <th>키</th>
                    <th>표시</th>
                    <th>아이콘</th>
                    <th>상태</th>
                    <th>사용 기록</th>
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
                            <strong><?php echo sr_e((string) ($definition['label'] ?? $definition['reaction_key'] ?? '')); ?></strong>
                            <?php if ((string) ($definition['description'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($definition['description'] ?? '')); ?></span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap">
                            <?php echo sr_reaction_public_icon_html($definition); ?>
                            <br><?php echo sr_e(sr_reaction_icon_type_label((string) ($definition['icon_type'] ?? 'emoji'))); ?>
                            <?php if ((string) ($definition['icon_value'] ?? '') !== '') { ?>
                                <br><code><?php echo sr_e((string) ($definition['icon_value'] ?? '')); ?></code>
                            <?php } ?>
                            <?php if ((string) ($definition['color_hex'] ?? '') !== '') { ?>
                                <br><code><?php echo sr_e((string) ($definition['color_hex'] ?? '')); ?></code>
                            <?php } ?>
                        </td>
                        <?php $definitionStatus = (string) ($definition['status'] ?? ''); ?>
                        <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e((string) ($reactionStatusClasses[$definitionStatus] ?? 'is-warning')); ?>"><?php echo sr_e(sr_admin_code_label($definitionStatus, 'module_status')); ?></span></td>
                        <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($definition['sort_order'] ?? 100)); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="리액션 정의 수정" title="수정" aria-haspopup="dialog" aria-expanded="<?php echo $definitionModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($definitionModalId); ?>" data-overlay="#<?php echo sr_e($definitionModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
    </div>
    <?php echo sr_admin_status_description_list_html('reaction_status', ['active' => '사용', 'disabled' => '중지']); ?>
</section>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">사용 중지 키의 기존 사용 기록 처리</h2>
    </div>
    <p class="admin-summary-meta">사용 중지된 키는 신규 적용/변경이 차단되고 공개 화면에서 숨겨집니다. 기존 사용 기록은 보관하거나, 삭제하거나, 다른 사용 중 리액션 키로 병합할 수 있습니다.</p>
    <?php if ($disabledDefinitions === []) { ?>
        <p class="admin-empty-state">처리할 사용 중지 키 사용 기록이 없습니다.</p>
    <?php } else { ?>
        <div class="table-wrapper">
            <table class="table table-list admin-reaction-cleanup-table">
                <caption class="sr-only">사용 중지 리액션 사용 기록 처리 대상</caption>
                <thead>
                    <tr>
                        <th>키</th>
                        <th>표시명</th>
                        <th>기존 사용 기록</th>
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
                            <td><strong><?php echo sr_e((string) ($definition['label'] ?? $reactionKey)); ?></strong></td>
                            <td class="admin-table-nowrap"><?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?>개</td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <button type="button" class="btn btn-sm btn-outline-danger" aria-haspopup="dialog" aria-expanded="<?php echo $cleanupModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($cleanupModalId); ?>" data-overlay="#<?php echo sr_e($cleanupModalId); ?>">처리</button>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
</section>
<?php } ?>

<?php if ($reactionAdminPage === 'presets') { ?>
<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">리액션 묶음</h2>
        <div class="card-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" aria-haspopup="dialog" aria-expanded="<?php echo $reactionCreatePresetModalOpen ? 'true' : 'false'; ?>" aria-controls="reaction-preset-create-modal" data-overlay="#reaction-preset-create-modal"><?php echo sr_material_icon_html('add'); ?>추가</button>
        </div>
    </div>
    <p class="admin-summary-meta">1차 정책은 단일 선택만 지원합니다. 선택한 리액션 키 순서대로 공개 버튼이 표시됩니다.</p>
    <div class="table-wrapper">
        <table class="table table-list admin-reaction-preset-table">
            <caption class="sr-only">리액션 묶음 목록</caption>
            <thead>
                <tr>
                    <th>키</th>
                    <th>이름</th>
                    <th>상태</th>
                    <th>공개 표시</th>
                    <th>리액션 키</th>
                    <th>정렬</th>
                    <th class="text-end">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($reactionPresets === []) { ?>
                    <tr><td colspan="7" class="admin-empty-state">등록된 리액션 묶음이 없습니다.</td></tr>
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
                            <strong><?php echo sr_e((string) ($preset['label'] ?? $presetKey)); ?></strong>
                            <?php if ((string) ($preset['description'] ?? '') !== '') { ?>
                                <br><span class="admin-summary-meta"><?php echo sr_e((string) ($preset['description'] ?? '')); ?></span>
                            <?php } ?>
                        </td>
                        <?php $presetStatus = (string) ($preset['status'] ?? ''); ?>
                        <td class="admin-table-nowrap"><span class="badge-status <?php echo sr_e((string) ($reactionStatusClasses[$presetStatus] ?? 'is-warning')); ?>"><?php echo sr_e(sr_admin_code_label($presetStatus, 'module_status')); ?></span></td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($preset['visible_key_limit'] ?? 6)); ?>개</td>
                        <td class="admin-table-break">
                            <?php if ($selectedKeys === []) { ?>
                                <span class="admin-summary-meta">없음</span>
                            <?php } else { ?>
                                <?php foreach ($selectedKeys as $selectedIndex => $selectedKey) { ?>
                                    <?php echo $selectedIndex > 0 ? ' ' : ''; ?><code><?php echo sr_e($selectedKey); ?></code>
                                <?php } ?>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap"><?php echo sr_e((string) (int) ($preset['sort_order'] ?? 100)); ?></td>
                        <td class="admin-table-actions-cell">
                            <div class="admin-row-actions">
                                <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" aria-label="리액션 묶음 수정" title="수정" aria-haspopup="dialog" aria-expanded="<?php echo $presetModalOpen ? 'true' : 'false'; ?>" aria-controls="<?php echo sr_e($presetModalId); ?>" data-overlay="#<?php echo sr_e($presetModalId); ?>"><?php echo sr_material_icon_html('edit'); ?></button>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <div class="admin-icon-button-legend" aria-label="아이콘 버튼 설명">
        <span class="admin-icon-button-legend-item"><?php echo sr_material_icon_html('edit'); ?> 수정</span>
    </div>
    <?php echo sr_admin_status_description_list_html('reaction_status', ['active' => '사용', 'disabled' => '중지']); ?>
</section>
<?php } ?>

<?php if ($reactionAdminPage === 'definitions') { ?>
<div id="reaction-definition-create-modal" class="<?php echo sr_e($reactionModalClass($reactionCreateDefinitionModalOpen)); ?>" role="dialog" tabindex="-1" aria-labelledby="reaction-definition-create-modal-title"<?php echo $reactionModalHiddenAttrs($reactionCreateDefinitionModalOpen); ?>>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e($reactionAdminFormAction); ?>" class="modal-content admin-form ui-form-theme" enctype="multipart/form-data">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_definition">
            <input type="hidden" name="status" value="active">
            <div class="modal-header">
                <h3 id="reaction-definition-create-modal-title" class="modal-title">리액션 정의 추가</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#reaction-definition-create-modal"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_key', '리액션 식별값', $reactionHelp['definition']['id'], $reactionHelpOpenLabel, true); ?>
                        <input id="reaction_new_key" type="text" name="reaction_key" class="form-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input required value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('reaction_key', 80)) : ''; ?>">
                        <p class="form-help">영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 사용합니다.</p>
                    </div>
                    <div class="form-field">
                        <label for="reaction_new_label">표시명 <span class="sr-required-label">(필수)</span></label>
                        <input id="reaction_new_label" type="text" name="label" class="form-input" maxlength="80" required value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('label', 80)) : ''; ?>">
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_icon_type', '아이콘 방식', $reactionHelp['icon']['id'], $reactionHelpOpenLabel); ?>
                        <select id="reaction_new_icon_type" name="icon_type" class="form-select">
                            <?php foreach ($iconTypes as $iconType) { ?>
                                <option value="<?php echo sr_e($iconType); ?>"<?php echo $reactionCreateDefinitionModalOpen && sr_post_string('icon_type', 20) === $iconType ? ' selected' : ''; ?>><?php echo sr_e(sr_reaction_icon_type_label($iconType)); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_icon_value', '아이콘 내용', $reactionHelp['icon']['id'], $reactionHelpOpenLabel); ?>
                        <input id="reaction_new_icon_value" type="text" name="icon_value" class="form-input" maxlength="180" list="reaction-material-icon-options" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('icon_value', 180)) : ''; ?>">
                        <p class="form-help">이모지 문자나 시스템 아이콘 이름을 입력합니다. 이미지 방식은 업로드 후 자동으로 채웁니다.</p>
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_icon_image', '아이콘 이미지', $reactionHelp['icon']['id'], $reactionHelpOpenLabel); ?>
                        <input id="reaction_new_icon_image" type="file" name="icon_image" class="form-input" accept="image/jpeg,image/png,image/webp">
                        <p class="form-help">이미지 업로드 유형에서 사용합니다. JPG, PNG, WebP / 최대 <?php echo sr_e(sr_format_bytes(sr_reaction_icon_upload_max_bytes())); ?> / 512px 이하.</p>
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_color_hex', '색상', $reactionHelp['display']['id'], $reactionHelpOpenLabel); ?>
                        <input id="reaction_new_color_hex" type="text" name="color_hex" class="form-input" maxlength="20" placeholder="#2563eb" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('color_hex', 20)) : ''; ?>">
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_sort', '정렬 순서', $reactionHelp['display']['id'], $reactionHelpOpenLabel); ?>
                        <input id="reaction_new_sort" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('sort_order', 20)) : '100'; ?>">
                    </div>
                </div>
                <div class="form-field">
                    <label for="reaction_new_description">관리용 설명</label>
                    <input id="reaction_new_description" type="text" name="description" class="form-input" maxlength="255" value="<?php echo $reactionCreateDefinitionModalOpen ? sr_e(sr_post_string('description', 255)) : ''; ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#reaction-definition-create-modal"><?php echo sr_e($reactionCloseLabel); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
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
            <form method="post" action="<?php echo sr_e($reactionAdminFormAction); ?>" class="modal-content admin-form ui-form-theme" enctype="multipart/form-data">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="save_definition">
                <input type="hidden" name="id" value="<?php echo sr_e((string) $definitionId); ?>">
                <input type="hidden" name="color_swatch" value="<?php echo sr_e((string) ($definition['color_swatch'] ?? '')); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($definitionModalTitleId); ?>" class="modal-title">리액션 정의 수정 <code><?php echo sr_e((string) ($definition['reaction_key'] ?? '')); ?></code></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#<?php echo sr_e($definitionModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="<?php echo sr_e($definitionModalId); ?>_label">표시명 <span class="sr-required-label">(필수)</span></label>
                            <input id="<?php echo sr_e($definitionModalId); ?>_label" type="text" name="label" class="form-input" maxlength="80" value="<?php echo sr_e((string) ($definition['label'] ?? '')); ?>" required>
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($definitionModalId . '_status', '사용 상태', $reactionHelp['definition']['id'], $reactionHelpOpenLabel); ?>
                            <select id="<?php echo sr_e($definitionModalId); ?>_status" name="status" class="form-select">
                                <?php foreach ($definitionStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($definition['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?></option>
                                <?php } ?>
                            </select>
                            <?php if ((string) ($definition['status'] ?? '') === 'disabled') { ?>
                                <p class="form-help">사용 중지된 키의 기존 사용 기록은 기본적으로 보관됩니다.</p>
                            <?php } ?>
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($definitionModalId . '_icon_type', '아이콘 방식', $reactionHelp['icon']['id'], $reactionHelpOpenLabel); ?>
                            <select id="<?php echo sr_e($definitionModalId); ?>_icon_type" name="icon_type" class="form-select">
                                <?php foreach ($iconTypes as $iconType) { ?>
                                    <option value="<?php echo sr_e($iconType); ?>"<?php echo (string) ($definition['icon_type'] ?? '') === $iconType ? ' selected' : ''; ?>><?php echo sr_e(sr_reaction_icon_type_label($iconType)); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($definitionModalId . '_icon_value', '아이콘 내용', $reactionHelp['icon']['id'], $reactionHelpOpenLabel); ?>
                            <input id="<?php echo sr_e($definitionModalId); ?>_icon_value" type="text" name="icon_value" class="form-input" maxlength="180" list="reaction-material-icon-options" value="<?php echo sr_e((string) ($definition['icon_value'] ?? '')); ?>">
                            <p class="form-help">이미지 업로드 유형에서 새 파일을 선택하지 않으면 현재 이미지 값을 유지합니다.</p>
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($definitionModalId . '_icon_image', '아이콘 이미지', $reactionHelp['icon']['id'], $reactionHelpOpenLabel); ?>
                            <input id="<?php echo sr_e($definitionModalId); ?>_icon_image" type="file" name="icon_image" class="form-input" accept="image/jpeg,image/png,image/webp">
                            <p class="form-help">JPG, PNG, WebP / 최대 <?php echo sr_e(sr_format_bytes(sr_reaction_icon_upload_max_bytes())); ?> / 512px 이하.</p>
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($definitionModalId . '_color_hex', '색상', $reactionHelp['display']['id'], $reactionHelpOpenLabel); ?>
                            <input id="<?php echo sr_e($definitionModalId); ?>_color_hex" type="text" name="color_hex" class="form-input" maxlength="20" value="<?php echo sr_e((string) ($definition['color_hex'] ?? '')); ?>">
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($definitionModalId . '_sort_order', '정렬 순서', $reactionHelp['display']['id'], $reactionHelpOpenLabel); ?>
                            <input id="<?php echo sr_e($definitionModalId); ?>_sort_order" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo sr_e((string) (int) ($definition['sort_order'] ?? 100)); ?>">
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="<?php echo sr_e($definitionModalId); ?>_description">관리용 설명</label>
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
            <form method="post" action="<?php echo sr_e($reactionAdminFormAction); ?>" class="modal-content admin-form ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="cleanup_records">
                <input type="hidden" name="reaction_key" value="<?php echo sr_e($reactionKey); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($cleanupModalTitleId); ?>" class="modal-title">기존 사용 기록 처리 <code><?php echo sr_e($reactionKey); ?></code></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#<?php echo sr_e($cleanupModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <p class="admin-summary-meta">기존 사용 기록 <?php echo sr_e(number_format((int) ($definition['record_count'] ?? 0))); ?>개</p>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html($cleanupModalId . '_policy', '처리 방식', $reactionHelp['cleanup']['id'], $reactionHelpOpenLabel); ?>
                        <select id="<?php echo sr_e($cleanupModalId); ?>_policy" name="cleanup_policy" class="form-select">
                            <option value="keep_public_hidden">보관하고 공개 UI에서 숨김</option>
                            <option value="keep_admin_statistics">보관하고 관리자/통계에만 표시</option>
                            <option value="delete">기존 사용 기록 삭제</option>
                            <option value="merge">다른 리액션 키로 병합</option>
                        </select>
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html($cleanupModalId . '_merge_target', '합칠 리액션', $reactionHelp['cleanup']['id'], $reactionHelpOpenLabel); ?>
                        <select id="<?php echo sr_e($cleanupModalId); ?>_merge_target" name="merge_target_key" class="form-select">
                            <option value="">선택 안 함</option>
                            <?php foreach ($activeDefinitions as $activeDefinition) { ?>
                                <?php $activeKey = (string) ($activeDefinition['reaction_key'] ?? ''); ?>
                                <option value="<?php echo sr_e($activeKey); ?>"><?php echo sr_e((string) ($activeDefinition['label'] ?? $activeKey)); ?> (<?php echo sr_e($activeKey); ?>)</option>
                            <?php } ?>
                        </select>
                        <p class="form-help">병합할 때만 선택합니다. 같은 회원·대상에 중복되는 기록은 하나만 남깁니다.</p>
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html($cleanupModalId . '_confirm', '확인 문구', $reactionHelp['cleanup']['id'], $reactionHelpOpenLabel); ?>
                        <input id="<?php echo sr_e($cleanupModalId); ?>_confirm" type="text" name="confirmation_key" class="form-input" maxlength="80">
                        <p class="form-help">삭제 또는 병합을 실행하려면 <code><?php echo sr_e($reactionKey); ?></code>를 입력하세요.</p>
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
<?php } ?>

<?php if ($reactionAdminPage === 'presets') { ?>
<div id="reaction-preset-create-modal" class="<?php echo sr_e($reactionModalClass($reactionCreatePresetModalOpen)); ?>" role="dialog" tabindex="-1" aria-labelledby="reaction-preset-create-modal-title"<?php echo $reactionModalHiddenAttrs($reactionCreatePresetModalOpen); ?>>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e($reactionAdminFormAction); ?>" class="modal-content admin-form ui-form-theme">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_preset">
            <input type="hidden" name="status" value="active">
            <div class="modal-header">
                <h3 id="reaction-preset-create-modal-title" class="modal-title">리액션 묶음 추가</h3>
                <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#reaction-preset-create-modal"><?php echo sr_material_icon_html('close'); ?></button>
            </div>
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_preset_key', '묶음 식별값', $reactionHelp['preset']['id'], $reactionHelpOpenLabel, true); ?>
                        <input id="reaction_new_preset_key" type="text" name="preset_key" class="form-input" maxlength="80" pattern="[a-z][a-z0-9_]*" data-admin-key-input required value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('preset_key', 80)) : ''; ?>">
                    </div>
                    <div class="form-field">
                        <label for="reaction_new_preset_label">이름 <span class="sr-required-label">(필수)</span></label>
                        <input id="reaction_new_preset_label" type="text" name="label" class="form-input" maxlength="80" required value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('label', 80)) : ''; ?>">
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_preset_limit', '공개 표시 개수', $reactionHelp['preset']['id'], $reactionHelpOpenLabel); ?>
                        <input id="reaction_new_preset_limit" type="number" name="visible_key_limit" class="form-input" min="1" max="12" value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('visible_key_limit', 20)) : '6'; ?>">
                    </div>
                    <div class="form-field">
                        <?php echo sr_admin_form_label_help_html('reaction_new_preset_sort', '묶음 정렬 순서', $reactionHelp['preset']['id'], $reactionHelpOpenLabel); ?>
                        <input id="reaction_new_preset_sort" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('sort_order', 20)) : '100'; ?>">
                    </div>
                </div>
                <div class="form-field">
                    <label for="reaction_new_preset_description">설명</label>
                    <input id="reaction_new_preset_description" type="text" name="description" class="form-input" maxlength="255" value="<?php echo $reactionCreatePresetModalOpen ? sr_e(sr_post_string('description', 255)) : ''; ?>">
                </div>
                <fieldset class="form-field">
                    <legend><?php echo $reactionHelpButtonHtml('공개할 리액션', $reactionHelp['preset']['id']); ?> 공개할 리액션 <span class="sr-required-label">(필수)</span></legend>
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
                <button type="submit" class="btn btn-solid-primary modal-action">저장</button>
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
            <form method="post" action="<?php echo sr_e($reactionAdminFormAction); ?>" class="modal-content admin-form ui-form-theme">
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="save_preset">
                <input type="hidden" name="id" value="<?php echo sr_e((string) $presetId); ?>">
                <div class="modal-header">
                    <h3 id="<?php echo sr_e($presetModalTitleId); ?>" class="modal-title">리액션 묶음 수정 <code><?php echo sr_e($presetKey); ?></code></h3>
                    <button type="button" class="btn btn-icon btn-ghost-light modal-close" aria-label="<?php echo sr_e($reactionCloseLabel); ?>" data-overlay="#<?php echo sr_e($presetModalId); ?>"><?php echo sr_material_icon_html('close'); ?></button>
                </div>
                <div class="modal-body">
                    <div class="form-grid">
                        <div class="form-field">
                            <label for="<?php echo sr_e($presetModalId); ?>_label">이름 <span class="sr-required-label">(필수)</span></label>
                            <input id="<?php echo sr_e($presetModalId); ?>_label" type="text" name="label" class="form-input" maxlength="80" value="<?php echo sr_e((string) ($preset['label'] ?? '')); ?>" required>
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($presetModalId . '_status', '사용 상태', $reactionHelp['preset']['id'], $reactionHelpOpenLabel); ?>
                            <select id="<?php echo sr_e($presetModalId); ?>_status" name="status" class="form-select">
                                <?php foreach ($presetStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($preset['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($presetModalId . '_limit', '공개 표시 개수', $reactionHelp['preset']['id'], $reactionHelpOpenLabel); ?>
                            <input id="<?php echo sr_e($presetModalId); ?>_limit" type="number" name="visible_key_limit" class="form-input" min="1" max="12" value="<?php echo sr_e((string) (int) ($preset['visible_key_limit'] ?? 6)); ?>">
                        </div>
                        <div class="form-field">
                            <?php echo sr_admin_form_label_help_html($presetModalId . '_sort_order', '묶음 정렬 순서', $reactionHelp['preset']['id'], $reactionHelpOpenLabel); ?>
                            <input id="<?php echo sr_e($presetModalId); ?>_sort_order" type="number" name="sort_order" class="form-input" min="0" max="999999" value="<?php echo sr_e((string) (int) ($preset['sort_order'] ?? 100)); ?>">
                        </div>
                    </div>
                    <div class="form-field">
                        <label for="<?php echo sr_e($presetModalId); ?>_description">설명</label>
                        <input id="<?php echo sr_e($presetModalId); ?>_description" type="text" name="description" class="form-input" maxlength="255" value="<?php echo sr_e((string) ($preset['description'] ?? '')); ?>">
                    </div>
                    <fieldset class="form-field">
                        <legend><?php echo $reactionHelpButtonHtml('공개할 리액션', $reactionHelp['preset']['id']); ?> 공개할 리액션 <span class="sr-required-label">(필수)</span></legend>
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
<?php } ?>

<?php foreach ($reactionHelp as $reactionHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $reactionHelpModal['id'], (string) $reactionHelpModal['title'], (string) $reactionHelpModal['body']); ?>
<?php } ?>

<datalist id="reaction-material-icon-options">
    <?php foreach ($reactionMaterialIconNames as $materialIconName) { ?>
        <option value="<?php echo sr_e((string) $materialIconName); ?>"></option>
    <?php } ?>
</datalist>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
