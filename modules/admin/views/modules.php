<?php

$adminPageTitle = '모듈 관리';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<div class="admin-notice">
    <span class="admin-notice-icon" aria-hidden="true">i</span>
    <div class="admin-notice-copy">
        <strong>모듈 상태 안내</strong>
        <p>설치 차단 상태는 메타데이터나 계약 오류가 있는 미설치 모듈을 뜻합니다.</p>
    </div>
</div>

<div class="admin-section-heading">
    <h2>설치 가능한 모듈</h2>
    <button type="button" class="btn btn-soft-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="module-upload-modal" data-overlay="#module-upload-modal" hidden>
        <?php echo sr_material_icon_html('upload'); ?>
        <span>zip 업로드</span>
    </button>
</div>
<?php if ($installableModules === []) { ?>
    <section class="admin-card admin-list-card card">
        <p>설치 가능한 새 모듈이 없습니다.</p>
    </section>
<?php } else { ?>
    <div class="admin-module-card-grid">
        <?php foreach ($installableModules as $module) { ?>
            <?php $moduleKey = (string) $module['module_key']; ?>
            <?php $moduleModalId = 'installable-module-detail-' . $moduleKey; ?>
            <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
            <?php $canInstall = $moduleErrors === []; ?>
            <article class="admin-card admin-module-card card admin-list-form">
                <div class="admin-module-card-header">
                    <div>
                        <h3><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></h3>
                        <p><?php echo sr_e($moduleKey); ?> · <?php echo sr_e(sr_admin_code_label((string) $module['type'], 'module_type')); ?></p>
                    </div>
                    <span class="admin-status <?php echo $canInstall ? 'is-normal' : 'is-blocked'; ?>">
                        <?php echo $canInstall ? '설치 가능' : '설치 차단'; ?>
                    </span>
                </div>
                <div class="admin-module-card-body">
                    <dl class="admin-module-card-meta">
                        <div>
                            <dt>코드 버전</dt>
                            <dd><?php echo sr_e((string) ($module['version'] !== '' ? $module['version'] : '-')); ?></dd>
                        </div>
                        <div>
                            <dt>수명주기</dt>
                            <dd>
                                <?php echo sr_e((string) ($module['lifecycle_label'] ?? '미설치')); ?>
                                <span><?php echo sr_e((string) ($module['lifecycle_action'] ?? '설치 가능')); ?></span>
                            </dd>
                        </div>
                        <div>
                            <dt>Saanraan 최소</dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></dd>
                        </div>
                        <div>
                            <dt>Saanraan 검증</dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></dd>
                        </div>
                    </dl>
                    <p><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></p>
                    <?php if ($moduleErrors !== []) { ?>
                        <p class="admin-module-card-warning">메타데이터/계약 오류로 설치할 수 없습니다.</p>
                    <?php } ?>
                </div>
                <div class="admin-module-card-actions">
                    <button type="button" class="btn btn-sm btn-soft-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($moduleModalId); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                        상세보기
                    </button>
                    <?php if ($canInstall) { ?>
                        <details class="admin-inline-edit-details">
                            <summary class="btn btn-sm btn-soft-default">설치</summary>
                            <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-inline-edit-form">
                                <?php echo sr_csrf_field(); ?>
                                <input type="hidden" name="intent" value="install">
                                <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                <label for="modules_admin_modules_status">
                                    <span>설치 후 상태</span>
                                    <select id="modules_admin_modules_status" name="status" class="form-select">
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
                    <?php } else { ?>
                        <span class="admin-module-card-action-note">설치 불가</span>
                    <?php } ?>
                </div>
            </article>
            <div id="<?php echo sr_e($moduleModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleModalId); ?>-label">
                <div class="modal-dialog modal-dialog-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 id="<?php echo sr_e($moduleModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?> 상세 정보</h3>
                            <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                                <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                            </button>
                        </div>
                        <div class="modal-body">
                            <dl class="admin-module-detail-list">
                                <dt>키</dt>
                                <dd><?php echo sr_e($moduleKey); ?></dd>
                                <dt>이름</dt>
                                <dd><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></dd>
                                <dt>유형</dt>
                                <dd><?php echo sr_e(sr_admin_code_label((string) $module['type'], 'module_type')); ?></dd>
                                <dt>코드 버전</dt>
                                <dd><?php echo sr_e((string) ($module['version'] !== '' ? $module['version'] : '-')); ?></dd>
                                <dt>수명주기</dt>
                                <dd><?php echo sr_e((string) ($module['lifecycle_label'] ?? '미설치')); ?> · <?php echo sr_e((string) ($module['lifecycle_action'] ?? '설치 가능')); ?></dd>
                                <dt>Saanraan 최소</dt>
                                <dd><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></dd>
                                <dt>Saanraan 검증</dt>
                                <dd><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></dd>
                                <dt>계약</dt>
                                <dd><?php echo sr_e((string) (($module['saanraan_module_contract'] ?? '') !== '' ? $module['saanraan_module_contract'] : '-')); ?></dd>
                                <dt>메타데이터/계약 오류</dt>
                                <dd>
                                    <?php if ($moduleErrors === []) { ?>
                                        -
                                    <?php } else { ?>
                                        <ul>
                                            <?php foreach ($moduleErrors as $moduleError) { ?>
                                                <li><?php echo sr_e((string) $moduleError); ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>
                                </dd>
                                <dt>설명</dt>
                                <dd><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></dd>
                            </dl>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-soft-default modal-action" data-overlay="#<?php echo sr_e($moduleModalId); ?>">닫기</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php } ?>
    </div>
<?php } ?>

<div class="admin-section-heading">
    <h2>설치된 모듈</h2>
</div>
<div class="admin-module-card-grid">
    <?php foreach ($modules as $module) { ?>
        <?php $moduleKey = (string) $module['module_key']; ?>
        <?php $moduleModalId = 'module-detail-' . $moduleKey; ?>
        <?php $isRequired = in_array($moduleKey, $requiredModules, true); ?>
        <?php $moduleErrors = isset($module['metadata_errors']) && is_array($module['metadata_errors']) ? $module['metadata_errors'] : []; ?>
        <?php $moduleStatus = (string) $module['status']; ?>
        <?php $moduleStatusClass = $moduleStatus === 'enabled' ? 'is-normal' : (in_array($moduleStatus, ['failed', 'installing'], true) ? 'is-left' : 'is-blocked'); ?>
        <article class="admin-card admin-module-card card admin-list-form">
            <div class="admin-module-card-header">
                <div>
                    <h3><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></h3>
                    <p><?php echo sr_e($moduleKey); ?> · <?php echo sr_e(sr_admin_code_label((string) ($module['code_type'] ?? 'module'), 'module_type')); ?></p>
                </div>
                <span class="admin-status <?php echo sr_e($moduleStatusClass); ?>">
                    <?php echo sr_e(sr_admin_code_label($moduleStatus, 'module_status')); ?>
                </span>
            </div>
            <div class="admin-module-card-body">
                <dl class="admin-module-card-meta">
                    <div>
                        <dt>설치 버전</dt>
                        <dd><?php echo sr_e((string) $module['version']); ?></dd>
                    </div>
                    <div>
                        <dt>코드 버전</dt>
                        <dd><?php echo sr_e((string) ($module['code_version'] !== '' ? $module['code_version'] : '-')); ?></dd>
                    </div>
                    <div>
                        <dt>수명주기</dt>
                        <dd>
                            <?php echo sr_e((string) ($module['lifecycle_label'] ?? '상태 확인 필요')); ?>
                            <span><?php echo sr_e((string) ($module['lifecycle_action'] ?? '모듈 상태 확인')); ?></span>
                        </dd>
                    </div>
                    <div>
                        <dt>업데이트</dt>
                        <dd>
                            <?php if ((int) ($module['pending_update_count'] ?? 0) > 0) { ?>
                                <a href="<?php echo sr_e(sr_url('/admin/updates')); ?>"><?php echo sr_e((string) $module['pending_update_count']); ?>개 SQL 대기</a>
                            <?php } elseif (($module['version_state'] ?? '') === 'code_newer') { ?>
                                <?php if ($canManageModuleSources && $moduleSourcesEnabled) { ?>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-module-sync-form">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="intent" value="sync_module_version">
                                        <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                                        <label for="modules_admin_modules_owner_password">
                                            <span class="sr-only">소유자 비밀번호</span>
                                            <input id="modules_admin_modules_owner_password" type="password" name="owner_password" class="form-input" autocomplete="current-password" required placeholder="소유자 비밀번호">
                                        </label>
                                        <button type="submit" class="btn btn-sm btn-soft-default">파일 업데이트 반영</button>
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
                        </dd>
                    </div>
                </dl>
                <p><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></p>
                <?php if ($moduleStatus === 'enabled' && $moduleErrors !== []) { ?>
                    <p class="admin-module-card-warning">런타임 계약 파일 비활성</p>
                <?php } ?>
            </div>
            <div class="admin-module-card-actions">
                <button type="button" class="btn btn-sm btn-soft-default" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($moduleModalId); ?>" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                    상세보기
                </button>
                <?php if (in_array($moduleStatus, ['failed', 'installing'], true)) { ?>
                    <details class="admin-inline-edit-details">
                        <summary class="btn btn-sm btn-soft-default">재설치</summary>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-inline-edit-form">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="install">
                            <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                            <label for="modules_admin_modules_status_2">
                                <span>설치 후 상태</span>
                                <select id="modules_admin_modules_status_2" name="status" class="form-select">
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
                    <details class="admin-inline-edit-details">
                        <summary class="btn btn-sm btn-soft-default"<?php echo $isRequired ? ' aria-disabled="true"' : ''; ?>>상태 변경</summary>
                        <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" class="admin-inline-edit-form">
                            <?php echo sr_csrf_field(); ?>
                            <input type="hidden" name="intent" value="status">
                            <input type="hidden" name="module_key" value="<?php echo sr_e($moduleKey); ?>">
                            <label for="modules_admin_modules_status_3">
                                <span>상태</span>
                                <select id="modules_admin_modules_status_3" name="status"<?php echo $isRequired ? ' disabled' : ''; ?> class="form-select">
                                    <?php foreach ($allowedStatuses as $status) { ?>
                                        <option value="<?php echo sr_e($status); ?>"<?php echo $moduleStatus === $status ? ' selected' : ''; ?>>
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
        </article>
        <div id="<?php echo sr_e($moduleModalId); ?>" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleModalId); ?>-label">
            <div class="modal-dialog modal-dialog-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h3 id="<?php echo sr_e($moduleModalId); ?>-label" class="modal-title"><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?> 상세 정보</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#<?php echo sr_e($moduleModalId); ?>">
                            <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <dl class="admin-module-detail-list">
                            <dt>키</dt>
                            <dd><?php echo sr_e($moduleKey); ?></dd>
                            <dt>이름</dt>
                            <dd><?php echo sr_e(sr_admin_module_name_label((string) $module['name'])); ?></dd>
                            <dt>유형</dt>
                            <dd><?php echo sr_e(sr_admin_code_label((string) ($module['code_type'] ?? 'module'), 'module_type')); ?></dd>
                            <dt>설치 버전</dt>
                            <dd><?php echo sr_e((string) $module['version']); ?></dd>
                            <dt>코드 버전</dt>
                            <dd><?php echo sr_e((string) ($module['code_version'] !== '' ? $module['code_version'] : '-')); ?></dd>
                            <dt>수명주기</dt>
                            <dd><?php echo sr_e((string) ($module['lifecycle_label'] ?? '상태 확인 필요')); ?> · <?php echo sr_e((string) ($module['lifecycle_action'] ?? '모듈 상태 확인')); ?></dd>
                            <dt>업데이트</dt>
                            <dd>
                                <?php if ((int) ($module['pending_update_count'] ?? 0) > 0) { ?>
                                    <a href="<?php echo sr_e(sr_url('/admin/updates')); ?>"><?php echo sr_e((string) $module['pending_update_count']); ?>개 SQL 대기</a>
                                <?php } elseif (($module['version_state'] ?? '') === 'code_newer') { ?>
                                    파일 전용 업데이트 가능
                                <?php } elseif (($module['version_state'] ?? '') === 'code_older') { ?>
                                    파일 재배치 필요
                                <?php } else { ?>
                                    -
                                <?php } ?>
                            </dd>
                            <dt>Saanraan 최소</dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_min_version'] !== '' ? $module['saanraan_min_version'] : '-')); ?></dd>
                            <dt>Saanraan 검증</dt>
                            <dd><?php echo sr_e((string) ($module['saanraan_tested_with'] !== '' ? $module['saanraan_tested_with'] : '-')); ?></dd>
                            <dt>계약</dt>
                            <dd><?php echo sr_e((string) (($module['saanraan_module_contract'] ?? '') !== '' ? $module['saanraan_module_contract'] : '-')); ?></dd>
                            <dt>메타데이터/계약 오류</dt>
                            <dd>
                                <?php if ($moduleErrors === []) { ?>
                                    -
                                <?php } else { ?>
                                    <ul>
                                        <?php foreach ($moduleErrors as $moduleError) { ?>
                                            <li><?php echo sr_e((string) $moduleError); ?></li>
                                        <?php } ?>
                                    </ul>
                                <?php } ?>
                            </dd>
                            <dt>상태</dt>
                            <dd>
                                <?php echo sr_e(sr_admin_code_label($moduleStatus, 'module_status')); ?>
                                <?php if ($moduleStatus === 'enabled' && $moduleErrors !== []) { ?>
                                    <br>런타임 계약 파일 비활성
                                <?php } ?>
                            </dd>
                            <dt>기본 포함</dt>
                            <dd><?php echo !empty($module['is_bundled']) ? '예' : '아니오'; ?></dd>
                            <dt>설치일</dt>
                            <dd><?php echo sr_e((string) ($module['installed_at'] ?? '')); ?></dd>
                            <dt>설명</dt>
                            <dd><?php echo sr_e((string) ($module['description'] !== '' ? sr_admin_module_description_label((string) $module['description']) : '-')); ?></dd>
                        </dl>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-soft-default modal-action" data-overlay="#<?php echo sr_e($moduleModalId); ?>">닫기</button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>
</div>

<?php $moduleUploadModalLabelId = (!$canManageModuleSources || !$moduleUploadAvailable) ? 'module-upload-modal-label-unavailable' : 'module-upload-modal-label'; ?>
<div id="module-upload-modal" class="modal-overlay modal-overlay-fade overlay hidden pointer-events-none opacity-0" role="dialog" tabindex="-1" aria-labelledby="<?php echo sr_e($moduleUploadModalLabelId); ?>">
    <div class="modal-dialog modal-dialog-lg">
        <div class="modal-content">
            <?php if (!$canManageModuleSources || !$moduleUploadAvailable) { ?>
                <div class="modal-header">
                    <h3 id="module-upload-modal-label-unavailable" class="modal-title">모듈 zip 업로드</h3>
                    <button type="button" class="modal-close" aria-label="닫기" data-overlay="#module-upload-modal">
                        <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                    </button>
                </div>
                <div class="modal-body">
                    <?php if (!$canManageModuleSources) { ?>
                        <p>모듈 파일 업로드는 소유자 권한이 필요합니다.</p>
                    <?php } elseif (!$moduleUploadAvailable) { ?>
                        <p>PHP ZipArchive 확장이 없어 이 서버에서는 zip 업로드를 사용할 수 없습니다. FTP로 <code>modules/{module_key}</code>에 업로드한 뒤 설치하세요.</p>
                    <?php } ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-soft-default modal-action" data-overlay="#module-upload-modal">닫기</button>
                </div>
            <?php } else { ?>
                <form method="post" action="<?php echo sr_e(sr_url('/admin/modules')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
                    <div class="modal-header">
                        <h3 id="module-upload-modal-label" class="modal-title">모듈 zip 업로드</h3>
                        <button type="button" class="modal-close" aria-label="닫기" data-overlay="#module-upload-modal">
                            <?php echo sr_material_icon_html('close', '', '닫기'); ?>
                        </button>
                    </div>
                    <div class="modal-body">
                        <?php echo sr_csrf_field(); ?>
                        <input type="hidden" name="intent" value="upload_module_zip">
                        <div class="admin-form-row">
                            <label class="form-label" for="admin_modules_module_zip">모듈 zip</label>
                            <div class="admin-form-field">
                                <input id="admin_modules_module_zip" type="file" name="module_zip" accept=".zip,application/zip" required class="form-input" data-overlay-focus>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="admin_modules_upload_module_key">모듈 key</label>
                            <div class="admin-form-field">
                                <input id="admin_modules_upload_module_key" type="text" name="upload_module_key" maxlength="60" pattern="[a-z0-9_]*" class="form-input">
                            </div>
                        </div>
                        <div class="admin-form-grid">
                            <div class="admin-form-row">
                                <span class="form-label">기존 모듈 파일 백업과 교체 확인</span>
                                <div class="admin-form-field">
                                    <label class="admin-form-check form-label" for="modules_admin_modules_confirm_file_replace">
                                                                            <input id="modules_admin_modules_confirm_file_replace" type="checkbox" name="confirm_file_replace" value="1" class="form-checkbox">
                                                                            <?php echo sr_admin_choice_label_html('기존 모듈 파일 백업과 교체 확인'); ?>
                                                                        </label>
                                </div>
                            </div>
                            <div class="admin-form-row">
                                <span class="form-label">낮은 버전 덮어쓰기 허용</span>
                                <div class="admin-form-field">
                                    <label class="admin-form-check form-label" for="modules_admin_modules_allow_downgrade">
                                                                            <input id="modules_admin_modules_allow_downgrade" type="checkbox" name="allow_downgrade" value="1" class="form-checkbox">
                                                                            <?php echo sr_admin_choice_label_html('낮은 버전 덮어쓰기 허용'); ?>
                                                                        </label>
                                </div>
                            </div>
                        </div>
                        <div class="admin-form-row">
                            <label class="form-label" for="admin_modules_owner_password">소유자 비밀번호</label>
                            <div class="admin-form-field">
                                <input id="admin_modules_owner_password" type="password" name="owner_password" autocomplete="current-password" required class="form-input">
                            </div>
                        </div>
                        <p>소유자 비밀번호 확인을 통과한 요청에서만 모듈 파일 반영을 일시적으로 허용하고, 업로드 처리가 끝나면 자동으로 다시 비활성화합니다. 최대 <?php echo sr_e($moduleUploadLimitLabel); ?>까지 업로드할 수 있습니다. 압축 해제 후 모듈 파일은 최대 <?php echo sr_e(sr_format_bytes(sr_module_source_uncompressed_limit_bytes())); ?>까지 허용합니다. zip은 <code>{module_key}/module.php</code> 구조를 권장하고, <code>module/module.php</code> 구조라면 module key를 입력하세요.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-soft-default modal-action" data-overlay="#module-upload-modal">닫기</button>
                        <button type="submit" class="btn btn-solid-primary modal-action">zip 업로드</button>
                    </div>
                </form>
            <?php } ?>
        </div>
    </div>
</div>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
