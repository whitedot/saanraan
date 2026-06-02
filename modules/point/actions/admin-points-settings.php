<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';
require_once SR_ROOT . '/modules/point/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) $account['id'], '/admin/points/settings', 'view');

$flashResult = sr_admin_pop_flash_result();
$errors = $flashResult['errors'];
$notice = (string) $flashResult['notice'];
$settings = sr_point_settings($pdo);

if (sr_request_method() === 'POST') {
    sr_require_csrf();
    sr_admin_require_permission($pdo, (int) $account['id'], '/admin/points/settings', 'edit');

    $intent = sr_post_string('intent', 40);
    if ($intent === 'expire_due') {
        try {
            $expirationResult = sr_point_expire_due_transactions($pdo, 1000);
            sr_audit_log($pdo, [
                'actor_account_id' => (int) $account['id'],
                'actor_type' => 'admin',
                'event_type' => 'point.expiration.run',
                'target_type' => 'module',
                'target_id' => 'point',
                'result' => 'success',
                'message' => 'Point expiration run.',
                'metadata' => [
                    'expired_count' => (int) $expirationResult['expired_count'],
                    'expired_amount' => (int) $expirationResult['expired_amount'],
                ],
            ]);

            sr_admin_flash_result(sr_admin_action_result([], sprintf(
                sr_t('point::action.admin.settings.expiration_run'),
                number_format((int) $expirationResult['expired_count']),
                number_format((int) $expirationResult['expired_amount'])
            )));
            sr_redirect('/admin/points/settings');
        } catch (Throwable $exception) {
            sr_log_exception($exception, 'point_manual_expiration_failed');
            $errors[] = sr_t('point::action.admin.settings.expiration_failed');
        }
    }

    if ($intent === '' || $intent === 'save_settings') {
        $defaultExpirationDaysInput = sr_post_string('default_expiration_days', 20);
        $postedSettings = [
            'display_name' => sr_point_clean_text(sr_post_string('display_name', 80), 40),
            'unit_label' => sr_point_clean_text(sr_post_string('unit_label', 40), 20),
            'default_expiration_days' => $defaultExpirationDaysInput,
        ];
        $postedSettings['default_expiration_days'] = (string) sr_point_normalize_expiration_days($postedSettings['default_expiration_days']);
        $settings = array_merge($settings, $postedSettings);

        if ($postedSettings['display_name'] === '') {
            $errors[] = sr_t('point::action.admin.settings.display_name_required');
        }
        if ($defaultExpirationDaysInput !== '' && (preg_match('/\A\d+\z/', $defaultExpirationDaysInput) !== 1 || (int) $defaultExpirationDaysInput > 3650)) {
            $errors[] = sr_t('point::action.admin.settings.expiration_days_invalid');
        }

        if ($errors === []) {
            try {
                sr_point_save_settings($pdo, $postedSettings);
                $settings = sr_point_settings($pdo);

                sr_audit_log($pdo, [
                    'actor_account_id' => (int) $account['id'],
                    'actor_type' => 'admin',
                    'event_type' => 'point.settings.updated',
                    'target_type' => 'module',
                    'target_id' => 'point',
                    'result' => 'success',
                    'message' => 'Point settings updated.',
                    'metadata' => [
                        'display_name' => (string) $settings['display_name'],
                        'unit_label' => (string) $settings['unit_label'],
                        'default_expiration_days' => (string) $settings['default_expiration_days'],
                    ],
                ]);

                sr_admin_flash_result(sr_admin_action_result([], sr_t('point::action.admin.settings.saved')));
                sr_redirect('/admin/points/settings');
            } catch (Throwable $exception) {
                if ($exception->getMessage() === 'Point display name is required.') {
                    $errors[] = sr_t('point::action.admin.settings.display_name_required');
                } else {
                    sr_log_exception($exception, 'point_settings_save_failed');
                    $errors[] = sr_t('point::action.admin.settings.save_failed');
                }
            }
        }
    }
}

include SR_ROOT . '/modules/point/views/admin-settings.php';
