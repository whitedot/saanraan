<?php

return [
    'label' => '쿠폰·이용권',
    'order' => 60,
    'items' => [
        [
            'label' => '쿠폰·이용권 관리',
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
        [
            'label' => '발급 캠페인',
            'path' => '/admin/coupons/campaigns',
            'order' => 40,
        ],
        [
            'label' => '환경설정',
            'path' => '/admin/coupons/settings',
            'order' => 50,
        ],
        [
            'label' => '임베드 캐시',
            'path' => '/admin/coupons/embed-cache',
            'order' => 55,
        ],
    ],
];
