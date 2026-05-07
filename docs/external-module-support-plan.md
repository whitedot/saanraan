# 외부 모듈 제작자 지원 계획

이 문서는 외부 모듈 제작자가 Toycore 내부 문서를 복사해 관리하지 않고, 원본 문서와 점검 도구를 기준으로 모듈을 만들 수 있게 하기 위한 작업 계획과 진행 상태를 기록한다.

작업이 중단되면 이 문서의 진행 상태를 보고 다음 미완료 항목부터 이어간다.

## 목표

외부 모듈 제작 흐름은 세 단계로 나눈다.

```text
기본 경로:
폴더 생성 -> module.php/install.sql 작성 -> 로컬 점검 -> zip 생성 -> 관리자 업로드

프로젝트 도구 경로:
create-external-module.php -> README/AGENTS/package-module 생성 -> 로컬 점검 -> zip 생성

자동 점검 경로:
Git 저장소 push -> GitHub Actions가 모듈 점검 자동 실행

배포 경로:
모듈 zip 업로드 또는 modules/{module_key} 폴더 배치
```

기본 안내에서는 Git 저장소나 CI를 먼저 요구하지 않는다. CI는 GitHub가 로컬 점검 명령을 대신 실행해 주는 자동 점검으로 설명한다.

## 진행 상태

```text
1차 문서 진입점 정리: 완료
2차 스캐폴딩 도구: 완료
3차 샘플 모듈 계약 정리: 완료
```

## 1차 작업

상태: 완료

완료 항목:

- `docs/external-module-quickstart.md` 추가
- `docs/templates/external-module-README.md` 추가
- `docs/module-checklist.md` 추가
- `docs/module-ci-quickstart.md` 추가
- `README.md`에 외부 모듈 제작 진입점 추가
- `docs/documentation-index.md`에 새 문서 추가
- `docs/module-guide.md` 앞부분에 빠른 시작/체크리스트 연결
- `docs/module-guide.md`의 CI 안내를 로컬 점검 우선 흐름으로 조정

## 2차 작업

상태: 완료

목표:

- 반복 제작이나 공개 배포를 위한 외부 모듈 프로젝트 폴더 생성 도구를 추가한다.
- 생성 결과를 전체 검사에서 검증한다.

예정 파일:

```text
.tools/bin/create-external-module.php
.tools/bin/check-create-external-module.php
```

완료 항목:

- `create-external-module.php`로 `AGENTS.md`, README, CHANGELOG, `.tools/bin/package-module`, `module/module.php`, `module/install.sql` 생성
- `--with-ci` 옵션으로 GitHub Actions 파일 생성 가능
- 기존 파일을 덮어쓰지 않도록 빈 target 디렉터리만 허용
- 생성 결과를 `check-external-module.php`로 검증
- `check-create-external-module.php`를 전체 검사에 연결

기본 사용 예:

```sh
php .tools/bin/create-external-module.php banner /path/to/banner-module
```

초기 생성 구조:

```text
banner-module/
- README.md
- AGENTS.md
- CHANGELOG.md
- .tools/
  - bin/
    - package-module
- module/
  - module.php
  - install.sql
```

`.github/workflows/check.yml`은 기본 생성하지 않는다. 자동 점검이 필요한 개발자는 `--with-ci` 옵션으로 생성한다. CI가 없어도 프로젝트 루트에서 Toycore 소스 경로를 명시해 같은 기준을 직접 점검할 수 있다.

```sh
TOYCORE=/path/to/toycore
php "$TOYCORE/.tools/bin/check-external-module.php" module banner
```

Toycore 소스 루트에서 실행한다면 `php .tools/bin/check-external-module.php /path/to/banner-module/module banner`처럼 모듈 프로젝트의 `module/` 디렉터리를 절대 경로 또는 명시적 상대 경로로 지정한다.

생성된 `.tools/bin/package-module`은 모듈 프로젝트의 `module/` 디렉터리를 `{module_key}-{version}.zip`으로 묶는다. 이 zip은 Toycore 관리자 모듈 화면에서 업로드할 수 있다.

처음 구현에서는 최소 구조와 zip 패키징까지만 생성한다. 관리자 화면, public route, output slot 같은 선택 파일은 이후 옵션으로 확장한다.

## 3차 작업

상태: 완료

목표:

- 공식 샘플 모듈을 현재 모듈 계약 기준으로 맞춘다.
- 샘플 모듈을 전체 검사에서 외부 모듈 기준으로 검증한다.

확인 기준:

- `examples/sample_module/module.php`에 `toycore.min_version` 있음
- `examples/sample_module/module.php`에 `toycore.tested_with` 있음
- `examples/sample_module/module.php`에 `toycore.module_contract` 있음
- `install.sql` 있음
- `admin-menu.php`가 있으면 `paths.php`도 있음
- `php .tools/bin/check-external-module.php examples/sample_module sample_notice` 통과

완료 항목:

- 샘플 모듈 `module.php`에 현재 Toycore 계약 메타데이터 추가
- 외부 모듈 검사에서 계약 파일의 array/callable 반환 규칙 반영
- 샘플 모듈을 전체 검사에 연결

## 운영 원칙

- 외부 모듈 프로젝트는 Toycore 문서를 복사해 오래 보관하지 않는다.
- 외부 모듈 README에는 짧은 사용법과 Toycore 문서 링크를 둔다.
- 자세한 계약 설명은 Toycore 본체 문서를 원본으로 둔다.
- 자동 점검은 선택 경로로 설명한다.
- 계약이 바뀌면 `TOY_MODULE_CONTRACT_VERSION`, 문서, CI 템플릿, 배포 대상 모듈 메타데이터를 함께 갱신한다.
