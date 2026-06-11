# 이슈 115 settlement 기반 복합 차감 계약 결정

2026-06-11 기준으로 이슈 115의 blocking/high 항목은 구현 전 계약 결정으로 먼저 고정했다. 이후 같은 작업에서 콘텐츠/커뮤니티 차감 로그에 `settlement_amount`, `settlement_currency`, `purchase_power_snapshot_json`을 추가하고, 차감 실행은 기준가격을 각 자산의 `purchase_power`로 정확히 충당 가능한 범위에 배분하도록 도입했다.

## 결정

- 멱등 key는 회원, 소비 모듈, `reference_type`, `reference_id`, 기준금액, 기준 통화, 클라이언트 요청 토큰처럼 안정 입력에서만 파생한다. 자산별 차감량, 잔액 snapshot, settlement 배분 결과, 확인 fingerprint는 검증과 기록 대상이며 dedupe key 입력이 아니다.
- 클라이언트 요청 토큰은 HTTP attempt가 아니라 구매 의도(intent)마다 확인 화면 렌더 시점에 1회 생성해 확정 POST 재시도 전체에서 동일하게 운반한다.
- 실행 트랜잭션은 원장 row lock보다 먼저 안정 입력 기반 dedupe key의 claim row를 insert해야 하며, 이 key에는 DB unique 제약을 둔다. duplicate-key가 나면 동시 중복으로 보고 `processing` 또는 저장된 성공 결과를 반환하며, lock 획득 뒤에도 claim row 상태를 다시 확인한다.
- 최초 성공 결과는 claim row와 함께 저장한다. claim row의 TTL은 확인 token의 staleness window보다 길어야 하며, window를 막 지난 late duplicate도 새 실행으로 보지 않고 저장된 성공 결과와 표시용 snapshot을 반환하고 원장을 다시 만들지 않는다.
- 재검증 거부나 실행 실패는 같은 transaction rollback으로 claim row도 사라지게 두며, 재시도 시 저장된 거부 결과를 반환하지 않고 현재 상태로 부작용 없이 재평가한다.
- 확인 화면 plan은 실행 시점에 row lock 아래에서 재검증한다. 잔액 부족/동시 차감과 구매력 snapshot, 통화 min-unit, `policy_version` drift를 별도 무효화 사유로 기록하고, 어느 경우든 재계획하지 않고 거부해 재확인을 요구한다.
- 마지막 자산의 잔여 settlement 흡수는 정확 충당이 가능한 범위까지만 허용하고, 정확 충당 불가분을 메우기 위한 ceil overpay는 허용하지 않는다. 1P = 10 KRW, 가격 1,005 KRW처럼 정확한 통화 최소단위 합계를 만들 수 없으면 잔액 부족/결제 불가로 처리한다.
- `purchase_power`는 `asset_units`, `settlement_units`, `settlement_currency` 구조로 snapshot에 저장한다. 내부 계산은 `settlement_units / asset_units` rational로 수행한다. `asset_units`와 `settlement_units`는 양의 정수, `settlement_currency`는 core/settings min-unit registry에 존재하는 통화인지 설정 저장 또는 관리자 config 로드 시점에 검증한다.
- 런타임 통화 불변식은 `price.currency == 각 참여 자산의 purchase_power.settlement_currency`이며, 통화 min-unit registry는 core/settings가 소유한다. `site.default_currency`는 새 가격 생성 기본값일 뿐 실행 가능 여부의 기준이 아니다.
- settlement log snapshot에는 구매력 snapshot, `currency_min_unit`, 반올림/carry `policy_version`을 함께 저장한다.
- 통화 min-unit 또는 rounding/carry `policy_version` 변경 시 기존 확인 화면의 in-flight 요청이 fail-closed 재확인으로 떨어질 수 있음을 운영 워크플로에 안내한다.
- `deduction_order` 동률은 `asset_module` 사전순으로 정렬한다.
- 다중 자산 row lock은 `deduction_order`와 `asset_module` tiebreak 순서대로 잡아 동시 복합 차감 간 deadlock 가능성을 낮춘다.
- `member-assets.php` 거래 helper는 같은 PDO transaction에 동참해야 하며 내부 commit이나 별도 connection을 쓰면 복합 차감 후보에서 제외한다.
- 기준금액 0은 원장 차감 없이 접근권 또는 완료 로그를 `settlement_amount=0`으로 남기고, 안정 입력 기반 dedupe key로 중복 생성을 막는다.
- 포인트 부족 시 적립금 자동 환전 결제 같은 환전 fallback은 이 범위에 포함하지 않는다.
- `check-asset-settlement-contract.php`는 계약 문구 삭제를 막는 정적 가드이며, transaction 동참, carry, overpay, lock 순서의 런타임 준수는 구현 시점의 fixture 기반 테스트와 필요한 HTTP smoke로 검증한다.
- InnoDB의 미커밋 unique claim 중복 insert는 선행 transaction commit/rollback까지 블록될 수 있으므로, 구현 fixture는 commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 함께 검증한다.

## 후속 구현 체크포인트

- legacy backfill은 자산별 과거 암묵 rate를 감사한 뒤 적용하고, 근거가 없을 때만 1:1을 default로 삼는다.
- 관리자 설정/로드 시점에 구매력 필드 형식, 가격 통화와 자산 settlement 통화 불일치를 설정 오류로 노출한다.
- 개인정보 export는 `purchase_power_snapshot`을 raw JSON만 내보낼지 사람이 읽을 형태를 함께 제공할지 export 정책과 맞춰 정한다.
- USD-cent 같은 sub-unit 통화성 자산은 asset amount 저장 단위 결정을 별도 이슈로 다룬다.
