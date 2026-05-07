# AGENTS.md

이 프로젝트는 Toycore 외부 모듈 `MODULE_KEY`를 관리한다. Git 저장소로 관리해도 되고, 단순 작업 폴더로 관리해도 된다. 실제 런타임 모듈 코드는 `module/` 아래에 두고, Toycore 설치본에는 릴리스 zip을 통해 `modules/MODULE_KEY/` 구조로 반영한다.

## 기준 문서

- Toycore 본체의 `docs/module-guide.md`를 모듈 작성의 기준 문서로 본다.
- 처음 구조를 확인할 때는 Toycore 본체의 `docs/external-module-quickstart.md`를 본다.
- 배포 전에는 Toycore 본체의 `docs/module-checklist.md`를 확인한다.
- 자동 점검을 쓰는 경우에는 Toycore 본체의 `docs/module-ci-quickstart.md`와 이 프로젝트의 `.github/workflows/check.yml`을 기준으로 한다.
- 구현 판단이 애매하면 Toycore 본체의 `docs/core-decisions.md`를 우선한다.
- 로컬에서 본체 문서를 직접 볼 때도 형제 디렉터리 배치를 가정하지 말고, `TOYCORE` 또는 실제 Toycore 소스 경로를 기준으로 찾는다.

## AI 보조 작업

- AI 코딩 도구에 작업을 맡길 때는 먼저 이 `AGENTS.md`를 기준 규칙으로 사용하게 한다.
- 구현 요청은 파일 경계와 목적을 같이 적는다. 예: `AGENTS.md 기준으로 module/paths.php와 관리자 action을 추가해줘.`
- 리뷰 요청은 배포 전 기준을 명시한다. 예: `AGENTS.md와 module-checklist.md 기준으로 릴리스 전 위험을 점검해줘.`
- AI가 만든 변경도 로컬 점검과 체크리스트를 통과하기 전에는 배포하지 않는다.
- AI가 제안한 코어 테이블 변경, 자동 등록, 숨은 dispatcher, Composer 런타임 의존성은 기본적으로 거절하고 모듈 소유 구조로 다시 설계한다.

## 저장소/작업 폴더 구조

권장 구조:

```text
README.md
CHANGELOG.md
AGENTS.md
.tools/bin/package-module
module/
  module.php
  install.sql
```

선택 파일:

```text
module/helpers.php
module/helpers/
module/paths.php
module/admin-menu.php
module/menu-links.php
module/output-slots.php
module/extension-points.php
module/privacy-export.php
module/sitemap.php
module/actions/
module/views/
module/assets/
module/lang/
module/updates/
```

## 모듈 메타데이터

- `module/module.php`는 배열만 반환하는 정보 파일이다.
- `module.php`에서 DB 변경, route 등록, action include, 출력, 세션 변경을 하지 않는다.
- `name`은 비워 두지 않는다.
- `version`은 `YYYY.MM.NNN` 형식을 사용한다.
- `type`은 `module` 또는 `plugin`만 사용한다.
- `toycore.min_version`은 필수다.
- `toycore.tested_with`는 비어 있지 않은 배열이어야 한다.
- `toycore.module_contract`는 `MODULE_CONTRACT_VERSION`을 사용한다.

## 이름과 DB 규칙

- `module_key`는 `\A[a-z][a-z0-9_]{1,39}\z` 형식을 사용한다.
- DB 테이블은 프로젝트 공통 prefix인 `toy_`로 시작하고, 모듈 namespace를 포함한다.
- 예: `toy_MODULE_KEY_items`
- `toy_member_accounts`나 코어 테이블을 미래 확장 목적으로 넓히지 않는다.
- 회원 연결 데이터는 모듈 소유 테이블에 `account_id`를 두고 연결한다.
- 다른 모듈의 내부 테이블을 직접 변경하지 않는다. 필요하면 공개 helper나 계약 파일을 우선 검토한다.

## 요청 흐름

- 요청 경로는 `module/paths.php`의 명시적 배열로 선언한다.
- `paths.php` key는 `GET /path` 또는 `POST /path` 형식이다.
- action 경로는 `actions/...php` 형식만 사용하고 실제 파일이 있어야 한다.
- 상태 변경은 `POST`로 처리한다.
- route 등록 함수, 자동 dispatcher, service provider, event bus, reflection 기반 자동 등록을 만들지 않는다.
- 같은 method/path를 여러 모듈이 선언하면 Toycore에서 충돌로 처리된다.

## Action 작성 규칙

- action 파일은 요청 판단, 권한 확인, 입력 검증, DB 변경, redirect, view include를 담당한다.
- 관리자 action은 시작 부분에서 로그인과 role을 확인한다.
- 회원 전용 action은 로그인 확인을 먼저 수행한다.
- 모든 상태 변경 `POST`는 `toy_require_csrf()`를 통과해야 한다.
- 입력값은 서버에서 타입, 길이, 허용 목록을 검증한다.
- SQL 동적 값은 `PDO::prepare()`와 named placeholder로 바인딩한다.
- 정렬 컬럼, 테이블명, 상태 값처럼 placeholder로 바인딩할 수 없는 값은 허용 목록에서만 고른다.
- 상태 변경 성공은 필요한 경우 감사 로그를 남긴다.
- 비밀번호, 토큰 원문, 세션 ID 원문, 개인정보 원문 대량 payload를 로그에 남기지 않는다.

## View 작성 규칙

- HTML은 일반 HTML로 작성하고 필요한 위치에만 `<?php echo ...; ?>`를 둔다.
- PHP short tag와 short echo tag를 쓰지 않는다.
- 전체 HTML 레이아웃을 heredoc 문자열로 출력하지 않는다.
- 출력값은 `toy_e()` 또는 같은 수준의 escape를 거친다.
- 줄바꿈이 필요한 텍스트는 `nl2br(toy_e($value))`를 사용한다.
- view에서 `$_GET`, `$_POST`, `$_COOKIE`를 직접 읽지 않는다.
- view에서 DB 변경을 하지 않는다.
- 권한 판단은 view가 아니라 action에서 끝낸다.

## Helper 작성 규칙

- 공통 함수 진입점은 가능하면 `module/helpers.php`로 둔다.
- action은 가능하면 자기 모듈의 `helpers.php`만 require한다.
- 하위 helper 파일은 로드 시 부작용을 만들지 않는다.
- helper 함수명은 `toy_MODULE_KEY_...` prefix를 사용한다.
- DB가 필요한 helper는 `PDO $pdo`를 명시적으로 인자로 받는다.
- helper가 HTML 문자열을 반환한다면 escape 책임을 helper 안에서 끝낸다.

## 계약 파일 규칙

- 계약 파일은 자동 등록이 아니라 소비 모듈이 명시적으로 읽는 공개 약속이다.
- 계약 파일은 로드 시 상태 변경을 하지 않는다.
- `admin-menu.php`가 있으면 같은 모듈의 `paths.php`에 `GET {path}`가 있어야 한다.
- `menu-links.php`는 운영자가 선택할 후보 링크만 제공하고 메뉴를 자동 생성하지 않는다.
- `output-slots.php`는 callable을 반환한다. 자기 helper를 읽을 때는 외부 점검을 위해 `__DIR__` 기준 경로를 사용한다.
- `extension-points.php`는 관리자 설정 화면에서 읽을 확장 가능 지점을 선언한다. 사용자 요청마다 비싼 탐색을 반복하지 않는다.
- `privacy-export.php`는 특정 회원의 데이터만 반환하고 hash, token, password, secret 계열 내부 값을 내보내지 않는다.
- `sitemap.php`는 공개 가능한 URL만 반환한다. 비공개, 삭제, 임시저장 콘텐츠는 제외한다.

## SQL과 업데이트

- `module/install.sql`은 필수 파일이다. 테이블이 없어도 설명 주석을 둔다.
- 설치 SQL은 여러 번 실행해도 실패하지 않도록 `CREATE TABLE IF NOT EXISTS`와 재실행 가능한 seed를 우선한다.
- 모듈 소유 테이블과 인덱스만 생성하거나 변경한다.
- 이미 배포된 구조 변경은 `module/updates/YYYY.MM.NNN.sql`로 추가한다.
- 업데이트 파일을 추가하면 `module.php`의 `version`도 올린다.
- `install.sql`은 항상 최신 신규 설치 구조를 담는다.
- 배포된 update SQL을 같은 버전에서 조용히 수정하지 않는다. 필요한 경우 새 버전 파일을 추가한다.

## Assets와 번역

- 모듈 정적 파일은 `module/assets/` 아래에 둔다.
- 공개 URL은 `/modules/MODULE_KEY/assets/...` 형태를 기준으로 한다.
- `assets/`에는 PHP, SQL, 설정 파일, 사용자 업로드 원본을 두지 않는다.
- 모듈 UI 번역은 `module/lang/{locale}.php` 배열 파일로 둔다.
- 번역 출력도 화면에서는 escape한다.
- 사용자 콘텐츠 다국어화는 코어가 대신 처리하지 않는다. 필요한 모듈이 자기 테이블과 화면으로 설계한다.

## 배포와 점검

로컬 점검은 Toycore 소스 경로와 이 모듈 프로젝트 경로를 명시해 실행한다. 두 폴더가 같은 상위 디렉터리에 있을 필요는 없다.

```sh
TOYCORE=/path/to/toycore
php "$TOYCORE/.tools/bin/check-external-module.php" module MODULE_KEY
```

Windows PowerShell:

```powershell
$env:TOYCORE = 'C:\path\to\toycore'
php "$env:TOYCORE\.tools\bin\check-external-module.php" module MODULE_KEY
```

패키징:

```sh
php .tools/bin/package-module
```

릴리스 zip은 압축 해제 시 바로 모듈 키 디렉터리가 나와야 한다.

```text
MODULE_KEY-2026.05.001.zip
-> MODULE_KEY/
   - module.php
   - install.sql
```

운영 반영 후에는 Toycore 관리자에서 `/admin/modules`와 `/admin/updates`를 확인한다.

## Git과 CI

- 별도 Git 저장소는 팀 작업, 공개 배포, 반복 배포, 공식 registry 등록이 필요할 때 사용한다.
- 혼자 만들고 zip으로 업로드하는 모듈은 Git 저장소나 CI 없이 시작해도 된다.
- GitHub Actions는 배포가 아니라 로컬 점검을 push 때 자동 실행하는 선택 기능이다.

## 개발 방향

- 절차형 PHP, vanilla JavaScript, plain CSS를 우선한다.
- 저가형 공유호스팅에서 PHP 파일과 SQL만으로 설치 가능한 구조를 유지한다.
- Composer, Git, CLI, background worker가 런타임 필수가 되게 만들지 않는다.
- Laravel Service Provider, ORM 모델, 클래스 migration, DI container, event bus를 기본 전제로 삼지 않는다.
- 코어가 도메인 정책을 대신 소유하게 만들지 않는다.
- 모듈은 자기 테이블, 화면, 정책, 권한, 업데이트를 책임진다.

## 커밋 메시지

- 커밋 메시지는 한국어로 쓴다.
- 형식은 `type: message`를 사용한다.
- type은 `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `style`, `perf`, `build`, `ci`, `revert`만 사용한다.
- 영역명은 type으로 쓰지 말고 필요한 경우 한국어 메시지에 포함한다.
- 예: `fix: MODULE_NAME 관리자 저장 검증 보완`
