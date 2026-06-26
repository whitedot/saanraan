<?php

$sourceQuizzes = is_array($sourceQuizzes ?? null) ? $sourceQuizzes : [];
$sourceModule = (string) ($sourceModule ?? '');
$sourceType = (string) ($sourceType ?? '');
$sourceId = (int) ($sourceId ?? 0);
$returnTo = (string) ($returnTo ?? '');
if ($sourceQuizzes === [] || $sourceModule === '' || $sourceType === '' || $sourceId < 1) {
    return;
}
?>
<section class="sr-quiz-source-links">
    <?php foreach ($sourceQuizzes as $sourceQuizIndex => $sourceQuiz) { ?>
        <?php
        $quizPath = '/quiz/' . rawurlencode((string) ($sourceQuiz['quiz_key'] ?? ''));
        $quizQuery = [
            'return_to' => $returnTo,
            'source_module' => $sourceModule,
            'source_type' => $sourceType,
            'source_id' => (string) $sourceId,
        ];
        $quizUrl = $quizPath . '?' . http_build_query($quizQuery);
        $quizFrameUrl = $quizPath . '?' . http_build_query(array_merge($quizQuery, ['embed' => '1']));
        $dialogId = 'sr_quiz_source_dialog_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $sourceModule . '_' . $sourceType . '_' . (string) $sourceId . '_' . (string) $sourceQuizIndex);
        $frameTitle = '퀴즈: ' . (string) ($sourceQuiz['title'] ?? '');
        ?>
        <article class="sr-quiz-source-link">
            <h2><?php echo sr_e((string) ($sourceQuiz['title'] ?? '')); ?></h2>
            <?php if ((string) ($sourceQuiz['description'] ?? '') !== '') { ?>
                <p><?php echo sr_e((string) ($sourceQuiz['description'] ?? '')); ?></p>
            <?php } ?>
            <p>
                <button type="button" class="btn btn-solid-primary" data-sr-quiz-dialog-open="<?php echo sr_e($dialogId); ?>"><?php echo sr_e((string) (($sourceQuiz['cta_label'] ?? '') !== '' ? $sourceQuiz['cta_label'] : '퀴즈 풀기')); ?></button>
                <a class="btn btn-solid-light" href="<?php echo sr_e(sr_url($quizUrl)); ?>">새 페이지</a>
            </p>
            <dialog id="<?php echo sr_e($dialogId); ?>" class="sr-quiz-dialog" aria-label="<?php echo sr_e($frameTitle); ?>" aria-modal="true">
                <form method="dialog" class="sr-quiz-dialog-toolbar">
                    <button type="submit" class="btn btn-solid-light">닫기</button>
                </form>
                <iframe src="<?php echo sr_e(sr_url($quizFrameUrl)); ?>" title="<?php echo sr_e($frameTitle); ?>"></iframe>
            </dialog>
        </article>
    <?php } ?>
</section>
<script>
document.addEventListener('click', function (event) {
    var opener = event.target && event.target.closest ? event.target.closest('[data-sr-quiz-dialog-open]') : null;
    if (!opener) {
        return;
    }
    var dialog = document.getElementById(opener.getAttribute('data-sr-quiz-dialog-open'));
    if (dialog && typeof dialog.showModal === 'function') {
        dialog.showModal();
    }
});
</script>
