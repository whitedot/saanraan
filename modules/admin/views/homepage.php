<?php

$adminPageTitle = '홈페이지';
$adminPageSubtitle = '초기화면과 기본 홈페이지 문구를 관리합니다.';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if (!$currentHomepageAvailable) { ?>
    <div class="admin-notice">
        <span class="admin-notice-icon">!</span>
        <div class="admin-notice-copy">
            <strong>현재 저장된 초기화면을 사용할 수 없습니다.</strong>
            <p>방문자는 기본 홈페이지를 보게 됩니다. 사용할 수 있는 후보를 다시 선택해 저장하세요.</p>
        </div>
    </div>
<?php } ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/homepage')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <section class="admin-card card">
        <h2>초기화면</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">초기화면</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">초기화면</span>
                    <select name="home_path" class="form-select">
                        <?php foreach ($homepageCandidates as $candidate) { ?>
                            <?php $candidatePath = (string) ($candidate['path'] ?? ''); ?>
                            <?php $candidateSelected = (string) ($values['home_path'] ?? '/') === $candidatePath; ?>
                            <option value="<?php echo sr_e($candidatePath); ?>"<?php echo $candidateSelected ? ' selected' : ''; ?><?php echo empty($candidate['available']) && !$candidateSelected ? ' disabled' : ''; ?>>
                                <?php echo sr_e((string) ($candidate['label'] ?? $candidatePath)); ?>
                                <?php echo $candidatePath !== '/' ? ' - ' . sr_e($candidatePath) : ''; ?>
                                <?php echo empty($candidate['available']) ? ' (사용 불가)' : ''; ?>
                            </option>
                        <?php } ?>
                    </select>
                </label>
                <p class="admin-form-help">페이지 모듈의 공개 페이지와 커뮤니티 홈은 활성 상태일 때 초기화면 후보로 사용할 수 있습니다.</p>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>기본 홈페이지</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">보조 문구</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">보조 문구</span>
                    <input type="text" name="home_eyebrow" value="<?php echo sr_e((string) ($values['home_eyebrow'] ?? '')); ?>" class="form-input" maxlength="80">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">제목</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">제목</span>
                    <input type="text" name="home_title" value="<?php echo sr_e((string) ($values['home_title'] ?? '')); ?>" class="form-input" maxlength="160">
                </label>
                <p class="admin-form-help">비워 두면 사이트 이름을 사용합니다.</p>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">설명</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">설명</span>
                    <textarea name="home_description" class="form-textarea" maxlength="1000" rows="4"><?php echo sr_e((string) ($values['home_description'] ?? '')); ?></textarea>
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">기본 버튼</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">기본 버튼 문구</span>
                    <input type="text" name="home_primary_label" value="<?php echo sr_e((string) ($values['home_primary_label'] ?? '')); ?>" class="form-input" maxlength="80" placeholder="버튼 문구">
                </label>
                <label>
                    <span class="sr-only">기본 버튼 URL</span>
                    <input type="text" name="home_primary_url" value="<?php echo sr_e((string) ($values['home_primary_url'] ?? '')); ?>" class="form-input" maxlength="255" placeholder="/community 또는 https://example.com">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">보조 버튼</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">보조 버튼 문구</span>
                    <input type="text" name="home_secondary_label" value="<?php echo sr_e((string) ($values['home_secondary_label'] ?? '')); ?>" class="form-input" maxlength="80" placeholder="버튼 문구">
                </label>
                <label>
                    <span class="sr-only">보조 버튼 URL</span>
                    <input type="text" name="home_secondary_url" value="<?php echo sr_e((string) ($values['home_secondary_url'] ?? '')); ?>" class="form-input" maxlength="255" placeholder="/pages/about 또는 https://example.com">
                </label>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/')); ?>" class="btn btn-soft-default" target="_blank" rel="noopener noreferrer">홈 보기</a>
        <button type="submit" class="btn btn-solid-primary">홈페이지 설정 저장</button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
