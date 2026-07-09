<?php

return [
    'policy_documents.version_notice' => [
        'label' => '정책 문서 변경 안내',
        'description' => '정책 문서 새 버전 안내메일 job 생성 시 subject snapshot으로 저장하는 제목입니다. 본문은 정책 문서 builder를 유지합니다.',
        'category' => 'transactional_email',
        'owner_module' => 'policy_documents',
        'channels' => ['email'],
        'pipeline' => 'config_mail',
        'editable' => true,
        'body_editable' => false,
        'disable_policy' => 'no_op',
        'subject_template' => '정책 문서 변경 안내',
        'body_template' => '',
        'variables' => [
            'site_name' => '사이트명',
            'document_title' => '정책 문서명',
            'effective_date' => '시행일',
        ],
        'required_variables' => [],
        'sensitive_variables' => [],
        'sample_values' => [
            'site_name' => '산란',
            'document_title' => '개인정보 처리방침',
            'effective_date' => '공개 즉시',
        ],
    ],
];
