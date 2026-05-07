# 릴리스 절차

이 문서는 Toycore 본체와 공식 모듈 zip을 릴리스할 때의 최소 절차를 정리한다.

## 1. 준비

- `main` 브랜치가 배포할 커밋을 가리키는지 확인한다.
- 외부 모듈 리포지토리가 toycore.git과 같은 상위 디렉터리에 있는지 확인한다.
- 다른 위치를 쓰면 `TOYCORE_MODULE_REPO_ROOT`에 모듈 리포지토리 상위 디렉터리를 지정한다.
- 각 모듈의 `module/module.php` version과 `CHANGELOG.md`를 확인한다.
- 공식 모듈 registry 구조는 `php .tools/bin/check-module-index.php`로 확인한다.
- 공식 배포 조합은 `docs/distributions.json`에서 확인하고, `php .tools/bin/check-distribution-policy.php`로 검증한다.

## 2. 본체 배포 패키지 생성

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

각 배포 디렉터리의 `distribution-manifest.json`에서 포함 모듈과 버전을 확인한다.

배포 디렉터리, manifest, 설치 화면 선택 모듈 구성의 기본 구조는 다음 명령으로 검증한다.

```sh
php .tools/bin/check-distribution-policy.php
php .tools/bin/check-distributions.php 2026.05.001
```

## 3. 공식 모듈 zip 확인

각 모듈 리포지토리에서 설치용 zip을 만든다.

```sh
./.tools/bin/package-module
```

확인 기준:

- zip 압축 해제 시 `{module_key}/module.php` 구조가 나오는가
- `module.php` version이 릴리스 버전과 맞는가
- `install.sql`과 필요한 `updates/` 파일이 포함되어 있는가
- 같은 버전의 update SQL을 이미 배포한 적이 있다면 내용이 바뀌지 않았는가

모듈 zip 생성, `docs/module-index.json` 갱신, GitHub Release 업로드를 한 번에 처리하려면 릴리스 담당자 환경에서 다음 명령을 사용한다.

```sh
./.tools/bin/publish-module-release 2026.05.001
```

기본 업로드 대상은 `whitedot/toycore-module-releases`의 `v2026.05.001` release다. 다른 모듈 리포지토리 위치나 release 저장소를 쓰면 인자를 명시한다.

```sh
./.tools/bin/publish-module-release 2026.05.001 whitedot/toycore-module-releases /release/modules
```

이 도구는 `docs/module-index.json`에 등록된 모듈별 리포지토리에서 `.tools/bin/package-module`을 실행하고, 생성된 zip을 `dist/modules`에 모은 뒤 checksum을 계산한다. `gh` CLI가 있으면 release 생성과 asset upload까지 수행하고, 없으면 수동 업로드 명령을 출력한다.

## 4. Checksum 기록

공식 모듈 release zip을 업로드한 뒤 sha256 checksum을 계산한다.

```sh
sha256sum module-name.zip
```

모듈 zip 파일이 한 디렉터리에 모여 있다면 다음 명령으로 `docs/module-index.json`을 갱신한다.

```sh
./.tools/bin/update-module-index 2026.05.001 https://github.com/whitedot/toycore-module-releases/releases/download/v2026.05.001 dist/modules
```

이 명령은 `{module_key}-2026.05.001.zip` 파일을 찾고 sha256 checksum을 계산해 다음 값을 갱신한다.

```json
{
  "latest_version": "2026.05.001",
  "zip_url": "https://github.com/whitedot/toycore-module-example/releases/download/v2026.05.001/example-2026.05.001.zip",
  "checksum": "..."
}
```

URL과 checksum이 모두 채워진 항목만 `/admin/modules`의 공식 registry 다운로드 대상으로 사용된다.

운영 환경에서 repository archive 반영도 허용해야 한다면 같은 명령에 `--repository-refs`를 추가한다. 이 옵션은 공식 모듈 리포지토리의 현재 commit SHA를 읽고 GitHub codeload archive zip의 sha256 checksum을 계산해 `repository_refs`에 기록한다.

```sh
./.tools/bin/update-module-index 2026.05.001 https://github.com/whitedot/toycore-module-releases/releases/download/v2026.05.001 dist/modules --repository-refs ../
```

`publish-module-release`에서 같은 작업을 함께 실행하려면 다음처럼 환경 변수를 지정한다.

```sh
TOYCORE_UPDATE_REPOSITORY_REFS=1 ./.tools/bin/publish-module-release 2026.05.001
```

## 5. 릴리스 노트

릴리스 노트에는 다음을 포함한다.

- 본체 버전
- `minimal`, `standard`, `ops` 패키지 차이
- 각 패키지의 `distribution-manifest.json` 내용
- 포함 모듈 버전
- DB update SQL이 있는 모듈 목록
- 수동 백업과 `/admin/updates` 실행 안내

## 6. 배포 후 확인

- `toycore-standard.zip`으로 신규 설치가 가능한지 확인한다.
- `php .tools/bin/check-distributions.php 2026.05.001`로 `minimal`, `standard`, `ops` manifest, 포함 모듈 버전, 설치 화면 선택 모듈 구성을 확인한다.
- `/admin/modules`에서 설치 버전과 코드 버전이 일치하는지 확인한다.
- `/admin/updates`에 미적용 SQL이 남아 있지 않은지 확인한다.
- 공식 registry 항목의 checksum 불일치가 없는지 확인한다.
