# 외부 모듈 제작 빠른 시작

이 문서는 Toycore 외부 모듈을 처음 만드는 개발자를 위한 시작 문서다. GitHub Actions나 CI를 몰라도 된다. 먼저 폴더를 만들고, 로컬에서 점검한 뒤, zip으로 묶어 Toycore 관리자에서 업로드하는 흐름을 익힌다.

자세한 파일 역할과 정책은 [모듈 작성 가이드](module-guide.md)를 본다. 릴리스 직전에는 [모듈 체크리스트](module-checklist.md)를 확인한다.

## 1. 저장소 구조

외부 모듈 저장소는 보통 다음 구조를 사용한다.

```text
toycore-module-banner/
- README.md
- CHANGELOG.md
- .tools/bin/package-module
- module/
  - module.php
  - install.sql
```

Toycore에 업로드되는 실제 모듈 코드는 `module/` 아래에 둔다. 릴리스 zip을 만들 때는 압축을 풀었을 때 `{module_key}/module.php` 구조가 나오게 만든다.

```text
banner-2026.05.001.zip
-> banner/
   - module.php
   - install.sql
```

Toycore 저장소가 이미 있다면 기본 구조를 스캐폴딩 도구로 만들 수 있다.

```sh
php .tools/bin/create-external-module.php banner ../toycore-module-banner
```

이 명령은 `README.md`, `CHANGELOG.md`, `.tools/bin/package-module`, `module/module.php`, `module/install.sql`, `.github/workflows/check.yml`을 만든다. 기존 파일은 덮어쓰지 않으므로 빈 디렉터리를 대상으로 실행한다.

GitHub Actions를 아직 쓰지 않으려면 자동 점검 파일을 빼고 만든다.

```sh
php .tools/bin/create-external-module.php banner ../toycore-module-banner --no-ci
```

## 2. module.php 작성

최소 예시는 다음과 같다.

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

## 3. install.sql 작성

모듈이 설치될 때 실행할 SQL을 `module/install.sql`에 둔다. 아직 테이블이 없는 모듈도 빈 파일이 아니라 설명 주석을 둔다.

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

## 4. 로컬 점검

zip을 만들기 전에 Toycore가 이 모듈을 읽을 수 있는지 확인한다.

아래 예시는 `toycore`와 모듈 저장소가 같은 상위 폴더에 나란히 있는 구조를 기준으로 한다.

```text
work/
- toycore/
- toycore-module-banner/
```

```sh
git clone https://github.com/whitedot/toycore.git toycore
cd toycore
git checkout v0.1.1
php .tools/bin/check-external-module.php ../toycore-module-banner/module banner
```

이미 `toycore` 저장소 안에 있다면 마지막 명령만 실행하면 된다. 모듈 저장소 위치가 다르면 `../toycore-module-banner/module` 부분만 실제 `module/` 폴더 경로로 바꾼다.

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

스캐폴딩 도구로 만든 저장소라면 다음 명령으로 같은 구조의 zip을 만들 수 있다.

```sh
./.tools/bin/package-module 2026.05.001
```

Windows처럼 실행 권한 개념이 다른 환경에서는 다음처럼 실행해도 된다.

```sh
php .tools/bin/package-module 2026.05.001
```

이 명령은 `dist/{module_key}-2026.05.001.zip`을 만든다. PHP `ZipArchive` 확장이 없는 환경에서는 수동으로 같은 구조의 zip을 만든다.

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

## 7. 자동 점검은 나중에 켜도 된다

CI는 배포가 아니다. CI는 로컬에서 실행하던 모듈 점검 명령을 GitHub가 push할 때 대신 실행해 주는 자동 점검이다.

처음에는 GitHub Actions를 몰라도 된다. 로컬 점검 명령에 익숙해진 뒤 [모듈 자동 점검 빠른 시작](module-ci-quickstart.md)을 보고 켠다.
