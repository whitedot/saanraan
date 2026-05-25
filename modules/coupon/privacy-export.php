<?php

return [
    'coupon_issues' => [
        'label' => '쿠폰 발급 내역',
        'query' => "SELECT i.id, i.status, i.issued_reason, i.issued_at, i.expires_at, i.used_count, d.coupon_key, d.title, d.target_type, d.target_id
                    FROM sr_coupon_issues i
                    INNER JOIN sr_coupon_definitions d ON d.id = i.coupon_definition_id
                    WHERE i.account_id = :account_id
                    ORDER BY i.id DESC",
    ],
    'coupon_redemptions' => [
        'label' => '쿠폰 사용 내역',
        'query' => "SELECT r.id, r.target_type, r.target_id, r.reference_module, r.reference_type, r.reference_id, r.status, r.redeemed_at, d.coupon_key, d.title
                    FROM sr_coupon_redemptions r
                    INNER JOIN sr_coupon_definitions d ON d.id = r.coupon_definition_id
                    WHERE r.account_id = :account_id
                    ORDER BY r.id DESC",
    ],
];
