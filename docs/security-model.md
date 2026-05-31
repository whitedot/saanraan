# Saanraan 보안 모델

산란은 비즈니스 도메인을 소유하지 않는 절차형 PHP 솔루션 베이스다. 대신 설치, 회원 인증, 관리자 권한, 감사 로그, 개인정보 사본 제공, 업데이트 같은 운영 도메인을 코어 기준선으로 제공하고, helper, 정적 검사, dispatch contract 세 층으로 그 기준선을 받친다.

이 문서는 모듈 작성자가 산란에서 무엇을 제공받고, 무엇을 직접 책임져야 하는지 구분하기 위한 기준이다.

## 참고 기준

산란의 최종 설계 판단은 [핵심 설계 결정](core-decisions.md)을 우선하지만, 보안 세부 기준은 다음 공개 기준과 충돌하지 않는 방향으로 검토한다.

- [OWASP ASVS](https://owasp.org/www-project-application-security-verification-standard/)
- [OWASP Authentication Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP CSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [European Commission GDPR principles](https://commission.europa.eu/law/law-topic/data-protection/rules-business-and-organisations/principles-gdpr_en)
- [European Data Protection Board: GDPR FAQ](https://www.edpb.europa.eu/sme-data-protection-guide/faq-frequently-asked-questions/answer/what-gdpr_en)
- [European Data Protection Board: individuals' rights](https://www.edpb.europa.eu/sme-data-protection-guide/respect-individuals-rights_en)

## 1. 작은 약속을 지킨다

산란의 보안 모델은 자동 보안을 약속하지 않는다.

```text
산란은 운영·보안 기준선의 호출 누락을 런타임에서 잡는다.
비즈니스 정책의 의미 판단은 모듈이 명시적으로 책임진다.
```

이 구분을 다음처럼 부른다.

```text
call-site contract: 필요한 helper가 요청 흐름에서 호출되었는지 확인하는 계약
semantic contract: 어떤 계정이 어떤 대상에 어떤 작업을 할 수 있는지 판단하는 계약
```

코어는 call-site contract를 보강한다. semantic contract는 모듈 action과 helper가 책임진다.

예를 들어 관리자 POST action에서 `sr_require_csrf()`, `sr_member_require_login()`, `sr_admin_require_permission()` 또는 `sr_admin_require_owner()` 호출이 빠지면 코어가 잡을 수 있다. 하지만 어떤 메뉴에서 `view`, `edit`, `delete` 중 어느 권한을 요구할지, 게시글 작성자가 자기 글만 수정할 수 있는지, 주문 상태를 어떤 순서로 바꿀 수 있는지는 해당 모듈의 정책이다.

## 1-1. 콘텐츠 보안 정책

기본 CSP는 자체 출처를 기준으로 한다. 외부 스타일/폰트와 연결 출처는 현재 관리자 폰트 로딩과 브라우저 개발자 도구의 보조 요청에 필요한 `https://cdn.jsdelivr.net`을 허용한다. CKEditor 플러그인의 기본 운영값은 저장소에 포함된 `modules/ckeditor/vendor/ckeditor5/` 파일을 쓰는 직접 호스팅이다. 선택 CDN 모드는 공식 CDN의 스크립트와 스타일을 불러올 수 있도록 `https://cdn.ckeditor.com`을 허용한다. 외부 스크립트 또는 연결 출처를 추가할 때는 UI 렌더링에 필요한지, 자체 호스팅으로 대체할 수 있는지, 운영 화면 전체에 주는 영향을 함께 검토한다.

CKEditor HTML 저장은 서버 helper가 허용 태그/속성 기반으로 정화한다. 클라이언트 에디터가 보낸 HTML을 신뢰하지 않으며, `script`, event handler, inline style, `iframe`, `object`, `embed`, `form`, `javascript:` URL, data URL 이미지는 허용하지 않는다. 커뮤니티 게시글은 커뮤니티 helper가, 콘텐츠와 알림 본문은 공통 rich text sanitizer를 거쳐 저장/출력한다.

회원 자산 알림은 자산 거래나 쿠폰 상태 변경이 성공한 뒤에만 생성한다. 알림 모듈이 비활성화되어 있거나 알림 템플릿이 없으면 no-op으로 처리하고, 포인트/적립금/예치금 원장 처리나 쿠폰 지급/사용/수동 환불 처리를 실패시키지 않는다. 외부 채널은 이벤트 템플릿에 명시된 경우에만 발송 작업 queue를 만들며, 기본 자산 알림은 사이트 알림으로 둔다.

쿠폰·이용권은 회원에게 귀속되는 권리성 자산이다. 쿠폰 종류는 쿠폰 모듈이 소유하고, 지급/사용/수동 환불/탈퇴 상태 변경 기록은 쿠폰 모듈의 개인정보 사본 제공에 포함한다. 콘텐츠와 커뮤니티는 쿠폰 테이블에 직접 접근하지 않고 helper를 통해 대상 열람권 사용 가능 여부와 사용 처리를 요청한다.

## 2. 세 층의 기준선

산란은 운영·보안 기준선을 한 helper에만 맡기지 않는다.

```text
1. helper
   - CSRF, 로그인, 관리자 권한, redirect, 오류 응답, 감사 로그 같은 공통 도구 제공

2. 정적 검사
   - .tools/bin/check.php와 세부 검사 도구가 action 파일의 누락과 위험한 패턴 확인

3. dispatch contract
   - index.php가 action include 전후로 요청 계약을 만들고 런타임에서 호출 누락 확인
```

이 구조는 프레임워크식 middleware 체인을 만들기 위한 것이 아니다. action 파일은 여전히 절차형 PHP 파일이고, 요청 흐름은 `index.php`, 모듈 `paths.php`, action 파일을 열어 추적한다.

## 3. 요청 흐름과 contract

설치 후 일반 요청은 다음 흐름을 따른다.

```text
index.php
-> sr_request_method(), sr_request_path()로 method/path 정규화
-> path가 / 이고 site.home_path가 /가 아니며 현재 사용할 수 있는 내부 경로이면 redirect
-> 초기화면 경로를 사용할 수 없으면 public layout/theme 기본 홈페이지 include
-> GET /ui-kit이면 public layout의 ui_kit view를 include
-> 활성 모듈의 paths.php 배열 읽기
-> METHOD /path와 일치하는 action 파일 검증
-> sr_start_request_contract(...)
-> action include
-> sr_enforce_request_contract('after_action')
```

contract는 현재 요청의 method, 정규화된 path, module key, action 파일, 관리자 요청 여부를 저장한다.

런타임 확인 기준:

- `POST` action은 `sr_require_csrf()`를 호출해야 한다.
- `/admin`과 `/admin/...` action은 `sr_member_require_login()`을 호출해야 한다.
- `/admin`과 `/admin/...` action은 `sr_admin_require_permission()` 또는 `sr_admin_require_owner()`를 호출해야 한다.
- 인증, 권한, CSRF guard가 요청을 막은 경우에는 의도된 차단으로 기록한다.
- 검사 누락 상태로 action이 끝나거나 응답 종료 지점에 도달하면 contract 위반으로 처리한다.

금액성 자산 차감, 쿠폰 사용, 접근권 생성처럼 회원 잔액이나 권리 상태를 바꾸는 공개 요청은 `GET`에서 실행하지 않는다. 콘텐츠 유료 열람, 콘텐츠 파일 다운로드, 커뮤니티 유료 열람, 커뮤니티 첨부 다운로드는 `GET`에서 로그인과 기존 접근권 또는 1회성 확인 세션만 확인하고, 실제 차감과 접근권 기록은 CSRF가 포함된 `POST` 확인 요청에서만 처리한다.

`sr_member_require_login()`은 비로그인 요청을 `/login`으로 보낼 때 현재 내부 경로와 쿼리스트링을 `next` 값으로 전달한다. 로그인 action은 안전한 내부 `next` 값이나 같은 호스트 referrer를 사용할 수 있으면 로그인 성공 후 그 위치로 보내고, 사용할 수 없거나 `/login`, `/logout` 같은 인증 흐름 경로이면 `/`로 보낸다.

contract 위반은 낮은 층에서 직접 로그를 남기고 평문 500 응답으로 종료한다. 이 실패 처리 안에서는 `sr_render_error()`나 `sr_redirect()` 같은 상위 helper를 다시 호출하지 않는다.

`sr_render_error()`는 원본 예외를 먼저 로그에 남긴 뒤 request contract를 검사하고 응답 상태를 설정한다. 관리자 레이아웃처럼 일부 출력이 이미 시작된 뒤 예외가 발생한 경우에도 `http_response_code()` 경고가 원본 예외 로그를 가리지 않아야 한다.

## 4. 허용된 응답 종료 지점

action 파일에서 직접 `exit` 또는 `die`를 호출하지 않는다. 응답을 끝내야 한다면 다음 helper 중 하나를 사용한다.

```text
sr_redirect()
sr_render_error()
sr_finish_response()
```

`header('Location: ...')`도 action에서 직접 호출하지 않는다. redirect는 `sr_redirect()`를 통과해야 안전한 상대 URL 검증과 contract 검사를 함께 받는다.

허용되는 예:

```text
header('Content-Type: ...') 같은 응답 메타 제어
http_response_code() 단독 호출
sr_redirect(), sr_render_error(), sr_finish_response()
```

금지되는 예:

```text
exit, die 직접 호출
header('Location: ...') 직접 호출
http_response_code(...); exit; 패턴
```

정적 검사는 `paths.php`에 등록된 action 파일에서 이 패턴을 확인한다.

`sr_request_contract_mark()`와 `sr_request_contract_guard_blocked()`는 코어와 공통 guard helper가 사용하는 낮은 층의 함수다. action 파일은 이 함수를 직접 호출하지 않고 `sr_require_csrf()`, `sr_member_require_login()`, `sr_admin_require_permission()`, `sr_admin_require_owner()` 같은 공개 helper를 통과한다.

## 5. 정규화된 path 기준

인증, 권한, 요청 계약 판단은 정규화된 request key를 기준으로 한다.

```text
$method = sr_request_method();
$path = sr_request_path();
$routeKey = $method . ' ' . $path;
```

action이나 helper가 권한 판단을 위해 `$_SERVER['REQUEST_URI']` 원문을 직접 해석하지 않는다. 공유호스팅 fallback처럼 URL 표현을 추가로 지원하더라도, 최종 권한 판단은 같은 정규화된 method/path 값으로 수렴해야 한다.

## 6. paths.php는 무엇이고 무엇이 아닌가

`paths.php`는 URL 요청을 action 파일로 연결하는 단순 배열이다. 산란은 요청 매핑을 사용하지만, 프레임워크식 라우팅 시스템으로 확장하지 않는다.

| 항목 | 산란 기준 |
| --- | --- |
| 등록 API | `sr_route()` 같은 등록 함수 없음 |
| 컨트롤러 클래스 | 없음 |
| 자동 스캔/리플렉션 | 없음 |
| 서비스 프로바이더 | 없음 |
| middleware 체인 | 없음 |
| 라우트 모델 바인딩 | 없음 |
| 요청 흐름 | `index.php`와 `paths.php`, action include로 추적 |

코어는 매핑 파일을 읽고 안전한 action 파일인지 검증한다. action 안에서 어떤 데이터를 읽고, 어떤 정책을 적용하고, 어떤 view를 include할지는 모듈이 명시적으로 작성한다.

## 7. 배포 보호와의 관계

dispatch contract는 애플리케이션 요청 흐름 안의 기준선이다. `config/`, `storage/`, `database/`, `core/`, `modules/`, `docs/`, `.tools/` 같은 내부 파일이 웹에서 직접 열리지 않도록 막는 배포 보호를 대체하지 않는다.

운영 환경에서는 루트 `index.php`만 공개 진입점으로 사용해야 한다. 이 조건을 만족하지 못하는 호스팅에는 운영 설치하지 않는다.
