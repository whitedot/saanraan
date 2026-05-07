# Toycore

## 토이코어 뜻

Toycore(토이코어)는 [G5 Codex 프로젝트](https://github.com/whitedot/g5codex)를 진행하며 얻은 경험을 바탕으로 시작한 실험적 코어 프로젝트입니다.

토이 프로젝트처럼 가벼운 마음으로 아이디어가 떠오를 때마다 조금씩 다듬어 가는 작업에 가깝습니다.

완성된 제품을 서둘러 만들기보다, 부담 없이 시도하고, 구조를 바꿔 보고, 재미있는 가능성을 확인해 보는 것을 목표로 합니다.

Toycore는 절차형 웹 솔루션 코어를 다시 쓰는 프로젝트입니다.

AI의 발전으로 코드 생성이 쉬워진 시대에도, 여전히 절차형 개발 방식에 익숙한 개발자와 운영자가 있습니다. Toycore는 그런 사람들을 위해 복잡한 프레임워크에 의존하지 않고, 읽기 쉽고 수정하기 쉬운 방식으로 웹 솔루션의 기본 구조를 다시 설계하는 것을 목표로 합니다.

## 프로젝트 관점

이 프로젝트는 최신 개발 흐름을 부정하기 위한 것이 아닙니다. 대신 AI와 자동화 도구를 활용하더라도, 결과물은 사람이 직접 읽고 고칠 수 있어야 한다는 관점에서 출발합니다.

Toycore는 절차형 개발 방식의 단순함을 유지하면서도, 현대적인 웹 솔루션에 필요한 확장성과 유지보수성을 함께 갖추는 것을 지향합니다.

## 핵심 원칙

- 코어는 똑똑해지지 말 것
- 흐름은 파일을 열면 보일 것
- 자동 등록보다 명시적 include
- 기능보다 경계 우선
- 처음부터 완성형 CMS를 만들지 말 것

## 예상되는 한계

Toycore는 단순한 배포와 절차형 코드의 접근성을 우선하기 때문에, 다음과 같은 한계를 가질 수 있습니다.

- 대규모 서비스에서 요구되는 복잡한 아키텍처에는 적합하지 않을 수 있음
- 프레임워크 기반 프로젝트에 비해 기본 제공 기능이 적을 수 있음
- 코드 구조와 규칙을 명확히 관리하지 않으면 절차형 코드의 복잡도가 빠르게 증가할 수 있음
- 최신 프론트엔드 개발 방식에 비해 화면 상태 관리와 컴포넌트 재사용성이 제한될 수 있음
- 저가형 웹호스팅 환경을 고려하기 때문에 고성능 서버 기능이나 백그라운드 작업 처리에는 제약이 있을 수 있음
- 보안, 인증, 권한 관리 같은 핵심 기능은 단순함보다 엄격한 검증과 지속적인 개선이 더 중요함

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

현재 toycore.git 본체는 웹 설치, 기본 관리자 진입, 회원 인증, 업데이트 실행, 개인정보/감사 로그 기반을 포함합니다. 모듈은 기본적으로 이 저장소의 `modules/{module_key}` 디렉터리에 놓인 파일 묶음으로 다룹니다.

Toycore는 작은 절차형 코어를 목표로 하지만, 운영 중 자주 문제가 되는 보안과 복구 흐름은 초기 구현 범위 안에서 다룹니다.

- 인증: DB 기반 로그인 세션, PHP 세션 strict/cookie-only 모드, 로그인 실패 타이밍 노출 완화, 비밀번호 변경/재설정 후 세션 폐기
- 토큰: 비밀번호 재설정/이메일 인증 token HMAC 저장, 원자적 사용 처리, 새 token 발급 시 기존 미사용 token 무효화, token URL referrer 차단
- 요청 보호: CSRF helper, 안전한 redirect helper, URL 제어 문자 차단, trusted proxy 기반 HTTPS/IP 해석, 기본 보안 응답 헤더
- 개인정보: 회원 개인정보 JSON 내보내기, 모듈별 export 확장, 내부 hash/token/secret-like 필드 제외, 탈퇴/익명화와 동의 철회 이력
- 운영 복구: 설치/업데이트 실패 marker, 업데이트 lock, checksum 기반 업데이트 파일 검증, 민감 정보 마스킹된 예외/감사 로그
- 모듈 격리: `paths.php` 기반 명시적 요청 처리, action 상대 경로 검증, 활성 모듈 route 충돌 감지, `module.php`의 `requires` 의존성 검증
- 배포: minimal/standard/ops 패키징, owner 재인증 기반 모듈 zip 업로드와 파일 교체

기본 설치 흐름은 필수 모듈을 항상 설치/활성화합니다. 선택 모듈은 `modules/{module_key}`에 파일을 배치한 뒤 `/admin/modules`에서 설치합니다.

```text
필수: core + member + admin
선택: modules/{module_key}에 배치된 모듈 폴더
```

배포 산출물은 다음처럼 나눌 수 있습니다.

```text
minimal: core + member + admin
standard: core + member + admin + seo + site_menu + banner
ops: standard + popup_layer + point + deposit + reward + notification
```

현재 공식 배포본은 toycore.git 안의 `modules/{module_key}` 폴더만 패키징합니다. 필요한 모듈을 따로 받은 경우에도 최종 배치 위치는 `modules/{module_key}`입니다.

- `member`: 회원가입, 로그인/로그아웃, 계정 화면, 비밀번호 재설정, 이메일 인증, 동의 기록, 탈퇴/익명화, DB 세션, 인증 로그, 전용 관리자 설정
- `admin`: 관리자 대시보드, 사이트 설정, 모듈 관리, 회원 관리, 권한, 감사 로그, 개인정보 요청, 보관 정리, 업데이트 실행
- `seo`, `site_menu`, `banner`, `popup_layer`, `point`, `deposit`, `reward`, `notification`: 선택 모듈
자세한 구현 범위는 [현재 구현 상태](docs/current-implementation-status.md)를 기준으로 확인합니다.

기본 점검은 다음 명령으로 실행합니다.

```sh
./.tools/bin/check
```

Docker 또는 OrbStack이 꺼져 있어도 공백, SQL 파일, 모듈 기본 구조 검사는 먼저 실행됩니다. PHP 문법 검사는 Docker 또는 OrbStack 실행 상태가 필요합니다.

Windows처럼 sh/WSL이 없는 환경에서 로컬 PHP가 있다면 같은 기본 검사를 PHP 도구로 실행할 수 있습니다.

```sh
php .tools/bin/check.php
```

로컬 서버나 스테이징 서버가 떠 있으면 최소 HTTP 스모크 점검을 실행할 수 있습니다.

```sh
php -S 127.0.0.1:8080 -t .tools/public .tools/bin/dev-router.php
php .tools/bin/smoke-http.php http://127.0.0.1:8080
```

## 설치 방식 선택

일반 설치자는 `package-distributions`를 직접 실행하지 않습니다. 이 명령은 공식 배포 zip을 만드는 Toycore maintainer용 도구입니다.

```text
공식 maintainer
-> toycore.git 안의 현재 파일로 minimal/standard/ops 배포본 생성

일반 설치자
-> 이미 만들어진 release zip 또는 Git 릴리스 태그를 받아 설치
```

릴리스 zip은 Git, SSH, CLI를 사용할 수 없는 저가형 호스팅을 위한 기본 배포 수단입니다. 설치는 편하지만, 운영 서버에 Git 이력이 없으므로 현재 파일이 어떤 릴리스에서 왔는지 추적하고 다음 릴리스와 비교하기 어렵습니다.

Git을 사용할 수 있는 서버나 배포 환경에서는 clone 또는 fork 기반 설치를 권장합니다. 현재 `toycore.git` 본체에는 공식 선택 모듈 코드도 `modules/` 아래에 함께 들어 있습니다.

```sh
git clone https://github.com/whitedot/toycore.git toycore
cd toycore
git checkout v0.1.1
```

위의 `v0.1.1`은 현재 공개 릴리스 예시이며, 실제 설치할 때는 원하는 릴리스의 태그를 사용합니다. `standard`나 `ops` 배포본도 toycore.git 안의 현재 파일을 기준으로 조립합니다.

운영자가 직접 수정하지 않는 설치라면 공식 저장소를 clone하고 릴리스 태그로 이동합니다. 운영자가 로컬 수정, 전용 모듈, 호스팅별 설정 파일, 배포 스크립트를 함께 관리해야 한다면 먼저 fork한 뒤 fork를 운영 원격 저장소로 사용합니다.

```sh
git remote -v
git remote add upstream https://github.com/whitedot/toycore.git
git fetch upstream --tags
git checkout -b release/<release-tag> <release-tag>
```

업데이트는 새 릴리스 태그를 가져온 뒤 스테이징에서 병합 또는 rebase로 검토하고, 파일 반영 후 `/admin/updates`에서 DB 업데이트를 명시적으로 실행합니다.

```sh
git fetch upstream --tags
git merge <next-release-tag>
```

`config/config.php`, `storage/installed.lock`, 로그, 백업 파일은 Git에 커밋하지 않습니다. Git 기반 설치에서도 DB 백업, 파일 백업, 스테이징 검증 후 운영 반영 순서를 지켜야 합니다. Git을 사용할 수 없는 호스팅에서는 릴리스 zip을 사용하되, 업로드한 zip 파일명, 릴리스 태그, `distribution-manifest.json`을 운영 기록으로 남깁니다.

## 릴리스 제작

공식 배포 조합은 [distributions.json](docs/distributions.json)에 정의합니다. 이 파일이 `minimal`, `standard`, `ops` 포함 모듈과 설치 화면의 기본 선택 모듈을 정하는 기준입니다.

배포 패키지는 공식 maintainer 환경에서 다음 명령으로 만듭니다.

```sh
./.tools/bin/package-distributions 2026.05.001
```

결과는 `dist/toycore-minimal`, `dist/toycore-standard`, `dist/toycore-ops` 디렉터리와, `zip` 명령이 있는 경우 같은 이름의 zip 파일로 생성됩니다.
각 배포 디렉터리에는 포함 모듈, 모듈 버전, Toycore 최소 버전, 모듈 계약 버전을 확인할 수 있는 `distribution-manifest.json`이 함께 생성됩니다.

생성된 배포 디렉터리, manifest의 버전/계약 정보, 설치 화면 선택 모듈 구성은 다음 명령으로 확인할 수 있습니다.

```sh
php .tools/bin/check-distribution-policy.php
php .tools/bin/check-distributions.php 2026.05.001
```

## 모듈 구조

Toycore의 모듈은 프레임워크 패키지가 아니라, 정해진 디렉터리에 놓인 절차형 PHP 파일과 DB에 저장된 설치/활성 상태로 동작합니다.

저장소 밖에서 모듈을 만들 수도 있지만, Toycore 안에서는 항상 `modules/{module_key}` 폴더로 다룹니다. zip 배포 전 확인 항목은 [모듈 체크리스트](docs/module-checklist.md)에 있습니다.

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
-> 현재 요청에 맞는 action 파일 검증 후 include
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

## 설계 문서

- [문서 분류](docs/documentation-index.md)
- [기본환경 테이블 ERD](docs/erd-basic-environment.md)
- [DB 접근 정책](docs/database-access-policy.md)
- [구현된 기능 리스트](docs/implemented-features.md)
- [구현 방향 및 보안 계획](docs/implementation-security-plan.md)
- [핵심 설계 결정](docs/core-decisions.md)
- [설치 및 초기화 계획](docs/install-plan.md)
- [업데이트 및 스키마 버전 계획](docs/update-plan.md)
- [운영 모드 및 에러 처리 계획](docs/runtime-ops-plan.md)
- [감사 로그 계획](docs/audit-log-plan.md)
- [보안 체크리스트](docs/security-checklist.md)
- [배포 보호 기준](docs/deployment-protection.md)
- [서버별 배포 예시](docs/deployment-examples.md)
- [릴리스 절차](docs/release-process.md)
- [현재 구현 상태](docs/current-implementation-status.md)
- [모듈 작성 가이드](docs/module-guide.md)
- [모듈 저장 위치 기준](docs/module-storage-policy.md)
- [모듈 설치 소스와 업데이트 보완 계획](docs/module-update-and-source-plan.md)
- [회원 모듈 상세 계획](docs/member-plan.md)
- [관리자 모듈 상세 계획](docs/admin-plan.md)
- [다국어 처리 계획](docs/i18n-plan.md)
- [개인정보 및 GDPR 대응 계획](docs/privacy-gdpr-plan.md)
- [SEO 대응 계획](docs/seo-plan.md)
- [진입점 및 홈 요청 분기 계획](docs/entry-request-plan.md)
- [캐시 계획](docs/cache-plan.md)

## 예제

- [절차형 요청 흐름 예제](examples/procedural-flow-sample.php.txt)

## 라이선스

Toycore는 [MIT License](LICENSE)로 배포합니다.
