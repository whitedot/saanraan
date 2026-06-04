<?php

$adminPageTitle = '게시판 복사';
$adminPageSubtitle = '게시판 설정 또는 운영 데이터를 새 disabled 게시판으로 복사합니다.';
$adminContainerClass = 'admin-community-board-form admin-ui-scope';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts('', $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards/copy')); ?>" class="admin-form ui-form-theme">
    <section class="admin-card card">
        <h2><?php echo sr_e('복사 정보'); ?></h2>
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="board_id" value="<?php echo sr_e((string) (int) $sourceBoard['id']); ?>">
        <dl class="admin-meta-list">
            <dt><?php echo sr_e('원본 게시판'); ?></dt>
            <dd><?php echo sr_e((string) $sourceBoard['title']); ?> / <code><?php echo sr_e((string) $sourceBoard['board_key']); ?></code></dd>
            <dt><?php echo sr_e('복사 수'); ?></dt>
            <dd>
                <?php echo sr_e('게시글 ' . number_format((int) $copyCounts['posts']) . ', 댓글 ' . number_format((int) $copyCounts['comments']) . ', 링크 참조 ' . number_format((int) $copyCounts['link_refs']) . ', 첨부 ' . number_format((int) $copyCounts['attachments']) . ', 첨부 총량 ' . sr_community_format_bytes((int) $copyCounts['bytes'])); ?>
            </dd>
            <dt><?php echo sr_e('복사 상태'); ?></dt>
            <dd><?php echo sr_e('복사본 게시판은 disabled로 저장됩니다. 신고, 스크랩, 자산 로그, 알림, 시리즈는 복사하지 않습니다.'); ?></dd>
        </dl>
        <?php if ($limitErrors !== []) { ?>
            <div class="alert alert-warning">
                <?php foreach ($limitErrors as $limitError) { ?>
                    <p><?php echo sr_e($limitError); ?></p>
                <?php } ?>
                <p><?php echo sr_e('상한 초과 게시판은 후속 배치 복사 흐름에서 처리합니다.'); ?></p>
            </div>
        <?php } ?>
        <div class="admin-form-row">
            <label class="form-label" for="community_board_copy_key"><?php echo sr_e('board_key'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="admin-form-field">
                <input id="community_board_copy_key" type="text" name="board_key" value="<?php echo sr_e((string) $values['board_key']); ?>" class="form-input form-control-full" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_board_copy_title"><?php echo sr_e('새 제목'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="admin-form-field">
                <input id="community_board_copy_title" type="text" name="title" value="<?php echo sr_e((string) $values['title']); ?>" class="form-input form-control-full" maxlength="120" required>
            </div>
        </div>
        <div class="admin-form-row">
            <span class="form-label"><?php echo sr_e('복사 범위'); ?></span>
            <div class="admin-form-field">
                <?php foreach (sr_community_board_copy_modes() as $modeKey => $modeLabel) { ?>
                    <label class="admin-form-check form-label" for="community_board_copy_mode_<?php echo sr_e($modeKey); ?>">
                        <input id="community_board_copy_mode_<?php echo sr_e($modeKey); ?>" type="radio" name="mode" value="<?php echo sr_e($modeKey); ?>" class="form-radio"<?php echo (string) $values['mode'] === (string) $modeKey ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html($modeLabel); ?>
                    </label>
                <?php } ?>
                <p class="admin-form-help"><?php echo sr_e('게시글/댓글/첨부파일 포함 복사는 동기 상한 안에서만 실행됩니다. 상한을 넘으면 복사를 시작하지 않습니다.'); ?></p>
            </div>
        </div>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light"><?php echo sr_e('취소'); ?></a>
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('복사본 만들기'); ?></button>
    </div>
</form>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
