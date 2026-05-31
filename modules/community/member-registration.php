<?php

declare(strict_types=1);

return [
    'helpers' => 'helpers.php',
    'fields_function' => 'sr_community_member_registration_fields',
    'validate_function' => 'sr_community_member_registration_validate',
    'save_function' => 'sr_community_member_registration_save',
    'exception_messages' => [
        'community_nickname_duplicate' => sr_t('community::action.nickname_duplicate'),
    ],
];
