<?php

declare(strict_types=1);

return [
  [
    'label' => 'policy_documents.mail_jobs.queued',
    'title' => '정책 문서 안내메일 대기',
    'module' => 'policy_documents',
    'table' => 'sr_policy_document_mail_jobs',
    'where' => 'status = \'queued\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '1시간',
    'warn_after_seconds' => 3600,
    'target_sql' => 'SELECT d.title AS target_label, j.job_key AS target_fallback
                FROM sr_policy_document_mail_jobs j
                INNER JOIN sr_policy_documents d ON d.id = j.document_id
                WHERE j.status = \'queued\'
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5',
    'target_fallback_prefix' => '작업',
    'followup' => '/admin/policy-documents에서 안내메일 발송 배치를 실행하거나 발송 실패를 확인합니다.',
    'action_url' => '/admin/policy-documents',
    'action_label' => '정책 문서 관리',
  ],
  [
    'label' => 'policy_documents.mail_jobs.failed',
    'title' => '정책 문서 안내메일 실패',
    'module' => 'policy_documents',
    'table' => 'sr_policy_document_mail_jobs',
    'where' => 'status = \'failed\'',
    'age_column' => 'updated_at',
    'delay_tolerance' => '즉시',
    'warn_after_seconds' => 0,
    'target_sql' => 'SELECT d.title AS target_label, j.job_key AS target_fallback
                FROM sr_policy_document_mail_jobs j
                INNER JOIN sr_policy_documents d ON d.id = j.document_id
                WHERE j.status = \'failed\'
                ORDER BY j.updated_at ASC, j.id ASC
                LIMIT 5',
    'target_fallback_prefix' => '작업',
    'followup' => '/admin/policy-documents에서 실패한 발송 작업과 메일 설정을 확인합니다.',
    'action_url' => '/admin/policy-documents',
    'action_label' => '정책 문서 관리',
  ],
];
