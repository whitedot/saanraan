# 모듈 설치 소스와 업데이트 보완 계획

이 문서는 모듈 리포지토리 분리 이후 운영자가 걱정할 수 있는 설치, 업데이트, 버전 표기, 출처 검증 문제를 정리하고 보완 계획을 정의한다.

## 1. 추정되는 우려

현재 대화와 구조 변경 흐름상 우려 지점은 다음으로 본다.

- toycore.git에는 선택 모듈 복사본이 없으므로, 최초 설치 때 필요한 모듈을 어떻게 포함할지 헷갈릴 수 있다.
- 코어와 모듈을 따로 받아야 한다면 최초 설치부터 번거롭게 느껴질 수 있다.
- 선택 모듈을 최초 설치 화면에서 고를 수 있으려면, 설치 전에 이미 배포본 안에 모듈 코드가 포함되어 있어야 한다.
- 모듈을 zip으로 업로드하거나 public repository에서 가져올 수 있다면, 두 방식의 결과물이 같은 설치 구조를 보장해야 한다.
- 모듈 업데이트 때 파일만 바뀌고 DB 업데이트가 누락되거나, DB만 업데이트되고 파일 버전이 맞지 않을 수 있다.
- `toy_modules.version`, `module.php`의 `version`, `toy_schema_versions`의 적용 SQL 버전이 서로 다른 의미를 가지므로 운영자가 혼동할 수 있다.
- public repository를 허용하면 아무 PHP 코드나 서버에 들어오는 통로가 될 수 있다.
- Git 사용 가능 환경도 있지만, 공유호스팅에서는 Git, SSH, CLI, `exec()`가 막힐 수 있다.
- 여러 모듈 리포지토리와 toycore 배포 패키지의 조합 검증이 릴리스 담당자에게 집중될 수 있다.

## 2. 원칙

- 모듈 리포지토리 분리 전략은 유지하되, 일반 운영자의 기본 설치 경험에는 repository 분리를 노출하지 않는다.
- 일반 운영자의 기본 다운로드 대상은 `toycore-standard.zip`으로 둔다.
- `toycore-minimal.zip`은 작게 시작하려는 고급 선택지로 설명한다.
- Git을 사용할 수 없는 호스팅도 지원해야 하므로 release zip 설치와 업데이트 경로는 유지한다.
- Git을 사용할 수 있는 운영 환경에서는 clone 또는 fork 기반 설치를 권장하고, 릴리스 태그 또는 검증된 commit SHA를 배포 기준으로 둔다. 단, 현재 `toycore.git` 본체만 clone하면 minimal 수준이며, standard/ops Git 설치를 쉽게 하려면 조립 완료된 배포 브랜치나 별도 배포 저장소 같은 기준점이 필요하다.
- 관리자 화면의 public repository 가져오기는 기본 경로가 아니라 owner 전용 고급 경로로 둔다.
- `package-distributions`는 일반 설치자용 명령이 아니라 Toycore 공식 maintainer가 공식 zip과 manifest를 만드는 명령으로 둔다.
- 어떤 소스에서 가져오든 최종 설치 구조는 항상 `modules/{module_key}/`여야 한다.
- 파일 교체와 DB 업데이트 실행은 분리한다.
- 모듈 코드 버전과 DB 적용 버전을 화면에서 동시에 확인할 수 있어야 한다.
- 업데이트는 자동 실행보다 검증, 미리보기, 백업 안내, 명시 실행을 우선한다.

## 3. 설치 소스 우선순위

최초 설치의 기본 UX는 운영자가 여러 리포지토리를 직접 다루지 않게 하는 것이다. 운영자는 가능한 한 하나의 배포 zip을 업로드하고 설치 화면으로 진입해야 한다.

권장 배포 단위:

```text
toycore-minimal.zip
- core + member + admin
- 가장 작은 시작점
- 선택 모듈은 설치 후 추가

toycore-standard.zip
- minimal + seo + site_menu + banner
- 일반 운영자가 처음 설치할 때 권장
- 최초 설치 화면에서 기본 선택 모듈 선택 가능

toycore-ops.zip
- standard + popup_layer + point + deposit + reward + notification
- 운영 기능까지 한 번에 검토할 때 사용
```

따라서 Git을 사용할 수 없는 일반 운영자에게 "toycore.git을 clone하고 필요한 모듈 repository도 각각 clone하라"고 안내하지 않는다. 다만 Git을 사용할 수 있는 운영자에게는 릴리스 zip보다 clone 또는 fork 기반 설치를 권장한다. 이 경우 운영 반영 기준은 `main` 같은 이동 브랜치가 아니라 릴리스 태그 또는 검증된 commit SHA다. 여러 모듈 저장소를 모아 `dist/`를 만드는 작업은 설치 절차가 아니라 공식 배포물을 만드는 릴리스 제작 절차다.

운영자에게 보여줄 기본 흐름:

```text
1. toycore-standard.zip 다운로드
2. 서버에 업로드
3. 웹 설치 화면 접속
4. 필요한 선택 모듈 체크
5. 설치 완료
```

### 1순위: zip 업로드

공유호스팅과 일반 운영자를 위한 기본 방식이다.

```text
module.zip
-> {module_key}/
   - module.php
   - install.sql
   - paths.php
   - actions/
   - views/
```

필수 검증:

- 압축 해제 후 최상위에 `{module_key}/module.php` 또는 `module/module.php`가 있는지 확인
- 업로드 검증 단계에서는 `module.php`를 실행하지 않고 정적으로 읽어 배열 반환 형태인지 확인
- `module.php.version`이 `YYYY.MM.NNN` 형식인지 확인
- `install.sql` 존재 확인
- `module_key`가 안전한 형식인지 확인
- 요청한 module key와 zip 내부 모듈 key가 다르면 중단
- zip 항목 수와 압축 해제 후 총 크기가 허용 범위 안인지 확인
- 업로드 zip의 sha256 checksum을 감사 로그에 기록
- 기존 `modules/{module_key}` 디렉터리가 있으면 owner가 백업과 파일 교체를 명시적으로 확인한 경우에만 교체
- 기존 설치 모듈이면 현재 코드 버전보다 낮은 버전으로 기본 덮어쓰기를 차단
- 낮은 버전 덮어쓰기는 owner가 명시적으로 허용한 경우에만 진행

### 2순위: 공식 registry 또는 release zip 다운로드

운영자가 직접 zip을 내려받지 않아도 되는 확장 방식이다.

필수 검증:

- registry에 등록된 모듈만 다운로드
- release zip URL과 checksum 확인
- 다운로드 후 zip 업로드와 동일한 구조 검증 적용
- registry에는 최소한 `module_key`, `latest_version`, `min_toycore_version`, `zip_url`, `checksum`을 둔다.

### 3순위: public repository 가져오기

Git 사용 가능 환경을 위한 owner 전용 고급 기능이다.

제약:

- 기본 UI에서는 접어 둔다.
- owner 권한에서만 실행한다.
- 우선 `https://github.com/whitedot/toycore-module-*` 또는 공식 registry에 등록된 public repository만 허용한다.
- `main` 브랜치 직접 설치보다 tag 또는 release 기준을 권장한다.
- repository archive 결과를 바로 실행하지 않고 임시 디렉터리에서 구조 검증 후 복사한다.
- Git 명령이나 `exec()`에 의존하지 않고 GitHub archive zip 다운로드를 사용한다.

## 4. 업데이트 흐름

모듈 업데이트는 파일 업데이트와 DB 업데이트를 분리한다.

```text
1. 새 모듈 소스 확보
2. 임시 디렉터리에 압축 해제 또는 clone
3. module.php, install.sql, updates/ 검증
4. 현재 설치 버전과 새 코드 버전 비교
5. 기존 modules/{module_key}/ 백업
6. 새 파일을 modules/{module_key}/에 반영
7. /admin/updates에서 미적용 SQL 확인
8. 운영자가 DB 백업 확인 후 SQL 업데이트 실행
9. 성공 시 toy_modules.version을 코드 버전에 맞춤
10. 실패 시 marker와 로그로 재시도 경로 제공
```

파일 교체 단계에서는 SQL을 자동 실행하지 않는다. SQL 실행은 `/admin/updates`에서 명시적으로 진행한다.

## 5. 버전 의미

버전 표기는 다음처럼 구분한다.

| 항목 | 의미 | 저장 위치 |
| --- | --- | --- |
| 코드 버전 | 현재 파일이 제공하는 모듈 버전 | `modules/{module_key}/module.php`의 `version` |
| 설치 버전 | DB에 설치/반영 완료된 모듈 버전 | `toy_modules.version` |
| 스키마 적용 버전 | 실행 완료된 SQL 파일 버전 | `toy_schema_versions` |
| Toycore 최소 버전 | 이 모듈을 설치할 수 있는 최소 Toycore 버전 | `module.php`의 `toycore.min_version` |
| Toycore 검증 버전 | 모듈 릴리스 시 검증한 Toycore 버전 | `module.php`의 `toycore.tested_with` |
| 모듈 계약 버전 | 코어가 요구하는 모듈 파일/메타데이터 계약 | `module.php`의 `toycore.module_contract` |

관리자 화면에서는 최소한 다음을 함께 보여준다.

```text
module_key
설치 버전
코드 버전
Toycore 최소 버전
Toycore 검증 버전
모듈 계약 버전
미적용 SQL 여부
```

## 6. 필요한 구현 보완

### 1단계: 최초 설치 배포본 제공

- GitHub Releases에 `toycore-minimal`, `toycore-standard`, `toycore-ops` zip을 함께 제공
- README의 빠른 시작은 `toycore-standard.zip` 기준으로 안내
- `minimal`은 "작게 시작하는 고급/개발자 선택지"로 설명
- `standard/ops` 패키지에 포함된 모듈 목록과 각 모듈 버전을 release note에 표기
- 배포 패키지 생성 시 포함 모듈의 `module.php version` 목록을 manifest로 남김

### 2단계: zip 업로드 설치/업데이트

- `/admin/modules`에 모듈 zip 업로드 기능 추가
- 업로드 파일 크기와 확장자 제한
- 임시 디렉터리 압축 해제
- `{module_key}/`와 `module/` 두 구조를 모두 인식하되 최종 설치는 `modules/{module_key}/`로 통일
- 기존 모듈 덮어쓰기 전 백업 디렉터리 생성
- 파일 교체 후 설치/업데이트 안내 표시

### 3단계: 버전 차이와 업데이트 안내 강화

- 설치 버전과 코드 버전이 다르면 관리자 화면에 표시
- 코드 버전이 더 높고 미적용 SQL이 있으면 `/admin/updates`로 안내
- SQL이 없는 파일 전용 업데이트도 운영자가 확인 후 `toy_modules.version`을 코드 버전으로 맞출 수 있게 처리
- 코드 버전이 설치 버전보다 낮으면 downgrade 경고

### 4단계: 공식 release zip 다운로드

- 공식 모듈 registry 형식 정의
- `zip_url`, `checksum`, `version`, `min_toycore_version` 검증
- 다운로드 실패, checksum 불일치, 구조 불일치 시 설치 중단
- 다운로드와 설치 감사 로그 기록

### 5단계: public repository 가져오기

- owner 전용 고급 UI로 제공
- 허용 repository 패턴 또는 registry 등록 repository만 허용
- tag/ref 선택 기능 제공
- GitHub archive zip 다운로드 후 zip 업로드와 같은 검증 함수 사용

## 7. 금지하는 방향

- 설치 화면에서 입력받은 임의 repository URL을 바로 clone해서 설치
- clone한 PHP 파일을 검증 전에 include
- 파일 교체와 DB SQL 실행을 한 요청에서 묶어 자동 처리
- `main` 브랜치 최신 상태를 운영 설치 기준으로 삼기
- 같은 version의 update SQL을 배포 후 조용히 수정
- 실패 marker 없이 파일 일부만 교체된 상태를 방치

## 8. 현재 상태와 다음 작업

현재 상태:

- 선택 모듈은 별도 리포지토리에서 관리한다.
- 각 모듈 리포지토리는 `module/` 구조와 `package-module` 스크립트를 가진다.
- toycore 배포 패키지는 `minimal`, `standard`, `ops`로 나눌 수 있다.
- 배포 패키지는 포함 모듈과 버전을 `distribution-manifest.json`에 남긴다.
- `.tools/bin/check-distributions.php`로 생성된 배포 패키지의 manifest, 포함 모듈 버전, 설치 화면 선택 모듈 구성을 검증할 수 있다.
- 공식 모듈 registry는 `docs/module-index.json`에 둔다.
- 운영 환경에서 repository archive를 반영하려면 registry의 `repository_refs`에 40자 commit SHA와 sha256 checksum을 함께 등록한다. Branch나 tag ref는 움직일 수 있으므로 개발/스테이징용 고급 경로로만 취급한다.
- 관리자 모듈 화면은 설치 버전, 코드 버전, Toycore 호환 정보를 표시한다.
- `/admin/modules`에서 owner가 모듈 zip을 업로드해 `modules/{module_key}` 파일을 반영할 수 있다.
- `/admin/modules`에서 registry에 URL과 checksum이 등록된 공식 release zip을 다운로드해 같은 검증 흐름으로 반영할 수 있다.
- `/admin/modules`에서 registry에 등록된 공식 GitHub repository의 archive zip을 ref 기준으로 다운로드해 같은 검증 흐름으로 반영할 수 있으며, 이 경로는 고급 UI로 접어 둔다.
- `.tools/bin/update-module-index`로 모듈 zip 디렉터리의 sha256 checksum을 계산해 `docs/module-index.json`을 갱신할 수 있다.
- `.tools/bin/update-module-index --repository-refs`로 공식 모듈 리포지토리의 현재 commit SHA와 GitHub archive zip checksum을 `repository_refs`에 기록할 수 있다.
- `.tools/bin/publish-module-release`로 공식 모듈 zip 수집, registry checksum 갱신, GitHub Release 업로드를 한 흐름으로 처리할 수 있으며, `TOYCORE_UPDATE_REPOSITORY_REFS=1`이면 archive checksum도 함께 갱신한다.
- 기존 모듈 파일을 교체할 때는 `storage/module-backups`에 이전 디렉터리를 보관한다.
- 코드 버전이 설치 버전보다 높고 미적용 SQL이 없으면 파일 전용 업데이트 버전을 관리자 화면에서 반영할 수 있다.
- 업로드 zip은 `{module_key}/module.php`, `module/module.php`, 한 단계 아래 `module/` 디렉터리를 둔 리포지토리 zip 구조를 인식한다.
- 설치 버전보다 낮은 코드 버전은 기본적으로 차단하고, owner의 명시적 허용이 있을 때만 덮어쓴다.
- `/admin/modules` action은 권한 확인과 view 연결만 담당하고, POST 처리와 목록 데이터 조립은 관리자 helper로 분리되어 있다.

## 9. 실패 후 운영자가 확인할 순서

모듈 파일 반영 또는 설치가 실패하면 다음 순서로 확인한다.

```text
1. 관리자 화면의 오류 메시지
2. storage/logs/error.log
3. toy_audit_logs의 module.source.* 또는 module.installed 이벤트
4. storage/module-upload 아래 남은 임시 작업 디렉터리 여부
5. storage/module-backups 아래 백업 디렉터리 여부
6. toy_modules.status가 failed 또는 installing으로 남았는지 확인
```

파일 교체 중 실패한 경우 기존 모듈 디렉터리는 가능한 한 백업에서 되돌린다. 백업 디렉터리가 남아 있고 `modules/{module_key}`가 비어 있거나 손상된 상태라면, 백업을 복구한 뒤 같은 zip을 재시도하지 말고 실패 원인을 먼저 확인한다.

모듈 설치 SQL 실행 중 실패한 경우 `toy_modules.status`는 `failed`로 남을 수 있다. 이 상태에서는 재설치를 먼저 실행하고, 운영자가 임의로 `enabled`로 바꾸지 않는다. DDL 일부가 이미 적용되었을 수 있으므로 실패 SQL을 그대로 재실행하기 전에 DB 상태를 확인한다.

관리자 대시보드는 `storage/update-failed.json`과 `storage/module-backups` 요약을 표시한다. `/admin/modules`에서 파일 교체를 수행한 뒤에는 `/admin`에서 최근 백업 존재 여부를 확인하고, `/admin/updates`에서 미적용 SQL을 확인한다.

오래된 모듈 백업은 `/admin/retention`에서 보관 기간 기준으로 정리한다. 백업 정리 전에는 최근 파일 교체가 정상 동작하는지 확인하고, 복구가 필요한 백업은 별도 위치에 보관한 뒤 정리를 실행한다.

다음 작업:

```text
1. 실제 릴리스 태그에서 publish-module-release 실행 후 standard/ops 웹 설치 검증
```
