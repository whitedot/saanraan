<?php

$adminPageTitle = '설문 보상 로그';
$adminPageSubtitle = '';
$adminContainerClass = 'admin-page-survey-reward-logs admin-ui-scope';
$surveyRewardFilters = isset($surveyRewardFilters) && is_array($surveyRewardFilters) ? $surveyRewardFilters : ['survey_id' => 0, 'status' => '', 'provider' => '', 'q' => ''];
$surveyRewardLogs = isset($surveyRewardLogs) && is_array($surveyRewardLogs) ? $surveyRewardLogs : [];
$surveyRewardSurveyOptions = isset($surveyRewardSurveyOptions) && is_array($surveyRewardSurveyOptions) ? $surveyRewardSurveyOptions : [];
$surveyRewardDetailFilterOpen = !empty($surveyRewardDetailFilterOpen);
$surveyRewardStatusOptions = isset($surveyRewardStatusOptions) && is_array($surveyRewardStatusOptions) ? $surveyRewardStatusOptions : [];
$surveyRewardProviderOptions = isset($surveyRewardProviderOptions) && is_array($surveyRewardProviderOptions) ? $surveyRewardProviderOptions : [];
$surveyRewardAssetOptions = isset($surveyRewardAssetOptions) && is_array($surveyRewardAssetOptions) ? $surveyRewardAssetOptions : [];
$surveyRewardModuleLabel = static function (string $moduleKey) use ($surveyRewardAssetOptions): string {
    if ($moduleKey === 'coupon') {
        return '쿠폰';
    }

    return (string) ($surveyRewardAssetOptions[$moduleKey]['label'] ?? $moduleKey);
};
$surveyRewardReferenceTypeLabel = static function (string $referenceType): string {
    return [
        'survey_reward' => '설문 보상',
        'coupon_issue' => '쿠폰 발급',
    ][$referenceType] ?? ($referenceType !== '' ? $referenceType : '참조');
};
$surveyRewardStatusClass = static function (string $status): string {
    return match ($status) {
        'granted' => 'is-success',
        'pending' => 'is-warning',
        'failed' => 'is-danger',
        'duplicate' => 'is-danger',
        default => 'is-warning',
    };
};
$adminPageTitleUrl = sr_admin_page_title_reset_url(true, '/admin/surveys/reward-logs');

include SR_ROOT . '/modules/admin/views/layout-header.php';
?>

<form method="get" action="<?php echo sr_e(sr_url('/admin/surveys/reward-logs')); ?>" class="filtering-form admin-survey-reward-log-filter ui-form-theme">
    <div class="filtering filtering-card<?php echo $surveyRewardDetailFilterOpen ? ' filtering-open' : ''; ?>" data-filtering>
        <div class="filtering-fields">
            <div class="filtering-field filtering-field-fill admin-survey-reward-log-filter-keyword">
                <label for="survey_reward_log_q" class="filtering-label">검색어</label>
                <input id="survey_reward_log_q" type="search" name="q" value="<?php echo sr_e((string) ($surveyRewardFilters['q'] ?? '')); ?>" class="form-input filtering-input" maxlength="120" placeholder="설문, 응답 ID, 공개 해시, 지급 참조, 실패 사유">
            </div>
        </div>
        <div id="survey_reward_log_detail_filters" class="filtering-body" data-filtering-body<?php echo $surveyRewardDetailFilterOpen ? '' : ' hidden'; ?>>
            <div class="filtering-field">
                <label for="survey_reward_log_survey_id" class="filtering-label">설문</label>
                <select id="survey_reward_log_survey_id" name="survey_id" class="form-select form-control-full">
                    <option value="">전체</option>
                    <?php foreach ($surveyRewardSurveyOptions as $surveyOption) { ?>
                        <?php
                        $optionSurveyId = (int) ($surveyOption['id'] ?? 0);
                        $optionSurveyKey = trim((string) ($surveyOption['survey_key'] ?? ''));
                        ?>
                        <option value="<?php echo sr_e((string) $optionSurveyId); ?>"<?php echo (int) ($surveyRewardFilters['survey_id'] ?? 0) === $optionSurveyId ? ' selected' : ''; ?>>
                            <?php echo sr_e((string) ($surveyOption['title'] ?? '')); ?><?php echo $optionSurveyKey !== '' ? ' (' . sr_e($optionSurveyKey) . ')' : ''; ?>
                        </option>
                    <?php } ?>
                </select>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">상태</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('survey_reward_log_status_filter', 'status', $surveyRewardStatusOptions, [(string) ($surveyRewardFilters['status'] ?? '')], '전체'); ?>
            </div>
            <div class="filtering-field">
                <span class="filtering-label">보상 종류</span>
                <?php echo sr_admin_filter_radio_toggle_group_html('survey_reward_log_provider_filter', 'provider', $surveyRewardProviderOptions, [(string) ($surveyRewardFilters['provider'] ?? '')], '전체'); ?>
            </div>
        </div>
        <div class="filtering-actions">
            <button type="button" class="btn btn-solid-light filtering-toggle" data-filtering-toggle aria-expanded="<?php echo $surveyRewardDetailFilterOpen ? 'true' : 'false'; ?>" aria-controls="survey_reward_log_detail_filters">상세검색</button>
            <button type="button" class="btn btn-outline-light filtering-reset" data-filtering-reset><?php echo sr_material_icon_html('restart_alt'); ?>초기화</button>
            <button type="submit" class="btn btn-solid-primary filtering-submit">검색</button>
        </div>
    </div>
</form>

<section class="card admin-list-card admin-list-form">
    <div class="card-header">
        <h2 class="card-title">보상 로그</h2>
    </div>
    <div class="admin-list-summary-row">
        <?php echo sr_admin_pagination_summary_html($surveyRewardPagination); ?>
    </div>
    <div class="table-wrapper">
        <table class="table table-list admin-survey-reward-log-table">
            <caption class="sr-only">설문 응답 보상 로그</caption>
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
                        <td colspan="7" class="admin-empty-state">보상 로그가 없습니다.</td>
                    </tr>
                <?php } ?>
                <?php foreach ($surveyRewardLogs as $rewardLog) { ?>
                    <?php
                    $rewardStatus = (string) ($rewardLog['status'] ?? '');
                    $surveyTitle = trim((string) ($rewardLog['survey_title'] ?? ''));
                    $surveyKey = trim((string) ($rewardLog['survey_key'] ?? ''));
                    $accountLabel = trim((string) (($rewardLog['account_display_name'] ?? '') ?: ($rewardLog['account_email'] ?? '')));
                    $rewardAccountId = (int) ($rewardLog['account_id'] ?? 0);
                    $rewardAccountHash = $rewardAccountId > 0 && function_exists('sr_admin_member_public_hash')
                        ? sr_admin_member_public_hash(isset($config) && is_array($config) ? $config : sr_runtime_config(), $rewardAccountId)
                        : '';
                    $errorMessage = trim((string) ($rewardLog['error_message'] ?? ''));
                    $referenceType = trim((string) ($rewardLog['provider_reference_type'] ?? ''));
                    $referenceId = trim((string) ($rewardLog['provider_reference_id'] ?? ''));
                    ?>
                    <tr>
                        <td class="admin-table-nowrap">#<?php echo sr_e((string) (int) ($rewardLog['id'] ?? 0)); ?></td>
                        <td class="admin-table-nowrap">
                            <span class="badge-status <?php echo sr_e($surveyRewardStatusClass($rewardStatus)); ?>"><?php echo sr_e(sr_survey_reward_log_status_label($rewardStatus)); ?></span>
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
                            <?php if ($rewardAccountId > 0) { ?>
                                <strong><?php echo sr_e($rewardAccountHash !== '' ? $rewardAccountHash : '회원 정보 없음'); ?></strong>
                                <small class="admin-summary-meta"><?php echo sr_e($accountLabel !== '' ? $accountLabel : '회원 정보 없음'); ?></small>
                            <?php } else { ?>
                                <span class="admin-summary-meta">회원 정보 없음</span>
                            <?php } ?>
                        </td>
                        <td class="admin-table-break">
                            <strong><?php echo sr_e(sr_survey_reward_provider_label((string) ($rewardLog['reward_provider'] ?? ''))); ?></strong>
                            <small class="admin-summary-meta">
                                <?php echo sr_e($surveyRewardModuleLabel((string) ($rewardLog['reward_module'] ?? ''))); ?>
                                <?php if (($rewardLog['reward_amount'] ?? null) !== null) { ?>
                                    <?php echo sr_e(number_format((int) ($rewardLog['reward_amount'] ?? 0))); ?>
                                <?php } ?>
                                · <?php echo sr_e(sr_survey_reward_dedupe_scope_label((string) ($rewardLog['dedupe_scope'] ?? ''))); ?>
                            </small>
                        </td>
                        <td class="admin-table-break admin-survey-reward-log-reference-cell">
                            <?php if ($referenceType !== '' || $referenceId !== '') { ?>
                                <span><?php echo sr_e($surveyRewardReferenceTypeLabel($referenceType)); ?> #<?php echo sr_e($referenceId !== '' ? $referenceId : '0'); ?></span>
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

<?php echo sr_admin_pagination_html($surveyRewardPagination, '설문 보상 로그 페이지'); ?>

<?php include SR_ROOT . '/modules/admin/views/layout-footer.php'; ?>
