# 외부 모듈 제작 빠른 시작

이 문서는 Toycore 외부 모듈을 처음 만드는 개발자를 위한 시작 문서다. 처음에는 Git, GitHub Actions, 별도 리포지토리를 몰라도 된다. 핵심은 **모듈은 폴더 하나이고, 그 폴더를 zip으로 묶어 Toycore 관리자에서 업로드한다**는 점이다.

자세한 파일 역할과 정책은 [모듈 작성 가이드](module-guide.md)를 본다. 릴리스 직전에는 [모듈 체크리스트](module-checklist.md)를 확인한다.

## 1. 가장 작은 모듈 폴더

외부 모듈의 최소 구조는 다음과 같다.

```text
banner/
- module.php
- install.sql
```

이 폴더를 zip으로 묶었을 때도 바로 모듈 키 디렉터리가 나와야 한다.

```text
banner-2026.05.001.zip
-> banner/
   - module.php
   - install.sql
```

`banner/module.php` 최소 예시는 다음과 같다.

```php
<?php

return [
    'name' => 'Banner',
    'version' => '2026.05.001',
    'type' => 'module',
    'description' => 'Banner module.',
    'toycore' => [
        'min_version' => '0.1.1',
        'tested_with' => ['0.1.1'],
        'module_contract' => '1.0',
    ],
];
```

중요한 값:

- `version`: 모듈 코드 버전이다.
- `toycore.min_version`: 이 모듈을 설치할 수 있는 Toycore 최소 버전이다.
- `toycore.tested_with`: 모듈 릴리스 때 실제로 확인한 Toycore 버전이다.
- `toycore.module_contract`: Toycore가 요구하는 모듈 파일/메타데이터 계약 버전이다.

`banner/install.sql`은 모듈 설치 때 실행할 SQL이다. 아직 테이블이 없는 모듈도 빈 파일이 아니라 설명 주석을 둔다.

```sql
-- Banner module has no tables yet.
```

테이블을 만들 때는 프로젝트 prefix인 `toy_`를 사용한다.

```sql
CREATE TABLE IF NOT EXISTS toy_banner_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id)
);
```

## 2. 선택: 프로젝트 폴더 생성 도구

반복해서 모듈을 만들거나 공개 배포를 준비한다면 Toycore 소스에 있는 생성 도구를 사용할 수 있다. 이 도구는 최소 모듈 파일뿐 아니라 README, AGENTS, CHANGELOG, zip 생성 스크립트까지 갖춘 **모듈 프로젝트 폴더**를 만든다.

```sh
php .tools/bin/create-external-module.php banner /path/to/banner-module
```

생성 결과:

```text
banner-module/
- AGENTS.md
- README.md
- CHANGELOG.md
- .tools/bin/package-module
- module/
  - module.php
  - install.sql
```

`AGENTS.md`는 구현 규칙과 AI 보조 작업의 기준 파일이다. 사람이 직접 구현할 때도, AI 코딩 도구에 작업을 맡길 때도 이 파일의 저장소 구조, 요청 흐름, 보안, 계약 파일, 점검 규칙을 기준으로 삼는다.

GitHub Actions 자동 점검까지 함께 만들고 싶을 때만 `--with-ci`를 붙인다.

```sh
php .tools/bin/create-external-module.php banner /path/to/banner-module --with-ci
```

## 3. 기능 구현

실제 런타임 파일은 모듈 폴더 안에 둔다. 프로젝트 생성 도구를 쓴 경우에는 `module/` 아래에 둔다.

```text
수동 최소 폴더:
banner/
- module.php
- install.sql
- paths.php
- actions/

프로젝트 생성 도구 사용:
banner-module/
- module/
  - module.php
  - install.sql
  - paths.php
  - actions/
```

관리자 화면이나 공개 경로가 필요하면 `paths.php`와 `actions/`를 추가한다. 관리자 메뉴가 필요하면 `admin-menu.php`도 추가한다.

## 4. 로컬 점검

zip을 만들기 전에 Toycore가 이 모듈을 읽을 수 있는지 확인한다. 점검에는 Toycore 소스 경로와 모듈 런타임 폴더 경로가 필요하다. 두 폴더가 같은 위치에 있을 필요는 없다.

수동 최소 폴더를 점검할 때:

```sh
cd /path/to/toycore
php .tools/bin/check-external-module.php /path/to/banner banner
```

프로젝트 생성 도구를 쓴 폴더에서 점검할 때:

```sh
cd /path/to/banner-module
TOYCORE=/path/to/toycore
php "$TOYCORE/.tools/bin/check-external-module.php" module banner
```

Windows PowerShell:

```powershell
Set-Location C:\path\to\banner-module
$env:TOYCORE = 'C:\path\to\toycore'
php "$env:TOYCORE\.tools\bin\check-external-module.php" module banner
```

이 명령이 성공하면 최소한 다음이 맞다는 뜻이다.

- `module.php`가 있다.
- `install.sql`이 있다.
- module key 형식이 맞다.
- `module.php`의 버전 형식이 맞다.
- Toycore 계약 버전이 맞다.
- PHP 문법 오류가 없다.
- `paths.php`의 route key와 action 파일이 맞다.
- 관리자 메뉴가 있으면 `paths.php`의 `GET` route와 맞는다.
- 계약 파일이 있으면 최소 반환 구조가 맞다.

## 5. zip 만들기

처음에는 수동으로 zip을 만들어도 된다. 중요한 것은 압축 해제 구조다.

```text
좋음:
banner/
- module.php
- install.sql

피함:
module/
- module.php
- install.sql
```

프로젝트 생성 도구를 썼다면 다음 명령으로 같은 구조의 zip을 만들 수 있다.

```sh
php .tools/bin/package-module
```

이 명령은 `dist/banner-2026.05.001.zip`을 만든다. PHP `ZipArchive` 확장이 없는 환경에서는 수동으로 같은 구조의 zip을 만든다.

## 6. 관리자에서 업로드

Toycore 관리자에서 다음 흐름으로 반영한다.

```text
1. /admin/modules 이동
2. 모듈 zip 업로드
3. owner 비밀번호 재입력
4. 설치 또는 파일 교체
5. /admin/updates에서 미적용 SQL 확인
```

운영 사이트에서는 업로드 전 DB와 파일 백업을 만든다.

## 7. Git과 CI는 선택이다

혼자 만들고 zip으로 업로드하는 모듈은 별도 Git 리포지토리나 CI 없이도 시작할 수 있다.

다음 상황에서는 별도 Git 리포지토리와 GitHub Actions를 고려한다.

- 팀이 함께 개발한다.
- 공개 배포나 유료 배포를 한다.
- 같은 모듈을 여러 사이트에 반복 배포한다.
- Toycore 버전별 호환성을 자동으로 확인하고 싶다.
- 같은 모듈을 여러 사이트에 반복 배포한다.

CI는 배포가 아니다. CI는 로컬에서 실행하던 모듈 점검 명령을 GitHub가 push할 때 대신 실행해 주는 자동 점검이다. 필요해졌을 때 [모듈 자동 점검 빠른 시작](module-ci-quickstart.md)을 보고 켠다.
