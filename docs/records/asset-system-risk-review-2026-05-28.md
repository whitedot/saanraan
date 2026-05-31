# 자산 시스템 운영/보안/정합성 점검 기록 - 2026-05-28

이 문서는 point/reward/deposit/coupon/asset_exchange와 콘텐츠/커뮤니티 연동에서 확인한 위험을 정리한다. 핵심 원장은 `FOR UPDATE` 잔액 잠금, 음수 잔액 차단, `balance_after` 기록, 정수 연산, dedupe key 기반 멱등성, 환전 실행의 그룹 트랜잭션을 갖추고 있다. 2026-05-31 기준 최초 과금 확인, 미완료 로그 마커, 환전 제출 토큰, 쿠폰 만료 전이, 알림 후처리, 환전 순환 가치 증가 차단, 관리자 자산 조정 상한, 환전 로그 인덱스와 환전 데드락 재시도를 보강했다.

## 높은 위험

### GET 요청 중 과금

- 위치: `modules/community/actions/view.php`, `modules/community/actions/attachment.php`, `modules/content/actions/view.php`, `modules/content/actions/download.php`
- 유료 게시글 열람, 첨부 다운로드, 유료 콘텐츠 열람/다운로드가 GET 처리 중 자산 차감 또는 쿠폰 소진을 실행한다.
- POST CSRF 토큰 검증 밖에서 상태가 바뀌므로 외부 페이지의 자동 요청, 링크 미리보기, 프리페치, 보안 스캐너가 과금을 발생시킬 수 있다.
- `once` 정책은 entitlement/session/dedupe로 반복 과금을 일부 막지만 최초 과금 자체는 GET에서 일어날 수 있다.
- `every_view`, `every_download` 같은 반복 과금 정책은 요청마다 난수 dedupe key를 만들기 때문에 프리페치/반복 호출에 더 취약하다.
- 2026-05-28 보강: 반복 과금 정책은 GET에서 차감/쿠폰 소진을 실행하지 않고, 확인 폼을 거친 POST + CSRF 요청에서만 처리한다. POST 성공 후에는 정책, 자산, 실효 금액, 복합 자산별 금액, 그룹 정책 snapshot fingerprint가 묶인 짧은 1회성 세션 확인권을 남기고 GET으로 redirect해 브라우저 새로고침/재전송으로 같은 POST가 반복 과금되지 않게 한다. 커뮤니티 첨부처럼 게시글 유료열람 확인과 첨부 다운로드 확인이 이어지는 흐름은 첨부 ID와 정책 fingerprint에 묶인 1회성 브리지로만 다음 요청을 통과시키며, 확인 화면 새로고침으로 TTL이 연장되지 않도록 최초 발급 시각을 유지한다. 브리지 소비 전에는 자산 모듈 가용성도 다시 확인한다. 그룹 정책으로 실효 차감액이 0원이 된 접근은 GET에서도 통과한다.
- 운영 메모: 반복 과금 확인권은 replay 방지를 위해 1회성으로 소비한다. 같은 redirect URL에 동시 GET이 들어오면 먼저 도착한 요청만 통과하고 나머지는 다시 확인을 요구할 수 있다. 이는 중복 GET을 무료 반복 열람/다운로드 창으로 만들지 않기 위한 의도된 보수 동작이다.
- 2026-05-31 보강: `once` 최초 과금도 반복 과금과 같은 확인 POST + CSRF 흐름을 거치도록 바꿨다. 이미 완료된 접근권은 GET에서 다시 과금하거나 확인을 요구하지 않는다.

### 멀티 자산 분할 차감 비원자성

- 위치: `modules/community/helpers/assets.php`, `modules/content/helpers/assets.php`
- 여러 자산에 나눠 차감할 때 각 자산 차감은 자산별 transaction helper를 호출한다.
- 각 helper는 외부 트랜잭션이 없으면 자체 커밋한다. 따라서 첫 자산 차감 후 다음 자산 차감이 실패하면 부분 차감이 남을 수 있다.
- 잔액 사전 검사가 있어 정상 경로에서는 드물지만, 검사와 차감 사이의 동시 소비나 중간 장애에서는 실제 손실이 가능하다.
- 2026-05-28 보강: 커뮤니티/콘텐츠 유료 접근과 다운로드의 placeholder 삽입, 모든 자산 차감, access entitlement 부여를 상위 DB 트랜잭션으로 묶었다.
- 2026-05-31 보강: point/reward/deposit transaction helper는 자체 트랜잭션을 시작한 경우 원장 커밋 뒤 알림을 만들고, 상위 트랜잭션 안에서 호출된 경우 shutdown 후처리로 알림을 지연한다. 롤백되어 원장 row가 없으면 알림은 만들어지지 않는다.

### placeholder 먼저 삽입 후 차감

- 위치: `sr_community_insert_asset_log_placeholder()`, `sr_content_insert_asset_access_placeholder()`
- dedupe placeholder가 먼저 커밋되고 원장 차감/entitlement 부여가 뒤따르는 구조다.
- placeholder 커밋 후 차감 전 하드 크래시가 나면 `transaction_id = 0` placeholder만 남을 수 있다.
- 이후 같은 once 요청에서 INSERT IGNORE가 중복으로 무시되면 차감 없이 허용되는 흐름이 생길 수 있다.
- 2026-05-28 보강: placeholder와 차감을 단일 트랜잭션에 넣고, once 정책에서 중복 placeholder가 발견되면 완료 접근권이 없는 한 실패로 처리한다.
- 2026-05-31 보강: 콘텐츠 접근/액션 로그와 커뮤니티 자산 로그에 `log_status`를 추가했다. 신규 placeholder는 `pending`으로 생성하고, 원장 transaction 연결 또는 정상 0원 처리 완료 뒤 `completed`로 전환한다. 관리자 보존 정책은 `transaction_id = 0`이 아니라 `log_status = 'pending'`과 보관 기간을 기준으로만 미완료 로그를 정리한다.

## 중간 위험

### 환전 반올림/속도 제한

- 위치: `modules/asset_exchange/helpers.php`
- `rounding_mode`가 `floor`, `round`, `ceil`을 허용한다. 양방향 정책이 모두 올림/반올림이고 수수료가 없으면 소액 반복 환전으로 가치가 늘어날 수 있다.
- 2026-05-31 보강: 환전 실행은 로그인, CSRF, 정책/금액/quote hash에 묶인 만료형 1회성 제출 토큰에 더해 `sr_rate_limits` 기반 계정 단위 단기 실행 제한을 적용한다.
- 2026-05-31 보강: 활성 양방향 정책이 모두 무수수료일 때 1~1000 단위 소액 순환을 계산해 반복 환전으로 출발 금액보다 커지는 조합은 저장을 거부한다. 운영 기본 반올림은 계속 `floor`다.
- 2026-05-31 보강: `sr_asset_exchange_correct_completed_group()` helper가 완료된 환전 묶음 ID 기준으로 출금/입금/수수료 원장을 반대 거래로 정정하고 정정 로그를 남긴다.
- 이미 방어된 부분: 환전 실행은 출금, 입금, 수수료, 로그를 같은 트랜잭션으로 처리한다.

### 쿠폰 자동 소진과 만료 상태

- 위치: `modules/coupon/helpers.php`
- `sr_coupon_redeem_for_target()`은 매칭되는 활성 쿠폰을 만료 임박순으로 자동 소진한다. 범용 쿠폰은 싼 대상에서 먼저 소진될 수 있다.
- 만료된 발급 건은 조회 조건에서 제외되지만 DB status가 `active`로 남을 수 있었다. status 기준 관리자 통계나 정리 작업에서 과대 집계될 수 있다.
- 쿠폰 소진과 소비 모듈 entitlement 부여가 별도 호출이라, 소진 후 entitlement 전 장애가 나면 정합성 틈이 있다.
- 이미 방어된 부분: 쿠폰 발급 건은 `FOR UPDATE`로 잠그고, redemption dedupe key로 중복 소진을 막는다.
- 2026-05-28 보강: 콘텐츠/커뮤니티 유료 접근에서 쿠폰 소진과 entitlement 부여를 같은 상위 트랜잭션에서 조율하도록 바꿨다.
- 2026-05-31 보강: 쿠폰 목록, 관리자 지급 목록, 쿠폰 사용 처리 진입 시 만료된 active 발급 건을 `expired`로 전이한다. 콘텐츠/커뮤니티 유료 접근은 쿠폰 소진도 확인 POST 이후에만 실행한다.

### 관리자 수동 조정 상한

- 위치: `modules/point/actions/admin-points.php`, `modules/reward/actions/admin-rewards.php`, `modules/deposit/actions/admin-deposits.php`
- 관리자 수동 조정은 로그인, 권한, CSRF, 부호/정수 검증, 감사 로그를 갖추고 있다.
- 2026-05-31 보강: 포인트/적립금/예치금 관리자 조정은 서버에서 관리자별 일일 10,000,000 절대금액 상한을 검증한다. 1,000,000 초과 조정은 처리자와 다른 편집 권한 보유 승인자의 식별자와 승인 사유가 있어야 저장되고, 10,000,000 초과 1회 조정은 저장하지 않는다.

## 중간-낮은 위험

### 알림 실행 위치

- 위치: `modules/point/helpers.php`, `modules/reward/helpers.php`, `modules/deposit/helpers.php`
- 자산 transaction helper가 원장 트랜잭션 안에서 알림 생성을 호출하던 구조는 잔액 행 잠금 보유 시간을 늘릴 수 있었다.
- 2026-05-31 보강: point/reward/deposit 알림은 원장 커밋 이후 또는 요청 종료 후처리로 지연한다.

### 환전 이력 조회 인덱스

- 위치: `sr_asset_exchange_fee_applies()`, `modules/asset_exchange/install.sql`
- 재환전 수수료 판정은 `account_id + to_module_key + status` 조건으로 로그를 조회한다.
- 2026-05-31 보강: 설치 SQL과 `2026.05.007` 업데이트에 `(account_id, to_module_key, status, created_at)` 인덱스를 추가했다.

### 데드락 가능성

- 같은 계정에서 A->B와 B->A 환전을 동시에 실행하거나 환전과 유료 열람이 같은 잔액 행을 두고 경합하면 잠금 순서 차이로 데드락이 날 수 있다.
- 이미 방어된 부분: DB 트랜잭션 실패 시 예외로 롤백된다.
- 2026-05-31 보강: 독립 환전 실행과 콘텐츠/커뮤니티의 상위 자산 처리 작업은 MySQL deadlock/lock wait timeout 계열 오류를 최대 3회 재시도한다. 상위 트랜잭션 안에서 호출될 때는 외부 작업 단위가 불명확하므로 내부 재시도를 하지 않고 예외를 전파한다.

## 우선순위

2026-05-31 보강 후 즉시 정합성 결함으로 남겨 둔 항목은 없다. 향후 운영 규모가 커지면 대액 승인 목록 화면, 승인 요청 큐, 자산별 설정형 상한처럼 편의와 감사 추적을 더 촘촘히 만드는 작업을 별도 고도화로 검토한다.
