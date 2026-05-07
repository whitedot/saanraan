# MODULE_NAME

이 저장소는 Toycore 외부 모듈 `MODULE_KEY`를 관리한다.

## 지원 버전

```text
Toycore 최소 버전: TOYCORE_VERSION
Toycore 검증 버전: TOYCORE_VERSION
모듈 계약 버전: MODULE_CONTRACT_VERSION
```

## 구조

```text
AGENTS.md
README.md
CHANGELOG.md
module/
- module.php
- install.sql
.tools/bin/package-module
```

Toycore에 업로드되는 실제 모듈 파일은 `module/` 아래에 둔다.

## 구현 규칙과 AI 보조 작업

구현 규칙은 이 저장소의 `AGENTS.md`를 기준으로 한다. `AGENTS.md`는 사람이 직접 구현할 때의 체크 기준이면서, AI 코딩 도구에 작업을 맡길 때도 같은 기준으로 쓰는 파일이다.

AI 보조 작업 예:

- `AGENTS.md 기준으로 module/paths.php와 관리자 action을 추가해줘.`
- `AGENTS.md와 module-checklist.md 기준으로 릴리스 전 위험을 점검해줘.`
- `AGENTS.md 기준으로 output-slots.php가 외부 CI에서 실패하지 않는지 확인해줘.`

## 로컬 점검

zip을 만들기 전에 Toycore가 이 모듈을 읽을 수 있는지 확인한다.

점검은 Toycore Git 저장소와 이 모듈 Git 저장소가 같은 상위 디렉터리에 있다는 가정에 기대지 않는다. 필요한 것은 이 모듈 저장소의 `module/` 경로와 점검에 사용할 Toycore 저장소 경로다.

Toycore 저장소가 없다면 원하는 위치에 clone하고, 모듈이 지원할 Toycore ref로 맞춘다.

```sh
git clone https://github.com/whitedot/toycore.git /path/to/toycore
cd /path/to/toycore
git checkout TOYCORE_REF
```

그 다음 이 모듈 저장소 루트에서 Toycore 저장소 경로를 지정해 점검한다.

```sh
cd /path/to/MODULE_REPOSITORY
TOYCORE_REPO=/path/to/toycore
php "$TOYCORE_REPO/.tools/bin/check-external-module.php" module MODULE_KEY
```

Windows PowerShell에서는 다음처럼 실행한다.

```powershell
Set-Location C:\path\to\MODULE_REPOSITORY
$env:TOYCORE_REPO = 'C:\path\to\toycore'
php "$env:TOYCORE_REPO\.tools\bin\check-external-module.php" module MODULE_KEY
```

이 점검은 `module.php`, `install.sql`, Toycore 계약 버전, PHP 문법, `paths.php` action 파일, 관리자 메뉴 route, 주요 계약 파일 반환 구조를 확인한다.

## zip 구조

릴리스 zip은 압축을 풀었을 때 바로 모듈 키 디렉터리가 나오게 만든다.

```text
MODULE_KEY-2026.05.001.zip
-> MODULE_KEY/
   - module.php
   - install.sql
```

스캐폴딩 도구가 만든 저장소라면 다음 명령으로 zip을 만들 수 있다.

```sh
./.tools/bin/package-module 2026.05.001
```

Windows처럼 실행 권한 개념이 다른 환경에서는 다음처럼 실행해도 된다.

```sh
php .tools/bin/package-module 2026.05.001
```

## Toycore 관리자 업로드

```text
1. /admin/modules 이동
2. 모듈 zip 업로드
3. owner 비밀번호 입력
4. 설치 또는 파일 교체
5. /admin/updates에서 미적용 SQL 확인
```

## 자동 점검

GitHub Actions를 사용하면 로컬 점검 명령을 push할 때 자동으로 실행할 수 있다. 처음에는 몰라도 된다.

자세한 내용은 Toycore 본체 문서를 본다.

- `docs/external-module-quickstart.md`
- `docs/module-checklist.md`
- `docs/module-ci-quickstart.md`
- `docs/module-guide.md`
