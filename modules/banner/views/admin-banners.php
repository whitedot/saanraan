<?php

$bannerAdminPage = isset($bannerAdminPage) ? (string) $bannerAdminPage : 'list';
$editing = is_array($editBanner);
$adminPageTitle = $bannerAdminPage === 'form' ? ($editing ? '배너 수정' : '배너 추가') : '배너';
$selectedTargetOption = sr_banner_public_target_option_value();
if ($editing && (string) ($editBanner['module_key'] ?? '') !== '') {
    $selectedTargetOption = (string) ($editBanner['module_key'] ?? '') . '|' . (string) ($editBanner['point_key'] ?? '') . '|' . (string) ($editBanner['slot_key'] ?? '');
}
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($bannerAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/save')); ?>" enctype="multipart/form-data" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $editing ? '배너 수정' : '배너 추가'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="banner_id" value="<?php echo $editing ? sr_e((string) $editBanner['id']) : '0'; ?>">
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">제목</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">제목</span>
                    <input type="text" name="title" value="<?php echo $editing ? sr_e((string) $editBanner['title']) : ''; ?>" class="form-input" maxlength="120" required>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">내용</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">내용</span>
                    <textarea name="body_text" maxlength="3000" class="form-textarea"><?php echo $editing ? sr_e((string) $editBanner['body_text']) : ''; ?></textarea>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">링크 URL (외부 http/https 링크는 새 창으로 열림)</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">링크 URL (외부 http/https 링크는 새 창으로 열림)</span>
                    <input type="text" name="link_url" value="<?php echo $editing ? sr_e((string) $editBanner['link_url']) : ''; ?>" class="form-input" maxlength="255">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">이미지 URL (/ 내부 경로 또는 http/https URL)</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">이미지 URL (/ 내부 경로 또는 http/https URL)</span>
                    <input type="text" name="image_url" value="<?php echo $editing ? sr_e((string) $editBanner['image_url']) : ''; ?>" class="form-input" maxlength="255">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">이미지 업로드</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">이미지 업로드</span>
                    <input type="file" name="image_upload" accept="image/jpeg,image/png,image/webp" class="form-input">
                    </label>
                <br>
                <small>JPEG, PNG, WebP / 최대 <?php echo sr_e(sr_banner_format_bytes(sr_banner_image_upload_max_bytes())); ?>. 업로드하면 이미지 URL보다 우선 적용됩니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">출력 위치</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">출력 위치</span>
                    <select name="target_option" class="form-select">
                        <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo $selectedTargetOption === sr_banner_public_target_option_value() ? ' selected' : ''; ?>>
                            공용 배너
                        </option>
                        <?php foreach ($availableTargets as $target) { ?>
                            <?php $optionValue = sr_banner_target_option_value($target); ?>
                            <option value="<?php echo sr_e($optionValue); ?>"<?php echo $selectedTargetOption === $optionValue ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $target['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                <br>
                <small>공용 배너는 자동 출력되지 않고, 게시판 같은 모듈의 개별 설정에서 선택해 사용합니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">매칭 방식</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">매칭 방식</span>
                    <select name="match_type" class="form-select">
                        <?php foreach ($allowedMatchTypes as $matchType) { ?>
                            <?php $currentMatchType = $editing ? (string) ($editBanner['match_type'] ?? 'all') : 'all'; ?>
                            <option value="<?php echo sr_e($matchType); ?>"<?php echo $currentMatchType === $matchType ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($matchType, 'match_type')); ?>
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">특정 subject ID</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">특정 subject ID</span>
                    <input type="text" name="subject_id" value="<?php echo $editing ? sr_e((string) ($editBanner['subject_id'] ?? '')) : ''; ?>" class="form-input" maxlength="80">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">상태</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">상태</span>
                    <select name="status" class="form-select">
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <?php $currentStatus = $editing ? (string) $editBanner['status'] : 'draft'; ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $currentStatus === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                <br>
                <small>사용 상태이고 기간 조건에 맞을 때만 사용자 화면에 노출됩니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">배너 스킨</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">배너 스킨</span>
                    <select name="skin_key" class="form-select">
                        <?php foreach ($bannerSkinOptions as $skinKey => $skinOption) { ?>
                            <?php $currentSkinKey = $editing ? (string) ($editBanner['skin_key'] ?? $bannerSkinKey) : $bannerSkinKey; ?>
                            <option value="<?php echo sr_e((string) $skinKey); ?>"<?php echo $currentSkinKey === (string) $skinKey ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) ($skinOption['label'] ?? $skinKey)); ?>
                                (<?php echo sr_e(implode(', ', array_map('sr_banner_placement_kind_label', is_array($skinOption['supports'] ?? null) ? $skinOption['supports'] : ['inline']))); ?>)
                            </option>
                        <?php } ?>
                    </select>
                    </label>
                <br>
                <small>저장 시 출력 위치와 호환되는 스킨인지 확인합니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">시작 시각</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">시작 시각</span>
                    <input type="datetime-local" name="starts_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['starts_at'] ?? null)) : ''; ?>" class="form-input">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">종료 시각</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">종료 시각</span>
                    <input type="datetime-local" name="ends_at" value="<?php echo $editing ? sr_e(sr_banner_admin_datetime_value($editBanner['ends_at'] ?? null)) : ''; ?>" class="form-input">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">정렬</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">정렬</span>
                    <input type="number" name="sort_order" value="<?php echo $editing ? sr_e((string) $editBanner['sort_order']) : '100'; ?>" class="form-input">
                    </label>
                </div>
            </div>
            <p>배너 스킨은 배너를 화면에 그리는 템플릿입니다. 선택한 스킨이 지원하는 출력 방식과 배너 출력 위치가 맞아야 사용자 화면에 표시됩니다.</p>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="btn btn-soft-default">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } else { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">배너 목록</h2>
                <p class="admin-dashboard-meta">사용 상태이고 기간 조건에 맞는 배너만 사용자 화면에 노출됩니다.</p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/banners/new')); ?>" class="btn btn-sm btn-soft-default">새 배너 추가</a>
        </div>
        <form method="get" action="<?php echo sr_e(sr_url('/admin/banners')); ?>" class="admin-filter ui-form-theme">
            <div class="admin-filter-grid admin-filter-grid-compact">
                <label class="admin-filter-field">
                    <span class="admin-filter-label">상태</span>
                    <select name="status" class="form-select">
                        <option value=""<?php echo $filters['status'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach ($allowedStatuses as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $filters['status'] === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">출력 위치</span>
                    <select name="target" class="form-select">
                        <option value=""<?php echo $filters['target'] === '' ? ' selected' : ''; ?>>전체</option>
                        <option value="<?php echo sr_e(sr_banner_public_target_option_value()); ?>"<?php echo $filters['target'] === sr_banner_public_target_option_value() ? ' selected' : ''; ?>>공용 배너</option>
                        <?php foreach ($availableTargets as $target) { ?>
                            <?php $optionValue = sr_banner_target_option_value($target); ?>
                            <option value="<?php echo sr_e($optionValue); ?>"<?php echo $filters['target'] === $optionValue ? ' selected' : ''; ?>>
                                <?php echo sr_e((string) $target['label']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
            </div>
        </form>
        <?php if ($banners === []) { ?>
            <p>등록된 배너가 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
            <table class="table">
                <thead class="ui-table-head">
                    <tr>
                        <th>제목</th>
                        <th>상태</th>
                        <th>스킨</th>
                        <th>링크</th>
                        <th>클릭</th>
                        <th>출력 위치</th>
                        <th>기간</th>
                        <th>정렬</th>
                        <th class="text-end">관리</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($banners as $banner) { ?>
                        <?php
                        if ((string) ($banner['module_key'] ?? '') === '') {
                            $bannerTargetLabel = '공용 배너';
                        } else {
                            $bannerTargetOption = (string) $banner['module_key'] . '|' . (string) $banner['point_key'] . '|' . (string) $banner['slot_key'];
                            $bannerTargetLabel = (string) ($targetLabels[$bannerTargetOption] ?? ('선언이 사라진 출력 위치 / ' . (string) $banner['module_key'] . ' / ' . (string) $banner['point_key'] . ' / ' . (string) $banner['slot_key']));
                        }
                        ?>
                        <tr>
                            <td><?php echo sr_e((string) $banner['title']); ?></td>
                            <td>
                                <?php echo sr_e(sr_admin_code_label((string) $banner['status'], 'content_status')); ?>
                                <?php if ((string) $banner['status'] !== 'enabled') { ?>
                                    <br><small>사용자 화면 미노출</small>
                                <?php } ?>
                            </td>
                            <td><?php echo sr_e(sr_banner_skin_key(['banner_skin_key' => (string) ($banner['skin_key'] ?? 'basic')])); ?></td>
                            <td>
                                <?php echo sr_e(sr_banner_link_type_label((string) ($banner['link_url'] ?? ''))); ?><br>
                                <?php echo sr_e((string) ($banner['link_url'] ?? '')); ?>
                            </td>
                            <td><?php echo sr_e(number_format((int) ($banner['click_count'] ?? 0))); ?></td>
                            <td><?php echo sr_e($bannerTargetLabel); ?></td>
                            <td>
                                <?php echo sr_e((string) ($banner['starts_at'] ?? '-')); ?><br>
                                <?php echo sr_e((string) ($banner['ends_at'] ?? '-')); ?>
                            </td>
                            <td><?php echo sr_e((string) $banner['sort_order']); ?></td>
                            <td class="admin-table-actions-cell">
                                <div class="admin-row-actions">
                                    <a href="<?php echo sr_e(sr_url('/admin/banners/edit?id=' . rawurlencode((string) $banner['id']))); ?>" class="btn btn-sm btn-soft-default">수정</a>
                                    <form method="post" action="<?php echo sr_e(sr_url('/admin/banners/delete')); ?>">
                                        <?php echo sr_csrf_field(); ?>
                                        <input type="hidden" name="banner_id" value="<?php echo sr_e((string) $banner['id']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">삭제</button>
                                    </form>
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

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
