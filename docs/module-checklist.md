# 모듈 체크리스트

이 문서는 외부 모듈을 zip으로 배포하거나 Toycore 관리자에서 업로드하기 전에 확인할 항목이다.

## 기본 파일

- `module/module.php`가 있다.
- `module/install.sql`이 있다.
- `README.md`가 있다.
- `CHANGELOG.md`가 있다.
- 스캐폴딩 도구를 사용했다면 `.tools/bin/package-module`이 있다.

## module.php

- `name`이 비어 있지 않다.
- `version`은 `YYYY.MM.NNN` 형식이다.
- `type`은 `module` 또는 `plugin`이다.
- `toycore.min_version`이 있다.
- `toycore.tested_with`가 비어 있지 않은 배열이다.
- `toycore.module_contract`가 현재 Toycore 계약 버전과 맞다.

## SQL

- 테이블 이름은 `toy_` prefix를 사용한다.
- `install.sql`은 여러 번 실행해도 실패하지 않도록 `CREATE TABLE IF NOT EXISTS`를 우선 사용한다.
- 이미 배포한 update SQL은 같은 버전에서 내용을 바꾸지 않는다.

## 관리자 화면이 있는 경우

- `admin-menu.php`가 있으면 `paths.php`도 있다.
- `admin-menu.php`의 관리자 path가 `paths.php`에 `GET` route로 있다.
- `paths.php`의 action 경로는 `actions/...php` 형식이고 실제 파일이 있다.
- 관리자 action 시작 부분에서 로그인과 권한을 확인한다.
- 상태 변경 `POST`는 CSRF 검증을 한다.
- 상태 변경은 감사 로그를 남긴다.

## zip 구조

스캐폴딩 도구로 만든 저장소라면 zip을 만들기 전에 다음 명령을 실행한다.

```sh
php .tools/bin/package-module 2026.05.001
```

좋은 구조:

```text
banner-2026.05.001.zip
-> banner/
   - module.php
   - install.sql
```

피할 구조:

```text
banner-2026.05.001.zip
-> module/
   - module.php
   - install.sql
```

## 로컬 점검

zip을 만들기 전에 Toycore 저장소 루트에서 다음 명령을 실행한다.

```sh
php .tools/bin/check-external-module.php ../toycore-module-banner/module banner
```

모듈 저장소 루트에서 실행한다면 Toycore 점검 스크립트 경로와 현재 모듈의 `module/` 경로를 함께 지정한다.

```sh
php ../toycore/.tools/bin/check-external-module.php module banner
```

GitHub Actions를 쓰는 저장소라면 `.github/workflows/check.yml`의 `TOYCORE_MODULE_KEY`와 `TOYCORE_REF`가 현재 모듈과 지원 Toycore 버전에 맞는지 확인한다.

## 운영 반영

- 운영 DB 백업을 만들었다.
- 운영 파일 백업을 만들었다.
- 업로드한 zip 파일명과 모듈 버전을 기록했다.
- Toycore 버전과 모듈 계약 버전을 기록했다.
- 업로드 후 `/admin/updates`에서 미적용 SQL을 확인했다.
