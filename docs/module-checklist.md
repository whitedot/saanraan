# 모듈 체크리스트

이 문서는 모듈을 Toycore 관리자에서 zip으로 업로드하거나 `modules/{module_key}`에 배치하기 전에 확인할 항목이다.

## 기본 파일

- `{module_key}/module.php`가 있다.
- `{module_key}/install.sql`이 있다.
- 모듈 key와 폴더 이름이 일치한다.

## module.php

- `name`이 비어 있지 않다.
- `version`은 `YYYY.MM.NNN` 형식이다.
- `type`은 `module` 또는 `plugin`이다.
- `toycore.min_version`이 있고 현재 Toycore 버전보다 높지 않다.
- `toycore.tested_with`가 비어 있지 않은 배열이다.
- `toycore.module_contract`가 현재 Toycore 계약 버전과 맞다.
- 실제 계약 파일은 모두 `contracts.provides`에 선언되어 있다.
- `contracts.provides`에 선언한 계약 파일은 실제로 존재한다.

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

## 운영 반영

- 운영 DB 백업을 만들었다.
- 운영 파일 백업을 만들었다.
- 업로드한 zip 파일명과 모듈 버전을 기록했다.
- Toycore 버전과 모듈 계약 버전을 기록했다.
- 업로드 후 `/admin/updates`에서 미적용 SQL을 확인했다.
