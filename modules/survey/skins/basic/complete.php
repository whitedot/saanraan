<section class="sr-survey-result">
    <h2>참여 완료</h2>
    <p><?php echo $canPreviewAsAdmin ? '테스트 응답을 저장했습니다.' : '설문 응답을 저장했습니다.'; ?></p>
    <?php $grant = is_array($submitResult['reward_grant'] ?? null) ? $submitResult['reward_grant'] : null; ?>
    <?php if (is_array($grant) && (string) ($grant['status'] ?? '') === 'granted'): ?>
        <p>보상이 지급되었습니다.</p>
    <?php elseif (is_array($grant) && (string) ($grant['status'] ?? '') === 'failed'): ?>
        <p>보상 지급을 확인해야 합니다.</p>
    <?php endif; ?>
    <?php if ($returnTo !== ''): ?>
        <p><a class="btn btn-solid-light" href="<?php echo sr_e(sr_url($returnTo)); ?>">이전 화면으로 돌아가기</a></p>
    <?php endif; ?>
</section>
