# 모듈 별도 리포지토리 관리 방안

이 문서는 Toycore 모듈을 코어 리포지토리 밖에서 관리할 때의 장단점, 권장 구조, 전환 단계를 정리한다.

결론부터 말하면, Toycore 초기에는 `core + 기본 필수 모듈(member, admin)`은 같은 리포지토리에 두고, 도메인 성격이 강하거나 선택 설치 성격이 강한 모듈부터 별도 리포지토리로 분리하는 방식을 권장한다. 단, 코어 실행이 Composer, Git, CLI에 의존하면 저가형 웹호스팅 목표와 충돌하므로 배포 산출물은 여전히 복사 가능한 PHP/SQL 디렉터리여야 한다.

## 1. 분리 대상 판단

코어 리포지토리에 남긴다:

- `core`
- 설치에 필요한 `member`
- 관리자 진입에 필요한 `admin`
- 샘플 모듈과 문서 예제
- 모듈 계약과 검증 helper

별도 리포지토리 후보:

- `seo`
- `popup_layer`
- `site_menu`
- `banner`
- `notification`
- `point`
- `deposit`
- `reward`
- 향후 게시판, 쇼핑몰, 예약, 쿠폰, 마케팅, 분석 모듈

현재 1차 분리 리포지토리:

```text
git@github.com:whitedot/toycore-module-banner.git
git@github.com:whitedot/toycore-module-popup-layer.git
git@github.com:whitedot/toycore-module-site-menu.git
git@github.com:whitedot/toycore-module-notification.git
git@github.com:whitedot/toycore-module-seo.git
git@github.com:whitedot/toycore-module-point.git
git@github.com:whitedot/toycore-module-deposit.git
git@github.com:whitedot/toycore-module-reward.git
```

2026-04-29 기준 1차 분리 작업 상태:

| 모듈 | 리포지토리 | 초기 커밋 | 설치 디렉터리 |
| --- | --- | --- | --- |
| `banner` | `toycore-module-banner` | `dca3565` | `modules/banner` |
| `popup_layer` | `toycore-module-popup-layer` | `3d27ee2` | `modules/popup_layer` |
| `site_menu` | `toycore-module-site-menu` | `2fdfe54` | `modules/site_menu` |
| `notification` | `toycore-module-notification` | `fc8f91d` | `modules/notification` |
| `seo` | `toycore-module-seo` | `0675451` | `modules/seo` |
| `point` | `toycore-module-point` | `365907e` | `modules/point` |
| `deposit` | `toycore-module-deposit` | `e4d12d2` | `modules/deposit` |
| `reward` | `toycore-module-reward` | `7125a87` | `modules/reward` |

1차 분리 작업의 범위:

- 각 모듈 리포지토리를 생성하고 `main` 브랜치에 초기 커밋을 push했다.
- 각 리포지토리는 `module/` 아래에 Toycore 런타임 파일을 둔다.
- 각 리포지토리에 `README.md`와 `CHANGELOG.md`를 추가했다.
- 각 모듈의 `module.php`에 `toycore.min_version`과 `toycore.tested_with` 메타데이터를 추가했다.
- Toycore 본체의 선택 모듈 `modules/{module_key}` 복사본은 제거했다.
- 각 모듈 리포지토리에 설치용 zip 산출물 생성 스크립트를 추가했다.
- Toycore 본체에 공식 모듈 release zip 수집, registry checksum 갱신, GitHub Releases 업로드 보조 스크립트를 추가했다.

현재 상태에서의 원본 기준:

- Toycore 본체 런타임 기준은 core/member/admin이다.
- 선택 모듈의 원본은 별도 리포지토리의 `module/` 디렉터리다.
- Toycore 본체 배포 패키지는 `minimal`, `standard`, `ops`로 나눠 생성한다.
- `standard`와 `ops` 패키지는 외부 모듈 리포지토리의 `module/` 디렉터리를 `modules/{module_key}`로 복사해 조립한다.
- 각 배포 패키지는 포함 모듈 버전을 `distribution-manifest.json`에 기록한다.
- 운영자는 `/admin/modules`에서 모듈 zip을 업로드해 신규 모듈 파일을 추가하거나 기존 모듈 파일을 교체할 수 있다.
- 공식 release zip은 `docs/module-index.json`에 URL과 checksum을 등록하면 `/admin/modules`에서 다운로드해 같은 검증 흐름으로 반영할 수 있다.
- GitHub repository archive 반영은 고급 경로로 둔다. 운영 환경에서는 `repository_refs`에 40자 commit SHA와 sha256 checksum 쌍이 등록된 archive만 허용하고, `main`, `develop`, tag 같은 가변 ref는 개발/스테이징에서만 사용한다.
- 공식 registry에 등록된 GitHub repository는 owner가 고급 UI에서 ref를 지정해 archive zip으로 다운로드할 수 있다.
- 기존 모듈 파일 교체 시 이전 디렉터리는 `storage/module-backups`에 보관하고, DB 업데이트는 `/admin/updates`에서 별도로 적용한다.
- 설치 소스와 업데이트 보완 계획은 [모듈 설치 소스와 업데이트 보완 계획](module-update-and-source-plan.md)을 따른다.

분리 우선순위가 높다:

- 특정 사이트에서만 필요한 도메인 기능
- 릴리스 주기가 코어와 다른 기능
- 외부 서비스 연동이 있는 기능
- 운영 정책이 사이트마다 크게 갈리는 기능
- 테이블과 관리자 화면이 독립적인 기능

분리 우선순위가 낮다:

- 코어 helper 계약을 검증하기 위한 최소 예제
- 설치 직후 관리자 접속에 필수인 기능
- 아직 계약이 자주 바뀌는 실험 기능

## 2. 장점

별도 리포지토리의 장점:

- 코어가 도메인 기능으로 커지는 속도를 늦출 수 있다.
- 모듈별 이슈, 릴리스, 문서를 독립적으로 관리할 수 있다.
- 사이트마다 필요한 모듈만 가져가는 배포가 쉬워진다.
- 외부 기여자가 특정 모듈만 이해하고 기여할 수 있다.
- 유료/비공개/실험 모듈과 공개 코어를 분리할 수 있다.
- 코어의 보안/설치 안정성과 선택 기능의 변화 속도를 분리할 수 있다.

## 2-1. 효율성과 효과 평가

현재 전략은 철회보다 유지가 낫다. 다만 분리된 리포지토리를 일반 운영자에게 직접 노출하면 효율이 급격히 떨어지므로, 공식 배포 zip을 기본 UX로 삼아야 한다.

평가:

```text
프로젝트 목표 부합도: 높음
코어 경계 유지 효과: 높음
개발 효율: 중상
운영자 설치 편의: 아직 낮음
릴리스 체계 완성도: 보완 필요
```

효과적인 지점:

- toycore.git이 core/member/admin 중심으로 작아졌다.
- SEO, 팝업, 포인트, 예치금, 배너, 알림 같은 도메인 기능이 코어에서 빠졌다.
- 모듈별 이슈, 버전, 릴리스를 독립적으로 관리할 수 있다.
- 향후 유료, 비공개, 실험 모듈을 공개 코어와 분리하기 쉽다.

비효율적인 지점:

- 최초 설치 때 운영자가 어떤 zip을 받아야 하는지 헷갈릴 수 있다.
- standard/ops 배포 zip을 릴리스하지 않으면 여러 리포지토리를 직접 다루는 부담이 생긴다.
- 모듈 업데이트는 파일 버전, DB 버전, SQL 적용 버전을 함께 관리해야 한다.
- 조합 테스트와 release note 작성 책임이 릴리스 담당자에게 생긴다.

결론:

- 모듈 리포지토리 분리는 유지한다.
- 일반 운영자에게는 분리된 repository를 기본 설치 경로로 안내하지 않는다.
- 기본 안내는 `toycore-standard.zip` 다운로드와 웹 설치 화면 진입으로 둔다.
- `toycore-minimal.zip`은 작게 시작하려는 개발자 또는 고급 운영자용으로 설명한다.
- 철회 조건은 공식 번들 zip, 모듈 zip, 업데이트 UX를 제공하지 않을 때다. 이 경우 분리는 운영 부담만 키우므로 재통합을 검토한다.

## 3. 단점과 위험

별도 리포지토리의 위험:

- 코어와 모듈 계약 버전이 어긋날 수 있다.
- 설치 문서와 예제가 분산된다.
- 테스트 조합이 늘어난다.
- 모듈 간 의존성 지옥이 생길 수 있다.
- 배포자가 여러 저장소를 직접 내려받아야 할 수 있다.
- Composer 중심으로 흐르면 공유호스팅 목표와 충돌할 수 있다.
- 모듈이 코어 내부 구현에 기대기 시작하면 분리 효과가 사라진다.

따라서 분리는 “Git 저장소를 나누는 일”보다 “계약을 안정화하는 일”이 먼저다.

## 4. 권장 리포지토리 구조

모듈 단독 리포지토리 루트는 Toycore의 `modules/{module_key}` 내부 구조와 거의 같게 둔다.

```text
toycore-module-board/
- README.md
- CHANGELOG.md
- LICENSE
- module/
  - module.php
  - helpers.php
  - paths.php
  - admin-menu.php
  - extension-points.php
  - privacy-export.php
  - sitemap.php
  - actions/
  - views/
  - lang/
  - install.sql
  - updates/
- tests/ (optional)
- docs/ (optional)
```

배포 산출물은 다음처럼 복사 가능해야 한다.

```text
toycore-module-board/module/* -> toycore/modules/board/*
```

이 구조를 권장하는 이유:

- 리포지토리 메타 파일과 실제 모듈 파일을 분리할 수 있다.
- Toycore 설치본에는 `module/` 내부만 복사하면 된다.
- Composer 없이도 zip 다운로드와 FTP 업로드로 설치할 수 있다.
- 테스트와 문서는 모듈 리포지토리에 남기고 런타임 파일만 배포할 수 있다.

대안으로 리포지토리 루트가 곧 모듈 루트인 구조도 가능하다.

```text
toycore-board/
- module.php
- paths.php
- install.sql
- actions/
- views/
```

이 방식은 단순하지만 README, 테스트, 배포 스크립트가 런타임 파일과 섞인다. 초기 공개 모듈에는 `module/` 하위 구조가 더 관리하기 쉽다.

## 5. 모듈 메타데이터 기준

별도 리포지토리 모듈의 `module.php`에는 호환 정보를 명시하는 것을 권장한다.

```php
<?php

return [
    'name' => 'Board',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => 'Board module for Toycore.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
    'requires' => [
        'modules' => [
            'member',
            'admin',
        ],
    ],
    'contracts' => [
        'provides' => [
            'admin-menu.php',
            'extension-points.php',
            'privacy-export.php',
            'sitemap.php',
        ],
    ],
];
```

`toycore.module_contract`는 관리자 모듈 zip 업로드와 외부 모듈 CI에서 현재 코어의 `TOY_MODULE_CONTRACT_VERSION`과 비교한다. 코어가 모듈 파일 구조나 메타데이터 정책을 호환되지 않게 바꾸면 이 값을 올리고, 외부 모듈은 새 계약에 맞춘 뒤 메타데이터를 갱신한다.

외부 모듈 저장소는 `docs/module-ci-template.yml`을 `.github/workflows/check.yml`로 복사해 Toycore 코어와의 기본 호환성을 자동 검증한다.

## 6. 버전 정책

Toycore 모듈은 날짜 기반 버전을 권장한다.

```text
2026.05.001
2026.05.002
2026.06.001
```

규칙:

- `module.php`의 `version`은 코드 기준 현재 버전이다.
- 모듈 버전 표기는 `YYYY.MM.NNN` 형식을 사용한다.
- 릴리스 zip 이름은 `{module_key}-{module.php version}.zip`을 기준으로 한다.
- 각 모듈 리포지토리의 `./.tools/bin/package-module`은 인자가 없으면 `module/module.php`의 `version`을 읽어 패키지 버전으로 쓴다.
- SQL 변경이 있으면 `updates/{version}.sql`을 추가한다.
- `install.sql`은 항상 최신 신규 설치 구조를 담는다.
- 이미 배포한 update SQL은 가능하면 수정하지 않는다.
- 같은 버전의 SQL checksum이 배포 후 바뀌지 않게 관리한다.
- 코어 호환성 변경은 CHANGELOG에 명시한다.

릴리스 태그 예:

```text
v2026.05.001
```

## 7. 설치 방식

Toycore에서 Git 리포지토리는 개발과 릴리스 관리를 위한 도구다. 운영 설치 방식은 Git 사용을 전제로 하지 않는다.

기본 원칙:

- 개발자는 별도 Git 리포지토리에서 모듈을 개발한다.
- 릴리스 시점에는 운영자가 업로드할 수 있는 zip 산출물을 만든다.
- 운영자는 zip을 내려받아 `modules/{module_key}`에 업로드한다.
- Toycore는 `/admin/modules`와 `/admin/updates`로 설치/활성화/업데이트를 처리한다.
- Git, Composer, SSH, CLI가 없어도 설치할 수 있어야 한다.

Toycore 본체 배포 패키지는 `minimal`, `standard`, `ops`로 나눈다. 공식 포함 모듈과 설치 화면 기본 선택 모듈은 `docs/distributions.json`을 기준으로 한다.

패키지 생성 명령:

```sh
./.tools/bin/package-distributions 2026.05.001
```

공식 모듈 zip을 모아 registry checksum을 갱신하고 GitHub Release에 업로드할 때:

```sh
./.tools/bin/publish-module-release 2026.05.001
```

선택 모듈 리포지토리는 기본적으로 toycore.git과 같은 상위 디렉터리에 있어야 한다.

```text
ai/
- toycore/
- toycore-module-seo/
- toycore-module-popup-layer/
```

다른 위치를 쓰는 릴리스 환경에서는 `TOYCORE_MODULE_REPO_ROOT`로 모듈 리포지토리 상위 디렉터리를 지정한다.

```sh
TOYCORE_MODULE_REPO_ROOT=/release/modules ./.tools/bin/package-distributions 2026.05.001
```

minimal 패키지에는 필수 실행 기반만 들어간다. standard/ops 패키지를 만들 때 필요한 외부 모듈 리포지토리를 찾지 못하면 패키징은 실패해야 한다.

### 수동 복사

가장 공유호스팅 친화적인 방식이다.

```text
1. GitHub Releases 또는 배포 페이지에서 모듈 zip 다운로드
2. zip 압축 해제
3. 압축 안의 모듈 디렉터리를 toycore/modules/{module_key}/에 업로드
4. /admin/modules에서 설치
5. 필요하면 활성화
```

zip은 압축을 풀었을 때 바로 모듈 키 디렉터리가 나오도록 만든다.

```text
banner-2026.05.001.zip
-> banner/
   - module.php
   - install.sql
   - paths.php
   - actions/
   - views/
```

모듈 리포지토리에서 설치용 zip 생성:

```sh
./.tools/bin/package-module
```

특정 버전을 명시해 다시 만들 때:

```sh
./.tools/bin/package-module 2026.05.001
```

업로드 후 Toycore 설치본에서는 다음 구조가 되어야 한다.

```text
toycore/modules/banner/
- module.php
- install.sql
- paths.php
- actions/
- views/
```

모듈 리포지토리의 내부 구조가 `module/` 하위에 런타임 파일을 두는 방식이라면, 릴리스 zip을 만들 때 `module/` 디렉터리 이름은 제거하고 `{module_key}/`로 패키징한다.

```text
toycore-module-banner/module/* -> banner/*
```

장점:

- Git, Composer, SSH가 없어도 된다.
- FTP와 파일 관리자만으로 가능하다.
- 현재 Toycore의 `/admin/modules` 설치 흐름을 그대로 사용할 수 있다.

단점:

- 버전 추적과 업데이트 실수 가능성이 있다.
- 파일 삭제가 필요한 릴리스에서 잔여 파일이 남을 수 있다.

업데이트 방식:

```text
1. 새 버전 zip 다운로드
2. 기존 toycore/modules/{module_key}/ 파일을 새 파일로 교체
3. /admin/updates에서 미적용 SQL 확인
4. DB 백업 확인 후 업데이트 실행
```

업데이트 zip에는 최신 `install.sql`과 누적 `updates/` 파일이 함께 들어 있어야 한다. 운영자가 Git 이력을 볼 수 없더라도 `/admin/updates`가 SQL 파일 기준으로 필요한 업데이트를 확인할 수 있어야 한다.

### Git submodule

개발자에게는 편하지만 일반 운영자에게는 어렵다.

```text
modules/board -> git submodule
```

장점:

- 코어 리포지토리와 모듈 커밋을 고정할 수 있다.
- 개발 환경에서 변경 추적이 명확하다.

단점:

- 공유호스팅 배포와 맞지 않는다.
- submodule 사용 경험이 없으면 운영 실수가 많다.

권장 용도:

- 코어 개발자 로컬 환경
- 공식 번들 구성 테스트
- CI 조합 검증

### Git subtree 또는 vendor import

코어 리포지토리에 모듈 코드를 복사해 넣되, 원본 이력을 어느 정도 유지하는 방식이다.

장점:

- 배포 산출물은 단일 리포지토리처럼 단순하다.
- submodule보다 운영이 쉽다.

단점:

- 원본 모듈 리포지토리와 동기화 절차가 필요하다.
- 잘못 쓰면 코어 PR에 모듈 변경이 섞인다.

권장 용도:

- 공식 배포판에 선택 모듈을 함께 싣는 경우
- 릴리스 시점에 검증된 모듈 버전을 고정하는 경우

### Composer package

PHP 생태계에는 익숙하지만 Toycore의 기본 배포 방식으로는 권장하지 않는다.

장점:

- 버전 제약과 다운로드가 표준화된다.
- 개발자 환경에서는 편하다.

단점:

- Composer가 없는 공유호스팅에서 부담이 된다.
- 자동 discovery를 붙이고 싶은 유혹이 생긴다.
- `vendor/` 기반 autoload가 기본 실행 흐름을 흐릴 수 있다.

권장 용도:

- 개발자용 보조 설치
- CI 테스트
- 선택적 mirror 배포

Composer를 쓰더라도 런타임은 `modules/{module_key}`에 복사된 PHP 파일과 SQL로 동작해야 한다.

## 8. 공식 모듈 인덱스

장기적으로는 코어 리포지토리에 공식 모듈 인덱스를 둘 수 있다.

예:

```text
docs/module-index.json
```

또는 별도 리포지토리:

```text
toycore-module-index
```

예시 구조:

```json
{
  "modules": [
    {
      "module_key": "board",
      "name": "Board",
      "repository": "https://github.com/example/toycore-module-board",
      "latest_version": "2026.05.001",
      "min_toycore_version": "2026.04.005",
      "type": "module",
      "category": "content"
    }
  ]
}
```

주의:

- 인덱스는 다운로드 안내와 호환성 표시용으로 시작한다.
- 원격 코드를 관리자 화면에서 자동 다운로드/실행하는 기능은 초기 범위에서 제외한다.
- 자동 설치를 붙이기 전에 서명, checksum, 권한, 백업, 롤백 정책이 필요하다.

## 9. 호환성 테스트 전략

별도 리포지토리 모듈은 최소한 다음 조합을 확인해야 한다.

- 신규 설치 SQL이 비어 있지 않은가?
- `module.php`가 배열을 반환하는가?
- `paths.php`가 있으면 action 경로가 안전한가?
- `admin-menu.php` path가 `paths.php`의 GET route와 맞는가?
- PHP lint를 통과하는가?
- Toycore 최신 main 또는 지정 min version에서 `/admin/modules` 설치가 되는가?
- 비활성 상태에서 route가 열리지 않는가?
- update SQL이 순서대로 적용되는가?

코어 리포지토리의 `./.tools/bin/check`와 같은 수준의 모듈용 점검 스크립트를 각 모듈 리포지토리에 두는 것을 권장한다.

```text
.tools/bin/check
```

단, 이 스크립트도 Docker가 없는 환경에서는 가능한 정적 검사를 먼저 수행해야 한다.

## 10. 분리 전 체크리스트

모듈을 별도 리포지토리로 분리하기 전에 확인한다.

- 코어 내부 helper에 과하게 기대지 않는가?
- 필요한 코어 helper가 공개 계약으로 설명되어 있는가?
- 다른 모듈 테이블을 직접 변경하지 않는가?
- 설치 SQL과 업데이트 SQL이 자기 테이블만 다루는가?
- 관리자 메뉴, route, extension point가 계약 파일로 정리되어 있는가?
- README에 설치 경로와 의존 모듈이 적혀 있는가?
- CHANGELOG에 버전별 DB 변경이 적혀 있는가?
- 개인정보 내보내기와 sitemap이 필요한 경우 계약 파일이 있는가?
- 코어 리포지토리에서 제거해도 필수 설치 흐름이 깨지지 않는가?

## 10-1. 새 모듈 추가 전 리포지토리 확인 규칙

새 모듈을 추가할 때는 구현 전에 먼저 저장 위치를 결정한다.

코어 리포지토리에 바로 추가해도 되는 경우:

- `member` 또는 `admin`처럼 필수 설치 흐름에 직접 필요한 모듈
- 모듈 계약을 설명하기 위한 샘플
- 아직 공개 계약이 안정되지 않아 실험용으로 짧게 검증해야 하는 모듈

별도 리포지토리를 먼저 요청해야 하는 경우:

- 도메인 테이블과 관리자 화면을 소유하는 선택 모듈
- 운영/마케팅/콘텐츠/커머스/분석처럼 사이트마다 필요 여부가 갈리는 모듈
- 외부 서비스 연동이 있는 모듈
- 독립 릴리스가 예상되는 모듈
- 나중에 유료, 비공개, 고객별 변형 가능성이 있는 모듈

작업자가 새 모듈을 만들 때 별도 리포지토리가 필요하다고 판단하면, 구현 전에 리포지토리 생성을 요청한다.

요청 형식:

```text
새 모듈 {module_key}는 별도 리포지토리 대상입니다.
다음 리포지토리를 만들어 주세요:
git@github.com:whitedot/toycore-module-{module-key}.git
```

리포지토리가 이미 있다면 해당 원격을 사용한다. 리포지토리가 아직 없다면 코어 리포지토리에 임시로 크게 구현하지 않고, 최소 설계와 파일 계약만 먼저 정리한다.

## 11. 권장 전환 단계

### 1단계: 문서와 계약 안정화

- `module-guide.md`를 기준으로 파일 계약을 고정한다.
- 기본 모듈이 계약을 같은 방식으로 사용하도록 맞춘다.
- 모듈 작성 샘플을 최신 규칙에 맞춘다.

### 2단계: 선택 모듈부터 분리 실험

후보:

```text
banner
popup_layer
site_menu
notification
```

이 단계에서는 원본 리포지토리 구조와 배포 zip 구조를 검증하고, 코어 리포지토리에서는 선택 모듈 복사본을 제거한다.

### 3단계: 공식 번들 개념 도입

코어는 최소 배포와 번들 배포를 구분한다.

```text
minimal = 필수 실행 기반
standard/ops = 검증된 선택 모듈 묶음
```

설치 화면은 번들 모듈을 선택할 수 있지만, 코어 설계상 필수로 보지 않는다.

### 4단계: 모듈 인덱스와 호환성 표시

- 공식 모듈 목록을 문서 또는 JSON으로 제공한다.
- 관리자 화면에서 설치된 모듈의 코드 버전, DB 버전, 호환 정보를 보여준다.
- checksum이 등록된 공식 release zip과 registry 등록 GitHub repository archive를 owner 전용으로 내려받을 수 있다.

### 5단계: 선택적 자동 설치 검토

자동 설치는 다음 조건이 갖춰진 뒤 검토한다.

- 릴리스 zip checksum 검증
- 관리자 권한과 CSRF 보호
- 설치 전 백업 안내
- 실패 marker 기록
- 기존 파일 덮어쓰기 전 호환성 검사
- 웹서버 쓰기 권한이 없는 호스팅을 위한 수동 fallback

## 12. 현재 프로젝트에 대한 판단

현재 Toycore는 이미 기본 제공 선택 모듈이 늘어난 상태다. 이 자체가 나쁘지는 않지만, 프로젝트 목표가 “작고 읽히는 코어”라면 다음 기준을 세우는 것이 좋다.

- 필수 설치 단위는 계속 `core + member + admin`으로 유지한다.
- 선택 모듈은 코어 리포지토리에 있더라도 코어 기능으로 설명하지 않는다.
- README의 기본 설치 범위와 실제 설치 화면의 기본 선택값을 일치시킨다.
- 운영/마케팅 성격이 강한 모듈은 별도 리포지토리 후보로 본다.
- 코어 helper는 모듈이 공통으로 쓰는 최소 도구만 제공한다.

권장 결론:

```text
지금 당장 모든 모듈을 분리하지 않는다.
먼저 계약 파일과 모듈 작성 규칙을 안정화한다.
그 다음 banner/popup_layer/site_menu/notification 같은 선택 모듈부터 별도 리포지토리 실험을 한다.
최종 배포는 minimal과 standard 번들을 구분한다.
```
