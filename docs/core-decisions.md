# 핵심 설계 결정

이 문서는 구현 중 흔들리면 안 되는 Toycore의 핵심 결정을 정리합니다. 다른 문서나 구현 판단이 애매할 때는 이 문서의 결정을 우선합니다.

## 0. 핵심 원칙

Toycore는 기능을 많이 가진 코어보다 읽고 고칠 수 있는 코어를 우선합니다.

구현 중 판단이 갈리면 다음 원칙을 우선합니다.

```text
코어는 똑똑해지지 말 것
흐름은 파일을 열면 보일 것
자동 등록보다 명시적 include
기능보다 경계 우선
처음부터 완성형 CMS를 만들지 말 것
```

구체적 기준:

- 코어는 요청 흐름, 설정 조회, 보안 helper, 최소 출력 기반만 담당
- 모듈은 자기 기능과 콘텐츠 의미를 책임
- 자동 탐색, 자동 등록, 숨은 부팅 흐름은 기본 구현에서 제외
- 다국어, SEO, 캐시, 개인정보 처리는 얇은 기반만 코어에 두고 세부 판단은 모듈로 넘김
- 기능 추가보다 작고 읽히는 흐름과 명확한 모듈 경계를 우선

## 0-1. 코어는 관리 시스템 전체가 되지 않는다

Toycore의 코어는 실행 기반입니다. 관리자 화면과 기본 모듈을 제공하더라도 코어가 사이트 운영 기능 전체를 직접 소유하지 않습니다.

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
- 모듈별 workflow를 대신 처리하는 범용 CMS 관리자

판단 기준:

- 기능이 특정 도메인 단어로 설명된다면 해당 모듈에 둡니다.
- 여러 모듈이 필요하더라도 정책 판단이 들어가면 코어가 아니라 helper 계약이나 별도 모듈을 우선합니다.
- 코어 테이블이나 `member` 테이블은 미래 확장을 예상해 넓히지 않습니다.
- 확장 정보는 모듈이 자기 테이블을 만들고 `account_id` 같은 안정적인 식별자로 연결합니다.
- `admin` 모듈은 공통 운영 화면을 제공하지만, 각 도메인의 관리 화면은 소유 모듈이 제공합니다.
- `admin` 모듈의 공통 레이아웃은 활성 모듈의 `admin-menu.php`를 읽어 메뉴를 노출할 수 있지만, 해당 화면의 action/view와 정책은 소유 모듈에 둡니다. 관리자 메뉴의 자산 분류와 모듈 정렬은 새 계약 파일 대신 `module.php`의 선택적 `admin` 메타데이터를 읽어 처리합니다. 운영자가 조정하는 표시 순서와 숨김 여부는 `admin` 모듈의 저장 오버라이드로만 덮어씁니다.
- 파일 업로드처럼 보안 사고 지점은 공통이지만 파일의 의미, 공개 범위, 다운로드 권한, 보존 정책이 도메인마다 다른 기능은 코어 helper와 모듈 책임으로 나눕니다. 코어는 업로드 검증과 안전한 파일 이동 같은 primitive만 제공하고, 공통 파일 테이블이나 파일 관리자 화면은 필요할 때 선택 모듈이 소유합니다.

## 0-2. 코어 helper는 역할별로 분류해 관리한다

Toycore가 기능을 늘릴수록 코어 helper가 도메인 기능을 흡수하려는 압력이 생긴다. 이를 막기 위해 `core/helpers/*.php`는 다음 분류로 관리한다.

대외 설명 문구는 구현 판단의 근거로 쓰지 않는다. 구현 판단의 기준은 실행 기반, 보안 기준선, 모듈 경계, 공유호스팅 운영 가능성이다.

### 0-2-1. 코어 primitive

코어에 둘 수 있다. 특정 도메인 정책 없이 요청 실행, 보안, 설정, 출력, 설치/업데이트를 가능하게 하는 낮은 층의 기능이다.

현재 해당 파일:

- `core/helpers/runtime.php`: 요청 값, URL, HTTPS/proxy 판단, 세션, CSRF, redirect, config, DB 연결, 시간, 토큰 같은 런타임 기반
- `core/helpers/settings.php`: 사이트 설정, 모듈 활성 상태, 모듈 메타데이터, 계약 파일 로딩, 모듈 호환성 확인
- `core/helpers/output.php`: escape, 번역 로딩, 공개 레이아웃 껍데기, SEO fallback, output slot 호출 기반
- `core/helpers/sql.php`: 정적 SQL 파일 실행, schema version 기록, 업데이트 파일 버전 수집
- `core/helpers/upload.php`: 업로드 파일명 정규화, 확장자/MIME/크기 검증, 안전한 저장 경로 primitive
- `core/helpers/storage.php`: local/S3 저장소에 파일을 put/delete/head 하는 저장소 primitive와 key/reference 검증

이 분류의 helper도 다음 조건을 넘으면 코어에 두지 않는다.

- 특정 모듈 테이블 구조를 알아야 한다.
- 파일의 도메인 의미, 공개 범위, 다운로드 권한, 보존 정책을 판단한다.
- 특정 업무 화면이나 관리자 workflow를 전제로 한다.
- 모듈 간 정책 조정을 자동으로 수행한다.

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

현재 감시 대상:

- `core/helpers/ledger.php`: 포인트, 예치금, 적립금이 공유하는 balance/transaction 패턴을 줄이지만, 금액성 원장이라는 도메인 모델을 전제한다.

유지 조건:

- 테이블명은 호출 모듈이 명시적으로 넘기고 `toy_` allowlist를 통과해야 한다.
- 음수 허용, 거래 유형, 사유, 참조 의미, 관리자 권한, 환불/취소/만료 정책은 호출 모듈이 책임진다.
- helper는 원자적 balance update와 transaction insert 같은 좁은 DB primitive에 머문다.
- 원장 조회 UI, 정산, 만료, 지급 정책, 통계, 외부 결제 연동은 코어에 넣지 않는다.

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

## 0-3. 공개 소스와 알려진 구조를 전제로 방어한다

Toycore는 오픈소스 프로젝트입니다. 운영 사이트가 Toycore를 사용한다는 사실, 기본 디렉터리 구조, 기본 테이블명, 요청 흐름, 모듈 파일 약속이 공격자에게 알려져 있다고 가정합니다.

기본 원칙:

- 보안은 숨겨진 파일명, 추측하기 어려운 관리자 URL, 비공개 테이블 prefix에 의존하지 않습니다.
- `toy_` 테이블 prefix는 프로젝트 네임스페이스이며 보안 장치가 아닙니다.
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

Toycore는 숨은 dispatcher, service provider, annotation, reflection, 자동 요청 분기를 사용하지 않습니다.

기본 흐름:

```text
index.php
-> path와 method 확인
-> 사이트 설정과 활성 모듈 확인
-> 허용된 모듈/action 파일인지 검증
-> 명시적 include
```

요청 분기는 다음 방식을 피합니다.

- `toy_route()` 같은 전역 path 등록 API 중심 구조
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

Toycore는 비즈니스 도메인을 소유하지 않습니다. 대신 설치, 회원 인증, 관리자 권한, 감사 로그, 개인정보 사본 제공, 업데이트처럼 운영 중 사고가 잦은 기준선은 helper, 정적 검사, dispatch contract 세 층으로 받칩니다.

기본 구분:

```text
call-site contract: 필요한 helper가 요청 흐름에서 호출되었는지 확인
semantic contract: 어떤 대상에 어떤 작업을 허용할지 판단
```

코어는 call-site contract를 강화합니다. `POST` action의 `toy_require_csrf()`, 관리자 action의 `toy_member_require_login()`과 `toy_admin_require_role()` 호출 누락은 정적 검사와 dispatch contract에서 잡습니다. 하지만 권한 배열에 어떤 role을 넣을지, 소유자만 수정 가능한 대상인지, 도메인 상태 전이가 올바른지는 모듈 action과 helper가 명시적으로 책임집니다.

action 파일은 응답 종료를 `toy_redirect()`, `toy_render_error()`, `toy_finish_response()`로 통과시킵니다. 직접 `exit`/`die` 호출이나 `header('Location: ...')` 직접 호출은 요청 contract를 우회하므로 사용하지 않습니다.

## 1-2. DB 접근은 PDO prepared statement를 기본으로 한다

Toycore는 절차형 PHP와 직접 include 구조를 유지하므로 DB 접근 규칙을 코드 작성자가 바로 확인할 수 있어야 합니다.

기본 원칙:

- DB 연결은 코어가 만든 `PDO` 인스턴스를 action/helper 함수에 명시적으로 전달합니다.
- 사용자 입력, 요청 값, 설정 값, 토큰, IP, 정렬 조건에서 파생된 값은 SQL 문자열에 직접 이어 붙이지 않습니다.
- 동적 값은 `PDO::prepare()`와 named placeholder로 바인딩합니다.
- `PDO::query()`는 외부 값이 전혀 섞이지 않는 고정 SQL에만 사용합니다.
- `PDO::exec()`는 설치/업데이트 SQL 파일처럼 배포자가 제공한 정적 SQL 실행에만 사용합니다.
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

## 5. GDPR 기능은 최소 기반과 확장 기능을 나눈다

GDPR 대응을 모두 코어에 넣지 않습니다. 하지만 회원가입, 동의 기록, 삭제/익명화 같은 최소 기반은 초기 구조에서 빠지면 안 됩니다.

기본 방향:

- `member` 모듈: 동의 기록, 회원 탈퇴, 세션 폐기, 계정 비활성화/익명화, 회원 목록 관리
- 코어: 개인정보 처리에 필요한 공통 설정과 보안 helper
- `privacy` 모듈: 개인정보 처리 요청 접수/검토, 개인정보 사본 제공 조정, 모듈별 `privacy-export.php` 수집
- `admin` 모듈: 개인정보 화면을 소유하지 않고 관리자 레이아웃/권한/감사 로그 기반을 제공

자동화보다 기록과 관리자 검토 흐름을 우선합니다.

## 6. 관리자 화면은 제공하되 코어에 직접 넣지 않는다

Toycore는 운영 가능한 웹 솔루션 코어이므로 관리자 화면을 제공해야 합니다.

다만 관리자 화면 전체를 코어에 직접 넣지 않습니다. 코어는 관리자 기능이 동작할 수 있는 최소 기반만 제공합니다.

기본 방향:

- 코어: 관리자 진입 보호, 인증 상태 확인, 권한 helper, CSRF, 로그 helper
- `admin` 모듈: 관리자 레이아웃, 설정 화면, 모듈 관리, 권한/감사 로그/업데이트 관리
- `member` 모듈: 회원 목록, 회원 설정, 회원 그룹 관리
- `privacy` 모듈: 개인정보 처리 요청 관리
- 각 모듈: 자기 도메인의 관리 화면과 action 파일 제공

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

## 8. member와 admin은 기본 설치 필수 모듈이다

`member`와 `admin`은 코어에 내장하지 않습니다. 하지만 Toycore의 기본 배포에서는 필수 기본 모듈로 포함하고, 기본 설치 과정에서 함께 설치합니다.

이유:

- 최초 관리자 계정 생성을 위해 계정/비밀번호/인증 기반이 필요함
- 관리자 화면은 계정 기반 권한 확인이 필요함
- 코어가 자체 계정 체계를 갖기 시작하면 `member` 모듈 경계가 깨짐

최소 설치 단위:

```text
core + member + admin
```

역할:

- 코어: 설치 흐름, 설정, DB 연결, 모듈 등록, 보안 helper
- `member` 모듈: 계정 생성, 비밀번호 hash, 로그인 기반
- `admin` 모듈: 관리자 화면, 관리자 권한, 관리 기능

`seo`, `site_menu`, `banner`, `popup_layer`, `point`, `deposit`, `reward`, `notification` 모듈은 현재 코드에 포함되거나 설치 후 배치될 수 있지만, 인증과 관리자 진입에 필요한 필수 모듈은 아닙니다. 설치 화면에서 선택한 모듈만 설치 SQL을 실행하고 `toy_modules`에 등록합니다. 선택하지 않은 코드 모듈은 `/admin/modules`에서 나중에 설치할 수 있습니다.

sitemap 같은 SEO 운영 기능은 코어가 아니라 `seo` 모듈 책임으로 두고, 팝업 정책과 대상 규칙은 `popup_layer` 모듈 책임으로 둡니다. 포인트, 예치금, 적립금 같은 회원 연계 도메인도 코어나 `member` 테이블을 넓히지 않고 각 모듈이 `account_id` 기반 확장 테이블로 소유합니다.

설치 순서:

```text
1. 코어 테이블 생성
2. member 모듈 설치
3. admin 모듈 설치
4. 배포본에 포함되어 있고 선택한 모듈 설치
5. 기본 사이트 설정 저장
6. 필수 모듈과 선택한 모듈 등록 및 활성화
7. 스키마 버전 기록
8. member 계정으로 최초 관리자 계정 생성
9. admin 권한 부여
10. 설치 완료
```

## 9. 모듈 계약 버전은 모듈 호환성 기준이다

`core/version.php`의 `TOY_MODULE_CONTRACT_VERSION`은 `modules/{module_key}` 아래에 현재 배치된 모듈 폴더가 코어와 맞춰야 하는 모듈 계약 버전입니다. 이 값은 별도 모듈 저장소 운영을 전제로 한 원격 배포 버전이 아니라, Git checkout, 릴리스 zip, FTP/SFTP 업로드처럼 파일 배치 방식이 달라도 코어가 읽을 수 있는 모듈 구조인지 확인하는 호환성 기준입니다.

이 값은 코어 버전과 별개로 관리합니다. 코어 자체 버그 수정이나 문서 변경은 코어 버전을 바꿀 수 있지만, 모듈의 구조나 동작 계약이 그대로라면 모듈 계약 버전은 유지합니다.

모듈의 `toycore.min_version`은 현재 `TOY_CORE_VERSION`이 충족해야 하는 최소 코어 버전입니다. 형식만 맞으면 되는 표시값이 아니라, 설치와 활성화 전에 실제 비교하는 요구사항입니다.

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
1. core/version.php의 TOY_MODULE_CONTRACT_VERSION
2. docs/module-guide.md의 모듈 메타데이터 예시와 설명
3. 배포 대상 모듈 폴더의 module.php toycore.module_contract
```

계약 버전이 올라가면 배포 대상 모듈 폴더의 `module.php`에 있는 `toycore.module_contract`를 새 계약에 맞게 갱신합니다.

계약 버전이나 `toycore.min_version` 요구사항이 맞지 않는 모듈은 설치 또는 활성화할 수 없습니다. 이미 DB에 `enabled`로 등록된 모듈이라도 코드의 메타데이터/계약이 현재 코어와 맞지 않으면 `paths.php`, `admin-menu.php`, `output-slots.php` 같은 계약 파일 로딩 대상에서 제외하며, 관리자 모듈 화면에 호환성 오류를 표시합니다.

계약 파일은 모든 모듈에 전부 필요한 필수 파일이 아닙니다. 모듈이 URL을 노출하면 `paths.php`, 관리자 메뉴를 노출하면 `admin-menu.php`, 출력 슬롯을 렌더링하면 `output-slots.php`처럼 기능별로 필요합니다. 다만 계약 파일이 실제로 존재하면 `module.php`의 `contracts.provides`에 반드시 선언해야 하며, `contracts.provides`에 선언한 파일은 실제 모듈 폴더에 있어야 합니다. 다른 모듈이 `requires.contracts`로 특정 계약 파일을 요구할 때도 대상 모듈의 파일 존재와 `contracts.provides` 선언을 함께 확인합니다.

## 10. 로그인 식별자는 원문보다 hash 조회를 우선한다

회원 계정은 최소 정보를 저장합니다. 로그인 식별자는 원문 컬럼을 기준으로 조회하지 않고, 정규화한 값의 hash를 기준으로 조회합니다.

기본 방향:

- `member` 모듈은 이메일 로그인과 별도 아이디 로그인을 모두 지원
- 로그인 방식은 관리자 설정으로 선택
- 조회와 중복 검사는 `account_identifier_hash`와 `email_hash` 사용
- 별도 로그인 아이디 원문은 기본 저장 대상에서 제외
- 이메일 원문은 메일 발송과 사용자 안내 목적에 한해 저장 가능
- hash는 설정 파일의 `app_key`를 사용하는 HMAC 방식 우선

회원 기본 테이블에는 `site_id`를 넣지 않습니다. 단일 사이트 운영에서는 모든 회원이 같은 사이트 설정을 사용하므로 `site_id`가 실질적인 의미를 갖기 어렵고, 로그인 조회와 모듈 확장 쿼리를 불필요하게 복잡하게 만듭니다. 멀티사이트를 실제 기능으로 제공한다면 그때 회원/관리자/모듈 데이터에 `site_id`를 추가하는 스키마 업데이트를 설계합니다.

## 11. 첫 구현은 단일 사이트 기준으로 줄인다

Toycore의 첫 구현은 단일 사이트 운영을 기준으로 합니다. 멀티사이트, 사이트별 모듈 활성화, 사이트별 설정 분리, 사이트별 locale 관리는 초기 코어 범위에 넣지 않습니다.

초기 코어 테이블은 다음처럼 단순하게 시작합니다.

```text
toy_site_settings
toy_modules
toy_module_settings
toy_schema_versions
```

초기 구현에서 제외합니다.

```text
toy_site_locales
멀티사이트용 연결 테이블
site_id가 붙은 회원/관리자/감사/개인정보 테이블
```

사이트 이름, base URL, timezone, default locale, 운영 상태는 별도 `toy_sites` 테이블이 아니라 `toy_site_settings`의 필수 키로 저장합니다. 단일 사이트에서 항상 1건만 존재하는 사이트 레코드는 설명 비용이 크므로 두지 않습니다.

모듈 활성화 상태는 `toy_modules.status`로 관리합니다. 단일 사이트 기준에서는 사이트별 모듈 연결 테이블을 두지 않습니다.

## 12. 관리자 권한은 단순 role로 시작한다

초기 관리자 권한은 permission 테이블까지 만들지 않습니다. `owner`, `admin`, `manager` 같은 role key를 계정에 직접 연결하고, role별 허용 작업은 `admin` 모듈 helper의 명시적 배열로 판단합니다.

초기 테이블:

```text
toy_admin_account_roles
```

후순위:

```text
toy_admin_roles
toy_admin_permissions
toy_admin_role_permissions
```

권한 편집 UI와 세밀한 permission 관리는 운영 요구가 확인된 뒤 추가합니다.

## 13. MVP는 설치와 관리자 진입까지만 증명한다

첫 구현은 다음 흐름만 끝까지 동작시키는 것을 목표로 합니다.

```text
1. index.php 설치 상태 분기
2. config/config.php 생성
3. app_key 생성
4. DB 연결
5. 최소 코어 테이블 설치
6. member/admin 모듈 설치
7. 최초 관리자 계정 생성
8. 로그인/로그아웃
9. /admin 대시보드 접근 확인
```

MVP 코어 테이블:

```text
toy_site_settings
toy_modules
toy_module_settings
toy_schema_versions
```

MVP member 테이블:

```text
toy_member_accounts
toy_member_auth_logs
```

MVP admin 테이블:

```text
toy_admin_account_roles
```

초기 계획에서는 후속 단계로 두었지만, 현재 1차 구현에는 다음 운영 기능이 포함되었습니다.

```text
공개 회원가입
회원 프로필
DB 세션
비밀번호 재설정
이메일 인증
회원 동의 기록
회원 탈퇴/익명화
감사 로그
개인정보 처리 요청 관리
회원 목록 관리
업데이트 실행 UI
SEO 관리자 설정
팝업레이어 관리
포인트 모듈 원장 관리
예치금 모듈 원장 관리
적립금 모듈 원장 관리
관리자 모듈 설치 화면
모듈 확장 지점 계약
```

다음 기능은 여전히 후속 단계로 둡니다.

```text
장기 로그인
설정/모듈 파일 캐시
번역 병합 캐시
```

설정 조회 helper의 요청 단위 메모리 캐시는 현재 구현에 포함됩니다. 파일 캐시는 `storage/cache`의 웹 직접 접근 차단 기준을 운영 환경별로 확인한 뒤 선택 적용합니다.

문서에 후속 기능의 설계가 있더라도, 실제 `paths.php`와 디렉터리에는 구현된 action만 둡니다.

## 14. 모듈 간 영향은 계약 파일로 연결한다

Toycore는 숨은 event bus, 자동 boot hook, 서비스 프로바이더로 모듈 간 영향을 연결하지 않습니다.

모듈 간 연결이 필요하면 다음 순서를 따릅니다.

```text
1. 모듈은 extension-points.php로 외부 확장이 붙을 수 있는 위치를 선언한다.
2. 확장 모듈은 관리자 설정 시점에 활성 모듈의 extension-points.php를 읽는다.
3. 확장 모듈은 자기 정책에 맞는 지점만 선택 가능하게 한다.
4. 실제 화면 처리 위치에서는 소유 모듈이 공통 출력 슬롯 helper를 명시적으로 호출한다.
```

예:

```text
popup_layer 모듈 -> 관리자 설정 시점에 활성 모듈의 extension-points.php를 읽어 노출 대상 목록 구성
member 모듈 -> 로그인/회원가입 화면에서 toy_render_output_slot()를 명시 호출
```

사용자 요청에서는 `extension-points.php`를 읽지 않습니다. 사용자 요청은 화면에서 전달한 `module_key`, `point_key`, `slot_key`, `subject_id`로 저장된 확장 모듈 규칙만 조회합니다.

코어는 계약 파일 위치를 안전하게 찾는 helper와 출력 슬롯 호출 helper까지만 제공합니다. 계약 값의 의미, 검증, 정책은 소비 모듈이 책임집니다.

선택 UI 깊이는 기본적으로 다음 4단계를 최대치로 봅니다.

```text
module -> point -> slot -> subject
```

팝업레이어도 배너와 같이 선언된 `content` slot을 읽어 `module -> point -> slot -> subject` 범위에서 대상을 선택합니다. 화면 소유 모듈은 실제 출력 위치에서 `slot_key`를 포함해 `toy_render_output_slot()`을 명시 호출하고, 팝업레이어 모듈은 저장된 대상 규칙에 맞는 HTML을 해당 content slot으로 반환합니다. 5단계 이상이 필요하면 단계를 늘리지 않고 filters/options로 분리합니다.

모듈과 플러그인은 같은 설치/활성화 테이블을 사용할 수 있지만, 개념은 구분합니다.

```text
module = 자기 도메인과 정책을 소유하는 확장
plugin = 특정 모듈이나 계약 파일에 붙어 동작하는 확장
```

공식 선택 모듈은 비활성 상태에서도 코어와 다른 모듈이 동작해야 합니다. 다른 모듈이 공식 선택 모듈을 활용하더라도 기본 실행에 필수 의존으로 만들지 않고, 안정 식별자 기반의 단방향 참조나 명시적 계약 파일을 우선합니다.

## 15. 모듈 설정은 전용 화면을 우선한다

운영자가 자주 바꾸거나 동작에 직접 영향을 주는 설정은 범용 key/value 편집보다 모듈 전용 관리자 화면을 우선합니다.

기본 방향:

- 범용 `/admin/modules` 설정 편집은 비상용 또는 낮은 수준의 관리 도구로 둔다.
- 모듈은 자기 설정의 의미, 단위, 허용 범위가 드러나는 전용 화면을 최대한 제공한다.
- 설정 저장은 POST, CSRF, 서버 측 타입/범위 검증을 적용한다.
- 설정 변경은 감사 로그에 남긴다.
- 도메인 설정의 의미와 정책은 해당 모듈이 책임진다.

예:

```text
member -> /admin/member-settings
seo -> /admin/seo
popup_layer -> /admin/popup-layers
```

## 16. 공개 레이아웃은 전역 껍데기만 담당한다

공개 화면의 전역 레이아웃 파일은 `layouts/public/{layout_key}/` 아래에 둡니다. 이 파일은 `<html>`, `<head>`, 공통 header/footer, 공통 메뉴, 전역 output slot처럼 사이트 전체 껍데기만 담당합니다.

모듈별 화면 구조와 도메인 표시 방식은 각 모듈 안에 둡니다.

```text
layouts/public/basic/layout.php
modules/community/themes/basic/home.php
modules/community/skins/basic/list.php
```

DB에는 파일 경로를 저장하지 않고 `public_layout_key`, `theme_key`, `skin_key` 같은 key만 저장합니다. 실제 파일 경로는 코드의 allowlist helper가 결정하고, 알 수 없는 key는 `basic`으로 fallback합니다.

페이지나 URL마다 레이아웃을 고르는 규칙은 만들지 않습니다. 전역 public 레이아웃은 사이트 단위로 하나를 선택하고, 모듈별 theme/skin 선택은 해당 모듈 책임으로 제한합니다.
