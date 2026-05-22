<?php

return [
    [
        'key' => 'site_menu',
        'title' => sr_t('site_menu::ui.menu.a14f2522'),
        'order' => 10,
        'view' => 'views/dashboard-summary.php',
        'items' => [
            [
                'label' => sr_t('site_menu::ui.menu.33822da6'),
                'value_sql' => "SELECT COUNT(*) AS value FROM sr_site_menus WHERE status = 'enabled'",
                'detail_prefix' => sr_t('site_menu::ui.active.item.prefix.e224e2d0'),
                'detail_sql' => "SELECT COUNT(*) AS detail FROM sr_site_menu_items WHERE status = 'enabled'",
                'state' => 'success',
                'emphasis' => 'primary',
            ],
        ],
    ],
];
