<?php

declare(strict_types=1);

require_once SR_ROOT . '/modules/member/helpers.php';
require_once SR_ROOT . '/modules/admin/helpers.php';

$account = sr_member_require_login($pdo);
sr_admin_require_permission($pdo, (int) ($account['id'] ?? 0), '/admin/members', 'edit');

sr_send_download_headers('text/csv; charset=UTF-8', 'saanraan-member-marketing-opt-out-sample.csv');
$output = fopen('php://output', 'wb');
if ($output === false) {
    sr_finish_response();
}
fwrite($output, "\xEF\xBB\xBF");
foreach (sr_admin_member_marketing_opt_out_sample_rows() as $row) {
    sr_admin_member_csv_row($output, $row);
}
fclose($output);
sr_finish_response();
