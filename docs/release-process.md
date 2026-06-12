# 릴리스 절차

이 문서는 산란 릴리스 전후의 최소 절차를 정리한다. 산란 릴리스는 현재 저장소의 파일을 기준으로 만들며, 외부 모듈 저장소 checkout을 전제로 하지 않는다.

## 1. 준비

- `main` 브랜치가 배포할 커밋을 가리키는지 확인한다.
- `core/version.php`의 본체 버전과 `SR_MODULE_CONTRACT_VERSION`을 확인한다.
- 릴리스 태그를 만들었다면 태그가 배포할 commit SHA를 가리키는지 확인한다.
- 배포할 모듈이 있다면 saanraan.git 안의 `modules/{module_key}` 폴더에 포함되어 있는지 확인한다.
- 각 모듈의 `module.php` version, `saanraan.min_version`, `saanraan.module_contract`, `contracts.provides`를 확인한다. `saanraan.min_version`은 현재 본체 버전이 충족해야 하고, `saanraan.module_contract`는 현재 `SR_MODULE_CONTRACT_VERSION`과 같아야 하며, 실제 계약 파일과 `contracts.provides` 선언이 일치해야 한다.
- 1.0 릴리스 후보라면 [1.0 범위 잠금 기준](1.0-scope.md)의 포함/제외 범위를 확인하고, 계획 문서 단계의 본인확인, 회원 마이그레이션, 결제 플러그인이 안정화 범위에 섞이지 않았는지 확인한다.
- 릴리스 설명이 [산란 포지셔닝 기준](positioning.md)에 맞는지 확인하고, 대형 CMS 생태계를 대체한다는 식의 과장 문구를 피한다.
- Wiki 구현 명세 정리 전이라면 [1.0 전 구현 스냅샷](implementation-snapshot.md)이 현재 번들 모듈, 대표 경로, 주요 DB 테이블과 맞는지 확인한다.
- 외부 라이브러리나 vendored asset이 포함되면 [외부 의존성 배치 기준](dependency-policy.md)에 따라 버전, 출처, 라이선스, cache 쓰기 경로를 확인한다.
- 기본 점검을 통과시키고, [검증 상태와 증거 기준](verification-status.md)에 맞춰 필요한 로컬/스테이징 HTTP 스모크 점검을 실행한다.
- 검증 결과는 [릴리스 검증 기록 템플릿](release-verification-template.md)을 기준으로 `docs/records/`에 남긴다.

```sh
./.tools/bin/check
php .tools/bin/release-preflight.php
php .tools/bin/release-installed-gate-status.php
```

## 2. 릴리스 산출물

Git을 사용할 수 있는 환경은 릴리스 태그나 검증된 commit SHA를 기준으로 배포한다. Git을 사용할 수 없는 공유호스팅에는 GitHub 릴리스의 source zip 또는 maintainer가 현재 저장소 파일로 만든 단일 zip을 업로드한다.

저장소는 하나로 유지하더라도 릴리스 산출물은 전체 배포용과 모듈별 배포용을 함께 제공할 수 있다.

```text
saanraan-full-2026.05.001.zip
point-2026.05.001.zip
banner-2026.05.001.zip
```

Git을 사용하는 운영자는 전체 브랜치를 pull/merge하지 않고 릴리스 태그나 원격 브랜치에서 `modules/{module_key}` 경로만 checkout해 특정 모듈만 업데이트할 수 있다. 이 경우에도 산란은 모듈 소스의 원격 출처를 관리하지 않으며, 최종 배치된 모듈 폴더와 DB 업데이트 상태만 확인한다.

릴리스 zip은 현재 저장소의 파일 구조를 보존해야 한다.

포함 기준:

- `index.php`, `core/`, `database/`, `modules/`
- `.htaccess`
- `config/` 디렉터리
- `assets/`, `lang/`, `layouts/`
- `docs/`, `examples/`, `README.md`, `LICENSE`
- nginx 배포를 안내할 때는 `docs/deployment/nginx-saanraan.conf`
- 배포자가 릴리스 검증에 쓰는 `.tools/` 파일
- 릴리스에 포함하기로 결정한 `vendor/` 파일 또는 선택 모듈 내부 vendor 파일과 라이선스 문서
- HTML Purifier를 기본 sanitizer 선행 엔진으로 포함하는 릴리스라면 `modules/htmlpurifier/vendor/ezyang/htmlpurifier/`, `modules/htmlpurifier/vendor/autoload.php`, `modules/htmlpurifier/DEPENDENCY.md`

제외 기준:

- `.git/`, `.claude/`, 에디터 설정, 로컬 임시 파일
- 릴리스에 포함하기로 결정하지 않은 개발용 Composer cache, 임시 vendor, package manager 작업 파일
- `.tools/browser-qa/node_modules/`, `.tools/browser-qa/results/`, `.tools/browser-qa/test-results/`, `.tools/browser-qa/package-lock.json`
- `config/config.php`, `config/config.php.tmp`, `config/config-*.tmp.php`, `config/*.bak`, `config/*.backup`, `config/*.old`, `config/*.orig`, `.env`, `.env.*`
- `storage/installed.lock`
- `storage/logs/`, `storage/module-backups/`, `storage/update-failed.json`
- DB dump, SQLite/DB 파일, 운영 백업, 업로드 파일, SSH key, package registry token 파일, 비밀값이 들어 있는 파일. 이 기준은 루트뿐 아니라 모듈 내부 파일에도 적용한다.

Apache 배포에서는 루트 `.htaccess`가 함께 올라가야 한다. nginx 배포에서는 [nginx 샘플 설정](deployment/nginx-saanraan.conf)을 기준으로 `server_name`, `root`, `fastcgi_pass`를 운영 환경에 맞게 바꾼 뒤 같은 보호 규칙을 서버 설정에 반영한다. `.tools/`나 `docs/`를 운영 서버에 함께 올리는 경우에도 [배포 보호 기준](deployment-protection.md)에 따라 웹 직접 접근을 차단해야 한다.

릴리스 zip을 직접 만들었다면 SHA-256 checksum을 함께 기록한다. 직접 제작 zip은 `php .tools/bin/release-package-dry-run.php --manifest` 출력의 파일 수와 `manifest-sha256`도 함께 남겨 zip 생성 전 후보 파일 집합을 고정한다. dry-run은 루트 `vendor/`, `dist/`, `storage/`, 비밀 파일, 백업/임시 파일, DB dump, SQLite/DB 파일, SSH key, package registry token 파일이 후보에 들어오면 실패해야 한다. 모듈 내부 vendor처럼 릴리스에 포함하기로 한 vendored 의존성은 허용하되, 모듈 내부의 `.env`, dump, key 파일은 제외한다. GitHub source zip을 그대로 사용하는 경우에는 태그와 commit SHA를 릴리스 노트에 기록한다.

릴리스 전 요약값은 다음 명령으로 한 번에 확인할 수 있다. 이 출력은 Purifier 로드 상태, HTML Purifier 버전, 모듈 내부 autoload 존재, cache 경로/쓰기 가능 여부, release package dry-run 파일 수, manifest hash를 릴리스 검증 기록에 옮겨 적기 위한 read-only preflight다.

```sh
php .tools/bin/release-preflight.php
```

설치 DB가 필요한 검증은 preflight가 대체하지 않는다. 릴리스 후보에서는 `php .tools/bin/release-installed-gate-status.php`를 실행해 새 설치/업데이트, read-only CLI 게이트, 관리자 read-only 화면, 인증/퀴즈/자산 mutation smoke, 개인정보 export/cleanup, CKEditor browser smoke, 성능 수동 점검의 현재 상태와 미실행 사유를 기록한다. 현재 CLI 사용자가 공유호스팅 보안 권한 때문에 `config/config.php`를 읽지 못하면 권한을 넓히지 말고 웹 서버 사용자 또는 로컬/staging 전용 실행 사용자로 다시 확인한다.

## 3. 모듈 zip 확인

산란 릴리스는 모듈 소스의 출처를 관리하지 않는다. 별도 배포가 필요한 모듈은 제작자가 자기 환경에서 zip을 만들고, 운영자는 `/admin/modules`에서 업로드하거나 FTP/SFTP로 `modules/{module_key}`에 배치한다. Git을 사용하는 운영자는 같은 내용을 `git checkout <tag-or-ref> -- modules/{module_key}`로 배치할 수 있다.

확인 기준:

- zip 압축 해제 시 `{module_key}/module.php` 구조가 나오는가
- 같은 모듈 key를 유지하는 단독 배포물이라면 `{module_key}/` 밖의 본체/다른 모듈/문서 파일을 포함하지 않았는가
- `module.php` version이 배포하려는 버전과 맞는가
- `module.php`의 `saanraan.min_version`과 `saanraan.module_contract`가 배포 대상 본체와 맞는가
- `install.sql`과 필요한 `updates/` 파일이 포함되어 있는가
- 같은 버전의 update SQL을 이미 배포한 적이 있다면 내용이 바뀌지 않았는가
- zip의 SHA-256 checksum을 릴리스 노트나 운영 기록에 남겼는가

이 외의 모듈 파일 배치, 교체, 관리자 zip 업로드 검증 기준은 [모듈 배치와 업데이트 기준](module-update-policy.md)을 따른다.

## 4. 릴리스 노트

릴리스 노트에는 다음을 포함한다.

- 본체 버전
- 태그와 commit SHA
- 릴리스 zip checksum 또는 GitHub source zip 사용 여부
- 직접 제작 zip이라면 `release-package-dry-run.php --manifest`의 파일 수와 `manifest-sha256`
- `release-preflight.php`의 Purifier 로드 상태, Purifier 버전, cache 경로/쓰기 가능 여부, release package 파일 수와 manifest hash
- `release-installed-gate-status.php`의 필수 설치 DB 게이트 상태표와 미실행 사유
- 포함된 모듈 목록과 각 모듈 버전
- 포함 모듈의 산란 최소 버전과 모듈 계약 버전
- DB update SQL이 있는 모듈 목록
- 정적 점검, HTTP 스모크, 브라우저 수동 점검 결과
- 자산 모듈을 포함한 릴리스라면 read-only reconciliation 실행 결과 또는 미실행 사유
- 실행하지 못한 고위험 검증의 사유와 후속 보완 항목
- 수동 백업과 `/admin/updates` 실행 안내

## 5. 배포 후 확인

- 릴리스 zip으로 신규 설치가 가능한지 확인한다.
- `/admin/modules`에서 설치 버전과 코드 버전이 일치하는지 확인한다.
- `/admin/updates`에 미적용 SQL이 남아 있지 않은지 확인한다.
