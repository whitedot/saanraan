<?php

$pageTitle = sr_t('member_oauth::ui.settings');
include SR_ROOT . '/modules/admin/views/layout-header.php';
?>
<section class="admin-section">
    <div class="admin-section-header">
        <div>
            <h1><?php echo sr_e($pageTitle); ?></h1>
            <p><?php echo sr_e('Callback URL: ' . sr_absolute_url($site ?? [], '/oauth/callback')); ?></p>
        </div>
    </div>
    <section class="admin-card">
        <h2><?php echo sr_e('Provider'); ?></h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th><?php echo sr_e('Key'); ?></th>
                    <th><?php echo sr_e('Label'); ?></th>
                    <th><?php echo sr_e('Type'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (sr_member_oauth_public_providers($pdo) as $provider) { ?>
                    <tr>
                        <td><?php echo sr_e((string) $provider['provider_key']); ?></td>
                        <td><?php echo sr_e((string) ($provider['label'] ?? $provider['provider_key'])); ?></td>
                        <td><?php echo !empty($provider['mock']) ? 'mock' : 'oauth'; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </section>
</section>
<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
