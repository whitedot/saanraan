# 모듈 배치와 업데이트 기준

이 문서는 모듈 파일 배치, 설치, 업데이트 흐름을 정리한다.

## 원칙

Toycore는 모듈 소스의 출처를 관리하지 않는다. 현재 `modules/{module_key}`에 놓인 폴더를 읽고, DB에는 설치 상태와 SQL 적용 상태만 기록한다.

```text
파일 기준:
modules/{module_key}/module.php
modules/{module_key}/install.sql
modules/{module_key}/updates/*.sql

DB 기준:
toy_modules
toy_schema_versions
```

## 모듈 배치 방식

공유호스팅과 일반 운영자를 위한 기본 방식은 파일 배치다.

```text
1. 모듈 zip을 준비한다.
2. /admin/modules에서 업로드하거나 FTP/파일 관리자로 배치한다.
3. 최종 위치가 modules/{module_key}/인지 확인한다.
4. /admin/modules에서 설치한다.
```

zip 구조는 다음을 권장한다.

```text
banner-2026.05.001.zip
-> banner/
   - module.php
   - install.sql
   - paths.php
   - actions/
   - views/
```

프로젝트 폴더가 `module/` 하위에 런타임 파일을 두는 구조라면 zip 업로드 시 module key를 입력해 `modules/{module_key}`로 반영할 수 있다. 다만 Toycore 안에 들어온 뒤의 기준은 항상 `modules/{module_key}`다.

## zip 업로드 검증

`/admin/modules`의 zip 업로드는 다음만 담당한다.

- 압축 해제 후 모듈 폴더 찾기
- `module.php` 메타데이터 정적 읽기
- `install.sql` 존재 확인
- module key 형식 확인
- 모듈 계약 버전 확인
- zip 항목 수와 압축 해제 크기 제한
- 기존 모듈 교체 전 owner 확인과 백업
- 낮은 코드 버전 덮어쓰기 기본 차단

zip 업로드는 DB 업데이트를 자동 실행하지 않는다.

## 설치

새 모듈 설치는 현재 배치된 `modules/{module_key}` 폴더를 기준으로 한다.

```text
1. module.php 읽기
2. install.sql 확인
3. toy_modules에 installing 기록
4. install.sql 실행
5. 현재 모듈 버전까지 schema version 기록
6. toy_modules 상태를 enabled 또는 disabled로 변경
```

설치 실패 시 모듈 상태는 `failed`로 남을 수 있다. 운영자는 DB 상태를 확인한 뒤 재설치한다.

## 업데이트

업데이트는 파일 교체와 DB 업데이트를 분리한다.

```text
1. 새 모듈 파일을 modules/{module_key}에 배치
2. /admin/modules에서 코드 버전 차이 확인
3. /admin/updates에서 미적용 updates/*.sql 확인
4. DB 백업 확인 후 SQL 업데이트 실행
5. SQL이 없거나 적용 완료되면 설치 버전을 코드 버전으로 맞춤
```

`/admin/updates`는 현재 배치된 파일만 읽는다. 원격 저장소나 release 정보를 조회하지 않는다.

## 버전 의미

| 항목 | 의미 | 저장 위치 |
| --- | --- | --- |
| 코드 버전 | 현재 파일이 제공하는 모듈 버전 | `module.php` |
| 설치 버전 | DB에 반영 완료된 모듈 버전 | `toy_modules.version` |
| 스키마 적용 버전 | 실행 완료된 SQL 버전 | `toy_schema_versions` |
| Toycore 최소 버전 | 설치 가능한 Toycore 최소 버전 | `module.php` |
| 모듈 계약 버전 | 파일/메타데이터 계약 버전 | `module.php` |

## 제외한 방향

다음은 기본 구현에서 제외한다.

- 공식 모듈 registry 다운로드
- GitHub repository archive 반영
- repository ref 선택 UI
- release zip checksum registry 관리
- 여러 모듈 저장소를 조립하는 기본 배포 흐름

필요한 경우 릴리스 담당자가 Toycore 밖의 도구로 처리하고, Toycore에는 최종 `modules/{module_key}` 폴더만 배치한다.
