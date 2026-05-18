<?php

$sessionErrors = $_SESSION['sr_page_admin_errors'] ?? [];
$sessionValues = $_SESSION['sr_page_admin_values'] ?? [];
unset($_SESSION['sr_page_admin_errors'], $_SESSION['sr_page_admin_values']);
if (is_array($sessionErrors)) {
    $errors = array_merge($errors, array_map('strval', $sessionErrors));
}
if (is_array($sessionValues)) {
    $values = $sessionValues;
}
$editing = is_array($editPage);
if ($values === []) {
    $values = $editing ? $editPage : [
        'title' => '',
        'slug' => '',
        'summary' => '',
        'body_text' => '',
        'status' => 'draft',
        'banner_before_content_id' => 0,
        'banner_after_content_id' => 0,
        'popup_layer_id' => 0,
        'seo_title' => '',
        'seo_description' => '',
    ];
}

$adminPageTitle = $pageAdminPage === 'form' ? ($editing ? '페이지 수정' : '페이지 추가') : '페이지';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($pageAdminPage === 'form') { ?>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/pages/save')); ?>" class="admin-form ui-form-theme">
        <section class="admin-card card">
            <h2><?php echo $editing ? '페이지 수정' : '페이지 추가'; ?></h2>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="page_id" value="<?php echo $editing ? sr_e((string) $editPage['id']) : '0'; ?>">
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">제목</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">제목</span>
                        <input type="text" name="title" value="<?php echo sr_e((string) ($values['title'] ?? '')); ?>" maxlength="160" required>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">Slug</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">Slug</span>
                        <input type="text" name="slug" value="<?php echo sr_e((string) ($values['slug'] ?? '')); ?>" maxlength="120" required>
                    </label>
                    <br>
                    <small>공개 URL은 /pages/slug 형식입니다. 소문자 영문, 숫자, 하이픈만 사용할 수 있습니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">요약</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">요약</span>
                        <textarea name="summary" maxlength="1000"><?php echo sr_e((string) ($values['summary'] ?? '')); ?></textarea>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">본문</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">본문</span>
                        <textarea name="body_text" rows="14"><?php echo sr_e((string) ($values['body_text'] ?? '')); ?></textarea>
                    </label>
                    <br>
                    <small>1차 페이지 본문은 plain text로 저장하고 출력 시 escape합니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">상태</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">상태</span>
                        <select name="status">
                            <?php foreach (sr_page_allowed_statuses() as $status) { ?>
                                <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($values['status'] ?? 'draft') === $status ? ' selected' : ''; ?>>
                                    <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">본문 상단 배너</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">본문 상단 배너</span>
                        <select name="banner_before_content_id">
                            <option value="0"<?php echo (int) ($values['banner_before_content_id'] ?? 0) === 0 ? ' selected' : ''; ?>>사용 안 함</option>
                            <?php foreach ($publicBanners as $banner) { ?>
                                <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) ($values['banner_before_content_id'] ?? 0) === (int) $banner['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $banner['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">본문 하단 배너</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">본문 하단 배너</span>
                        <select name="banner_after_content_id">
                            <option value="0"<?php echo (int) ($values['banner_after_content_id'] ?? 0) === 0 ? ' selected' : ''; ?>>사용 안 함</option>
                            <?php foreach ($publicBanners as $banner) { ?>
                                <option value="<?php echo sr_e((string) $banner['id']); ?>"<?php echo (int) ($values['banner_after_content_id'] ?? 0) === (int) $banner['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $banner['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                    <br>
                    <small>공용 배너만 직접 선택할 수 있습니다. 세부 출력 규칙은 배너 모듈의 출력 위치에서 설정할 수도 있습니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">팝업레이어</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">팝업레이어</span>
                        <select name="popup_layer_id">
                            <option value="0"<?php echo (int) ($values['popup_layer_id'] ?? 0) === 0 ? ' selected' : ''; ?>>사용 안 함</option>
                            <?php foreach ($publicPopupLayers as $popupLayer) { ?>
                                <option value="<?php echo sr_e((string) $popupLayer['id']); ?>"<?php echo (int) ($values['popup_layer_id'] ?? 0) === (int) $popupLayer['id'] ? ' selected' : ''; ?>>
                                    <?php echo sr_e((string) $popupLayer['title']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </label>
                    <br>
                    <small>공용 팝업레이어만 직접 선택할 수 있습니다. 페이지 전체 규칙은 팝업레이어 모듈의 출력 위치에서 설정할 수도 있습니다.</small>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">SEO 제목</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">SEO 제목</span>
                        <input type="text" name="seo_title" value="<?php echo sr_e((string) ($values['seo_title'] ?? '')); ?>" maxlength="160">
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">SEO 설명</span></div>
                <div class="admin-form-field">
                    <label>
                        <span class="sr-only">SEO 설명</span>
                        <input type="text" name="seo_description" value="<?php echo sr_e((string) ($values['seo_description'] ?? '')); ?>" maxlength="255">
                    </label>
                </div>
            </div>
            <?php if ($editing) { ?>
                <p>공개 URL: <a href="<?php echo sr_e(sr_url(sr_page_path((string) $editPage['slug']))); ?>" target="_blank" rel="noopener noreferrer"><?php echo sr_e(sr_page_path((string) $editPage['slug'])); ?></a></p>
            <?php } ?>
        </section>
        <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
            <a href="<?php echo sr_e(sr_url('/admin/pages')); ?>" class="btn btn-surface-default-soft">목록</a>
            <button type="submit" class="btn btn-solid-primary">저장</button>
        </div>
    </form>
<?php } else { ?>
    <section class="admin-card admin-list-card card admin-list-form">
        <div class="card-header">
            <div>
                <h2 class="card-title">페이지 목록</h2>
                <p class="admin-dashboard-meta">공개 상태인 페이지는 /pages/slug URL로 노출됩니다.</p>
            </div>
            <a href="<?php echo sr_e(sr_url('/admin/pages/new')); ?>" class="btn btn-sm btn-surface-default-soft">새 페이지 추가</a>
        </div>
        <form method="get" action="<?php echo sr_e(sr_url('/admin/pages')); ?>" class="admin-filter ui-form-theme">
            <div class="admin-filter-grid admin-filter-grid-compact">
                <label class="admin-filter-field">
                    <span class="admin-filter-label">상태</span>
                    <select name="status">
                        <option value=""<?php echo $filters['status'] === '' ? ' selected' : ''; ?>>전체</option>
                        <?php foreach (sr_page_allowed_statuses() as $status) { ?>
                            <option value="<?php echo sr_e($status); ?>"<?php echo $filters['status'] === $status ? ' selected' : ''; ?>>
                                <?php echo sr_e(sr_admin_code_label($status, 'content_status')); ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <label class="admin-filter-field">
                    <span class="admin-filter-label">검색</span>
                    <input type="search" name="q" value="<?php echo sr_e((string) $filters['q']); ?>" maxlength="120">
                </label>
                <button type="submit" class="btn btn-solid-primary admin-filter-submit">조회</button>
            </div>
        </form>
        <?php if ($pages === []) { ?>
            <p>등록된 페이지가 없습니다.</p>
        <?php } else { ?>
            <div class="table-wrapper">
                <table class="table">
                    <thead class="ui-table-head">
                        <tr>
                            <th>제목</th>
                            <th>Slug</th>
                            <th>상태</th>
                            <th>작성자</th>
                            <th>수정일</th>
                            <th>공개일</th>
                            <th>관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $page) { ?>
                            <tr>
                                <td><?php echo sr_e((string) $page['title']); ?></td>
                                <td><code><?php echo sr_e((string) $page['slug']); ?></code></td>
                                <td><?php echo sr_e(sr_admin_code_label((string) $page['status'], 'content_status')); ?></td>
                                <td><?php echo sr_e((string) ($page['created_by_name'] ?? '')); ?></td>
                                <td><?php echo sr_e((string) $page['updated_at']); ?></td>
                                <td><?php echo sr_e((string) ($page['published_at'] ?? '')); ?></td>
                                <td>
                                    <div class="admin-actions">
                                        <?php if ((string) $page['status'] === 'published') { ?>
                                            <a href="<?php echo sr_e(sr_url(sr_page_path((string) $page['slug']))); ?>" class="btn btn-sm btn-surface-default-soft" target="_blank" rel="noopener noreferrer">보기</a>
                                        <?php } ?>
                                        <a href="<?php echo sr_e(sr_url('/admin/pages/edit?id=' . rawurlencode((string) $page['id']))); ?>" class="btn btn-sm btn-surface-default-soft">수정</a>
                                        <?php if ((string) $page['status'] !== 'hidden') { ?>
                                            <form method="post" action="<?php echo sr_e(sr_url('/admin/pages/delete')); ?>" class="admin-inline-form">
                                                <?php echo sr_csrf_field(); ?>
                                                <input type="hidden" name="page_id" value="<?php echo sr_e((string) $page['id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-surface-danger-soft">숨김</button>
                                            </form>
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
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
