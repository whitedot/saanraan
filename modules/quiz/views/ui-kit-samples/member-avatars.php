<?php

$memberHelperPath = SR_ROOT . '/modules/member/helpers.php';
if (is_file($memberHelperPath)) {
    require_once $memberHelperPath;
}

$memberAvatarPalette = function_exists('sr_member_default_avatar_color_palette')
    ? sr_member_default_avatar_color_palette()
    : [
        '#b91c1c',
        '#c2410c',
        '#a16207',
        '#4d7c0f',
        '#047857',
        '#0f766e',
        '#0369a1',
        '#1d4ed8',
        '#4f46e5',
        '#7e22ce',
        '#be185d',
        '#9f1239',
    ];
$memberAvatarSamples = [
    ['name' => '김산', 'initial' => '김', 'hash' => 'b91c1c' . str_repeat('0', 26)],
    ['name' => 'Lee', 'initial' => 'L', 'hash' => '0369a1' . str_repeat('0', 26)],
    ['name' => 'Mina', 'initial' => 'M', 'hash' => '7e22ce' . str_repeat('0', 26)],
];
?>
<div class="ui-kit-sample-section" data-ui-kit-sample="member-avatars">
<div class="container-fluid">
                    <div class="ui-kit-grid ui-kit-grid-1 ui-kit-grid-xl-2 ui-kit-gap-base">
                        <div class="card ui-kit-column-xl-2">
                            <div class="card-header">
                                <h4 class="card-title">기본 회원 아바타 색상표</h4>
                            </div>

                            <div class="card-body">
                                <p class="sample-note ui-kit-space-after-4">아바타 이미지가 없을 때 회원 공개 해시 앞 6글자와 가장 가까운 색상을 적용합니다.</p>

                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-3">
                                    <?php foreach ($memberAvatarPalette as $memberAvatarColorIndex => $memberAvatarColor) { ?>
                                        <?php
                                        $memberAvatarHash = ltrim((string) $memberAvatarColor, '#') . str_repeat('0', 26);
                                        $memberAvatarColorClass = function_exists('sr_member_default_avatar_color_class')
                                            ? sr_member_default_avatar_color_class($memberAvatarHash)
                                            : 'member-avatar-color-' . (string) $memberAvatarColorIndex;
                                        ?>
                                        <span class="member-avatar-palette-item">
                                            <span class="member-default-avatar <?php echo sr_e($memberAvatarColorClass); ?>" aria-hidden="true"><?php echo sr_e((string) ($memberAvatarColorIndex + 1)); ?></span>
                                            <code><?php echo sr_e((string) $memberAvatarColor); ?></code>
                                        </span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">아바타 미리보기</h4>
                            </div>

                            <div class="card-body">
                                <div class="ui-kit-cluster ui-kit-wrap ui-kit-gap-3">
                                    <?php foreach ($memberAvatarSamples as $memberAvatarSample) { ?>
                                        <?php
                                        $memberAvatarSampleHash = (string) ($memberAvatarSample['hash'] ?? '');
                                        $memberAvatarColorClass = function_exists('sr_member_default_avatar_color_class')
                                            ? sr_member_default_avatar_color_class($memberAvatarSampleHash)
                                            : 'member-avatar-color-8';
                                        ?>
                                        <span class="member-avatar-preview-item">
                                            <span class="member-default-avatar <?php echo sr_e($memberAvatarColorClass); ?>" aria-hidden="true"><?php echo sr_e((string) ($memberAvatarSample['initial'] ?? 'M')); ?></span>
                                            <span class="member-avatar-preview-text">
                                                <strong><?php echo sr_e((string) ($memberAvatarSample['name'] ?? 'Member')); ?></strong>
                                                <code><?php echo sr_e(substr($memberAvatarSampleHash, 0, 6)); ?></code>
                                            </span>
                                        </span>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title">드롭다운 헤더 미리보기</h4>
                            </div>

                            <div class="card-body">
                                <div class="dropdown-menu dropdown-menu-profile dropdown-menu-profile-preview" role="menu" aria-orientation="vertical" aria-label="기본 아바타 드롭다운 샘플">
                                    <div class="dropdown-profile-header">
                                        <span class="dropdown-profile-avatar member-avatar-color-7" aria-hidden="true">S</span>
                                        <span class="dropdown-profile-identity">
                                            <strong class="dropdown-profile-name">Sample Member</strong>
                                            <span class="dropdown-profile-email">sample@example.com</span>
                                        </span>
                                        <span class="badge badge-soft-info badge-pill">MEMBER</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
</div>
</div>
