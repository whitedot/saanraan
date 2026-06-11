# 마일스톤 28 통화·정산 정책 기록 - 2026-06-11

마일스톤 28은 사이트 기본 통화 변경 기능을 여는 작업이 아니라, 기본 통화 잠금 이후 통화·정산·구매력·환불·통계 정책이 서로 다른 기준을 만들지 않도록 exit checklist를 고정하는 작업으로 처리한다. #117은 reference/open 상태로 둘 수 있으며, 완료 판단은 #312-#319와 #115/#315/#62/#116 참조가 아래 기준과 충돌하지 않는지로 본다.

## 기본 통화와 추가 통화

- `site.default_currency`는 신규 가격/정책 row의 기본값일 뿐 기존 가격, 거래, 구매력 snapshot을 변환하거나 재해석하는 전역 스위치가 아니다.
- 설치 시 기본 통화는 core/settings의 known currency min-unit registry에서 고르고, 설치 후 일반 설정에서는 읽기 전용으로만 보여준다.
- 추가 통화나 가격/정책 row별 currency 입력은 #115 settlement snapshot field contract와 #315 registry freeze 뒤에 별도 구현 이슈로 연다.
- 추가 통화를 열더라도 실제 차감 기준은 가격/정책 row의 settlement currency와 자산별 `purchase_power.settlement_currency` 일치 여부다.
- 비로그인 fallback, 회원 선택 통화, locale formatting은 표시 계층이며 실제 차감 currency를 바꾸지 않는다.
- 추가 통화 비활성화는 기존 가격 row와 로그를 숨기거나 변환하지 않고, 신규 생성/수정/차감 가능 여부만 제한하는 방향을 기본값으로 둔다.

## 환율과 환산 표시

- 환율은 초기 범위에서 실제 차감 기준으로 쓰지 않고 통계/표시 보조값으로만 검토한다.
- 환율 미설정, 만료, 조회 실패는 실제 구매 차감을 실패시키지 않는다. 환산 표시나 통계 화면에서는 계산 불가 상태와 마지막 성공 기준 시각을 보여준다.
- 환산 금액을 저장하거나 노출하면 환율 값, 기준 통화, 대상 통화, 기준 시각, 출처, `exchange_rate_policy_version`, 환산 rounding 기준을 함께 남긴다.
- 기존 로그는 저장된 `settlement_amount`, `settlement_currency`, `purchase_power_snapshot_json`, `snapshot_schema_version`, `rounding_policy_version`으로만 읽고 환율 변경으로 재계산하지 않는다.
- 외부 환율 API secret/API key는 새 저장소를 만들지 않고 기존 관리자 secret 설정/마스킹 primitive와 운영 로그 sanitize 기준을 따른다.
- 사용자 화면의 환산 금액은 `예상/참고`와 `실제 차감 기준`을 명확히 구분해야 한다.

## Version Registry와 0원 분류

- `snapshot_schema_version`은 settlement/reversal snapshot 구조와 직렬화 형식 version이다.
- `rounding_policy_version`은 minor unit 변환, fractional carry, 마지막 자산 정확 충당, reversal 금액 배분/잔여 처리 같은 계산 정책 version이다.
- `exchange_rate_policy_version`은 환율 환산 표시를 열 때만 쓰는 환율 출처/만료/환산 rounding 정책 version이며 실제 차감 기준 version이 아니다.
- `settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나로 시작한다.
- `free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함한다. 자산 증감량은 `direction`과 `asset_amount`로 별도 해석한다.
- `legacy_unknown` row는 운영 합계에서 임의로 매출/무료 어느 쪽으로 재분류하지 않고 통계/export에서 별도 bucket으로 보여준다.
- `legacy_unknown` row의 환불/정정은 자동 금액 환불 대상이 아니라 관리자 검토 대상으로 둔다.

## 구매력 변경과 일괄 변환

- 구매력 변경은 기존 로그를 재해석하지 않고, 변경 이후 새 요청만 새 `purchase_power_snapshot_json`과 `rounding_policy_version`을 남긴다.
- DB 기반 구매력 변경 UI를 열기 전에는 변경 이력, 관리자 재인증 또는 승인, 변경 사유, 감사 로그, 적용 시각, 확인 token 무효화 기준을 별도 범위로 분리한다.
- 구매력 변경과 가격/정책 통화 일괄 변환 batch apply는 같은 자산/통화/도메인 scope에서 동시에 실행하지 않는다.
- #319 dry-run/apply는 dry-run 시점의 purchase power version, 가격 row version, currency registry, `rounding_policy_version`을 저장하고 apply 직전에 같은 기준인지 재검증한다.
- 가격/정책 통화 일괄 변환은 가격/정책 row만 대상으로 하고, 기존 settlement snapshot과 차감 로그는 변환하지 않는다.
- 금액성 일괄 작업은 #268의 선택 snapshot 기준과 #269의 job lock, 멱등성, cursor/resume 기준을 먼저 따른다.

## Reversal, 통계, Export

- 환불·취소·정정의 소유자는 원 차감/접근권/다운로드 이력을 소유한 도메인 모듈이다. 전역 코어 reversal 테이블을 기본값으로 만들지 않는다.
- reversal snapshot 후보의 정규 필드는 `original_settlement_log_id`, `reversal_amount`, `reversal_currency`, `reversal_asset_amount`, `reversal_reason`, `rounding_policy_version`, `snapshot_schema_version`, `created_at`이다.
- 부분 환불/정정은 원 settlement snapshot의 `settlement_amount`, `asset_amount`, `purchase_power_snapshot_json`, `rounding_policy_version`을 기준으로 하며 현재 구매력으로 재계산하지 않는다.
- `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown`은 모두 settlement amount가 0일 수 있어도 통계와 export에서 별도 count 또는 filter로 구분한다. `free` 중 지급/적립 row는 매출이 아니라 자산 지급 지표로 분리한다.
- 통계 합계는 기본적으로 `settlement_currency`별로 분리하고, 환산 합계는 환율 정책이 실제 구현으로 분리되기 전까지 제공하지 않는다.
- export/cache key는 `settlement_currency`, `snapshot_schema_version`, `rounding_policy_version`, `settlement_kind`, reversal 포함 여부를 명시적으로 구분한다.
- CSV/admin/privacy export는 raw JSON만 내보내지 않고 저장 기준인 minor unit, settlement currency, asset amount, `snapshot_schema_version`, `rounding_policy_version`과 사람이 읽을 수 있는 settlement 요약을 함께 제공한다.
- 개인정보 export에는 사용자 이해에 필요한 정산 요약을 포함하되, 관리자 사유, 내부 감사 metadata, 외부 provider raw payload는 최소화한다.

## 검증 기록

- #312 기본 통화 설치 선택/잠금은 코드와 Wiki에 반영하고 `php .tools/bin/check.php`를 통과했다.
- #115/#315 settlement snapshot 보강으로 content/community 로그에 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`을 추가하고 privacy export 요약에 포함했다.
- HTTP smoke는 로컬 `config/config.php` 권한 문제로 500 응답이 발생해 완료하지 못했다. 실패 원인은 include 권한 오류이며, 이번 정책/스키마 변경의 HTTP 동작 회귀로 판단하지 않는다.
