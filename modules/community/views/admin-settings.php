<?php

$communitySettingsPage = isset($communitySettingsPage) ? (string) $communitySettingsPage : 'settings';
$adminPageTitle = $communitySettingsPage === 'levels' ? sr_t('community::ui.community.c1f4d427') : sr_t('community::ui.community.settings.af4e5ebd');
$accessConditionPriorityLabels = [
    'both_required' => sr_t('community::ui.text.e11baa69'),
    'group_first' => sr_t('community::ui.text.eeebd1cf'),
    'level_first' => sr_t('community::ui.text.e6e726db'),
];
$accessConditionPriorityDescriptions = [
    'both_required' => sr_t('community::ui.settings.c2fb86ae'),
    'group_first' => sr_t('community::ui.text.e111bd78'),
    'level_first' => sr_t('community::ui.text.dce86ee3'),
];
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
    ? sr_t('community::ui.text.706623d8') . implode(' > ', $assetDeductionPriorityLabels)
    : sr_t('community::ui.text.3e195cdd');
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
        <h2><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.community.active.9b707ae1')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_level_enabled">
                                            <input id="modules_community_admin_settings_level_enabled" type="checkbox" name="level_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['level_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.community.active.9b707ae1')); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.f9447e05')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_level_auto_recalculate">
                                            <input id="modules_community_admin_settings_level_auto_recalculate" type="checkbox" name="level_auto_recalculate" value="1" class="form-checkbox"<?php echo !empty($settings['level_auto_recalculate']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.text.f9447e05')); ?>
                                        </label>
                </div>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_level_post_score"><?php echo sr_e(sr_t('community::ui.text.99092cba')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="community_admin_settings_level_post_score" type="number" name="level_post_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_post_score']); ?>" required class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_level_comment_score"><?php echo sr_e(sr_t('community::ui.text.96af1f5c')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
            <div class="admin-form-field">
                <input id="community_admin_settings_level_comment_score" type="number" name="level_comment_score" min="0" max="10000" value="<?php echo sr_e((string) $settings['level_comment_score']); ?>" required class="form-input">
            </div>
        </div>
        <div class="admin-form-row">
            <?php echo sr_admin_form_label_help_html($accessConditionPriorityInputId, sr_t('community::ui.text.98a7801d'), $accessConditionPriorityHelpModalId, sr_t('community::ui.text.60c97100')); ?>
            <div class="admin-form-field">
                <select id="<?php echo sr_e($accessConditionPriorityInputId); ?>" name="access_condition_priority" class="form-select">
                                    <?php foreach (sr_community_access_condition_priority_values() as $priority) { ?>
                                        <option value="<?php echo sr_e($priority); ?>"<?php echo $priority === (string) $settings['access_condition_priority'] ? ' selected' : ''; ?>><?php echo sr_e((string) ($accessConditionPriorityLabels[$priority] ?? $priority)); ?></option>
                                    <?php } ?>
                                </select>
                                <small class="admin-form-help"><?php echo sr_e(sr_t('community::ui.text.b64f0562')); ?> <?php echo sr_e((string) ($accessConditionPriorityLabels[$currentAccessConditionPriority] ?? $currentAccessConditionPriority)); ?><?php echo sr_e(sr_t('community::ui.settings.999d80a0')); ?></small>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('community::ui.text.919bd592')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_message_write_policy"><?php echo sr_e(sr_t('community::ui.text.31edcf4a')); ?></label>
            <div class="admin-form-field">
                <select id="community_admin_settings_message_write_policy" name="message_write_policy" class="form-select">
                                    <?php foreach (sr_community_message_write_policy_values() as $policy) { ?>
                                        <option value="<?php echo sr_e($policy); ?>"<?php echo $policy === (string) $settings['message_write_policy'] ? ' selected' : ''; ?>><?php echo sr_e($policy); ?></option>
                                    <?php } ?>
                                </select>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_message_write_group_keys"><?php echo sr_e(sr_t('community::ui.member.69b1363d')); ?></label>
            <div class="admin-form-field">
                <?php echo sr_admin_member_group_key_select_html('community_admin_settings_message_write_group_keys', 'message_write_group_keys', is_array($settings['message_write_group_keys'] ?? null) ? $settings['message_write_group_keys'] : [], $enabledMemberGroups); ?>
            </div>
        </div>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_message_write_min_level"><?php echo sr_e(sr_t('community::ui.text.c96c86df')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
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
        <h2><?php echo sr_e(sr_t('community::ui.member.415a098e')); ?></h2>
        <div class="admin-form-grid">
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.a3cc976c')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_post_reward_enabled">
                                            <input id="modules_community_admin_settings_post_reward_enabled" type="checkbox" name="post_reward_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['post_reward_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.active.3ed52f4b')); ?>
                                        </label>
                                        <select name="post_reward_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value=""><?php echo sr_e(sr_t('community::ui.text.3e195cdd')); ?></option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['post_reward_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="post_reward_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['post_reward_amount']); ?>" class="form-input">
                                        <label class="admin-form-check form-label" for="modules_community_admin_settings_post_reward_reversal_enabled">
                                            <input id="modules_community_admin_settings_post_reward_reversal_enabled" type="checkbox" name="post_reward_reversal_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['post_reward_reversal_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.delete.5cd8f702')); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.bb39df0e')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_comment_reward_enabled">
                                            <input id="modules_community_admin_settings_comment_reward_enabled" type="checkbox" name="comment_reward_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['comment_reward_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.active.1549f7df')); ?>
                                        </label>
                                        <select name="comment_reward_asset_module" class="form-select">
                                            <?php if ($assetModuleOptions === []) { ?>
                                                <option value=""><?php echo sr_e(sr_t('community::ui.text.3e195cdd')); ?></option>
                                            <?php } ?>
                                            <?php foreach ($assetModuleOptions as $assetModule => $assetOption) { ?>
                                                <option value="<?php echo sr_e((string) $assetModule); ?>"<?php echo (string) $settings['comment_reward_asset_module'] === (string) $assetModule ? ' selected' : ''; ?>><?php echo sr_e((string) $assetOption['label']); ?></option>
                                            <?php } ?>
                                        </select>
                                        <input type="number" name="comment_reward_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['comment_reward_amount']); ?>" class="form-input">
                                        <label class="admin-form-check form-label" for="modules_community_admin_settings_comment_reward_reversal_enabled">
                                            <input id="modules_community_admin_settings_comment_reward_reversal_enabled" type="checkbox" name="comment_reward_reversal_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['comment_reward_reversal_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.delete.5cd8f702')); ?>
                                        </label>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.ce1392a2')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_write_charge_enabled">
                                            <input id="modules_community_admin_settings_write_charge_enabled" type="checkbox" name="write_charge_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['write_charge_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.active.98b7dd61')); ?>
                                        </label>
                                        <?php $writeChargeAssetModules = sr_community_asset_module_keys_from_value($settings['write_charge_asset_module'] ?? 'point'); ?>
                                        <?php echo sr_admin_checkbox_list_html('community_admin_settings_write_charge_asset_module', 'write_charge_asset_module', $assetModuleChoiceOptions, $writeChargeAssetModules, sr_t('community::ui.text.3e195cdd')); ?>
                                        <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                                        <input type="number" name="write_charge_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['write_charge_amount']); ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.629c5136')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_comment_charge_enabled">
                                            <input id="modules_community_admin_settings_comment_charge_enabled" type="checkbox" name="comment_charge_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['comment_charge_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.active.5f0ef7af')); ?>
                                        </label>
                                        <?php $commentChargeAssetModules = sr_community_asset_module_keys_from_value($settings['comment_charge_asset_module'] ?? 'point'); ?>
                                        <?php echo sr_admin_checkbox_list_html('community_admin_settings_comment_charge_asset_module', 'comment_charge_asset_module', $assetModuleChoiceOptions, $commentChargeAssetModules, sr_t('community::ui.text.3e195cdd')); ?>
                                        <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                                        <input type="number" name="comment_charge_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['comment_charge_amount']); ?>" class="form-input">
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.c9b3e6f0')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_paid_read_enabled">
                                            <input id="modules_community_admin_settings_paid_read_enabled" type="checkbox" name="paid_read_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['paid_read_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.active.11ad75bb')); ?>
                                        </label>
                                        <?php $paidReadAssetModules = sr_community_asset_module_keys_from_value($settings['paid_read_asset_module'] ?? 'point'); ?>
                                        <?php echo sr_admin_checkbox_list_html('community_admin_settings_paid_read_asset_module', 'paid_read_asset_module', $assetModuleChoiceOptions, $paidReadAssetModules, sr_t('community::ui.text.3e195cdd')); ?>
                                        <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                                        <input type="number" name="paid_read_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['paid_read_amount']); ?>" class="form-input">
                                        <select name="paid_read_charge_policy" class="form-select">
                                            <option value="once"<?php echo (string) $settings['paid_read_charge_policy'] === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                            <option value="every_view"<?php echo (string) $settings['paid_read_charge_policy'] === 'every_view' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.53e8d077')); ?></option>
                                        </select>
                </div>
            </div>
            <div class="admin-form-row">
                <span class="form-label"><?php echo sr_e(sr_t('community::ui.text.5b864b9e')); ?></span>
                <div class="admin-form-field">
                    <label class="admin-form-check form-label" for="modules_community_admin_settings_paid_attachment_download_enabled">
                                            <input id="modules_community_admin_settings_paid_attachment_download_enabled" type="checkbox" name="paid_attachment_download_enabled" value="1" class="form-checkbox"<?php echo !empty($settings['paid_attachment_download_enabled']) ? ' checked' : ''; ?>>
                                            <?php echo sr_admin_choice_label_html(sr_t('community::ui.active.ac757b6f')); ?>
                                        </label>
                                        <?php $paidAttachmentDownloadAssetModules = sr_community_asset_module_keys_from_value($settings['paid_attachment_download_asset_module'] ?? 'point'); ?>
                                        <?php echo sr_admin_checkbox_list_html('community_admin_settings_paid_attachment_download_asset_module', 'paid_attachment_download_asset_module', $assetModuleChoiceOptions, $paidAttachmentDownloadAssetModules, sr_t('community::ui.text.3e195cdd')); ?>
                                        <p class="admin-form-help"><?php echo sr_e($assetDeductionPriorityHelp); ?></p>
                                        <input type="number" name="paid_attachment_download_amount" min="0" max="999999999" value="<?php echo sr_e((string) $settings['paid_attachment_download_amount']); ?>" class="form-input">
                                        <select name="paid_attachment_download_charge_policy" class="form-select">
                                            <option value="once"<?php echo (string) $settings['paid_attachment_download_charge_policy'] === 'once' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.6eb4fe4e')); ?></option>
                                            <option value="every_download"<?php echo (string) $settings['paid_attachment_download_charge_policy'] === 'every_download' ? ' selected' : ''; ?>><?php echo sr_e(sr_t('community::ui.text.e9d14df2')); ?></option>
                                        </select>
                </div>
            </div>
        </div>
    </section>

    <section class="admin-card card">
        <h2><?php echo sr_e(sr_t('community::ui.text.b5361f64')); ?></h2>
        <div class="admin-form-row">
            <label class="form-label" for="community_admin_settings_layout_key"><?php echo sr_e(sr_t('community::ui.community.8f453af4')); ?> <span class="sr-required-label"><?php echo sr_e(sr_t('community::ui.required.1f227c67')); ?></span></label>
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
    </section>

    <div class="admin-form-sticky-actions admin-form-actions admin-form-actions-primary">
        <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.settings.save.59aa86cd')); ?></button>
    </div>
</form>

<?php ob_start(); ?>
<p class="admin-form-help"><?php echo sr_e(sr_t('community::ui.settings.c4dcf2ad')); ?></p>
<div class="table-wrapper">
    <table class="table">
        <thead class="ui-table-head">
            <tr>
                <th><?php echo sr_e(sr_t('community::ui.text.3f5e5497')); ?></th>
                <th><?php echo sr_e(sr_t('community::ui.text.8c3f651d')); ?></th>
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
<?php echo sr_admin_help_modal_html($accessConditionPriorityHelpModalId, sr_t('community::ui.text.fd2ad6a5'), (string) ob_get_clean()); ?>
<?php } ?>

<?php if ($communitySettingsPage === 'levels') { ?>
<section class="admin-card admin-list-card card admin-list-form">
    <div class="card-header">
        <h2 class="card-title"><?php echo sr_e(sr_t('community::ui.text.b2845de5')); ?></h2>
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
                    <th><?php echo sr_e(sr_t('community::ui.text.7d97b5a5')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.name.253d1510')); ?></th>
                    <th><?php echo sr_e(sr_t('community::ui.text.2ba8a858')); ?></th>
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
        <?php if ($levels !== []) { ?>
            <div class="admin-list-actions">
                <button type="submit" class="btn btn-solid-primary"><?php echo sr_e(sr_t('community::ui.save.bca4cb2b')); ?></button>
            </div>
        <?php } ?>
    </form>

    <form method="post" action="<?php echo sr_e(sr_url('/admin/community/levels')); ?>">
        <?php echo sr_csrf_field(); ?>
        <input type="hidden" name="intent" value="recalculate_levels">
        <div class="admin-list-actions">
            <button type="submit" class="btn btn-solid-light"><?php echo sr_e(sr_t('community::ui.member.9fba6ddf')); ?></button>
        </div>
    </form>
</section>
<?php } ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
