# 모듈 저장 위치 기준

이 문서는 Toycore 모듈을 어디에 두고 어떻게 배포 대상으로 볼지 정리한다.

## 기준

Toycore에서 모듈의 기본 단위는 `modules/{module_key}` 디렉터리다.

Toycore는 별도의 외부 모듈 프로젝트 구조나 소스 관리 생명주기를 정의하지 않는다. 현재 배치된 모듈 폴더를 읽고 설치, 활성화, 업데이트 상태만 관리한다.

```text
Toycore가 아는 것:
- modules/{module_key}/module.php
- modules/{module_key}/install.sql
- modules/{module_key}/updates/*.sql
- toy_modules 설치/활성 상태
- toy_schema_versions 적용 상태

Toycore가 기본으로 관리하지 않는 것:
- 모듈 파일의 외부 출처
- 배포 산출물의 URL이나 ref
- 배포 산출물의 checksum 색인
- 모듈별 CI와 배포 workflow
```

## 저장소 정책

기본 모듈과 공식 배포에 포함할 모듈은 toycore.git 안의 `modules/` 아래에 둔다.

도메인 책임은 계속 모듈로 분리한다. 하지만 그 분리는 `modules/{module_key}` 폴더와 모듈 계약으로 표현한다.

```text
좋은 분리:
코어와 도메인 책임을 나눈다.
모듈이 자기 테이블, 화면, 설정, 업데이트를 가진다.

피할 분리:
모듈을 저장소, 릴리스, checksum 색인 단위의 별도 제품처럼 다룬다.
```

## 공식 배포 밖 모듈

공식 배포에 포함되지 않은 모듈 파일을 직접 준비하거나 따로 받은 경우에도 설치 기준은 같다.

```text
1. 모듈 폴더를 만든다.
2. zip으로 묶거나 FTP/파일 관리자로 업로드한다.
3. 최종 위치를 modules/{module_key}/로 맞춘다.
4. /admin/modules에서 설치한다.
5. /admin/updates에서 미적용 SQL을 확인한다.
```

Toycore 런타임으로 들어오는 산출물은 `modules/{module_key}` 폴더여야 한다. Toycore 본체는 파일의 출처나 배포 생명주기를 추적하지 않는다.

## 배포 기준

Toycore 본체는 저장소에 포함된 현재 파일을 그대로 릴리스 기준으로 본다. 운영 환경에는 Git checkout, 릴리스 zip, FTP/SFTP 중 가능한 방식으로 같은 파일 구조를 배치한다.

선택 모듈을 함께 배포하려면 해당 모듈의 런타임 파일이 toycore.git의 `modules/{module_key}` 아래에 있어야 한다. 별도 저장소나 외부 산출물에서 만든 모듈도 Toycore 안으로 들어온 뒤의 기준은 항상 `modules/{module_key}`다.

## 관리자 화면 책임

`/admin/modules`는 현재 배치된 모듈 폴더를 설치하고 상태를 바꾸는 화면이다.

허용:

- 설치 가능한 `modules/{module_key}` 폴더 표시
- 모듈 설치, 재설치, 활성화, 비활성화
- 모듈 zip 업로드 후 `modules/{module_key}`로 반영
- 기존 모듈 폴더 교체 전 백업
- 코드 버전과 설치 버전 차이 표시

기본 책임 밖:

- 외부 모듈 목록에서 내려받기
- 원격 archive 내려받기
- 원격 ref 선택
- 배포 산출물 checksum 색인 관리

## 판단 질문

새 모듈 관련 기능을 추가하기 전에 다음을 묻는다.

```text
이 기능은 현재 modules/{module_key} 폴더를 읽거나 배치하는 일을 돕는가?
아니면 모듈 소스의 출처와 릴리스 생명주기를 Toycore 안으로 들여오는가?
```

전자는 Toycore의 기본 책임에 가깝다. 후자는 기본 구현에 넣지 않는다.
