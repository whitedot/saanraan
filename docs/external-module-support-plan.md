# 외부 모듈 제작자 지원 계획

이 문서는 외부 모듈 제작자가 Toycore 내부 문서를 복사해 관리하지 않고, 원본 문서와 점검 도구를 기준으로 모듈을 만들 수 있게 하기 위한 작업 계획과 진행 상태를 기록한다.

작업이 중단되면 이 문서의 진행 상태를 보고 다음 미완료 항목부터 이어간다.

## 목표

외부 모듈 제작 흐름은 세 단계로 나눈다.

```text
기본 경로:
폴더 생성 -> module.php/install.sql 작성 -> 로컬 점검 -> zip 생성 -> 관리자 업로드

자동 점검 경로:
GitHub 저장소 push -> GitHub Actions가 모듈 점검 자동 실행

공식 릴리스 경로:
코어 maintainer가 공식 모듈 ref/checksum/registry/배포 패키지 조립 관리
```

기본 안내에서는 CI를 먼저 요구하지 않는다. CI는 GitHub가 로컬 점검 명령을 대신 실행해 주는 자동 점검으로 설명한다.

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

- 외부 모듈 저장소 기본 구조를 생성하는 도구를 추가한다.
- 생성 결과를 전체 검사에서 검증한다.

예정 파일:

```text
.tools/bin/create-external-module.php
.tools/bin/check-create-external-module.php
```

완료 항목:

- `create-external-module.php`로 README, CHANGELOG, `module/module.php`, `module/install.sql`, `.github/workflows/check.yml` 생성
- 기존 파일을 덮어쓰지 않도록 빈 target 디렉터리만 허용
- 생성 결과를 `check-external-module.php`로 검증
- `check-create-external-module.php`를 전체 검사에 연결

기본 사용 예:

```sh
php .tools/bin/create-external-module.php banner ../toycore-module-banner
```

초기 생성 구조:

```text
toycore-module-banner/
- README.md
- CHANGELOG.md
- module/
  - module.php
  - install.sql
- .github/
  - workflows/
    - check.yml
```

처음 구현에서는 최소 구조만 생성한다. 관리자 화면, public route, output slot 같은 선택 파일은 이후 옵션으로 확장한다.

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

- 외부 모듈 저장소는 Toycore 문서를 복사해 오래 보관하지 않는다.
- 외부 모듈 README에는 짧은 사용법과 Toycore 문서 링크를 둔다.
- 자세한 계약 설명은 Toycore 본체 문서를 원본으로 둔다.
- 자동 점검은 선택 경로로 설명한다.
- 계약이 바뀌면 `TOY_MODULE_CONTRACT_VERSION`, 문서, CI 템플릿, 공식 모듈 메타데이터를 함께 갱신한다.
