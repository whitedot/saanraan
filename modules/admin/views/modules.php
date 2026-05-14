<?php

$adminPageTitle = '모듈 관리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="ui-notice">
    <span class="ui-notice-icon" aria-hidden="true">i</span>
    <div class="ui-notice-copy">
        <strong>모듈 상태 안내</strong>
        <p>설치 차단 상태는 메타데이터나 계약 오류가 있는 미설치 모듈을 뜻합니다.</p>
    </div>
</div>

<nav class="tab-nav-bordered admin-tabs" data-admin-tabs>
    <button type="button" class="tab-trigger-underline active" data-admin-tab-target="installed">설치된 모듈</button>
    <button type="button" class="tab-trigger-underline" data-admin-tab-target="installable">설치 가능한 모듈</button>
    <button type="button" class="tab-trigger-underline" data-admin-tab-target="upload">zip 업로드</button>
    <button type="button" class="tab-trigger-underline" data-admin-tab-target="settings">고급 설정</button>
</nav>

<section id="module-tab-installed" class="member-table-card admin-member-list-form" data-admin-tab-panel="installed">
<div class="card-header">
    <h2 class="card-title">설치된 모듈</h2>
</div>
<div class="table-wrapper">
<table class="table">
    <thead class="ui-table-head">
        <tr>
            <th>키</th>
            <th>이름</th>
            <th>유형</th>
            <th>설치 버전</th>
            <th>코드 버전</th>
            <th>수명주기</th>
            <th>업데이트</th>
            <th>Saanraan 최소</th>
            <th>Saanraan 검증</th>
            <th>계약</th>
            <th>상태</th>
            <th>기본 포함</th>
            <th>설치일</th>
            <th>설명</th>
            <th class="text-end">변경</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($modules as $module) { ?>
            <?php $isRequired = in_array((string) $module['module_key'], $requiredModules, true); ?>
            <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
            <tr>
                <td><?php echo sr_e((string) $module['module_key']); ?></td>
                <td><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></td>
                <td><?php echo sr_e(sr_admin_code_label((string) ($module['code_type'] ?? 'module'), 'module_type')); ?></td>
                <td><?php echo sr_e((string) $module['version']); ?></td>
                <td><?php echo sr_e((string) ($module['code_version'] !== '' ? $module['code_version'] : '-')); ?></td>
                <td>
                    <?php echo sr_e((string) ($module['lifecycle_label'] ?? '상태 확인 필요')); ?>
                    <br>
                    <?php echo sr_e((string) ($module['lifecycle_action'] ?? '모듈 상태 확인')); ?>
                </td>
                <td>
                    <?php if ((int) ($module['pending_update_count'] ?? 0) > 0) { ?>
                        <a href="<?php echo sr_e(sr_url('/admin/updates')); ?>"><?php echo sr_e((string) $module['pending_update_count']); ?>개 SQL 대기</a>
                    <?php } elseif (($module['version_state'] ?? '') === 'code_newer') { ?>
                        <?php if ($canManageModuleSources && $moduleSourcesEnabled) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="sync_module_version">
                                <input type="hidden" name="module_key" value="<?php echo sr_e((string) $module['module_key']); ?>">
                                <label>
                                    <span class="sr-only">소유자 비밀번호</span>
                                    <input type="password" name="owner_password" class="form-input" autocomplete="current-password" required placeholder="소유자 비밀번호">
                                </label>
                                <button type="submit" class="btn btn-sm btn-surface-default-soft">파일 업데이트 반영</button>
                            </form>
                        <?php } elseif ($canManageModuleSources) { ?>
                            소스 반영 비활성화
                        <?php } else { ?>
                            소유자 확인 필요
                        <?php } ?>
                    <?php } elseif (($module['version_state'] ?? '') === 'code_older') { ?>
                        파일 재배치 필요
                    <?php } else { ?>
                        -
                    <?php } ?>
                </td>
                <td><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></td>
                <td><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></td>
                <td>
                    <?php echo sr_e((string) (($module['saanraan_module_contract'] ?? '') !== '' ? $module['saanraan_module_contract'] : '-')); ?>
                    <?php if ($moduleErrors !== []) { ?>
                        <br>
                        <strong>메타데이터/계약 오류</strong>
                        <ul>
                            <?php foreach ($moduleErrors as $moduleError) { ?>
                                <li><?php echo sr_e((string) $moduleError); ?></li>
                            <?php } ?>
                        </ul>
                    <?php } ?>
                </td>
                <td>
                    <?php echo sr_e(sr_admin_code_label((string) $module['status'], 'module_status')); ?>
                    <?php if ((string) $module['status'] === 'enabled' && $moduleErrors !== []) { ?>
                        <br>런타임 계약 파일 비활성
                    <?php } ?>
                </td>
                <td><?php echo !empty($module['is_bundled']) ? '예' : '아니오'; ?></td>
                <td><?php echo sr_e((string) ($module['installed_at'] ?? '')); ?></td>
                <td><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></td>
                <td class="member-cell-manage">
                    <div class="member-manage">
                    <?php if (in_array((string) $module['status'], ['failed', 'installing'], true)) { ?>
                        <details class="member-edit-details">
                            <summary class="btn btn-sm btn-surface-default-soft">재설치</summary>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="member-edit-form">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="install">
                                <input type="hidden" name="module_key" value="<?php echo sr_e((string) $module['module_key']); ?>">
                                <label>
                                    <span>설치 후 상태</span>
                                    <select name="status">
                                        <?php foreach ($allowedInstallStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                                <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <button type="submit" class="btn btn-sm btn-solid-primary">재설치</button>
                            </form>
                        </details>
                    <?php } else { ?>
                        <details class="member-edit-details">
                            <summary class="btn btn-sm btn-surface-default-soft"<?php echo $isRequired ? ' aria-disabled="true"' : ''; ?>>상태 변경</summary>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="member-edit-form">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="status">
                                <input type="hidden" name="module_key" value="<?php echo sr_e((string) $module['module_key']); ?>">
                                <label>
                                    <span>상태</span>
                                    <select name="status"<?php echo $isRequired ? ' disabled' : ''; ?>>
                                        <?php foreach ($allowedStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $module['status'] === $status ? ' selected' : ''; ?>>
                                                <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </label>
                                <button type="submit" class="btn btn-sm btn-solid-primary"<?php echo $isRequired ? ' disabled' : ''; ?>>저장</button>
                            </form>
                        </details>
                    <?php } ?>
                    </div>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>
</div>
</section>

<div data-admin-tab-panel="upload" hidden>
<?php if (!$canManageModuleSources || !$moduleSourcesEnabled || !$moduleUploadAvailable) { ?>
    <section class="card">
        <h2>모듈 zip 업로드</h2>
        <?php if (!$canManageModuleSources) { ?>
            <p>모듈 파일 업로드는 소유자 권한이 필요합니다.</p>
        <?php } elseif (!$moduleSourcesEnabled) { ?>
            <p>현재 환경에서는 모듈 소스 반영 기능이 비활성화되어 있습니다. <code>admin.module_sources_enabled</code>를 참/거짓 유형의 참 값으로 저장하면 소유자 재인증 후 사용할 수 있습니다.</p>
        <?php } elseif (!$moduleUploadAvailable) { ?>
            <p>PHP ZipArchive 확장이 없어 이 서버에서는 zip 업로드를 사용할 수 없습니다. FTP로 <code>modules/{module_key}</code>에 업로드한 뒤 설치하세요.</p>
        <?php } ?>
    </section>
<?php } else { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" enctype="multipart/form-data" class="admin-form-layout ui-form-theme ui-form-showcase">
            <section class="card">
                <h2>모듈 zip 업로드</h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="upload_module_zip">
            <div class="af-row">
                <div class="af-label"><span class="form-label">모듈 zip</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">모듈 zip</span>
                    <input type="file" name="module_zip" accept=".zip,application/zip" required>
                    </label>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">모듈 key</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">모듈 key</span>
                    <input type="text" name="upload_module_key" maxlength="60" pattern="[a-z0-9_]*">
                    </label>
                </div>
            </div>
            <div class="af-grid">
                <div class="af-row">
                    <div class="af-label"><span class="form-label">기존 모듈 파일 백업과 교체 확인</span></div>
                    <div class="af-field">
                        <label class="af-check form-label">
                            <input type="checkbox" name="confirm_file_replace" value="1" class="form-checkbox">
                            <?php echo sr_admin_choice_label_html('기존 모듈 파일 백업과 교체 확인'); ?>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">낮은 버전 덮어쓰기 허용</span></div>
                    <div class="af-field">
                        <label class="af-check form-label">
                            <input type="checkbox" name="allow_downgrade" value="1" class="form-checkbox">
                            <?php echo sr_admin_choice_label_html('낮은 버전 덮어쓰기 허용'); ?>
                        </label>
                    </div>
                </div>
            </div>
            <div class="af-row">
                <div class="af-label"><span class="form-label">소유자 비밀번호</span></div>
                <div class="af-field">
                    <label>
                        <span class="sr-only">소유자 비밀번호</span>
                    <input type="password" name="owner_password" autocomplete="current-password" required>
                    </label>
                </div>
            </div>
            <p>최대 <?php echo sr_e($moduleUploadLimitLabel); ?>까지 업로드할 수 있습니다. 압축 해제 후 모듈 파일은 최대 <?php echo sr_e(sr_admin_format_bytes(sr_admin_module_uncompressed_limit_bytes())); ?>까지 허용합니다. zip은 <code>{module_key}/module.php</code> 구조를 권장하고, <code>module/module.php</code> 구조라면 module key를 입력하세요.</p>
            </section>
            <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
                <button type="submit" class="btn btn-solid-primary">zip 업로드</button>
            </div>
        </form>
<?php } ?>
</div>

<section class="member-table-card admin-member-list-form" data-admin-tab-panel="installable" hidden>
    <div class="card-header">
        <h2 class="card-title">설치 가능한 모듈</h2>
    </div>
    <?php if ($installableModules === []) { ?>
        <p>설치 가능한 새 모듈이 없습니다.</p>
    <?php } else { ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>키</th>
                    <th>이름</th>
                    <th>유형</th>
                    <th>코드 버전</th>
                    <th>수명주기</th>
                    <th>Saanraan 최소</th>
                    <th>Saanraan 검증</th>
                    <th>계약</th>
                    <th>설명</th>
                    <th class="text-end">설치</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($installableModules as $module) { ?>
                    <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
                    <tr>
                        <td><?php echo sr_e((string) $module['module_key']); ?></td>
                        <td><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $module['type'], 'module_type')); ?></td>
                        <td><?php echo sr_e((string) ($module['version'] !== '' ? $module['version'] : '-')); ?></td>
                        <td>
                            <?php echo sr_e((string) ($module['lifecycle_label'] ?? '미설치')); ?>
                            <br>
                            <?php echo sr_e((string) ($module['lifecycle_action'] ?? '설치 가능')); ?>
                        </td>
                        <td><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></td>
                        <td><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></td>
                        <td>
                            <?php echo sr_e((string) (($module['saanraan_module_contract'] ?? '') !== '' ? $module['saanraan_module_contract'] : '-')); ?>
                            <?php if ($moduleErrors !== []) { ?>
                                <br>
                                <strong>메타데이터/계약 오류</strong>
                                <ul>
                                    <?php foreach ($moduleErrors as $moduleError) { ?>
                                        <li><?php echo sr_e((string) $moduleError); ?></li>
                                    <?php } ?>
                                </ul>
                            <?php } ?>
                        </td>
                        <td><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></td>
                        <td class="member-cell-manage">
                            <div class="member-manage">
                            <?php if ($moduleErrors !== []) { ?>
                                설치 불가
                            <?php } else { ?>
                                <details class="member-edit-details">
                                    <summary class="btn btn-sm btn-surface-default-soft">설치</summary>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="member-edit-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="install">
                                        <input type="hidden" name="module_key" value="<?php echo sr_e((string) $module['module_key']); ?>">
                                        <label>
                                            <span>설치 후 상태</span>
                                            <select name="status">
                                                <?php foreach ($allowedInstallStatuses as $status) { ?>
                                                    <option value="<?php echo sr_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                                        <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                                    </option>
                                                <?php } ?>
                                            </select>
                                        </label>
                                        <button type="submit" class="btn btn-sm btn-solid-primary">설치</button>
                                    </form>
                                </details>
                            <?php } ?>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        </div>
    <?php } ?>
</section>

<section class="member-table-card admin-member-list-form" data-admin-tab-panel="settings" hidden>
    <div class="card-header">
        <h2 class="card-title">모듈 설정 항목</h2>
    </div>
    <p>이 영역은 전용 화면이 없는 낮은 수준의 고급 설정입니다. 저장과 삭제는 소유자만 실행할 수 있습니다.</p>
    <?php if ($canManageAdvancedModuleSettings) { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-form-layout ui-form-theme ui-form-showcase">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="module_setting">
            <section class="card">
                <h2>모듈 설정 추가</h2>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">모듈</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">모듈</span>
                        <select name="module_key">
                            <?php foreach ($modules as $module) { ?>
                                <option value="<?php echo sr_e((string) $module['module_key']); ?>">
                                    <?php echo sr_e((string) $module['module_key']); ?>
                                </option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">키</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">키</span>
                        <input type="text" name="setting_key" maxlength="120" required>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">값</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">값</span>
                        <textarea name="setting_value" maxlength="5000"></textarea>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">유형</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">유형</span>
                        <select name="value_type">
                            <?php foreach ($allowedSettingTypes as $type) { ?>
                                <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'setting_type')); ?></option>
                            <?php } ?>
                        </select>
                        </label>
                    </div>
                </div>
                <div class="af-row">
                    <div class="af-label"><span class="form-label">소유자 비밀번호</span></div>
                    <div class="af-field">
                        <label>
                            <span class="sr-only">소유자 비밀번호</span>
                        <input type="password" name="owner_password" autocomplete="current-password">
                        </label>
                    </div>
                </div>
            </section>
            <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
                <button type="submit" class="btn btn-solid-primary">항목 저장</button>
            </div>
        </form>
    <?php } ?>

    <div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>모듈</th>
                <th>키</th>
                <th>값</th>
                <th>유형</th>
                <th>수정일</th>
                <th class="text-end">삭제</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($moduleSettings === []) { ?>
                <tr>
                    <td colspan="6" class="admin-dashboard-empty">설정 항목이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($moduleSettings as $setting) { ?>
                <tr>
                    <td><?php echo sr_e((string) $setting['module_key']); ?></td>
                    <td><?php echo sr_e((string) $setting['setting_key']); ?></td>
                    <td><?php echo sr_e(sr_admin_module_setting_display_value($setting)); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $setting['value_type'], 'setting_type')); ?></td>
                    <td><?php echo sr_e((string) $setting['updated_at']); ?></td>
                    <td class="member-cell-manage">
                        <div class="member-manage">
                        <?php if ($canManageAdvancedModuleSettings) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete_module_setting">
                                <input type="hidden" name="module_key" value="<?php echo sr_e((string) $setting['module_key']); ?>">
                                <input type="hidden" name="setting_key" value="<?php echo sr_e((string) $setting['setting_key']); ?>">
                                <?php if (sr_admin_setting_value_is_secret((string) $setting['setting_key'])) { ?>
                                    <input type="password" name="owner_password" autocomplete="current-password" required>
                                <?php } ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                            </form>
                        <?php } else { ?>
                            소유자 전용
                        <?php } ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    </div>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
