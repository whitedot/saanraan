<?php

return [
    [
        'key' => 'popup_layer',
        'title' => sr_t('popup_layer::ui.text.1063d585'),
        'order' => 30,
        'default_visible' => false,
        'view' => 'views/dashboard-summary.php',
        'rows' => [
            [
                'label' => sr_t('popup_layer::ui.text.903a4275'),
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_popup_layers WHERE status = 'enabled'",
                'detail_prefix' => sr_t('popup_layer::ui.save.prefix.674b6ae2'),
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_popup_layers WHERE status = 'draft'",
            ],
        ],
    ],
];
