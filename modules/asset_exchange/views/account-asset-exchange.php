<?php

$pageTitle = '포인트/금액 환전';
$seo = [
    'title' => $pageTitle,
    'canonical' => sr_canonical_url($site, '/account/asset-exchange'),
    'robots' => 'noindex, nofollow',
];
sr_public_layout_begin($pdo ?? null, $site ?? null, $seo, []);
?>
    <main class="ui-page">
        <?php echo sr_public_feedback_toasts('asset-exchange', $notice, $errors); ?>
        <div class="ui-page-stack">
            <div class="ui-page-header">
                <div>
                    <h1 class="type-page-title-fluid"><?php echo sr_e($pageTitle); ?></h1>
                </div>
                <a href="<?php echo sr_e(sr_url('/account')); ?>" class="btn btn-outline-default">
                    <?php echo sr_material_icon_html('arrow_back'); ?>
                    계정으로
                </a>
            </div>

            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">보유 포인트/금액</h2>
                </div>
                <div class="card-body ui-card-body-stack">
                    <?php if ($assets === []) { ?>
                        <p class="ui-feedback type-small">환전 가능한 포인트/금액 항목이 없습니다.</p>
                    <?php } else { ?>
                        <div class="ui-stat-grid">
                            <?php foreach ($assets as $asset) { ?>
                                <div class="ui-stat-card">
                                    <span class="type-small"><?php echo sr_e((string) $asset['label']); ?></span>
                                    <strong class="type-page-title"><?php echo sr_e(number_format((int) ($balances[(string) $asset['module_key']] ?? 0))); ?></strong>
                                    <span class="type-small"><?php echo sr_e((string) $asset['unit_label']); ?></span>
                                </div>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </div>
            </section>

            <section id="asset-exchange-request" class="card">
                <div class="card-header">
                    <h2 class="card-title">환전 신청</h2>
                </div>
                <div class="card-body ui-card-body-stack">
                    <?php if (!empty($assetExchangeIdentityRequired)) { ?>
                        <div class="alert <?php echo !empty($assetExchangeIdentitySatisfied) ? 'alert-success' : 'alert-warning'; ?>">
                            <p><?php echo !empty($assetExchangeIdentitySatisfied) ? sr_e('환전 신청 본인확인이 완료되었습니다.') : sr_e('환전 신청 전 본인확인이 필요합니다.'); ?></p>
                            <?php if (empty($assetExchangeIdentitySatisfied) && !empty($assetExchangeIdentityStartUrl)) { ?>
                                <p><a class="btn btn-sm btn-solid-primary" href="<?php echo sr_e((string) $assetExchangeIdentityStartUrl); ?>"><?php echo sr_e('본인확인'); ?></a></p>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (empty($exchangeEnabled)) { ?>
                        <p class="ui-feedback type-small">현재 환전 신청이 중지되어 있습니다.</p>
                    <?php } elseif ($availablePolicies === []) { ?>
                        <p class="ui-feedback type-small">현재 신청 가능한 환전 정책이 없습니다.</p>
                    <?php } else { ?>
                        <form method="get" action="<?php echo sr_e(sr_url('/account/asset-exchange')); ?>" class="ui-inline-form">
                            <label class="ui-field" for="asset_exchange_policy_id">
                                <span>환전 조합</span>
                                <select id="asset_exchange_policy_id" name="policy_id" class="form-select" required>
                                    <?php foreach ($availablePolicies as $policy) { ?>
                                        <option value="<?php echo sr_e((string) $policy['id']); ?>"<?php echo is_array($selectedPolicy) && (int) $selectedPolicy['id'] === (int) $policy['id'] ? ' selected' : ''; ?>>
                                            <?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $policy['from_module_key']) . ' -> ' . sr_asset_exchange_asset_label($assets, (string) $policy['to_module_key'])); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </label>
                            <label class="ui-field" for="asset_exchange_amount">
                                <span>환전 금액</span>
                                <input id="asset_exchange_amount" type="number" name="amount" value="<?php echo sr_e((string) ($_GET['amount'] ?? '')); ?>" class="form-input" min="1" required>
                            </label>
                            <button type="submit" class="btn btn-solid-primary">
                                <?php echo sr_material_icon_html('calculate'); ?>
                                예상 금액 확인
                            </button>
                        </form>
                        <?php if (is_array($selectedPolicy) && is_array($quote)) { ?>
                            <form method="post" action="<?php echo sr_e(sr_url('/account/asset-exchange')); ?>" class="card">
                                <div class="card-body ui-card-body-stack">
                                    <?php echo sr_csrf_field(); ?>
                                    <input type="hidden" name="policy_id" value="<?php echo sr_e((string) $selectedPolicy['id']); ?>">
                                    <input type="hidden" name="amount" value="<?php echo sr_e((string) $quote['request_amount']); ?>">
                                    <input type="hidden" name="exchange_submit_token" value="<?php echo sr_e((string) ($exchangeSubmitToken ?? '')); ?>">
                                    <dl class="ui-stat-grid">
                                        <div class="ui-stat-card">
                                            <dt class="type-caption">출금</dt>
                                            <dd class="type-section-title"><?php echo sr_e(number_format((int) $quote['request_amount'])); ?></dd>
                                        </div>
                                        <div class="ui-stat-card">
                                            <dt class="type-caption">입금</dt>
                                            <dd class="type-section-title"><?php echo sr_e(number_format((int) $quote['deposit_before_fee'])); ?></dd>
                                        </div>
                                        <div class="ui-stat-card">
                                            <dt class="type-caption">수수료</dt>
                                            <dd class="type-section-title"><?php echo sr_e(number_format((int) $quote['fee_amount'])); ?></dd>
                                        </div>
                                        <div class="ui-stat-card">
                                            <dt class="type-caption">최종 증가</dt>
                                            <dd class="type-section-title"><?php echo sr_e(number_format((int) $quote['deposit_amount'])); ?></dd>
                                        </div>
                                    </dl>
                                    <p class="type-body">적용 비율 <?php echo sr_e('출금 ' . number_format((int) $selectedPolicy['rate_denominator']) . '당 입금 ' . number_format((int) $selectedPolicy['rate_numerator'])); ?></p>
                                    <button type="submit" class="btn btn-solid-success">
                                        <?php echo sr_material_icon_html('check_circle'); ?>
                                        환전 확정
                                    </button>
                                </div>
                            </form>
                        <?php } ?>
                    <?php } ?>
                </div>
            </section>

            <section id="asset-exchange-history" class="card">
                <div class="card-header">
                    <h2 class="card-title">환전 내역</h2>
                </div>
                <div class="card-body ui-card-body-stack">
                    <?php if ($logs === []) { ?>
                        <p class="ui-feedback type-small">환전 내역이 없습니다.</p>
                    <?php } else { ?>
                        <div class="table-wrapper">
                            <table class="table">
                            <thead>
                            <tr>
                                <th>일시</th>
                                <th>항목</th>
                                <th>출금</th>
                                <th>입금</th>
                                <th>수수료</th>
                                <th>상태</th>
                                <th>실패 사유</th>
                            </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log) { ?>
                                    <?php $failureReason = trim((string) ($log['failure_reason'] ?? '')); ?>
                                    <tr>
                                        <td><?php echo sr_asset_exchange_time_html((string) $log['created_at']); ?></td>
                                        <td><?php echo sr_e(sr_asset_exchange_asset_label($assets, (string) $log['from_module_key']) . ' -> ' . sr_asset_exchange_asset_label($assets, (string) $log['to_module_key'])); ?></td>
                                        <td class="table-align-end table-nowrap"><?php echo sr_e(number_format((int) $log['request_amount'])); ?></td>
                                        <td class="table-align-end table-nowrap"><?php echo sr_e(number_format((int) $log['deposit_amount'])); ?></td>
                                        <td class="table-align-end table-nowrap"><?php echo sr_e(number_format((int) $log['fee_amount'])); ?></td>
                                        <td><span class="badge badge-soft-<?php echo (string) $log['status'] === 'failed' ? 'danger' : 'success'; ?>"><?php echo sr_e(sr_asset_exchange_log_status_label((string) $log['status'])); ?></span></td>
                                        <td><?php echo sr_e($failureReason !== '' ? $failureReason : '-'); ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                            </table>
                        </div>
                    <?php } ?>
                    <?php echo sr_public_pagination_html($assetExchangeHistoryPagination, $assetExchangeHistoryBasePath, '환전 내역 페이지', 'history_page', 'asset-exchange-history'); ?>
                </div>
            </section>
        </div>
    </main>
<?php sr_public_layout_end(); ?>
