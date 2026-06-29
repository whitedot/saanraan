<section class="example-quiz-result">
    <p class="example-content-kicker">RESULT FROM THEME</p>
    <h2>Quiz Result</h2>
    <p>점수: <?php echo sr_e((string) (int) ($submitResult['display_score'] ?? $submitResult['total_score'] ?? 0)); ?></p>
    <p><?php echo !empty($submitResult['passed']) ? '통과했습니다.' : '통과하지 못했습니다.'; ?></p>
    <?php $resultSnapshot = is_array($submitResult['selected_result'] ?? null) ? $submitResult['selected_result'] : []; ?>
    <?php if ((string) ($resultSnapshot['title'] ?? '') !== '') { ?>
        <p>결과: <?php echo sr_e((string) $resultSnapshot['title']); ?></p>
        <?php if ((string) ($resultSnapshot['summary'] ?? '') !== '') { ?>
            <p><?php echo sr_e((string) $resultSnapshot['summary']); ?></p>
        <?php } ?>
    <?php } ?>
    <?php $rewardGrant = is_array($submitResult['reward_grant'] ?? null) ? $submitResult['reward_grant'] : null; ?>
    <?php if (is_array($rewardGrant) && (string) ($rewardGrant['status'] ?? '') === 'granted') { ?>
        <p>보상이 지급되었습니다.</p>
    <?php } elseif (is_array($rewardGrant) && (string) ($rewardGrant['status'] ?? '') === 'failed') { ?>
        <p>보상 지급을 확인해야 합니다.</p>
    <?php } ?>
    <?php if ((string) ($returnTo ?? '') !== '') { ?>
        <p><a class="btn btn-solid-primary" href="<?php echo sr_e(sr_url((string) $returnTo)); ?>" target="_top">돌아가기</a></p>
    <?php } ?>
</section>
