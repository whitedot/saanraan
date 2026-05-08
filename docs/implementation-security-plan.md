# 구현 방향 및 보안 계획

Toycore는 절차형 PHP, 바닐라 JavaScript, plain CSS를 기반으로 하는 웹 솔루션 코어입니다. 목표는 복잡한 프레임워크 없이도 저가형 웹호스팅에서 설치하고 운영할 수 있는 구조를 제공하는 것입니다.

이 문서는 Toycore의 구현 방향, 모듈 경계, 보안 이슈, 운영 관점의 우선순위를 정리합니다.

## 기준

- 프로젝트 접두사는 `toy_`를 사용한다.
- 회원 인증은 기본 제공하되 `member` 모듈로 취급한다.
- `member`와 `admin`은 코어에 내장하지 않지만 기본 설치 필수 모듈로 취급한다.
- 선택 모듈은 현재 코드에 포함되거나 설치 후 배치될 수 있지만, 인증과 관리자 진입의 필수 조건으로 보지 않는다.
- 코어는 모든 기능을 직접 포함하지 않고, 모듈을 등록하고 실행하는 최소 기반을 담당한다.
- 보안 기준은 OWASP ASVS와 OWASP Cheat Sheet Series를 참고한다.
- 저가형 웹호스팅 호환성을 위해 서버 데몬, 큐 워커, 복잡한 배포 파이프라인에 의존하지 않는다.

참고 기준:

- [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [European Commission GDPR principles](https://commission.europa.eu/law/law-topic/data-protection/rules-business-and-organisations/principles-gdpr_en)
- [European Data Protection Board: GDPR FAQ](https://www.edpb.europa.eu/sme-data-protection-guide/faq-frequently-asked-questions/answer/what-gdpr_en)
- [European Data Protection Board: individuals' rights](https://www.edpb.europa.eu/sme-data-protection-guide/respect-individuals-rights_en)

## 전체 구조

Toycore는 다음 계층으로 나눕니다.

```text
web entry
-> bootstrap
-> request branch / path matching
-> explicit module include
-> procedural action files
-> view/template
-> response
```

### 루트 진입점

웹에서 직접 접근 가능한 진입점은 루트 `index.php`입니다. 저가형 공유호스팅에서 별도 DocumentRoot 변경이 어렵다는 전제를 우선해, 도메인이 프로젝트 루트를 가리키면 `index.php`가 바로 실행되는 구조로 둡니다.

단, 코어는 Apache `.htaccess` 같은 특정 서버용 보호 파일을 기본 배포 산출물로 제공하지 않습니다. `config/`, `storage/`, `database/`, `core/`, `modules/`, `docs/`, `examples/`, `.git/`, `.tools/`, `AGENTS.md`, `README.md`가 URL로 직접 열리지 않도록 하는 책임은 배포 환경의 접근 차단 규칙에 둡니다. 이 조건을 만족하지 못하는 환경에서는 운영 설치를 진행하지 않는 것이 기본 원칙입니다.

배포 전 보호 기준은 [배포 보호 기준](deployment-protection.md)에 따릅니다.

최초 진입점은 코어가 책임집니다. `page` 같은 특정 모듈이 `/` 요청 자체를 항상 소유하지 않습니다. 홈 화면의 콘텐츠 제공자는 사이트 설정으로 결정하고, 설정된 모듈이 활성 상태일 때만 해당 모듈 action 파일을 include합니다.

### `bootstrap`

공통 초기화 영역입니다.

- 경로 상수 정의
- 설정 로드
- DB 연결
- 세션 초기화
- 에러 처리 정책 적용
- 활성 모듈 로드

### `core`

코어는 기능 구현보다 실행 기반을 담당합니다.

- 설정 조회
- locale 결정과 번역 문자열 조회
- 기본 SEO 메타 출력 기반
- DB 헬퍼
- 요청/응답 헬퍼
- 요청 분기와 path 매핑
- 모듈 등록과 활성화 확인
- 홈 제공자 설정 확인
- 선택적 파일 캐시 조회
- 인증 상태 조회 인터페이스
- CSRF, escape, redirect 같은 보안 헬퍼

### `modules`

기능별 구현 영역입니다. 회원 인증도 `modules/member`에 둡니다.

모듈은 다음 구성을 기본으로 합니다.

```text
modules/{module_key}/
- module.php
- paths.php
- actions/
- views/
- install.sql
```

## 모듈 처리 방식

Toycore의 모듈 시스템은 라라벨 같은 프레임워크의 패키지, 서비스 프로바이더, 마이그레이션, 이벤트 컨테이너 방식을 따르지 않습니다.

모듈은 다음 세 가지 정보로만 연결합니다.

- 파일 위치 약속
- DB에 저장된 설치/활성 상태
- 절차형 PHP 함수와 action 파일

`module.php`는 모듈 메타 정보와 기본 설정 후보를 제공하는 명시적 정보 파일입니다. 요청 처리 자동 실행, path 등록, 숨은 부팅 처리를 담당하지 않습니다.

`actions/`는 입력 검증, 권한 확인, DB 처리, redirect, view 변수 준비를 담당합니다. `views/`는 action 파일이 준비한 값을 escape해서 출력하는 HTML/PHP 파일이며, DB 변경이나 권한 판단의 최종 결정을 맡지 않습니다.

### 설치

모듈 설치는 모듈이 제공하는 `install.sql`과 `module.php` 메타데이터를 기준으로 처리합니다.

```text
modules/{module_key}/install.sql
modules/{module_key}/module.php
```

설치 과정:

```text
1. install.sql 실행
2. toy_modules에 module_key 등록
3. toy_modules.status에 활성 상태 저장
4. toy_module_settings에 기본 설정 저장
```

설치 과정은 웹 설치 화면이나 관리자 화면에서 실행할 수 있어야 하며, CLI 도구를 필수로 요구하지 않습니다.
별도 PHP 설치 스크립트 자동 실행 흐름은 현재 기본 설치 모델에 포함하지 않습니다.

### 활성화

활성화는 초기 구현에서 `toy_modules.status` 값을 기준으로 판단합니다.

```text
enabled  : 해당 사이트에서 모듈 사용
disabled : 해당 사이트에서 모듈 미사용
```

요청 처리 시 코어는 현재 사이트의 활성 모듈 목록만 읽고, 활성 모듈의 path 매핑만 확인합니다. 현재 요청과 일치하는 action 파일만 검증 후 include합니다.

```php
$modules = toy_get_enabled_modules($siteId);

foreach ($modules as $module) {
    $paths = TOY_MODULES_PATH . '/' . $module['module_key'] . '/paths.php';

    if (is_file($paths)) {
        $module_paths = require $paths;
    }
}
```

이 코드는 개념 예시입니다. 실제 구현에서는 `module_key`와 action 파일 상대 경로를 검증한 뒤, 현재 요청에 필요한 action 파일만 include합니다. `paths.php`는 전역 path 등록을 수행하지 않고 배열만 반환합니다.

### 명시적으로 피할 방식

Toycore는 다음 방식을 기본 구현에 도입하지 않습니다.

- Composer 패키지 자동 발견
- Laravel Service Provider 유사 구조
- Artisan 같은 CLI 필수 설치/운영 명령
- ORM 모델 중심 설계
- PHP 클래스 기반 마이그레이션 필수화
- 의존성 주입 컨테이너
- 이벤트 버스 기반의 숨은 실행 흐름
- annotation, attribute, reflection 기반 자동 path 매핑
- `toy_route()` 같은 전역 path 등록 API 중심 구조

필요한 규칙은 코드 생성이나 자동 탐색보다 명시적인 파일과 DB 상태로 표현합니다.

## 다국어 처리 방향

Toycore는 처음부터 다국어 구조를 고려합니다. 다만 복잡한 번역 플랫폼을 전제로 하지 않고, 파일 기반 번역과 DB 기반 설정을 함께 사용합니다.

코어 다국어 기능은 UI 문구, locale 결정, fallback, 회원 선호 언어까지로 제한합니다. 게시글, 페이지, 상품 같은 사용자 콘텐츠의 다국어화는 각 모듈의 책임으로 둡니다.

### Locale 결정 순서

요청에서 사용할 locale은 다음 순서로 결정합니다.

```text
1. 명시적 URL 또는 query 값
2. 로그인 회원의 저장된 locale
3. 사이트 기본 locale
4. 브라우저 Accept-Language
5. 시스템 기본값
```

초기 구현에서는 사이트 기본 locale과 회원 locale을 우선 지원하고, URL 기반 다국어 요청 분기는 후순위로 둡니다.

### 번역 문자열

코어와 모듈은 각자 번역 파일을 가질 수 있습니다.

```text
lang/{locale}/core.php
modules/{module_key}/lang/{locale}.php
```

번역은 명시적인 key-value 배열로 관리합니다.

```php
return [
    'login.title' => '로그인',
    'login.submit' => '로그인',
];
```

DB에 저장되는 사용자 콘텐츠의 다국어화는 별도 모듈 책임으로 둡니다. 코어는 사이트 기본 locale, 지원 locale 목록, 회원 선호 locale까지만 관리합니다.

코어는 `toy_page_translations` 같은 콘텐츠 번역 테이블을 강제하지 않습니다. 필요한 모듈이 자신의 데이터 구조에 맞게 번역 테이블을 설계합니다.

## SEO 대응 방향

Toycore의 코어는 SEO 출력 기반만 제공합니다. 콘텐츠별 SEO 판단은 각 모듈의 책임입니다.

코어 담당:

- 사이트 기본 title/description 설정
- canonical URL helper
- robots 기본값
- `<head>` 영역에 SEO 값을 출력할 수 있는 공통 변수 규칙
- Open Graph 출력 슬롯
- 로그인, 관리자, 오류 페이지의 `noindex` 기본 처리

모듈 담당:

- 콘텐츠별 title
- 콘텐츠별 description
- 콘텐츠별 canonical
- Open Graph 값
- 구조화 데이터
- sitemap에 포함할 URL 목록

코어는 모든 콘텐츠 테이블에 SEO 컬럼을 강제하지 않습니다. `page`, `board`, `shop` 같은 모듈이 자신에게 맞는 SEO 필드와 URL 정책을 설계합니다. Open Graph도 코어는 출력 슬롯만 제공하고, 실제 값은 모듈이 결정합니다.

## 진입점 및 홈 요청 분기 방향

루트 `index.php`는 코어 진입점입니다. 홈 화면은 코어가 직접 고정하지 않고 사이트 설정으로 결정합니다.

```text
home.module = page
home.action = home
home.value = home
```

요청 흐름:

```text
GET /
-> 코어가 사이트 설정 확인
-> home.module 확인
-> 해당 모듈 활성 상태 확인
-> 모듈 action 파일 include
```

`page` 모듈은 홈 제공자가 될 수 있지만 필수 의존성은 아닙니다. `page` 모듈이 없거나 비활성 상태여도 코어는 기본 홈, 설치 안내, 점검 화면, 오류 화면을 처리할 수 있어야 합니다.

## 캐시 방향

초기 코어는 캐시 없이 동작해야 합니다. 캐시는 필수 의존성이 아니라 성능 최적화 계층으로 둡니다.

1차 캐시 대상:

- 사이트 설정
- 활성 모듈 목록
- 번역 배열

HTML 전체 캐시는 코어 기본 기능으로 넣지 않습니다. 로그인 상태, CSRF 토큰, 권한, 개인정보, locale에 따라 화면이 달라질 수 있기 때문입니다.

공개 페이지 HTML 캐시가 필요하면 `page`, `board`, `shop` 같은 공개 콘텐츠 모듈이 선택 기능으로 구현합니다. 이 경우에도 locale, path, 콘텐츠 수정 시점, 공개 여부를 캐시 키와 무효화 정책에 반영해야 합니다.

캐시는 파일 기반부터 시작하며 Redis, Memcached, 상시 실행 워커를 기본 의존성으로 두지 않습니다.

### 다국어 설계 원칙

- 날짜, 시간은 저장 시 UTC 또는 명확한 timezone 기준을 사용하고 출력 시 locale/timezone을 반영
- 번역 키는 화면 문구에 직접 의존하지 않고 안정적인 key로 작성
- 관리자 화면에서 기본 locale을 설정할 수 있게 설계
- 없는 번역은 기본 locale로 fallback
- URL 구조 다국어화는 `/ko/...`, `/en/...` 같은 방식으로 확장 가능하게 남김

## GDPR 및 개인정보 처리 방향

Toycore는 GDPR 대응을 법률 자문 없이 완성할 수 있다고 가정하지 않습니다. 대신 GDPR 적용 가능성이 있는 사이트가 최소한의 구조적 대응을 할 수 있도록 데이터 최소화, 동의 기록, 권리 요청, 보관 기간, 삭제/익명화 흐름을 코어 설계에 포함합니다.

GDPR 적용 여부는 운영 주체, 대상 사용자, 서비스 지역에 따라 달라집니다. EDPB는 GDPR이 EEA 내 조직뿐 아니라 EU 내 개인을 대상으로 하는 조직에도 적용될 수 있다고 설명합니다.

GDPR 관련 기능은 다음처럼 나눕니다.

- `member` 모듈: 동의 기록, 회원 탈퇴, 세션 폐기, 계정 비활성화/익명화
- 코어: 개인정보 처리에 필요한 공통 설정과 보안 helper
- `admin` 또는 `privacy` 모듈: 개인정보 요청 처리, 내보내기, 보관 기간 정리

### 개인정보 처리 원칙

- 수집 목적을 명확히 정하고 필요한 데이터만 저장
- 필수 데이터와 선택 데이터를 구분
- 동의가 필요한 항목은 동의 시점, 버전, IP, User-Agent를 기록
- 개인정보 조회, 정정, 삭제, 처리 제한, 이동권 요청을 처리할 수 있는 기록 구조 마련
- 탈퇴와 삭제는 즉시 물리 삭제만 전제로 하지 않고, 법적 보관 필요 여부에 따라 익명화와 보관을 구분
- 로그와 백업의 보관 기간을 문서화
- 관리자 화면에서 개인정보 원문 노출을 최소화

### 회원 데이터 분류

회원 모듈은 데이터를 다음처럼 분류합니다.

| 분류 | 예시 | 처리 방향 |
| --- | --- | --- |
| 계정 필수 | account_identifier_hash, email_hash, password_hash | 인증 목적에 한정 |
| 연락처 | email | 메일 발송과 사용자 안내 목적 |
| 프로필 선택 | nickname, phone, birth_date, avatar_path | 선택 수집, 수정/삭제 가능 |
| 보안 로그 | login IP, User-Agent, auth result | 보관 기간 설정 |
| 동의 기록 | privacy policy, terms, marketing | 버전과 시점 보관 |
| 운영 로그 | 관리자 변경 이력 | 필요한 범위로 제한 |

### 권리 요청 처리

GDPR 대응을 위해 다음 요청 유형을 기록할 수 있어야 합니다.

```text
access        : 개인정보 열람
rectification : 정정
erasure       : 삭제
restriction   : 처리 제한
portability   : 이동권/내보내기
objection     : 처리 반대
withdrawal    : 동의 철회
```

초기 구현은 자동 처리보다 관리자 검토 흐름을 우선합니다. 요청을 접수하고, 처리 상태와 처리자를 기록하며, 실제 삭제/내보내기는 보수적으로 구현합니다.

### 삭제와 익명화

탈퇴 또는 삭제 요청 시 다음 정책을 구분합니다.

- 인증 계정은 비활성화 후 개인정보 필드를 익명화
- 법적/보안상 필요한 로그는 정해진 기간 동안 최소 정보만 보관
- 세션과 장기 로그인 토큰은 즉시 폐기
- 게시물 같은 사용자 생성 콘텐츠는 모듈 정책에 따라 작성자 익명화 또는 삭제
- 개인정보 요청 이력은 계정 삭제/익명화 이후에도 보존 가능해야 함

### 쿠키와 동의

- 필수 세션 쿠키와 선택 쿠키를 구분
- 분석, 마케팅, 외부 위젯은 기본 비활성으로 시작
- 선택 쿠키를 쓰는 모듈은 동의 상태를 확인한 뒤만 동작
- 동의 문서 버전이 바뀌면 재동의가 필요한지 판단 가능해야 함

## 구현 단계

### 1단계: 설치 가능한 최소 코어

- 디렉터리 구조 확정
- 루트 `index.php` 진입점 구성
- 내부 디렉터리 직접 접근 차단을 설치 전 조건과 점검 항목으로 분리
- `config/config.php` 방식 확정
- PDO 기반 DB 연결
- 공통 응답, redirect, escaping 헬퍼 작성
- 사이트 기본 locale 설정
- `toy_site_settings`, `toy_modules`, `toy_module_settings`, `toy_schema_versions` 설치 SQL 작성

완료 기준:

- 브라우저에서 기본 페이지가 표시됨
- DB 연결 실패와 설정 누락이 명확한 오류로 처리됨
- 활성 모듈 목록을 DB에서 읽을 수 있음
- 기본 locale 기준으로 문구를 출력할 수 있음

### 2단계: MVP 회원 인증 모듈

- `member` 모듈 등록
- 설치 과정의 최초 관리자 계정 생성
- 로그인, 로그아웃
- 비밀번호 해시 저장
- 세션 재생성
- 로그인 실패 기록
- 기본 회원 상태 관리

완료 기준:

- 최초 관리자 계정 생성과 로그인이 동작함
- 비밀번호 원문은 저장되지 않음
- 로그인 성공 시 세션 ID가 재생성됨
- 인증 이벤트가 `toy_member_auth_logs`에 기록됨

### 3단계: MVP 관리자 진입

- 관리자 로그인
- `/admin` 대시보드
- 사이트 기본 설정 조회
- owner/admin/manager role 확인

완료 기준:

- 관리자 권한이 없는 사용자는 관리 화면에 접근할 수 없음
- 관리자 계정은 대시보드에 접근할 수 있음
- 관리자 화면은 기본 `noindex` 처리됨

### 4단계: 운영 기능 확장

- 사이트 기본 설정 수정
- 모듈 활성화/비활성화
- 감사 로그
- 회원 목록과 상태 변경
- 공개 회원가입과 동의 기록
- 개인정보 요청 접수/처리 상태 관리

## 관리자 화면 방향

Toycore는 관리자 화면을 제공합니다. 단, 관리자 화면을 코어에 직접 몰아넣지 않고 `admin` 모듈을 기본 관리 모듈로 둡니다.

코어 담당:

- 관리자 진입 보호
- 인증 상태 확인
- 권한 helper
- CSRF helper
- 관리자 작업 로그 helper
- 관리자 화면 기본 `noindex` 처리

`admin` 모듈 담당:

- 관리자 레이아웃
- 사이트 설정 화면
- 모듈 설치/활성화/비활성화 화면
- 회원 목록과 상태 변경 화면
- 개인정보 요청 접수/처리 화면
- 보관 기간 정리 실행 화면

각 기능 모듈 담당:

- 자기 모듈의 관리자 메뉴
- 자기 모듈의 설정 화면
- 자기 모듈의 콘텐츠 관리 화면

관리자 화면도 절차형 원칙을 따릅니다. `admin` 모듈의 path 매핑은 `paths.php` 배열로 제공하고, 코어가 활성 상태와 권한을 검증한 뒤 action 파일을 include합니다.

### 4단계: 확장 모듈 기반

- 게시판, 페이지 같은 일반 모듈을 추가할 수 있는 규칙 정리
- 모듈별 설정 조회 헬퍼
- 모듈별 view 경로 규칙
- 모듈별 번역 파일 규칙
- 모듈 설치 SQL 실행 규칙
- 모듈별 `extension-points.php` 계약 파일 규칙

완료 기준:

- 새 모듈을 추가해 path와 화면을 붙일 수 있음
- 모듈 비활성화 시 해당 path가 차단됨
- 모듈이 기본 locale 번역 파일을 제공할 수 있음
- 확장 모듈이 활성 모듈의 명시적 확장 지점을 읽어 자기 설정에 사용할 수 있음

## 보안 원칙

### 공개 소스 위협 모델

Toycore는 소스와 구조가 공개되는 오픈소스 프로젝트입니다. 따라서 공격자가 Toycore 사용 여부, 기본 디렉터리 구조, 기본 테이블명, 요청 흐름, 모듈 action 파일 약속을 알고 있다고 가정합니다.

이 전제에서 보안은 은닉이 아니라 검증으로 성립해야 합니다.

- `toy_` table prefix는 프로젝트 네임스페이스이며 보안 장치로 보지 않음
- 관리자 URL, 설치 파일 위치, 모듈 key, 버전 문자열 은닉에 의존하지 않음
- SQL injection은 table prefix 은닉이 아니라 prepared statement와 허용 목록으로 방어
- 관리자와 업데이트 요청은 경로 난독화가 아니라 인증, 권한, CSRF, 설치 상태 확인으로 방어
- 모듈 action은 직접 경로가 알려져도 활성 모듈, 허용된 method/path, action 상대 경로 검증을 통과해야 실행
- 알려진 취약점은 숨기기보다 패치, 릴리스, 업데이트 절차로 대응

기능 추가와 리뷰에서는 "공격자가 이 경로와 구조를 이미 알고 있어도 안전한가"를 기본 질문으로 둡니다.

### 입력값 검증

모든 외부 입력은 신뢰하지 않습니다.

- `$_GET`, `$_POST`, `$_COOKIE`, `$_FILES`, request URI는 모두 검증 대상
- 숫자 ID는 정수 변환 후 범위 확인
- enum 값은 허용 목록으로 검증
- 이메일, URL, 날짜는 전용 검증 함수 사용
- DB 저장 전 검증과 출력 전 escaping을 별도로 수행

### SQL Injection 방지

- 모든 동적 값은 PDO prepared statement로 처리
- 테이블명, 컬럼명, 정렬 방향은 바인딩할 수 없으므로 허용 목록으로만 선택
- 검색 조건 조립은 공통 헬퍼로 제한
- SQL 문자열 연결은 식별자 선택처럼 불가피한 경우에만 허용

### XSS 방지

- HTML 출력은 기본적으로 `htmlspecialchars()` 래퍼를 사용
- 속성값, URL, JavaScript 컨텍스트를 구분해 escape
- 사용자 입력 HTML 허용은 기본 금지
- 관리자 화면도 XSS 방어 대상에 포함
- 에디터나 첨부 HTML이 필요하면 별도 sanitizer 도입 전까지 기능 보류

### CSRF 방지

쿠키 기반 인증을 사용하는 모든 상태 변경 요청은 CSRF 토큰을 요구합니다.

- POST, PUT, PATCH, DELETE 성격의 요청은 토큰 검증
- 토큰은 세션에 저장하고 폼 hidden 필드로 전달
- 로그인 후 세션 재생성과 함께 토큰도 갱신
- SameSite 쿠키 설정을 함께 적용하되, CSRF 토큰을 대체하지 않음

### 인증

- 비밀번호는 PHP `password_hash()`와 `password_verify()` 사용
- 비밀번호 변경, 이메일 변경 같은 민감 작업은 현재 비밀번호 재확인
- 로그인 실패 횟수 제한 또는 지연 처리
- 계정 상태는 `active`, `pending`, `suspended`, `deleted`처럼 명확한 값으로 제한
- 인증 실패 메시지는 계정 존재 여부를 노출하지 않도록 설계

### 세션

- 로그인 성공 시 `session_regenerate_id(true)` 실행
- 세션 쿠키에 `HttpOnly`, `Secure`, `SameSite` 적용
- HTTPS가 아닌 개발 환경과 운영 환경의 쿠키 정책 분리
- 세션 ID를 URL로 받지 않음
- 로그인 세션은 DB에도 hash로 기록하고, 관리자 화면에서 계정별 세션을 폐기할 수 있음
- 장기 로그인 토큰을 구현할 경우 원문 저장 금지, 서버에는 해시만 저장
- 로그아웃 시 서버 세션과 관련 토큰을 함께 폐기

### 권한

인증과 권한을 분리합니다.

- 로그인 여부 확인: 인증
- 특정 기능 수행 가능 여부 확인: 권한
- 관리자 여부는 단순 플래그보다 역할/권한 확장 가능성을 고려
- 요청 진입 시 권한 확인
- 화면에서 버튼을 숨기는 것은 보조 수단이며 서버 검증을 대체하지 않음

### 파일 업로드

코어는 공통 파일 테이블이나 파일 관리자 화면을 만들지 않고 업로드 보안 primitive만 제공합니다. 파일의 의미, 공개 범위, 다운로드 권한, 보존 정책은 업로드를 받는 모듈이 소유합니다.

- 모듈 action은 `toy_upload_validate_file()`로 업로드 오류, 파일 크기, 확장자 allowlist, MIME, 실행 가능한 확장자 차단을 확인
- 업로드 저장 위치는 가능하면 웹에서 직접 접근할 수 없는 위치 사용
- 프로젝트 루트가 웹 접근 루트인 공유호스팅에서는 서버 접근 차단 규칙이 적용된 저장 디렉터리만 사용
- 원본 파일명으로 저장하지 않고 `toy_upload_random_filename()` 같은 서버 생성 이름 사용
- 이동은 `toy_upload_move_uploaded_file()` 또는 검증된 값 기반의 명시적 이동만 사용
- 이미지 업로드는 필요할 때 `toy_upload_reencode_image()`로 재인코딩하고, GD/Imagick이 없거나 실패하면 모듈 정책에 따라 거부
- 비공개 다운로드는 직접 경로 노출 대신 action 파일과 단기 HMAC token을 사용
- 다운로드 응답은 `toy_send_download_headers()`와 `toy_finish_response()`를 통과

### 설정과 비밀값

- DB 비밀번호, 암호화 키, 메일 계정은 Git에 커밋하지 않음
- 설치가 생성하는 실제 설정 파일은 `config/config.php`로 둠
- 예시 파일은 `config/config.sample.php`로 제공하고 실제 비밀값은 Git에 커밋하지 않음
- 운영 모드에서는 에러 상세를 화면에 노출하지 않음
- 설치 화면에서 최초 관리자 계정과 비밀번호를 입력받고, 하드코딩된 기본 계정/비밀번호를 제공하지 않음

### 로그

- 인증 이벤트, 관리자 변경, 설정 변경은 로그 대상
- 비밀번호, 세션 토큰, CSRF 토큰은 로그에 남기지 않음
- IP와 User-Agent는 보안 추적용으로 저장하되 개인정보 보관 정책을 문서화
- 저가형 호스팅을 고려해 로그 테이블이 무한히 커지지 않도록 보관 기간을 둠

### 개인정보 보호

- 개인정보 필드는 목적과 보관 기간을 문서화
- 선택 개인정보는 필수 가입 흐름에서 분리
- 탈퇴, 삭제, 익명화는 회원 모듈의 기본 책임으로 구현
- 개인정보 내보내기는 JSON 또는 CSV 같은 단순 포맷으로 시작
- 관리자 접근 로그와 개인정보 조회 로그를 남김
- 백업에 포함된 개인정보의 보관 기간과 삭제 한계를 문서화

## 주요 리스크

| 리스크 | 영향 | 대응 |
| --- | --- | --- |
| 절차형 코드의 확산 | 유지보수 난이도 증가 | 파일 역할과 헬퍼 경계를 문서화 |
| 모듈과 코어 결합 | 기능 교체 어려움 | 모듈은 등록, path 매핑, 설정 규칙으로 연결 |
| 인증 구현 오류 | 계정 탈취 | OWASP 기준에 맞춘 인증/세션 체크리스트 유지 |
| XSS | 관리자 세션 탈취, 데이터 변조 | 출력 escape 기본화 |
| CSRF | 원치 않는 설정 변경 | 모든 상태 변경 요청에 토큰 검증 |
| 업로드 취약점 | 웹쉘, 저장소 고갈 | 업로드 기능 보수적 도입 |
| 저가형 호스팅 제약 | 백그라운드 작업 제한 | 동기 처리와 주기적 수동 정리 기능 우선 |
| DB 마이그레이션 부재 | 설치/업데이트 오류 | SQL 파일과 버전 테이블 도입 |
| 다국어 후순위 설계 | URL, 설정, 콘텐츠 구조 재작업 | 초기부터 locale 필드와 번역 파일 규칙 도입 |
| GDPR 미고려 | 삭제/동의/열람 요청 처리 불가 | 동의 로그와 권리 요청 테이블 도입 |

## 품질 기준

- 코어 함수는 작고 명확하게 유지
- 전역 상태는 설정, DB 연결, 현재 요청 정도로 제한
- 모든 SQL은 위치를 추적할 수 있게 함수나 파일 단위로 관리
- 화면 출력은 escape가 기본인 helper를 통해 수행
- 모듈은 비활성화 상태에서도 코어 부팅을 깨지 않아야 함
- 설치 SQL은 빈 DB에서 반복 검토 가능해야 함

## 테스트 전략

프레임워크 없는 환경을 고려해 처음부터 복잡한 테스트 도구에 의존하지 않습니다.

- 핵심 보안 헬퍼는 PHP CLI에서 실행 가능한 단위 테스트로 작성
- 설치 SQL은 로컬 MySQL 또는 MariaDB에서 반복 실행 검증
- 로그인, 로그아웃, 설정 변경은 브라우저 수동 체크리스트 유지
- 보안 체크리스트는 기능 추가 시 함께 갱신
- 추후 프로젝트 규모가 커지면 PHPUnit 도입 검토

## 문서화 전략

문서는 구현보다 앞서 최소 기준을 잡고, 구현 결과에 맞춰 계속 갱신합니다.

구현 판단이 갈릴 때는 이 문서보다 [핵심 설계 결정](core-decisions.md)을 우선합니다. 실제 구현 범위는 코드와 README의 현재 구현 범위를 기준으로 확인합니다.

- `README.md`: 프로젝트 소개와 주요 문서 링크
- `AGENTS.md`: 개발 규칙과 네이밍
- `docs/core-decisions.md`: 핵심 설계 결정
- `docs/install-plan.md`: 설치와 초기화 계획
- `docs/update-plan.md`: 업데이트와 스키마 버전 계획
- `docs/runtime-ops-plan.md`: 운영 모드와 에러 처리 계획
- `docs/audit-log-plan.md`: 감사 로그 계획
- `docs/implementation-security-plan.md`: 구현과 보안 계획
- `docs/security-checklist.md`: 기능별 보안 확인 목록
- `docs/module-guide.md`: 모듈 작성 규칙
- `docs/privacy-gdpr-plan.md`: 개인정보와 GDPR 대응 계획
- `docs/i18n-plan.md`: 다국어 처리 계획
- `docs/seo-plan.md`: SEO 대응 계획
- `docs/entry-request-plan.md`: 진입점과 홈 요청 분기 계획
- `docs/cache-plan.md`: 캐시 계획

## 현재 남은 설계 판단

- 멀티사이트 요구가 실제로 생길 경우 스키마 업데이트로 확장할 범위
- 단순 role 이후 permission 테이블이 필요한 조건
- 업로드 기능을 코어에 포함할지 별도 모듈로 둘지
- 다국어 URL prefix와 locale별 SEO 관계를 도입할 조건
- 설정/모듈/번역 파일 캐시를 파일 캐시로 확장할 조건
