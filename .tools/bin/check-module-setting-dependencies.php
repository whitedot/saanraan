#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$errors = [];

$mustContain = static function (string $path, array $needles, string $label) use ($root, &$errors): void {
    $content = file_get_contents($root . '/' . $path);
    if (!is_string($content)) {
        $errors[] = $label . ' file must be readable: ' . $path;
        return;
    }

    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            $errors[] = $label . ' must contain marker: ' . $needle;
        }
    }
};

foreach ([
    'content' => [
        'action' => 'modules/content/actions/admin-settings.php',
        'view' => 'modules/content/views/admin-settings.php',
        'available' => '$contentReactionAvailable',
        'attributes' => '$contentReactionInputAttributes',
        'notice' => 'content-settings-reaction-unavailable',
        'error' => '콘텐츠 리액션 설정을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.',
    ],
    'community' => [
        'action' => 'modules/community/actions/admin-settings.php',
        'view' => 'modules/community/views/admin-settings.php',
        'available' => '$communityReactionAvailable',
        'attributes' => '$communityReactionInputAttributes',
        'notice' => 'community-settings-reaction-unavailable',
        'error' => '커뮤니티 리액션 설정을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.',
    ],
] as $moduleKey => $definition) {
    $mustContain($definition['action'], [
        $definition['available'],
        '$reactionPresetOptions = ' . $definition['available'],
        $definition['error'],
    ], $moduleKey . ' reaction settings action dependency guard');
    $mustContain($definition['view'], [
        $definition['available'],
        $definition['attributes'],
        $definition['notice'],
        'form-help-warning',
        'disabled aria-describedby=',
    ], $moduleKey . ' reaction settings view dependency state');
}

foreach ([
    'quiz' => [
        'action' => 'modules/quiz/actions/admin-settings.php',
        'helper' => 'modules/quiz/helpers.php',
        'view' => 'modules/quiz/views/admin-settings.php',
        'available' => '$quizReactionAvailable',
        'attributes' => '$quizReactionInputAttributes',
        'notice' => 'quiz-settings-reaction-unavailable',
        'error' => '퀴즈 리액션 기본값을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.',
    ],
    'survey' => [
        'action' => 'modules/survey/actions/admin-settings.php',
        'helper' => 'modules/survey/helpers.php',
        'view' => 'modules/survey/views/admin-settings.php',
        'available' => '$surveyReactionAvailable',
        'attributes' => '$surveyReactionInputAttributes',
        'notice' => 'survey-settings-reaction-unavailable',
        'error' => '설문 리액션 기본값을 사용하려면 리액션 모듈을 먼저 설치하고 활성화하세요.',
    ],
] as $moduleKey => $definition) {
    $mustContain($definition['action'], [
        $definition['available'],
        '$reactionPresetOptions = ' . $definition['available'],
    ], $moduleKey . ' reaction preset action dependency guard');
    $mustContain($definition['helper'], [
        "sr_module_enabled(\$pdo, 'reaction')",
        $definition['error'],
    ], $moduleKey . ' reaction preset helper dependency validation');
    $mustContain($definition['view'], [
        $definition['available'],
        $definition['attributes'],
        $definition['notice'],
        'form-help-warning',
        'disabled aria-describedby=',
    ], $moduleKey . ' reaction preset view dependency state');
}

foreach ([
    'community' => [
        'action' => 'modules/community/actions/admin-settings.php',
        'view' => 'modules/community/views/admin-settings.php',
        'available' => '$communityIdentityRestrictedBoardAvailable',
        'notice' => 'community-settings-identity-unavailable',
        'error' => '제한 게시판 본인확인을 사용하려면 본인확인 사용을 켜고 제한 게시판 목적을 지원하는 제공자를 설정하세요.',
    ],
    'reward' => [
        'action' => 'modules/reward/actions/admin-rewards-settings.php',
        'view' => 'modules/reward/views/admin-settings.php',
        'available' => '$rewardIdentityWithdrawalAvailable',
        'notice' => 'reward-settings-identity-unavailable',
        'error' => '출금 신청 본인확인을 사용하려면 본인확인 사용을 켜고 적립금 출금 신청 목적을 지원하는 제공자를 설정하세요.',
    ],
    'deposit' => [
        'action' => 'modules/deposit/actions/admin-deposits-settings.php',
        'view' => 'modules/deposit/views/admin-settings.php',
        'available' => '$depositIdentityRefundAvailable',
        'notice' => 'deposit-settings-identity-unavailable',
        'error' => '환불 신청 본인확인을 사용하려면 본인확인 사용을 켜고 예치금 환불 신청 목적을 지원하는 제공자를 설정하세요.',
    ],
    'asset_exchange' => [
        'action' => 'modules/asset_exchange/actions/admin-asset-exchange.php',
        'view' => 'modules/asset_exchange/views/admin-asset-exchange.php',
        'available' => '$assetExchangeIdentityAvailable',
        'notice' => 'asset-exchange-settings-identity-unavailable',
        'error' => '환전 신청 본인확인을 사용하려면 본인확인 사용을 켜고 자산 환전 신청 목적을 지원하는 제공자를 설정하세요.',
    ],
] as $moduleKey => $definition) {
    $mustContain($definition['action'], [
        $definition['available'],
        $definition['error'],
    ], $moduleKey . ' identity settings action dependency guard');
    $mustContain($definition['view'], [
        $definition['available'],
        $definition['notice'],
        'form-help-warning',
        'disabled aria-describedby=',
    ], $moduleKey . ' identity settings view dependency state');
}

$mustContain('modules/asset_exchange/actions/admin-asset-exchange.php', [
    '$assetExchangeAvailable = count($assetExchangeAssets) >= 2',
    '환전을 사용하려면 환전 가능한 자산 모듈을 2개 이상 설치하고 활성화하세요.',
], 'asset exchange enabled setting dependency guard');
$mustContain('modules/asset_exchange/views/admin-asset-exchange.php', [
    '$assetExchangeAvailable',
    '$assetExchangeInputAttributes',
    'asset-exchange-settings-unavailable',
    'form-help-warning',
    '교환할 수 있는 포인트·금액 모듈이 2개 이상 설치되어 있고 활성화되어야 사용할 수 있습니다.',
], 'asset exchange enabled setting dependency state');

if ($errors !== []) {
    fwrite(STDERR, "module setting dependency checks failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, '- ' . $error . "\n");
    }
    exit(1);
}

echo "module setting dependency checks completed.\n";
