# 결제 플러그인 연동 계획

이 문서는 산란에서 결제 기능과 PG 플러그인을 연동하기 위한 구현 계획이다.

문서 수명:

- 결제 모듈과 첫 PG 플러그인을 구현하기 전까지 계획 문서로 보관한다.
- 실제 구현과 검증이 완료되면 이 문서는 삭제한다.
- 구현 후 계속 유지해야 하는 기준은 `docs/module-guide.md`, `docs/core-decisions.md`, 결제 모듈 README 중 필요한 곳으로만 옮긴다.

## 기본 방향

결제는 코어가 아니라 선택 모듈과 플러그인으로 처리한다.

권장 구조:

- `payment`: 결제 시도, 승인 결과, 취소/환불 요청, 관리자 화면을 소유하는 공식 선택 모듈
- `payment_{provider}`: 특정 PG와 통신하는 플러그인
- 결제를 사용하는 업무 모듈: 주문, 예치금 충전, 유료 콘텐츠 같은 도메인 정책을 소유하는 모듈
- `core`: 결제 상태, 주문, 환불, PG 설정을 알지 않는다.

결제 모듈은 결제 흐름의 공통 상태를 관리하지만, 상품/주문/충전/정산 정책을 대신 소유하지 않는다.

## 책임 분리

### 결제 사용 모듈

예상 모듈:

- `shop_order`
- `deposit`
- `subscription`
- `paid_content`

책임:

- 결제 대상 금액과 설명 생성
- 결제 완료 후 자기 도메인 상태 변경
- 취소 가능 여부 판단
- 환불 가능 금액 판단
- 결제 실패 후 자기 화면 처리

결제 사용 모듈은 결제 모듈에 다음 정도만 요청한다.

```text
이 참조 대상에 대해 이 금액의 결제를 시작한다.
이 결제가 성공하면 내 confirm action으로 알려 달라.
```

### `payment` 모듈

책임:

- 결제 시도 생성
- 결제 상태 전이 관리
- PG 플러그인 선택
- 승인 callback/return/webhook 수신
- 결제 검증 후 사용 모듈 confirm 호출
- 취소/환불 요청 기록
- 관리자 결제 조회
- 감사 로그

소유하지 않는다:

- 주문 테이블
- 상품 테이블
- 장바구니
- 배송
- 정산 정책
- 예치금 충전 정책
- 쿠폰/할인 정책

### PG 플러그인

예상 플러그인:

- `payment_toss`
- `payment_nice`
- `payment_inicis`
- `payment_kakaopay`
- `payment_naverpay`

책임:

- PG별 설정 필드 정의
- 결제창 시작 파라미터 생성
- 승인 API 호출
- 취소/환불 API 호출
- webhook 서명 검증
- PG 응답을 산란 공통 결과로 변환
- PG 원문 응답의 안전한 보관 범위 판단

PG 플러그인은 주문, 회원 자산, 상품 상태를 직접 변경하지 않는다.

## 계약 파일

계약 파일을 과하게 늘리지 않고, `payment` 모듈이 활성 플러그인의 단일 파일을 명시적으로 읽는다.

권장 파일:

```text
modules/payment_toss/payment-provider.php
modules/payment_nice/payment-provider.php
modules/payment_inicis/payment-provider.php
```

`payment-provider.php`는 배열을 반환한다.

예상 값:

- provider_key
- display_name
- supported_methods
- supported_actions
- settings_schema
- action_handlers
- webhook_paths
- return_paths

예시:

```php
<?php

return [
    'provider_key' => 'toss',
    'display_name' => '토스페이먼츠',
    'supported_methods' => ['card', 'transfer', 'virtual_account'],
    'supported_actions' => ['prepare', 'approve', 'cancel'],
    'settings_schema' => [
        'client_key' => ['type' => 'string', 'secret' => false],
        'secret_key' => ['type' => 'string', 'secret' => true],
    ],
    'handlers' => [
        'prepare' => 'helpers/provider.php:sr_payment_toss_prepare',
        'approve' => 'helpers/provider.php:sr_payment_toss_approve',
        'cancel' => 'helpers/provider.php:sr_payment_toss_cancel',
        'verify_webhook' => 'helpers/provider.php:sr_payment_toss_verify_webhook',
    ],
];
```

자동 등록이나 service provider 방식은 사용하지 않는다.

## 데이터 저장 계획

`payment` 모듈 예상 테이블:

- `sr_payment_attempts`
- `sr_payment_events`
- `sr_payment_refunds`
- `sr_payment_provider_settings`

### `sr_payment_attempts`

결제 1건의 공통 상태를 저장한다.

예상 컬럼:

- id
- payment_key
- provider_key
- method
- account_id
- subject_module
- subject_type
- subject_id
- amount
- currency
- status
- requested_at
- approved_at
- failed_at
- canceled_at
- expires_at
- idempotency_key
- return_path
- confirm_path
- description
- raw_summary_json
- created_at
- updated_at

`subject_module`, `subject_type`, `subject_id`는 결제를 사용하는 모듈의 대상을 가리키는 안정 식별자다.

### `sr_payment_events`

승인, 실패, webhook, 취소 요청 같은 이벤트를 append-only로 저장한다.

예상 컬럼:

- id
- attempt_id
- event_type
- provider_event_id
- status_before
- status_after
- payload_json
- created_at

민감 정보는 저장하지 않는다. 저장해야 하는 원문도 카드번호, 인증값, secret, 개인정보를 제거한 요약으로 제한한다.

### `sr_payment_refunds`

취소/환불 요청과 결과를 저장한다.

예상 컬럼:

- id
- attempt_id
- refund_key
- amount
- reason
- status
- requested_by
- requested_at
- completed_at
- failed_at
- provider_refund_id
- result_json

환불 가능 여부와 환불 후 도메인 상태 변경은 결제 사용 모듈이 판단한다.

### `sr_payment_provider_settings`

PG별 설정을 저장한다.

예상 컬럼:

- provider_key
- setting_key
- setting_value
- is_secret
- updated_at

secret 값은 평문 저장을 피한다. shared hosting 환경에서 완전한 secret manager를 전제할 수 없으므로, 최소한 관리자 화면에서는 재표시하지 않고 변경만 허용한다.

## 상태 모델

공통 결제 상태:

- `draft`
- `ready`
- `pending`
- `approved`
- `failed`
- `canceled`
- `partial_canceled`
- `expired`

상태 전이 원칙:

- `approved` 이후 같은 승인 callback이 반복되어도 멱등 처리한다.
- `failed`, `canceled`, `expired` 상태에서 승인으로 되돌리지 않는다.
- 부분 취소는 누적 환불액으로 계산한다.
- PG webhook과 사용자 return이 순서 없이 도착해도 같은 결과가 되어야 한다.

## 결제 시작 흐름

```text
사용 모듈
-> payment attempt 생성 요청
-> payment 모듈이 provider 선택
-> provider 플러그인 prepare 호출
-> 결제창 또는 외부 결제 URL로 이동
-> 사용자 return 또는 webhook 수신
-> provider 플러그인 approve/verify 호출
-> payment 상태 확정
-> 사용 모듈 confirm action 호출
```

사용 모듈 confirm은 반드시 멱등해야 한다.

예:

```text
deposit 충전 결제 성공
-> payment approved
-> deposit confirm
-> deposit transaction 생성
-> 이미 처리된 payment_key면 재처리하지 않음
```

## 취소/환불 흐름

```text
사용 모듈 또는 관리자
-> 환불 가능 여부 확인
-> payment refund 요청
-> provider 플러그인 cancel 호출
-> payment refund 결과 저장
-> 사용 모듈 refund confirm 호출
```

결제 모듈은 환불 API 호출과 결과 기록을 담당한다. 환불로 주문을 취소할지, 예치금 차감이 가능한지, 부분 환불을 허용할지는 사용 모듈이 책임진다.

## webhook과 보안

PG webhook은 직접 접근 가능한 public action으로 받는다.

필수 조건:

- 활성 provider인지 확인
- provider별 서명 검증
- 요청 method 검증
- replay 방지
- provider_event_id 중복 처리
- raw secret 로그 금지
- 상태 전이 멱등 처리

webhook action은 CSRF를 요구하지 않는다. 대신 provider 서명 검증과 이벤트 중복 검증을 필수로 한다.

관리자 설정, 환불, 수동 상태 확인은 CSRF와 관리자 권한을 요구한다.

## 관리자 화면

예상 화면:

- `/admin/payments`
- `/admin/payments/{id}`
- `/admin/payment-providers`

기능:

- 결제 목록 조회
- 상태/PG/기간/회원/참조 모듈 필터
- 결제 상세와 이벤트 로그
- PG 설정
- 테스트 모드 설정
- 환불 요청
- webhook 수신 이력 확인

PG secret은 관리자 화면에 다시 표시하지 않는다.

## 1차 구현 범위

초기 구현은 하나의 PG 플러그인과 하나의 사용 모듈로 제한한다.

권장 조합:

- 사용 모듈: `deposit`
- 결제 모듈: `payment`
- PG 플러그인: `payment_toss`

이유:

- 예치금 충전은 상품, 배송, 주문 취소 같은 커머스 정책보다 범위가 좁다.
- 결제 성공 후 처리해야 할 도메인 결과가 명확하다.
- 결제 모듈과 PG 플러그인 계약을 검증하기 좋다.

1차 포함:

- 결제 시도 생성
- PG prepare/approve
- 사용자 return 처리
- webhook 처리
- deposit 충전 confirm
- 관리자 결제 목록/상세
- 전체 취소
- 테스트 모드 설정

1차 제외:

- 부분 취소
- 가상계좌 입금 대기
- 정기결제
- 해외 통화
- 다중 PG 라우팅
- 주문/배송/장바구니
- PG별 고급 결제수단 전체 지원

## 구현 단계

1. `payment` 모듈 골격과 설치 SQL을 만든다.
2. `payment-provider.php` 계약 파일 형식을 구현한다.
3. provider 설정 저장과 관리자 화면을 만든다.
4. payment attempt 생성 helper를 만든다.
5. `payment_toss` 플러그인을 하나의 기준 provider로 구현한다.
6. return/webhook action을 구현하고 멱등 처리를 검증한다.
7. `deposit` 모듈에 결제 충전 시작/confirm 흐름을 붙인다.
8. 관리자 결제 목록/상세/이벤트 로그를 만든다.
9. 전체 취소를 구현한다.
10. 테스트 결제, 중복 callback, 실패 callback, webhook 재전송을 검증한다.
11. 구현 완료 후 이 계획 문서를 삭제하고 필요한 기준만 유지 문서로 옮긴다.

## 구현 전 확인할 외부 문서

PG별 실제 구현에 들어갈 때는 반드시 공식 문서를 확인한다.

- 결제창 시작 방식
- 승인 API
- 취소 API
- webhook 서명 검증
- 테스트/운영 키 구분
- 가상계좌, 간편결제, 부분취소 지원 범위
- 에러 코드와 재시도 기준

이 계획 문서에는 외부 API의 세부 endpoint나 파라미터를 고정하지 않는다.
