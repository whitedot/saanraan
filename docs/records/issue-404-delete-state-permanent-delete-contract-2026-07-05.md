# 이슈 #404 콘텐츠·퀴즈·설문 삭제 상태와 영구 삭제 계약

이 문서는 #404 구현 전 Gate인 G1~G5를 저장소 기준으로 승격한 설계 계약이다. 대상은 `content`, `quiz`, `survey` 세 모듈이며, 커뮤니티는 제외한다. 기존 삭제분 소급 복구, 소급 마이그레이션, 고아 파일 스캔은 범위에서 제외한다.

## 삭제 모델

| 단계 | 의미 | 파일 책임 | 관리자 표면 |
| --- | --- | --- | --- |
| 일반 삭제 | 원문 redaction, 공개 비노출, 복구 불가 운영 보존 | 소유 저장소 파일 삭제를 시도한다. 실패하면 cleanup failure로 남긴다. | 일반 편집 대상이 아니며 삭제됨 보기에서 최소 식별자와 보존 로그/cleanup failure 상태를 확인한다. |
| 영구 물리 삭제 | 이미 redaction된 본체 row와 삭제로 배정된 live 하위 row를 DB에서 제거한다. | 소유 파일을 처음 삭제하는 단계가 아니다. 남은 cleanup failure/pending row를 보존하고 재시도 가능하게 둔다. | 삭제됨 보기에서만 제공하고, ID 또는 slug/key 확인 문구와 영향 범위를 요구한다. |

## G1. 테이블 단위 삭제·보존 매트릭스

### 콘텐츠

| 테이블/참조 | 배정 | 기준 |
| --- | --- | --- |
| `sr_content_items` | 삭제 | `status = deleted`인 본체 row만 영구 삭제 대상이다. |
| `sr_content_revisions` | 삭제 | 삭제된 content의 원문 revision은 redaction 완료 후 live 참조가 아니므로 삭제한다. |
| `sr_content_submissions` | 삭제 | 제출 원문은 redaction되며 본체 영구 삭제 시 함께 제거한다. |
| `sr_content_comments` | 삭제 | 삭제된 content에 달린 댓글 row는 본체와 함께 제거한다. 댓글 자체 운영 보존이 필요하면 별도 이슈로 승격한다. |
| `sr_content_setting_sources` | 삭제 | 본체별 설정 source는 본체가 사라지면 의미가 없다. |
| `sr_content_series_items` | 삭제 | 시리즈 자체는 보존하되 대상 content 연결 row만 제거한다. |
| `sr_content_file_links` | 삭제 | 대상 content 연결 row만 제거한다. |
| `sr_content_files` | 삭제/보존 혼합 | 대상 content가 소유하거나 대상 content에만 연결된 row는 삭제한다. `content_id = 0` 미연결 row는 정상 임시/공용 상태일 수 있으므로 고아로 오판하지 않는다. |
| `sr_content_access_entitlements` | 삭제 | 본체 접근권은 대상 본체가 사라지면 live 권리로 유지하지 않는다. 환불/결제 증빙은 로그 snapshot에 의존한다. |
| `sr_content_file_download_logs` | 보존 | 다운로드/환불/분쟁 증빙이며 snapshot 기준으로 조회 가능해야 한다. |
| `sr_content_view_payment_logs` | 보존 | 결제 증빙이다. 본체 JOIN 없이 snapshot 표시가 가능해야 한다. |
| `sr_content_asset_access_logs` | 보존 | 금액성 원장성 증빙이다. |
| `sr_content_asset_action_logs` | 보존 | 금액성 원장성 증빙이다. |
| `sr_content_author_reward_logs` | 보존 | 보상/정산 증빙이다. |
| `sr_content_storage_cleanup_failures` | 보존 | 본체 row 없이도 재시도 가능해야 한다. |
| `sr_content_asset_policy_sets` | 보존 | 공유 정책 자원이며 content 영구 삭제로 제거하지 않는다. |
| URL embed cache | 삭제 | owner가 대상 content인 cache row와 target이 대상 content인 stale/deleted cache row를 제거한다. |
| reaction record | 삭제 | target이 대상 content/comment인 reaction row를 제거한다. |
| notification | 보존 | 기존 알림은 클릭 시 404 또는 안내로 fail-closed한다. |

### 퀴즈

| 테이블/참조 | 배정 | 기준 |
| --- | --- | --- |
| `sr_quiz_sets` | 삭제 | `deleted_at IS NOT NULL`인 본체 row만 영구 삭제 대상이다. `status = archived`는 삭제가 아니다. |
| `sr_quiz_sources` | 삭제 | 대상 quiz 연결 row를 제거한다. |
| `sr_quiz_questions` | 삭제 | redaction된 문제 구조는 본체와 함께 제거한다. |
| `sr_quiz_choices` | 삭제 | 대상 question 소유 row를 제거한다. |
| `sr_quiz_results` | 삭제 | 대상 quiz 결과 구조를 제거한다. |
| `sr_quiz_result_rules` | 삭제 | 대상 quiz 결과 규칙을 제거한다. |
| `sr_quiz_reward_policies` | 보존 | reward grant가 정책 ID를 참조할 수 있으므로 영구 삭제에서 제거하지 않는다. 목록에서는 본체 부재/삭제됨 정책으로 표시한다. |
| `sr_quiz_comments` | 삭제 | 대상 quiz 댓글 row를 제거한다. |
| `sr_quiz_attempts` | 익명화 후 보존 | 응시 증빙과 개인정보 export 안정성을 위해 account 연결 최소화 상태로 보존한다. 이미 redaction된 snapshot은 복구하지 않는다. |
| `sr_quiz_attempt_answers` | 익명화 후 보존 | attempt 하위 증빙으로 보존하되 redaction된 snapshot 기준이다. |
| `sr_quiz_attempt_result_scores` | 익명화 후 보존 | attempt 하위 결과 증빙으로 보존한다. |
| `sr_quiz_reward_grants` | 보존 | 자산 원장 reference anchor가 될 수 있다. redaction 후 남은 금액/reference와 provider reference만 증빙으로 삼는다. |
| URL embed cache | 삭제 | owner/target이 대상 quiz인 cache row를 제거하거나 stale/deleted 처리 후 purge한다. |
| reaction record | 삭제 | target이 대상 quiz/comment인 reaction row를 제거한다. |
| notification | 보존 | 기존 알림은 클릭 시 404 또는 안내로 fail-closed한다. |

### 설문

| 테이블/참조 | 배정 | 기준 |
| --- | --- | --- |
| `sr_survey_forms` | 삭제 | `deleted_at IS NOT NULL`인 본체 row만 영구 삭제 대상이다. |
| 설문 문항/선택지 구조 테이블 | 삭제 | 대상 survey 설계 구조를 제거한다. |
| `sr_survey_reward_policies` | 보존 | reward grant 추적을 위해 영구 삭제에서 제거하지 않는다. |
| `sr_survey_comments` | 삭제 | 대상 survey 댓글 row를 제거한다. |
| `sr_survey_responses` | 익명화 후 보존 | 응답 증빙과 export 안정성을 위해 account 연결 최소화 상태로 보존한다. 이미 redaction된 snapshot은 복구하지 않는다. |
| `sr_survey_response_answers` | 익명화 후 보존 | response 하위 증빙으로 보존하되 redaction된 snapshot 기준이다. |
| `sr_survey_reward_grants` | 보존 | 자산 원장 reference anchor가 될 수 있다. redaction 후 남은 금액/reference와 provider reference만 증빙으로 삼는다. |
| URL embed cache | 삭제 | owner/target이 대상 survey인 cache row를 제거하거나 stale/deleted 처리 후 purge한다. |
| reaction record | 삭제 | target이 대상 survey/comment인 reaction row를 제거한다. |
| notification | 보존 | 기존 알림은 클릭 시 404 또는 안내로 fail-closed한다. |

## G2. Cleanup Failure 인프라

기준 스키마는 `sr_content_storage_cleanup_failures`의 `status`, `attempt_count`, driver/key/source snapshot 모델로 삼는다. #404 구현에서는 퀴즈와 설문에도 같은 목적의 모듈별 cleanup failure 테이블을 추가한다.

- `sr_quiz_storage_cleanup_failures`
- `sr_survey_storage_cleanup_failures`

각 row는 본체 JOIN 없이 재시도 가능하도록 `source_type`, `source_id`, `storage_driver`, `storage_key`, `status`, `attempt_count`, `last_error`, `created_at`, `updated_at`을 자체 보유한다. 관리자 표면은 각 모듈의 삭제됨 보기 또는 저장소 정리 실패 보기에서 접근한다. 본체가 영구 삭제되어도 failure row는 삭제하지 않는다.

## G3. DB/Storage 순서와 멱등성

일반 삭제/redaction은 pending 선기록 구조로 이관한다.

1. 삭제 대상 파일 driver/key/path를 수집한다.
2. redaction DB 변경과 같은 transaction 안에서 cleanup failure row를 `pending`으로 선기록한다.
3. DB transaction을 commit한다.
4. commit 후 저장소 삭제를 실행한다.
5. 성공하면 `resolved`, 실패하면 `failure`로 남긴다.

Rollback 시 파일은 아직 삭제되지 않아야 한다. 영구 삭제는 cleanup row를 삭제하지 않고 본체와 삭제 대상 하위 row만 commit한다. 같은 영구 삭제 요청이 반복되면 이미 본체가 없거나 삭제 대상이 0건이어도 500 없이 종료해야 하며, UI에는 이미 처리됨 또는 찾을 수 없음으로 안내한다.

## G4. 환불·보상·자산·쿠폰 경로

영구 삭제는 운영 증빙 로그를 삭제하지 않는다. 콘텐츠 결제/다운로드/자산 로그는 snapshot 기준으로 조회되어야 하며, 환불과 권리 회수는 본체 row 부재를 500으로 만들면 안 된다. 콘텐츠 접근권은 영구 삭제 시 제거할 수 있으므로 환불 실행의 revoke 대상이 0건이면 이미 회수된 상태로 처리해도 된다.

퀴즈/설문 reward grant는 보존한다. 현재 redaction이 `request_snapshot_json`/`result_snapshot_json`을 비울 수 있으므로, #404 구현은 유실된 기존 snapshot을 소급 복구하지 않는다. 앞으로의 증빙은 grant 금액, provider reference, asset ledger transaction reason/reference로 충분하다고 본다.

## G5. 파일 책임 모델

영구 삭제는 소유 파일을 처음 삭제하는 단계가 아니다. 일반 삭제 UI는 “원문과 파일 삭제를 시도하며 실패 시 재시도 대상이 남을 수 있음”을 설명한다. 영구 삭제 UI는 “본체 및 삭제 대상 하위 데이터 삭제, 운영 증빙 로그와 cleanup failure 기록 보존 가능”을 설명하고, “모든 기록 삭제” 또는 “소유 파일 실제 삭제” 같은 표현을 쓰지 않는다.

## 관리자 UX 기준

- 삭제된 콘텐츠는 일반 편집 화면에서 수정 가능한 대상으로 보이지 않아야 한다.
- 삭제된 콘텐츠 복사, 저장, 일괄 상태 변경, 일반 삭제는 서버에서도 거부한다.
- 콘텐츠는 `status = deleted`, 퀴즈/설문은 `deleted_at IS NOT NULL`을 삭제 판정으로 사용한다.
- 퀴즈의 `status = archived`는 정상 보관 상태이며 영구 삭제 후보가 아니다.
- 삭제됨 보기에는 내부 ID, slug/key, 삭제 판정값, 삭제 시각, redaction 완료 여부, 보존 로그 카운트, cleanup failure 카운트, 영구 삭제 가능 여부를 표시한다.
- 영구 삭제 확인 문구는 content ID/slug, quiz ID/key, survey ID/key 중 하나를 요구한다.

## 자동 검사 기준

구현 커밋은 다음 회귀 검사를 추가해야 한다.

- 콘텐츠 deleted 상태에서 edit GET, save POST, copy POST, batch status POST fail-closed.
- 퀴즈 `archived` + `deleted_at IS NULL`이 영구 삭제 후보에서 제외됨.
- 콘텐츠/퀴즈/설문 삭제됨 보기와 영구 삭제 action이 삭제 판정값을 정확히 사용함.
- 영구 삭제 후 삭제 배정 테이블 row가 남지 않고, 보존 배정 로그는 snapshot 기준 조회 가능.
- 일반 삭제 저장소 실패 주입 시 cleanup failure row가 본체 JOIN 없이 재시도 가능.
- `sr_content_files.content_id = 0` row를 영구 삭제 고아로 오판하지 않음.
