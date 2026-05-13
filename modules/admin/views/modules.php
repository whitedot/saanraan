<?php

$adminPageTitle = '모듈 관리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php if ($notice !== '') { ?>
    <p><?php echo sr_e($notice); ?></p>
<?php } ?>

<?php if ($errors !== []) { ?>
    <ul>
        <?php foreach ($errors as $error) { ?>
            <li><?php echo sr_e($error); ?></li>
        <?php } ?>
    </ul>
<?php } ?>

<p>설치 차단 상태는 메타데이터나 계약 오류가 있는 미설치 모듈을 뜻합니다.</p>

<table>
    <thead>
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
            <th>변경</th>
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
                                <label>소유자 비밀번호<br>
                                    <input type="password" name="owner_password" autocomplete="current-password" required>
                                </label>
                                <button type="submit">파일 업데이트 반영</button>
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
                <td>
                    <?php if (in_array((string) $module['status'], ['failed', 'installing'], true)) { ?>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="install">
                            <input type="hidden" name="module_key" value="<?php echo sr_e((string) $module['module_key']); ?>">
                            <select name="status">
                                <?php foreach ($allowedInstallStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button type="submit">재설치</button>
                        </form>
                    <?php } else { ?>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="status">
                            <input type="hidden" name="module_key" value="<?php echo sr_e((string) $module['module_key']); ?>">
                            <select name="status"<?php echo $isRequired ? ' disabled' : ''; ?>>
                                <?php foreach ($allowedStatuses as $status) { ?>
                                    <option value="<?php echo sr_e($status); ?>"<?php echo $module['status'] === $status ? ' selected' : ''; ?>>
                                        <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button type="submit"<?php echo $isRequired ? ' disabled' : ''; ?>>저장</button>
                        </form>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    </tbody>
</table>

<section>
    <h2>모듈 zip 업로드</h2>
    <?php if (!$canManageModuleSources) { ?>
        <p>모듈 파일 업로드는 소유자 권한이 필요합니다.</p>
    <?php } elseif (!$moduleSourcesEnabled) { ?>
        <p>현재 환경에서는 모듈 소스 반영 기능이 비활성화되어 있습니다. <code>admin.module_sources_enabled</code>를 참/거짓 유형의 참 값으로 저장하면 소유자 재인증 후 사용할 수 있습니다.</p>
    <?php } elseif (!$moduleUploadAvailable) { ?>
        <p>PHP ZipArchive 확장이 없어 이 서버에서는 zip 업로드를 사용할 수 없습니다. FTP로 <code>modules/{module_key}</code>에 업로드한 뒤 설치하세요.</p>
    <?php } else { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" enctype="multipart/form-data">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="upload_module_zip">
            <p>
                <label>모듈 zip<br>
                    <input type="file" name="module_zip" accept=".zip,application/zip" required>
                </label>
            </p>
            <p>
                <label>모듈 key<br>
                    <input type="text" name="upload_module_key" maxlength="60" pattern="[a-z0-9_]*">
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="confirm_file_replace" value="1">
                    기존 모듈 파일 백업과 교체 확인
                </label>
            </p>
            <p>
                <label>
                    <input type="checkbox" name="allow_downgrade" value="1">
                    낮은 버전 덮어쓰기 허용
                </label>
            </p>
            <p>
                <label>소유자 비밀번호<br>
                    <input type="password" name="owner_password" autocomplete="current-password" required>
                </label>
            </p>
            <p>최대 <?php echo sr_e($moduleUploadLimitLabel); ?>까지 업로드할 수 있습니다. 압축 해제 후 모듈 파일은 최대 <?php echo sr_e(sr_admin_format_bytes(sr_admin_module_uncompressed_limit_bytes())); ?>까지 허용합니다. zip은 <code>{module_key}/module.php</code> 구조를 권장하고, <code>module/module.php</code> 구조라면 module key를 입력하세요.</p>
            <button type="submit">zip 업로드</button>
        </form>
    <?php } ?>
</section>

<section>
    <h2>설치 가능한 모듈</h2>
    <?php if ($installableModules === []) { ?>
        <p>설치 가능한 새 모듈이 없습니다.</p>
    <?php } else { ?>
        <table>
            <thead>
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
                    <th>설치</th>
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
                        <td>
                            <?php if ($moduleErrors !== []) { ?>
                                설치 불가
                            <?php } else { ?>
                                <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="intent" value="install">
                                    <input type="hidden" name="module_key" value="<?php echo sr_e((string) $module['module_key']); ?>">
                                    <select name="status">
                                        <?php foreach ($allowedInstallStatuses as $status) { ?>
                                            <option value="<?php echo sr_e($status); ?>"<?php echo $status === 'enabled' ? ' selected' : ''; ?>>
                                                <?php echo sr_e(sr_admin_code_label($status, 'module_status')); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                    <button type="submit">설치</button>
                                </form>
                            <?php } ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</section>

<section>
    <h2>모듈 설정 항목</h2>
    <p>이 영역은 전용 화면이 없는 낮은 수준의 고급 설정입니다. 저장과 삭제는 소유자만 실행할 수 있습니다.</p>
    <?php if ($canManageAdvancedModuleSettings) { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="module_setting">
            <p>
                <label>모듈<br>
                    <select name="module_key">
                        <?php foreach ($modules as $module) { ?>
                            <option value="<?php echo sr_e((string) $module['module_key']); ?>">
                                <?php echo sr_e((string) $module['module_key']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>키<br>
                    <input type="text" name="setting_key" maxlength="120" required>
                </label>
            </p>
            <p>
                <label>값<br>
                    <textarea name="setting_value" maxlength="5000"></textarea>
                </label>
            </p>
            <p>
                <label>유형<br>
                    <select name="value_type">
                        <?php foreach ($allowedSettingTypes as $type) { ?>
                            <option value="<?php echo sr_e($type); ?>"><?php echo sr_e(sr_admin_code_label($type, 'setting_type')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label>소유자 비밀번호<br>
                    <input type="password" name="owner_password" autocomplete="current-password">
                </label>
            </p>
            <button type="submit">항목 저장</button>
        </form>
    <?php } ?>

    <table>
        <thead>
            <tr>
                <th>모듈</th>
                <th>키</th>
                <th>값</th>
                <th>유형</th>
                <th>수정일</th>
                <th>삭제</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($moduleSettings === []) { ?>
                <tr>
                    <td colspan="6">설정 항목이 없습니다.</td>
                </tr>
            <?php } ?>
            <?php foreach ($moduleSettings as $setting) { ?>
                <tr>
                    <td><?php echo sr_e((string) $setting['module_key']); ?></td>
                    <td><?php echo sr_e((string) $setting['setting_key']); ?></td>
                    <td><?php echo sr_e(sr_admin_module_setting_display_value($setting)); ?></td>
                    <td><?php echo sr_e(sr_admin_code_label((string) $setting['value_type'], 'setting_type')); ?></td>
                    <td><?php echo sr_e((string) $setting['updated_at']); ?></td>
                    <td>
                        <?php if ($canManageAdvancedModuleSettings) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="delete_module_setting">
                                <input type="hidden" name="module_key" value="<?php echo sr_e((string) $setting['module_key']); ?>">
                                <input type="hidden" name="setting_key" value="<?php echo sr_e((string) $setting['setting_key']); ?>">
                                <?php if (sr_admin_setting_value_is_secret((string) $setting['setting_key'])) { ?>
                                    <input type="password" name="owner_password" autocomplete="current-password" required>
                                <?php } ?>
                                <button type="submit">삭제</button>
                            </form>
                        <?php } else { ?>
                            소유자 전용
                        <?php } ?>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</section>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
