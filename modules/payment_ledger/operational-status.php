<?php

declare(strict_types=1);

return [
    [
        'label' => 'payment_ledger.pending_reversal',
        'title' => '결제 되돌림 대기',
        'module' => 'payment_ledger',
        'table' => 'sr_payment_record_items',
        'where' => 'reversal_status = \'pending\'',
        'age_column' => 'updated_at',
        'delay_tolerance' => '즉시',
        'warn_after_seconds' => 0,
        'target_sql' => 'SELECT owner_module, reference_type, reference_id, payment_record_id AS target_fallback
                    FROM sr_payment_record_items
                    WHERE reversal_status = \'pending\'
                    ORDER BY updated_at ASC, id ASC
                    LIMIT 5',
        'target_format' => '{owner_module} {reference_type}#{reference_id}',
        'target_fallback_prefix' => '결제 항목',
        'followup' => '결제 기록을 소유한 도메인 모듈의 취소/환불 흐름에서 되돌림 상태를 확인합니다.',
    ],
];
