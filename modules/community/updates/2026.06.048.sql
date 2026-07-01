CREATE TABLE IF NOT EXISTS {{SR_TABLE_PREFIX}}community_post_read_payment_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    board_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    post_id BIGINT UNSIGNED NOT NULL,
    post_title_snapshot VARCHAR(160) NOT NULL DEFAULT '',
    account_id BIGINT UNSIGNED NULL,
    payment_type VARCHAR(40) NOT NULL DEFAULT 'asset_only',
    settlement_kind VARCHAR(30) NOT NULL DEFAULT 'paid',
    charge_policy VARCHAR(20) NOT NULL DEFAULT 'once',
    asset_module VARCHAR(60) NOT NULL DEFAULT '',
    payable_amount BIGINT NOT NULL DEFAULT 0,
    settlement_amount BIGINT NOT NULL DEFAULT 0,
    settlement_currency CHAR(3) NOT NULL DEFAULT 'KRW',
    asset_access_log_ids_json TEXT NULL,
    coupon_redemption_id BIGINT UNSIGNED NULL,
    coupon_dedupe_key VARCHAR(160) NOT NULL DEFAULT '',
    payment_dedupe_key VARCHAR(190) NOT NULL,
    refund_status VARCHAR(20) NOT NULL DEFAULT '',
    refund_transaction_ids_json TEXT NULL,
    refund_note VARCHAR(255) NOT NULL DEFAULT '',
    refunded_by_account_id BIGINT UNSIGNED NULL,
    refunded_at DATETIME NULL,
    access_revoked_at DATETIME NULL,
    refund_policy_version VARCHAR(40) NOT NULL DEFAULT 'community_post_read_refund_v1',
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sr_comm_post_read_payments_dedupe (payment_dedupe_key),
    KEY idx_sr_comm_post_read_payments_post (post_id, created_at),
    KEY idx_sr_comm_post_read_payments_board (board_id, created_at),
    KEY idx_sr_comm_post_read_payments_account (account_id, created_at),
    KEY idx_sr_comm_post_read_payments_type (payment_type, created_at),
    KEY idx_sr_comm_post_read_payments_coupon (coupon_redemption_id),
    KEY idx_sr_comm_post_read_payments_refund (refund_status, refunded_at)
);

UPDATE {{SR_TABLE_PREFIX}}modules
SET version = '2026.06.048',
    updated_at = NOW()
WHERE module_key = 'community';
