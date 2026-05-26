<?php

$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$adminPageTitle = $communitySettingsPage === 'levels' ? sr_t('community::ui.community.c1f4d427') : sr_t('community::ui.community.settings.af4e5ebd');
$assetModuleChoiceOptions = [];
foreach ($assetModuleOptions as $assetModule => $assetOption) {
    $assetModuleChoiceOptions[(string) $assetModule] = (string) ($assetOption['label'] ?? $assetModule);
}
$assetDeductionPriorityLabels = [];
foreach (sr_community_asset_deduction_order() as $assetModule) {
    if (isset($assetModuleChoiceOptions[$assetModule])) {
        $assetDeductionPriorityLabels[] = $assetModuleChoiceOptions[$assetModule];
    }
}
$assetDeductionPriorityHelp = $assetDeductionPriorityLabels !== []
    ? sr_t('community::ui.text.706623d8') . implode(', ', $assetDeductionPriorityLabels)
    : sr_t('community::ui.text.3e195cdd');
$communityAssetAuditUrl = sr_admin_asset_settings_audit_url('community.settings.asset_settings.updated', 'module', 'community');
$messageWritePolicyLabels = [
    'member' => sr_t('community::ui.message_policy.member'),
    'group' => sr_t('community::ui.message_policy.group'),
    'disabled' => sr_t('community::ui.message_policy.disabled'),
];
$levelScoreHelpModalId = 'community-level-score-help-modal';
$levelScoreHelpBodyHtml = '<p>' . sr_e(sr_t('community::ui.level_score_help_global_default')) . '</p>'
    . '<p>' . sr_e(sr_t('community::ui.level_score_help_formula')) . '</p>'
    . '<ul>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_priority')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_group_initial')) . '</li>'
    . '<li>' . sr_e(sr_t('community::ui.level_score_help_board_initial')) . '</li>'
    . '</ul>';
$communitySettingsHelpOpenLabel = sr_t('community::help.open');
$communitySettingsHelpButtonHtml = static function (string $label, string $modalId) use ($communitySettingsHelpOpenLabel): string {
    return '<button type="button" class="btn btn-icon-xs btn-ghost-default admin-label-help-button" aria-label="' . sr_e($label . ' ' . $communitySettingsHelpOpenLabel) . '" aria-haspopup="dialog" aria-expanded="false" aria-controls="' . sr_e($modalId) . '" data-overlay="#' . sr_e($modalId) . '">'
        . sr_material_icon_html('help')
        . '</button>';
};
$communitySettingsHelpBodyHtml = static function (array $keys): string {
    $html = '';
    foreach ($keys as $key) {
        $html .= '<p>' . sr_e(sr_t((string) $key)) . '</p>';
    }

    return $html;
};
$communitySettingsHelp = [
    'level_feature' => [
        'id' => 'community_settings_help_level_feature',
        'title' => sr_t('community::help.level_feature.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_feature.body.1', 'community::help.level_feature.body.2']),
    ],
    'level_auto_recalculate' => [
        'id' => 'community_settings_help_level_auto_recalculate',
        'title' => sr_t('community::help.level_auto_recalculate.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_auto_recalculate.body.1', 'community::help.level_auto_recalculate.body.2']),
    ],
    'message_policy' => [
        'id' => 'community_settings_help_message_policy',
        'title' => sr_t('community::help.message_policy.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.message_policy.body.1', 'community::help.message_policy.body.2', 'community::help.message_policy.body.3', 'community::help.message_policy.body.4']),
    ],
    'message_group' => [
        'id' => 'community_settings_help_message_group',
        'title' => sr_t('community::help.message_group.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.message_group.body.1', 'community::help.message_group.body.2']),
    ],
    'message_min_level' => [
        'id' => 'community_settings_help_message_min_level',
        'title' => sr_t('community::help.message_min_level.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.message_min_level.body.1', 'community::help.message_min_level.body.2']),
    ],
    'nickname' => [
        'id' => 'community_settings_help_nickname',
        'title' => sr_t('community::help.nickname.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.nickname.body.1', 'community::help.nickname.body.2']),
    ],
    'asset_settings' => [
        'id' => 'community_settings_help_asset_settings',
        'title' => sr_t('community::help.asset_settings.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.asset_settings.body.1', 'community::help.asset_settings.body.2', 'community::help.asset_settings.body.3']),
    ],
    'layout' => [
        'id' => 'community_settings_help_layout',
        'title' => sr_t('community::help.layout.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.layout.body.1', 'community::help.layout.body.2']),
    ],
    'level_min_score' => [
        'id' => 'community_settings_help_level_min_score',
        'title' => sr_t('community::help.level_min_score.title'),
        'body' => $communitySettingsHelpBodyHtml(['community::help.level_min_score.body.1', 'community::help.level_min_score.body.2']),
    ],
];
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<?php echo sr_admin_feedback_toasts($notice, $errors); ?>

<?php if ($communitySettingsPage === 'settings') { ?>
<form method="post" action="<?php echo sr_e(sr_url('/admin/community/settings')); ?>" class="admin-form ui-form-theme">
    <?php echo sr_csrf_field(); ?>
    <input type="hidden" name="intent" value="save_settings">

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.community.active.9b707ae1'), $communitySettingsHelp['level_feature']['id']); ?><span><?php echo sr_e(sr_t('community::ui.community.active.9b707ae1')); ?></span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_level_enabled">
                                            <input id="modules_community_admin_settings_level_enabled" type="checkbox" name="level_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['level_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.community.active.9b707ae1')); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.f9447e05'), $communitySettingsHelp['level_auto_recalculate']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.f9447e05')); ?></span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_level_auto_recalculate">
                                            <input id="modules_community_admin_settings_level_auto_recalculate" type="checkbox" name="level_auto_recalculate" value="1" class="form-checkbox"<?php echo !empty($settings['level_auto_recalculate']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.f9447e05')); ?>
                                        </label>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_level_post_score', sr_t('community::ui.text.99092cba'), $levelScoreHelpModalId, sr_t('community::ui.level_score_help_open'), true); ?>
            <div class="admin-form-field">
                <input id="community_admin_settings_level_post_score" type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_post_score']); ?>" required class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_level_comment_score', sr_t('community::ui.text.96af1f5c'), $levelScoreHelpModalId, sr_t('community::ui.level_score_help_open'), true); ?>
            <div class="admin-form-field">
                <input id="community_admin_settings_level_comment_score" type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_comment_score']); ?>" required class="form-input">
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('community::ui.text.919bd592')); ?></h2>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_message_write_policy', sr_t('community::ui.text.31edcf4a'), $communitySettingsHelp['message_policy']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="admin-form-field">
                <select id="community_admin_settings_message_write_policy" name="message_write_policy" class="form-select" data-community-message-policy>
                                    <?php foreach (sr_community_message_write_policy_values() as $policy) { ?>
                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === (string) $settings['message_write_policy'] ? ' selected' : ''; ?>><?php echo sr_e((string) ($messageWritePolicyLabels[$policy] ?? sr_admin_code_label($policy, 'policy'))); ?></option>
                                    <?php } ?>
                                </select>
                <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.message_policy.help')); ?></p>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_message_write_group_keys', sr_t('community::ui.member.69b1363d'), $communitySettingsHelp['message_group']['id'], $communitySettingsHelpOpenLabel); ?>
            <div class="admin-form-field">
                <?php echo sr_admin_member_group_key_select_html('community_admin_settings_message_write_group_keys', 'message_write_group_keys', is_array($settings['message_write_group_keys'] ?? null) ? $settings['message_write_group_keys'] : [], $enabledMemberGroups); ?>
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_message_write_min_level', sr_t('community::ui.text.c96c86df'), $communitySettingsHelp['message_min_level']['id'], $communitySettingsHelpOpenLabel, true); ?>
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
        <h2><?php echo sr_e(sr_t('community::ui.nickname.section')); ?></h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <div class="form-label admin-form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.nickname.enabled'), $communitySettingsHelp['nickname']['id']); ?><span><?php echo sr_e(sr_t('community::ui.nickname.enabled')); ?></span></div>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_nickname_enabled">
                        <input id="modules_community_admin_settings_nickname_enabled" type="checkbox" name="nickname_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['nickname_enabled']) ? ' checked' : ''; ?>>
                        <?php echo sr_admin_choice_label_html(sr_t('community::ui.nickname.enabled.choice')); ?>
                    </label>
                    <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.nickname.enabled.help')); ?></p>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2>
            <span><?php echo sr_e(sr_t('community::ui.member.415a098e')); ?></span>
            <span class="admin-form-actions">
                <a href="<?php echo sr_e($communityAssetAuditUrl); ?>" class="btn btn-sm btn-solid-light"><?php echo sr_e('자산 변경 이력'); ?></a>
            </span>
        </h2>
        <div class="admin-form-grid">
            <?php foreach ([
                'post_reward' => sr_t('community::ui.text.a3cc976c'),
                'comment_reward' => sr_t('community::ui.text.bb39df0e'),
                'write_charge' => sr_t('community::ui.text.ce1392a2'),
                'comment_charge' => sr_t('community::ui.text.629c5136'),
                'paid_read' => sr_t('community::ui.text.c9b3e6f0'),
                'paid_attachment_download' => sr_t('community::ui.text.5b864b9e'),
            ] as $assetPrefix => $assetLabel) { ?>
                <?php $assetEnabledId = 'modules_community_admin_settings_' . (string) $assetPrefix . '_enabled'; ?>
                <?php $isRewardAsset = in_array((string) $assetPrefix, ['post_reward', 'comment_reward'], true); ?>
                <?php $selectedAssetModules = sr_community_asset_module_keys_from_value($settings[$assetPrefix . '_asset_module'] ?? '', true); ?>
                <div class="admin-form-row">
                    <div class="form-label admin-form-label-help"><?php echo $communitySettingsHelpButtonHtml($assetLabel, $communitySettingsHelp['asset_settings']['id']); ?><span><?php echo sr_e($assetLabel); ?></span></div>
                    <div class="admin-form-field">
                        <div class="admin-asset-setting-line">
                            <div class="admin-asset-setting-control<?php echo $isRewardAsset ? '' : ' admin-asset-setting-control-full'; ?>">
                                <div class="admin-asset-setting-primary">
                                    <label class="admin-form-check form-label" for="<?php echo sr_e($assetEnabledId); ?>">
                                        <input id="<?php echo sr_e($assetEnabledId); ?>" type="checkbox" name="<?php echo sr_e((string) $assetPrefix); ?>_enabled" value="1" class="form-checkbox"<?php echo !empty($settings[$assetPrefix . '_enabled']) ? ' checked' : ''; ?>>
                                        <?php echo sr_admin_choice_label_html($isRewardAsset ? ($assetPrefix === 'post_reward' ? sr_t('community::ui.active.3ed52f4b') : sr_t('community::ui.active.1549f7df')) : $assetLabel . sr_t('community::ui.active.d11d5dbb')); ?>
                                    </label>
                                    <?php if ($isRewardAsset) { ?>
                                        <div class="admin-asset-setting-target">
                                            <select name="<?php echo sr_e((string) $assetPrefix); ?>_asset_module" class="form-select">
                                                <option value=""><?php echo sr_e(sr_t('community::ui.text.3e195cdd')); ?></option>
                                                <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                    <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) ($settings[$assetPrefix . '_asset_module'] ?? '') === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    <?php } ?>
                                </div>
                                <?php if ($isRewardAsset) { ?>
                                    <div class="admin-asset-setting-secondary">
                                        <input type="number" name="<?php echo sr_e((string) $assetPrefix); ?>_amount" min="0" max="999999999" value="<?php echo sr_e((string) ($settings[$assetPrefix . '_amount'] ?? 0)); ?>" class="form-input admin-asset-setting-amount" aria-label="<?php echo sr_e(sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel])); ?>">
                                        <label class="admin-form-check form-label" for="<?php echo sr_e('modules_community_admin_settings_' . (string) $assetPrefix . '_reversal_enabled'); ?>">
                                            <input id="<?php echo sr_e('modules_community_admin_settings_' . (string) $assetPrefix . '_reversal_enabled'); ?>" type="checkbox" name="<?php echo sr_e((string) $assetPrefix); ?>_reversal_enabled" value="1" class="form-checkbox"<?php echo !empty($settings[$assetPrefix . '_reversal_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.delete.5cd8f702')); ?>
                                        </label>
                                    </div>
                                <?php } else { ?>
                                    <div class="admin-asset-setting-target" data-admin-asset-enable-target="#<?php echo sr_e($assetEnabledId); ?>">
                                        <?php echo sr_community_asset_grouped_amount_inputs_html('community_admin_settings_' . (string) $assetPrefix . '_asset_amounts', (string) $assetPrefix . '_asset_module', (string) $assetPrefix . '_amounts', $assetModuleOptions, $selectedAssetModules, $settings[$assetPrefix . '_amounts_json'] ?? '', (int) ($settings[$assetPrefix . '_amount'] ?? 0), sr_t('community::ui.asset.amount.0df01f4b', ['label' => $assetLabel]), sr_t('community::ui.text.3e195cdd')); ?>
                                    </div>
                                    <div class="admin-asset-setting-secondary">
                                        <input type="hidden" name="<?php echo sr_e((string) $assetPrefix); ?>_amount" value="<?php echo sr_e((string) ($settings[$assetPrefix . '_amount'] ?? 0)); ?>">
                                        <?php if ($assetPrefix === 'paid_read') { ?>
                                            <select name="paid_read_charge_policy" class="form-select admin-asset-setting-policy" aria-label="<?php echo sr_e(sr_t('community::ui.text.05ead7ab')); ?>">
                                                <option value="once"<?php echo (string) ($settings['paid_read_charge_policy'] ?? 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                                <option value="every_view"<?php echo (string) ($settings['paid_read_charge_policy'] ?? 'once') === 'every_view' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.53e8d077')); ?></option>
                                            </select>
                                        <?php } elseif ($assetPrefix === 'paid_attachment_download') { ?>
                                            <select name="paid_attachment_download_charge_policy" class="form-select admin-asset-setting-policy" aria-label="<?php echo sr_e(sr_t('community::ui.text.978f8b2e')); ?>">
                                                <option value="once"<?php echo (string) ($settings['paid_attachment_download_charge_policy'] ?? 'once') === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                                <option value="every_download"<?php echo (string) ($settings['paid_attachment_download_charge_policy'] ?? 'once') === 'every_download' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.e9d14df2')); ?></option>
                                            </select>
                                        <?php } ?>
                                    </div>
                                    <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('community::ui.text.b5361f64')); ?></h2>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html('community_admin_settings_layout_key', sr_t('community::ui.community.8f453af4'), $communitySettingsHelp['layout']['id'], $communitySettingsHelpOpenLabel, true); ?>
            <div class="admin-form-field">
                <select id="community_admin_settings_layout_key" name="layout_key" class="form-select">
                                    <?php foreach ($communityLayoutOptions as $layoutKey => $layoutOption) { ?>
                                        <option value="<?php echo sr_e((string) $layoutKey); ?>"<?php echo (string) $settings['layout_key'] === (string) $layoutKey ? ' selected' : ''; ?>>
                                            <?php echo sr_e((string) ($layoutOption['label'] ?? $layoutKey)); ?>
                                        </option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_post_editor">게시글 에디터 <span class="sr-required-label">(필수)</span></label>
            <div class="admin-form-field">
                <select id="community_admin_settings_post_editor" name="post_editor" class="form-select" required>
                    <?php foreach ($editorOptions as $editorKey => $editorLabel) { ?>
                        <option value="<?php echo sr_e((string) $editorKey); ?>"<?php echo (string) ($settings['post_editor'] ?? 'textarea') === (string) $editorKey ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) $editorLabel); ?>
                        </option>
                    <?php } ?>
                </select>
                <p class="admin-form-help">새 게시판 그룹과 새 게시판을 만들 때 참고할 전역 기본값입니다. 기존 게시판 값은 자동 변경되지 않습니다.</p>
            </div>
        </div>
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.settings.save.59aa86cd')); ?></button>
    </div>
</form>

<?php echo sr_admin_help_modal_html($levelScoreHelpModalId, sr_t('community::ui.level_score_help_title'), $levelScoreHelpBodyHtml); ?>
<?php foreach ($communitySettingsHelp as $communitySettingsHelpModal) { ?>
    <?php echo sr_admin_help_modal_html((string) $communitySettingsHelpModal['id'], (string) $communitySettingsHelpModal['title'], (string) $communitySettingsHelpModal['body']); ?>
<?php } ?>

<?php } ?>

<?php if ($communitySettingsPage === 'levels') { ?>
<?php $communityLevelEnabled = !empty($settings['level_enabled']); ?>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.text.b2845de5')); ?></h2>
    </div>
    <p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.level_definitions_help')); ?></p>
    <form id="community-level-definitions-form" method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>">
        <?php if ($levels !== []) { ?>
            <?php echo sr_csrf_field(); ?>
            <input type="hidden" name="intent" value="save_level_definitions">
        <?php } ?>
        <div class="table-wrapper">
        <table class="table">
            <thead class="ui-table-head">
                <tr>
                    <th><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?></th>
                    <th><span class="admin-form-check admin-form-label-help"><?php echo $communitySettingsHelpButtonHtml(sr_t('community::ui.text.2ba8a858'), $communitySettingsHelp['level_min_score']['id']); ?><span><?php echo sr_e(sr_t('community::ui.text.2ba8a858')); ?></span></span></th>
                    <th><?php echo sr_e(sr_t('community::ui.status.e10195a1')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($levels === []) { ?>
                    <tr><td colspan="4" class="admin-empty-state"><?php echo sr_e(sr_t('community::ui.text.b4915f04')); ?></td></tr>
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
    </form>

    <form
        id="community-level-recalculate-form"
        method="post"
        action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>"
        data-community-level-recalculate-form
        data-recalculate-url="<?php echo sr_e(sr_url('/admin/community/levels/recalculate')); ?>"
        data-batch-size="50"
    >
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="recalculate_levels">
    </form>
    <div class="admin-list-actions">
        <button type="submit" form="community-level-recalculate-form" class="btn btn-solid-light"<?php echo $communityLevelEnabled ? '' : ' disabled'; ?> data-community-level-recalculate-submit><?php echo sr_e(sr_t('community::ui.member.9fba6ddf')); ?></button>
        <?php if ($levels !== []) { ?>
            <button type="submit" form="community-level-definitions-form" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.save.bca4cb2b')); ?></button>
        <?php } ?>
    </div>
    <div class="community-level-recalculate-progress" data-community-level-recalculate-progress hidden>
        <p data-community-level-recalculate-status role="status" aria-live="polite"><?php echo sr_e(sr_t('community::ui.level_recalculate_ready')); ?></p>
        <progress value="0" max="100" data-community-level-recalculate-meter></progress>
    </div>
</section>
<?php echo sr_admin_help_modal_html((string) $communitySettingsHelp['level_min_score']['id'], (string) $communitySettingsHelp['level_min_score']['title'], (string) $communitySettingsHelp['level_min_score']['body']); ?>
<script>
(function () {
    var form = document.querySelector('[data-community-level-recalculate-form]');
    if (!form || !window.fetch || !window.FormData) {
        return;
    }

    var button = document.querySelector('[data-community-level-recalculate-submit]');
    var progress = document.querySelector('[data-community-level-recalculate-progress]');
    var status = document.querySelector('[data-community-level-recalculate-status]');
    var meter = document.querySelector('[data-community-level-recalculate-meter]');
    var labels = <?php echo json_encode([
        'start' => sr_t('community::ui.level_recalculate_start'),
        'running' => sr_t('community::ui.level_recalculate_running'),
        'running_unknown' => sr_t('community::ui.level_recalculate_running_unknown'),
        'error' => sr_t('community::ui.level_recalculate_error'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE); ?>;

    function text(template, values) {
        return Object.keys(values).reduce(function (message, key) {
            return message.replace('{' + key + '}', String(values[key]));
        }, template);
    }

    function updateStatus(message) {
        if (status) {
            status.textContent = message;
        }
    }

    function updateProgress(processed, total, done) {
        if (!meter) {
            return;
        }

        meter.value = total > 0 ? Math.min(100, Math.round((processed / total) * 100)) : (done ? 100 : 0);
    }

    form.addEventListener('submit', function (event) {
        if (button && button.disabled) {
            event.preventDefault();
            return;
        }

        event.preventDefault();

        var url = form.getAttribute('data-recalculate-url') || '';
        var batchSize = parseInt(form.getAttribute('data-batch-size') || '50', 10);
        if (!url || !Number.isFinite(batchSize) || batchSize < 1) {
            return;
        }

        var cursor = 0;
        var processed = 0;
        var total = 0;

        if (button) {
            button.disabled = true;
        }
        if (progress) {
            progress.hidden = false;
        }
        updateProgress(0, 0, false);
        updateStatus(labels.start);

        function runBatch() {
            var body = new FormData(form);
            body.set('cursor', String(cursor));
            body.set('batch_size', String(batchSize));
            body.set('processed_total', String(processed));

            return window.fetch(url, {
                method: 'POST',
                body: body,
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                },
            }).then(function (response) {
                return response.json().then(function (payload) {
                    if (!response.ok || !payload || !payload.ok) {
                        throw new Error(payload && payload.message ? payload.message : labels.error);
                    }

                    return payload;
                });
            }).then(function (payload) {
                processed = Number(payload.processed_total || processed);
                total = Number(payload.total || total);
                cursor = Number(payload.next_cursor || cursor);
                updateProgress(processed, total, !!payload.done);

                if (payload.done) {
                    updateStatus(payload.message || text(labels.running, {
                        processed: processed,
                        total: total,
                    }));
                    if (button) {
                        button.disabled = false;
                    }
                    return;
                }

                updateStatus(total > 0 ? text(labels.running, {
                    processed: processed,
                    total: total,
                }) : text(labels.running_unknown, {
                    processed: processed,
                }));
                return runBatch();
            }).catch(function (error) {
                updateStatus(error && error.message ? error.message : labels.error);
                if (button) {
                    button.disabled = false;
                }
            });
        }

        runBatch();
    });
})();
</script>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
