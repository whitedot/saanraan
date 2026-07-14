<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/coupon/helpers.php';

$account = sr_member_require_login($pdo);
$couponPerPage = 20;
$couponPageInput = sr_get_string('page', 20);
$couponPage = preg_match('/\A[1-9][0-9]*\z/', $couponPageInput) === 1 ? (int) $couponPageInput : 1;
$couponCount = sr_coupon_active_account_issue_count($pdo, (int) $account['id']);
$couponTotalPages = max(1, (int) ceil($couponCount / $couponPerPage));
$couponPage = min(max(1, $couponPage), $couponTotalPages);
$couponPagination = ['page' => $couponPage, 'total_pages' => $couponTotalPages];
$coupons = sr_coupon_active_account_issues($pdo, (int) $account['id'], $couponPerPage, ($couponPage - 1) * $couponPerPage);

include SR_ROOT . '/modules/coupon/views/account-coupons.php';
