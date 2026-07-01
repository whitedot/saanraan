# 통화 Denomination 계약

관련 이슈: #396

이 문서는 유료 자산 차감, 정산, 환불, 쿠폰, 결제 증빙, 향후 세무 snapshot이 공유하는 통화 denomination 계약의 정본이다. 목표는 다통화나 환율 기능이 아니라 단일 사이트 denomination에서 저장 의미를 흔들리지 않게 고정하는 것이다.

## 층위

- 포인트, 적립금, 예치금의 `balance`, `amount`, `balance_after`는 통화 금액이 아니라 모듈별 asset-unit 정수다.
- 통화 의미는 자산의 `purchase_power` 비율과 도메인 가격·정액 할인·고정 수수료 같은 currency-literal에만 있다.
- `purchase_power`는 `asset_units : settlement_units + settlement_currency` 형식의 통화-bound 비율이다.
- settlement 기준 가격의 통화와 모든 참여 자산의 `purchase_power.settlement_currency`가 다르면 fail-closed한다.
- settlement/refund/payment/tax snapshot은 record-time 통화로 frozen이다. 사이트 기본 통화 변경은 과거 snapshot을 소급 재해석하지 않는다.

## Min Unit

`sr_known_currency_min_units()`와 `sr_currency_min_unit()`의 값은 decimal exponent가 아니라 정수 settlement minimum unit이다. 예를 들어 `KRW => 1`은 1원 단위 settlement를 뜻한다.

1.x의 저장 모델은 decimal sub-unit 저장을 지원하지 않는다. 현재 `USD => 1`은 센트가 아니라 whole-dollar settlement만 의미한다. 센트 단위가 필요하면 known currency 목록을 늘리는 것이 아니라 settlement amount scale, formatter, 가격 입력, snapshot, 환불 재현성까지 함께 바꾸는 별도 마일스톤으로 다룬다.

## 기본 통화 변경

사이트 기본 통화는 새 가격·정책 row와 통화가 비어 있는 fallback 입력의 기준이다. 기본 통화를 바꾸더라도 기존 가격, 접근 로그, 결제 record, 쿠폰 사용 snapshot, purchase-power snapshot은 변환하지 않는다.

통화 변경 영향 분석은 다음을 분리해 보여줘야 한다.

- live purchase-power 비율의 `settlement_currency`
- 가격, 쿠폰 정액, 고정 수수료, 세무 임계값 같은 live currency-literal
- 통화가 비어 site default로 해석되는 live 비율 또는 정책
- 과거 settlement/refund/payment/tax snapshot

nonzero balance 자체는 통화 변경 차단 사유가 아니다. 잔액은 asset-unit이기 때문이다. 위험은 구 통화에 묶인 live purchase-power 비율과 currency-literal을 새 기본 통화로 조용히 재해석하는 데 있다.

통화 없는 live purchase-power 비율은 변경 감지 구멍이므로 경고·백필·저장 거부 중 명시 정책을 가져야 한다. 새 구현은 통화 없는 live 비율을 정상 경로로 만들지 않는다.

## 표시 경계

저장 의미와 denomination source는 이 문서가 소유한다. 기호, grouping, 소수점, 한국어 `원` 같은 표시는 #374 formatter가 이 계약을 소비해 처리한다. 화면에 `원`을 하드코딩하는 지점은 표시 이관 대상이며, 저장 스케일이나 settlement 의미를 바꾸는 근거가 아니다.

## 비목표

- 한 사이트 안의 동시 다통화 balance
- 사용자별 표시 통화
- FX 환율, 환차손익, cross-denomination asset exchange
- 사이트 통화 변경 시 자동 가격 변환 또는 재동의 UI
- cent/sub-unit 저장 확대
