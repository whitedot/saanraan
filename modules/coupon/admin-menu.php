<?php

return [
    'label' => '쿠폰·이용권',
    'order' => 60,
    'items' => [
        [
            'label' => '쿠폰 관리',
            'path' => '/admin/coupons',
            'order' => 10,
        ],
        [
            'label' => '지급 내역',
            'path' => '/admin/coupons/issues',
            'order' => 20,
        ],
        [
            'label' => '사용 내역',
            'path' => '/admin/coupons/redemptions',
            'order' => 30,
        ],
    ],
];
