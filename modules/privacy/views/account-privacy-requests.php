<?php

$pageTitle = sr_t('privacy::ui.privacy.216d449a');
$seo = [
    'title' => $pageTitle,
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, [
    'style_profile' => 'kit',
]);
?>
    <main>
        <h1><?php echo sr_e($pageTitle); ?></h1>

        <?php if ($notice !== '') { ?>
            <p><?php echo sr_e($notice); ?></p>
        <?php } ?>

        <?php if ($errors !== []) { ?>
            <ul>
                <?php foreach ($errors as $error) { ?>
                    <li><?php echo sr_e($error); ?></li>
                <?php } ?>
            </ul>
        <?php } ?>

        <form method="post" action="<?php echo sr_e(sr_url('/account/privacy-requests')); ?>">
            <?php echo sr_csrf_field(); ?>
            <p>
                <label for="modules_privacy_account_privacy_requests_request_type">
                    <span><?php echo sr_e(sr_t('privacy::ui.text.9305558c')); ?></span>
                    <select id="modules_privacy_account_privacy_requests_request_type" name="request_type">
                        <?php foreach ($allowedTypes as $requestType) { ?>
                            <option value="<?php echo sr_e($requestType); ?>"<?php echo $values['request_type'] === $requestType ? ' selected' : ''; ?>><?php echo sr_e(sr_admin_code_label($requestType, 'privacy_request_type')); ?></option>
                        <?php } ?>
                    </select>
                </label>
            </p>
            <p>
                <label for="modules_privacy_account_privacy_requests_request_message">
                    <span><?php echo sr_e(sr_t('privacy::ui.text.c165c36d')); ?></span>
                    <textarea id="modules_privacy_account_privacy_requests_request_message" name="request_message" rows="5" cols="60"><?php echo sr_e($values['request_message']); ?></textarea>
                </label>
            </p>
            <button type="submit"><?php echo sr_e(sr_t('privacy::ui.text.e1f6f909')); ?></button>
        </form>

        <table>
            <thead>
                <tr>
                    <th><?php echo sr_e(sr_t('privacy::ui.text.5cf2792b')); ?></th>
                    <th><?php echo sr_e(sr_t('privacy::ui.status.e10195a1')); ?></th>
                    <th><?php echo sr_e(sr_t('privacy::ui.text.e470d24b')); ?></th>
                    <th><?php echo sr_e(sr_t('privacy::ui.text.73bb6cce')); ?></th>
                    <th><?php echo sr_e(sr_t('privacy::ui.admin.35568056')); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ($requests === []) { ?>
                    <tr>
                        <td colspan="5"><?php echo sr_e(sr_t('privacy::ui.text.1969657d')); ?></td>
                    </tr>
                <?php } ?>
                <?php foreach ($requests as $request) { ?>
                    <tr>
                        <td><?php echo sr_e(sr_admin_code_label((string) $request['request_type'], 'privacy_request_type')); ?></td>
                        <td><?php echo sr_e(sr_admin_code_label((string) $request['status'], 'privacy_request_status')); ?></td>
                        <td><?php echo sr_e((string) $request['created_at']); ?></td>
                        <td><?php echo sr_e((string) ($request['handled_at'] ?? '')); ?></td>
                        <td><?php echo sr_e(sr_admin_privacy_request_list_preview($request['admin_note'] ?? null)); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
        <p><a href="<?php echo sr_e(sr_url('/account')); ?>"><?php echo sr_e(sr_t('privacy::ui.text.13b28045')); ?></a></p>
    </main>
<?php sr_public_layout_end(); ?>
