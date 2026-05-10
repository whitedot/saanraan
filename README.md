# Toycore

## 프로젝트 동기

- Toycore(토이코어)는 [G5 Codex 프로젝트](https://github.com/whitedot/g5codex)를 진행하며 얻은 경험을 바탕으로 시작한 실험적 코어 프로젝트입니다.
- 토이 프로젝트처럼 가벼운 마음으로 아이디어가 떠오를 때마다 조금씩 다듬어 가는 작업에 가깝습니다.
- 완성된 제품을 서둘러 만들기보다, 부담 없이 시도하고, 구조를 바꿔 보고, 재미있는 가능성을 확인해 보는 것을 목표로 합니다.

## 시작하기

Toycore는 문서 루트에 저장소 파일 구조를 그대로 배치해 설치합니다. Git, SSH, CLI를 사용할 수 있는 환경에서는 clone 또는 fork 기반 설치를 권장합니다. 운영 서버에 Git 이력이 남아야 현재 파일이 어떤 릴리스에서 왔는지 추적하고 다음 릴리스와 비교하기 쉽습니다.

Git을 사용할 수 없는 공유호스팅에서는 릴리스 zip을 사용할 수 있습니다. 이 경우 업로드한 zip 파일명, 릴리스 태그, 적용 일자를 운영 기록으로 남기는 편이 좋습니다.

운영 사이트는 보통 `https://example.com/`처럼 도메인 루트에 둡니다. `toycore`는 저장소 이름 예시일 뿐입니다. 문서 루트 아래에 `toycore/` 폴더를 만들면 URL도 `https://example.com/toycore/`가 되므로, 하위 경로 설치를 의도한 경우가 아니라면 문서 루트 자체가 Toycore 루트를 가리키게 하거나 비어 있는 문서 루트 안에 저장소 내용을 직접 clone합니다.

```sh
cd /path/to/document-root
git clone https://github.com/whitedot/toycore.git .
git checkout v0.1.1
```

위의 `v0.1.1`은 릴리스 태그 예시입니다. 실제 설치할 때는 사용할 릴리스의 태그를 선택합니다.

운영자가 직접 수정하지 않는 설치라면 공식 저장소를 clone하고 릴리스 태그로 이동합니다. 전용 모듈, 호스팅별 설정 파일, 배포 스크립트, 운영 패치처럼 사이트별 변경을 함께 관리해야 한다면 먼저 fork한 뒤 fork를 운영 원격 저장소로 사용합니다.

```sh
git remote -v
git remote add upstream https://github.com/whitedot/toycore.git
git fetch upstream --tags
git checkout -b release/<release-tag> <release-tag>
```

업데이트는 새 릴리스 태그를 가져온 뒤 스테이징에서 병합 또는 rebase로 검토합니다. 파일을 반영한 뒤에는 `/admin/updates`에서 DB 업데이트를 명시적으로 실행합니다.

```sh
git fetch upstream --tags
git merge <next-release-tag>
```

`config/config.php`, `storage/installed.lock`, 로그, 백업 파일은 Git에 커밋하지 않습니다. Git 기반 설치에서도 DB 백업, 파일 백업, 스테이징 검증 후 운영 반영 순서를 지켜야 합니다.

Apache 또는 Apache 호환 공유호스팅 배포본에는 루트 `.htaccess`가 포함됩니다. 이 파일은 `config/`, `database/`, `modules/`, `storage/`, `.git/` 같은 내부 경로의 직접 접근을 막는 기본 보호 규칙입니다. 설치 전에 내부 파일이 웹에서 열리지 않는지 확인하고, `.htaccess`가 적용되지 않는 서버에서는 [배포 보호 기준](docs/deployment-protection.md)에 따라 서버 설정이나 호스팅 패널에서 같은 차단 규칙을 적용합니다.

## Toycore

Toycore는 WordPress 같은 콘텐츠 CMS도, Laravel/CodeIgniter 같은 애플리케이션 프레임워크도 아닙니다. 비즈니스 도메인은 모듈에 맡기고, 설치·회원 인증·관리자·감사 로그·개인정보·업데이트 같은 운영 도메인을 절차형 PHP 기준선으로 제공하는 솔루션 베이스입니다.

Toycore는 절차형 웹 솔루션 코어를 다시 쓰는 프로젝트입니다. 최신 개발 흐름을 부정하지 않지만, AI와 자동화 도구가 만든 코드도 사람이 파일을 열어 요청 흐름을 추적하고 수정할 수 있어야 한다는 관점에서 출발합니다.

Toycore는 코드보다 결정과 경계를 먼저 적어 둡니다. `docs/`의 규칙 문서와 [핵심 설계 결정](docs/core-decisions.md)을 기준으로, 복잡한 프레임워크에 의존하지 않고 읽기 쉽고 수정하기 쉬운 웹 솔루션 구조를 유지합니다.

## 무엇이 아닌가

| 구분 | Toycore가 하지 않는 것 | Toycore가 하는 것 |
| --- | --- | --- |
| 콘텐츠 CMS | 게시글, 페이지, 카테고리, 콘텐츠 workflow를 코어가 소유하지 않음 | 콘텐츠 도메인은 해당 모듈이 자기 테이블과 정책으로 소유 |
| 애플리케이션 프레임워크 | 컨트롤러 클래스, ORM, 서비스 프로바이더, middleware 체인을 강제하지 않음 | `index.php`, `paths.php`, action include로 요청 흐름을 노출 |
| hook 생태계 | 전역 hook/event dispatcher를 기본 구조로 두지 않음 | 필요한 연결은 계약 파일과 명시적 helper 호출로 처리 |
| 도메인 기본팩 | 포인트, 예치금, 적립금 같은 도메인을 코어 기능으로 만들지 않음 | 자주 쓰이는 도메인은 선택 예시 모듈로 제공 |

## 핵심 원칙

- 코어는 똑똑해지지 말 것
- 흐름은 파일을 열면 보일 것
- 자동 등록보다 명시적 include
- 기능보다 경계 우선
- 처음부터 완성형 CMS를 만들지 말 것

## 운영·보안 기준선

Toycore는 절차형 개발 방식의 단순함을 유지하지만, 보안·인증·권한 관리처럼 운영 사고로 이어지는 영역에서는 단순함보다 검증을 우선합니다.

운영·보안 기준선은 세 층으로 받칩니다.

- helper: CSRF, 로그인, 관리자 권한, 안전한 redirect, 오류 응답, 감사 로그 같은 공통 도구 제공
- 정적 검사: `.tools/bin/check.php`와 세부 검사 도구로 action 파일의 누락과 위험한 패턴 확인
- dispatch contract: action include 전후 런타임에서 POST CSRF, 관리자 로그인, 관리자 권한 helper 호출 누락 감지

이 기준선은 비즈니스 정책을 자동 판단하지 않습니다. Toycore는 운영·보안 helper의 호출 누락을 잡고, 어떤 role이 어떤 도메인 작업을 할 수 있는지는 해당 모듈이 명시적으로 책임집니다. 자세한 경계는 [Toycore 보안 모델](docs/security-model.md)을 따릅니다.

## 예상되는 한계

Toycore는 단순한 배포와 절차형 코드의 접근성을 우선하기 때문에, 다음과 같은 한계를 가질 수 있습니다.

- 대규모 서비스에서 요구되는 복잡한 아키텍처에는 적합하지 않을 수 있음
- 프레임워크 기반 프로젝트에 비해 기본 제공 기능이 적을 수 있음
- 코드 구조와 규칙을 명확히 관리하지 않으면 절차형 코드의 복잡도가 빠르게 증가할 수 있음
- 최신 프론트엔드 개발 방식에 비해 화면 상태 관리와 컴포넌트 재사용성이 제한될 수 있음
- 저가형 웹호스팅 환경을 고려하기 때문에 고성능 서버 기능이나 백그라운드 작업 처리에는 제약이 있을 수 있음

이러한 한계를 인식한 상태에서, Toycore는 모든 상황에 맞는 범용 프레임워크가 아니라 작고 명확한 웹 솔루션 코어를 목표로 합니다.

## 목표

- 회원 중심의 웹 솔루션 코어 구축
- PHP, 바닐라 JavaScript, plain CSS 기반 개발
- 저가형 웹호스팅 환경에서도 사용할 수 있는 구조 지향
- 작게 시작해 점진적으로 확장할 수 있는 솔루션 설계
- 절차형 코드에 익숙한 사람도 이해하고 유지보수할 수 있는 코드 구성
- 운영에 필요한 관리자 화면 제공

## 기술 방향

Toycore는 다음과 같은 기술 구성을 기본으로 합니다.

- PHP
- Vanilla JavaScript
- Plain CSS
- 일반적인 웹호스팅 환경

실행 환경은 PHP 8.1 이상을 기준으로 합니다. 설치 화면도 PHP 8.1 미만에서는 오류 상태로 표시합니다.

특정 빌드 도구, 복잡한 프론트엔드 프레임워크, 고사양 서버 환경을 전제로 하지 않습니다. 필요한 기능을 명확하게 나누고, 배포와 운영이 단순한 구조를 우선합니다.

## 현재 구현 범위

현재 toycore.git 본체는 웹 설치, 기본 관리자 진입, 회원 인증, 업데이트 실행, 개인정보/감사 로그 기반을 포함합니다. 모듈은 이 저장소의 `modules/{module_key}` 디렉터리에 놓인 파일 묶음으로 다룹니다.

Toycore는 작은 절차형 코어를 목표로 하지만, 운영 중 자주 문제가 되는 보안과 복구 흐름은 초기 구현 범위 안에서 다룹니다.

- 인증: DB 기반 로그인 세션, PHP 세션 strict/cookie-only 모드, 로그인 실패 타이밍 노출 완화, 비밀번호 변경/재설정 후 세션 폐기
- 토큰: 비밀번호 재설정/이메일 인증 token HMAC 저장, 원자적 사용 처리, 새 token 발급 시 기존 미사용 token 무효화, token URL referrer 차단
- 요청 보호: CSRF helper, 안전한 redirect helper, URL 제어 문자 차단, trusted proxy 기반 HTTPS/IP 해석, 기본 보안 응답 헤더
- 요청 계약: action include 전후 dispatch contract로 POST CSRF, 관리자 로그인, 관리자 권한 확인 helper의 호출 누락 감지
- 개인정보: 회원 개인정보 JSON 내보내기, 모듈별 export 확장, 내부 hash/token/secret-like 필드 제외, 탈퇴/익명화와 동의 철회 이력
- 운영 복구: 설치/업데이트 실패 marker, 업데이트 lock, checksum 기반 업데이트 파일 검증, 민감 정보 마스킹된 예외/감사 로그
- 모듈 격리: `paths.php` 기반 명시적 요청 처리, action 상대 경로 검증, 활성 모듈 route 충돌 감지, `module.php`의 `requires` 의존성 검증
- 배포: Git/릴리스 zip 기반 설치, owner 재인증 기반 모듈 zip 업로드와 파일 교체

기본 설치 흐름은 필수 모듈을 항상 설치하고 활성화합니다. 배포본에 포함된 선택 모듈은 설치 화면에서 함께 설치할 수 있고, 설치 때 선택하지 않았거나 나중에 배치한 모듈은 `/admin/modules`에서 설치합니다.

```text
필수: core + member + admin
선택: 설치 화면에서 선택한 포함 모듈 또는 modules/{module_key}에 배치된 모듈 폴더
```

선택 모듈은 코어 도메인이 아닙니다. `point`, `deposit`, `reward`는 자주 쓰이는 회원 연계 도메인을 코어 밖 모듈로 분리해 둔 예시 모듈입니다. 설치하지 않아도 `core + member + admin` 기준선은 동작합니다.

Toycore 런타임은 현재 배치된 파일과 `modules/{module_key}` 폴더를 기준으로 동작합니다. 필요한 모듈을 따로 받은 경우에도 최종 배치 위치는 `modules/{module_key}`입니다.

- `member`: 회원가입, 로그인/로그아웃, 계정 화면, 비밀번호 재설정, 이메일 인증, 동의 기록, 탈퇴/익명화, DB 세션, 인증 로그, 전용 관리자 설정
- `admin`: 관리자 대시보드, 사이트 설정, 모듈 관리, 회원 관리, 권한, 감사 로그, 개인정보 요청, 보관 정리, 업데이트 실행
- `seo`, `site_menu`, `banner`, `popup_layer`, `notification`: 선택 운영 모듈
- `point`, `deposit`, `reward`: 회원 연계 도메인을 코어 밖에 둔 선택 예시 모듈

기본 점검은 다음 명령으로 실행합니다.

```sh
./.tools/bin/check
```

Docker 또는 OrbStack이 실행 중이 아니어도 공백, SQL 파일, 모듈 기본 구조 검사는 먼저 실행됩니다. PHP 문법 검사는 Docker 또는 OrbStack 실행 상태가 필요합니다.

Windows처럼 sh/WSL을 쓰지 않는 환경에서는 로컬 PHP로 같은 기본 검사를 실행할 수 있습니다.

```sh
php .tools/bin/check.php
```

로컬 서버나 스테이징 서버가 떠 있으면 최소 HTTP 스모크 점검을 실행할 수 있습니다.

```sh
php -S 127.0.0.1:8080 -t .tools/public .tools/bin/dev-router.php
TOY_SMOKE_BASE_URL=http://127.0.0.1:8080 php .tools/bin/smoke-http.php
```

인증 커뮤니티 흐름까지 확인하는 스모크 점검은 [스모크 테스트 기준](docs/smoke-test.md)에 정리되어 있습니다.

## 모듈 구조

Toycore의 모듈은 프레임워크 패키지가 아니라, 정해진 디렉터리에 놓인 절차형 PHP 파일과 DB에 저장된 설치/활성 상태로 동작합니다.

Toycore 안에서는 모듈을 항상 `modules/{module_key}` 폴더로 다룹니다. zip 업로드 전 확인 항목은 [모듈 체크리스트](docs/module-checklist.md)에 있습니다.

설치만 가능한 최소 모듈은 `module.php`와 `install.sql`로 시작합니다. URL 요청 하나를 처리하는 가장 작은 모듈은 여기에 `paths.php`와 `actions/{name}.php` 하나를 더하면 됩니다. `admin-menu.php`, `views/`, `assets/`, `lang/`, `updates/`는 필요할 때 추가합니다.

```text
modules/{module_key}/
- module.php
- paths.php
- admin-menu.php
- output-slots.php
- actions/
- views/
- assets/
- lang/
- install.sql
- updates/
```

요청 흐름은 숨은 dispatcher 대신 명시적 파일 읽기를 따릅니다.

```text
index.php
-> method/path 확인
-> 활성 모듈 조회
-> 각 모듈의 paths.php 확인
-> 현재 요청에 맞는 action 파일 검증
-> request contract 시작
-> action 파일 include
-> request contract 검사
```

모듈과 플러그인은 같은 설치/활성화 테이블을 사용할 수 있지만 개념은 구분합니다.

```text
module = 자기 도메인과 정책을 소유하는 확장
plugin = 특정 모듈이나 계약 파일에 붙어 동작하는 확장
```

현재 DB 테이블 이름은 `toy_modules`를 유지하고, 확장의 성격은 `module.php`의 `type` 값으로 표시합니다. 자세한 작성 규칙은 [모듈 작성 가이드](docs/module-guide.md)를 따릅니다.

최소 모듈 구조 예시는 [sample_module](examples/sample_module/README.md)에서 확인할 수 있습니다.

설치 후에는 owner가 `/admin/modules`에서 모듈 zip을 업로드할 수 있습니다. 업로드 zip은 `{module_key}/module.php` 구조를 권장하며, `module/module.php` 구조도 module key를 입력하면 사용할 수 있습니다. 기존 모듈 파일을 교체할 때는 owner가 파일 교체를 명시적으로 확인해야 하고, 이전 디렉터리는 `storage/module-backups`에 보관합니다. 설치 버전보다 낮은 코드 버전은 기본 차단되며, 파일 교체와 DB 업데이트는 분리되어 있으므로 기존 모듈을 교체한 뒤에는 `/admin/updates`에서 미적용 SQL을 확인합니다.

## Extension Points

Toycore는 전역 hook/event dispatcher를 기본 구조로 두지 않습니다. 모듈 간 영향이 필요하면 각 모듈이 명시적 계약 파일을 제공하고, 소비 모듈이 필요한 시점에 그 파일을 읽습니다.

외부 출력이나 확장이 붙을 수 있는 화면/기능 위치는 `extension-points.php`로 선언합니다.

```php
<?php

return [
    [
        'point_key' => 'member.login',
        'label' => '로그인',
        'surface' => 'public',
        'output' => true,
        'slots' => [
            [
                'slot_key' => 'before_content',
                'label' => '본문 위',
                'kind' => 'content',
            ],
        ],
    ],
];
```

선택 깊이는 기본적으로 다음 4단계를 최대치로 봅니다.

```text
module -> point -> slot -> subject
```

팝업레이어도 배너와 같이 선언된 `content` slot을 읽어 `module -> point -> slot -> subject` 범위에서 대상을 선택합니다. 화면 소유 모듈은 실제 출력 위치에서 `slot_key`를 포함해 `toy_render_output_slot()`을 명시 호출하고, 팝업레이어 모듈은 저장된 대상 규칙에 맞는 HTML을 해당 content slot으로 반환합니다. 5단계 이상이 필요하면 단계를 늘리지 않고 `filters`, `schedule`, `device`, `locale`, `member_status` 같은 조건 필드로 분리합니다.

성능 기준도 명확히 나눕니다.

- 관리자 설정 시점: 활성 모듈의 `extension-points.php`를 읽어 선택 가능한 대상을 구성
- 사용자 요청 시점: `extension-points.php`를 읽지 않고 저장된 규칙 테이블만 조회
- 대량 subject: 전체 options를 반환하지 않고 검색형 selector로 확장

## 규칙 문서

- [핵심 설계 결정](docs/core-decisions.md)
- [Toycore 보안 모델](docs/security-model.md)
- [보안 체크리스트](docs/security-checklist.md)
- [DB 접근 정책](docs/database-access-policy.md)
- [배포 보호 기준](docs/deployment-protection.md)
- [릴리스 절차](docs/release-process.md)
- [스모크 테스트 기준](docs/smoke-test.md)
- [모듈 작성 가이드](docs/module-guide.md)
- [모듈 체크리스트](docs/module-checklist.md)
- [모듈 저장 위치 기준](docs/module-storage-policy.md)
- [모듈 배치와 업데이트 기준](docs/module-update-policy.md)
- [관리자 POST action 작성 규칙](docs/admin-post-action-rules.md)

## 예제

- [절차형 요청 흐름 예제](examples/procedural-flow-sample.php.txt)

## 라이선스

Toycore는 [MIT License](LICENSE)로 배포합니다.
