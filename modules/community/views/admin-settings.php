<?php

$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$adminPageTitle = $communitySettingsPage === 'levels' ? '커뮤니티 레벨 정의' : '커뮤니티 설정';
$messageWriteGroupKeysValue = implode(', ', is_array($settings['message_write_group_keys'] ?? null) ? $settings['message_write_group_keys'] : []);
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<p>
    <a href="<?php echo sr_e(sr_url('/admin/community/boards')); ?>">게시판 관리</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/community/board-groups')); ?>">게시판 그룹 관리</a>
    |
    <a href="<?php echo sr_e(sr_url('/admin/community/levels')); ?>">레벨 정의</a>
</p>

<?php if ($enabledMemberGroups !== []) { ?>
    <section>
        <h2>사용 가능한 회원 그룹 key</h2>
        <ul>
            <?php foreach ($enabledMemberGroups as $memberGroup) { ?>
                <li>
                    <?php echo sr_e((string) $memberGroup['group_key']); ?>
                    - <?php echo sr_e((string) $memberGroup['title']); ?>
                </li>
            <?php } ?>
        </ul>
    </section>
<?php } ?>

<?php if ($communitySettingsPage === 'settings') { ?>
<form method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="admin-card card">
        <h2>레벨</h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">커뮤니티 레벨 사용</span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label">
                        <input type="checkbox" name="level_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['level_enabled']) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('커뮤니티 레벨 사용'); ?>
                    </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="admin-form-label"><span class="form-label">게시글/댓글 활동 후 레벨 자동 재계산</span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label">
                        <input type="checkbox" name="level_auto_recalculate" value="1" class="form-checkbox"<?php echo !empty($settings['level_auto_recalculate']) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html('게시글/댓글 활동 후 레벨 자동 재계산'); ?>
                    </label>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">게시글 점수</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">게시글 점수</span>
                <input type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_post_score']); ?>">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">댓글 점수</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">댓글 점수</span>
                <input type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_comment_score']); ?>">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">그룹+레벨 판정</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">그룹+레벨 판정</span>
                <select name="access_condition_priority">
                    <?php foreach (sr_community_access_condition_priority_values() as $priority) { ?>
                        <option value="<?php echo sr_e($priority); ?>"<?php echo $priority === (string) $settings['access_condition_priority'] ? ' selected' : ''; ?>><?php echo sr_e($priority); ?></option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>쪽지</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">발송 정책</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">발송 정책</span>
                <select name="message_write_policy">
                    <?php foreach (sr_community_message_write_policy_values() as $policy) { ?>
                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === (string) $settings['message_write_policy'] ? ' selected' : ''; ?>><?php echo sr_e($policy); ?></option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">발송 그룹 key</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">발송 그룹 key</span>
                <input type="text" name="message_write_group_keys" maxlength="1000" value="<?php echo sr_e($messageWriteGroupKeysValue); ?>" placeholder="regular_member, vip">
                </label>
            </div>
        </div>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">발송 최소 레벨</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">발송 최소 레벨</span>
                <select name="message_write_min_level">
                    <?php for ($levelValue = 0; $levelValue <= sr_community_max_level_value(); $levelValue++) { ?>
                        <option value="<?php echo sr_e((string) $levelValue); ?>"<?php echo (int) $settings['message_write_min_level'] === $levelValue ? ' selected' : ''; ?>>
                            <?php echo sr_e('레벨 ' . (string) $levelValue); ?>
                        </option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>화면</h2>
        <div class="admin-form-row">
            <div class="admin-form-label"><span class="form-label">커뮤니티 테마</span></div>
            <div class="admin-form-field">
                <label>
                    <span class="sr-only">커뮤니티 테마</span>
                <select name="theme_key">
                    <?php foreach ($communityThemeOptions as $themeKey => $themeOption) { ?>
                        <option value="<?php echo sr_e((string) $themeKey); ?>"<?php echo (string) $settings['theme_key'] === (string) $themeKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($themeOption['label'] ?? $themeKey)); ?>
                        </option>
                    <?php } ?>
                </select>
                </label>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary">설정 저장</button>
    </div>
</form>
<?php } ?>

<?php if ($communitySettingsPage === 'levels') { ?>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">레벨 정의</h2>
    </div>
    <?php if ($levels === []) { ?>
        <div class="table-wrapper">
        <table class="table">
            <tbody>
                <tr><td class="admin-empty-state">레벨 테이블이 없거나 정의된 레벨이 없습니다.</td></tr>
            </tbody>
        </table>
        </div>
    <?php } else { ?>
        <form method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>">
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_level_definitions">
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
                </tbody>
            </table>
            </div>
            <div class="admin-list-actions">
                <button type="submit" class="btn btn-solid-primary">레벨 정의 저장</button>
            </div>
        </form>
    <?php } ?>

    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="recalculate_levels">
        <div class="admin-list-actions">
            <button type="submit" class="btn btn-surface-default-soft">최근 회원 레벨 재계산</button>
        </div>
    </form>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
