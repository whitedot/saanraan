<?php

declare(strict_types=1);

return [
    'helpers' => 'helpers.php',
    'fields_function' => 'sr_message_registration_fields',
    'save_function' => 'sr_message_registration_save',
    'exception_messages' => [
        'message_registration_save_failed' => '쪽지 수신 설정을 저장하지 못했습니다.',
    ],
];
