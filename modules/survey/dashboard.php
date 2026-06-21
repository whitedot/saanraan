<?php

return [
    [
        'key' => 'survey',
        'title' => '설문·여론조사',
        'order' => 70,
        'view' => 'views/dashboard-summary.php',
        'items' => [
            [
                'label' => '공개 설문',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_survey_forms WHERE status = 'active'",
                'detail_prefix' => '초안 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_survey_forms WHERE status = 'draft'",
                'state' => 'info',
                'emphasis' => 'primary',
            ],
            [
                'label' => '제출 응답',
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_survey_responses WHERE status = 'submitted'",
                'detail_prefix' => '검토 필요 ',
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_survey_responses WHERE quality_status <> 'accepted'",
                'state' => 'warning',
            ],
        ],
    ],
];
