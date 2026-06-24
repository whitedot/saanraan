<?php

$adminPageTitle = '설문 리워드 로그';
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-survey-reward-logs admin-ui-scope';
$surveyRewardFilters = isset($surveyRewardFilters) && is_array($surveyRewardFilters) ? $surveyRewardFilters : ['status' => '', 'provider' => '', 'q' => ''];
$surveyRewardLogs = isset($surveyRewardLogs) && is_array($surveyRewardLogs) ? $surveyRewardLogs : [];
$surveyRewardStatusClass = static function (string $status): string {
    return match ($status) {
        'granted' => 'is-normal',
        'pending' => 'is-warning',
        'failed' => 'is-danger',
        'duplicate' => 'is-left',
        default => 'is-blocked',
    };
};
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/surveys/reward-logs');

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/surveys/reward-logs')); ?>" class="filtering-form filtering filtering-plain admin-survey-reward-log-filter ui-form-theme">
    <div class="filtering-fields admin-survey-reward-log-search-grid">
        <label class="filtering-field" for="survey_reward_log_status">
            <span class="filtering-label">상태</span>
            <select id="survey_reward_log_status" name="status" class="form-select filtering-input">
                <option value="">전체</option>
                <?php foreach (sr_survey_reward_log_statuses() as $status) { ?>
                    <option value="<?php echo sr_e($status); ?>"<?php echo (string) ($surveyRewardFilters['status'] ?? '') === $status ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_reward_log_status_label($status)); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="filtering-field" for="survey_reward_log_provider">
            <span class="filtering-label">공급자</span>
            <select id="survey_reward_log_provider" name="provider" class="form-select filtering-input">
                <option value="">전체</option>
                <?php foreach (sr_survey_reward_providers() as $provider) { ?>
                    <option value="<?php echo sr_e($provider); ?>"<?php echo (string) ($surveyRewardFilters['provider'] ?? '') === $provider ? ' selected' : ''; ?>><?php echo sr_e(sr_survey_reward_provider_label($provider)); ?></option>
                <?php } ?>
            </select>
        </label>
        <label class="filtering-field admin-survey-reward-log-filter-keyword" for="survey_reward_log_q">
            <span class="filtering-label">검색</span>
            <input id="survey_reward_log_q" type="search" name="q" value="<?php echo sr_e((string) ($surveyRewardFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="설문, 응답, 회원, 지급 참조, 실패 사유">
        </label>
        <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">리워드 로그</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($surveyRewardPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-survey-reward-log-table">
            <caption class="sr-only">설문 응답 리워드 로그</caption>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>상태</th>
                    <th>설문/응답</th>
                    <th>회원</th>
                    <th>보상</th>
                    <th>지급 참조</th>
                    <th>생성</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($surveyRewardLogs === []) { ?>
                    <tr>
                        <td colspan="7" class="admin-empty-state">리워드 로그가 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($surveyRewardLogs as $rewardLog) { ?>
                    <?php
                    $rewardStatus = (string) ($rewardLog['status'] ?? '');
                    $surveyTitle = trim((string) ($rewardLog['survey_title'] ?? ''));
                    $surveyKey = trim((string) ($rewardLog['survey_key'] ?? ''));
                    $accountLabel = trim((string) (($rewardLog['account_display_name'] ?? '') ?: ($rewardLog['account_email'] ?? '')));
                    $errorMessage = trim((string) ($rewardLog['error_message'] ?? ''));
                    $referenceType = trim((string) ($rewardLog['provider_reference_type'] ?? ''));
                    $referenceId = trim((string) ($rewardLog['provider_reference_id'] ?? ''));
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($rewardLog['id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="admin-status <?php echo sr_e($surveyRewardStatusClass($rewardStatus)); ?>"><?php echo sr_e(sr_survey_reward_log_status_label($rewardStatus)); ?></span>
                        </td>
                        <td class="admin-table-break admin-survey-reward-log-subject-cell">
                            <a href="<?php echo sr_e(sr_url('/admin/surveys?mode=edit&id=' . rawurlencode((string) (int) ($rewardLog['survey_id'] ?? 0)))); ?>" class="btn btn-sm btn-solid-light">설문</a>
                            <strong><?php echo sr_e($surveyTitle !== '' ? $surveyTitle : '삭제된 설문'); ?></strong>
                            <small class="admin-summary-meta">
                                <?php if ($surveyKey !== '') { ?>(<?php echo sr_e($surveyKey); ?>) · <?php } ?>응답 #<?php echo sr_e((string) (int) ($rewardLog['response_id'] ?? 0)); ?>
                                <?php if ((int) ($rewardLog['is_test'] ?? 0) === 1) { ?> · 테스트<?php } ?>
                            </small>
                        </td>
                        <td class="admin-table-break admin-survey-reward-log-account-cell">
                            <?php if ((int) ($rewardLog['account_id'] ?? 0) > 0) { ?>
                                <strong>#<?php echo sr_e((string) (int) ($rewardLog['account_id'] ?? 0)); ?></strong>
                                <small class="admin-summary-meta"><?php echo sr_e($accountLabel !== '' ? $accountLabel : '회원 정보 없음'); ?></small>
                            <?php } else { ?>
                                <span class="admin-summary-meta">회원 정보 없음</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e(sr_survey_reward_provider_label((string) ($rewardLog['reward_provider'] ?? ''))); ?></strong>
                            <small class="admin-summary-meta">
                                <?php echo sr_e((string) ($rewardLog['reward_module'] ?? '')); ?>
                                <?php if (($rewardLog['reward_amount'] ?? null) !== null) { ?>
                                    <?php echo sr_e(number_format((int) ($rewardLog['reward_amount'] ?? 0))); ?>
                                <?php } ?>
                                · <?php echo sr_e(sr_survey_reward_dedupe_scope_label((string) ($rewardLog['dedupe_scope'] ?? ''))); ?>
                            </small>
                        </td>
                        <td class="admin-table-break admin-survey-reward-log-reference-cell">
                            <?php if ($referenceType !== '' || $referenceId !== '') { ?>
                                <span><?php echo sr_e($referenceType !== '' ? $referenceType : '참조'); ?> #<?php echo sr_e($referenceId !== '' ? $referenceId : '0'); ?></span>
                            <?php } else { ?>
                                <span class="admin-summary-meta">없음</span>
                            <?php } ?>
                            <?php if ($errorMessage !== '') { ?>
                                <small class="text-danger"><?php echo sr_e($errorMessage); ?></small>
                            <?php } ?>
                        </td>
                        <td class="admin-table-nowrap admin-survey-reward-log-date-cell"><?php echo sr_survey_time_html((string) ($rewardLog['created_at'] ?? '')); ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php echo sr_admin_status_description_list_html('survey_reward_log_status', array_combine(sr_survey_reward_log_statuses(), array_map('sr_survey_reward_log_status_label', sr_survey_reward_log_statuses())) ?: []); ?>
</section>

<?php echo sr_admin_pagination_html($surveyRewardPagination, '설문 리워드 로그 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
