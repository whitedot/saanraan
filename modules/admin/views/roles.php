<?php

$adminPageTitle = sr_t('admin::ui.admin.004791bd');
$adminPageSubtitle = sr_t('admin::ui.member.search.admin.411aa70f');
$adminContainerClass = 'admin-page-role-list admin-ui-scope';
$permissionOptions = isset($permissionOptions) && is_array($permissionOptions) ? $permissionOptions : [];
$permissionActions = isset($permissionActions) && is_array($permissionActions) ? $permissionActions : sr_admin_permission_actions();
$permissionFormAction = sr_url('/admin/roles');
$memberSearchUrl = sr_url('/admin/members/search');
$permissionOptionMap = [];
$permissionPickerGroups = [];
foreach ($permissionOptions as $permissionGroupIndex => $permissionGroup) {
    $pickerItems = [];
    foreach ((array) ($permissionGroup['items'] ?? []) as $permissionItem) {
        $path = (string) ($permissionItem['path'] ?? '');
        $label = (string) ($permissionItem['label'] ?? '');
        if ($path === '' || $label === '') {
            continue;
        }

        $permissionOptionMap[$path] = [
            'label' => $label,
            'path' => $path,
            'group_label' => (string) ($permissionGroup['label'] ?? ''),
        ];
        $pickerItems[] = [
            'label' => $label,
            'path' => $path,
        ];
    }

    if ($pickerItems !== []) {
        $permissionPickerGroups[] = [
            'id' => 'group_' . (string) $permissionGroupIndex,
            'label' => (string) ($permissionGroup['label'] ?? ''),
            'items' => $pickerItems,
        ];
    }
}
$permissionPickerJson = json_encode($permissionPickerGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$permissionActionLabels = [];
foreach ($permissionActions as $actionKey) {
    $permissionActionLabels[(string) $actionKey] = sr_admin_code_label((string) $actionKey, 'admin_permission_action');
}
$permissionActionLabelJson = json_encode($permissionActionLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
$roleHelpOpenLabel = sr_t('admin::roles.help.open');
$roleHelpButtonHtml = static function (string $label, string $modalId) use ($roleHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $roleHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$roleHelpBodyHtml = static function (array $bodyKeys): string {
    $html = '';
    foreach ($bodyKeys as $bodyKey) {
        $html .= '<p>' . sr_e(sr_t((string) $bodyKey)) . '</p>';
    }

    return $html;
};
$roleHelp = [
    'add_member' => [
        'id' => 'admin-permission-help-add-member-modal',
        'title' => sr_t('admin::roles.help.add_member.title'),
        'body_html' => $roleHelpBodyHtml([
            'admin::roles.help.add_member.body.1',
            'admin::roles.help.add_member.body.2',
        ]),
    ],
    'edit_member' => [
        'id' => 'admin-permission-help-edit-member-modal',
        'title' => sr_t('admin::roles.help.edit_member.title'),
        'body_html' => $roleHelpBodyHtml([
            'admin::roles.help.edit_member.body.1',
            'admin::roles.help.edit_member.body.2',
        ]),
    ],
    'owner' => [
        'id' => 'admin-permission-help-owner-modal',
        'title' => sr_t('admin::roles.help.owner.title'),
        'body_html' => $roleHelpBodyHtml([
            'admin::roles.help.owner.body.1',
            'admin::roles.help.owner.body.2',
        ]),
    ],
    'permission_group' => [
        'id' => 'admin-permission-help-group-modal',
        'title' => sr_t('admin::roles.help.permission_group.title'),
        'body_html' => $roleHelpBodyHtml([
            'admin::roles.help.permission_group.body.1',
            'admin::roles.help.permission_group.body.2',
        ]),
    ],
    'permission_item' => [
        'id' => 'admin-permission-help-item-modal',
        'title' => sr_t('admin::roles.help.permission_item.title'),
        'body_html' => $roleHelpBodyHtml([
            'admin::roles.help.permission_item.body.1',
            'admin::roles.help.permission_item.body.2',
        ]),
    ],
    'permission_action' => [
        'id' => 'admin-permission-help-action-modal',
        'title' => sr_t('admin::roles.help.permission_action.title'),
        'body_html' => $roleHelpBodyHtml([
            'admin::roles.help.permission_action.body.1',
            'admin::roles.help.permission_action.body.2',
        ]),
    ],
    'selected_permissions' => [
        'id' => 'admin-permission-help-selected-modal',
        'title' => sr_t('admin::roles.help.selected_permissions.title'),
        'body_html' => $roleHelpBodyHtml([
            'admin::roles.help.selected_permissions.body.1',
            'admin::roles.help.selected_permissions.body.2',
        ]),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-card admin-list-card card admin-list-form">
<div class="admin-permission-section-header">
    <div>
        <h2 class="card-title">권한 보유 회원</h2>
        <p class="admin-dashboard-meta">소유자이거나 메뉴별 권한이 추가된 회원만 표시됩니다.</p>
    </div>
    <button type="button" class="btn btn-solid-primary" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-permission-add-modal" data-overlay="#admin-permission-add-modal">권한 추가</button>
</div>
<div class="table-wrapper">
<table class="table">
    <thead class="ui-table-head">
        <tr>
            <th><?php echo sr_e(sr_t('admin::ui.text.4ca2f9ab')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.email.3b7dbc4c')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.e8857c35')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.status.3808960c')); ?></th>
            <th><?php echo sr_e(sr_t('admin::ui.text.4b72a63a')); ?></th>
            <th class="text-end"><?php echo sr_e(sr_t('admin::ui.text.16f64fe4')); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php if ($accounts === []) { ?>
            <tr>
                <td colspan="6" class="admin-empty-state">추가된 관리자 권한이 없습니다.</td>
            </tr>
        <?php } ?>
        <?php foreach ($accounts as $adminAccount) { ?>
            <?php
            $permissionModalId = 'admin-permission-modal-' . (string) $adminAccount['id'];
            $permissionCounts = array_fill_keys($permissionActions, 0);
            foreach ((array) ($adminAccount['permission_keys'] ?? []) as $permissionToken) {
                [$permissionPath, $permissionAction] = sr_admin_parse_permission_token((string) $permissionToken);
                if ($permissionPath !== '' && isset($permissionCounts[$permissionAction])) {
                    $permissionCounts[$permissionAction]++;
                }
            }
            $permissionSummaryParts = [];
            foreach ($permissionCounts as $actionKey => $countValue) {
                if ($countValue > 0) {
                    $permissionSummaryParts[] = sr_admin_code_label((string) $actionKey, 'admin_permission_action') . ' ' . (string) $countValue;
                }
            }
            $permissionSummary = !empty($adminAccount['is_owner'])
                ? sr_admin_code_label('owner', 'role')
                : ($permissionSummaryParts === [] ? sr_t('admin::ui.text.72ea3d64') : implode(', ', $permissionSummaryParts));
            ?>
            <tr>
                <td><?php echo sr_e((string) $adminAccount['account_public_hash']); ?></td>
                <td><?php echo sr_e(sr_admin_member_email_display($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_member_display_name_preview($adminAccount)); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) $adminAccount['status'], 'member_status')); ?></td>
                <td><?php echo sr_e($permissionSummary); ?></td>
                <td class="admin-table-actions-cell">
                    <div class="admin-row-actions">
                        <button type="button" class="btn btn-sm btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($permissionModalId); ?>" data-overlay="#<?php echo sr_e($permissionModalId); ?>">
                            <?php echo sr_e(sr_t('admin::ui.text.5336e811')); ?>
                        </button>
                        <?php if (empty($adminAccount['is_owner'])) { ?>
                            <form method="post" action="<?php echo sr_e($permissionFormAction); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="account_id" value="<?php echo sr_e((string) $adminAccount['id']); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">권한 회수</button>
                            </form>
                        <?php } ?>
                    </div>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</div>

<div id="admin-permission-add-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0 admin-permission-add-modal" role="dialog" tabindex="-1" aria-labelledby="admin-permission-add-modal-label" aria-hidden="true" inert>
    <div class="modal-dialog">
        <form method="post" action="<?php echo sr_e($permissionFormAction); ?>" class="modal-content ui-form-theme" data-admin-permission-form data-admin-permission-add-form>
            <div class="modal-header">
                <h3 id="admin-permission-add-modal-label" class="modal-title">관리자 권한 추가</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#admin-permission-add-modal">
                    <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                </button>
            </div>
            <div class="modal-body" data-admin-permission-picker>
                <?php echo sr_csrf_field(); ?>
                <input type="hidden" name="intent" value="add_permission">
                <input type="hidden" name="account_id" value="" data-admin-permission-account-id>
                <div hidden data-admin-permission-hidden></div>
                <div class="admin-form-row">
                    <?php echo sr_admin_form_label_help_html('admin-permission-add-account-identifier', '회원', $roleHelp['add_member']['id'], $roleHelpOpenLabel, true); ?>
                    <div class="admin-form-field">
                        <div class="admin-lookup-control">
                            <input id="admin-permission-add-account-identifier" type="text" value="" class="form-input" maxlength="80" readonly required data-admin-permission-account-identifier data-admin-permission-selected-member data-overlay-focus placeholder="회원을 검색해 선택하세요.">
                            <button type="button" class="btn btn-solid-light" aria-haspopup="dialog" aria-expanded="false" aria-controls="admin-permission-member-lookup-modal" data-overlay="#admin-permission-member-lookup-modal" data-admin-permission-member-lookup-open data-target="#admin-permission-add-account-identifier">회원 검색</button>
                        </div>
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label admin-form-label-help"><?php echo $roleHelpButtonHtml(sr_admin_code_label('owner', 'role'), $roleHelp['owner']['id']); ?><span><?php echo sr_e(sr_admin_code_label('owner', 'role')); ?></span></span>
                    <div class="admin-form-field">
                        <label class="admin-role-choice admin-form-check form-label" for="admin-permission-add-owner">
                            <input id="admin-permission-add-owner" type="checkbox" name="is_owner" value="1" class="form-checkbox">
                            <span><?php echo sr_e(sr_t('admin::ui.text.7258c171')); ?></span>
                        </label>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="form-label admin-form-label-help"><?php echo $roleHelpButtonHtml('1차', $roleHelp['permission_group']['id']); ?><label for="admin-permission-add-group">1차 <span class="sr-required-label" data-admin-permission-required-label><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label></div>
                    <div class="admin-form-field">
                        <select id="admin-permission-add-group" class="form-select" data-admin-permission-group>
                            <option value="">선택</option>
                            <option value="__all_groups__">전체</option>
                            <?php foreach ($permissionPickerGroups as $pickerGroup) { ?>
                                <option value="<?php echo sr_e((string) $pickerGroup['id']); ?>"><?php echo sr_e((string) $pickerGroup['label']); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <div class="form-label admin-form-label-help"><?php echo $roleHelpButtonHtml('2차', $roleHelp['permission_item']['id']); ?><label for="admin-permission-add-item">2차 <span class="sr-required-label" data-admin-permission-required-label><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></label></div>
                    <div class="admin-form-field">
                        <select id="admin-permission-add-item" class="form-select" data-admin-permission-item disabled>
                            <option value="">1차 선택 후 선택</option>
                        </select>
                    </div>
                </div>
                <div class="admin-form-row">
                    <span class="form-label admin-form-label-help"><?php echo $roleHelpButtonHtml('권한', $roleHelp['permission_action']['id']); ?><span>권한 <span class="sr-required-label" data-admin-permission-required-label><?php echo sr_e(sr_t('admin::ui.required.1f227c67')); ?></span></span></span>
                    <div class="admin-form-field">
                        <fieldset class="admin-permission-picker-actions">
                            <legend class="sr-only">권한</legend>
                            <?php foreach ($permissionActions as $actionKey) { ?>
                                <?php $pickerActionId = 'admin-permission-add-picker-' . (string) $actionKey; ?>
                                <label class="admin-form-check form-label" for="<?php echo sr_e($pickerActionId); ?>">
                                    <input id="<?php echo sr_e($pickerActionId); ?>" type="checkbox" value="<?php echo sr_e((string) $actionKey); ?>" class="form-checkbox" data-admin-permission-action>
                                    <span><?php echo sr_e(sr_admin_code_label((string) $actionKey, 'admin_permission_action')); ?></span>
                                </label>
                            <?php } ?>
                        </fieldset>
                        <p class="admin-form-help"><?php echo sr_e(sr_t('admin::ui.save.member.admin.f97e1d67')); ?></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-permission-add-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                <button type="submit" class="btn btn-solid-primary modal-action" data-admin-permission-submit disabled><?php echo sr_e(sr_t('admin::ui.save.a6e3d7fe')); ?></button>
            </div>
        </form>
    </div>
</div>

<div id="admin-permission-member-lookup-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="admin-permission-member-lookup-modal-label" aria-hidden="true" inert data-admin-return-overlay="#admin-permission-add-modal">
    <div class="modal-dialog admin-lookup-dialog">
        <div class="modal-content ui-form-theme">
            <div class="modal-header">
                <h3 id="admin-permission-member-lookup-modal-label" class="modal-title">회원 검색</h3>
                <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#admin-permission-member-lookup-modal">
                    <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                </button>
            </div>
            <div class="modal-body">
                <form class="admin-lookup-search-form" data-admin-permission-member-search data-search-url="<?php echo sr_e($memberSearchUrl); ?>">
                    <select name="field" class="form-select" aria-label="<?php echo sr_e(sr_t('admin::ui.member.search.cb8a60d7')); ?>" data-admin-permission-member-field>
                        <option value="all"><?php echo sr_e(sr_t('admin::ui.all.a4b69faf')); ?></option>
                        <option value="hash"><?php echo sr_e(sr_t('admin::ui.text.93971787')); ?></option>
                        <option value="email"><?php echo sr_e(sr_t('admin::ui.email.3b7dbc4c')); ?></option>
                        <option value="login_id"><?php echo sr_e(sr_t('admin::ui.login.0cdb28b5')); ?></option>
                        <option value="name"><?php echo sr_e(sr_t('admin::ui.name.253d1510')); ?></option>
                    </select>
                    <input type="text" name="q" maxlength="120" class="form-input" placeholder="<?php echo sr_e(sr_t('admin::ui.email.login.name.c26ba637')); ?>" data-admin-permission-member-keyword data-overlay-focus>
                    <button type="submit" class="btn btn-solid-primary" data-admin-permission-member-search-button><?php echo sr_e(sr_t('admin::ui.search.4b8d541e')); ?></button>
                </form>
                <div class="admin-lookup-results" data-admin-permission-member-results>
                    <p class="admin-empty-state admin-lookup-empty"><?php echo sr_e(sr_t('admin::ui.search.search.member.3f9d9039')); ?></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-solid-primary modal-action" data-overlay="#admin-permission-add-modal" data-admin-permission-return>권한 추가로 돌아가기</button>
                <button type="button" class="btn btn-solid-light modal-action" data-overlay="#admin-permission-member-lookup-modal"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
            </div>
        </div>
    </div>
</div>

<?php foreach ($accounts as $adminAccount) { ?>
    <?php
    $permissionModalId = 'admin-permission-modal-' . (string) $adminAccount['id'];
    $selectedPermissionMap = array_fill_keys((array) ($adminAccount['permission_keys'] ?? []), true);
    ?>
    <div id="<?php echo sr_e($permissionModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($permissionModalId); ?>-label">
        <div class="modal-dialog modal-dialog-lg admin-permission-modal-dialog">
            <div class="modal-content">
                <form method="post" action="<?php echo sr_e($permissionFormAction); ?>" class="admin-form ui-form-theme" data-admin-permission-form>
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($permissionModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_t('admin::ui.admin.bedced78')); ?></h3>
                        <button type="button" class="modal-close" aria-label="<?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?>" data-overlay="#<?php echo sr_e($permissionModalId); ?>">
                            <?php echo sr_material_icon_html('close', '', sr_t('admin::ui.close.1e8c1020')); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="account_id" value="<?php echo sr_e((string) $adminAccount['id']); ?>">
                        <div class="admin-form-row">
                            <span class="form-label admin-form-label-help"><?php echo $roleHelpButtonHtml(sr_t('admin::ui.member.e335b899'), $roleHelp['edit_member']['id']); ?><span><?php echo sr_e(sr_t('admin::ui.member.e335b899')); ?></span></span>
                            <div class="admin-form-field">
                                <strong><?php echo sr_e((string) $adminAccount['account_public_hash']); ?></strong><br>
                                <?php echo sr_e(sr_admin_member_email_display($adminAccount)); ?> · <?php echo sr_e(sr_admin_member_display_name_preview($adminAccount)); ?>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <span class="form-label admin-form-label-help"><?php echo $roleHelpButtonHtml(sr_admin_code_label('owner', 'role'), $roleHelp['owner']['id']); ?><span><?php echo sr_e(sr_admin_code_label('owner', 'role')); ?></span></span>
                            <div class="admin-form-field">
                                <label class="admin-role-choice admin-form-check form-label" for="<?php echo sr_e($permissionModalId); ?>-owner">
                                    <input id="<?php echo sr_e($permissionModalId); ?>-owner" type="checkbox" name="is_owner" value="1" class="form-checkbox"<?php echo !empty($adminAccount['is_owner']) ? ' checked' : ''; ?> data-overlay-focus>
                                    <span><?php echo sr_e(sr_t('admin::ui.text.7258c171')); ?></span>
                                </label>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <span class="form-label admin-form-label-help"><?php echo $roleHelpButtonHtml(sr_t('admin::ui.text.4b72a63a'), $roleHelp['selected_permissions']['id']); ?><span><?php echo sr_e(sr_t('admin::ui.text.4b72a63a')); ?></span></span>
                            <div class="admin-form-field">
                                <div class="admin-permission-picker" data-admin-permission-picker>
                                    <div class="admin-permission-picker-grid">
                                        <div>
                                            <label class="admin-filter-label" for="<?php echo sr_e($permissionModalId); ?>-group">1차</label>
                                            <select id="<?php echo sr_e($permissionModalId); ?>-group" class="form-select form-control-full" data-admin-permission-group>
                                                <option value="">선택</option>
                                                <option value="__all_groups__">전체</option>
                                                <?php foreach ($permissionPickerGroups as $pickerGroup) { ?>
                                                    <option value="<?php echo sr_e((string) $pickerGroup['id']); ?>"><?php echo sr_e((string) $pickerGroup['label']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="admin-filter-label" for="<?php echo sr_e($permissionModalId); ?>-item">2차</label>
                                            <select id="<?php echo sr_e($permissionModalId); ?>-item" class="form-select form-control-full" data-admin-permission-item disabled>
                                                <option value="">1차 선택 후 선택</option>
                                            </select>
                                        </div>
                                        <fieldset class="admin-permission-picker-actions">
                                            <legend class="admin-filter-label">권한</legend>
                                            <?php foreach ($permissionActions as $actionKey) { ?>
                                                <?php $pickerActionId = $permissionModalId . '-picker-' . (string) $actionKey; ?>
                                                <label class="admin-form-check form-label" for="<?php echo sr_e($pickerActionId); ?>">
                                                    <input id="<?php echo sr_e($pickerActionId); ?>" type="checkbox" value="<?php echo sr_e((string) $actionKey); ?>" class="form-checkbox" data-admin-permission-action>
                                                    <span><?php echo sr_e(sr_admin_code_label((string) $actionKey, 'admin_permission_action')); ?></span>
                                                </label>
                                            <?php } ?>
                                        </fieldset>
                                        <button type="button" class="btn btn-solid-light admin-permission-add" data-admin-permission-add>추가</button>
                                    </div>
                                </div>

                                <div class="admin-permission-selected-list" data-admin-permission-selected>
                                <?php
                                $selectedByPath = [];
                                foreach (array_keys($selectedPermissionMap) as $permissionToken) {
                                    [$selectedPath, $selectedAction] = sr_admin_parse_permission_token((string) $permissionToken);
                                    if ($selectedPath !== '' && $selectedAction !== '') {
                                        $selectedByPath[$selectedPath][$selectedAction] = true;
                                    }
                                }
                                ?>
                                <?php if ($selectedByPath === []) { ?>
                                    <p class="admin-empty-state admin-permission-empty" data-admin-permission-empty>선택된 메뉴 권한이 없습니다.</p>
                                <?php } ?>
                                <?php foreach ($selectedByPath as $selectedPath => $selectedActions) { ?>
                                    <?php $selectedOption = $permissionOptionMap[$selectedPath] ?? ['label' => $selectedPath, 'path' => $selectedPath, 'group_label' => '']; ?>
                                    <div class="admin-permission-selected-row" data-admin-permission-row data-path="<?php echo sr_e($selectedPath); ?>" data-label="<?php echo sr_e((string) ($selectedOption['label'] ?? $selectedPath)); ?>">
                                        <div class="admin-permission-selected-title">
                                            <strong><?php echo sr_e((string) ($selectedOption['label'] ?? $selectedPath)); ?></strong>
                                            <span><?php echo sr_e($selectedPath); ?></span>
                                        </div>
                                        <div class="admin-permission-selected-actions">
                                            <?php foreach ($permissionActions as $actionKey) { ?>
                                                <?php
                                                $permissionToken = sr_admin_permission_token($selectedPath, (string) $actionKey);
                                                $permissionInputId = $permissionModalId . '-permission-' . preg_replace('/[^a-z0-9_-]+/', '-', strtolower($permissionToken));
                                                ?>
                                                <label class="admin-form-check form-label" for="<?php echo sr_e($permissionInputId); ?>">
                                                    <input id="<?php echo sr_e($permissionInputId); ?>" type="checkbox" name="permission_keys[]" value="<?php echo sr_e($permissionToken); ?>" class="form-checkbox"<?php echo isset($selectedActions[$actionKey]) ? ' checked' : ''; ?>>
                                                    <span><?php echo sr_e(sr_admin_code_label((string) $actionKey, 'admin_permission_action')); ?></span>
                                                </label>
                                            <?php } ?>
                                            <button type="button" class="btn btn-sm btn-solid-light" data-admin-permission-remove>삭제</button>
                                        </div>
                                    </div>
                                <?php } ?>
                                </div>
                                <p class="admin-form-help"><?php echo sr_e(sr_t('admin::ui.save.member.admin.f97e1d67')); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-solid-light modal-action" data-overlay="#<?php echo sr_e($permissionModalId); ?>"><?php echo sr_e(sr_t('admin::ui.close.1e8c1020')); ?></button>
                        <button type="submit" class="btn btn-solid-primary modal-action"><?php echo sr_e(sr_t('admin::ui.save.a6e3d7fe')); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php } ?>

<?php foreach ($roleHelp as $roleHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $roleHelpModal['id'], (string) $roleHelpModal['title'], (string) $roleHelpModal['body_html']); ?>
<?php } ?>

<script>
(function () {
    var groups = <?php echo $permissionPickerJson ?: '[]'; ?>;
    var actionLabels = <?php echo $permissionActionLabelJson ?: '{}'; ?>;
    var actions = <?php echo json_encode(array_values($permissionActions), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;
    var allGroupsValue = '__all_groups__';
    var allItemsValue = '__all__';

    function option(text, value) {
        var item = document.createElement('option');
        item.textContent = text;
        item.value = value;
        return item;
    }

    function findGroup(groupId) {
        for (var i = 0; i < groups.length; i++) {
            if (groups[i].id === groupId) {
                return groups[i];
            }
        }
        return null;
    }

    function selectedPermissionItems(form) {
        var groupSelect = form ? form.querySelector('[data-admin-permission-group]') : null;
        var itemSelect = form ? form.querySelector('[data-admin-permission-item]') : null;
        if (!groupSelect || !itemSelect || itemSelect.value === '') {
            return [];
        }

        if (groupSelect.value === allGroupsValue) {
            var allItems = [];
            groups.forEach(function (group) {
                if (Array.isArray(group.items)) {
                    allItems = allItems.concat(group.items);
                }
            });
            if (itemSelect.value === allItemsValue) {
                return allItems;
            }
            return allItems.filter(function (item) {
                return item.path === itemSelect.value;
            });
        }

        var group = findGroup(groupSelect.value);
        if (!group) {
            return [];
        }

        if (itemSelect.value === allItemsValue) {
            return Array.isArray(group.items) ? group.items : [];
        }

        for (var i = 0; i < group.items.length; i++) {
            if (group.items[i].path === itemSelect.value) {
                return [group.items[i]];
            }
        }

        return [];
    }

    function permissionToken(path, action) {
        return path + '|' + action;
    }

    function safeId(value) {
        return String(value).toLowerCase().replace(/[^a-z0-9_-]+/g, '-');
    }

    function cssEscape(value) {
        if (window.CSS && typeof window.CSS.escape === 'function') {
            return window.CSS.escape(value);
        }

        return String(value).replace(/["\\]/g, '\\$&');
    }

    function ensureEmptyState(list) {
        var empty = list.querySelector('[data-admin-permission-empty]');
        var rows = list.querySelectorAll('[data-admin-permission-row]');
        if (rows.length === 0) {
            if (!empty) {
                empty = document.createElement('p');
                empty.className = 'admin-empty-state admin-permission-empty';
                empty.setAttribute('data-admin-permission-empty', '');
                empty.textContent = '선택된 메뉴 권한이 없습니다.';
                list.appendChild(empty);
            }
        } else if (empty) {
            empty.remove();
        }
    }

    function createActionCheck(form, modalId, path, action, checked) {
        var inputId = modalId + '-permission-' + safeId(permissionToken(path, action));
        var label = document.createElement('label');
        label.className = 'admin-form-check form-label';
        label.setAttribute('for', inputId);

        var input = document.createElement('input');
        input.id = inputId;
        input.type = 'checkbox';
        input.name = 'permission_keys[]';
        input.value = permissionToken(path, action);
        input.className = 'form-checkbox';
        input.checked = checked;

        var span = document.createElement('span');
        span.textContent = actionLabels[action] || action;

        label.appendChild(input);
        label.appendChild(span);
        return label;
    }

    function createRow(form, path, labelText) {
        var list = form.querySelector('[data-admin-permission-selected]');
        var modal = form.closest('.modal-overlay');
        var modalId = modal ? modal.id : 'admin-permission';
        var row = document.createElement('div');
        row.className = 'admin-permission-selected-row';
        row.setAttribute('data-admin-permission-row', '');
        row.setAttribute('data-path', path);
        row.setAttribute('data-label', labelText);

        var title = document.createElement('div');
        title.className = 'admin-permission-selected-title';
        var strong = document.createElement('strong');
        strong.textContent = labelText;
        var small = document.createElement('span');
        small.textContent = path;
        title.appendChild(strong);
        title.appendChild(small);

        var actionWrap = document.createElement('div');
        actionWrap.className = 'admin-permission-selected-actions';
        actions.forEach(function (action) {
            actionWrap.appendChild(createActionCheck(form, modalId, path, action, false));
        });

        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn btn-sm btn-solid-light';
        remove.setAttribute('data-admin-permission-remove', '');
        remove.textContent = '삭제';
        actionWrap.appendChild(remove);

        row.appendChild(title);
        row.appendChild(actionWrap);
        list.appendChild(row);
        ensureEmptyState(list);
        return row;
    }

    function updateAddSubmit(form) {
        var submit = form.querySelector('[data-admin-permission-submit]');
        var accountInput = form.querySelector('[data-admin-permission-account-id]');
        if (submit && accountInput) {
            submit.disabled = accountInput.value === '' || !addFormHasGrantTarget(form);
        }
        syncAddRequiredIndicators(form);
    }

    function syncAddRequiredIndicators(form) {
        var ownerInput = form ? form.querySelector('input[name="is_owner"]') : null;
        var ownerSelected = !!(ownerInput && ownerInput.checked);
        if (!form) {
            return;
        }

        form.querySelectorAll('[data-admin-permission-required-label]').forEach(function (label) {
            label.hidden = ownerSelected;
        });
    }

    function addFormHasGrantTarget(form) {
        var ownerInput = form ? form.querySelector('input[name="is_owner"]') : null;
        if (ownerInput && ownerInput.checked) {
            return true;
        }

        if (!form) {
            return false;
        }

        return selectedPermissionItems(form).length > 0
            && form.querySelectorAll('[data-admin-permission-action]:checked').length > 0;
    }

    function clearAddFormValidity(form) {
        if (!form) {
            return;
        }

        var groupSelect = form.querySelector('[data-admin-permission-group]');
        var itemSelect = form.querySelector('[data-admin-permission-item]');
        var firstAction = form.querySelector('[data-admin-permission-action]');
        [groupSelect, itemSelect, firstAction].forEach(function (control) {
            if (control && typeof control.setCustomValidity === 'function') {
                control.setCustomValidity('');
            }
        });
    }

    function validateAddForm(form, report) {
        clearAddFormValidity(form);

        if (!form || addFormHasGrantTarget(form)) {
            return true;
        }

        var groupSelect = form.querySelector('[data-admin-permission-group]');
        var itemSelect = form.querySelector('[data-admin-permission-item]');
        var firstAction = form.querySelector('[data-admin-permission-action]');
        var target = null;

        if (groupSelect && groupSelect.value === '') {
            groupSelect.setCustomValidity('1차 권한 범위를 선택하세요.');
            target = groupSelect;
        } else if (itemSelect && itemSelect.value === '') {
            itemSelect.setCustomValidity('2차 권한 대상을 선택하세요.');
            target = itemSelect;
        } else if (firstAction) {
            firstAction.setCustomValidity('부여할 권한을 하나 이상 선택하세요.');
            target = firstAction;
        }

        if (report && target && typeof target.reportValidity === 'function') {
            target.reportValidity();
        }

        return false;
    }

    function syncAddFormPermissionInputs(form) {
        var hiddenWrap = form.querySelector('[data-admin-permission-hidden]');
        if (!hiddenWrap) {
            return;
        }

        hiddenWrap.innerHTML = '';
        var items = selectedPermissionItems(form);
        if (items.length === 0) {
            return;
        }

        form.querySelectorAll('[data-admin-permission-action]:checked').forEach(function (actionInput) {
            items.forEach(function (item) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'permission_keys[]';
                input.value = permissionToken(item.path, actionInput.value);
                hiddenWrap.appendChild(input);
            });
        });
    }

    function memberSummary(item) {
        var parts = [];
        if (item.account_public_hash) {
            parts.push(item.account_public_hash);
        }
        if (item.email) {
            parts.push(item.email);
        }
        if (item.display_name) {
            parts.push(item.display_name);
        }
        return parts.join(' · ');
    }

    function memberResultsRoot(searchRoot) {
        var modal = searchRoot.closest ? searchRoot.closest('.modal-overlay') : null;
        return modal ? modal.querySelector('[data-admin-permission-member-results]') : searchRoot.querySelector('[data-admin-permission-member-results]');
    }

    function returnToAddModal(searchOverlay) {
        var returnButton = searchOverlay ? searchOverlay.querySelector('[data-admin-permission-return]') : null;
        if (returnButton) {
            returnButton.click();
        }
    }

    function renderMemberResults(searchRoot, items) {
        var results = memberResultsRoot(searchRoot);
        if (!results) {
            return;
        }

        results.innerHTML = '';
        if (!Array.isArray(items) || items.length === 0) {
            var empty = document.createElement('p');
            empty.className = 'admin-empty-state admin-permission-empty';
            empty.textContent = '검색 결과가 없습니다.';
            results.appendChild(empty);
            return;
        }

        var list = document.createElement('div');
        list.className = 'admin-lookup-results-list';
        items.forEach(function (item) {
            var summary = memberSummary(item) || (item.account_public_hash || ('#' + item.id));
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'admin-lookup-result-button';
            button.setAttribute('data-admin-permission-member-pick', '');
            button.setAttribute('data-account-id', item.id || '');
            button.setAttribute('data-account-label', summary);
            button.setAttribute('data-account-identifier', item.account_public_hash || '');

            var title = document.createElement('strong');
            title.textContent = item.display_name || item.account_public_hash || ('#' + item.id);
            button.appendChild(title);

            var meta = document.createElement('div');
            meta.className = 'admin-lookup-result-meta';
            [item.email || '', item.status || '', item.account_public_hash || ''].forEach(function (value) {
                if (value === '') {
                    return;
                }
                var span = document.createElement('span');
                span.textContent = value;
                meta.appendChild(span);
            });
            button.appendChild(meta);

            list.appendChild(button);
        });
        results.appendChild(list);
    }

    function runMemberSearch(searchRoot) {
        var url = searchRoot.getAttribute('data-search-url') || '';
        var field = searchRoot.querySelector('[data-admin-permission-member-field]');
        var keyword = searchRoot.querySelector('[data-admin-permission-member-keyword]');
        var results = memberResultsRoot(searchRoot);
        if (url === '' || !field || !keyword || !results) {
            return;
        }

        results.innerHTML = '<p class="admin-empty-state admin-permission-empty">검색 중입니다.</p>';
        var params = new URLSearchParams();
        params.set('field', field.value || 'all');
        params.set('q', keyword.value || '');
        params.set('limit', '10');

        fetch(url + '?' + params.toString(), {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.ok ? response.json() : {items: []};
        }).then(function (payload) {
            renderMemberResults(searchRoot, payload.items || []);
        }).catch(function () {
            results.innerHTML = '<p class="admin-empty-state admin-permission-empty">회원 검색 중 오류가 발생했습니다.</p>';
        });
    }

    document.addEventListener('submit', function (event) {
        var searchForm = event.target.closest && event.target.closest('[data-admin-permission-member-search]');
        if (!searchForm) {
            var addForm = event.target.closest && event.target.closest('[data-admin-permission-add-form]');
            if (addForm) {
                syncAddFormPermissionInputs(addForm);
                if (!validateAddForm(addForm, true)) {
                    event.preventDefault();
                    updateAddSubmit(addForm);
                }
            }
            return;
        }

        event.preventDefault();
        runMemberSearch(searchForm);
    });

    document.addEventListener('change', function (event) {
        var groupSelect = event.target.closest && event.target.closest('[data-admin-permission-group]');
        if (!groupSelect) {
            return;
        }

        var form = groupSelect.closest('[data-admin-permission-form]');
        var itemSelect = form ? form.querySelector('[data-admin-permission-item]') : null;
        if (!itemSelect) {
            return;
        }

        itemSelect.innerHTML = '';
        if (groupSelect.value === allGroupsValue) {
            itemSelect.appendChild(option('전체', allItemsValue));
            itemSelect.disabled = false;
            if (form && form.matches('[data-admin-permission-add-form]')) {
                validateAddForm(form, false);
                updateAddSubmit(form);
            }
            return;
        }

        var group = findGroup(groupSelect.value);
        if (!group) {
            itemSelect.appendChild(option('1차 선택 후 선택', ''));
            itemSelect.disabled = true;
            if (form && form.matches('[data-admin-permission-add-form]')) {
                validateAddForm(form, false);
                updateAddSubmit(form);
            }
            return;
        }

        itemSelect.appendChild(option('선택', ''));
        itemSelect.appendChild(option('전체', allItemsValue));
        group.items.forEach(function (item) {
            itemSelect.appendChild(option(item.label, item.path));
        });
        itemSelect.disabled = false;
        if (form && form.matches('[data-admin-permission-add-form]')) {
            validateAddForm(form, false);
            updateAddSubmit(form);
        }
    });

    document.addEventListener('change', function (event) {
        var control = event.target.closest && event.target.closest('[data-admin-permission-item], [data-admin-permission-action], input[name="is_owner"]');
        if (!control) {
            return;
        }

        var form = control.closest('[data-admin-permission-add-form]');
        if (!form) {
            return;
        }

        validateAddForm(form, false);
        updateAddSubmit(form);
    });

    document.addEventListener('click', function (event) {
        var memberSearchButton = event.target.closest && event.target.closest('[data-admin-permission-member-search-button]');
        if (memberSearchButton) {
            event.preventDefault();
            var searchRoot = memberSearchButton.closest('[data-admin-permission-member-search]');
            if (searchRoot) {
                runMemberSearch(searchRoot);
            }
            return;
        }

        var memberPickButton = event.target.closest && event.target.closest('[data-admin-permission-member-pick]');
        if (memberPickButton) {
            var pickForm = document.querySelector('[data-admin-permission-add-form]');
            var accountInput = pickForm ? pickForm.querySelector('[data-admin-permission-account-id]') : null;
            var accountIdentifier = pickForm ? pickForm.querySelector('[data-admin-permission-account-identifier]') : null;
            if (accountInput) {
                accountInput.value = memberPickButton.getAttribute('data-account-id') || '';
            }
            if (accountIdentifier) {
                accountIdentifier.value = memberPickButton.getAttribute('data-account-identifier') || memberPickButton.getAttribute('data-account-label') || '';
                accountIdentifier.dispatchEvent(new Event('change', { bubbles: true }));
            }
            if (pickForm) {
                validateAddForm(pickForm, false);
                updateAddSubmit(pickForm);
            }
            returnToAddModal(memberPickButton.closest('.modal-overlay'));
            return;
        }

        var memberLookupOpen = event.target.closest && event.target.closest('[data-admin-permission-member-lookup-open]');
        if (memberLookupOpen) {
            var memberTarget = document.querySelector(memberLookupOpen.getAttribute('data-target') || '');
            var memberModal = document.querySelector(memberLookupOpen.getAttribute('data-overlay') || '');
            if (memberTarget && memberModal) {
                var memberQuery = memberModal.querySelector('[data-admin-permission-member-keyword]');
                if (memberQuery && memberQuery.value === '') {
                    memberQuery.value = memberTarget.value;
                }
            }
            return;
        }

        var addButton = event.target.closest && event.target.closest('[data-admin-permission-add]');
        if (addButton) {
            var form = addButton.closest('[data-admin-permission-form]');
            var items = selectedPermissionItems(form);
            if (!form || items.length === 0) {
                return;
            }

            var pickedActions = form.querySelectorAll('[data-admin-permission-action]:checked');
            if (pickedActions.length === 0) {
                return;
            }

            items.forEach(function (item) {
                var row = form.querySelector('[data-admin-permission-row][data-path="' + cssEscape(item.path) + '"]') || createRow(form, item.path, item.label);
                pickedActions.forEach(function (actionInput) {
                    var target = row.querySelector('input[value="' + cssEscape(permissionToken(item.path, actionInput.value)) + '"]');
                    if (target) {
                        target.checked = true;
                    }
                });
            });
            pickedActions.forEach(function (actionInput) {
                actionInput.checked = false;
            });
            return;
        }

        var removeButton = event.target.closest && event.target.closest('[data-admin-permission-remove]');
        if (removeButton) {
            var selectedList = removeButton.closest('[data-admin-permission-selected]');
            var selectedRow = removeButton.closest('[data-admin-permission-row]');
            if (selectedRow) {
                selectedRow.remove();
            }
            if (selectedList) {
                ensureEmptyState(selectedList);
            }
        }
    });

    document.addEventListener('keydown', function (event) {
        var keyword = event.target.closest && event.target.closest('[data-admin-permission-member-keyword]');
        if (!keyword || event.key !== 'Enter') {
            return;
        }

        event.preventDefault();
        var searchRoot = keyword.closest('[data-admin-permission-member-search]');
        if (searchRoot) {
            runMemberSearch(searchRoot);
        }
    });

    document.querySelectorAll('[data-admin-permission-add-form]').forEach(function (form) {
        syncAddRequiredIndicators(form);
        updateAddSubmit(form);
    });
})();
</script>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong><?php echo sr_e(sr_t('admin::ui.admin.c6bfc841')); ?></strong>
        <p><?php echo sr_e(sr_t('admin::ui.member.6c9f2a2d')); ?></p>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
