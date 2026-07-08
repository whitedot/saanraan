#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
define('SR_ROOT', $root);
chdir($root);

require_once $root . '/core/helpers.php';
require_once $root . '/modules/admin/helpers.php';
require_once $root . '/modules/member/helpers.php';
require_once $root . '/modules/message/helpers.php';

$errors = [];

function sr_message_policy_error(string $message): void
{
    global $errors;
    $errors[] = $message;
}

function sr_message_policy_assert(bool $condition, string $message): void
{
    if (!$condition) {
        sr_message_policy_error($message);
    }
}

function sr_message_policy_fixture_settings(string $sendPolicy, string $receivePolicy, bool $defaultReceive = true, bool $enabled = true): array
{
    return [
        'message_enabled' => $enabled,
        'send_policy' => $sendPolicy,
        'send_group_keys' => ['vip'],
        'receive_policy' => $receivePolicy,
        'receive_group_keys' => ['vip'],
        'default_member_receive_enabled' => $defaultReceive,
        'message_create_window_seconds' => 300,
        'message_create_limit' => 20,
    ];
}

function sr_message_policy_fixture_account_receive_enabled(array $scenario, bool $defaultReceive): bool
{
    if (!array_key_exists('receive_enabled', $scenario)) {
        return $defaultReceive;
    }

    return (bool) $scenario['receive_enabled'];
}

function sr_message_policy_expected_receive_for_send(array $scenario, array $settings): bool
{
    if (!sr_message_policy_fixture_account_receive_enabled($scenario, (bool) $settings['default_member_receive_enabled'])) {
        return false;
    }

    if ((string) $settings['receive_policy'] === 'disabled') {
        return false;
    }
    if ((string) $settings['receive_policy'] === 'group') {
        return !empty($scenario['vip']);
    }
    if ((string) $settings['receive_policy'] === 'opt_in') {
        return !empty($scenario['has_receive_row']);
    }

    return (string) $settings['receive_policy'] === 'all';
}

function sr_message_policy_expected_can_send(array $scenario, array $settings): bool
{
    if (empty($settings['message_enabled'])) {
        return false;
    }
    if (!empty($scenario['staff_bypass'])) {
        return true;
    }
    if (!sr_message_policy_expected_receive_for_send($scenario, $settings)) {
        return false;
    }

    if ((string) $settings['send_policy'] === 'disabled') {
        return false;
    }
    if ((string) $settings['send_policy'] === 'group') {
        return !empty($scenario['vip']);
    }

    return (string) $settings['send_policy'] === 'all';
}

function sr_message_policy_expected_can_receive(array $recipientScenario, array $senderScenario, array $settings, string $recipientStatus = 'active'): bool
{
    if ($recipientStatus !== 'active' || empty($settings['message_enabled'])) {
        return false;
    }
    if (!empty($senderScenario['staff_bypass'])) {
        return true;
    }
    if (!sr_message_policy_fixture_account_receive_enabled($recipientScenario, (bool) $settings['default_member_receive_enabled'])) {
        return false;
    }

    if ((string) $settings['receive_policy'] === 'disabled') {
        return false;
    }
    if ((string) $settings['receive_policy'] === 'group') {
        return !empty($recipientScenario['vip']);
    }
    if ((string) $settings['receive_policy'] === 'opt_in') {
        return !empty($recipientScenario['has_receive_row'])
            && sr_message_policy_fixture_account_receive_enabled($recipientScenario, (bool) $settings['default_member_receive_enabled']);
    }

    return (string) $settings['receive_policy'] === 'all';
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$pdo->exec("CREATE TABLE sr_admin_account_roles (account_id INTEGER NOT NULL, role_key TEXT NOT NULL, created_at TEXT NOT NULL, UNIQUE(account_id, role_key))");
$pdo->exec("CREATE TABLE sr_admin_account_permissions (account_id INTEGER NOT NULL, menu_path TEXT NOT NULL, action_key TEXT NOT NULL, created_at TEXT NOT NULL, UNIQUE(account_id, menu_path, action_key))");
$pdo->exec("CREATE TABLE sr_member_groups (id INTEGER PRIMARY KEY AUTOINCREMENT, group_key TEXT NOT NULL, title TEXT NOT NULL DEFAULT '', description TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'enabled', is_system INTEGER NOT NULL DEFAULT 0, sort_order INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_member_group_memberships (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, account_id INTEGER NOT NULL, assignment_type TEXT NOT NULL DEFAULT 'manual', source_module_key TEXT NOT NULL DEFAULT '', source_rule_key TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'active', granted_at TEXT NOT NULL, expires_at TEXT NULL, revoked_at TEXT NULL, created_by_account_id INTEGER NULL, updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_member_group_rules (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, source_module_key TEXT NOT NULL DEFAULT '', rule_key TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'enabled', evaluation_policy TEXT NOT NULL DEFAULT 'grant_only', params_json TEXT NOT NULL DEFAULT '{}', last_evaluated_at TEXT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_member_group_membership_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, group_id INTEGER NOT NULL, account_id INTEGER NOT NULL, membership_id INTEGER NULL, event_type TEXT NOT NULL, actor_account_id INTEGER NULL, message TEXT NOT NULL DEFAULT '', metadata_json TEXT NULL, created_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_message_member_settings (account_id INTEGER PRIMARY KEY, receive_enabled INTEGER NOT NULL, created_at TEXT NOT NULL, updated_at TEXT NOT NULL)");
$pdo->exec("CREATE TABLE sr_modules (id INTEGER PRIMARY KEY AUTOINCREMENT, module_key TEXT NOT NULL, version TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'enabled')");
$pdo->exec("CREATE TABLE sr_module_settings (module_id INTEGER NOT NULL, setting_key TEXT NOT NULL, setting_value TEXT NOT NULL DEFAULT '', value_type TEXT NOT NULL DEFAULT 'string', created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE(module_id, setting_key))");

$now = sr_now();
$pdo->prepare('INSERT INTO sr_modules (id, module_key, version, status) VALUES (:id, :module_key, :version, :status)')
    ->execute(['id' => 1, 'module_key' => 'message', 'version' => 'fixture', 'status' => 'enabled']);
$pdo->prepare('INSERT INTO sr_admin_account_roles (account_id, role_key, created_at) VALUES (:account_id, :role_key, :created_at)')
    ->execute(['account_id' => 1, 'role_key' => 'owner', 'created_at' => $now]);
$pdo->prepare('INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at) VALUES (:account_id, :menu_path, :action_key, :created_at)')
    ->execute(['account_id' => 2, 'menu_path' => '/admin/message/settings', 'action_key' => 'edit', 'created_at' => $now]);
$pdo->prepare('INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at) VALUES (:account_id, :menu_path, :action_key, :created_at)')
    ->execute(['account_id' => 3, 'menu_path' => '/admin/community/posts', 'action_key' => 'view', 'created_at' => $now]);
$pdo->prepare('INSERT INTO sr_admin_account_permissions (account_id, menu_path, action_key, created_at) VALUES (:account_id, :menu_path, :action_key, :created_at)')
    ->execute(['account_id' => 8, 'menu_path' => '/admin/members', 'action_key' => 'view', 'created_at' => $now]);
$pdo->exec("INSERT INTO sr_member_groups (id, group_key, title, status, created_at, updated_at) VALUES (1, 'vip', 'VIP', 'enabled', '', '')");
$pdo->exec("INSERT INTO sr_member_group_memberships (group_id, account_id, status, granted_at, updated_at) VALUES (1, 5, 'active', '', '')");
$pdo->exec("INSERT INTO sr_message_member_settings (account_id, receive_enabled, created_at, updated_at) VALUES (6, 1, '', ''), (7, 0, '', '')");

$restrictedSettings = [
    'message_enabled' => true,
    'send_policy' => 'group',
    'send_group_keys' => ['vip'],
    'receive_policy' => 'all',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$disabledSettings = [
    'message_enabled' => true,
    'send_policy' => 'disabled',
    'send_group_keys' => [],
    'receive_policy' => 'all',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$optInSettings = [
    'message_enabled' => true,
    'send_policy' => 'all',
    'send_group_keys' => [],
    'receive_policy' => 'opt_in',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$openSettings = [
    'message_enabled' => true,
    'send_policy' => 'all',
    'send_group_keys' => [],
    'receive_policy' => 'all',
    'receive_group_keys' => [],
    'default_member_receive_enabled' => true,
];
$receiveGroupSettings = [
    'message_enabled' => true,
    'send_policy' => 'all',
    'send_group_keys' => [],
    'receive_policy' => 'group',
    'receive_group_keys' => ['vip'],
    'default_member_receive_enabled' => true,
];

sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 1], $restrictedSettings),
    'Owner manager must bypass message send group conditions.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 2], $restrictedSettings),
    'Staff with message edit permission must bypass message send group conditions.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 8], $restrictedSettings),
    'Staff with member list view permission must bypass message send group conditions.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 3], $restrictedSettings),
    'Staff without message write permission must not bypass message send conditions.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 4], $restrictedSettings),
    'Regular member outside allowed groups must not send when send_policy=group.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 5], $restrictedSettings),
    'Regular member in an allowed group must send when send_policy=group.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 1], $disabledSettings),
    'Manager bypass must still allow operational sends when send_policy=disabled.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 6], $openSettings),
    'Regular member with receive setting enabled must send when send_policy=all.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 7], $openSettings),
    'Regular member with receive setting disabled must not send messages.'
);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 6], $receiveGroupSettings),
    'Regular member outside receive-allowed groups must not send messages.'
);
sr_message_policy_assert(
    sr_message_account_can_send($pdo, ['id' => 5], $receiveGroupSettings),
    'Regular member inside receive-allowed groups must send when send_policy=all.'
);
sr_message_policy_assert(
    sr_message_account_can_receive($pdo, ['id' => 6, 'status' => 'active'], ['id' => 4], $optInSettings),
    'Opt-in recipient with receive setting enabled must be receivable.'
);
sr_message_policy_assert(
    !sr_message_account_can_receive($pdo, ['id' => 7, 'status' => 'active'], ['id' => 4], $optInSettings),
    'Opt-in recipient with receive setting disabled must not be receivable.'
);
sr_message_policy_assert(
    sr_message_account_can_receive($pdo, ['id' => 7, 'status' => 'active'], ['id' => 2], $optInSettings),
    'Message staff bypass must override member receive opt-out for active accounts.'
);

$accountScenarios = [
    'regular_default' => [
        'id' => 4,
        'vip' => false,
        'has_receive_row' => false,
        'staff_bypass' => false,
    ],
    'regular_vip_default' => [
        'id' => 5,
        'vip' => true,
        'has_receive_row' => false,
        'staff_bypass' => false,
    ],
    'regular_opt_in' => [
        'id' => 6,
        'vip' => false,
        'has_receive_row' => true,
        'receive_enabled' => true,
        'staff_bypass' => false,
    ],
    'regular_opt_out' => [
        'id' => 7,
        'vip' => false,
        'has_receive_row' => true,
        'receive_enabled' => false,
        'staff_bypass' => false,
    ],
    'message_staff' => [
        'id' => 2,
        'vip' => false,
        'has_receive_row' => false,
        'staff_bypass' => true,
    ],
];
$sendPolicies = ['all', 'group', 'disabled'];
$receivePolicies = ['all', 'group', 'opt_in', 'disabled'];
$defaultReceiveOptions = [true, false];
$sendSimulationCount = 0;
$receiveSimulationCount = 0;

foreach ($sendPolicies as $sendPolicy) {
    foreach ($receivePolicies as $receivePolicy) {
        foreach ($defaultReceiveOptions as $defaultReceive) {
            $settings = sr_message_policy_fixture_settings($sendPolicy, $receivePolicy, $defaultReceive);
            foreach ($accountScenarios as $scenarioKey => $scenario) {
                $actual = sr_message_account_can_send($pdo, ['id' => (int) $scenario['id']], $settings);
                $expected = sr_message_policy_expected_can_send($scenario, $settings);
                sr_message_policy_assert(
                    $actual === $expected,
                    'Send simulation mismatch for '
                        . 'send_policy=' . $sendPolicy
                        . ', receive_policy=' . $receivePolicy
                        . ', default_receive=' . ($defaultReceive ? '1' : '0')
                        . ', account=' . $scenarioKey
                        . '; expected ' . ($expected ? 'allow' : 'deny')
                        . ', got ' . ($actual ? 'allow' : 'deny') . '.'
                );
                $sendSimulationCount++;
            }
        }
    }
}

foreach ($receivePolicies as $receivePolicy) {
    foreach ($defaultReceiveOptions as $defaultReceive) {
        $settings = sr_message_policy_fixture_settings('all', $receivePolicy, $defaultReceive);
        foreach ($accountScenarios as $recipientKey => $recipientScenario) {
            if (!empty($recipientScenario['staff_bypass'])) {
                continue;
            }
            foreach (['regular_default', 'message_staff'] as $senderKey) {
                $senderScenario = $accountScenarios[$senderKey];
                $actual = sr_message_account_can_receive(
                    $pdo,
                    ['id' => (int) $recipientScenario['id'], 'status' => 'active'],
                    ['id' => (int) $senderScenario['id']],
                    $settings
                );
                $expected = sr_message_policy_expected_can_receive($recipientScenario, $senderScenario, $settings);
                sr_message_policy_assert(
                    $actual === $expected,
                    'Receive simulation mismatch for '
                        . 'receive_policy=' . $receivePolicy
                        . ', default_receive=' . ($defaultReceive ? '1' : '0')
                        . ', recipient=' . $recipientKey
                        . ', sender=' . $senderKey
                        . '; expected ' . ($expected ? 'allow' : 'deny')
                        . ', got ' . ($actual ? 'allow' : 'deny') . '.'
                );
                $receiveSimulationCount++;
            }
        }
    }
}

$disabledSettings = sr_message_policy_fixture_settings('all', 'all', true, false);
sr_message_policy_assert(
    !sr_message_account_can_send($pdo, ['id' => 2], $disabledSettings),
    'Global message disabled setting must block staff sends too.'
);
sr_message_policy_assert(
    !sr_message_account_can_receive($pdo, ['id' => 5, 'status' => 'withdrawn'], ['id' => 2], sr_message_policy_fixture_settings('all', 'all')),
    'Inactive recipients must remain blocked even for staff senders.'
);

$registrationFields = sr_message_registration_fields($pdo);
sr_message_policy_assert(
    count($registrationFields) === 1
        && (string) ($registrationFields[0]['key'] ?? '') === sr_message_registration_field_key()
        && (string) ($registrationFields[0]['type'] ?? '') === 'checkbox'
        && !empty($registrationFields[0]['default']),
    'Message registration contract must expose a default-checked receive checkbox when member opt-in is enabled.'
);
$registrationValues = sr_member_registration_extension_empty_values([
    sr_message_registration_field_key() => [
        'key' => sr_message_registration_field_key(),
        'type' => 'checkbox',
        'default' => true,
    ],
]);
sr_message_policy_assert(
    (string) ($registrationValues[sr_message_registration_field_key()] ?? '') === '1',
    'Registration checkbox default value must initialize to checked when field default is true.'
);

$_POST = ['registration_extensions' => [sr_message_registration_field_key() => '1']];
$registrationErrors = [];
$postedValues = sr_member_registration_extension_values_from_post([
    sr_message_registration_field_key() => [
        'key' => sr_message_registration_field_key(),
        'type' => 'checkbox',
        'label' => '쪽지 수신 허용',
    ],
], $registrationErrors);
sr_message_policy_assert(
    $registrationErrors === [] && (string) ($postedValues[sr_message_registration_field_key()] ?? '') === '1',
    'Checked registration checkbox must normalize to 1.'
);

$_POST = ['registration_extensions' => []];
$registrationErrors = [];
$postedValues = sr_member_registration_extension_values_from_post([
    sr_message_registration_field_key() => [
        'key' => sr_message_registration_field_key(),
        'type' => 'checkbox',
        'label' => '쪽지 수신 허용',
    ],
], $registrationErrors);
sr_message_policy_assert(
    $registrationErrors === [] && (string) ($postedValues[sr_message_registration_field_key()] ?? '') === '0',
    'Unchecked registration checkbox must normalize to 0.'
);

$_POST = ['registration_extensions' => []];
$registrationErrors = [];
$postedValues = sr_member_registration_extension_values_from_post([
    'required_text' => [
        'key' => 'required_text',
        'type' => 'text',
        'label' => '필수 텍스트',
        'required' => true,
        'maxlength' => 20,
    ],
    'required_checkbox' => [
        'key' => 'required_checkbox',
        'type' => 'checkbox',
        'label' => '필수 체크',
        'required' => true,
    ],
], $registrationErrors);
sr_message_policy_assert(
    (string) ($postedValues['required_text'] ?? 'filled') === ''
        && (string) ($postedValues['required_checkbox'] ?? '1') === '0'
        && count($registrationErrors) === 2,
    'Required registration extension text and checkbox fields must be validated server-side.'
);
$_POST = [];

$metadata = sr_message_registration_save($pdo, 20, [sr_message_registration_field_key() => '0']);
$receiveEnabled = (int) $pdo->query('SELECT receive_enabled FROM sr_message_member_settings WHERE account_id = 20')->fetchColumn();
sr_message_policy_assert(
    !empty($metadata['saved']) && empty($metadata['receive_enabled']) && $receiveEnabled === 0,
    'Message registration save must store unchecked receive setting in the message-owned table.'
);

$pdo->prepare(
    'INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
     VALUES (1, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
     ON CONFLICT(module_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value, value_type = excluded.value_type, updated_at = excluded.updated_at'
)->execute([
    'setting_key' => 'member_receive_opt_enabled',
    'setting_value' => '0',
    'value_type' => 'bool',
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare(
    'INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
     VALUES (1, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
     ON CONFLICT(module_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value, value_type = excluded.value_type, updated_at = excluded.updated_at'
)->execute([
    'setting_key' => 'default_member_receive_enabled',
    'setting_value' => '1',
    'value_type' => 'bool',
    'created_at' => $now,
    'updated_at' => $now,
]);
sr_clear_module_settings_cache('message');
sr_message_policy_assert(
    sr_message_registration_fields($pdo) === [],
    'Message registration contract must hide the checkbox when member opt-in editing is disabled.'
);
$metadata = sr_message_registration_save($pdo, 21, []);
$receiveEnabled = (int) $pdo->query('SELECT receive_enabled FROM sr_message_member_settings WHERE account_id = 21')->fetchColumn();
sr_message_policy_assert(
    !empty($metadata['saved']) && !empty($metadata['receive_enabled']) && $receiveEnabled === 1,
    'Message registration save must create a default receive row when member opt-in editing is disabled.'
);

$pdo->prepare(
    'INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
     VALUES (1, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
     ON CONFLICT(module_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value, value_type = excluded.value_type, updated_at = excluded.updated_at'
)->execute([
    'setting_key' => 'message_enabled',
    'setting_value' => '0',
    'value_type' => 'bool',
    'created_at' => $now,
    'updated_at' => $now,
]);
$pdo->prepare(
    'INSERT INTO sr_module_settings (module_id, setting_key, setting_value, value_type, created_at, updated_at)
     VALUES (1, :setting_key, :setting_value, :value_type, :created_at, :updated_at)
     ON CONFLICT(module_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value, value_type = excluded.value_type, updated_at = excluded.updated_at'
)->execute([
    'setting_key' => 'member_receive_opt_enabled',
    'setting_value' => '1',
    'value_type' => 'bool',
    'created_at' => $now,
    'updated_at' => $now,
]);
sr_clear_module_settings_cache('message');
sr_message_policy_assert(
    sr_message_registration_fields($pdo) === [],
    'Message registration contract must hide the checkbox when the message feature is disabled.'
);
$metadata = sr_message_registration_save($pdo, 22, []);
$receiveEnabled = (int) $pdo->query('SELECT receive_enabled FROM sr_message_member_settings WHERE account_id = 22')->fetchColumn();
sr_message_policy_assert(
    !empty($metadata['saved']) && !empty($metadata['receive_enabled']) && $receiveEnabled === 1,
    'Message registration save must still create the default receive row when the message feature is disabled.'
);

if ($errors !== []) {
    fwrite(STDERR, "message policy checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "message policy checks completed. send simulations: " . (string) $sendSimulationCount . ", receive simulations: " . (string) $receiveSimulationCount . ".\n";
