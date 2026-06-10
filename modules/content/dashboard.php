<?php

return [
    [
        'key' => 'content',
        'title' => '콘텐츠',
        'order' => 35,
        'view' => 'views/dashboard-summary.php',
        'items' => [
            [
                'label' => '공개 콘텐츠',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_content_items WHERE status = 'published'",
                'detail_prefix' => '초안 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_content_items WHERE status = 'draft'",
                'state' => 'info',
                'emphasis' => 'primary',
            ],
            [
                'label' => '검토 대기',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_content_submissions WHERE review_status = 'pending_review'",
                'detail_prefix' => '작성자 신청 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_content_author_applications WHERE status = 'pending'",
                'state' => 'warning',
            ],
        ],
    ],
];
