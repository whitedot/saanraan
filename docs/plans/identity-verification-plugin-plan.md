# 본인확인 플러그인 연동 계획

이 문서는 산란에서 외부 본인확인 서비스를 선택 모듈과 플러그인으로 연동하기 위한 구현 계획과 남은 검증 항목이다.

상태:

- 2026-07-04에 `identity_verification`, `identity_kcp`, `identity_inicis`의 초기 구현을 추가했다.
- 공통 attempt/result/link 저장, provider 설정, return/callback 처리, privacy export/cleanup, retention/operational-status 계약은 코드에 있다.
- KCP와 KG이니시스 provider adapter는 공개 문서 기준으로 구현했지만, 실제 provider 성공 인증 smoke는 테스트/운영 상점 정보와 provider 암복호화 라이브러리가 있는 로컬 또는 staging에서 별도로 검증해야 한다.
- 이 문서는 실인증 smoke와 #324 소비 정책 연결이 끝날 때까지 삭제하지 않는다.

대상 서비스:

- KG이니시스 통합인증 본인확인
- NHN KCP 휴대폰 본인확인

문서 수명:

- 실제 provider smoke와 소비 모듈 정책 연결 전까지 남은 검증 항목으로 보관한다.
- 구현과 검증이 모두 완료되면 이 문서는 삭제한다.
- 계속 유지해야 하는 기준은 `docs/module-guide.md`, `docs/core-decisions.md`, `docs/security-model.md`, 본인확인 모듈 README 중 필요한 곳으로만 옮긴다.

## 공식 문서 확인 기준

구현 시점에는 각 provider의 가맹점 계약 문서와 개발가이드를 다시 확인한다.

공개 페이지에서 확인한 기준:

- KG이니시스는 통합인증서비스로 간편인증, 전자서명, 본인확인을 통합 제공한다고 안내한다.
- NHN KCP는 주민등록번호를 이용하지 않고 휴대폰 가입정보로 진행하는 휴대폰 본인확인 서비스를 제공한다고 안내한다.

참고 URL:

- KG이니시스: https://www.inicis.com/intro-inicis
- NHN KCP: https://www.kcp.co.kr/service/

공개 페이지에는 실제 endpoint, 암호화 방식, 서명 검증, 응답 필드가 모두 고정되어 있지 않을 수 있다. 구현 전 반드시 계약 후 제공되는 최신 개발가이드로 다음 항목을 확정한다.

- 요청 파라미터
- return URL과 server callback URL
- nonce/state 검증 방식
- 서명 또는 암호문 검증 방식
- 테스트/운영 상점 ID
- 응답 필드와 개인정보 보관 제한
- 재시도와 중복 callback 처리
- 서비스 이용 약관과 개인정보 처리 안내 문구

## 기본 방향

본인확인은 코어가 아니라 선택 모듈과 provider 플러그인으로 처리한다.

권장 구조:

- `identity_verification`: 본인확인 요청, 결과, 관리자 설정, 공통 화면을 소유하는 공식 선택 모듈
- `identity_inicis`: KG이니시스 통합인증 본인확인 provider 플러그인
- `identity_kcp`: NHN KCP 휴대폰 본인확인 provider 플러그인
- 본인확인을 사용하는 모듈: 회원가입, 성인 인증, 개인정보 처리 요청, 고위험 계정 작업 같은 업무 정책을 소유하는 모듈
- `core`: 실명, 생년월일, 통신사, 본인확인 상태, provider 설정을 알지 않는다.

본인확인 결과를 회원 도메인에 연결할 수는 있지만, `sr_member_accounts`를 넓히지 않는다. 검증 결과와 연결 정보는 `identity_verification` 모듈의 테이블이 소유한다.

## 책임 분리

### 본인확인 사용 모듈

예상 사용처:

- `member`: 가입 후 본인확인, 계정 본인확인 상태 확인
- `privacy`: 개인정보 처리 요청 제출/자료 다운로드 전 강화 확인
- `community`: 성인 게시판 또는 실명 확인이 필요한 게시판
- `deposit` 또는 `payment`: 고위험 금전성 작업 전 추가 확인

책임:

- 어떤 상황에서 본인확인이 필요한지 판단
- 본인확인 성공 후 자기 도메인 상태 변경
- 본인확인 실패 시 사용자 흐름 결정
- 본인확인 결과를 얼마나 오래 신뢰할지 결정

사용 모듈은 본인확인 모듈에 다음 정도만 요청한다.

```text
이 계정 또는 이 세션에 대해 이 purpose의 본인확인을 시작한다.
성공하면 내 confirm action으로 알려 달라.
```

### `identity_verification` 모듈

책임:

- 본인확인 요청 생성
- provider 선택
- state/nonce 생성과 검증
- return/callback 수신
- provider 응답 검증 요청
- 공통 상태 전이 관리
- 사용 모듈 confirm 호출
- 관리자 설정과 결과 조회
- 감사 로그
- 보관 기간 정리 대상 제공

소유하지 않는다:

- 회원가입 정책 전체
- 성인 게시판 정책
- 개인정보 처리 요청 완료 판단
- 금전성 거래 제한 정책

### provider 플러그인

`identity_inicis` 책임:

- KG이니시스 통합인증 본인확인 설정 필드 정의
- 인증창 시작 파라미터 생성
- return/callback 응답 검증
- provider 원문 응답을 산란 공통 결과로 변환
- 테스트/운영 환경 전환 처리

`identity_kcp` 책임:

- NHN KCP 휴대폰 본인확인 설정 필드 정의
- 인증창 시작 파라미터 생성
- return/callback 응답 검증
- provider 원문 응답을 산란 공통 결과로 변환
- 테스트/운영 환경 전환 처리

provider 플러그인은 회원 상태, 개인정보 요청 상태, 게시판 권한, 금전성 거래 상태를 직접 변경하지 않는다.

## 계약 파일

계약 파일을 늘리지 않기 위해 provider마다 단일 계약 파일을 둔다.

권장 파일:

```text
modules/identity_inicis/identity-provider.php
modules/identity_kcp/identity-provider.php
```

`identity-provider.php`는 배열을 반환한다.

예상 값:

- provider_key
- display_name
- supported_methods
- settings_schema
- handlers
- return_paths
- callback_paths

예시:

```php
<?php

return [
    'provider_key' => 'inicis',
    'display_name' => 'KG이니시스 통합인증 본인확인',
    'supported_methods' => ['integrated_identity'],
    'settings_schema' => [
        'merchant_id' => ['type' => 'string', 'secret' => false],
        'merchant_key' => ['type' => 'string', 'secret' => true],
        'environment' => ['type' => 'enum', 'values' => ['test', 'production']],
    ],
    'handlers' => [
        'prepare' => 'helpers/provider.php:sr_identity_inicis_prepare',
        'verify_return' => 'helpers/provider.php:sr_identity_inicis_verify_return',
        'verify_callback' => 'helpers/provider.php:sr_identity_inicis_verify_callback',
    ],
];
```

자동 등록이나 service provider 방식은 사용하지 않는다. `identity_verification` 모듈이 활성 플러그인의 계약 파일을 명시적으로 읽는다.

## 데이터 저장 계획

`identity_verification` 모듈 예상 테이블:

- `sr_identity_verification_attempts`
- `sr_identity_verification_results`
- `sr_identity_verification_provider_settings`
- `sr_identity_verification_links`

### `sr_identity_verification_attempts`

본인확인 시도 1건을 저장한다.

예상 컬럼:

- id
- verification_key
- provider_key
- method
- account_id
- purpose
- subject_module
- subject_type
- subject_id
- status
- state_token_hash
- nonce_hash
- return_path
- confirm_path
- requested_at
- completed_at
- failed_at
- expires_at
- failure_code
- failure_message
- created_at
- updated_at

`state_token_hash`와 `nonce_hash`는 원문을 저장하지 않는다.

### `sr_identity_verification_results`

검증 성공 결과의 최소 요약을 저장한다.

예상 컬럼:

- id
- attempt_id
- account_id
- provider_key
- provider_transaction_id
- ci_hash
- di_hash
- name_hash
- phone_hash
- birth_date
- gender
- nationality
- age_over_14
- age_over_19
- result_summary_json
- verified_at
- expires_at

저장 원칙:

- 주민등록번호 원문은 저장하지 않는다.
- CI/DI가 제공되면 원문 대신 HMAC hash를 저장한다.
- 이름과 휴대폰 번호는 원문 저장을 피하고 기본은 HMAC hash만 저장한다.
- 화면 표시가 필요한 값은 provider 응답 직후 사용자 확인 화면에서만 사용하고 DB에는 남기지 않는 것을 우선한다.
- 생년월일, 성별, 내외국인 여부는 실제 서비스 정책상 필요한 경우에만 저장한다.

### `sr_identity_verification_links`

본인확인 결과와 산란 계정의 연결을 저장한다.

예상 컬럼:

- id
- account_id
- result_id
- purpose
- linked_at
- revoked_at
- created_at

회원 테이블에 `identity_verified_at` 같은 컬럼을 바로 추가하지 않는다. 필요한 조회 helper를 `identity_verification` 모듈이 제공한다.

### `sr_identity_verification_provider_settings`

provider별 설정을 저장한다.

예상 컬럼:

- provider_key
- setting_key
- setting_value
- is_secret
- updated_at

secret 값은 관리자 화면에 다시 표시하지 않고 변경만 허용한다.

## 상태 모델

공통 상태:

- `draft`
- `ready`
- `pending`
- `verified`
- `failed`
- `expired`
- `canceled`

상태 전이 원칙:

- `verified` 이후 같은 callback이 반복되어도 멱등 처리한다.
- `failed`, `expired`, `canceled` 상태에서 `verified`로 되돌리지 않는다.
- return과 server callback이 순서 없이 도착해도 같은 결과가 되어야 한다.
- 만료된 state/nonce는 실패 처리하고 원문을 로그에 남기지 않는다.

## 시작 흐름

```text
사용 모듈
-> identity_verification attempt 생성 요청
-> provider 선택
-> provider prepare 호출
-> 외부 본인확인 창 또는 페이지 이동
-> 사용자 return 또는 server callback 수신
-> provider verify 호출
-> identity_verification 상태 확정
-> 사용 모듈 confirm action 호출
```

사용 모듈 confirm은 반드시 멱등해야 한다.

예:

```text
member 가입 후 본인확인 성공
-> identity_verification verified
-> member confirm
-> 이미 연결된 verification_key면 재처리하지 않음
```

## 개인정보와 보안

본인확인은 민감도가 높은 개인정보 흐름으로 다룬다.

필수 기준:

- 주민등록번호 원문 저장 금지
- CI/DI 원문 저장 금지, HMAC hash 저장 우선
- 이름/휴대폰 번호 원문 저장 지양
- provider 응답 원문 로그 금지
- 관리자 목록에서 개인정보 원문 표시 금지
- 결과 다운로드 기능 1차 제외
- 보관 기간 설정과 정리 대상 포함
- state/nonce 원문 저장 금지
- callback replay 방지
- provider 서명 또는 암호문 검증 필수
- HTTPS 환경 전제

본인확인 결과는 "그 시점에 provider가 확인했다"는 기록이지, 모든 업무에서 영구적으로 신뢰할 수 있는 회원 속성이 아니다. 사용 모듈은 purpose별 유효기간을 따로 판단한다.

## GDPR 연계

본인확인 모듈은 GDPR/개인정보 처리 흐름과 분리하지 않는다.

`identity_verification` 1차 구현 필수 항목:

- `privacy-export.php` 제공
- 회원별 본인확인 시도, 성공 결과 요약, 계정 연결 이력 export
- provider 원문 응답, CI/DI 원문, 이름/휴대폰 원문 export 금지
- `privacy` 모듈의 개인정보 사본 제공에서 본인확인 요약이 수집되는지 검증
- 회원 탈퇴/익명화 시 본인확인 연결을 revoke 하거나 익명화하는 정책 제공
- 보관 정리 대상에 실패/만료 attempt와 오래된 result 포함
- 관리자 화면에서 원문 개인정보를 표시하지 않는지 검증

본인확인 결과는 다음 개인정보 처리 요청과 직접 연결된다.

- 접근권: 본인확인 기록 요약을 개인정보 사본에 포함
- 정정권: 원본 신원정보를 직접 수정하지 않고 재검증을 유도
- 삭제권/처리제한권: 법적 보존이 필요한 결과와 삭제 가능한 연결 이력을 구분
- 이동권: provider 원문이 아니라 산란이 보관하는 최소 요약만 제공
- 동의철회/반대권: 본인확인 목적별 사용 중단과 연결 revoke 정책 검토

본인확인 모듈 구현 전후로 다른 회원 귀속 모듈의 `privacy-export.php` 누락도 함께 확인한다. 포인트, 적립금, 예치금처럼 `account_id` 기반 원장 데이터를 가진 모듈은 개인정보 사본 제공 대상이다.

## 관리자 화면

예상 화면:

- `/admin/identity-verifications`
- `/admin/identity-verifications/{id}`
- `/admin/identity-providers`

기능:

- 본인확인 시도 목록
- provider, 상태, 기간, purpose 필터
- 본인확인 상세와 상태 전이 기록
- provider 설정
- 테스트/운영 모드 설정
- 보관 기간 설정

관리자 화면에서도 원문 개인정보는 표시하지 않는다. 해시 일부, provider, purpose, 상태, 시각 중심으로 표시한다.

## 1차 구현 범위

1차 구현은 회원 계정 본인확인 연결에 집중한다.

권장 조합:

- 사용 모듈: `member`
- 공통 모듈: `identity_verification`
- provider 플러그인: `identity_kcp`

이유:

- NHN KCP 휴대폰 본인확인은 대상 범위가 명확하다.
- 회원 계정과 연결하는 흐름이 가장 기본적이다.
- KG이니시스 통합인증은 간편인증/전자서명/본인확인을 함께 제공하므로 provider 추상화 검증 후 추가하는 편이 안전하다.

1차 포함:

- 본인확인 시도 생성
- provider 설정 화면
- KCP provider prepare/verify
- return/callback 처리
- member 계정에 본인확인 결과 연결
- `privacy-export.php` 제공
- 회원 탈퇴/익명화 연동
- 관리자 조회
- 보관 정리 대상 추가

1차 제외:

- KG이니시스 provider 실제 구현
- 성인 인증 정책
- 개인정보 처리 요청 강제 본인확인
- 금전성 거래 제한
- 원문 개인정보 관리자 표시
- 결과 다운로드

## 2차 구현 범위

2차에서 KG이니시스 통합인증 본인확인을 추가한다.

포함:

- `identity_inicis` 플러그인
- KG이니시스 통합인증 provider 설정
- KG이니시스 prepare/verify
- KCP와 동일한 공통 결과 변환
- provider별 테스트/운영 전환
- 두 provider 중 기본 provider 선택

## 3차 확장 후보

- `privacy` 요청 제출 전 본인확인 요구
- `community` 성인 게시판 접근 확인
- `deposit` 고위험 출금/환불 작업 확인
- purpose별 유효기간 정책
- 관리자 수동 revoke
- 회원 화면에서 본인확인 이력 확인

## 구현 단계

1. `identity_verification` 모듈 골격과 설치 SQL을 만든다.
2. provider 계약 파일 로딩 helper를 만든다.
3. attempt 생성, state/nonce 생성, 만료 처리 helper를 만든다.
4. return/callback path를 추가한다.
5. provider 설정 관리자 화면을 만든다.
6. `identity_kcp` 플러그인을 만든다.
7. KCP 개발가이드 기준으로 prepare/verify를 구현한다.
8. 성공 결과를 공통 result로 변환하고 민감값을 HMAC hash로 저장한다.
9. `member` 모듈에 본인확인 시작/결과 확인 UI를 붙인다.
10. `identity_verification/privacy-export.php`를 추가한다.
11. 회원 탈퇴/익명화 시 본인확인 연결 revoke 또는 익명화 정책을 붙인다.
12. 관리자 조회와 감사 로그를 추가한다.
13. 보관 정리 대상에 실패/만료 attempt와 오래된 result를 포함한다.
14. KCP 테스트 환경에서 성공, 실패, 만료, 중복 callback, 개인정보 사본 제공을 검증한다.
15. `identity_inicis` 플러그인을 같은 계약으로 구현한다.
16. 구현 완료 후 이 계획 문서를 삭제하고 필요한 기준만 유지 문서로 옮긴다.

## 검증 항목

- provider 비활성 상태에서 본인확인 시작이 차단되는가
- state/nonce 불일치 요청이 실패 처리되는가
- 만료된 시도가 성공 처리되지 않는가
- callback이 중복 도착해도 결과가 한 번만 연결되는가
- provider 응답 원문이 로그에 남지 않는가
- CI/DI, 이름, 휴대폰이 원문 저장되지 않는가
- 관리자 화면에서 원문 개인정보가 보이지 않는가
- 회원 계정 연결이 멱등하게 처리되는가
- 개인정보 사본 제공에 본인확인 요약이 포함되는가
- 회원 탈퇴/익명화 시 본인확인 연결이 revoke 또는 익명화되는가
- 보관 정리에서 만료/실패 attempt가 삭제되는가
- HTTPS가 아닌 운영 환경에서 시작을 막거나 경고하는가
