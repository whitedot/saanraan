<?php

$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$adminPageTitle = $communitySettingsPage === 'levels' ? '커뮤니티 레벨 정의' : '커뮤니티 설정';
$accessConditionPriorityLabels = [
    'both_required' => '그룹과 레벨 모두 필요',
    'group_first' => '그룹 우선',
    'level_first' => '레벨 우선',
];
$accessConditionPriorityDescriptions = [
    'both_required' => '그룹 조건과 레벨 조건이 함께 설정된 경우 두 조건을 모두 만족해야 허용합니다.',
    'group_first' => '그룹 또는 레벨 중 하나만 만족해도 허용하며, 둘 다 만족하면 그룹 조건으로 통과한 것으로 기록합니다.',
    'level_first' => '그룹 또는 레벨 중 하나만 만족해도 허용하며, 둘 다 만족하면 레벨 조건으로 통과한 것으로 기록합니다.',
];
$accessConditionPriorityInputId = 'community_admin_settings_access_condition_priority';
$currentAccessConditionPriority = (string) $settings['access_condition_priority'];
$accessConditionPriorityHelpModalId = 'community_access_condition_priority_help_modal';
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communitySettingsPage === 'settings') { ?>
<form method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="admin-card card">
        <h2>레벨</h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label">커뮤니티 레벨 사용</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_level_enabled">
                                            <input id="modules_community_admin_settings_level_enabled" type="checkbox" name="level_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['level_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('커뮤니티 레벨 사용'); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">게시글/댓글 활동 후 레벨 자동 재계산</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_level_auto_recalculate">
                                            <input id="modules_community_admin_settings_level_auto_recalculate" type="checkbox" name="level_auto_recalculate" value="1" class="form-checkbox"<?php echo !empty($settings['level_auto_recalculate']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('게시글/댓글 활동 후 레벨 자동 재계산'); ?>
                                        </label>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_level_post_score">게시글 점수</label>
            <div class="admin-form-field">
                <input id="community_admin_settings_level_post_score" type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_post_score']); ?>" class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_level_comment_score">댓글 점수</label>
            <div class="admin-form-field">
                <input id="community_admin_settings_level_comment_score" type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_comment_score']); ?>" class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html($accessConditionPriorityInputId, '그룹+레벨 판정', $accessConditionPriorityHelpModalId, '판정 방식 보기'); ?>
            <div class="admin-form-field">
                <select id="<?php echo sr_e($accessConditionPriorityInputId); ?>" name="access_condition_priority" class="form-select">
                                    <?php foreach (sr_community_access_condition_priority_values() as $priority) { ?>
                                        <option value="<?php echo sr_e($priority); ?>"<?php echo $priority === (string) $settings['access_condition_priority'] ? ' selected' : ''; ?>><?php echo sr_e((string) ($accessConditionPriorityLabels[$priority] ?? $priority)); ?></option>
                                    <?php } ?>
                                </select>
                                <small class="admin-form-help">현재: <?php echo sr_e((string) ($accessConditionPriorityLabels[$currentAccessConditionPriority] ?? $currentAccessConditionPriority)); ?>. 그룹 key와 최소 레벨을 함께 설정한 접근 조건에 적용됩니다.</small>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>쪽지</h2>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_message_write_policy">발송 정책</label>
            <div class="admin-form-field">
                <select id="community_admin_settings_message_write_policy" name="message_write_policy" class="form-select">
                                    <?php foreach (sr_community_message_write_policy_values() as $policy) { ?>
                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === (string) $settings['message_write_policy'] ? ' selected' : ''; ?>><?php echo sr_e($policy); ?></option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_message_write_group_keys">발송 회원 그룹</label>
            <div class="admin-form-field">
                <?php echo sr_admin_member_group_key_select_html('community_admin_settings_message_write_group_keys', 'message_write_group_keys', is_array($settings['message_write_group_keys'] ?? null) ? $settings['message_write_group_keys'] : [], $enabledMemberGroups); ?>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_message_write_min_level">발송 최소 레벨</label>
            <div class="admin-form-field">
                <select id="community_admin_settings_message_write_min_level" name="message_write_min_level" class="form-select">
                                    <?php for ($levelValue = 0; $levelValue <= sr_community_max_level_value(); $levelValue++) { ?>
                                        <option value="<?php echo sr_e((string) $levelValue); ?>"<?php echo (int) $settings['message_write_min_level'] === $levelValue ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) $levelValue); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>회원 자산</h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label">게시글 적립</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_post_reward_enabled">
                                            <input id="modules_community_admin_settings_post_reward_enabled" type="checkbox" name="post_reward_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['post_reward_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('게시글 작성 적립 사용'); ?>
                                        </label>
                                        <select name="post_reward_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value="">활성 자산 모듈 없음</option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['post_reward_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="post_reward_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['post_reward_amount']); ?>" class="form-input">
                                        <label class="admin-form-check form-label" for="modules_community_admin_settings_post_reward_reversal_enabled">
                                            <input id="modules_community_admin_settings_post_reward_reversal_enabled" type="checkbox" name="post_reward_reversal_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['post_reward_reversal_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('숨김/삭제 시 적립 회수'); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">댓글 적립</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_comment_reward_enabled">
                                            <input id="modules_community_admin_settings_comment_reward_enabled" type="checkbox" name="comment_reward_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['comment_reward_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('댓글 작성 적립 사용'); ?>
                                        </label>
                                        <select name="comment_reward_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value="">활성 자산 모듈 없음</option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['comment_reward_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="comment_reward_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['comment_reward_amount']); ?>" class="form-input">
                                        <label class="admin-form-check form-label" for="modules_community_admin_settings_comment_reward_reversal_enabled">
                                            <input id="modules_community_admin_settings_comment_reward_reversal_enabled" type="checkbox" name="comment_reward_reversal_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['comment_reward_reversal_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('숨김/삭제 시 적립 회수'); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">글쓰기 차감</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_write_charge_enabled">
                                            <input id="modules_community_admin_settings_write_charge_enabled" type="checkbox" name="write_charge_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['write_charge_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('글쓰기 차감 사용'); ?>
                                        </label>
                                        <select name="write_charge_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value="">활성 자산 모듈 없음</option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['write_charge_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="write_charge_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['write_charge_amount']); ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">댓글 차감</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_comment_charge_enabled">
                                            <input id="modules_community_admin_settings_comment_charge_enabled" type="checkbox" name="comment_charge_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['comment_charge_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('댓글 차감 사용'); ?>
                                        </label>
                                        <select name="comment_charge_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value="">활성 자산 모듈 없음</option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['comment_charge_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="comment_charge_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['comment_charge_amount']); ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">유료 열람</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_paid_read_enabled">
                                            <input id="modules_community_admin_settings_paid_read_enabled" type="checkbox" name="paid_read_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['paid_read_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('게시글 유료 열람 사용'); ?>
                                        </label>
                                        <select name="paid_read_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value="">활성 자산 모듈 없음</option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['paid_read_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="paid_read_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['paid_read_amount']); ?>" class="form-input">
                                        <select name="paid_read_charge_policy" class="form-select">
                                            <option value="once"<?php echo (string) $settings['paid_read_charge_policy'] === 'once' ? ' selected' : ''; ?>>최초 1회</option>
                                            <option value="every_view"<?php echo (string) $settings['paid_read_charge_policy'] === 'every_view' ? ' selected' : ''; ?>>매 열람</option>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label">첨부 다운로드 차감</span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_paid_attachment_download_enabled">
                                            <input id="modules_community_admin_settings_paid_attachment_download_enabled" type="checkbox" name="paid_attachment_download_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['paid_attachment_download_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html('첨부 다운로드 차감 사용'); ?>
                                        </label>
                                        <select name="paid_attachment_download_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value="">활성 자산 모듈 없음</option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['paid_attachment_download_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="paid_attachment_download_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['paid_attachment_download_amount']); ?>" class="form-input">
                                        <select name="paid_attachment_download_charge_policy" class="form-select">
                                            <option value="once"<?php echo (string) $settings['paid_attachment_download_charge_policy'] === 'once' ? ' selected' : ''; ?>>최초 1회</option>
                                            <option value="every_download"<?php echo (string) $settings['paid_attachment_download_charge_policy'] === 'every_download' ? ' selected' : ''; ?>>매 다운로드</option>
                                        </select>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>화면</h2>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_theme_key">커뮤니티 테마</label>
            <div class="admin-form-field">
                <select id="community_admin_settings_theme_key" name="theme_key" class="form-select">
                                    <?php foreach ($communityThemeOptions as $themeKey => $themeOption) { ?>
                                        <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) $settings['theme_key'] === (string) $themeKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">설정 저장</button>
    </div>
</form>

<?php ob_start(); ?>
<p class="admin-form-help">게시판 접근 조건에서 그룹 key와 최소 레벨이 함께 설정된 경우에만 적용됩니다.</p>
<div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th>방식</th>
                <th>설명</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (sr_community_access_condition_priority_values() as $priority) { ?>
                <tr>
                    <td><?php echo sr_e((string) ($accessConditionPriorityLabels[$priority] ?? $priority)); ?></td>
                    <td><?php echo sr_e((string) ($accessConditionPriorityDescriptions[$priority] ?? '')); ?></td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
<?php echo sr_admin_help_modal_html($accessConditionPriorityHelpModalId, '그룹+레벨 판정 방식', (string) ob_get_clean()); ?>
<?php } ?>

<?php if ($communitySettingsPage === 'levels') { ?>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">레벨 정의</h2>
    </div>
    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>">
        <?php if ($levels !== []) { ?>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_level_definitions">
        <?php } ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th>레벨</th>
                    <th>이름</th>
                    <th>최소 점수</th>
                    <th>상태</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($levels === []) { ?>
                    <tr><td colspan="4" class="admin-empty-state">레벨 테이블이 없거나 정의된 레벨이 없습니다.</td></tr>
                <?php } else { ?>
                    <?php foreach ($levels as $level) { ?>
                        <tr>
                            <td><?php echo sr_e((string) $level['level_value']); ?></td>
                            <td><?php echo sr_e((string) $level['title']); ?></td>
                            <td>
                                <input
                                    type="number"
                                    name="level_min_score[<?php echo sr_e((string) $level['id']); ?>]"
                                    class="form-input"
                                    min="0"
                                    max="1000000000"
                                    value="<?php echo sr_e((string) $level['min_score']); ?>"
                                >
                            </td>
                            <td><?php echo sr_e(sr_admin_code_label((string) $level['status'], 'content_status')); ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </tbody>
        </table>
        </div>
        <?php if ($levels !== []) { ?>
            <div class="admin-list-actions">
                <button type="submit" class="btn btn-solid-primary">레벨 정의 저장</button>
            </div>
        <?php } ?>
    </form>

    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="recalculate_levels">
        <div class="admin-list-actions">
            <button type="submit" class="btn btn-solid-light">최근 회원 레벨 재계산</button>
        </div>
    </form>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
