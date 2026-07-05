# 핵심 설계 결정

이 문서는 구현 중 흔들리면 안 되는 산란의 핵심 결정을 정리합니다. 다른 문서나 구현 판단이 애매할 때는 이 문서의 결정을 우선합니다.

## 0. 핵심 원칙

산란은 기능을 많이 가진 코어보다 읽고 고칠 수 있는 코어를 우선합니다.

구현 중 판단이 갈리면 다음 원칙을 우선합니다.

```text
코어는 똑똑해지지 말 것
흐름은 파일을 열면 보일 것
자동 등록보다 명시적 include
기능보다 경계 우선
```

구체적 기준:

- 코어는 요청 흐름, 설정 조회, 보안 helper, 최소 출력 기반만 담당
- 모듈은 자기 기능과 콘텐츠 의미를 책임
- 자동 탐색, 자동 등록, 숨은 부팅 흐름은 기본 구현에서 제외
- 다국어, SEO, 캐시, 개인정보 처리는 얇은 기반만 코어에 두고 세부 판단은 모듈로 넘김
- 기능 추가보다 작고 읽히는 흐름과 명확한 모듈 경계를 우선

## 0-1. 코어는 관리 시스템 전체가 되지 않는다

산란의 코어는 실행 기반입니다. 관리자 화면과 기본 모듈을 제공하더라도 코어가 사이트 운영 기능 전체를 직접 소유하지 않습니다.

코어에 둘 수 있는 것:

- 요청 진입과 설치/업데이트 흐름
- DB 연결과 기본 설정 조회
- 모듈 등록 상태 확인과 안전한 action include
- CSRF, escape, redirect, token hash, mail 같은 공통 helper
- 다국어 UI 문자열 로드 기반
- SEO 출력 슬롯과 기본 fallback
- 감사 로그, 에러 로그, 개인정보 처리에 필요한 최소 기반

코어에 두지 않는 것:

- 게시글, 페이지, 상품, 주문, 댓글, 카테고리, 메뉴, 포인트, 쿠폰 같은 도메인 모델
- 도메인별 관리자 화면과 정책
- 모든 모듈에 강제되는 콘텐츠/SEO/다국어 테이블 구조
- 커뮤니티, 커머스, 마케팅, 분석 기능을 예상한 공유 테이블 컬럼
- 모듈별 workflow를 대신 처리하는 범용 통합 관리자

판단 기준:

- 기능이 특정 도메인 단어로 설명된다면 해당 모듈에 둡니다.
- 여러 모듈이 필요하더라도 정책 판단이 들어가면 코어가 아니라 helper 계약이나 별도 모듈을 우선합니다.
- 코어 테이블이나 `member` 테이블은 미래 확장을 예상해 넓히지 않습니다.
- 확장 정보는 모듈이 자기 테이블을 만들고 `account_id` 같은 안정적인 식별자로 연결합니다.
- `admin` 모듈은 공통 운영 화면을 제공하지만, 각 도메인의 관리 화면은 소유 모듈이 제공합니다.
- 대부분의 실용적인 운영 기능은 여러 모듈의 상태와 조치를 한 흐름에 배치해야 하는 필요가 생깁니다. 이때 코어와 `admin` 모듈은 운영 흐름을 조립할 수 있지만, 도메인 상태 판단과 저장 정책은 모듈이 명시적으로 공개한 계약이나 소유 endpoint에 둡니다. 자세한 기준은 [모듈을 가로지르는 운영 흐름 가이드](cross-module-operations-guide.md)를 따릅니다.
- `admin` 모듈의 공통 레이아웃은 활성 모듈의 `admin-menu.php`를 읽어 메뉴를 노출할 수 있지만, 해당 화면의 action/view와 정책은 소유 모듈에 둡니다. 관리자 메뉴의 분류, 모듈 정렬, 메뉴 아이콘 선언은 새 계약 파일 대신 `module.php`의 선택적 `admin` 메타데이터를 읽어 처리합니다. 프로젝트 기본 아이콘셋은 번들 폰트로 직접 제공하는 Google Material Symbols이며, 메뉴 아이콘 심볼 이름 목록과 Material 아이콘 매핑은 admin 모듈 공통 helper 계약에 둡니다. 사이트 메뉴, 로고 매니저, 배너, 팝업레이어, SEO는 `사이트` 분류로 노출하고, 콘텐츠, 커뮤니티, 쇼핑몰, 티켓팅처럼 사이트 방문자가 직접 이용하는 서비스 도메인 모듈은 `서비스` 분류로 노출합니다. 포인트, 적립금, 예치금처럼 회원 계정 없이는 성립하지 않는 모듈은 `회원` 분류로 노출합니다. 콘텐츠/커뮤니티/퀴즈/설문 사용자 화면 UI Kit 미리보기는 소유 모듈에 두되 모듈 서브메뉴에 넣지 않고, `/content/ui-kit`, `/community/ui-kit`, `/quiz/ui-kit`, `/survey/ui-kit` 사용자 화면에서 현재 공개 레이아웃 설정과 선택 theme을 적용해 제공합니다. 관리자 UI-KIT의 링크 모음은 설치된 모듈만 연결합니다. CKEditor처럼 독립 도메인이 아니라 편집기 기능을 제공하는 플러그인은 `플러그인` 분류에서 설정 접근만 제공하고, 적용 대상과 저장 정책은 콘텐츠/커뮤니티/관리자처럼 화면을 소유한 모듈이 결정합니다. 운영자가 조정하는 표시 순서, 숨김 여부, 모듈 그룹 아이콘은 `admin` 모듈의 저장 오버라이드로만 덮어씁니다.
- 파일 업로드처럼 보안 사고 지점은 공통이지만 파일의 의미, 공개 범위, 다운로드 권한, 보존 정책이 도메인마다 다른 기능은 코어 helper와 모듈 책임으로 나눕니다. 코어는 업로드 검증과 안전한 파일 이동 같은 primitive만 제공하고, 공통 파일 테이블이나 파일 관리자 화면은 필요할 때 선택 모듈이 소유합니다.

## 0-2. 코어 helper는 역할별로 분류해 관리한다

산란이 기능을 늘릴수록 코어 helper가 도메인 기능을 흡수하려는 압력이 생긴다. 이를 막기 위해 `core/helpers/*.php`는 다음 분류로 관리한다.

대외 설명 문구는 구현 판단의 근거로 쓰지 않는다. 구현 판단의 기준은 실행 기반, 보안 기준선, 모듈 경계, 공유호스팅 운영 가능성이다.

### 0-2-1. 코어 primitive

코어에 둘 수 있다. 특정 도메인 정책 없이 요청 실행, 보안, 설정, 출력, 설치/업데이트를 가능하게 하는 낮은 층의 기능이다.

현재 해당 파일:

- `core/helpers/common.php`: 문자열 축약/정리, 상대시간 라벨, datetime-local 정규화, 바이트 표기, boolean-like 값 파싱, JSON 배열 decode, 이미지 MIME 확장자/허용 여부 매핑처럼 도메인 정책이 없는 순수 유틸
- `core/helpers/runtime.php`: 요청 값, URL, HTTPS/proxy 판단, 세션, CSRF, redirect, config, DB 연결, 시간, 토큰 같은 런타임 기반
- `core/helpers/settings.php`: 사이트 설정, 모듈 활성 상태, 모듈 메타데이터, 계약 파일 로딩, 모듈 호환성 확인
- `core/helpers/output.php`: escape, 번역 로딩, 공개 레이아웃 껍데기, SEO fallback, output slot 호출 기반
- `core/helpers/sql.php`: 정적 SQL 파일 실행, schema version 기록, 업데이트 파일 버전 수집
- `core/helpers/upload.php`: 업로드 제공 여부 판정, 업로드 파일명 정규화, 확장자/MIME/크기 검증, 안전한 저장 경로 primitive
- `core/helpers/storage.php`: local/S3 저장소에 파일을 put/delete/head 하는 저장소 primitive와 key/reference 검증

이 분류의 helper도 다음 조건을 넘으면 코어에 두지 않는다.

- 특정 모듈 테이블 구조를 알아야 한다.
- 파일의 도메인 의미, 공개 범위, 다운로드 권한, 보존 정책을 판단한다.
- 특정 업무 화면이나 관리자 workflow를 전제로 한다.
- 모듈 간 정책 조정을 자동으로 수행한다.

여러 모듈이 같은 유틸을 쓰더라도 상태 라벨, 정책 이름, 자산 처리, 게시글/콘텐츠/쿠폰 같은 업무 의미가 들어가면 모듈 helper에 남긴다. 반대로 의미 없는 문자열 정리, 시간 표시 라벨, 바이트 단위 표시, boolean-like 값 파싱, 단순 JSON 배열 decode, 일반 웹 이미지 MIME 허용 판정처럼 같은 구현을 반복하던 함수는 모듈별 wrapper 이름을 유지하더라도 내부 구현은 코어 primitive를 호출하게 한다.

### 0-2-2. 운영 기준선 helper

코어에 둘 수 있지만 계속 감시한다. 설치, 배포 보호, 요청 contract, 감사 로그, 에러 로그처럼 운영 사고를 줄이는 공통 안전장치다. 도메인 정책을 갖기 시작하면 `admin` 모듈이나 별도 공식 선택 모듈로 옮긴다.

현재 해당 파일:

- `core/helpers/ops.php`: 배포 보호 확인, config 쓰기, 요청 contract, 에러 렌더링, 예외 로그, 감사 로그, 운영 marker

유지 기준:

- 요청 흐름의 호출 누락을 잡는 call-site contract여야 한다.
- 어떤 role이 어떤 업무를 할 수 있는지 같은 semantic contract는 모듈에 둔다.
- 운영 marker는 설치/업데이트/배포 상태를 기록하는 수준에 머문다.
- healthcheck, 백업, 보안 점검 UI처럼 운영 기능이 커지면 공식 선택 모듈 후보로 본다.

### 0-2-3. 공유 도메인 패턴 helper

코어에 두는 것을 기본값으로 삼지 않는다. 여러 모듈에서 반복되더라도 데이터 모델이나 업무 규칙의 모양을 강하게 유도한다면 코어 primitive가 아니라 공유 도메인 패턴이다.

현재 공유 도메인 패턴은 코어 primitive와 공식 선택 모듈을 구분한다. `asset_ledger`는 자산 원장 primitive를 소유하는 공식 선택 모듈이고, `payment_ledger`는 여러 도메인의 결제 증빙을 묶는 공식 선택 모듈이다. 본문 URL 임베드는 별도 관리 모듈 없이 `core/helpers/url-embed.php`의 좁은 helper가 맡는다.

본인확인은 `identity_verification` 선택 모듈이 provider 설정, 시도, 결과, purpose별 계정 연결을 소유한다. 생년월일 사용은 별도 opt-in 설정이며, 켜진 경우에만 회원가입 본인확인 결과의 생년월일과 성인 여부를 회원 프로필에 반영하고 잠근다. 성인확인 정책은 생년월일 사용이 켜져 있을 때만 저장할 수 있고, 콘텐츠/퀴즈/설문 같은 소비 모듈은 공개 열람 또는 참여 접근 정책을 자기 설정으로 저장한다.

회원 자산 모듈은 하나의 `sr_member_ledgers` 같은 통합 원장으로 합치지 않고 `point`, `reward`, `deposit`이 각자 balance/transaction 테이블을 소유한다. 세 모듈은 모양이 비슷하지만 운영 의미가 다르다. 포인트는 활동 보상과 차감 정책, 적립금은 구매 보상/만료, 예치금은 현금성 충전/환불/정산 같은 정책을 가질 수 있으므로 단일 테이블로 합치면 코어 또는 공유 모듈이 자산 정책을 소유하게 된다. 반복되는 원자적 잔액 갱신과 일반 거래 insert는 `asset_ledger` 공식 선택 모듈의 helper로 줄이되, 만료 잔여량처럼 모듈 소유 정책 필드가 함께 바뀌는 경우에는 해당 자산 모듈이 같은 트랜잭션 안에서 자체 insert helper를 둘 수 있다. 정책/권한/UI/보관 기준은 각 모듈에 둔다. 콘텐츠와 커뮤니티가 사용할 금액성 자산 후보는 고정 배열이 아니라 활성 자산 모듈의 `member-assets.php` 계약에서 읽고, 회원 탈퇴 시 정리 대상은 `member-withdrawal-assets.php` 계약에서 읽는다.

결제내역은 도메인 주문내역과 분리한다. `payment_ledger`는 `sr_payment_records`와 `sr_payment_record_items`에 결제 전 금액, 실제 settlement 금액, 통화, 쿠폰 사용, 자산 거래, 외부 PG 승인, 접근권 부여 같은 구성 item을 저장하지만 상품, 주문, 배송, 콘텐츠 접근 정책, 커뮤니티 열람 정책을 소유하지 않는다. 커머스 모듈이 추가되면 `sr_commerce_orders` 같은 주문/주문상품/배송/환불 정책 테이블은 커머스가 소유하고, 주문 결제 확정 트랜잭션 안에서 `payment_ledger`에 결제 record와 item만 기록한다. 콘텐츠와 커뮤니티도 같은 원칙으로 유료 열람/다운로드 접근권과 자산 로그를 자기 테이블에 남기고, 공통 결제 record는 증빙 묶음으로만 쓴다. 도메인 모듈은 `payment-ledger-targets.php` 계약으로 subject module/type 설명을 제공한다.

자산 간 환전은 `asset_exchange` 선택 모듈이 실행, 파생 정책 row, 실행 로그, 정정 흐름을 소유한다. 코어는 통합 자산 테이블을 만들지 않고, 환전 모듈은 활성 자산 모듈의 `asset-exchange.php` 계약에서 잔액 조회 함수와 원장 거래 생성 함수를 읽어 호출한다. 관리자는 `/admin/asset-exchange`에서 고정 6개 환전 방향마다 출금 기준값과 입금 기준값, 환전 조건을 직접 저장한다. `/admin/asset-exchange/settings`는 전역 환전 사용 여부와 회원 알림만 저장하며, 환전 사용 여부를 끄면 파생 정책이 사용 상태여도 회원 신청, 예상 금액 계산, 실행 helper가 모두 거부된다. 환전 실행은 하나의 DB transaction 안에서 출금 원장, 입금 원장, 선택 수수료 원장을 같은 `reference_type=asset_exchange`와 환전 묶음 ID로 연결한다. 비율, 반올림, 수수료 조건은 실행 로그에 스냅샷으로 남기고 설정 변경은 이후 요청에만 적용한다. 임의 자산 조합 정책 row는 보존하지 않으며, 업데이트와 설정 동기화에서 기존 로그의 정책 참조만 비운 뒤 제거한다. 따라서 `sr_asset_exchange_policies`는 활성 슬롯과 tombstone을 같은 pair에 함께 보관하는 active-slot unique 모델이 아니라, 고정 6개 방향만 남기는 단순 `(from_module_key, to_module_key)` unique 모델을 유지한다. 콘텐츠/커뮤니티 구매력과 settlement 차감 배분은 `member-assets.php`의 `purchase_power` 계약을 기준으로 하며, 환전 정책 row를 가격 환산에 사용하지 않는다. 다만 확인 UI가 있는 유료 열람/다운로드에서 선택 자산 조합이 정확 충당에 실패하고 활성 환전 정책으로 같은 조합 안의 잔액을 맞출 수 있으면, 사용자가 환전 후 결제를 명시 확인한 POST에서만 환전 실행 후 settlement 계획을 다시 계산한다.

`asset_ledger` 유지 조건:

- 내부 키는 `asset_ledger`, 운영자 표시명은 `잔액 처리 기반`이다.
- 관리자 사이드메뉴에는 표시하지 않지만, `/admin/modules`의 모듈 목록에는 항상 표시하고 `기반` 배지로 구분한다.
- 새 설치에서는 `point`, `reward`, `deposit`, `community` 중 하나를 선택하면 설치기가 `asset_ledger`를 필요한 기반 모듈로 자동 포함하고 사용자에게 함께 설치됨을 안내한다. 기존 설치에서 세 자산 모듈 또는 커뮤니티를 설치하거나 활성화할 때도 관리자 수명주기 처리에서 `asset_ledger`를 먼저 자동 설치/활성화한다.
- `point`, `reward`, `deposit`, `community` 중 하나라도 활성 상태이면 `asset_ledger` 비활성화는 UI와 서버 POST 모두에서 차단한다. 삭제 기능이 추가될 때도 같은 차단 기준을 적용한다.
- 자동 설치/활성화는 감사 로그의 `module.foundation.installed` 또는 `module.foundation.enabled` 이벤트로 남기고, 관리자 처리 결과에는 기반 모듈이 함께 준비되었다고 표시한다.
- `asset_ledger`는 통합 balance/transaction 테이블을 만들지 않는다. 실제 자산 테이블은 계속 각 자산 모듈이 소유한다.
- 테이블명은 호출 모듈이 명시적으로 넘기되, helper 안의 공식 table pair allowlist를 통과해야 한다. 현재 공통 helper를 직접 쓰는 pair는 `sr_deposit_balances`/`sr_deposit_transactions`다.
- 음수 허용, 거래 유형, 사유, 참조 의미, 관리자 권한, 환불/취소/만료 정책은 호출 모듈이 책임진다.
- 환전 가능 여부와 환금성 여부는 자산 모듈의 좁은 계약과 환전 모듈 정책으로 판단하고, 코어나 회원 테이블에 자산 도메인 컬럼을 추가하지 않는다.
- helper는 원자적 balance update와 transaction insert 같은 좁은 DB primitive에 머문다.
- 원장 조회 UI, 정산, 만료, 지급 정책, 통계, 외부 결제 연동은 코어에 넣지 않는다.
- `point`와 `reward`는 만료와 소비 매핑 정책 때문에 `asset_ledger`의 공통 transaction helper를 쓰지 않는다. `sr_ledger_nullable_positive_int()` 같은 작은 입력 보정만 공유할 수 있다.
- 보상 회수 실패 큐는 `asset_ledger`의 공통 운영 기반으로 둔다. `sr_asset_recovery_failures`는 정상 지급 로그가 아니라 실패한 회수 작업만 저장하며, dedupe key는 `source:{source_module}:{source_log_id}:rev:{reversal_event_key}` 형식을 사용한다. 공통 관리자 화면은 포인트/금액 미회수 관리 화면으로 제공하고, 지급 로그는 각 자산/도메인 모듈의 읽기 전용 로그 화면에 둔다.
- 회수 실패 상태는 `open`, `recovered`, `manually_resolved`, `cancelled`를 사용한다. `recovered`는 전액 회수 종결, `manually_resolved`는 운영자의 오프라인 처리 또는 회수 포기 인정, `cancelled`는 회수 대상 제외다. 수동 해소와 취소는 남은 `unrecovered_amount`를 0으로 만들지 않고 보존한다. 부분 회수 원장 거래는 `sr_asset_recovery_reversal_links`로 회수 row와 연결한다.
- 회수 실패 큐의 개인정보 export/cleanup은 `asset_ledger`가 소유한다. `operation_context_json`은 allowlist 키만 저장하고, `failure_reason`은 enum/code로 기록한다.

`payment_ledger` 유지 조건:

- 내부 키는 `payment_ledger`, 운영자 표시명은 `결제 기록 기반`이다.
- 관리자 사이드메뉴에는 표시하지 않지만, `/admin/modules`의 모듈 목록에는 항상 표시하고 `기반` 배지로 구분한다.
- 새 설치에서는 `content`, `community` 중 하나를 선택하면 설치기가 `payment_ledger`를 필요한 기반 모듈로 자동 포함하고 사용자에게 함께 설치됨을 안내한다. 기존 설치에서 이 모듈들을 설치하거나 활성화할 때도 관리자 수명주기 처리에서 `payment_ledger`를 먼저 자동 설치/활성화한다.
- `content`, `community` 중 하나라도 활성 상태이면 `payment_ledger` 비활성화는 UI와 서버 POST 모두에서 차단한다. 삭제 기능이 추가될 때도 같은 차단 기준을 적용한다.
- `payment_ledger`는 주문, 상품, 접근권 정책, 배송, 환불 가능 여부 같은 도메인 정책을 소유하지 않는다. 공통 테이블은 결제 record와 item 증빙에만 사용한다.
- 주문내역과 결제내역은 같은 테이블로 합치지 않는다. 커머스 주문은 커머스 모듈이 소유하고, 공통 결제내역은 주문 ID를 subject/reference로 참조한다.
- 결제 record의 `dedupe_key`는 불변 증빙 단위다. 같은 key의 재호출은 기존 record id만 반환하고 item을 추가하지 않으며, account/subject/amount/currency 같은 생성 증빙 값이나 비익명 record의 item 묶음이 기존 기록과 다르면 실패한다. 결제 종류, 생성 상태, item 되돌림 상태 같은 lifecycle key는 올바른 identifier/status 값일 때만 저장하고 잘못된 값을 기본값으로 흡수하지 않는다. 동시 생성 중 unique 충돌이 발생해도 기존 record를 다시 조회해 같은 값이면 흡수하고, 탈퇴/익명화 후 늦게 도착한 같은 dedupe replay는 account 연결을 복원하지 않은 채 흡수한다.
- 결제 record의 subject module/type은 활성 모듈이 제공하는 `payment-ledger-targets.php` 계약에 있어야 한다. 계약 제공 모듈은 자기 모듈 키와 같은 `subject_module`만 선언할 수 있고, 오타나 아직 계약되지 않은 결제 대상은 공통 결제 기록에 저장하지 않는다.
- 결제 record와 item의 identifier, dedupe/reference key, 통화 코드, 금액은 저장 전에 검증한다. 길이 초과 key, 3자리 ISO 형태가 아닌 통화 코드, 비정수 금액, 음수 record 금액은 잘라내거나 0으로 보정하지 않고 거부한다.
- 결제 record item 중 접근권 부여 item의 `reference_id`는 접근 대상과 접근 종류만 식별하고 raw account id를 포함하지 않는다. 탈퇴/익명화 cleanup은 record `account_id`를 끊기 전에 과거 접근권 item reference와 item snapshot 문자열의 정확한 `:account:{id}` segment도 익명 marker로 바꾸며, `:account:77`처럼 다른 계정 ID prefix를 잘못 치환하지 않는다.
- 취소/환불 실행은 도메인 모듈이 자기 정책과 원장 되돌림을 수행하고, `payment_ledger`는 record/status와 item `reversal_status`를 통해 결과 증빙을 보존한다. record가 새로 취소 상태로 전이될 때만 reversible item을 `pending`으로 표시하며, 이미 `reversed`인 item을 재호출로 `pending`에 되돌리지 않는다. 도메인 환불이 실제 원장 환불이나 접근권 회수를 끝내면 연결 item을 `reversed`로 표시하고, 해당 record의 reversible item이 모두 `reversed`가 된 경우 record 상태를 `refunded`로 닫는다. 할인 쿠폰처럼 일부 구성 item이 환불 대상이 아니면 해당 item은 열린 상태로 남고 record도 부분 환불 증빙으로 유지한다.
- `payment_ledger`가 활성화되어 있는데 helper나 테이블이 준비되지 않은 부분 설치 상태라면 유료 접근 처리 모듈은 결제 기록 없이 성공시키지 않고 실패 처리한다.
- 개인정보 export/cleanup은 `payment_ledger`가 소유한다. 사본 제공에는 결제 record와 item snapshot이 포함되고, member cleanup event context로 탈퇴/익명화가 전달되면 결제 record의 account 연결을 제거한다. cleanup 저장소 오류는 0건 처리로 숨기지 않고 member cleanup orchestrator로 전파해 전체 익명화 트랜잭션이 rollback되게 한다.

URL 임베드 helper 유지 조건:

- 임베드는 별도 관리 모듈이 아니라 `core/helpers/url-embed.php`의 URL 임베드 helper가 맡는다. 콘텐츠·커뮤니티 본문에 단독으로 붙여 넣은 YouTube, X, Instagram, 내부 콘텐츠 URL을 공개 렌더링 시점에 해석하며, 관리자 검색 삽입 화면과 검색 계약은 사용하지 않는다.
- 관리자 사이드메뉴와 별도 설정 화면을 만들지 않는다. 각 owner 모듈은 필요한 경우 자체 `embed_enabled` 설정으로 본문 URL 임베드를 켜거나 끈다.
- CKEditor는 편집기 에셋/초기화 플러그인이므로 플러그인 분류에 두고, URL 임베드 helper는 저장 HTML을 오염시키지 않는 공개 렌더링 보조로 유지한다.
- rich text HTML 정화는 HTML Purifier가 배치된 환경에서는 Purifier adapter를 먼저 사용하고, 없으면 내부 DOM sanitizer를 fallback으로 사용한다. 코어 public helper 이름은 유지해 콘텐츠, 알림, 팝업레이어 같은 호출부가 sanitizer 구현 선택을 직접 알지 않게 한다. Purifier cache는 vendor 내부가 아니라 `storage/cache/htmlpurifier`처럼 운영 쓰기 경로를 사용한다.
- 콘텐츠/커뮤니티 검색 삽입은 본문 안에 안전한 URL 또는 링크 HTML을 넣고 전용 marker를 만들지 않는다.
- 본문 URL이 저장 진실원이며, `sr_url_embed_cache`는 canonical URL hash, target tuple, public snapshot, cache status를 담는 파생 cache/index다.
- 공개 baseline으로 판정된 내부 URL 임베드는 대상 모듈이 `fragment_cache_public` 계약을 명시한 경우에만 `storage/cache/embeds` 아래 sanitized HTML fragment를 생성할 수 있다. 이 파일은 직접 공개 URL로 제공하지 않고 PHP 렌더 경로에서만 읽는다.
- fragment 캐시 대상은 익명 공개 상태, viewer-independent HTML, target cache version이 있는 내부 URL로 제한한다. 콘텐츠 유료 열람, 커뮤니티 유료/비밀/권한 게시글, 퀴즈 회원 그룹 제한, 설문 로그인/그룹 제한처럼 viewer별 계약이 필요한 대상은 fresh public fragment로 저장하지 않는다.
- 대상 모듈은 제목, 요약, 이미지, 공개 상태, 유료/권한 정책, 공개 기간이 바뀌는 저장/삭제/상태 변경 뒤 `sr_url_embed_mark_target_url_cache_stale()` 또는 이에 준하는 모듈 helper로 기존 URL 캐시를 갱신 필요 상태로 표시해야 한다.
- URL 임베딩은 전역 `url_embed_enabled`, 내부 URL, 외부 URL, scope 설정과 각 지원 모듈의 `embed_enabled` 설정이 먼저 gate한다. 꺼져 있으면 resolver와 renderer를 호출하지 않는다.
- 복사 시 본문 URL을 그대로 복사하고 새 owner 저장/렌더링 과정에서 cache를 다시 파생한다.
- 대상 모듈은 `url-embed-targets.php` 계약으로 URL allowlist, canonical URL, target id, public snapshot, target/cache 상태, renderer, 전용 `embed_stylesheet`를 제공한다. 내부 임베드 스타일은 호출처 모듈의 reset/module stylesheet를 끌어오지 않고 대상 모듈의 `assets/embed.css` 같은 전용 CSS만 로드한다. renderer는 `aside` 같은 호출처 의미 태그보다 `sr-content-embed`, `sr-community-embed`, `sr-quiz-embed`, `sr-survey-embed`, `sr-coupon-embed` 같은 임베드 전용 custom tag를 사용한다. fragment cache가 공개 HTML 조각을 저장하므로 renderer 마크업이나 sanitizer allowlist가 바뀌면 대상 계약의 `fragment_cache_schema`를 올린다.
- 공개 표시 HTML은 공통 관리 모듈 카드가 아니라 외부 provider renderer 또는 대상 모듈 renderer가 현재 viewer와 공개 정책을 기준으로 결정한다.
- URL 임베드 helper는 상품 가격/재고, 콘텐츠 유료 열람, 커뮤니티 게시글 공개/삭제/권한, 쿠폰 사용 가능성 같은 대상 모듈 정책을 소유하지 않는다.
- 개인정보가 포함될 수 있는 snapshot이나 클릭/노출 로그를 저장하는 확장을 추가하면 `privacy-export.php`, `privacy-cleanup.php`, 보존 기간 정책을 함께 설계한다.
- 토큰 기반 본문 연결 호환 레이어는 URL 임베드 범위에서 제거한다. 본문 연결은 저장된 일반 URL 또는 허용된 HTML 링크를 공개 렌더링 시점에 `url-embed-targets.php` 계약으로 해석하는 방식만 사용한다.

퇴출 또는 이동 기준:

- 세 개 미만의 모듈만 쓰거나 모듈별 정책 차이가 커지면 각 모듈 helper로 복사/분리한다.
- 테이블 구조가 한 모듈 요구에 맞춰 넓어지기 시작하면 해당 모듈로 옮긴다.
- 금액성 도메인 외 기능이 붙기 시작하면 별도 공식 선택 모듈 또는 모듈별 구현으로 분리한다.

### 0-2-4. 새 helper 추가 심사

새 `core/helpers/*.php` 또는 새 core helper 함수를 추가하기 전 다음 질문을 통과해야 한다.

```text
이 helper 없이 index.php의 요청 실행, 설치/업데이트, 보안 기준선, 설정/모듈 계약 로딩이 불가능한가?
도메인 단어 없이 설명할 수 있는가?
특정 DB 테이블 모양을 강제하지 않는가?
비활성 모듈이 있어도 의미가 유지되는가?
정책 판단이 아니라 primitive나 call-site contract인가?
```

하나라도 아니면 우선 모듈 helper, 계약 파일, 공식 선택 모듈 후보로 둔다. "여러 모듈이 쓸 것 같다"는 이유만으로 코어에 넣지 않는다.

### 0-2-5. 새 모듈 계약 파일 추가 심사

모듈 계약 파일은 코어가 특정 모듈의 의미를 직접 나열하지 않기 위한 좁은 연결면으로만 추가한다. 계약 파일은 다음 조건을 통과해야 한다.

```text
코어가 알아야 할 것은 파일명과 반환 shape뿐인가?
반환 값이 모듈의 도메인 정책을 실행하지 않고, 이미 모듈이 판단한 후보/row/handler를 드러내는가?
계약이 비활성 모듈을 자동 활성화하거나 다른 모듈 저장을 대신 수행하지 않는가?
계약 파일을 읽어도 요청 흐름, 권한 검증, 상태 변경 action이 숨겨지지 않는가?
계약 추가 후 코어에서 특정 module_key 조건문이 줄어드는가?
```

계약 파일이 특정 업무 상태 전이, 권한 정책, 저장 transaction을 대신 소유한다면 계약이 아니라 소유 모듈 action/helper에 둔다. 새 계약 파일을 추가할 때는 `docs/module-guide.md`에 사용 shape와 호출 책임을 설명하고, 실제 번들 모듈에는 파일을 명시적으로 둔다. known contract 목록을 늘리는 작업은 특정 모듈 정책을 코어로 우회시키는 수단이 아니어야 한다.

## 0-3. 공개 소스와 알려진 구조를 전제로 방어한다

산란은 오픈소스 프로젝트입니다. 운영 사이트가 산란을 사용한다는 사실, 기본 디렉터리 구조, 기본 테이블명, 요청 흐름, 모듈 파일 약속이 공격자에게 알려져 있다고 가정합니다.

기본 원칙:

- 보안은 숨겨진 파일명, 추측하기 어려운 관리자 URL, 비공개 테이블 prefix에 의존하지 않습니다.
- `sr_` 테이블 prefix는 프로젝트 네임스페이스이며 보안 장치가 아닙니다.
- 소스 구조가 공개되어도 SQL injection, XSS, CSRF, 인증 우회, 권한 우회가 성립하지 않아야 합니다.
- 설치/업데이트/관리자 요청은 위치가 알려져도 실행 조건과 권한 검증으로 막아야 합니다.
- 모듈 action 파일은 직접 경로가 알려져도 활성 상태, method/path 허용 목록, 인증, 권한, CSRF 검증을 통과해야 상태 변경을 수행합니다.
- 버전 정보나 배포 구조를 숨기는 것보다 알려진 취약점을 빠르게 패치하고 릴리스 기준을 명확히 하는 것을 우선합니다.

구현 중 판단 기준:

- "공격자가 이 파일명과 요청 path를 알고 있어도 안전한가"를 기준으로 검토합니다.
- 관리자 버튼을 숨기거나 메뉴에 노출하지 않는 것은 보조 UI일 뿐 서버 권한 검증을 대체하지 않습니다.
- 설치 완료 후 설치 흐름은 경로를 모르는 상태에 의존하지 않고 설치 상태와 설정 파일 상태로 실행을 거부해야 합니다.
- 배포 보호 규칙은 내부 파일 원문 노출을 막기 위한 필수 운영 조건이지만, 애플리케이션 보안 검증을 대신하지 않습니다.

## 1. 요청 분기는 명시적 include를 기본으로 한다

산란은 숨은 dispatcher, service provider, annotation, reflection, 자동 요청 분기를 사용하지 않습니다.

기본 흐름:

```text
index.php
-> core/request-bootstrap.php의 요청 준비 함수를 명시적으로 호출
-> path와 method 확인
-> 사이트 설정과 활성 모듈 확인
-> 허용된 모듈/action 파일인지 검증
-> 명시적 include
```

`core/request-bootstrap.php`는 파일을 읽는 것만으로 요청을 처리하지 않는다. CLI 내장 서버 정적 파일 처리, 오류 처리기, 설정/DB/session/locale 준비, 공유호스팅용 보조 runner처럼 요청 실행 전에 필요한 준비 단계를 작은 함수로 제공하고, `index.php`가 필요한 순서대로 호출한다.

요청 분기는 다음 방식을 피합니다.

- `sr_route()` 같은 전역 path 등록 API 중심 구조
- 모듈이 부팅 중 path를 몰래 등록하는 구조
- 클래스/attribute/reflection 자동 스캔
- Composer 자동 발견
- Laravel Service Provider 유사 구조

모듈이 요청 path 정보를 제공해야 한다면 `routes.php`가 실행 중 등록하는 방식이 아니라, `paths.php`처럼 단순 배열을 반환하는 방식을 사용합니다.

예시:

```php
<?php

return [
    'GET /login' => 'actions/login.php',
    'POST /login' => 'actions/login.php',
    'POST /logout' => 'actions/logout.php',
];
```

코어는 이 배열을 읽은 뒤, 모듈 활성 상태와 action 파일 경로를 검증하고 include합니다.

## 1-1. 운영·보안 기준선은 호출 누락을 잡되 의미 판단은 모듈에 둔다

산란은 비즈니스 도메인을 소유하지 않습니다. 대신 설치, 회원 인증, 관리자 권한, 감사 로그, 개인정보 사본 제공, 업데이트처럼 운영 중 사고가 잦은 기준선은 helper, 정적 검사, dispatch contract 세 층으로 받칩니다.

기본 구분:

```text
call-site contract: 필요한 helper가 요청 흐름에서 호출되었는지 확인
semantic contract: 어떤 대상에 어떤 작업을 허용할지 판단
```

코어는 call-site contract를 강화합니다. `POST` action의 `sr_require_csrf()`, 관리자 action의 `sr_member_require_login()`과 `sr_admin_require_permission()` 또는 `sr_admin_require_owner()` 호출 누락은 정적 검사와 dispatch contract에서 잡습니다. 하지만 어떤 메뉴 경로와 작업 권한을 요구할지, 소유자만 수정 가능한 대상인지, 도메인 상태 전이가 올바른지는 모듈 action과 helper가 명시적으로 책임집니다.

action 파일은 응답 종료를 `sr_redirect()`, `sr_render_error()`, `sr_finish_response()`로 통과시킵니다. JSON action 응답은 `sr_json_response()`로 content type, UTF-8 대체 인코딩, 추가 응답 헤더 allowlist, 응답 종료를 함께 처리합니다. 직접 `exit`/`die` 호출이나 `header('Location: ...')` 직접 호출은 요청 contract를 우회하므로 사용하지 않습니다.

HTML view 또는 inline JavaScript handler에 PHP 값을 주입할 때는 직접 `json_encode()`를 쓰지 않고 `sr_js_json_encode()`를 사용합니다. 이 helper는 JSON HEX flags와 invalid UTF-8 대체를 함께 적용해 script parser 경계가 데이터 값으로 깨지지 않게 합니다.

## 1-2. DB 접근은 PDO prepared statement를 기본으로 한다

산란은 절차형 PHP와 직접 include 구조를 유지하므로 DB 접근 규칙을 코드 작성자가 바로 확인할 수 있어야 합니다.

기본 원칙:

- DB 연결은 코어가 만든 `PDO` 인스턴스를 action/helper 함수에 명시적으로 전달합니다.
- 사용자 입력, 요청 값, 설정 값, 토큰, IP, 정렬 조건에서 파생된 값은 SQL 문자열에 직접 이어 붙이지 않습니다.
- 동적 값은 `PDO::prepare()`와 named placeholder로 바인딩합니다.
- `PDO::query()`는 외부 값이 전혀 섞이지 않는 고정 SQL에만 사용합니다.
- 설치/업데이트 SQL 파일은 코어 SQL 실행 helper로만 실행하고, 일반 기능 코드에서 `PDO::exec()`를 직접 사용하지 않습니다.
- placeholder로 바인딩할 수 없는 테이블명, 컬럼명, 정렬 방향은 허용 목록에서 선택한 값만 사용합니다.

세부 규칙은 [DB 접근 정책](database-access-policy.md)을 따릅니다.

## 2. 개인정보 요청 이력은 계정 삭제 이후에도 보존 가능해야 한다

GDPR 대응 구조에서는 개인정보 요청 이력이 계정 물리 삭제로 깨지면 안 됩니다.

구현 방향:

- 계정은 기본적으로 물리 삭제보다 비활성화/익명화를 우선
- 개인정보 요청 테이블의 `account_id`는 nullable 가능성을 고려
- 요청 당시 식별에 필요한 최소 snapshot 또는 hash 저장 검토
- 개인정보 요청 이력은 법적/운영 정책에 맞춰 별도 보관 기간 적용

## 3. 토큰은 원문 저장을 기본 금지한다

세션, 장기 로그인, 비밀번호 재설정, 이메일 인증 같은 토큰은 원문을 DB에 저장하지 않습니다.

구현 방향:

- DB에는 token hash 저장
- 사용자에게 전달되는 토큰 원문은 생성 시점에만 사용
- 컬럼명은 `*_token`보다 `*_token_hash`를 우선
- 로그에는 토큰 원문과 hash 모두 남기지 않음

## 4. SEO 값의 판단은 모듈 책임이다

코어는 SEO 출력 기반만 제공합니다.

코어 담당:

- `<head>` 출력 위치
- 기본 title/description fallback
- canonical helper
- robots 기본값
- Open Graph 출력 슬롯

모듈 담당:

- 콘텐츠별 title
- 콘텐츠별 description
- 콘텐츠별 canonical
- Open Graph 값
- 구조화 데이터
- sitemap 후보 URL
- 다국어 SEO 관계

코어가 콘텐츠의 의미를 추론해서 SEO 값을 자동 생성하지 않습니다.

사이트 공통 메타 기본값은 사이트 설정이 소유한다. `site.title_suffix`, `site.meta_description`, `site.og_image`는 공개 레이아웃 출력 직전에 공통 적용하며, 화면별 제목/설명/OG 이미지가 있으면 해당 화면 값이 우선한다. 기본 OG 이미지는 운영자가 URL을 직접 입력하거나 업로드할 수 있고, 업로드 후 공개 경로를 `site.og_image` 설정값에 저장한다. 콘텐츠별 SEO 값은 콘텐츠/커뮤니티 같은 콘텐츠 소유 모듈이 자기 테이블에서 판단한다. `seo` 모듈은 sitemap/robots 같은 검색 운영 기능을 맡고, 사이트 회원 전용 모드가 켜지면 robots 응답은 전체 차단, sitemap 응답은 빈 urlset을 우선한다.

커뮤니티 모듈은 게시판 SEO/OG 기본값을 `sr_community_board_settings`의 `seo_title`, `seo_description`, `og_image_url` 설정 key로 소유한다. 제목과 설명은 검색 메타와 OG 메타에 함께 쓰며, 기존 `og_title`, `og_description` 설정 key는 과거 데이터 호환을 위해 읽기 fallback으로만 유지한다. 게시글별 값은 `sr_community_posts`의 SEO/OG 컬럼으로 소유한다. 게시판 그룹은 SEO/OG 기본값을 제공하지 않는다. 공개 접근 가능한 게시판과 게시글만 `index, follow` 및 sitemap 후보가 되며, 검색 결과, 잘못된 카테고리, 권한 제한, 유료 열람 확인 전 화면은 noindex 기준을 사용한다. 게시글 OG 이미지는 지정된 활성 이미지 첨부, 첫 활성 이미지 첨부, 게시판 기본 OG 이미지, 사이트 기본 OG 이미지 순서로 이어지고, 작성자나 `/admin/community/posts` edit/delete 권한자는 지정 OG 이미지를 제거할 수 있다.

로고 용도도 코어 도메인으로 승격하지 않습니다. `logo_manager` 모듈은 기본 공개/관리자 용도를 제공하고, 다른 모듈은 기존 `logo-positions.php` 계약으로 자기 화면의 로고 용도 후보만 제공한다. DB 컬럼 `position_key`와 계약 파일명은 호환을 위해 유지하지만 관리자 UI에서는 위치가 아니라 용도로 표현한다. 공개 레이아웃별 상단/하단 사용처는 `logo_manager`의 `sr_logo_manager_logo_usage_targets`가 소유하며, 개별 레이아웃 provider 지정이 `전체` 지정과 기존 상단 fallback보다 우선한다. 실제 출력은 화면 소유 레이아웃이나 모듈 view가 `sr_logo_manager_render_logo()` 또는 앱아이콘 심볼 후보 helper를 명시적으로 호출해 request flow가 파일에서 보이도록 유지한다.

## 5. GDPR 기능은 최소 기반과 확장 기능을 나눈다

GDPR 대응을 모두 코어에 넣지 않습니다. 하지만 회원가입, 동의 기록, 삭제/익명화 같은 최소 기반은 기본 구조에서 빠지면 안 됩니다.

기본 방향:

- `member` 모듈: 동의 기록, 회원 탈퇴, 세션 폐기, 계정 비활성화/익명화, 회원 목록 관리와 관리자 직접 추가. 탈퇴 화면은 남은 회원 자산이 있을 때만 자산 처리 안내를 보여주고, 포인트/적립금 잔액은 소멸 원장을 남기며 예치금 잔액은 환불계좌를 입력받아 출금 원장으로 접수한 뒤 익명화한다.
- 코어: 개인정보 처리에 필요한 공통 설정과 보안 helper
- `privacy` 모듈: 관리자 전용 개인정보 사본 제공 보조, 운영자 문의로 접수한 처리 기록 관리, 모듈별 `privacy-export.php` 수집
- `admin` 모듈: 개인정보 화면을 소유하지 않고 관리자 레이아웃/권한/감사 로그 기반을 제공

자동화보다 기록과 관리자 검토 흐름을 우선합니다.

## 6. 관리자 화면은 제공하되 코어에 직접 넣지 않는다

산란은 운영 가능한 웹 솔루션 코어이므로 관리자 화면을 제공해야 합니다.

다만 관리자 화면 전체를 코어에 직접 넣지 않습니다. 코어는 관리자 기능이 동작할 수 있는 최소 기반만 제공합니다.

기본 방향:

- 코어: 관리자 진입 보호, 인증 상태 확인, 권한 helper, CSRF, 로그 helper
- `admin` 모듈: 관리자 레이아웃, 설정 화면, 모듈 관리, 권한/감사 로그/업데이트 관리
- `member` 모듈: 회원 목록, 회원 설정, 회원 그룹 관리
- `privacy` 모듈: 개인정보 대응 기록과 사본 제공 보조
- 각 모듈: 자기 도메인의 관리 화면과 action 파일 제공

감사 로그 테이블과 기록 helper는 코어 운영 기반이다. `admin` 모듈은 감사 로그의 저장 구조를 소유하지 않고, 관리자 조회 화면과 필터/보관 실행 같은 운영 UI를 맡는다. 일반 `sr_audit_log()`는 운영 로그 실패가 본 업무를 막지 않도록 실패를 삼키지만, 신고 대상 조치처럼 감사 로그가 수용 기준 또는 원자적 경계의 일부인 흐름은 `sr_audit_log_required()`를 사용해 실패 시 같은 트랜잭션을 롤백한다.

관리자 화면도 일반 화면과 같은 원칙을 따릅니다.

- 명시적 include
- 숏태그 금지
- 출력 escape
- 모든 상태 변경 요청 CSRF 검증
- 서버 권한 검증 필수
- 관리자 화면은 기본 `noindex`

## 7. 비밀번호 재설정은 member 모듈 책임이다

비밀번호 재설정은 인증 도메인에 속하므로 코어 기능으로 넣지 않습니다.

기본 방향:

- `member` 모듈: 비밀번호 재설정 요청, 토큰 생성, 메일 발송 요청, 토큰 검증, 비밀번호 변경
- 코어: token hash 원칙, random token 생성 helper, mail helper가 있다면 최소 인터페이스만 제공
- 관리자 화면: 필요 시 회원 상태 확인과 강제 초기화 정책만 제공

비밀번호 재설정 토큰도 원문을 저장하지 않고 hash만 저장합니다.

## 8. member, admin, privacy는 기본 설치 필수 모듈이다

`member`, `admin`, `privacy`는 코어에 내장하지 않습니다. 하지만 산란의 기본 배포에서는 필수 기본 모듈로 포함하고, 기본 설치 과정에서 함께 설치합니다.

이유:

- 최초 관리자 계정 생성을 위해 계정/비밀번호/인증 기반이 필요함
- 관리자 화면은 계정 기반 권한 확인이 필요함
- 개인정보 사본 제공과 탈퇴/익명화 대응은 여러 모듈이 연결되는 운영 기준선임
- 코어가 자체 계정 체계를 갖기 시작하면 `member` 모듈 경계가 깨짐

최소 설치 단위:

```text
core + member + admin + privacy
```

역할:

- 코어: 설치 흐름, 설정, DB 연결, 모듈 등록, 보안 helper
- `member` 모듈: 계정 생성, 비밀번호 hash, 로그인 기반
- `admin` 모듈: 관리자 화면, 관리자 권한, 관리 기능
- `privacy` 모듈: 관리자 전용 개인정보 대응 기록과 개인정보 사본 제공 보조

`seo`, `site_menu`, `logo_manager`, `banner`, `popup_layer`, `point`, `deposit`, `reward`, `notification`, `content`, `community`, `quiz`, `survey` 모듈은 배포본에 포함되어 있어도 인증과 관리자 진입에 필요한 필수 모듈은 아닙니다. 설치 화면에서 선택한 모듈만 설치 SQL을 실행하고 `sr_modules`에 등록합니다. 선택하지 않은 코드 모듈은 `/admin/modules`에서 나중에 설치할 수 있습니다. 기본 홈페이지는 별도 도메인 모듈이 아니라 public layout/theme이 제공하고, 운영자는 관리자 설정의 `화면` 섹션에서 초기화면 경로만 선택합니다. 기본 홈페이지 본문은 사이트 운영자가 public layout/theme의 홈 템플릿을 직접 작성해 구성합니다. 콘텐츠, 커뮤니티, 퀴즈·테스트, 설문·여론조사처럼 `module.php`의 `service_domain.main_page`를 선언한 선택 모듈은 설치 화면의 모듈 카드에서 `초기화면으로 설정` 체크를 제공하고, 선택값은 `site.home_path`에 저장됩니다. 신규 설치에서 `site_menu`를 선택하면 초기 상단 메뉴는 홈 다음에 콘텐츠, 커뮤니티, 퀴즈·테스트, 설문·여론조사 순서로 생성합니다. 관리자 사이트 설정의 초기화면 선택지는 기본 홈페이지 뒤에 관리자 사이드바 순서대로 콘텐츠, 커뮤니티, 퀴즈·테스트, 설문·여론조사를 표시합니다. 사이트 공통 레이아웃 선택지도 기본 공통 레이아웃을 먼저 표시한 뒤 레이아웃 제공 모듈을 관리자 사이드바 순서대로 정렬합니다. `homepage-candidates.php` 계약은 저장된 경로의 사용 가능 여부를 판단하는 호환 계약으로 유지하되, 사이트 설정의 선택 목록을 확장하지 않습니다.

사이트맵과 robots.txt 같은 SEO 운영 기능은 코어가 아니라 `seo` 모듈 책임으로 두고, 사이트 공통 제목 접미사/기본 설명/기본 OG 이미지는 사이트 설정 책임으로 둡니다. 용도별 로고 배치는 `logo_manager` 모듈 책임으로 둡니다. 공개/관리자 layout은 활성 모듈의 helper가 반환한 현재 로고만 읽고, 로고 배치 테이블이나 예약 정책을 코어 테이블로 끌어올리지 않습니다. 팝업 정책과 대상 규칙은 `popup_layer` 모듈 책임으로 둡니다. 포인트, 적립금, 예치금 같은 회원 연계 도메인도 코어나 `member` 테이블을 넓히지 않고 각 모듈이 `account_id` 기반 확장 테이블로 소유합니다.

설치 순서:

```text
1. 코어 테이블 생성
2. member 모듈 설치
3. admin 모듈 설치
4. privacy 모듈 설치
5. 배포본에 포함되어 있고 선택한 모듈 설치
6. 기본 사이트 설정 저장
7. 필수 모듈과 선택한 모듈 등록 및 활성화
8. 스키마 버전 기록
9. member 계정으로 최초 관리자 계정 생성
10. admin 권한 부여
11. 설치 완료
```

## 9. 모듈 계약 버전은 모듈 호환성 기준이다

`core/version.php`의 `SR_MODULE_CONTRACT_VERSION`은 `modules/{module_key}` 아래에 현재 배치된 모듈 폴더가 코어와 맞춰야 하는 모듈 계약 버전입니다. 이 값은 별도 모듈 저장소 운영을 전제로 한 원격 배포 버전이 아니라, Git checkout, 릴리스 zip, FTP/SFTP 업로드처럼 파일 배치 방식이 달라도 코어가 읽을 수 있는 모듈 구조인지 확인하는 호환성 기준입니다.

이 값은 코어 버전과 별개로 관리합니다. 코어 자체 버그 수정이나 문서 변경은 코어 버전을 바꿀 수 있지만, 모듈의 구조나 동작 계약이 그대로라면 모듈 계약 버전은 유지합니다.

모듈의 `saanraan.min_version`은 현재 `SR_CORE_VERSION`이 충족해야 하는 최소 코어 버전입니다. 형식만 맞으면 되는 표시값이 아니라, 설치와 활성화 전에 실제 비교하는 요구사항입니다.

모듈 계약 버전을 올리는 경우:

- `module.php` 메타데이터 구조나 필수 키가 호환되지 않게 바뀜
- 모듈 필수 파일, 선택 파일, 계약 파일 이름이나 위치가 바뀜
- `install.sql`, `updates/*.sql`, `paths.php`, `admin-menu.php`, `output-slots.php` 같은 모듈 진입 계약이 호환되지 않게 바뀜
- 릴리스 파일 구성, 모듈 zip 업로드, 관리자 설치 흐름에서 모듈이 맞춰야 하는 검증 규칙이 바뀜
- 보안 검증, 권한 확인, 경로 제한처럼 모듈 코드가 따라야 하는 필수 정책이 호환되지 않게 바뀜

모듈 계약 버전을 올리지 않는 경우:

- 문서만 수정함
- 기존 모듈을 깨지 않는 선택 helper를 추가함
- 코어 내부 구현을 정리하지만 모듈이 사용하는 파일, 메타데이터, helper 계약은 그대로임
- 릴리스 설명이나 README 설명만 바뀌고 모듈 구조 요구사항은 그대로임

모듈 계약 버전을 올릴 때는 코어 제작자가 다음을 함께 갱신합니다.

```text
1. core/version.php의 SR_MODULE_CONTRACT_VERSION
2. docs/module-guide.md의 모듈 메타데이터 예시와 설명
3. 배포 대상 모듈 폴더의 module.php saanraan.module_contract
```

계약 버전이 올라가면 배포 대상 모듈 폴더의 `module.php`에 있는 `saanraan.module_contract`를 새 계약에 맞게 갱신합니다.

계약 버전이나 `saanraan.min_version` 요구사항이 맞지 않는 모듈은 설치 또는 활성화할 수 없습니다. 이미 DB에 `enabled`로 등록된 모듈이라도 코드의 메타데이터/계약이 현재 코어와 맞지 않으면 `paths.php`, `admin-menu.php`, `output-slots.php` 같은 계약 파일 로딩 대상에서 제외하며, 관리자 모듈 화면에 호환성 오류를 표시합니다.

계약 파일은 모든 모듈에 전부 필요한 필수 파일이 아닙니다. 모듈이 URL을 노출하면 `paths.php`, 관리자 메뉴를 노출하면 `admin-menu.php`, 출력 슬롯을 렌더링하면 `output-slots.php`, 관리자 대시보드 요약을 제공하면 `dashboard.php`처럼 기능별로 필요합니다. `paths.php`는 기본적으로 정확한 method/path를 선언하고, `/content/*`처럼 끝에 붙은 좁은 wildcard prefix만 허용합니다. wildcard는 content 모듈처럼 자기 prefix 아래의 slug 화면을 소유하는 경우에 쓰며, `/content/` bare prefix 자체가 아니라 그 아래 하위 path만 매칭합니다. 루트 catch-all permalink나 숨은 dispatcher 용도로 쓰지 않습니다. 계약 파일이 실제로 존재하면 `module.php`의 `contracts.provides`에 반드시 선언해야 하며, `contracts.provides`에 선언한 파일은 실제 모듈 폴더에 있어야 합니다. 다른 모듈이 `requires.contracts`로 특정 계약 파일을 요구할 때도 대상 모듈의 파일 존재와 `contracts.provides` 선언을 함께 확인합니다.

선택 모듈의 런타임 연동도 직접 helper 파일 경로에만 기대지 않고 좁은 계약 파일을 우선합니다. 예를 들어 포인트, 적립금, 예치금, 쿠폰, 콘텐츠, 커뮤니티, 퀴즈, 설문은 알림 저장 정책을 소유하지 않고, 알림 모듈이 활성화되어 있고 `notification-events.php` 계약을 제공할 때만 계정 알림 생성 함수를 호출합니다. 반복 가능한 도메인 이벤트는 `sr_notification_event_templates`의 DB 템플릿을 우선 사용하며, 알림 템플릿, 채널, delivery queue는 계속 `notification` 모듈 책임입니다. 관리자 운영 알림은 회원 사이트 알림과 분리해 `admin-notification-events.php` 계약으로만 생성하며, 저장소와 권한 재검증은 알림 모듈이 소유합니다.

## 10. 로그인 식별자는 원문보다 hash 조회를 우선한다

회원 계정은 최소 정보를 저장합니다. 로그인 식별자는 원문 컬럼을 기준으로 조회하지 않고, 정규화한 값의 hash를 기준으로 조회합니다.

기본 방향:

- `member` 모듈의 로그인 정책은 `이메일 + 로그인 아이디`로 고정한다
- 이메일 조회는 항상 `email_hash`를 사용하고, 로그인 아이디 조회와 중복 검사는 `login_id_hash`를 우선 사용한다
- `account_identifier_hash`는 기존 설치본 호환 fallback으로 유지
- 별도 로그인 아이디 원문은 저장 대상에서 제외
- 이메일 원문은 메일 발송과 사용자 안내 목적에 한해 저장 가능
- hash는 설정 파일의 `app_key`를 사용하는 HMAC 방식 우선

회원 기본 테이블에는 `site_id`를 넣지 않습니다. 단일 사이트 운영에서는 모든 회원이 같은 사이트 설정을 사용하므로 `site_id`가 실질적인 의미를 갖기 어렵고, 로그인 조회와 모듈 확장 쿼리를 불필요하게 복잡하게 만듭니다. 멀티사이트를 실제 기능으로 제공한다면 그때 회원/관리자/모듈 데이터에 `site_id`를 추가하는 스키마 업데이트를 설계합니다.

## 11. 현재 구현은 단일 사이트 기준이다

산란은 현재 단일 사이트 운영을 기준으로 합니다. 멀티사이트, 사이트별 모듈 활성화, 사이트별 설정 분리, 사이트별 locale 관리는 현재 구현 범위에 넣지 않습니다.

코어 테이블은 다음 기준을 유지합니다.

```text
sr_site_settings
sr_modules
sr_module_settings
sr_schema_versions
```

현재 구현에서 제외합니다.

```text
sr_site_locales
멀티사이트용 연결 테이블
site_id가 붙은 회원/관리자/감사/개인정보 테이블
```

사이트 이름, base URL, timezone, default locale, 운영 상태, 회원 전용 사이트 여부는 별도 `sr_sites` 테이블이 아니라 `sr_site_settings`의 필수 키로 저장합니다. 단일 사이트에서 항상 1건만 존재하는 사이트 레코드는 설명 비용이 크므로 두지 않습니다. 회원 전용 사이트 모드는 사이트 운영 접근 정책이며, 회원 여부 판정과 로그인 화면은 member 모듈 helper와 route를 사용합니다.

모듈 활성화 상태는 `sr_modules.status`로 관리합니다. 단일 사이트 기준에서는 사이트별 모듈 연결 테이블을 두지 않습니다.

## 12. 관리자 권한은 소유자와 회원별 메뉴 권한으로 관리한다

현재 관리자 권한은 전역 소유자인 `owner`와 회원별 메뉴 권한으로 나눈다. `admin`, `manager` 같은 중간 role은 사용하지 않는다. 소유자는 모든 관리자 메뉴와 고위험 운영 작업을 수행할 수 있고, 일반 회원은 `sr_admin_account_permissions`에 저장된 메뉴 경로와 작업 권한(`view`, `edit`, `delete`) 조합으로 접근을 판단한다.

현재 테이블:

```text
sr_admin_account_roles
sr_admin_account_permissions
```

`sr_admin_account_roles`는 `owner`만 저장한다. 관리자 권한 화면은 회원 검색 결과 안에서 소유자 여부와 메뉴별 조회/수정/삭제 권한을 편집한다. 사이드바 메뉴 노출은 `view` 권한 기준이며, 저장·상태 변경과 새로 만들기/수정 폼 진입은 `edit`, 명시적 삭제/숨김 처리는 `delete` 권한을 요구한다. 관리자 action은 로그인과 관리자 권한 검사를 마치기 전에는 관리자 내부 경로로 리다이렉트하지 않는다. 관리자 메뉴 표시 순서와 숨김 설정, 모듈 설치·상태 변경·소스 반영을 다루는 모듈 관리 화면은 전체 관리자 경험과 실행 코드를 바꾸는 전역 운영 기능이므로 회원별 권한으로 위임하지 않고 소유자만 관리한다.

## 13. 현재 구현 범위와 제외 범위

현재 구현에는 다음 운영 기능이 포함되었습니다.

```text
공개 회원가입
회원 프로필
DB 세션
비밀번호 재설정
이메일 인증
회원 동의 기록
회원 탈퇴/익명화
감사 로그
개인정보 대응 기록과 사본 제공 보조
회원 목록 관리
관리자 메뉴 표시 순서/숨김 설정
업데이트 실행 UI
커뮤니티 모듈
사이트 메뉴 관리
배너 관리
알림 관리
SEO 관리자 설정
팝업레이어 관리
포인트 모듈 원장 관리
예치금 모듈 원장 관리
적립금 모듈 원장 관리
관리자 모듈 설치 화면
모듈 확장 지점 계약
```

다음 기능은 현재 범위에서 제외합니다.

```text
장기 로그인
설정/모듈 파일 캐시
번역 병합 캐시
```

설정 조회 helper의 요청 단위 메모리 캐시는 현재 구현에 포함됩니다. 파일 캐시는 `storage/cache`의 웹 직접 접근 차단 기준을 운영 환경별로 확인한 뒤 선택 적용합니다. 구체적인 허용/금지 기준은 [성능과 캐시 기준](performance-policy.md)을 따릅니다.

## 14. 모듈 간 영향은 계약 파일로 연결한다

산란은 숨은 event bus, 자동 boot hook, 서비스 프로바이더로 모듈 간 영향을 연결하지 않습니다.

모듈 간 연결이 필요하면 다음 순서를 따릅니다.

```text
1. 모듈은 extension-points.php로 외부 확장이 붙을 수 있는 위치를 선언한다.
2. 확장 모듈은 관리자 설정 시점에 활성 모듈의 extension-points.php를 읽는다.
3. 확장 모듈은 자기 정책에 맞는 지점만 선택 가능하게 한다.
4. 실제 화면 처리 위치에서는 소유 모듈이 공통 출력 슬롯 helper를 명시적으로 호출한다.
```

예:

```text
popup_layer 모듈 -> 관리자 설정 시점에 활성 모듈의 extension-points.php를 읽어 서비스/노출위치 목록 구성
banner 모듈 -> 관리자 배너 폼에서 레이아웃 전역 위치 또는 활성 모듈의 extension-points.php를 서비스/상세 노출 위치로 고르고 coupon-targets.php 검색 계약으로 특정 subject 후보를 조회
content 모듈 -> content.view의 before_content/after_content slot에서 sr_render_output_slot() 호출
member 모듈 -> 로그인/회원가입 화면에서 sr_render_output_slot()를 명시 호출
```

사용자 요청에서는 `extension-points.php`를 읽지 않습니다. 사용자 요청은 화면에서 전달한 `module_key`, `point_key`, `slot_key`, `subject_id`로 저장된 확장 모듈 규칙만 조회합니다.

코어는 계약 파일 위치를 안전하게 찾는 helper와 출력 슬롯 호출 helper까지만 제공합니다. 계약 값의 의미, 검증, 정책은 소비 모듈이 책임집니다.

선택 UI 깊이는 기본적으로 다음 4단계를 최대치로 봅니다.

```text
module -> point -> slot -> subject
```

팝업레이어는 선언된 public `content` slot 중 화면별 대표 slot을 읽어 `module -> point -> representative slot -> subject` 범위에서 대상을 저장합니다. 관리자 UI는 배너처럼 세부 삽입 위치를 노출하지 않고 `module`을 서비스로, `point`를 노출위치로 보여줍니다. 팝업레이어는 fixed overlay로 렌더링되므로 `보조 메뉴 아래`, `본문 위`, `댓글 아래` 같은 slot 위치가 사용자에게 보이는 팝업 위치를 의미하지 않습니다. 저장 식별자는 기존 `module_key + point_key + slot_key` 조합을 유지하고, 화면 소유 모듈은 실제 출력 위치에서 `slot_key`를 포함해 `sr_render_output_slot()`을 명시 호출합니다. 매칭 방식 `선택`은 콘텐츠, 커뮤니티 게시판/게시글, 퀴즈, 설문처럼 검색 계약이 있는 노출위치에서만 대상 검색으로 지정할 수 있습니다.

콘텐츠 모듈은 `content.view` point를 제공하고, 콘텐츠 관리 화면에서 공용 배너/팝업레이어를 직접 선택하는 좁은 편의 경로도 둡니다. 직접 선택은 공용 항목만 허용하며, 상태, 대표/OG 이미지, 콘텐츠 레이아웃, 표시, 자산 정책은 현재 콘텐츠에 저장합니다. 대표/OG 이미지는 콘텐츠 모듈이 `sr_content_items.cover_image_url`로 소유하고, 파일 업로드 이미지는 콘텐츠 모듈 저장소와 `/content/cover-image` 프록시로 공개합니다. 값이 비어 있으면 공유 metadata는 사이트 기본 OG 이미지 fallback을 사용합니다. 콘텐츠 편집 화면에서 운영자가 명시적으로 선택한 `그룹`/`전체` 적용은 현재 편집값을 다른 콘텐츠에 한 번 복사할 수 있지만, 콘텐츠 그룹은 개별 콘텐츠 설정의 기본값이나 상속값을 제공하지 않습니다. 콘텐츠 그룹은 목록 페이지와 메뉴 후보를 위한 운영 묶음이고, 콘텐츠 시리즈는 회차 표시와 이전/다음 이동을 위한 읽기 흐름입니다. 한 콘텐츠는 콘텐츠 그룹에 속하면서 동시에 하나의 시리즈 회차로 연결될 수 있으며 두 정렬 기준은 서로 영향을 주지 않습니다. 세부 노출 규칙이 필요하면 배너/팝업레이어 모듈의 target 규칙을 사용합니다. 사이트 설정의 초기화면 후보는 콘텐츠 개별/그룹이 아니라 콘텐츠 서비스 메인으로 제한하며, 본문/파일/유료 열람/SEO 정책은 계속 콘텐츠 모듈이 소유합니다.

콘텐츠 유료 열람, 다운로드 과금, 완료 버튼 자산 처리 정책은 콘텐츠 모듈이 소유합니다. 완료 버튼 자산 처리는 공개 콘텐츠에서 회원이 버튼을 눌렀을 때만 실행되며, 단순 콘텐츠 조회는 포함하지 않습니다. 콘텐츠 모듈은 포인트, 적립금, 예치금 모듈을 필수 의존으로 만들지 않고 활성 모듈의 잔액/원장 helper를 좁게 호출합니다. 관리자 자산 선택 UI에는 설치되어 있고 활성화된 자산 모듈만 표시합니다. 여러 자산을 함께 고르는 설정은 선택 항목의 금액 합계를 기준 통화 가격으로 보고, 차감 방향에서는 각 자산의 `purchase_power`에 따라 정확히 충당 가능한 범위만 배분합니다. 완료 버튼 지급 방향은 한 항목만 허용합니다. 회원 그룹별 적용은 선택한 자산과 맞는 정책 세트만 사용할 수 있어야 합니다. 기존 단일 금액 설정은 호환용 합계로 유지합니다. 열람/다운로드 차감 기록은 `sr_content_asset_access_logs`, 완료 버튼 지급/차감 기록은 `sr_content_asset_action_logs`, 무료/유료 파일 다운로드 성공 이력은 `sr_content_file_download_logs`에 콘텐츠 모듈 책임으로 남깁니다. 차감 로그의 `amount`는 실제 자산 단위, `settlement_amount`/`settlement_currency`는 기준가격 충당 단위, `purchase_power_snapshot_json`은 실행 당시 구매력/통화/정책 snapshot입니다. 로그 row에는 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`을 함께 저장해 0원/legacy 분류, snapshot 구조, 계산 정책 변경을 분리합니다. `log_status = 'pending'`인 오래된 placeholder는 권리·정산 증빙 row가 아니므로 관리자 보관 정책의 미완료 로그 정리 대상이고, `completed` 원장성 row는 account 연결과 실행 snapshot을 보존합니다. 유료 다운로드 수동 환불은 연결된 차감/접근 로그가 있는 다운로드 성공 이력 행을 중복 방지 기준으로 삼아 원거래를 직접 수정하지 않고 자산별 `refund` 원장 거래를 추가하며, 최초 1회 또는 최종 금액 0 접근권은 함께 회수합니다. 최초 1회 기존 접근권 사용처럼 이번 요청의 연결 로그가 없는 반복 다운로드 이력은 환불/회수 대상이 아닙니다. `account_id` 기반 기록은 개인정보 사본 제공에 포함하며, 탈퇴/익명화 시 파일 다운로드 이력의 회원 연결을 제거합니다. 최초 1회 열람/다운로드 접근권은 거래 원장이 아니라 `sr_content_access_entitlements`에 별도 기록하고, 탈퇴/익명화 시 이 접근권의 `account_id`와 원천 참조를 끊어 개인정보 보유기간과 서비스 접근 상태를 분리합니다.

커뮤니티 게시글/댓글 적립, 글쓰기/댓글 차감, 게시글 유료 열람, 첨부 다운로드 차감 정책은 커뮤니티 모듈이 소유합니다. 커뮤니티 모듈은 포인트, 적립금, 예치금 모듈을 필수 의존으로 만들지 않고 활성 자산 모듈의 잔액 조회와 원장 생성 helper만 호출합니다. 관리자 자산 선택 UI에는 설치되어 있고 활성화된 자산 모듈만 표시합니다. 글쓰기/댓글 차감, 유료 열람, 첨부 다운로드 차감은 여러 자산을 함께 고를 수 있고, 선택 항목 금액 합계를 기준 통화 가격으로 보아 각 자산의 `purchase_power`로 정확히 충당 가능한 범위만 배분합니다. 회원 그룹별 적용은 선택한 자산과 맞는 정책 세트만 사용할 수 있어야 합니다. 기존 단일 금액 설정은 호환용 합계로 유지합니다. 게시판 편집 화면에서 운영자가 명시적으로 선택한 `그룹`/`전체` 적용은 현재 편집값을 다른 게시판에 한 번 복사할 수 있지만, 게시판 그룹은 개별 게시판 설정의 기본값이나 상속값을 제공하지 않습니다. 커뮤니티 자산 처리 로그는 `sr_community_asset_logs`에 남기고, 로그의 `amount`는 실제 자산 단위, `settlement_amount`/`settlement_currency`는 기준가격 충당 단위, `purchase_power_snapshot_json`은 실행 당시 구매력/통화/정책 snapshot으로 남깁니다. 로그 row에는 `settlement_kind`, `snapshot_schema_version`, `rounding_policy_version`을 함께 저장해 0원/legacy 분류, snapshot 구조, 계산 정책 변경을 분리합니다. `log_status = 'pending'`인 오래된 placeholder는 권리·정산 증빙 row가 아니므로 관리자 보관 정책의 미완료 로그 정리 대상이고, `completed` 원장성 row는 account 연결과 실행 snapshot을 보존합니다. 자산 원장 참조는 `community.post`, `community.comment`, `community.attachment` 같은 커뮤니티 도메인 참조로 제한합니다. 게시글 유료 열람과 첨부 다운로드의 최초 1회 접근권은 `sr_community_access_entitlements`에 별도로 기록하고 탈퇴/익명화 시 회원 연결과 원천 참조를 제거합니다.

커뮤니티 게시판별 개인정보 수집 및 이용동의는 회원가입 동의가 아니라 커뮤니티 제출 행위의 증적이므로 커뮤니티 모듈이 소유합니다. 설정은 `sr_community_board_settings`의 `privacy_consent_*` key로 저장하고, 게시글 작성/수정, 댓글 작성, 첨부 업로드 적용 여부를 분리합니다. 첨부 업로드 적용 대상에는 일반 이미지/파일 첨부와 CKEditor 본문 이미지 임시 업로드가 포함됩니다. 제출 성공 시 `sr_community_submission_consents`에 동의 제목, 본문, 버전 snapshot과 IP/UA 해시를 저장해 이후 문구 변경과 제출 당시 기준을 분리합니다. 개인정보 export에는 회원 귀속 동의 증적과 IP/UA 해시를 포함하고, 탈퇴/익명화 cleanup에서는 계정 연결과 IP/UA 해시를 제거합니다.

비회원 게시글/댓글 작성은 표면별 운영 옵션으로만 열고, 작성자 표시명, 검증 token/password hash, IP/UA hash, 동의 snapshot, 보관 기간 cleanup을 한 묶음으로 설계합니다. 연락처처럼 운영자가 추가로 받고 싶은 값은 기본 작성자 모델이나 member/core 테이블이 아니라 게시판별 추가 입력 항목으로 분리합니다. 게시판별 추가 입력 항목은 커뮤니티 모듈 소유 확장 정의/값 테이블(`sr_community_board_field_definitions`, `sr_community_post_field_values`, 필요 시 `sr_community_comment_field_values`)을 우선하고, 세부 기준과 후속 범위는 GitHub 이슈에서 추적합니다.

콘텐츠/커뮤니티가 자산별 고정 amount 차감에서 기준가격을 여러 금액성 자산의 구매력으로 나누어 충당하는 settlement 기반 복합 차감으로 확장되어도 코어는 가격 정책을 소유하지 않습니다. 소비 모듈은 `settlement`를 실제 외부 결제가 아니라 "기준가격을 통화 최소단위로 얼마나 충당했는가"라는 내부 기록 단위로 정의해야 합니다. 멱등 key는 회원, 소비 모듈, `reference_type`, `reference_id`, 기준금액, 기준 통화, 클라이언트 요청 토큰처럼 재시도 사이에 변하지 않는 입력으로만 만들고, 자산별 차감량, 잔액 snapshot, settlement 배분 결과, 확인 화면 fingerprint는 key 재계산 입력으로 쓰지 않습니다. 클라이언트 요청 토큰은 HTTP attempt마다 새로 만들지 않고 구매 의도(intent), 즉 확인 화면 렌더 시점에 1회 생성해 확정 POST 재시도 전체에서 동일하게 운반합니다. 실행 트랜잭션에 들어가면 원장 row를 잠그기 전에 안정 입력 기반 dedupe key를 가진 claim row를 먼저 insert해야 하며, 이 key에는 DB unique 제약을 둡니다. 동시 중복 요청은 원장 lock이 아니라 이 unique claim 충돌에서 `processing` 또는 저장된 `completed` 결과로 흡수하고, lock 획득 뒤에도 claim row 상태를 다시 확인합니다. 성공 결과만 claim row와 함께 커밋해 sticky 저장하고, 재검증 거부나 실행 실패는 같은 트랜잭션 rollback으로 claim row도 사라지게 둡니다. 따라서 거부/실패 재시도는 저장된 거부 결과를 반환하지 않고 현재 상태로 부작용 없이 재평가합니다. 성공 claim row의 TTL은 확인 token의 staleness window보다 길게 유지하며, window를 막 지난 late duplicate도 새 실행으로 보지 않고 저장된 성공 결과와 표시용 snapshot을 반환합니다.

settlement 기반 복합 차감은 확인 시점 계획을 실행 시점에 무조건 honor하지 않습니다. 실행 트랜잭션은 참여 자산 row를 결정적 `deduction_order`와 `asset_module` 사전순 tiebreak 순서대로 잠근 뒤 확인 시점 plan의 자산별 차감량, 구매력 snapshot, 통화 min-unit, 정책 버전을 재검증합니다. 무효화 사유는 잔액 부족/동시 차감처럼 plan 수량을 실행할 수 없는 경우와, 확인-실행 사이 구매력 snapshot, 통화 min-unit, rounding/carry `rounding_policy_version`이 달라진 경우를 분리해 기록합니다. 어느 사유든 재계획 없이 거부해 재확인을 요구합니다. 확인 token의 staleness window는 짧은 세션 확인권으로 제한하며, 구매력 snapshot을 honor하는 별도 정책을 두려면 이 결정 로그를 먼저 갱신해야 합니다. 기준금액이 0이면 원장 차감 없이 접근권이나 완료 로그를 `settlement_amount=0`으로 남기며, 같은 안정 입력 기반 dedupe key로 중복 접근권 생성을 막습니다.

구매력 계약은 `purchase_power => ['asset_units' => 양의 정수, 'settlement_units' => 양의 정수, 'settlement_currency' => 통화 코드]` 형태로 snapshot에 저장합니다. 내부 계산은 `settlement_units / asset_units`의 정확한 rational로 유지하고, 스칼라 `asset_units_per_settlement_unit`만으로 방향을 주석에 맡기는 형식은 사용하지 않습니다. 통화 최소단위 미만의 rational 값은 자산 row 하나에서 올림하지 않고 다음 allocation으로 carry할 수 있으며, 예를 들어 각각 0.5 KRW 가치인 두 자산 1단위는 함께 1 KRW를 정확히 충당할 수 있습니다. `asset_units`와 `settlement_units`는 양의 정수여야 하고, `settlement_currency`는 core/settings의 known currency min-unit registry에 존재해야 합니다. 이 검증은 차감 실행 시점에 처음 발견하지 않도록 자산 설정 저장 또는 관리자 config 로드 시점에 설정 오류로 노출합니다. 런타임 불변식은 사이트 기본 통화가 아니라 `price.currency == 각 참여 자산의 purchase_power.settlement_currency`이며, 해당 통화는 core/settings가 소유한 known currency min-unit registry에 있어야 합니다. `site.default_currency`는 새 가격 생성의 기본값일 뿐 기존 가격 row의 실행 가능 여부를 뒤집는 기준이 아닙니다. 구매력 통화가 사이트 또는 소비 모듈이 지원하는 가격 통화와 맞지 않으면 차감 실행 시점이 아니라 자산 설정/관리자 config 로드 시 설정 오류로 보여줘야 합니다. 운영자가 통화 min-unit 또는 rounding/carry `rounding_policy_version`을 바꾸면 기존 확인 화면에서 진행 중인 in-flight 요청은 fail-closed로 재확인이 발생할 수 있음을 통화/정책 변경 워크플로에 안내합니다.

통화 min-unit registry는 자산 모듈이 아니라 core/settings가 소유하며, settlement log snapshot에는 실행 당시 `currency_min_unit`, 반올림/carry 규칙 `rounding_policy_version`, 구매력 snapshot을 함께 저장합니다. `snapshot_schema_version`은 snapshot 구조 버전, `rounding_policy_version`은 금액 계산/반올림/잔여 처리 버전, `settlement_kind`는 `paid`, `free`, `paid_settled_zero`, `preview_test_zero`, `legacy_unknown` 중 하나로 시작합니다. `free`는 무료 접근뿐 아니라 지급/적립처럼 기준가격 settlement가 발생하지 않는 non-use row를 포함하고, 자산 증감량은 `direction`과 `asset_amount`로 별도 해석합니다. rational carry가 발생한 allocation snapshot은 `settlement_numerator`, `settlement_denominator`, `cumulative_settlement_numerator`, `fractional_carry_numerator`, `fractional_carry_denominator`를 남겨 row별 정수 `settlement_amount`만으로 보이지 않는 누적 충당 근거를 보존합니다. 마지막 자산의 잔여 settlement 흡수는 정확 충당이 가능한 범위까지만 허용하고, 정확 충당이 불가능한 잔여 금액은 잔액 부족/결제 불가로 거부합니다. 1 자산 단위의 settlement 가치가 통화 최소단위보다 커서 정확한 기준금액을 만들 수 없는 경우 1단위 미만 ceil overpay는 허용하지 않습니다. 예를 들어 1P = 10 KRW이고 기준가격이 1,005 KRW라면 1,000 KRW는 부족하고 1,010 KRW는 초과이므로 포인트만으로 충당할 수 없습니다. 반대로 10P = 1 KRW처럼 `settlement_units <= asset_units`인 자산은 여러 allocation의 rational carry로 정확 충당될 수 있습니다. 자산별 `deduction_order`가 같으면 `asset_module` 사전순으로 정렬해 같은 입력이 항상 같은 snapshot을 만들도록 합니다. 다중 자산 row lock도 같은 순서로 잡아 동시 복합 차감 간 deadlock 가능성을 낮춥니다. 확인 UI가 있는 유료 열람/다운로드는 정확 충당 실패 시 활성 환전 정책으로 선택 자산 조합 안의 잔액을 맞출 수 있는 경우에만 환전 후 결제 확인을 다시 요구하고, 확인된 POST에서 환전과 차감을 같은 transaction에서 처리합니다.

마일스톤 28의 통화 확장 범위는 `docs/records/milestone-28-currency-settlement-policy-2026-06-11.md`를 따른다. `site.default_currency`는 설치 시 선택하고 설치 후 잠그며, 신규 가격/정책 row의 기본값일 뿐 기존 가격·거래·구매력 snapshot 변환 스위치가 아닙니다. 추가 통화, 환율 환산 표시, DB 기반 구매력 변경, 가격/정책 통화 일괄 변환은 별도 운영 기능으로 열기 전까지 실제 차감 기준을 바꾸지 않습니다. 환율은 초기 범위에서 통계/표시 보조값이고, 환율 미설정/만료/조회 실패는 실제 구매 차감을 실패시키지 않습니다. 환산 금액을 저장하거나 노출하면 환율 값, 기준 통화, 대상 통화, 기준 시각, 출처, `exchange_rate_policy_version`, 환산 rounding 기준을 함께 남기며, 외부 환율 API secret은 기존 관리자 secret 설정/마스킹 primitive와 운영 로그 sanitize 기준을 사용합니다. 구매력 변경과 가격/정책 통화 일괄 변환 batch apply는 같은 자산/통화/도메인 scope에서 동시에 실행하지 않고, dry-run 시점의 purchase power version, 가격 row version, currency registry, `rounding_policy_version`을 apply 직전에 재검증합니다. 환불·취소·정정은 원 도메인 모듈이 소유하고, reversal snapshot 후보의 정규 필드는 `original_settlement_log_id`, `reversal_amount`, `reversal_currency`, `reversal_asset_amount`, `reversal_reason`, `rounding_policy_version`, `snapshot_schema_version`, `created_at`입니다. 통계/export/cache key는 `settlement_currency`, `snapshot_schema_version`, `rounding_policy_version`, `settlement_kind`, reversal 포함 여부를 구분하며, `legacy 1:1 assumed` 또는 `legacy_unknown` 콘텐츠/커뮤니티 차감 로그는 조회/export 기준으로 보존하지 않고 업데이트에서 삭제합니다.

settlement 기반 복합 차감에 참여하는 `member-assets.php` helper는 호출자가 시작한 같은 PDO transaction에 동참해야 하며 내부 commit이나 별도 connection을 쓰면 안 됩니다. 이 속성은 확인 사항이 아니라 계약 요건과 테스트 대상입니다. 비준수 자산 모듈은 복합 차감 후보에서 제외하고, 소비 모듈은 단일 자산 차감 또는 설정 오류 안내로 fallback해야 합니다. 문구 존재를 보는 정적 체크는 계약 조항 삭제를 막는 가드일 뿐 transaction 동참, carry, overpay, lock 순서의 런타임 준수를 증명하지 못하므로, 실제 구현 시점에는 fixture 기반 단위/통합 테스트와 필요한 HTTP smoke로 행위를 검증해야 합니다. InnoDB에서는 미커밋 unique claim row에 대한 중복 insert가 선행 트랜잭션의 commit/rollback까지 블록될 수 있으므로, 구현 fixture는 commit 후 duplicate-key, rollback 후 insert 성공, lock wait timeout 시 `processing` 응답을 모두 확인해야 합니다. legacy `legacy 1:1 assumed` settlement snapshot row와 `legacy_unknown` 차감 로그는 보정 기준으로 남기지 않고 삭제한다. 향후 USD-cent 같은 sub-unit 통화성 자산을 지원하려면 asset amount 저장 단위를 정수로 유지할지 별도 최소단위 정수로 바꿀지 먼저 별도 결정으로 남깁니다.

게시판별 일반 회원 관리권한은 커뮤니티 모듈의 `sr_community_board_managers`가 소유한다. 권한 key는 `view_manage`, `delete_post`, `remove_post_og_image`만 허용하고, `delete_post`는 해당 게시판 게시글 삭제 판정에만 합산한다. 게시판 관리권한은 게시글 본문 수정 권한으로 자동 확대하지 않으며, 전역 관리자 권한과 작성자 본인 권한도 별도 helper에서 합산해 판단한다.

쿠폰은 포인트/적립금/예치금처럼 금액 잔액을 차감하는 자산이 아니라 특정 대상에 사용할 수 있는 회원 혜택 자산입니다. `coupon` 모듈이 쿠폰 종류, 회원별 지급, 사용 로그, 수동 환불 이력, 탈퇴 시 상태 전환을 소유하고, 콘텐츠와 커뮤니티는 쿠폰 테이블을 직접 알지 않고 좁은 helper로 열람권 사용 가능 여부와 사용 처리를 요청합니다. 1차 사용 모델 분류는 `sr_coupon_definitions.coupon_type`이며 현재 저장 가능한 값은 `access`, `fixed_discount`, `percent_discount`입니다. `access`는 단발 접근권 사용을 기록하고 잔액을 보존하지 않으므로 부분 사용 가능한 쿠폰 잔액 원장이나 `stored_value` backfill을 만들지 않습니다. `fixed_discount`와 `percent_discount`는 쿠폰 정의와 지급 상태를 저장하되, 관리자 정의 단계의 정액 할인 금액은 현재 원화 기준 단일 값으로 저장하고 다중 통화별 금액, 환율 snapshot, 주문/배송/부분 취소 같은 소비 도메인 정책은 해당 도메인의 별도 계약으로 연결해야 합니다. 콘텐츠와 커뮤니티 유료 열람/다운로드는 `access`, `fixed_discount`, `percent_discount`를 모두 쿠폰 사용 후보로 평가하고, 할인 쿠폰이 일부만 덮은 남은 금액은 같은 작업 트랜잭션에서 금액성 자산으로 차감합니다. 다만 할인 쿠폰 사용과 남은 자산 차감을 하나의 도메인 취소로 되돌리는 계약은 아직 없으므로 `fixed_discount`와 `percent_discount`는 환급 가능 정책으로 저장할 수 없습니다. 쿠폰 정의 상태 `disabled`는 신규 지급과 기존 지급분 사용을 모두 막지만 지급/사용 이력 row를 자동 변경하지 않습니다. 쿠폰 환경설정은 지급, 사용, 사용 환불, 발급 환불, 지급 상태 변경, 사용 중지 회수 안내 알림의 사용 여부와 회원 대상 채널을 케이스별로 보관합니다. 운영자가 사용 중지로 전환하면 해당 케이스가 켜져 있고 알림 모듈이 활성화되어 있을 때 아직 한 번도 사용하지 않은 활성 지급건 회원에게 설정된 채널로 회수 안내 알림을 보냅니다. 쿠폰 사용 중복 방지는 `sr_coupon_redemptions.dedupe_key`에 둡니다. 수동 환불은 소비 모듈 접근권 회수가 성공할 때만 쿠폰 사용 상태와 사용 횟수를 되돌리고, 회수가 실패하면 같은 트랜잭션 rollback으로 사용 로그와 지급 건 상태를 그대로 둡니다.

#390 기준 쿠폰 사용기간 정책(`none`, `fixed_range`, `fixed_expiry`, `relative_days`)은 `coupon` 모듈의 정의/발급본 계약이며 core 자산 모델로 승격하지 않습니다. 미래 시작 지급건은 보유 권리에는 포함하지만 실제 사용 가능 후보와 사용 SQL에서는 제외합니다.

쿠폰 사용처 후보는 쿠폰 모듈이 고정 목록으로 소유하지 않고, 실제 소비 모듈이 `coupon-targets.php` 계약으로 제공합니다. 쿠폰 모듈은 활성 모듈의 계약만 읽어 관리자 선택지와 저장 검증에 사용하며, 해당 모듈이 비활성화되면 새 쿠폰 종류 생성 후보에서도 빠집니다. 사용처 검색과 쿠폰 환불 시 소비 모듈 접근권 회수도 redemption의 `target_type`이 가리키는 소비 모듈 계약 함수로 위임해, 쿠폰 모듈이 콘텐츠/커뮤니티 같은 소비 도메인 테이블을 직접 조회하거나 회수 함수를 하드코딩하지 않도록 합니다. 특정 target에 연결한 환급 가능 쿠폰은 저장 시점에 `access` 혜택 모델과 해당 target 계약의 `revoke_access` capability를 요구합니다. 같은 `dedupe_key`가 다른 소비 모듈에 남아 있더라도 현재 환불하는 redemption의 target 계약 밖 접근권은 건드리지 않습니다. 환급 가능 사용 내역에 해당 target의 `revoke_access_function`이 없거나 회수 대상 접근권이 0건이면 접근권 회수 가능성을 증명할 수 없으므로 환불 상태 전이를 rollback하고 운영자가 target 계약 또는 접근권 상태를 복구한 뒤 다시 처리해야 합니다.

`coupon-targets.php`의 `target_type`은 쿠폰, 배너, 팝업레이어가 함께 쓰는 전역 식별자이므로 모듈 간 중복 선언을 허용하지 않습니다. 런타임 소비자는 이미 등록된 `target_type`을 덮어쓰지 않고 첫 유효 계약을 유지합니다.

읽기 참조 계약은 공유 대상을 소유한 모듈이 소비 모듈의 정책 테이블을 직접 수정하지 않고 영향 범위를 확인하기 위한 역방향 조회 계약입니다. 대상별 계약 파일은 `coupon-references.php`, `banner-references.php`, `popup-layer-references.php`, `member-group-references.php`, `site-setting-references.php`이며, 공통 helper는 활성 모듈의 계약만 읽어 row를 정규화하고 destructive/admin-sensitive POST에서 계약 오류를 차단 사유로 돌려줍니다. 소유 모듈은 참조 대상 자동 치환, 소비 정책 삭제, 비활성 모듈 잔여 데이터 스캔을 하지 않습니다.

배포판은 모듈 소유권과 경계를 유지하지만, 실제 운영 사이트는 관리자 업무 흐름에 맞춰 배포판 파일을 직접 수정하거나 별도 모듈/계약 파일로 화면을 조합할 수 있습니다. 문서는 이런 변경을 금지 목록으로 다루기보다 업데이트 때 발생할 수 있는 충돌과 병합 비용을 설명합니다. 장기 운영에서 병합 비용을 줄이고 싶다면 화면 소유 모듈에는 활성 모듈의 계약을 읽는 작은 지점만 두고, 실제 도메인 요약과 쿼리는 제공 모듈의 계약 파일에 둡니다. 예를 들어 회원 목록은 `account_id[]`를 제공하고 커뮤니티/콘텐츠 같은 활성 모듈의 회원 요약 계약을 배치 조회로 읽을 수 있지만, 회원 모듈이 서비스 모듈 테이블 구조를 직접 소유하지는 않습니다.

회원 공개 닉네임은 member 모듈이 소유합니다. `sr_member_nicknames`가 표시용 닉네임과 lowercase lookup 값을 저장하고, member 설정의 `nickname_enabled`가 가입, 계정 수정, 관리자 저장, 공개 이름 표시, 멘션 lookup 기준을 결정합니다. 닉네임 사용이 켜져 있으면 닉네임 입력은 필수입니다. community/content 같은 서비스 모듈은 닉네임 테이블을 직접 조회하지 않고 `sr_member_public_name*`와 공개 이름/멘션 helper를 사용합니다. 댓글 멘션 자동완성은 로그인 회원에게만 공개 이름 후보와 public account hash prefix를 반환하고, `@공개이름#prefix`는 현재 공개 이름과 prefix가 함께 단일 활성 회원에 일치할 때만 확정 멘션으로 처리합니다. `@공개이름`만으로 여러 활성 회원이 일치하면 모호한 멘션으로 보고 알림을 만들지 않습니다. 기존 커뮤니티 닉네임 데이터와 설정은 community 업데이트 경로에서 member 소유 테이블/설정으로 이관합니다.

커뮤니티 게시글/댓글과 콘텐츠 댓글은 작성자 연결을 `author_account_id`로 유지하되, 작성 당시 공개 이름은 `author_public_name_snapshot`에 함께 저장합니다. 새 행의 snapshot은 작성 시점의 member 공개 이름 정책에 따라 닉네임 또는 이름으로 채우며, 화면 표시는 탈퇴/익명화 계정이면 탈퇴 회원 라벨을 우선하고 그 외에는 snapshot을 우선 사용합니다. snapshot은 개인정보로 취급해 privacy export에 포함하고 탈퇴/익명화 cleanup에서 비웁니다.

삭제된 글과 댓글은 별도 정책이 없는 한 즉시 물리 삭제하지 않고 운영 보존 상태로 둡니다. 커뮤니티 게시글/댓글, 콘텐츠 댓글, 퀴즈/설문 댓글처럼 `status = deleted` 또는 `deleted_at`으로 삭제를 표현하는 대상은 공개 목록과 공개 상세에서 제외하되, 관리자 목록에서는 상태 필터와 배지로 삭제 상태를 확인할 수 있어야 합니다. 단, 커뮤니티 게시글/댓글, 콘텐츠, 퀴즈, 설문처럼 모듈이 삭제 시 원문 제거 정책을 채택한 경우 삭제 상태 행에는 최소 식별자와 redacted marker만 남기고 공개·숨김 상태로 원문 복구하지 않습니다. 물리 삭제나 상위 도메인 삭제에 따른 하위 데이터 정리는 해당 모듈이 감사 로그, 신고/알림/자산 로그, 개인정보 export/cleanup, 첨부/본문 이미지 정리 실패 재시도, 댓글 자식 관계와 tombstone 표시 기준을 함께 정의한 경우에만 허용합니다. 코어는 삭제 보존 기간이나 휴지통 정책을 소유하지 않고, 여러 모듈에 같은 요구가 반복될 때도 먼저 좁은 helper 또는 계약으로 조정합니다.

숨김은 삭제와 다르게 복구 가능한 임시 비공개 상태로 둡니다. 커뮤니티 관리자 게시글/댓글 숨김은 7일, 15일, 30일, 90일, 영구 기간과 사유, 운영 메모를 저장할 수 있으며, 권리 요청 대응의 기본 운영값은 30일 숨김입니다. 기간 만료 후 자동 복구나 자동 삭제는 별도 정책 없이는 실행하지 않고, 운영자가 상태 필터와 숨김 메타데이터를 보고 후속 처리합니다.

회원가입 화면에 서비스 도메인별 추가 입력이 필요하면 회원 모듈이 해당 도메인을 직접 알지 않습니다. 소비 모듈은 `member-registration.php` 계약으로 입력 필드, 검증 함수, 저장 함수, 예외 메시지 매핑을 제공하고, 회원 모듈은 활성 모듈의 계약만 읽어 가입 트랜잭션 안에서 실행합니다. 확장 입력은 `registration_extensions[...]` POST namespace 아래에서만 받으며, 회원가입 기본 필드명이나 다른 모듈 확장 필드명과 충돌하는 key는 렌더링하지 않습니다. 닉네임처럼 여러 서비스 모듈이 공유하는 공개 회원 식별자는 이 계약으로 각 서비스가 소유하지 않고 member 공개 이름 계약으로 둡니다.

textarea 강화 에디터 후보도 core 고정 목록으로 늘리지 않는다. core는 저장 형식 기반의 기본 입력 모드인 `textarea`, `HTML`, `Markdown`만 알고, CKEditor 같은 플러그인은 `editor-options.php` 계약으로 key, 표시명, 에셋 렌더링 함수를 제공한다. 콘텐츠/커뮤니티/관리자 설정 화면은 core editor helper를 통해 기본 입력 모드와 활성 플러그인의 계약을 선택지와 렌더링에 사용한다.

포인트/적립금/예치금 원장 거래, 쿠폰·이용권 지급/사용/상태 변경/사용 환불/발급 환불, 쿠폰 정의 사용 중지로 인한 미사용 지급건 회수 안내, 콘텐츠/커뮤니티 댓글 작성자 알림, 콘텐츠/커뮤니티/퀴즈/설문 댓글 멘션 알림은 알림 모듈이 활성화되어 있으면 `sr_notification_event_templates`의 DB 템플릿을 통해 회원 대상 알림을 만듭니다. 이벤트 알림은 `sr_notifications`에 `source_module_key`, `event_key`, `metadata_json`을 함께 저장하고, 화면/발송 제목은 저장된 제목 문자열보다 현재 이벤트 템플릿과 메타데이터를 우선해 생성합니다. `title` 컬럼은 수동 알림과 기존 알림 호환 fallback으로 유지합니다. 포인트/적립금/예치금/쿠폰 모듈은 자기 환경설정에서 케이스별 회원 알림 사용 여부와 채널을 저장하고, 비활성 케이스는 원래 자산 처리를 실패시키지 않은 채 알림만 만들지 않습니다. 환전 환경설정 화면은 자산 모듈이 소유한 환전 출금/입금/수수료 알림 케이스를 함께 편집하고 각 자산 모듈 설정에 저장합니다. 포인트 회원 알림 케이스의 기본값은 사용 안 함이고, 적립금/예치금/쿠폰 회원 알림 케이스는 기존 동작 보존을 위해 기본값을 사용으로 둡니다. 알림 모듈이 비활성화되어 있거나 설치되어 있지 않으면 no-op으로 처리하고, 자산 처리나 댓글 저장 자체를 실패시키지 않습니다. 외부 채널은 템플릿 또는 소비 모듈 설정에 명시된 경우에만 delivery queue를 만들며, 회원 외부 푸시는 알림 모듈 provider와 회원별 수신처가 준비된 경우에만 발송 후보가 됩니다. 커뮤니티 신고, 콘텐츠 등록자 신청, 개인정보 처리 요청, 이메일 발송 실패 표시, 저장소 정리 재시도 실패처럼 운영자가 조치해야 하는 이벤트는 별도 `sr_admin_notifications` 흐름을 사용하고 공개 회원 알림 목록에 섞지 않습니다.

관리자 수동 자산 조정은 포인트/적립금/예치금 모두 회원 그룹별 차등 정책을 두지 않고, 관리자 권한 확인과 원장/감사 로그 기록을 기준선으로 삼습니다. 관리자가 입력한 금액은 거래 유형별 부호 검증과 잔액 검증을 거친 뒤 그대로 원장에 저장하며, 회원 그룹에 따른 자동 증감이나 면제는 적용하지 않습니다. 콘텐츠와 커뮤니티처럼 도메인 정책이 필요한 자산 처리는 각 도메인 모듈의 회원 그룹별 자산 정책으로 분리해 다룹니다.

포인트/적립금/예치금 원장의 `reference_type`과 `reference_id`는 환불, 회수, 도메인 모듈의 자동 자산 처리처럼 시스템이 정확한 원거래를 연결해야 하는 흐름에 사용합니다. 관리자 잔액 조정 모달에서는 실제 원본 레코드를 검증하지 않는 단순 기록용 연결 유형/ID 입력을 노출하지 않고, 운영자가 입력한 사유를 원장과 감사 로그의 설명 근거로 사용합니다. 감사 로그 metadata의 `approval_note`에는 잔액 조정 사유를 항상 저장합니다. 1,000,000 초과 대액 조정은 별도 승인자와 승인 사유를 다시 받지 않고 현재 조정 대상 회원과 입력 사유를 감사 로그 metadata에 함께 남기며, 1회/일일 상한은 계속 서버에서 강제합니다.

퀴즈와 설문의 대표/OG 이미지는 각 모듈의 `cover_image_url` 문자열 필드로 보관합니다. 운영자는 안전한 내부 경로 또는 HTTP(S) URL을 직접 입력할 수 있고, JPG/PNG/WebP 파일을 업로드하면 각 모듈 소유 저장소 경로(`quiz/cover-images`, `survey/cover-images`)와 프록시 URL을 사용합니다. 공개 목록, 상세 상단, 공유 이미지 metadata는 이 값을 읽으며, 값이 비어 있으면 공유 metadata는 사이트 기본 OG 이미지 fallback을 사용합니다. 교체·삭제·레코드 삭제 시 같은 URL을 다른 레코드가 쓰지 않으면 저장소 파일도 정리합니다.

모듈과 플러그인은 같은 설치/활성화 테이블을 사용할 수 있지만, 개념은 구분합니다.

```text
module = 자기 도메인과 정책을 소유하는 확장
plugin = 특정 모듈이나 계약 파일에 붙어 동작하는 확장
```

공식 선택 모듈은 비활성 상태에서도 코어와 다른 모듈이 동작해야 합니다. 다른 모듈이 공식 선택 모듈을 활용하더라도 기본 실행에 필수 의존으로 만들지 않고, 안정 식별자 기반의 단방향 참조나 명시적 계약 파일을 우선합니다.

## 15. 모듈 설정은 전용 화면을 우선한다

운영자가 바꾸거나 동작에 직접 영향을 주는 설정은 모듈 전용 관리자 화면에서 다룹니다. `/admin/modules`는 설치, 상태, 파일 반영 같은 모듈 수명주기만 관리하고 범용 key/value 설정 편집을 제공하지 않습니다.

모듈/업데이트 수명주기의 권위 있는 실행 기준은 코어 helper가 소유합니다. 코어는 설치 상태, 스키마 버전, pending SQL 계산, update SQL 적용, 모듈 소스 배치 검증, route 충돌 검사, 설치/상태/버전 변경을 제공하고, 관리자 모듈은 이 코어 API를 호출하는 기본 운영 UI와 권한, 재인증, 감사 로그 표시를 담당합니다. 다른 관리자 UI를 만들더라도 같은 코어 helper를 호출해야 `sr_modules`, `sr_schema_versions`, 모듈 계약 검증 기준이 갈라지지 않습니다.

기본 방향:

- 모듈은 자기 설정의 의미, 단위, 허용 범위가 드러나는 전용 화면을 최대한 제공한다.
- 설정 저장은 POST, CSRF, 서버 측 타입/범위 검증을 적용한다.
- 설정 변경은 감사 로그에 남긴다.
- 도메인 설정의 의미와 정책은 해당 모듈이 책임진다.

예:

```text
member -> /admin/member-settings
seo -> /admin/seo
logo_manager -> /admin/logo-manager
popup_layer -> /admin/popup-layers
```

## 16. 공개 레이아웃은 전역 껍데기만 담당한다

공개 화면의 기본 레이아웃 파일은 `layouts/public/{layout_key}/` 아래에 둡니다. 레이아웃 파일은 `<html>`, `<head>`, 공통 header/footer, 공통 메뉴, 전역 output slot처럼 사이트 전체 껍데기만 담당합니다. 콘텐츠·커뮤니티·퀴즈·설문은 주 메뉴 key를 layout context의 `site_menus.primary`로 전달하고, 추가 메뉴는 `area_key`, `label`, `menu_key`를 가진 항목 목록을 `site_extra_menus` 배열로 전달합니다. `area_key`는 새 항목에 자동 생성되는 중복 없는 6바이트 hex key입니다. 주 메뉴는 기본적으로 `header` 사이트 메뉴를 사용하고, 추가 메뉴는 기본 고정 슬롯을 만들지 않고 관리자 화면에서 항목을 추가한 순서대로 저장합니다. 관리자 화면에서는 `주 메뉴`와 `추가 메뉴` 항목 목록으로 표시하고, 선택지는 `사용 안 함`, 모듈 내장 메뉴, 사이트 메뉴 순서로 표시합니다. 번들 공통/콘텐츠/커뮤니티/퀴즈/설문 레이아웃은 주 메뉴만 기본 렌더링하고, 추가 메뉴 렌더링은 제작자가 만든 레이아웃이 필요할 때 직접 처리합니다. 콘텐츠 내장 메뉴 key `sr_content_groups`는 콘텐츠 그룹을, 커뮤니티 내장 메뉴 key `sr_community_board_groups`는 게시판 그룹을 렌더링합니다. 실제 위치는 레이아웃 구현이 결정합니다. 배너의 `core/site.layout/before_layout` 전역 slot은 body 안에서 layout header보다 앞에, `core/site.layout/after_layout` 전역 slot은 layout footer 뒤에 렌더링합니다. `layout-options.php`로 공개 레이아웃을 제공하는 번들 서비스 모듈은 `{module}.layout/before_layout` 배너 slot을 해당 모듈 layout header 앞에, `{module}.layout/before_footer` 배너 slot을 해당 모듈 layout footer 앞에 렌더링합니다.

번들 사용자 레이아웃의 header 액션은 회원 계정 링크와, 알림 모듈이 활성화되어 있을 때 회원 사이트 알림 요약 드롭다운을 표시합니다. 알림 시간은 상대 한국어 시간으로 노출하되 정확한 원본 시각은 `<time>`의 `datetime`, `data-sr-time-tooltip-label`, 접근성 label에 남깁니다.

상단 사이트 메뉴는 현재 요청과 메뉴 항목 URL이 일치할 때 해당 링크에 `aria-current="page"`를 붙여 활성 상태를 표시합니다. 메뉴 항목 URL에 쿼리가 있으면 해당 쿼리 값이 현재 요청에 포함되는지 비교하고, 쿼리가 없으면 같은 경로의 요청을 활성으로 봅니다. 커뮤니티 게시판 메뉴(`/community/board?key=...`)는 해당 게시판의 목록, 글쓰기, 글읽기, 글수정 화면에서도 같은 게시판 컨텍스트로 보고 활성화합니다.

번들 콘텐츠/커뮤니티 레이아웃에서 주 메뉴를 사용 안 함으로 설정하면 사이트 메뉴 output slot은 호출하지 않고, 내장 그룹 메뉴를 자동 fallback 렌더링하지 않습니다. 운영자가 메뉴 선택에서 `콘텐츠 그룹` 또는 `게시판 그룹` 내장 메뉴를 명시 선택하면 같은 그룹 링크를 렌더링합니다. 커뮤니티 게시판 그룹 링크는 `/community/group?key=...` 전용 페이지를 사용하고, 사용 상태인 게시판 그룹이 하나도 없을 때만 공개 게시판을 후보로 사용합니다.

모듈별 화면 구조와 도메인 표시 방식은 각 모듈 안에 둡니다.

```text
layouts/public/basic/layout.php
modules/community/theme/basic/layout.php
modules/community/theme/basic/home.php
modules/community/skins/basic/list.php
```

## 17. 고부하 관리자 작업은 사전 영향 안내와 서버 재검증을 갖춘다

대량 재계산, 복사, 삭제, 저장소 정리, 전체/그룹 적용처럼 데이터량에 따라 실행 시간이 길어질 수 있는 관리자 작업은 실행 전 대상 수와 영향 범위를 보여줍니다. 공통 등급은 `주의`, `높음`, `매우 높음`으로 나누고, 판단에는 대상 레코드 수, 관련 테이블 수, 파일 저장소 작업 수, 외부 저장소 호출, 긴 트랜잭션 가능성, 실패 시 롤백/재시도 가능성을 함께 사용합니다.

`높음` 이상 작업은 HTML confirm이나 disabled 버튼만으로 보호하지 않고 확인 문구 입력, dry-run/사전 계산, 배치 실행, 진행률 표시 중 하나 이상을 적용합니다. 서버 POST는 CSRF/권한과 함께 확인 문구 또는 확인값을 다시 검증하고, 실행 직전 대상 수를 다시 계산합니다. 완료/실패/부분 완료 결과는 화면 피드백과 감사 로그 metadata 또는 배치 작업 테이블에 대상 수, 성공/실패 수, 배치 여부, 부하 등급, 확인 검증 결과를 남깁니다.

관리자 목록의 선택 기반 일괄 작업은 core가 도메인 정책을 소유하는 기능이 아니라, 목록 UI와 서버 신뢰 경계를 맞추는 관리자 화면 패턴으로 둡니다. 선택 열, 현재 페이지 전체 선택, 선택 수 표시, 확인/진행 모달, 결과 토스트 같은 반복 UI는 공통화할 수 있지만, 작업 가능 상태, 조건부 상태 전이, 감사 로그 의미, 원장 처리, 발송 정책은 각 모듈 action이 소유합니다. 검색 결과 전체 선택은 현재 페이지 선택과 화면에서도 서버 계약에서도 분리하며, 서버는 POST된 ID 배열과 선택 수를 신뢰하지 않고 권한, 대상 존재 여부, 처리 가능 상태를 다시 계산합니다.

고부하 관리자 작업 저장소는 모듈별 작업 테이블을 기본으로 유지하고, 관리자 공통 UI는 모듈이 제공하는 작업 상태 contract를 읽습니다. 도메인별 metadata, 개인정보, 보관 기간, 실패 정리 정책이 다르므로 단일 공통 작업 테이블을 기본값으로 삼지 않습니다. 공통화는 관리자 UI helper, 진행 모달 JS, lock/상태 전이 helper, 작업 상태 contract처럼 도메인 정책을 소유하지 않는 실행 기반에 한정합니다. 공통 작업 테이블이 필요해지는 경우에는 별도 결정으로 이 기본값을 뒤집고, 저장할 metadata와 개인정보 보관 기간을 먼저 정의합니다.

신규 고부하 작업은 `즉시 제한형` 또는 `작업 테이블형` 중 하나로 설계합니다. 작업 ID 없이 브라우저가 cursor를 이어 보내는 배치형은 기존 구현의 호환/전환 패턴으로만 두고 신규 1급 실행 모델로 삼지 않습니다. 고위험 작업은 기본적으로 작업 테이블형을 사용합니다. 다만 소량·건수 제한·서버 재검증·조건부 상태 전이·대상 단위 멱등성으로 충분히 통제되는 고위험 작업은 즉시 제한형을 유지할 수 있습니다. 안전한 단일 요청 시간을 넘길 가능성이 있는 작업이나 브라우저 종료 후 재개가 필요한 작업은 carve-out 없이 작업 테이블형을 사용합니다.

lock은 동시 실행 방지 수단일 뿐 재시도 멱등성을 보장하지 않습니다. 재시도 가능한 batch는 대상 단위 완료 마커, map row, dedupe key, 또는 조건부 상태 전이를 가져야 합니다. 상태 변경 일괄 작업은 기본적으로 `UPDATE ... WHERE id IN (...) AND status = :expected_from`처럼 기대 상태를 조건에 포함하고 affected rows를 요청 수와 대조합니다. 금전성 작업은 같은 대상에 중복 원장이나 중복 상태 변경이 생기지 않도록 대상 단위 dedupe나 상태 전이 조건을 둡니다. 대상 단위 멱등성 마커와 도메인 변경은 같은 원자적 경계 안에서 갱신합니다. 도메인 변경만 성공하고 마커가 남지 않는 순서는 허용하지 않습니다.

작업 테이블형의 `lock_token`은 fencing token으로 취급합니다. 재시도 takeover 뒤 이전 요청이 늦게 완료되더라도, 모든 상태 쓰기와 대상 처리 결과 저장은 현재 token이 유효할 때만 성공해야 합니다. 만료된 `running` 작업을 재시도 가능 상태로 보여줄 수는 있지만, takeover가 이전 token의 쓰기를 서버에서 거부하지 못하면 같은 작업을 동시에 이어받는 구조로 보지 않습니다. batch 트랜잭션은 시작 시 현재 token을 재확인하고, 도메인 쓰기와 map/cursor/count 갱신을 같은 commit에 포함합니다. 외부 파일/메일 작업처럼 트랜잭션에 넣을 수 없는 작업은 사전/사후 상태를 분리하고 보상 정리 또는 dedupe 상태를 먼저 기록합니다.

검색 결과 전체 선택은 작업 생성 시 explicit ID snapshot을 고정할지, 서버가 allowlist로 정규화한 query snapshot으로 batch마다 재계산할지 operation별로 정합니다. 고위험·금전성 작업은 explicit ID snapshot 고정을 기본으로 하고, 그룹 재평가나 레벨 재계산처럼 멱등적이고 재실행 자체가 자연스러운 작업만 query snapshot 재계산을 허용합니다. query snapshot 모드는 작업 생성 직전 예상 대상 수를 계산하고, 각 batch 전 실제 대상 수를 다시 계산합니다. drift가 절대 50건 이상 또는 10% 이상이면 작업을 재확인 필요 상태로 멈추고, 관리자에게 이전 예상 대상 수와 현재 대상 수를 보여준 뒤 이어가기 또는 취소를 선택하게 합니다. operation별로 더 낮은 drift 기준을 선언할 수 있습니다.

작업 `operation_key`는 core가 모든 도메인 작업을 직접 열거하지 않습니다. 각 모듈은 관리자 작업 contract에서 operation key, 권한 path/action, 실행 모델, snapshot 모드, 위험도, handler를 제공합니다. operation key는 `{module_key}.{operation}` 형식을 기본으로 하며, 공통 런타임은 관리자 POST 처리 전에 활성 모듈의 작업 contract를 명시적으로 읽어 registry를 구성합니다. 중복 operation key가 있으면 작업 생성을 거부하고 운영 로그에 남깁니다. 공통 런타임은 등록 여부와 권한, 공통 상태 전이 규칙만 검증합니다. 회원 그룹 재평가 조건, 커뮤니티 게시판 복사 stage, 금전성 신청 완료/거부 정책 같은 의미는 계속 해당 모듈이 소유합니다.

DB에는 파일 경로를 저장하지 않고 `public_layout_key`, `layout_key`, `skin_key` 같은 key만 저장합니다. 공개 레이아웃 key는 `common.basic`, `content.basic`, `community.basic`, `quiz.basic`, `survey.basic`처럼 provider namespace를 포함하며, 실제 파일 경로는 코드의 allowlist helper와 `layout-options.php` 계약이 결정합니다. 기존 `basic` 값은 `common.basic`으로 정규화합니다. 알 수 없는 사이트 공통 레이아웃 key는 `common.basic`으로 fallback합니다. 콘텐츠, 커뮤니티, 퀴즈, 설문처럼 환경설정에서 레이아웃을 저장하는 모듈은 저장값이 없거나 무효이면 사이트 공통 레이아웃을 먼저 사용한 뒤 기본 공통 레이아웃으로 fallback하되, 해당 레이아웃이 모듈의 필수 화면 target 전체를 지원할 때만 선택지와 저장값으로 인정합니다.

사이트 공통 레이아웃과 콘텐츠/커뮤니티/퀴즈/설문 공개 레이아웃은 같은 레이아웃 option registry에서 고릅니다. 콘텐츠·커뮤니티·퀴즈·설문 공개 화면은 각 모듈 환경설정의 레이아웃 key를 하위 공개 화면 전체에 적용합니다. 필수 target은 콘텐츠 `content.home`, `content.group`, `content.view`, 커뮤니티 `community.home`, `community.group`, `community.list`, `community.post`, `community.form`, `community.search`, 퀴즈 `quiz.home`, `quiz.view`, `quiz.result`, 설문 `survey.home`, `survey.view`, `survey.complete`입니다. 교차 모듈 레이아웃은 이 target 전체를 명시적으로 지원할 때만 해당 모듈 환경설정에 표시됩니다. 과거 콘텐츠별/그룹별 `layout_key` 저장값은 입력 기본값과 revision 호환 기록으로 남길 수 있지만, 공개 렌더링의 header/footer 레이아웃 선택은 모듈 환경설정이 우선합니다. 모듈별 세부 목록/상세/작성 화면의 본문 구성은 계속 해당 모듈의 layout/skin 책임으로 둡니다.
번들 `content.basic`, `community.basic`, `quiz.basic`, `survey.basic`은 공통 레이아웃과 같은 시각 언어를 사용할 수 있지만 실제 파일은 각 모듈 경계 안에서 소유합니다. 콘텐츠는 `modules/content/theme/{theme_key}/layout.php`, 커뮤니티는 `modules/community/theme/{theme_key}/layout.php`, 퀴즈는 `modules/quiz/theme/{theme_key}/layout.php`, 설문은 `modules/survey/theme/{theme_key}/layout.php`를 사용합니다. 같은 theme 디렉터리의 `assets/layout.css`가 해당 layout shell 표현을 소유하며, 모듈 전용 header/footer 스타일도 공통 스타일시트가 아니라 선택된 layout provider theme의 CSS에 둡니다.

디자인 책임은 다음 경계를 유지합니다.

| 범위 | 책임 | 피할 것 |
| --- | --- | --- |
| public layout | 문서 골격, 공통 head, 사이트 header/footer, 공통 메뉴, output slot, 전체 폭과 기본 여백 | 게시판 목록, 회원 폼, 상품 카드처럼 모듈 도메인 표시를 직접 소유 |
| public foundation | 공개 화면의 reset/base, 접근성 helper, 역할 기반 타이포그래피 | 버튼, 폼, 카드, 탭, 테이블처럼 서비스 표현을 바꿀 수 있는 UI primitive |
| public UI kit | kit profile에서 쓰는 버튼, 폼, 카드, 테이블, 알림, 페이지네이션 같은 반복 가능한 기본 class | 특정 모듈의 정책이나 화면 흐름을 전제로 한 class, 콘텐츠/커뮤니티 minimal profile에 기본 주입 |
| module layout | 모듈 홈이나 섹션 첫 화면처럼 모듈 단위의 큰 정보 배치 | 특정 게시판/항목의 세부 표시를 모두 흡수 |
| module skin | 목록, 상세, 작성 폼, 배너 item, 팝업 layer처럼 특정 기능 단위의 표시 | 사이트 전체 header/footer나 다른 모듈 화면을 변경 |
| admin theme | 관리자 shell, 사이드바, 상단바, 관리자 공통 asset, 관리자 콘텐츠 컨테이너 | 모듈별 관리자 도메인 정책이나 저장 흐름 |
| admin view | 각 모듈의 관리자 본문 마크업과 도메인 출력 | 관리자 shell, 전역 navigation, 공통 관리자 asset 선택 |

CSS class는 충돌을 줄이기 위해 책임 범위별 이름을 사용합니다. 공개 reset/foundation은 `assets/reset.css`가 토큰, 아이콘 fallback, reset/base, 접근성 helper, `type-*` 역할 class를 소유합니다. 반복 가능한 공개 UI 원형은 `assets/ui-kit.css`가 `btn`, `card`, `table`, `badge`, `form-*`, `dropdown-*`, `modal-*`, `tab-*` 같은 의미 class를 소유하고, `/ui-kit` 미리보기 배치와 샘플 helper는 `assets/ui-kit-layout.css`가 소유합니다. 기본 공개 layout shell은 `assets/layout.css`, 초기 공개 화면 본문 스타일은 `assets/module.css`가 맡습니다. 콘텐츠/커뮤니티/퀴즈/설문처럼 내부 view theme을 가진 공개 모듈은 선택된 `theme/{theme_key}/assets/` 아래에 reset, UI kit, layout, module, theme stylesheet를 둡니다. 관리자 런타임은 기존처럼 `modules/admin/assets/tokens.css`, `modules/admin/assets/common.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css`를 사용합니다. 관리자 전용은 `admin-*`, 모듈 전용은 `{module_key}-*` 또는 `sr-{module_key}-*`, 특정 스킨 전용은 `{module_key}-skin-{skin_key}-*` 형식을 우선합니다. 공통 layout과 루트 UI kit만 `public-layout-*`, `public-ui-*` namespace를 소유하고, 공개 레이아웃을 제공하는 모듈은 해당 prefix를 재사용하지 않습니다. 공통 layout이나 UI kit이 모듈 전용 class를 덮어쓰지 않고, 모듈 skin도 전역 `body`, `a`, `.container`, `.btn` 같은 넓은 선택자를 직접 재정의하지 않습니다.

원본 `/ui-kit` 조회 화면은 초기/기본 공개 페이지와 public layout 런타임 기준 공통 원형을 확인하는 개발 보조 화면으로 보존합니다. 모듈 화면의 최종 디자인 정책은 원본 UI-KIT이 소유하지 않습니다. 콘텐츠/커뮤니티/퀴즈/설문 공개 화면은 선택된 내부 view theme의 `assets/` CSS를 사용하되 파일은 `reset.css`, `layout.css`, `module.css`, `ui-kit.css`, `ui-kit-layout.css`, `theme.css`를 기본으로 두고, 스킨을 사용하는 화면은 필요한 경우 `skin.css`를 추가합니다. `reset.css`는 해당 모듈 공개 화면의 foundation, `layout.css`는 레이아웃 shell, `module.css`는 모듈 서비스 화면 스타일, `ui-kit.css`는 모듈 공개 UI primitive와 샘플에서 확인할 공통 표현, `ui-kit-layout.css`는 모듈 UI-KIT 미리보기의 컨테이너와 샘플 배치 helper, `theme.css`는 선택 theme 고유 보정을 소유합니다. 모듈별 UI-KIT 미리보기는 `/content/ui-kit`, `/community/ui-kit`, `/quiz/ui-kit`, `/survey/ui-kit` 사용자 화면에서 선택 theme의 `ui-kit.php`와 같은 stylesheet 조합으로 확인합니다. 단, 운영자가 해당 모듈 환경설정에서 레이아웃을 변경한 경우에는 선택된 레이아웃의 호출 정책을 따릅니다.

모듈 skin에 전용 CSS가 필요하면 `sr_public_layout_begin()`의 layout context에 `stylesheets`를 전달합니다. 공개 화면은 layout option의 `style_profile` 또는 layout context의 `style_profile`로 `kit`, `minimal`, `install`, `module` 중 하나를 선택합니다. 공통 layout은 `kit` profile을 기본값으로 삼아 `btn`, `card`, `form-*`, `table`, `badge` 같은 공통 공개 UI primitive를 제공합니다. 회원, 알림, 포인트/자산, 개인정보처럼 자기 public layout을 갖지 않는 account/utility 화면은 사이트 설정의 공개 layout을 그대로 사용하고 layout context에는 필요한 스킨 stylesheet만 추가합니다. 콘텐츠/커뮤니티/퀴즈/설문 layout은 minimal profile을 기본값으로 삼아 루트 공통 공개 UI primitive가 자동으로 섞이지 않게 합니다. 단, layout option의 profile은 fallback일 뿐 화면 성격보다 우선하지 않습니다. 원본 공개 UI-KIT처럼 공통 primitive 확인이 목적이거나 독립 화면이 직접 kit을 요구할 때만 layout context에 `style_profile => 'kit'`을 명시합니다. 공통 공개 layout은 `assets/reset.css`, `assets/ui-kit.css`, `assets/layout.css`, `assets/public-layout.js`를 사용하고, 초기 공개 화면은 `assets/module.css`를 추가합니다. 콘텐츠/커뮤니티/퀴즈/설문 공개 화면은 `style_profile => 'module'`로 루트 reset 자동 호출을 끈 뒤 각 모듈의 선택 theme `assets/reset.css`, `assets/ui-kit.css`, 선택된 layout provider theme의 `layout.php`와 `assets/layout.css`, 화면 모듈 theme의 `assets/module.css`, Markdown 본문 공통 `assets/editor-md.css`, CKEditor 실효 에디터의 HTML 본문 공통 `assets/editor-ck.css`, `assets/theme.css` 순서로 호출합니다. 화면 view는 `consumer_target`을 전달하고, core output helper는 선택된 layout option이 해당 target을 지원할 때 provider theme layout shell과 provider stylesheet를 선택합니다. provider `assets/layout.js`는 선택된 provider layout template이 출력하고, 화면 모듈 script는 context의 `assets/module.js`로 따로 전달합니다. 예를 들어 커뮤니티 화면이 콘텐츠 레이아웃을 선택하려면 콘텐츠 레이아웃 option이 커뮤니티 필수 target 전체를 지원해야 하며, 이 조건을 만족할 때만 콘텐츠 theme layout shell과 layout stylesheet/script를 사용하고 커뮤니티 화면 script는 `assets/module.js`로 따로 호출합니다. 모듈 UI-KIT 조회 화면은 여기에 선택 theme의 `assets/ui-kit-layout.css`를 추가하고, 스킨 화면은 `skin.css`를 화면 조합에 맞게 추가합니다. public layout은 전달받은 stylesheet와 script를 출력하는 통로를 제공합니다. 출력 슬롯처럼 head 렌더링보다 뒤에서 HTML이 만들어지는 공개 모듈 출력도 해당 슬롯을 호출하는 view, skin, public layout이 필요한 stylesheet와 script를 layout context의 `output_slots`와 provider `output-slots.php` asset metadata로 head 렌더링 전에 합류시킵니다. 공개 CSS와 script는 활성화된 모든 모듈 기준으로 자동 호출하지 않습니다.

모듈 관리자 본문에 전용 CSS가 필요하면 `module.php`의 `admin.stylesheets`에 자기 모듈 `assets/` 아래 CSS를 선언합니다. admin theme은 `modules/admin/assets/tokens.css`, `modules/admin/assets/common.css`, `assets/admin-ui.css`, `modules/admin/assets/admin.css` 뒤에 현재 관리자 화면을 소유한 모듈의 선언만 검증해 출력합니다. `/admin` 대시보드는 실제 대시보드 섹션을 제공하는 모듈의 선언만 추가합니다. admin 공통 CSS는 모듈 도메인 class를 직접 소유하지 않습니다.

스킨별 기능이 필요한 모듈은 스킨 폴더의 `skin.php` 계약으로 필수 view와 선택 action을 명시합니다. action은 스킨 view에서 직접 실행하지 않고 모듈이 소유한 단일 진입 action을 거쳐 현재 선택된 스킨과 allowlist를 검증한 뒤 include합니다. 필수 view가 누락된 스킨은 선택 목록에서 제외하고 저장된 key도 `basic`으로 fallback합니다.

퀴즈와 설문처럼 운영자가 개별 공개 대상을 관리하는 모듈은 환경설정의 `skin_key`를 기본값으로 두고, 개별 퀴즈나 개별 설문 row의 값이 비어 있으면 그 기본값을 상속합니다. 개별 row에 값이 있으면 해당 모듈의 정적 허용 목록으로 다시 검증한 뒤 공개 렌더링에 사용합니다. 스킨은 허용된 view 파일 묶음과 해당 스킨의 CSS class hook을 함께 선택하는 값입니다.

현재 선택 action 계약은 커뮤니티 게시판 스킨에만 둡니다. 관리자 테마, 회원/배너/팝업레이어 스킨, 공개 레이아웃, 커뮤니티 레이아웃은 표시 전용 계약이며 필수 view 누락 검증과 fallback만 공유합니다.

## 18. 행동 보상은 모듈 로그와 공통 dedupe 용어를 함께 쓴다

퀴즈와 설문처럼 회원 행동 완료 후 보상을 자동 지급하는 모듈은 코어 테이블을 넓히지 않고 자기 `*_reward_grants` 로그를 소유한다. 로그는 `reward_provider`, `reward_module`, `reward_code`, `dedupe_scope`, `dedupe_key`, `status`, provider reference를 공통 이름으로 저장한다. `dedupe_key`에는 회원 ID, 대상 ID, 정책 ID, 공급자, 모듈, scope별 대상 식별자를 포함해 같은 정책의 중복 지급을 막는다.

재시도는 기존 `pending` 또는 `failed` 로그에서만 수행하며, 실행 직전에 자산 계약 함수나 쿠폰 정의 활성 상태를 다시 확인한다. 기존 `granted` 로그가 있으면 새 원장/쿠폰 지급을 만들지 않는다. 퀴즈의 설문형 모드는 별도 `survey` 모듈로 분리하고, 퀴즈는 점수형/진단형 풀이와 채점/결과/보상에 집중한다.

## 19. 보상 회수 실패는 차감 목적별로 분리한다

원장 `balance_after >= 0` 불변식은 유지한다. 부족분을 음수 잔액이나 가짜 원장 거래로 표현하지 않는다. 회원이 열람, 다운로드, 작성, 구매, 교환을 위해 직접 지불하는 차감은 잔액 부족 시 본래 액션을 차단한다. 관리자 직접 회수도 회수 자체가 주 액션이므로 1차 정책에서는 실패할 수 있다.

숨김, 삭제, 무효화, 정정처럼 도메인 운영 액션에 부수되는 과거 보상 회수는 별도 정책으로 다룬다. 정확히 타입된 `asset_balance_low`만 비차단 미회수로 전환하고, unknown failure, 중복 처리, DB lock/deadlock, transaction aborted 류는 운영 액션을 차단한다. deadlock/transaction aborted 류는 SAVEPOINT rollback을 시도하지 않고 상위로 전파한다.

커뮤니티 게시글/댓글 보상 회수 실패는 `asset_ledger`의 공통 `sr_asset_recovery_failures` 큐에 기록한다. 커뮤니티 모듈은 source callback과 호환 backfill을 제공하되 신규 운영 화면과 개인정보 계약은 공통 큐를 기준으로 한다. 같은 원 지급 로그와 canonical reversal event key는 하나의 미회수 row로 수렴하고, 운영 종류는 제한된 `operation_context_json` 필드로 남긴다.

운영 액션, 회수 시도, 미회수 upsert는 같은 상위 트랜잭션에 속해야 한다. 회수 시도는 항목별 SAVEPOINT 안에서 실행하지만, 미회수 row는 운영 액션과 함께 commit 또는 rollback된다. 현재 확인된 `sr_community_run_asset_event_once()`, point/reward/deposit/ledger transaction helper는 상위 트랜잭션이 있으면 자체 begin/commit을 열지 않는 계약을 따른다. 상위 트랜잭션 안에서는 커뮤니티 자산 retry loop가 내부 재시도 없이 단일 시도로 abort될 수 있으며, v1에서는 이 trade-off를 수용한다.

1차 범위는 커뮤니티 게시글/댓글 숨김/삭제에 한정한다. 일반 적립금 직접 회수, 퀴즈 직접 회수, 커뮤니티 회원 리워드 로그 신규 회수, 설문/콘텐츠 회수 신규 구현, 자산 교환 정정 비차단화, 가역 운영의 stale open row 자동 해소, 자동 재회수와 임계 알림은 후속 작업이다. 보상을 받은 회원이 보상을 소진한 뒤 콘텐츠가 숨김/삭제되면 운영 액션은 막지 않고 미회수로 남기는 악용 표면이 있으며, 운영자는 관리자 미회수 화면에서 조회, 수동 재회수, 수동 해소/취소를 처리한다.

## 20. 런타임은 최신 스키마를 기준으로 실패한다

개발 단계의 런타임 코드는 옛 DB 스키마를 정상 경로로 호환하지 않는다. 모듈이 소유한 current schema 테이블과 컬럼은 `install.sql`과 `updates/`가 보장해야 하며, 관리자 목록, 개인정보 export, 환불/취소, 접근권/다운로드/정산 로그에서 컬럼 누락을 `schema_unavailable`, `legacy_unknown`, `NULL AS ...`, `'' AS ...` 같은 alias로 합성하지 않는다.

스키마가 오래된 DB는 graceful degradation 대상이 아니라 설치/업데이트 경계에서 드러나야 하는 운영 상태다. `/admin/updates`, module lifecycle helper, release/install smoke는 pending SQL을 확인하고, 코드 업로드 뒤 업데이트를 적용하지 않은 요청은 fail-fast SQL 오류 또는 업데이트 필요 상태로 발견되는 것을 허용한다. 공유호스팅 배포에서는 코드 교체 후 `/admin/updates` 적용을 배포 절차에 포함한다.

`*_column_exists`, `*_columns_exist`, `optional_column_exists` 같은 필수 컬럼 런타임 가드는 새로 추가하지 않는다. `optional_table_exists`는 다른 모듈이 설치되지 않았을 수 있는 cross-module reference counter처럼 실제 optional module boundary에서만 사용한다. 2026-07-01 기준 허용된 동적 optional boundary는 콘텐츠/커뮤니티 삭제 영향 계산에서 외부 사이트 메뉴 등 선택 모듈 테이블을 카운트하는 내부 count helper뿐이다. 회귀 방지는 `.tools/bin/check-schema-fallback-policy.php`와 `.tools/bin/snapshot-schema-fallbacks.php`가 담당한다.

원장 레거시 데이터 호환은 스키마 fallback과 분리한다. 예를 들어 커뮤니티 asset recovery의 옛 평면 event key 조회는 컬럼 존재 여부 호환이 아니라 기존 원장 row를 찾는 복구 경로이므로, 옛 row 부재 검증이나 별도 마이그레이션 완료 기준 없이 이 결정으로 제거하지 않는다.

## 21. 신고 자동 임시조치는 커뮤니티 모듈 경계 안에서 구현한다

#392 검토 결과, #388의 커뮤니티 신고 임계치 자동 임시조치는 새 코어 helper나 범용 moderation 계약을 선행으로 요구하지 않는다. 자동 조치는 신고, 게시글, 댓글, 게시판 공개 상태, 관리자 처리 흐름, 커뮤니티 운영 알림을 함께 판단하므로 커뮤니티 모듈이 소유한다.

자동 조치 이력 테이블, active 대상 유일성, 숨김 fingerprint, per-target confirmed/released 결정, 공개 placeholder/exclude 정책은 커뮤니티 모듈의 스키마와 helper로 둔다. 코어는 범용 `moderation` 테이블, route, 권한, 상태 모델을 만들지 않는다. 새 `*-targets.php` 계약도 #388 v1에는 추가하지 않는다. 정책 위임이 필요한 소비 지점이 확인되지 않았기 때문이다.

#392의 cleanup failure helper, 조회수 dedupe helper, 유료 접근권 spike, 댓글 capability-aware 계약은 #388의 하드 선행이 아니다. 댓글 공통화 판단은 #388/#389로 moderation 상태 모델이 확정된 뒤 다시 본다. #388 구현 중 실제 반복 로직이 드러나더라도 먼저 커뮤니티 모듈 내부 helper로 좁히고, 둘 이상의 모듈이 같은 정책 없는 기반을 소비한다는 증거가 생길 때만 공통 helper나 계약으로 승격한다.

2026-07-01 #388/#389 구현 후 재검토 결과, 댓글은 아직 공유 저장소나 공통 `comment-targets.php` 계약으로 승격하지 않는다. 콘텐츠 댓글과 커뮤니티 댓글은 thread/depth, secret, 작성자 snapshot 같은 모양은 비슷하지만, 커뮤니티는 비회원 작성, 게시판별 댓글 권한, IP/UA hash, 신고 자동조치, hidden metadata, 계정 guard 판단과 관리자 게시판 권한을 함께 가진다. 콘텐츠는 로그인 회원 댓글과 콘텐츠 작성자/관리자 중심의 좁은 숨김 정책을 유지한다. 따라서 현 단계의 공통화 후보는 저장/상태 전이가 아니라 공개 read shaping, thread ordering, body visibility 결과처럼 도메인 정책이 이미 판정된 뒤의 얇은 helper다. 그런 helper도 두 모듈이 실제로 같은 출력 계약을 소비하는 화면이나 모듈이 생길 때만 추가하고, 그 전에는 각 모듈 테이블과 정책 helper를 유지한다.

## 22. 통화 denomination은 asset-unit과 settlement currency를 분리한다

#396 결정 gate 결과, 포인트/적립금/예치금 잔액과 거래 `amount`는 통화 금액이 아니라 모듈별 asset-unit 정수다. 통화 의미는 `purchase_power`의 `asset_units : settlement_units + settlement_currency` 비율과 가격, 정액 할인, 고정 수수료 같은 currency-literal에만 둔다. settlement 기준 통화와 참여 자산의 purchase-power 통화가 다르면 fail-closed한다.

`currency_min_unit`은 decimal exponent가 아니라 core/settings가 소유하는 정수 settlement minimum unit이자 known currency 가드다. 1.x는 decimal sub-unit 저장을 지원하지 않으므로 현재 `USD => 1`은 cent가 아니라 whole-dollar settlement만 의미한다. cent 지원이나 다통화/FX는 별도 저장 스케일 마일스톤으로 다룬다.

사이트 기본 통화 변경은 과거 settlement/refund/payment/tax snapshot을 소급 재해석하지 않는다. 기존 기록은 record-time 통화로 frozen이며, 기본 통화는 새 가격·정책 row와 통화가 빠진 fallback 입력의 기준일 뿐이다. nonzero balance 자체는 변경 차단 사유가 아니고, 위험은 live purchase-power 비율과 currency-literal을 새 통화로 조용히 재해석하는 데 있다. 세부 계약은 [통화 Denomination 계약](denomination-contract.md)을 따른다.
