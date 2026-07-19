<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

return [
    'available_function' => 'sr_reaction_tables_available',
    'resolve_function' => 'sr_reaction_resolve_targets',
    'summary_function' => 'sr_reaction_record_summaries',
    'render_function' => 'sr_reaction_render_widget',
    'assets_function' => 'sr_reaction_public_assets',
    'delete_targets_function' => 'sr_reaction_delete_target_records',
    'disabled_preset_key_function' => 'sr_reaction_disabled_preset_key',
    'preset_options_function' => 'sr_reaction_preset_options',
    'preset_options_with_disabled_function' => 'sr_reaction_preset_options_with_disabled',
    'setting_preset_key_function' => 'sr_reaction_setting_preset_key',
    'setting_preset_key_or_disabled_function' => 'sr_reaction_setting_preset_key_or_disabled',
];
