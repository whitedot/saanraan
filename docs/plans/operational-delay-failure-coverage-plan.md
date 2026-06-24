# 운영 지연/실패 점검 전수 조사와 처리 계획

## 배경

`/admin/operations`의 `운영 지연/실패 점검`은 배치나 queue 전용 모니터가 아니라, 운영자가 놓치면 안 되는 미완료/실패 작업을 read-only로 발견하는 화면이다. 이 문서는 현재 코드베이스에서 같은 방식으로 점검할 수 있는 상황을 전수 조사하고, 추가할 항목과 제외할 항목의 기준을 정리한다.

조사 기준은 현재 worktree의 `modules/*/install.sql`, 관리자 action/view, 작업 helper, `docs/smoke-test.md`, `docs/operational-status.md`이다.

## 감지 가능 조건

운영 지연/실패 점검에 올릴 수 있는 대상은 다음 조건을 만족해야 한다.

| 조건 | 의미 |
| --- | --- |
| 영속 row가 먼저 생김 | 작업 시작 또는 처리 대상이 DB row로 남아야 한다. row 생성 전 실패는 이 화면이 아니라 즉시 오류/로그 영역이다. |
| 상태값이 있음 | `pending`, `queued`, `running`, `failed`, `open`처럼 운영자가 해석할 수 있는 상태가 있어야 한다. |
| 시각 기준이 있음 | `created_at`, `updated_at`, `failed_at`, `expires_at`, `last_attempted_at`처럼 허용 지연을 계산할 기준이 있어야 한다. |
| 대상 식별값이 있음 | 게시판명, 알림 제목, 문서 key, 계정 ID, 파일 key처럼 표의 `대상` 컬럼에 표시할 대표값이 있어야 한다. |
| 후속 처리 화면이 있음 | 이 화면은 read-only이므로 실제 재시도/취소/정리는 소유 모듈 관리자 화면에서 처리해야 한다. |

다음 상황은 운영 지연/실패 점검의 직접 대상이 아니다.

- 작업 row 생성 전에 실패하고 부작용도 없는 경우: 작업이 시작되지 않은 것이므로 action 오류/토스트와 오류 로그로 충분하다.
- 작업 row 생성 전에 이미 도메인 데이터나 파일을 만든 경우: 점검 항목 추가보다 작업 설계를 고쳐 job row를 첫 영속 작업으로 만들어야 한다.
- 회원 신청, 관리자 승인 대기, 콘텐츠 검수처럼 정상 업무 대기인 `pending`: 오래 남았다는 이유만으로 시스템 이상이 아니다.
- 단일 요청에서 bounded로 끝나는 삭제/정리 작업: 실패 잔여 row가 따로 없다면 감사 로그와 화면 결과가 정본이다.

## 현재 커버됨

| 신호 | 기준 row | 현재 판정 | 대상 표시 | 후속 화면 |
| --- | --- | --- | --- | --- |
| 정책 문서 안내메일 대기 | `sr_policy_document_mail_jobs.status = 'queued'` | 1시간 초과 시 지연 | 문서 key / version key | `/admin/policy-documents` |
| 정책 문서 안내메일 실패 | `sr_policy_document_mail_jobs.status = 'failed'` | 즉시 지연 초과 | 문서 key / version key | `/admin/policy-documents` |
| 공통 자산 미회수 | `sr_asset_recovery_failures.status = 'open'` | 즉시 지연 초과 | 출처 모듈 + 대상 유형/ID + 계정 ID | `/admin/assets/recovery-failures` |
| 커뮤니티 legacy 자산 미회수 | `sr_community_asset_recovery_failures.status = 'open'` | 즉시 지연 초과 | 자산 모듈 + 대상 유형/ID + 계정 ID | `/admin/assets/recovery-failures` |
| 커뮤니티 게시자 보상 대기 | `sr_community_publisher_reward_logs.status = 'pending'` | 15분 초과 시 지연 | 게시글/첨부/게시자 ID | `/admin/community/publisher-rewards` |
| 커뮤니티 게시자 보상 실패 | `sr_community_publisher_reward_logs.status = 'failed'` | 즉시 지연 초과 | 게시글/첨부/게시자 ID | `/admin/community/publisher-rewards` |
| 알림 delivery 대기 | `sr_notification_deliveries.status IN ('queued', 'processing')` | 1시간 초과 시 지연 | 알림 제목 또는 delivery ID | 알림 delivery 관리 |
| 알림 delivery 실패 | `sr_notification_deliveries.status IN ('failed', 'dead')` | 즉시 지연 초과 | 알림 제목 또는 delivery ID | 알림 delivery 관리 |
| 콘텐츠 저장소 정리 대기 | `sr_content_storage_cleanup_failures.status = 'pending'` | 24시간 초과 시 지연 | storage key | 콘텐츠 저장소 정리 실패 목록 |
| 커뮤니티 저장소 정리 대기 | `sr_community_storage_cleanup_failures.status = 'pending'` | 24시간 초과 시 지연 | storage key | 커뮤니티 저장소 정리 실패 목록 |
| 게시판 복사 진행 중 | `sr_community_board_copy_jobs.status IN ('pending', 'running')` | 15분 초과 시 지연 | 원본 게시판명 또는 작업 ID | 게시판 배치 복사 |
| 게시판 복사 실패 | `sr_community_board_copy_jobs.status IN ('failed', 'canceled')` | 즉시 지연 초과 | 원본 게시판명 또는 작업 ID | 게시판 배치 복사 |
| 퀴즈 보상 지급 대기 | `sr_quiz_reward_grants.status = 'pending'` | 15분 초과 시 지연 | 퀴즈 제목 또는 ID | 퀴즈 보상/응시 관리 |
| 퀴즈 보상 지급 실패 | `sr_quiz_reward_grants.status = 'failed'` | 즉시 지연 초과 | 퀴즈 제목 또는 ID | 퀴즈 보상/응시 관리 |
| 설문 보상 지급 대기 | `sr_survey_reward_grants.status = 'pending'` | 15분 초과 시 지연 | 설문 제목 또는 ID | 설문 응답/보상 관리 |
| 설문 보상 지급 실패 | `sr_survey_reward_grants.status = 'failed'` | 즉시 지연 초과 | 설문 제목 또는 ID | 설문 응답/보상 관리 |
| 포인트 만료 처리 대상 | `sr_point_transactions.expires_at <= NOW() AND expires_remaining > 0` | 24시간 초과 시 지연 | 계정 ID | 포인트 만료 CLI 또는 포인트 거래 흐름 |

## 추가 권장

현재 추가 권장으로 남겨둘 신호는 없다. 새 후보는 감지 가능 조건을 모두 만족하고, 후속 처리 화면이 있는지 확인한 뒤 이 문서에 추가한다.

## 별도 설계가 먼저 필요한 항목

| 항목 | 현재 상태 | 판정 |
| --- | --- | --- |
| 커뮤니티 레벨 재계산 | AJAX batch가 cursor와 processed_total을 클라이언트가 들고 이어서 호출한다. 별도 job row가 없다. | 현재 처리: 운영 점검 제외. 선행 작업: `sr_community_level_recalculate_jobs` 같은 작업 row, stage, cursor, lock token, `updated_at`, `completed`/`failed` 상태를 먼저 도입한다. |
| 콘텐츠 작가 보상 로그 | `sr_content_author_reward_logs.status`에 `pending`/`failed`가 남지만 전용 보상 로그/복구 화면이 없다. | 현재 처리: 운영 점검 제외. 선행 작업: `/admin/content/submissions` 또는 별도 보상 로그 화면에서 대상, 실패 사유, 처리 기준을 먼저 노출한다. |
| 저장소 캐시 정리, 보관 정책 정리 | bounded 단일 요청이며 결과가 즉시 화면/감사 로그로 남는다. | 현재 처리: 운영 점검 제외. 실행 결과 토스트, 감사 로그, 필요한 경우 요약 화면을 정본으로 둔다. |
| 콘텐츠/게시판 동기 복사 | 단일 요청으로 완료되고 실패 시 action 오류/감사 로그로 남는다. | 현재 처리: 운영 점검 제외. 대량이면 게시판 배치 복사 job을 사용하고, 콘텐츠 복사는 별도 job row가 생기기 전까지 운영 지연 점검 대상이 아니다. |
| 설문 CSV export, 개인정보 export | 요청 시 생성되는 read-only 출력이며 영속 job이 없다. | 현재 처리: 운영 점검 제외. 장기 export queue를 도입하기 전까지는 운영 지연/실패 점검 대상이 아니다. |

## 제외 항목

| 항목 | 제외 이유 |
| --- | --- |
| 콘텐츠 제출 `review_status`, 작가 신청 `status`, 커뮤니티 신고 `status`, 개인정보 요청 `status` | 정상적인 관리자 업무 대기다. 운영 이상이 아니라 업무 목록/대시보드 영역이다. |
| 회원 비밀번호 재설정, 이메일 인증, 세션 만료 | 만료 토큰 정리는 보안/보관 정책 영역이며 운영자가 조치할 실패 작업이 아니다. |
| 쿠폰 발급/사용, 환전 실패 로그 | 도메인 거래 이력이며 실패 자체가 사용자 요청 결과다. 재시도 가능한 미완료 작업 row가 아니므로 운영 지연/실패 점검보다는 해당 도메인 로그와 reconciliation이 정본이다. |
| 콘텐츠/커뮤니티 자산 access/action pending log placeholder | idempotency claim 경계다. 정상 실패 시 삭제/rollback되도록 설계되어 있으며, 오래 남는 row가 발견되면 별도 정합성 점검으로 다루는 것이 맞다. |
| 적립금 출금 신청 대기, 예치금 환불 신청 대기 | 정상적인 운영 업무 대기다. 오래 남았다는 사실만으로 시스템 지연/실패가 아니므로 현재 `운영 지연/실패 점검`에 포함하지 않는다. |

## 처리 계획

### 1차: 이미 전용 화면이 있는 운영 잔여물 연결

- `policy_documents.mail_jobs.queued`
- `policy_documents.mail_jobs.failed`
- `asset_recovery.open`

처리 상태: 완료.

- `sr_admin_operational_status_checks()`에 read-only check를 추가한다.
- 대상 목록은 각각 문서 key/version key, 출처 모듈/대상/계정 ID로 최대 5개 표시한다.
- 후속 확인 문구는 전용 관리자 화면을 명시한다.
- `.tools/bin/check-operational-status.php`에 신호 marker와 SQLite fixture를 추가한다.
- `docs/operational-status.md`의 표에 항목을 추가한다.

### 2차: 보상 placeholder 계열 정리

- `community.publisher_rewards.pending`
- `community.publisher_rewards.failed`

처리 상태: 완료.

- `/admin/community/publisher-rewards`가 실패 사유와 대상 식별값을 충분히 보여주는지 확인한다.
- pending이 정상적으로 오래 남을 수 있는 정책이 있는지 확인하고 허용 지연을 확정한다.

콘텐츠 작성자 보상은 별도 2.5차로 분리한다.

- `content.author_rewards.pending`
- `content.author_rewards.failed`

먼저 콘텐츠 제출/작성자 관리 화면 또는 별도 보상 로그 화면에 보상 로그, 실패 사유, 처리 기준을 노출한 뒤 운영 점검에 연결한다.

### 2.1차: legacy 자산 미회수 호환 신호 연결

- `community.asset_recovery_legacy.open`

처리 상태: 완료.

- 신규 기준은 `asset_recovery.open`이며, legacy 신호는 공통 자산 원장 이전/병행 기간의 잔여 row 누락 방지용이다.
- `/admin/community/recovery-failures` legacy route는 공통 미회수 화면으로 이동하므로 후속 확인 위치는 `/admin/assets/recovery-failures`로 통일한다.
- 대상 목록은 자산 모듈, 대상 유형/ID, 계정 ID를 최대 5개 표시한다.

### 3차: 업무 SLA 성격의 대기 항목은 운영 지연/실패 점검에서 제외

- `reward.withdrawal_requests.pending`
- `deposit.refund_requests.pending`

처리 상태: 현재 `운영 지연/실패 점검`에 포함하지 않음.

검토 기준:

- 시스템 이상 신호와 일반 업무 대기를 같은 색의 `지연 초과`로 섞지 않는다.
- 출금/환불 신청은 운영자가 심사하거나 처리해야 하는 업무 backlog이며, 배치/queue 실패나 중단 감지 대상이 아니다.
- 알림이 필요하다면 `운영 지연/실패 점검`이 아니라 관리자 대시보드의 `처리 대기` 카드, 각 자산 모듈 목록 필터, 또는 별도 업무 SLA 리포트로 분리한다.
- 별도 SLA 리포트를 만들더라도 상태 라벨은 `처리 대기`/`처리 지연`처럼 업무 처리 맥락을 사용하고, 실패/장애로 오인되는 `지연 초과` 라벨은 쓰지 않는다.
- SLA 기준은 시스템 공통 상수가 아니라 자산 모듈 설정, 운영 정책, 영업일 계산 기준을 먼저 정한 뒤 적용한다.

### 4차: job row 없는 고부하 작업의 설계 보완

- 커뮤니티 레벨 재계산처럼 클라이언트 cursor 기반 batch는 중단 감지가 어렵다.
- 운영 점검 대상이 되어야 한다면 작업 row, stage, cursor, lock token, updated_at, completed/failed 상태를 먼저 도입한다.
- job row 생성 전에는 부작용을 만들지 않는 원칙을 적용한다.

## 검증 계획

- 문서만 변경하는 조사 라운드는 `git diff --check`를 실행한다.
- 운영 점검 항목을 추가하는 구현 라운드는 최소 `php .tools/bin/check-operational-status.php`와 `php .tools/bin/check.php`를 실행한다.
- 로컬 PHP 내장 서버를 안전하게 띄울 수 있으면 `SR_SMOKE_BASE_URL=http://127.0.0.1:<port> php .tools/bin/smoke-http.php`로 `/admin/operations` 렌더링을 확인한다.

## 문서 영향

이 문서는 저장소 내 처리 계획이다. 실제 운영 점검 항목이 추가되면 관리자 화면 필드 가이드, 운영 절차, request flow 또는 testing guide에 `/admin/operations` 항목과 후속 처리 화면을 함께 반영한다.
