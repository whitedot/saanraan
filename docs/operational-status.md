# 운영 상태 점검 기준

이 문서는 공유호스팅처럼 상시 worker를 전제로 하지 않는 환경에서 지연 작업과 실패 작업을 어떻게 확인할지 정리한다.

산란은 이메일 발송, 포인트 만료, 저장소 정리 실패 재시도, 게시판 복사 작업, 보상 지급 실패 복구 같은 흐름을 모두 실시간 worker에 맡기지 않는다. 일부 처리는 관리자 요청, 사용자 요청, cron, CLI 수동 실행, 또는 관리자 화면의 재시도 버튼으로 진행된다. 따라서 운영 기준은 "항상 즉시 처리"가 아니라 "지연과 실패를 발견하고 안전하게 재시도할 수 있음"이다.

## 기본 원칙

- 공유호스팅 환경에서는 cron이 없거나 느릴 수 있음을 전제로 한다.
- queue나 batch 작업은 실시간 처리를 보장한다고 설명하지 않는다.
- 지연이 허용되는 작업과 즉시 실패로 처리해야 하는 작업을 구분한다.
- 운영 DB에서 파괴적 smoke를 실행하지 않는다.
- read-only 점검 명령은 운영 상태 확인에 사용할 수 있지만, 정정/재시도는 관리자 권한과 감사 로그가 남는 흐름으로 처리한다.
- `storage/logs/error.log`에 쓸 수 없는 환경에서도 예외 로깅 실패가 PHP warning으로 화면에 노출되면 안 된다. 로그 파일 권한이 의심되면 배포 보호 점검과 함께 웹서버/PHP 실행 사용자의 `storage/logs` 쓰기 권한을 확인한다.
- 모듈 백업과 zip 업로드 임시 작업 보관 정리는 각각 `storage/module-backups`, `storage/module-upload` 내부의 실제 디렉터리만 대상으로 하며, 심볼릭 링크나 작업 루트 밖으로 해석되는 경로는 집계와 삭제 대상에서 제외한다. zip 업로드 임시 작업은 `upload-YYYYMMDDHHMMSS-{12자리 hex}` 형식의 오래된 작업 디렉터리만 정리한다.

## Read-Only 상태 점검

설치된 로컬 또는 staging 환경에서 다음 명령으로 주요 지연 신호를 확인한다.

```sh
php .tools/bin/ops-status.php
```

이 명령은 데이터를 변경하지 않고 다음 항목의 status, count, 허용 지연, 가장 오래된 시각을 출력하고 마지막에 `summary` 행으로 status별 개수와 전체 대기/실패 건수를 요약한다. 실패 항목은 건수가 생기는 즉시 `지연 초과`로 본다.

| 항목 | 의미 | 허용 지연 | 후속 확인 |
| --- | --- | --- | --- |
| `notification.deliveries.queued` | 이메일 등 외부 delivery가 대기 또는 처리 중 | 1시간 | 알림 관리자 delivery 목록, provider 설정, runner 실행 상태 |
| `notification.deliveries.failed` | delivery 실패 또는 dead-letter가 남아 있음 | 즉시 | 실패 사유, 설정 수정, 재발송 또는 취소 기준 |
| `content.storage_cleanup.pending` | 콘텐츠 삭제 후 저장소 파일 정리 실패 | 24시간 | 콘텐츠 관리자 정리 실패 목록과 재시도 |
| `community.storage_cleanup.pending` | 커뮤니티 삭제 후 저장소 파일 정리 실패 | 24시간 | 게시판 관리자 정리 실패 목록과 재시도 |
| `community.board_copy.active` | 게시판 복사 작업이 대기 또는 실행 중 | 15분 | 복사 작업 진행 상태와 lock 만료 |
| `community.board_copy.failed` | 게시판 복사 실패 또는 취소 기록 | 즉시 | 실패 단계, 부분 생성물 정리 |
| `quiz.reward_grants.pending` | 퀴즈 보상 지급 대기 | 15분 | 보상 정책, 자산/쿠폰 provider 상태 |
| `quiz.reward_grants.failed` | 퀴즈 보상 지급 실패 | 즉시 | 관리자 복구 또는 수동 완료 |
| `survey.reward_grants.pending` | 설문 보상 지급 대기 | 15분 | 보상 정책, 자산/쿠폰 provider 상태 |
| `survey.reward_grants.failed` | 설문 보상 지급 실패 | 즉시 | 관리자 복구 또는 수동 완료 |
| `point.expiration.due` | 만료 시각이 지난 포인트 잔여분 | 24시간 | `php .tools/bin/expire-points.php` 또는 다음 포인트 거래 |

미설치 환경에서는 명령이 `saanraan is not installed.`를 출력하고 종료한다. 모듈이 비활성화되어 있으면 해당 항목은 `skipped`로 표시한다.

관리자 화면에서는 `/admin/operations`의 `운영 상태` 화면에서 같은 read-only 기준을 확인한다. 이 화면은 CLI와 같은 `sr_admin_operational_status_rows()` 기준을 사용하며, 대기/실패 count, 허용 지연, 가장 오래된 시각, 후속 확인 위치를 보여준다. 화면은 데이터를 바꾸지 않으므로 재시도나 정정은 각 소유 모듈의 관리자 action에서 처리한다.

운영 상태 점검 정의는 코드 내부 목록으로만 관리한다. `table`과 `age_column`은 단일 SQL 식별자만 허용하고, `where` 조건은 세미콜론, SQL 주석, DDL/DML 키워드가 있으면 오류로 처리한다. `.tools/bin/check-operational-status.php`는 안전한 조건, 위험한 식별자, 위험한 `where` 조건, CLI row/summary 출력 형식, 번들 신호 일부의 실제 count/overdue 계산을 SQLite fixture로 확인해 read-only 점검 경계를 유지한다.

### 알림 delivery 재시도/취소 기준

`/admin/notification-deliveries`는 외부 발송 작업 상태를 확인하고 이메일 delivery runner를 수동 실행할 수 있다. runner는 `queued` 작업을 `processing`으로 claim한 뒤 성공하면 `sent`, 실패하면 backoff를 둔 `queued` 또는 최대 시도 초과 시 `dead`로 전환한다. 재시도는 `failed`, `canceled`, `dead` 상태를 `queued`로 되돌리는 작업이며, 이때 provider message ID, 오류 메시지, 시도 시각, lock, 다음 시도 시각을 비워 다음 발송 시도와 이전 실패를 분리한다. 취소는 `queued`, `processing`, `failed`, `dead` 상태를 `canceled`로 바꾸는 작업이다. `sent`는 터미널 상태로 보고 재시도나 취소 대상으로 되돌리지 않는다. 수동으로 `failed`, `dead`, `sent`로 표시하는 전이는 운영자가 외부 provider 상태를 확인한 뒤 수행하며, 모든 상태 변경과 수동 runner 실행은 CSRF, `/admin/notification-deliveries` edit 권한, 조건부 상태 업데이트, 감사 로그 기록을 거쳐야 한다.

알림 delivery 기본 실행 모델은 공유호스팅을 기준으로 한다. 웹 GET 요청 말미에는 설정된 간격마다 작은 배치만 처리하고, 관리자는 `/admin/notification-deliveries`에서 대기 작업을 수동 실행할 수 있다. cron이 가능한 환경에서는 다음 CLI runner를 권장한다.

```sh
php .tools/bin/run-notification-deliveries.php
```

## 포인트/금액 정합성 점검

포인트, 적립금, 예치금 원장은 설치된 환경에서 다음 read-only 명령으로 잔액 행, 거래 합계, 마지막 거래 `balance_after`, 거래별 `balance_after` 연쇄를 비교한다.

```sh
php .tools/bin/reconcile-assets.php
```

관리자 화면에서는 `/admin/assets/reconciliation`의 `포인트/금액 정합성 점검` 화면에서 같은 기준을 확인한다. 이 화면은 데이터를 바꾸지 않고 불일치 유형, 계정 ID, 거래별 잔액 연쇄가 처음 깨진 transaction ID와 기대/실제 `balance_after`를 보여주며, 요약 섹션 하단에는 `점검 완료`/`건너뜀`/`오류` 상태 설명을, 불일치 섹션 하단에는 유형별 의미를 함께 표시한다. 완료 환전 묶음 정정은 환전 모듈 소유 흐름으로 분리해 `/admin/asset-exchange/logs`의 CSRF/edit 권한 기반 action에서 반대 원장 거래와 감사 로그를 남긴다. 일반 원장 불일치 자동 정정은 별도 관리자 action, 승인 기준, 감사 로그가 준비된 뒤에만 추가한다.

### 자산 불일치 대응 절차

`php .tools/bin/reconcile-assets.php` 또는 `/admin/assets/reconciliation`에서 불일치가 나오면 운영자는 자동으로 balance row를 직접 수정하지 않는다. 먼저 해당 계정과 자산 모듈의 신규 금액성 action을 중단하거나 유지보수 시간으로 전환하고, 같은 명령과 관리자 화면을 다시 실행해 일시적 조회 문제가 아닌지 확인한다.

불일치 기록에는 자산 모듈, 계정 ID, 불일치 유형, 저장 잔액, 거래 합계, 마지막 거래 `balance_after`, 거래별 잔액 연쇄가 처음 깨진 transaction ID, 기대/실제 `balance_after`, 실행 시각, 실행한 환경을 남긴다. 이 값은 릴리스 검증 기록이나 운영 사고 기록에 그대로 옮겨 적을 수 있어야 한다.

원인 확인은 원장 거래와 소유 도메인 로그를 함께 본다. 환전 묶음처럼 소유 모듈이 정정 action을 제공하는 경우에는 `/admin/asset-exchange/logs`의 `환전 묶음 정정`처럼 반대 원장 거래와 감사 로그가 남는 흐름만 사용한다. 포인트, 적립금, 예치금의 일반 조정/환불/회수는 각 자산 모듈의 관리자 거래 화면에서 서버 검증을 거친 action으로 처리하고, 참조 유형과 참조 ID, 운영 메모에 원인 기록을 남긴다.

DB에서 balance row, 거래 row, `balance_after`를 직접 UPDATE하는 응급 수정을 1.0 기준 정상 절차로 인정하지 않는다. 불가피한 수동 DB 정정이 필요하면 운영 DB 백업, 영향 계정 목록, 실행 SQL, 재실행한 reconciliation 결과, 승인자를 별도 사고 기록에 남기고, 같은 상황을 관리자 action이나 fixture로 옮길 수 있는지 후속 작업으로 등록한다.

## 지연 허용 기준

| 작업 | 지연 허용 | 주의 기준 |
| --- | --- | --- |
| 사이트 알림 생성 | 낮음 | 생성 실패가 원 업무 실패로 전파되지 않아야 함 |
| 이메일 delivery | 중간, 1시간 | `queued`/`processing`이 오래 남거나 `failed`/`dead`가 증가하면 provider 설정과 runner 확인 |
| 포인트 만료 | 중간, 24시간 | 만료 예정 잔여분이 누적되면 수동 만료 실행 |
| 저장소 파일 정리 | 중간, 24시간 | 실패 항목이 계속 남으면 파일 유실/권한 문제 확인 |
| 게시판 복사 | 낮음, 15분 | `running` lock이 오래 유지되면 takeover 또는 실패 처리 기준 확인 |
| 퀴즈/설문 보상 지급 | 낮음, 15분 | `pending`/`failed`가 남으면 중복 지급 없이 복구해야 함 |

## Cron 후보

cron을 사용할 수 있는 환경에서는 다음을 후보로 둔다. cron이 없으면 관리자 수동 실행 또는 사용자 요청 시점 처리 기준을 문서화한다.

```sh
php .tools/bin/expire-points.php --dry-run
php .tools/bin/expire-points.php
php .tools/bin/ops-status.php
php .tools/bin/run-notification-deliveries.php
```

`ops-status.php`는 read-only라 자동 실행해도 데이터를 바꾸지 않는다. 출력 결과를 운영 로그에 남기면 지연 증가 추세를 확인할 수 있다. `expire-points.php --dry-run`은 만료 대상 건수와 금액만 출력하고 원장을 만들지 않는다. `expire-points.php`는 만료 대상 포인트를 실제 `expire` 원장 거래로 차감하는 변경 명령이므로 운영 DB에서는 실행 전 `ops-status.php`, `expire-points.php --dry-run`, 또는 관리자 운영 상태 화면에서 대상 규모를 먼저 확인한다.

## 기록 기준

릴리스 후보 또는 운영 점검에서는 [릴리스 검증 기록 템플릿](release-verification-template.md)의 `queue/cron/배치 작업` 항목에 다음을 기록한다.

- `php .tools/bin/ops-status.php` 실행 결과 또는 미실행 사유
- `php .tools/bin/reconcile-assets.php` 또는 `/admin/assets/reconciliation` 실행 결과
- 포인트 만료 처리 실행 여부
- 저장소 정리 실패 재시도 여부
- 게시판 복사 작업의 active/failed 상태
- 보상 지급 pending/failed 상태
- 실패가 회귀인지, 환경 미준비인지, 기존 보완 항목인지

## Board Copy Lock 기준

게시판 복사 batch job의 `lock_token`은 fencing token이다. 실행 요청이 lock을 얻은 뒤 stage를 처리할 때는 현재 job row가 `running` 상태이고 같은 `lock_token`을 유지하는지 확인한다. token을 잃은 요청은 map 상태, 복사 결과, cleanup 결과를 계속 쓰면 안 된다.

`.tools/bin/check-community-board-copy-job-lock.php`는 이 기준을 고정한다. 체커는 `sr_community_board_copy_job_assert_lock()`이 stale token, 빈 token, 종료된 job을 거부하는지 SQLite fixture로 확인하고, stage/map 처리 helper가 lock token을 받는 marker를 점검한다.

## 1.0 전 보강 대상

- 게시판 복사 job lock 만료 takeover와 늦은 쓰기 거부를 설치 DB 또는 staging에서 smoke 기록으로 남긴다.
- 퀴즈/설문 보상 실패 복구 절차를 자산 reconciliation과 연결해 기록한다.
