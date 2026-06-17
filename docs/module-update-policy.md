# 모듈 배치와 업데이트 기준

이 문서는 산란 모듈 파일 배치, 설치, 업데이트 흐름의 기준을 정리한다.

## 원칙

산란은 모듈 소스의 출처를 관리하지 않는다. 현재 `modules/{module_key}`에 놓인 폴더를 읽고, DB에는 설치 상태와 SQL 적용 상태만 기록한다.

모듈 설치, 상태 변경, 파일 전용 버전 반영, pending SQL 계산, SQL 적용, 모듈 소스 zip 검증 같은 수명주기 실행 기준은 코어 helper가 제공한다. helper 책임은 파일 단위로 나눈다. `core/helpers/schema-updates.php`는 update 파일 탐색, checksum, lock, 적용 기록을 맡고, `core/helpers/module-source.php`는 zip 업로드 검증과 파일 배치/백업/복구를 맡고, `core/helpers/module-metadata.php`는 `module.php` 정적 metadata 읽기를 맡고, `core/helpers/module-lifecycle.php`는 설치/상태 변경/version sync와 lifecycle 상태 계산을 맡는다. 기본 `/admin/modules`와 `/admin/updates` 화면은 이 코어 helper를 호출하는 운영 UI이며, 관리자 화면 row 조립, 소유자 권한 확인, owner 재인증, 감사 로그, 결과 표시를 맡는다. `/admin/modules`는 설치된 PHP 실행 코드를 바꿀 수 있는 화면이므로 메뉴별 권한으로 위임하지 않고 소유자만 접근한다. 별도 관리자 UI를 만들더라도 같은 코어 helper를 호출해야 DB 상태와 업데이트 판정이 분기되지 않는다.

```text
파일 기준:
modules/{module_key}/module.php
modules/{module_key}/install.sql
modules/{module_key}/updates/*.sql

DB 기준:
sr_modules
sr_schema_versions
```

## 모듈 배치 방식

공유호스팅과 일반 운영자를 위한 기본 방식은 파일 배치다.

```text
1. 모듈 zip을 준비한다.
2. `/admin/modules`에서 모듈 파일 반영을 일시 허용한 뒤 zip 업로드를 실행하거나 FTP/파일 관리자로 배치한다.
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

모듈을 단독 배포할 때 산란 런타임이 읽는 범위는 최종 배치된 `modules/{module_key}` 폴더다. 따라서 같은 모듈 key를 유지하는 교체 배포물은 해당 모듈 폴더 안의 런타임 파일, 계약 파일, asset, `install.sql`, `updates/`만 포함하면 된다. 초기 설치 화면의 선택 모듈 목록, 관리자 공통 라벨, 저장소 문서, 점검 스크립트처럼 모듈 폴더 밖에 있는 파일은 본체 릴리스와 함께 관리한다.

예를 들어 `community` 모듈을 같은 key로 새 구현으로 교체한다면 배포 zip은 `community/module.php`, `community/install.sql`, `community/paths.php`, 필요한 `community/actions/`, `community/views/`, `community/helpers/`, 계약 파일, `community/updates/`를 포함한다. `/community`와 `/admin/community/...` URL을 유지하려면 `paths.php`에서 새 action 파일로 다시 매핑한다. 모듈 key나 관리자 설정 경로를 바꾸는 경우에는 본체/관리자 문서와 일부 공통 안내도 함께 갱신해야 한다.

프로젝트 폴더가 `module/` 하위에 런타임 파일을 두는 구조라면 zip 업로드 시 module key를 입력해 `modules/{module_key}`로 반영할 수 있다. 다만 산란 안에 들어온 뒤의 기준은 항상 `modules/{module_key}`다.

Git을 사용할 수 있는 운영자는 전체 브랜치를 병합하지 않고 특정 릴리스 태그나 원격 브랜치에서 필요한 모듈 폴더만 갱신할 수 있다. 예를 들어 포인트 모듈만 태그 기준으로 갱신하려면 다음처럼 `modules/point` 경로만 작업 트리에 반영한다.

```sh
git fetch origin --tags
git checkout v2026.05.001 -- modules/point
```

원격 브랜치의 최신 포인트 모듈만 반영해야 한다면 다음처럼 사용할 수 있다.

```sh
git fetch origin
git checkout origin/main -- modules/point
```

이 방식은 파일 배치의 다른 형태일 뿐이다. 산란은 Git ref를 직접 조회하거나 선택하지 않고, 운영자가 최종적으로 배치한 `modules/{module_key}` 폴더만 읽는다. 모듈 파일을 교체한 뒤에는 zip이나 FTP 배치와 같은 업데이트 절차를 따른다.

## zip 업로드 검증

`/admin/modules`의 zip 업로드는 소유자가 모듈 파일 반영을 일시 허용한 뒤 사용할 수 있다. 일시 허용과 zip 업로드 요청은 각각 소유자 비밀번호 재확인을 요구하며, 업로드 요청이 성공하거나 검증 실패로 끝나면 `admin.module_sources_enabled` 값을 bool false로 다시 저장한다. 허용 상태가 켜진 동안에는 운영자가 수동으로 허용을 닫을 수도 있다.

`/admin/modules`의 zip 업로드는 다음만 담당한다.

- 압축 해제 후 모듈 폴더 찾기
- `module.php` 메타데이터 정적 읽기와 정적 리터럴 배열 확인
- `install.sql` 존재 확인
- module key 형식 확인
- 모듈 계약 버전 확인
- `updates/YYYY.MM.NNN.sql` 파일명과 module.php version 이하 업데이트 버전 확인
- 공개 `assets/` 경로의 PHP 실행 파일과 SQL 파일 차단
- zip 안의 유효 모듈 구조가 하나인지 확인
- zip 항목 수, 압축 해제 크기, 백슬래시 경로, 파일/디렉터리 경로 충돌 제한
- zip 통계 확인, 압축 해제, 모듈 검증 실패 시 작업 디렉터리 정리
- 프로세스 중단 등으로 남은 오래된 zip 업로드 작업 디렉터리는 보관 정책의 세션 보관 기준에 맞춰 정리
- 서버 설정/비밀 파일, 저장소 메타 파일, 우회 실행 확장자 차단
- 활성 모듈 교체 시 다른 활성 모듈과의 라우트 충돌 확인
- 비활성 설치 모듈도 활성화 시 메타데이터, 의존성, 라우트 충돌 재확인
- 기존 모듈 교체 전 owner 확인과 백업
- 낮은 코드 버전 덮어쓰기 기본 차단

zip 업로드는 DB 업데이트를 자동 실행하지 않는다.

zip 업로드 단계는 업로드된 PHP 코드를 실행하지 않고 검사한다. 따라서 contract 파일의 반환 shape나 PHP 문법 전체를 완전히 검증하지 않는다. PHP 문법 검사는 배포 전 `php .tools/bin/check.php` 같은 정적 검사에서 수행하고, 업로드 단계는 파일명, 확장자, 경로, 필수 파일, 메타데이터, 계약 선언처럼 실행 없이 판정할 수 있는 항목을 거부 기준으로 삼는다. `module.php`는 문자열, 숫자, bool, null, 배열 리터럴만 포함한 정적 return 배열이어야 하며 함수 호출이나 동적 expression은 거부한다. zip 하나에는 유효한 모듈 구조가 하나만 있어야 하며, 여러 모듈 구조가 발견되면 자동 선택하지 않고 거부한다. 금지 파일 검사는 선택된 모듈 폴더뿐 아니라 압축 해제된 zip 전체에 적용한다. 예를 들어 `.env`, `.env.*`, `.htaccess`, `.user.ini`, `web.config`, 저장소 메타 파일/디렉터리, 패키지 레지스트리 인증 파일, SSH key/key store 파일, 클라우드·컨테이너·Kubernetes 인증 정보 파일/디렉터리, DB 파일, 백업 파일, `.phar`, `.phtml`, `.pht`, `.php7`, `.php8`, `.cgi`, `.shtml`, shell/binary 실행 파일은 거부한다. 공개 URL로 제공되는 `assets/` 아래에는 정적 자산만 둘 수 있으며 `.php`, `.phar`, `.phtml`, `.php7`, `.php8`, `.sql` 같은 실행 또는 내부 데이터 파일은 거부한다.

## 설치

새 모듈 설치는 현재 배치된 `modules/{module_key}` 폴더를 기준으로 한다.

```text
1. module.php 읽기
2. install.sql 확인
3. sr_modules에 installing 기록
4. install.sql 실행
5. 현재 모듈 버전까지 schema version 기록
6. sr_modules 상태를 enabled 또는 disabled로 변경
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

`/admin/updates`는 현재 배치된 파일만 읽는다. 원격 위치나 외부 배포 정보를 조회하지 않는다.

Git으로 특정 모듈 경로만 갱신한 경우에도 같은 기준을 따른다.

```text
1. git checkout <tag-or-ref> -- modules/{module_key}
2. /admin/modules에서 코드 버전과 설치 버전 차이 확인
3. module.php의 산란 최소 버전과 모듈 계약 버전 확인
4. /admin/updates에서 해당 모듈의 미적용 updates/*.sql 확인
5. DB 백업 확인 후 SQL 업데이트 실행
```

새 모듈 버전의 `saanraan.min_version`이 현재 본체 버전보다 높거나 `saanraan.module_contract`가 현재 `SR_MODULE_CONTRACT_VERSION`과 맞지 않으면 해당 모듈만 단독으로 업데이트하지 않는다. 이 경우 본체와 필요한 기본 모듈을 함께 업데이트한다.

## 완료 판정 기준

모듈 설치, 활성화, 업데이트 흐름은 다음 조건을 모두 만족할 때 완료로 본다.

관리자 화면 상태 표시:

- `/admin/modules`는 소유자 전용 화면이며 `모듈 파일 반영`, `설치 가능한 모듈`, `설치된 모듈` 순서로 한 화면에 표시한다. zip 업로드 버튼은 모듈 파일 반영이 일시 허용된 동안만 표시한다.
- `/admin/modules`의 설치 가능한 모듈과 설치된 모듈은 모듈별 카드로 표시하고, 상세 정보는 각 카드의 상세보기 모달에서 확인한다.
- `/admin/modules`의 설치 가능한 모듈은 카드의 primary `설치` 버튼으로 설치 모달을 열고, 모달에서 설치 후 상태를 확인한 뒤 설치한다.
- `/admin/modules`의 설치된 모듈 상태 변경은 카드 안에서 바로 펼치지 않고 상태 변경 모달에서 현재 상태와 변경할 상태를 확인한 뒤 저장한다.
- `/admin/modules`가 설치된 모듈을 `활성 최신`, `비활성 최신`, `설치 미완료`, `계약 오류`, `SQL 적용 필요`, `파일 전용 업데이트 가능`, `코드 버전 낮음` 같은 운영 상태로 구분한다.
- `/admin/modules`가 미설치 모듈과 설치 차단 모듈을 구분한다.
- `/admin/updates`가 pending SQL, 파일 전용 버전 차이, 코드 버전이 설치 버전보다 낮은 위험 상태를 구분한다.

설치:

- 새 모듈 설치 전 `module.php`, `install.sql`, `saanraan.min_version`, `saanraan.module_contract`, 계약 파일 선언/존재를 확인한다.
- 활성 설치를 선택한 경우 의존 모듈, 계약 의존성, route 충돌을 설치 전 확인한다.
- 설치 중에는 `sr_modules.status = installing`으로 기록한다.
- 설치 성공 후 현재 코드 버전까지 schema version을 기록하고 요청한 상태로 전환한다.
- 설치 실패 시 `failed` 상태를 남기고 운영자가 재설치할 수 있게 한다.
- 설치 SQL 일부가 이미 실행된 상태에서 실패할 수 있으므로 `install.sql`은 반복 실행 가능한 `CREATE TABLE IF NOT EXISTS` 중심으로 작성한다.

활성화와 비활성화:

- `member`, `admin` 기본 모듈은 비활성화할 수 없다.
- `failed`, `installing` 상태는 활성화/비활성화 대신 재설치를 요구한다.
- 활성화 전 메타데이터, 계약 파일, 의존성, route 충돌을 다시 확인한다.
- 설치 버전보다 코드 버전이 낮은 모듈은 활성화하지 않는다.
- 상태 변경은 감사 로그에 남긴다.

파일 교체:

- zip 업로드는 owner 권한, 일시 허용 값, 재인증, ZipArchive 사용 가능 여부를 모두 확인하고 요청 처리 후 모듈 소스 반영 허용 값을 자동으로 끈다.
- zip 항목 수, 압축 해제 크기, 경로 탈출, 백슬래시 경로, 파일/디렉터리 경로 충돌, 심볼릭 링크, 단일 모듈 구조, 서버 설정/비밀 파일, 저장소 메타 파일, 우회 실행 확장자, 필수 파일, 계약 버전, 낮은 코드 버전 덮어쓰기를 검증한다.
- zip 통계 확인, 압축 해제, 모듈 검증 중 실패하면 업로드 작업 디렉터리를 제거한다.
- 프로세스 중단 등으로 `storage/module-upload`에 남은 오래된 `upload-YYYYMMDDHHMMSS-{12자리 hex}` 작업 디렉터리는 보관 정책 정리 대상이다.
- 기존 모듈 파일 교체 전 백업을 만들고, 교체 실패 시 백업 복구를 시도한다.
- 백업 복구에 실패하면 실패 상태로 닫고 오류를 남긴다.
- zip 업로드는 DB SQL 업데이트를 자동 실행하지 않고 파일 배치까지만 수행한다.

업데이트:

- `/admin/updates`는 현재 배치된 `database/core/updates/*.sql`과 설치된 모듈의 `updates/*.sql`만 읽는다.
- SQL 적용 전 백업 확인을 요구한다.
- SQL 적용 전후 checksum을 확인하고, 허용된 update 경로만 실행한다.
- 업데이트 SQL에서 `INFORMATION_SCHEMA.TABLES/COLUMNS/STATISTICS`를 조회하거나 `PREPARE`용 동적 SQL 문자열 안에 테이블명을 넣을 때는 실제 설치 prefix를 반영할 수 있도록 `{{SR_TABLE_PREFIX}}member_accounts`처럼 `{{SR_TABLE_PREFIX}}` placeholder를 사용한다. 일반 SQL 식별자 위치의 `sr_member_accounts`는 런타임 PDO가 설치 prefix로 변환한다.
- 업데이트 실행 중 DB lock을 잡아 중복 실행을 막는다.
- 실패 시 감사 로그와 `storage/update-failed.json` 운영 marker를 남긴다.
- 성공 후 pending SQL이 없는 모듈만 파일 전용 버전 반영을 수행한다.
- pending SQL이 남은 모듈은 단순 version sync를 허용하지 않는다.

정적 검사:

- `.tools/bin/check.php`가 필수 모듈 파일, 모듈 메타데이터, 계약 파일 선언/존재와 일부 반환 shape, update SQL 버전, route 충돌, lifecycle UI 안전장치를 확인한다.
- 배포 전에는 정적 검사와 스모크 테스트를 실행해 관리자 모듈/업데이트 화면이 500 없이 열리는지 확인한다.

## 버전 의미

| 항목 | 의미 | 저장 위치 |
| --- | --- | --- |
| 코드 버전 | 현재 파일이 제공하는 모듈 버전 | `module.php` |
| 설치 버전 | DB에 반영 완료된 모듈 버전 | `sr_modules.version` |
| 스키마 적용 버전 | 실행 완료된 SQL 버전 | `sr_schema_versions` |
| 산란 최소 버전 | 설치 가능한 산란 최소 버전 | `module.php` |
| 모듈 계약 버전 | 파일/메타데이터 계약 버전 | `module.php` |

## 제외한 방향

다음은 기본 구현에서 제외한다.

- 외부 모듈 목록에서 다운로드
- 원격 archive 반영
- 원격 ref 선택 UI
- 배포 zip checksum 색인 관리
- 여러 외부 위치의 모듈을 조립하는 기본 배포 흐름

필요한 경우 릴리스 담당자가 산란 밖의 도구로 처리하고, 산란에는 최종 `modules/{module_key}` 폴더만 배치한다.
