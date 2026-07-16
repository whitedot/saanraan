<?php

$adminPageTitle = '게시판 복사';
$adminPageSubtitle = '기존 게시판 설정이나 운영 데이터를 새 사용 중지 게시판으로 복사합니다.';
$adminContainerClass = 'admin-community-board-form admin-ui-scope';
$adminPageTitleActionsHtml = '<a href="' . sr_e(sr_url('/admin/community/board-copy-jobs')) . '" class="btn btn-outline-secondary">'
    . sr_e('작업 관리')
    . '</a>';
$communityBoardCopySeriesSuggestions = sr_community_board_copy_series_suggestions($pdo, (int) $sourceBoard['id']);
$communityBoardCopyStorageWarnings = sr_community_board_copy_storage_warnings($copyCounts);
$communityBoardCopySelectedCounts = isset($selectedCopyCounts) && is_array($selectedCopyCounts)
    ? $selectedCopyCounts
    : sr_community_board_copy_counts_for_values($copyCounts, $values);
$communityBoardCopyScopeValues = sr_community_board_copy_scope_values($values);
$communityBoardCopyScopeChecked = static function (string $scopeKey) use ($communityBoardCopyScopeValues): bool {
    return in_array($scopeKey, $communityBoardCopyScopeValues, true);
};
$communityBoardCopyAllChecked = sr_community_board_copy_scope_all_selected($values);
$communityBoardCopyLoad = sr_community_board_copy_load_assessment($communityBoardCopySelectedCounts, $values, $batchAvailable);
$communityBoardCopySettingsSubmitLabel = '설정만 복사';
$communityBoardCopyStartSubmitLabel = '복사 시작 (1/' . (string) sr_community_board_copy_job_stage_total() . ')';
$communityBoardCopyHelpOpenLabel = '도움말 보기';
$communityBoardCopyHelp = [
    'board_key' => [
        'id' => 'community-board-copy-key-help',
        'title' => '새 게시판 식별값 도움말',
        'body' => '<p>복사본 게시판을 구분하는 고유한 값으로, 게시판 공개 주소의 <code>?key=...</code>에도 사용됩니다. 영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 입력하세요.</p>'
            . '<p>이미 사용 중인 식별값은 저장할 때 자동으로 다른 값으로 보정됩니다. 복사본을 만든 뒤에는 바꿀 수 없으므로 실행 전에 확인하세요.</p>',
    ],
    'scope' => [
        'id' => 'community-board-copy-scope-help',
        'title' => '복사 범위 도움말',
        'body' => '<p>‘설정’은 게시판 그룹, 설명, 읽기·쓰기·댓글 권한, 카테고리, 화면과 운영에 사용하는 게시판별 설정을 복사합니다. 선택하지 않으면 새 게시판은 기본 설정으로 만들어집니다.</p>'
            . '<p>‘게시글+댓글’을 선택하면 여러 번에 나누어 처리하는 복사 작업을 만듭니다. ‘첨부파일’은 게시글에 연결된 실제 파일까지 복사하며, ‘시리즈’는 새 게시판에 새 시리즈를 만들고 복사된 글을 연결합니다. 두 항목은 게시글+댓글과 함께만 선택할 수 있습니다.</p>'
            . '<p>‘전체’를 켜면 모든 항목을 선택합니다. 복사본은 범위와 관계없이 항상 ‘사용 중지’ 상태로 만들어지므로, 복사가 끝난 뒤 설정을 확인하고 직접 사용 상태로 바꾸세요.</p>',
    ],
    'series' => [
        'id' => 'community-board-copy-series-help',
        'title' => '시리즈 제목 도움말',
        'body' => '<p>원본 시리즈는 그대로 두고 새 게시판 안에 별도의 시리즈를 만듭니다. 각 입력란에서 복사본에 사용할 제목을 확인하세요.</p>'
            . '<p>시리즈에 연결된 글이 복사 범위에 포함되어야 새 시리즈에도 연결됩니다. 시리즈 복사를 선택하지 않으면 게시글만 복사되고 시리즈 연결은 만들어지지 않습니다.</p>',
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts('', $errors); ?>

<form method="post" action="<?php echo sr_e(sr_url('/admin/community/boards/copy')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="board_id" value="<?php echo sr_e((string) (int) $sourceBoard['id']); ?>">
    <input type="hidden" name="mode" value="<?php echo sr_e(!empty($values['copy_posts_comments']) ? 'full' : 'settings'); ?>" data-copy-mode-input>
    <section class="card admin-community-board-copy-info-card">
        <h2><?php echo sr_e('복사 정보'); ?></h2>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('원본 게시판'); ?></span>
            <div class="form-field">
                <p class="admin-form-static admin-community-board-copy-info-text"><?php echo sr_e((string) $sourceBoard['title']); ?> (<?php echo sr_e((string) $sourceBoard['board_key']); ?>)</p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('복사 수'); ?></span>
            <div class="form-field">
                <p class="admin-form-static admin-community-board-copy-info-text"><?php echo sr_e('게시글 ' . number_format((int) $copyCounts['posts']) . ', 댓글 ' . number_format((int) $copyCounts['comments']) . ', 첨부 ' . number_format((int) $copyCounts['attachments']) . ', 시리즈 ' . number_format((int) ($copyCounts['series'] ?? 0)) . ', 첨부 총량 ' . sr_community_format_bytes((int) $copyCounts['bytes'])); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('복사 상태'); ?></span>
            <div class="form-field">
                <p class="admin-form-static admin-community-board-copy-info-text"><?php echo sr_e('복사본은 사용 중지 게시판으로 먼저 만들어집니다. 설정만 선택하면 바로 완료되고, 게시글+댓글을 선택하면 작업 화면에서 단계별로 이어서 처리합니다. 첨부파일과 시리즈는 복사 범위에서 켠 경우에만 함께 복사합니다.'); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('부하 등급'); ?></span>
            <div class="form-field">
                <p class="admin-form-static admin-community-board-copy-info-text"><?php echo sr_e((string) $communityBoardCopyLoad['label']); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('중단/실패 시 상태'); ?></span>
            <div class="form-field">
                <p class="admin-form-static admin-community-board-copy-info-text"><?php echo sr_e((string) $communityBoardCopyLoad['failure_state']); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('권장 실행 시점'); ?></span>
            <div class="form-field">
                <p class="admin-form-static admin-community-board-copy-info-text"><?php echo sr_e((string) $communityBoardCopyLoad['recommended_time']); ?></p>
            </div>
        </div>
        <div class="form-row">
            <span class="form-label"><?php echo sr_e('기록 위치'); ?></span>
            <div class="form-field">
                <p class="admin-form-static admin-community-board-copy-info-text"><?php echo sr_e('설정만 복사는 감사 로그에 남고, 게시글+댓글을 포함한 복사는 작업 목록에 현재 단계, 실패, 정리 필요 상태가 남습니다.'); ?></p>
            </div>
        </div>
        <?php if ($limitErrors !== []) { ?>
            <div class="alert alert-warning">
                <?php foreach ($limitErrors as $limitError) { ?>
                    <p><?php echo sr_e($limitError); ?></p>
                <?php } ?>
                <?php if ($batchAvailable) { ?>
                    <p><?php echo sr_e('게시글 포함 복사는 상한과 관계없이 게시판 복사 작업으로 나누어 처리합니다.'); ?></p>
                <?php } else { ?>
                    <p><?php echo sr_e('위 차단 사유를 먼저 정리한 뒤 다시 시도하세요.'); ?></p>
                <?php } ?>
            </div>
        <?php } ?>
    </section>

    <section class="card">
        <h2><?php echo sr_e('복사 설정'); ?></h2>
        <div class="form-row">
            <?php echo sr_admin_form_label_help_html('community_board_copy_key', '새 게시판 식별값', $communityBoardCopyHelp['board_key']['id'], $communityBoardCopyHelpOpenLabel, true); ?>
            <div class="form-field">
                <input id="community_board_copy_key" type="text" name="board_key" value="<?php echo sr_e((string) $values['board_key']); ?>" class="form-input form-control-full" maxlength="60" pattern="[a-z][a-z0-9_]{1,59}" inputmode="latin" autocapitalize="none" spellcheck="false" required data-admin-key-input>
                <p class="form-help">영문 소문자로 시작하고 영문 소문자, 숫자, 밑줄만 입력하세요. 만든 뒤에는 바꿀 수 없습니다.</p>
            </div>
        </div>
        <div class="form-row">
            <label class="form-label" for="community_board_copy_title"><?php echo sr_e('새 제목'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></label>
            <div class="form-field">
                <input id="community_board_copy_title" type="text" name="title" value="<?php echo sr_e((string) $values['title']); ?>" class="form-input form-control-full" maxlength="120" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-label form-label-help">
                <button type="button" class="admin-label-help-button" aria-label="<?php echo sr_e($communityBoardCopyHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityBoardCopyHelp['scope']['id']); ?>" data-overlay="#<?php echo sr_e($communityBoardCopyHelp['scope']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                <span><?php echo sr_e('복사 범위'); ?> <span class="sr-required-label"><?php echo sr_e('(필수)'); ?></span></span>
            </div>
            <div class="form-field">
                <div class="filtering-toggle-group admin-checkbox-toggle-group admin-community-board-copy-scope-group" role="group" aria-label="<?php echo sr_e('복사 범위'); ?>" data-copy-scope-group>
                    <span class="filtering-toggle-item">
                        <input id="community_board_copy_scope_all" type="checkbox" name="copy_scope[]" value="all" class="form-choice-toggle-input sr-only"<?php echo $communityBoardCopyAllChecked ? ' checked' : ''; ?> data-copy-scope-all>
                        <label for="community_board_copy_scope_all" class="btn btn-choice-light btn-group-start"><?php echo sr_admin_choice_label_html('전체'); ?></label>
                    </span>
                    <span class="filtering-toggle-item">
                        <input id="community_board_copy_scope_settings" type="checkbox" name="copy_scope[]" value="settings" class="form-choice-toggle-input sr-only"<?php echo $communityBoardCopyScopeChecked('settings') ? ' checked' : ''; ?> data-copy-scope-item data-copy-scope="settings">
                        <label for="community_board_copy_scope_settings" class="btn btn-choice-light btn-group-middle">
                            <?php echo sr_admin_choice_label_html('설정'); ?>
                        </label>
                    </span>
                    <span class="filtering-toggle-item">
                        <input id="community_board_copy_scope_posts_comments" type="checkbox" name="copy_scope[]" value="posts_comments" class="form-choice-toggle-input sr-only"<?php echo $communityBoardCopyScopeChecked('posts_comments') ? ' checked' : ''; ?> data-copy-scope-item data-copy-scope="posts_comments">
                        <label for="community_board_copy_scope_posts_comments" class="btn btn-choice-light btn-group-middle">
                            <?php echo sr_admin_choice_label_html('게시글+댓글'); ?>
                        </label>
                    </span>
                    <span class="filtering-toggle-item">
                        <input id="community_board_copy_scope_attachments" type="checkbox" name="copy_scope[]" value="attachments" class="form-choice-toggle-input sr-only"<?php echo $communityBoardCopyScopeChecked('attachments') ? ' checked' : ''; ?> data-copy-scope-item data-copy-scope="attachments">
                        <label for="community_board_copy_scope_attachments" class="btn btn-choice-light btn-group-middle">
                            <?php echo sr_admin_choice_label_html('첨부파일'); ?>
                        </label>
                    </span>
                    <span class="filtering-toggle-item">
                        <input id="community_board_copy_scope_series" type="checkbox" name="copy_scope[]" value="series" class="form-choice-toggle-input sr-only"<?php echo $communityBoardCopyScopeChecked('series') ? ' checked' : ''; ?> data-copy-scope-item data-copy-scope="series">
                        <label for="community_board_copy_scope_series" class="btn btn-choice-light btn-group-end">
                            <?php echo sr_admin_choice_label_html('시리즈'); ?>
                        </label>
                    </span>
                </div>
                <p class="form-help"><?php echo sr_e('설정만 선택하면 바로 복사하고, 게시글+댓글을 선택하면 여러 단계에 나누어 처리합니다.'); ?></p>
            </div>
        </div>
        <?php if ($communityBoardCopyStorageWarnings !== []) { ?>
            <div class="alert alert-warning" data-copy-storage-warning<?php echo $communityBoardCopyScopeChecked('attachments') ? '' : ' hidden'; ?>>
                <div>
                    <?php foreach ($communityBoardCopyStorageWarnings as $storageWarning) { ?>
                        <p><?php echo sr_e($storageWarning); ?></p>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
        <?php if ((int) ($copyCounts['series'] ?? 0) > 0) { ?>
            <div class="form-row" data-copy-series-detail<?php echo $communityBoardCopyScopeChecked('series') ? '' : ' hidden'; ?>>
                <div class="form-label form-label-help">
                    <button type="button" class="admin-label-help-button" aria-label="<?php echo sr_e($communityBoardCopyHelpOpenLabel); ?>" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo sr_e($communityBoardCopyHelp['series']['id']); ?>" data-overlay="#<?php echo sr_e($communityBoardCopyHelp['series']['id']); ?>"><?php echo sr_material_icon_html('help'); ?></button>
                    <span><?php echo sr_e('시리즈 제목'); ?></span>
                </div>
                <div class="form-field">
                    <p class="form-help"><?php echo sr_e('새 게시판에 만들 각 시리즈의 제목을 확인하세요.'); ?></p>
                    <?php foreach ($communityBoardCopySeriesSuggestions as $seriesSuggestion) { ?>
                        <?php $seriesId = (int) $seriesSuggestion['series_id']; ?>
                        <div class="admin-setting-unit admin-setting-unit-wide">
                            <label class="form-label" for="community_board_copy_series_title_<?php echo sr_e((string) $seriesId); ?>"><?php echo sr_e('새 시리즈 제목'); ?> <span class="sr-required-label" data-copy-series-required-label<?php echo $communityBoardCopyScopeChecked('series') ? '' : ' hidden'; ?>><?php echo sr_e('(필수)'); ?></span></label>
                            <input id="community_board_copy_series_title_<?php echo sr_e((string) $seriesId); ?>" type="text" name="community_series_titles[<?php echo sr_e((string) $seriesId); ?>]" value="<?php echo sr_e((string) $seriesSuggestion['title']); ?>" class="form-input form-control-full" maxlength="160"<?php echo $communityBoardCopyScopeChecked('series') ? ' required' : ''; ?> data-copy-series-input>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </section>
    <div class="form-sticky-actions form-actions form-actions-split admin-community-board-copy-actions">
        <div class="admin-community-board-copy-secondary-actions">
            <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>" class="btn btn-solid-light"><?php echo sr_e('취소'); ?></a>
            <a href="<?php echo sr_e(sr_url('/admin/community/board-copy-jobs')); ?>" class="btn btn-outline-secondary"><?php echo sr_e('작업 관리'); ?></a>
        </div>
        <div class="admin-community-board-copy-submit-actions">
            <button type="submit" class="btn btn-solid-primary admin-community-board-copy-primary" data-copy-submit-label data-copy-settings-label="<?php echo sr_e($communityBoardCopySettingsSubmitLabel); ?>" data-copy-start-label="<?php echo sr_e($communityBoardCopyStartSubmitLabel); ?>"><?php echo sr_e(!empty($values['copy_posts_comments']) ? $communityBoardCopyStartSubmitLabel : $communityBoardCopySettingsSubmitLabel); ?></button>
        </div>
    </div>
</form>

<?php foreach ($communityBoardCopyHelp as $communityBoardCopyHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $communityBoardCopyHelpModal['id'], (string) $communityBoardCopyHelpModal['title'], (string) $communityBoardCopyHelpModal['body']); ?>
<?php } ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-copy-scope-group]').forEach(function (group) {
        var form = group.closest('form');
        var all = group.querySelector('[data-copy-scope-all]');
        var items = Array.prototype.slice.call(group.querySelectorAll('[data-copy-scope-item]'));
        var modeInput = form ? form.querySelector('[data-copy-mode-input]') : null;
        var storageWarning = form ? form.querySelector('[data-copy-storage-warning]') : null;
        var seriesDetail = form ? form.querySelector('[data-copy-series-detail]') : null;
        var submitLabel = form ? form.querySelector('[data-copy-submit-label]') : null;
        var itemByScope = function (scope) {
            return items.find(function (item) {
                return item.getAttribute('data-copy-scope') === scope;
            }) || null;
        };
        var sync = function (source) {
            var posts = itemByScope('posts_comments');
            var attachments = itemByScope('attachments');
            var series = itemByScope('series');
            if (source === all && all) {
                items.forEach(function (item) {
                    item.checked = all.checked;
                });
            }
            if (source === attachments && attachments && attachments.checked && posts) {
                posts.checked = true;
            }
            if (source === series && series && series.checked && posts) {
                posts.checked = true;
            }
            if (source === posts && posts && !posts.checked) {
                if (attachments) {
                    attachments.checked = false;
                }
                if (series) {
                    series.checked = false;
                }
            }
            var allChecked = items.length > 0 && items.every(function (item) {
                return item.checked;
            });
            if (all) {
                all.checked = allChecked;
            }
            var hasPosts = !!(posts && posts.checked);
            var hasAttachments = !!(attachments && attachments.checked);
            var hasSeries = !!(series && series.checked);
            if (modeInput) {
                modeInput.value = hasPosts ? 'full' : 'settings';
            }
            if (storageWarning) {
                storageWarning.hidden = !hasAttachments;
            }
            if (seriesDetail) {
                seriesDetail.hidden = !hasSeries;
            }
            if (form) {
                form.querySelectorAll('[data-copy-series-input]').forEach(function (input) {
                    input.required = hasSeries;
                });
                form.querySelectorAll('[data-copy-series-required-label]').forEach(function (label) {
                    label.hidden = !hasSeries;
                });
            }
            if (submitLabel) {
                submitLabel.textContent = hasPosts
                    ? (submitLabel.getAttribute('data-copy-start-label') || '복사 시작')
                    : (submitLabel.getAttribute('data-copy-settings-label') || '설정만 복사');
            }
        };
        if (!form) {
            return;
        }
        if (all) {
            all.addEventListener('change', function () {
                sync(all);
            });
        }
        items.forEach(function (item) {
            item.addEventListener('change', function () {
                sync(item);
            });
        });
        sync(null);
    });
});
</script>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
