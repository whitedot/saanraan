<?php

$adminPageTitle = '게시판 복사';
$adminPageSubtitle = '게시판 설정 또는 운영 데이터를 새 disabled 게시판으로 복사합니다.';
$adminContainerClass = 'admin-community-board-form admin-ui-scope';
$communityBoardCopySeriesSuggestions = sr_community_board_copy_series_suggestions($pdo, (int) $sourceBoard['id']);
$communityBoardCopyStorageWarnings = sr_community_board_copy_storage_warnings($copyCounts);
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
                <?php echo sr_e('게시글 ' . number_format((int) $copyCounts['posts']) . ', 댓글 ' . number_format((int) $copyCounts['comments']) . ', 첨부 ' . number_format((int) $copyCounts['attachments']) . ', 시리즈 ' . number_format((int) ($copyCounts['series'] ?? 0)) . ', 첨부 총량 ' . sr_community_format_bytes((int) $copyCounts['bytes'])); ?>
            </dd>
            <dt><?php echo sr_e('복사 상태'); ?></dt>
            <dd><?php echo sr_e('복사본 게시판은 disabled로 저장됩니다. 신고, 스크랩, 자산 로그, 알림은 복사하지 않습니다. 시리즈는 아래 선택지를 켠 경우에만 새 사본으로 복사합니다.'); ?></dd>
        </dl>
        <?php if ($limitErrors !== []) { ?>
            <div class="alert alert-warning">
                <?php foreach ($limitErrors as $limitError) { ?>
                    <p><?php echo sr_e($limitError); ?></p>
                <?php } ?>
                <?php if ($batchAvailable) { ?>
                    <p><?php echo sr_e('상한 초과 게시판은 배치 복사 작업으로 나누어 처리할 수 있습니다.'); ?></p>
                <?php } else { ?>
                    <p><?php echo sr_e('첨부파일 저장소 또는 원본 파일 상태를 먼저 정리한 뒤 다시 시도하세요.'); ?></p>
                <?php } ?>
            </div>
        <?php } ?>
        <?php if ($communityBoardCopyStorageWarnings !== []) { ?>
            <div class="alert alert-warning">
                <div>
                    <?php foreach ($communityBoardCopyStorageWarnings as $storageWarning) { ?>
                        <p><?php echo sr_e($storageWarning); ?></p>
                    <?php } ?>
                </div>
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
                <p class="admin-form-help"><?php echo sr_e('게시글/댓글/첨부파일 포함 복사는 동기 상한 안에서만 실행됩니다. 상한을 넘으면 복사를 시작하지 않습니다. 첨부파일이 있으면 위 용량 안내를 확인하세요.'); ?></p>
            </div>
        </div>
        <?php if ((int) ($copyCounts['series'] ?? 0) > 0) { ?>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e('시리즈'); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="community_board_copy_series">
                        <input id="community_board_copy_series" type="checkbox" name="copy_series" value="1" class="form-checkbox"<?php echo !empty($values['copy_series']) ? ' checked' : ''; ?> data-copy-series-toggle>
                        <?php echo sr_admin_choice_label_html('게시글 포함 복사 시 시리즈도 새 사본으로 복사'); ?>
                    </label>
                    <p class="admin-form-help"><?php echo sr_e('설정만 복사에서는 적용되지 않습니다. 원본 시리즈에 사본 글을 섞지 않고 새 게시판 안에 새 시리즈를 만듭니다.'); ?></p>
                    <?php foreach ($communityBoardCopySeriesSuggestions as $seriesSuggestion) { ?>
                        <?php $seriesId = (int) $seriesSuggestion['series_id']; ?>
                        <div class="admin-form-row">
                            <label class="form-label" for="community_board_copy_series_title_<?php echo sr_e((string) $seriesId); ?>"><?php echo sr_e('시리즈 제목'); ?> <span class="sr-required-label" data-copy-series-required-label<?php echo !empty($values['copy_series']) ? '' : ' hidden'; ?>><?php echo sr_e('(필수)'); ?></span></label>
                            <div class="admin-form-field">
                                <input id="community_board_copy_series_title_<?php echo sr_e((string) $seriesId); ?>" type="text" name="community_series_titles[<?php echo sr_e((string) $seriesId); ?>]" value="<?php echo sr_e((string) $seriesSuggestion['title']); ?>" class="form-input form-control-full" maxlength="160"<?php echo !empty($values['copy_series']) ? ' required' : ''; ?> data-copy-series-input>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </section>
    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-split">
        <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light"><?php echo sr_e('취소'); ?></a>
        <div class="admin-form-actions">
            <?php if ($batchAvailable) { ?>
                <button type="submit" name="intent" value="start_batch" class="btn btn-solid-primary"><?php echo sr_e('배치 복사 작업 만들기'); ?></button>
            <?php } ?>
            <button type="submit" class="btn btn-solid-primary"><?php echo sr_e('복사본 만들기'); ?></button>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-copy-series-toggle]').forEach(function (toggle) {
        var form = toggle.closest('form');
        var sync = function () {
            var required = toggle.checked;
            if (!form) {
                return;
            }
            form.querySelectorAll('[data-copy-series-input]').forEach(function (input) {
                input.required = required;
            });
            form.querySelectorAll('[data-copy-series-required-label]').forEach(function (label) {
                label.hidden = !required;
            });
        };
        toggle.addEventListener('change', sync);
        sync();
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
