# 릴리스 절차

이 문서는 Toycore 배포 zip을 만들 때의 최소 절차를 정리한다. Toycore 릴리스는 현재 저장소의 파일을 기준으로 만들며, 릴리스 과정에서 다른 모듈 저장소를 checkout하거나 별도 색인 파일을 갱신하지 않는다.

## 1. 준비

- `main` 브랜치가 배포할 커밋을 가리키는지 확인한다.
- 배포할 모듈이 있다면 toycore.git 안의 `modules/{module_key}` 폴더에 포함되어 있는지 확인한다.
- 각 모듈의 `module.php` version, `toycore.min_version`, `toycore.module_contract`를 확인한다.
- 공식 배포 조합은 `docs/distributions.json`에서 확인하고, `php .tools/bin/check-distribution-policy.php`로 검증한다.

## 2. 배포 패키지 생성

GitHub Actions를 사용할 수 있으면 `Release packages` workflow를 수동 실행한다. 이 workflow는 전체 점검과 `package-distributions`, `check-distributions`를 실행한 뒤 `dist/toycore-*` 산출물을 artifact로 업로드한다.

로컬 maintainer 환경에서는 다음 명령을 사용한다.

```sh
./.tools/bin/package-distributions 2026.05.001
```

생성 결과:

```text
dist/toycore-minimal
dist/toycore-standard
dist/toycore-ops
dist/toycore-minimal-2026.05.001.zip
dist/toycore-standard-2026.05.001.zip
dist/toycore-ops-2026.05.001.zip
```

각 배포 디렉터리의 `distribution-manifest.json`에서 포함 모듈, 모듈 버전, Toycore 최소 버전, 모듈 계약 버전을 확인한다.

배포 디렉터리, manifest, 설치 화면 선택 모듈 구성의 기본 구조는 다음 명령으로 검증한다.

```sh
php .tools/bin/check-distribution-policy.php
php .tools/bin/check-distributions.php 2026.05.001
```

## 3. 모듈 zip 확인

Toycore 릴리스는 모듈 소스의 출처를 관리하지 않는다. 별도 배포가 필요한 모듈은 제작자가 자기 환경에서 zip을 만들고, 운영자는 `/admin/modules`에서 업로드하거나 FTP/SFTP로 `modules/{module_key}`에 배치한다.

확인 기준:

- zip 압축 해제 시 `{module_key}/module.php` 구조가 나오는가
- `module.php` version이 배포하려는 버전과 맞는가
- `install.sql`과 필요한 `updates/` 파일이 포함되어 있는가
- 같은 버전의 update SQL을 이미 배포한 적이 있다면 내용이 바뀌지 않았는가

## 4. 릴리스 노트

릴리스 노트에는 다음을 포함한다.

- 본체 버전
- `minimal`, `standard`, `ops` 패키지 차이
- 각 패키지의 `distribution-manifest.json` 내용
- 포함 모듈 버전
- 포함 모듈의 Toycore 최소 버전과 모듈 계약 버전
- DB update SQL이 있는 모듈 목록
- 수동 백업과 `/admin/updates` 실행 안내

## 5. 배포 후 확인

- 릴리스 zip으로 신규 설치가 가능한지 확인한다.
- `php .tools/bin/check-distributions.php 2026.05.001`로 `minimal`, `standard`, `ops` manifest, 포함 모듈 버전, Toycore 최소 버전, 모듈 계약 버전, 설치 화면 선택 모듈 구성을 확인한다.
- `/admin/modules`에서 설치 버전과 코드 버전이 일치하는지 확인한다.
- `/admin/updates`에 미적용 SQL이 남아 있지 않은지 확인한다.
