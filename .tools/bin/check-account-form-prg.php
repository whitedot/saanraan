#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];
$assertContains = static function (string $file, array $markers) use ($root, &$errors): void {
    $contents = file_get_contents($root . '/' . $file);
    if (!is_string($contents)) {
        $errors[] = 'cannot read account form source: ' . $file;
        return;
    }
    foreach ($markers as $marker) {
        if (!str_contains($contents, $marker)) {
            $errors[] = $file . ' missing PRG marker: ' . $marker;
        }
    }
};

$assertContains('modules/content/actions/account-content.php', [
    "unset(\$_SESSION['sr_content_submission_flash'])",
    "'values' => \$contentSubmissionFormValues",
    "'notice' => \$intent === 'submit' ? '콘텐츠를 제출했습니다.' : '임시저장했습니다.'",
    "sr_redirect('/account/content'",
]);
$assertContains('modules/content/views/account-content.php', [
    'array_merge(',
    '$contentSubmissionFormValues',
    "sr_public_feedback_toasts('content', \$notice, \$errors)",
]);
$assertContains('assets/common-ui.js', [
    '[data-sr-public-toast-close]',
    "toast.classList.add('is-hiding')",
    'toast.remove()',
]);

foreach ([
    'reward' => [
        'action' => 'modules/reward/actions/account-rewards.php',
        'view' => 'modules/reward/views/account-rewards.php',
        'flash' => 'sr_reward_flash',
        'values' => 'rewardWithdrawalFormValues',
        'path' => '/account/rewards',
    ],
    'deposit' => [
        'action' => 'modules/deposit/actions/account-deposits.php',
        'view' => 'modules/deposit/views/account-deposits.php',
        'flash' => 'sr_deposit_flash',
        'values' => 'depositRefundFormValues',
        'path' => '/account/deposits',
    ],
] as $moduleKey => $definition) {
    $assertContains($definition['action'], [
        "unset(\$_SESSION['" . $definition['flash'] . "'])",
        "'values' => \$" . $definition['values'],
        "sr_redirect('" . $definition['path'] . "'",
    ]);
    $assertContains($definition['view'], [
        "sr_public_feedback_toasts('" . $moduleKey . "', \$notice, \$errors)",
        "\$" . $definition['values'] . "['amount']",
        "\$" . $definition['values'] . "['bank_account_number']",
    ]);
}

if ($errors !== []) {
    fwrite(STDERR, "account form PRG checks failed:\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}

echo "account form PRG checks completed.\n";
